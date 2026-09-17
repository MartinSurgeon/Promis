<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Service;

use PDO;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Service\BaseService;
use Promis\Src\Execution\Auth\ExecutionAuthorizationGuard;
use Promis\Src\Execution\Auth\ExecutionPermissions;
use Promis\Src\Execution\Domain\DTO\AuditLogDTO;
use Promis\Src\Execution\Domain\DTO\RequisitionDTO;
use Promis\Src\Execution\Domain\DTO\WorkflowActionLogDTO;
use Promis\Src\Execution\Domain\DTO\WorkflowActionRequest;
use Promis\Src\Execution\Domain\DTO\WorkflowActionResult;
use Promis\Src\Execution\Domain\DTO\WorkflowDefinitionDTO;
use Promis\Src\Execution\Domain\DTO\WorkflowStepRuleDTO;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Domain\WorkflowTransition;
use Promis\Src\Execution\Exception\InvalidWorkflowTransitionException;
use Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException;
use Promis\Src\Execution\Exception\WorkflowException;
use Promis\Src\Execution\Repository\AuditLogRepository;
use Promis\Src\Execution\Repository\AuditLogRepositoryInterface;
use Promis\Src\Execution\Repository\RequisitionRepository;
use Promis\Src\Execution\Repository\RequisitionRepositoryInterface;
use Promis\Src\Execution\Repository\WorkflowActionLogRepository;
use Promis\Src\Execution\Repository\WorkflowActionLogRepositoryInterface;
use Promis\Src\Execution\Repository\WorkflowDefinitionRepository;
use Promis\Src\Execution\Repository\WorkflowDefinitionRepositoryInterface;
use Promis\Src\Execution\Repository\WorkflowStepRuleRepository;
use Promis\Src\Execution\Repository\WorkflowStepRuleRepositoryInterface;
use Promis\Core\Support\Logger;
use Promis\Src\Identity\Domain\Model\ResponsibilityCode;
use Promis\Src\Planning\Domain\Decimal;

/**
 * Application service coordinating Configurable Workflow Routing and Multi-Stage Approvals for Requisitions.
 */
class RequisitionWorkflowService extends BaseService implements RequisitionWorkflowServiceInterface
{
    private RequisitionRepositoryInterface $reqRepo;
    private WorkflowDefinitionRepositoryInterface $defRepo;
    private WorkflowStepRuleRepositoryInterface $stepRuleRepo;
    private WorkflowActionLogRepositoryInterface $actionLogRepo;
    private AuditLogRepositoryInterface $auditLogRepo;

    public function __construct(
        ?PDO $db = null,
        ?RequisitionRepositoryInterface $reqRepo = null,
        ?WorkflowDefinitionRepositoryInterface $defRepo = null,
        ?WorkflowStepRuleRepositoryInterface $stepRuleRepo = null,
        ?WorkflowActionLogRepositoryInterface $actionLogRepo = null,
        ?AuditLogRepositoryInterface $auditLogRepo = null
    ) {
        parent::__construct($db);
        $this->reqRepo = $reqRepo ?? new RequisitionRepository($this->db);
        $this->defRepo = $defRepo ?? new WorkflowDefinitionRepository($this->db);
        $this->stepRuleRepo = $stepRuleRepo ?? new WorkflowStepRuleRepository($this->db);
        $this->actionLogRepo = $actionLogRepo ?? new WorkflowActionLogRepository($this->db);
        $this->auditLogRepo = $auditLogRepo ?? new AuditLogRepository($this->db);
    }

    /**
     * Execute an atomic workflow action and state transition on a requisition.
     */
    public function executeAction(WorkflowActionRequest $request): WorkflowActionResult
    {
        // 1. Validate Input Parameters
        if ($request->requisitionId <= 0) {
            throw new ValidationException('A valid requisition ID is required.', [
                'requisition_id' => ['Requisition ID must be a positive integer.'],
            ]);
        }

        if ($request->actingUserId <= 0) {
            throw new ValidationException('A valid acting user ID is required.', [
                'acting_user_id' => ['User ID must be a positive integer.'],
            ]);
        }

        // 2. Validate Negative Action Mandatory Comments
        $comments = $request->comments !== null ? trim($request->comments) : null;
        if ($request->action->requiresComment() && ($comments === null || $comments === '')) {
            $actionLabel = $request->action->value;
            throw new ValidationException("A non-empty justification comment is mandatory when performing a {$actionLabel} action.", [
                'comments' => ["Comment is required for {$actionLabel} action."],
            ]);
        }

        // 3. Load Requisition Record
        $requisition = $this->reqRepo->findById($request->requisitionId);
        if ($requisition === null) {
            throw new ValidationException("Requisition #{$request->requisitionId} does not exist.", [
                'requisition_id' => ["Requisition #{$request->requisitionId} does not exist."],
            ]);
        }

        // 4. Server-Side Execution Authorization Guard (Entity Scoped)
        ExecutionAuthorizationGuard::requireCanExecuteAction($request->action, $requisition->planningEntityId);

        // 5. Execute Transition Inside Managed Transaction Boundary
        return $this->transaction(function () use ($requisition, $request, $comments) {
            // Lock the requisition row for update
            $lockedRow = $this->lockRequisition($requisition->id);
            if ($lockedRow === false) {
                throw new WorkflowException("Failed to acquire row lock on requisition #{$requisition->id}.");
            }

            $currentStatus = RequisitionStatus::tryFrom($lockedRow['status']);
            if ($currentStatus === null) {
                throw new WorkflowException("Requisition has unrecognized database status '{$lockedRow['status']}'.");
            }

            // Reject transitions from terminal state
            if ($currentStatus->isTerminal()) {
                throw new InvalidWorkflowTransitionException(
                    "Cannot execute action '{$request->action->value}' on requisition in terminal {$currentStatus->value} status.",
                    sourceStatus: $currentStatus->value,
                    action: $request->action->value
                );
            }

            // Resolve active workflow definition
            $definition = $this->resolveWorkflowDefinition($requisition->planningEntityId);

            // Resolve applicable step rules
            $stepRules = $this->stepRuleRepo->findByWorkflowDefinitionId($definition->id);
            if (empty($stepRules)) {
                throw new WorkflowException("Workflow definition '{$definition->workflowName}' has no configured step rules.");
            }

            // Resolve valid transition and target status
            $transition = $this->matchTransition($requisition, $currentStatus, $request->action, $stepRules);

            // Evaluate threshold bounds on the matched step rule if applicable
            if ($transition->stepRuleId !== null) {
                $matchedStep = $this->findStepRuleById($stepRules, $transition->stepRuleId);
                if ($matchedStep !== null) {
                    $this->validateThresholds($requisition->totalEstimatedCost, $matchedStep);
                    $this->validateRoleRequirement($request->actingUserId, $requisition->planningEntityId, $matchedStep);
                }
            }

            // Self-approval governance guard
            $isAuthor = ($requisition->createdBy === $request->actingUserId);
            if ($isAuthor && in_array($request->action, [WorkflowAction::ENDORSE, WorkflowAction::APPROVE, WorkflowAction::RETURN, WorkflowAction::REJECT], true)) {
                if ($this->userHasActiveResponsibilities($request->actingUserId)) {
                    if (!$this->userCanSelfApprove($request->actingUserId, $requisition->planningEntityId, $request->action)) {
                        throw new UnauthorizedWorkflowActionException("You are not allowed to perform this action.");
                    }
                }
            }

            // Negative action individual responsibility checks
            if ($request->action === WorkflowAction::RETURN && $this->userHasActiveResponsibilities($request->actingUserId)) {
                if (!$this->userHasResponsibility($request->actingUserId, ResponsibilityCode::RETURN_REQUESTS, $requisition->planningEntityId)) {
                    throw new UnauthorizedWorkflowActionException("You are not allowed to perform this action.");
                }
            }
            if ($request->action === WorkflowAction::REJECT && $this->userHasActiveResponsibilities($request->actingUserId)) {
                if (!$this->userHasResponsibility($request->actingUserId, ResponsibilityCode::REJECT_REQUESTS, $requisition->planningEntityId)) {
                    throw new UnauthorizedWorkflowActionException("You are not allowed to perform this action.");
                }
            }

            // Concurrency-Safe Conditional Status Update
            $now = date('Y-m-d H:i:s');
            $updated = $this->reqRepo->updateStatus(
                $requisition->id,
                $transition->targetStatus->value,
                $request->actingUserId,
                $currentStatus->value
            );

            if (!$updated) {
                throw new InvalidWorkflowTransitionException(
                    "Conditional status update failed for requisition #{$requisition->id}. Expected status '{$currentStatus->value}', but record was modified concurrently.",
                    sourceStatus: $currentStatus->value,
                    action: $request->action->value,
                    targetStatus: $transition->targetStatus->value
                );
            }

            // Persist append-only Workflow Action Log
            $actionLogId = $this->actionLogRepo->create([
                'document_type' => 'REQUISITION',
                'document_id' => $requisition->id,
                'step_id' => $transition->stepRuleId,
                'actor_user_id' => $request->actingUserId,
                'action' => $request->action->value,
                'pre_status' => $currentStatus->value,
                'post_status' => $transition->targetStatus->value,
                'comments' => $comments,
                'action_timestamp' => $now,
            ]);

            if ($actionLogId <= 0) {
                throw new WorkflowException("Failed to persist workflow action log record.");
            }

            // Persist Institutional Audit Log
            $previousStateJson = json_encode([
                'id' => $requisition->id,
                'status' => $currentStatus->value,
                'total_estimated_cost' => $requisition->totalEstimatedCost,
                'updated_at' => $requisition->updatedAt,
                'updated_by' => $requisition->updatedBy,
            ], JSON_THROW_ON_ERROR);

            $newStateJson = json_encode([
                'id' => $requisition->id,
                'status' => $transition->targetStatus->value,
                'total_estimated_cost' => $requisition->totalEstimatedCost,
                'updated_at' => $now,
                'updated_by' => $request->actingUserId,
            ], JSON_THROW_ON_ERROR);

            $auditLogId = $this->auditLogRepo->create([
                'event_timestamp' => $now,
                'actor_user_id' => $request->actingUserId,
                'planning_entity_id' => $requisition->planningEntityId,
                'action' => 'WORKFLOW_' . $request->action->value,
                'record_type' => 'requisitions',
                'record_id' => $requisition->id,
                'ip_address' => $request->ipAddress ?? '127.0.0.1',
                'user_agent' => $request->userAgent ?? 'PROMIS/CLI',
                'previous_state_json' => $previousStateJson,
                'new_state_json' => $newStateJson,
            ]);

            if ($auditLogId <= 0) {
                throw new WorkflowException("Failed to persist institutional audit log record.");
            }

            // Re-fetch hydrated updated requisition
            $updatedDto = $this->reqRepo->findById($requisition->id);
            if ($updatedDto === null) {
                throw new WorkflowException("Failed to re-fetch updated requisition #{$requisition->id}.");
            }

            return new WorkflowActionResult(
                requisition: $updatedDto,
                actionLogId: $actionLogId,
                auditLogId: $auditLogId,
                previousStatus: $currentStatus,
                newStatus: $transition->targetStatus,
                action: $request->action,
                stepRuleId: $transition->stepRuleId,
                stepName: $transition->stepName,
                timestamp: $now
            );
        });
    }

    /**
     * Resolve the active workflow definition governing a planning entity's requisitions.
     */
    public function resolveWorkflowDefinition(int $planningEntityId): WorkflowDefinitionDTO
    {
        $entityTypeId = $this->getEntityTypeIdForPlanningEntity($planningEntityId);

        // 1. Try to find active specific workflow definition for entity type
        if ($entityTypeId !== null) {
            $specificDefs = $this->defRepo->findActiveByDocumentAndEntityType('REQUISITION', $entityTypeId);
            if (count($specificDefs) > 1) {
                throw new WorkflowException("Ambiguous workflow configuration: Multiple active workflow definitions found for entity type #{$entityTypeId}.");
            }
            if (count($specificDefs) === 1) {
                return $specificDefs[0];
            }

            // Check if an inactive definition exists for this entity type
            $stmt = $this->db->prepare("SELECT id, is_active FROM workflow_definitions WHERE document_type = 'REQUISITION' AND entity_type_id = :tid");
            $stmt->execute(['tid' => $entityTypeId]);
            $inactiveCheck = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($inactiveCheck)) {
                foreach ($inactiveCheck as $row) {
                    if ((int)$row['is_active'] === 0) {
                        throw new WorkflowException("The workflow definition configured for this entity type is currently inactive.");
                    }
                }
            }
        }

        // 2. Try to find active global fallback workflow definition (entity_type_id IS NULL)
        $globalDefs = $this->defRepo->findActiveGlobalByDocument('REQUISITION');
        if (count($globalDefs) > 1) {
            throw new WorkflowException("Ambiguous workflow configuration: Multiple active global workflow definitions found.");
        }
        if (count($globalDefs) === 1) {
            return $globalDefs[0];
        }

        // Check if inactive global definition exists
        $stmt = $this->db->prepare("SELECT id, is_active FROM workflow_definitions WHERE document_type = 'REQUISITION' AND entity_type_id IS NULL");
        $stmt->execute();
        $inactiveGlobals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($inactiveGlobals)) {
            foreach ($inactiveGlobals as $row) {
                if ((int)$row['is_active'] === 0) {
                    throw new WorkflowException("The global workflow definition for requisitions is currently inactive.");
                }
            }
        }

        throw new WorkflowException("No active workflow definition configured for requisitions in entity #{$planningEntityId}.");
    }

    /**
     * Resolve the set of permitted workflow transitions available for a requisition in its current state.
     *
     * @return WorkflowTransition[]
     */
    public function resolvePermittedTransitions(int $requisitionId, int $actingUserId): array
    {
        if ($actingUserId <= 0) {
            return [];
        }

        $requisition = $this->reqRepo->findById($requisitionId);
        if ($requisition === null) {
            return [];
        }

        if ($requisition->status->isTerminal()) {
            return [];
        }

        try {
            $definition = $this->resolveWorkflowDefinition($requisition->planningEntityId);
            $stepRules = $this->stepRuleRepo->findByWorkflowDefinitionId($definition->id);
        } catch (\Throwable) {
            return [];
        }

        // 1. Determine candidate actions explicitly assigned by governance rules for this status
        $candidateActions = $this->resolveAssignedActionsForStatus($requisition->status);
        if (empty($candidateActions)) {
            return [];
        }

        $permitted = [];
        foreach ($candidateActions as $action) {
            try {
                $transition = $this->matchTransition($requisition, $requisition->status, $action, $stepRules);
                if ($this->isActionAssignedToUser($requisition, $transition, $actingUserId, $stepRules)) {
                    $permitted[] = $transition;
                }
            } catch (\Throwable) {
                // Not permitted from current state, rule, or role assignment
            }
        }

        return $permitted;
    }

    /**
     * Resolve candidate actions explicitly assigned by governance rules for a given requisition status.
     *
     * @return WorkflowAction[]
     */
    public function resolveAssignedActionsForStatus(RequisitionStatus $status): array
    {
        return match ($status) {
            RequisitionStatus::DRAFT, RequisitionStatus::RETURNED => [
                WorkflowAction::SUBMIT,
            ],
            RequisitionStatus::SUBMITTED => [
                WorkflowAction::ENDORSE,
                WorkflowAction::RETURN,
                WorkflowAction::REJECT,
            ],
            RequisitionStatus::ENDORSED => [
                WorkflowAction::APPROVE,
                WorkflowAction::RETURN,
                WorkflowAction::REJECT,
            ],
            RequisitionStatus::DEPARTMENT_APPROVED => [
                WorkflowAction::APPROVE,
                WorkflowAction::RETURN,
                WorkflowAction::REJECT,
            ],
            RequisitionStatus::COMMITMENT_AUTHORIZED => [
                WorkflowAction::RECEIVE,
            ],
            default => [],
        };
    }

    /**
     * Check if a transition is explicitly assigned and authorized for the acting user within their entity scope.
     *
     * @param WorkflowStepRuleDTO[] $stepRules
     */
    public function isActionAssignedToUser(
        RequisitionDTO $requisition,
        WorkflowTransition $transition,
        int $actingUserId,
        array $stepRules
    ): bool {
        if ($actingUserId <= 0) {
            return false;
        }

        $action = $transition->action;
        $status = $requisition->status;

        // 1. Requisition Creator / Requester submission stage
        if ($action === WorkflowAction::SUBMIT) {
            if ($status !== RequisitionStatus::DRAFT && $status !== RequisitionStatus::RETURNED) {
                return false;
            }
            if ($requisition->createdBy === $actingUserId) {
                if ($this->userHasActiveResponsibilities($actingUserId)) {
                    return $this->userHasResponsibility($actingUserId, ResponsibilityCode::CREATE_SUBMIT_OWN, $requisition->planningEntityId);
                }
                return true;
            }
            return $this->userCanCreateForEntity($actingUserId, $requisition->planningEntityId);
        }

        // 2. Self-approval governance check: if acting user created this requisition
        if ($requisition->createdBy === $actingUserId && in_array($action, [WorkflowAction::ENDORSE, WorkflowAction::APPROVE, WorkflowAction::RETURN, WorkflowAction::REJECT], true)) {
            if ($this->userHasActiveResponsibilities($actingUserId)) {
                if (!$this->userCanSelfApprove($actingUserId, $requisition->planningEntityId, $action)) {
                    return false;
                }
            }
        }

        // 3. Negative action individual responsibility checks
        if ($action === WorkflowAction::RETURN && $this->userHasActiveResponsibilities($actingUserId)) {
            if (!$this->userHasResponsibility($actingUserId, ResponsibilityCode::RETURN_REQUESTS, $requisition->planningEntityId)) {
                return false;
            }
        }
        if ($action === WorkflowAction::REJECT && $this->userHasActiveResponsibilities($actingUserId)) {
            if (!$this->userHasResponsibility($actingUserId, ResponsibilityCode::REJECT_REQUESTS, $requisition->planningEntityId)) {
                return false;
            }
        }

        // 4. Workflow Step transitions (ENDORSE, APPROVE, RECEIVE, and associated RETURN / REJECT)
        if ($transition->stepRuleId === null) {
            return false;
        }

        $matchedStep = $this->findStepRuleById($stepRules, $transition->stepRuleId);
        if ($matchedStep === null) {
            return false;
        }

        // Check threshold constraints
        if ($matchedStep->thresholdMinAmount !== null && Decimal::lt($requisition->totalEstimatedCost, $matchedStep->thresholdMinAmount, 2)) {
            return false;
        }
        if ($matchedStep->thresholdMaxAmount !== null && Decimal::gt($requisition->totalEstimatedCost, $matchedStep->thresholdMaxAmount, 2)) {
            return false;
        }

        // Check role and entity assignment for the matched step rule
        return $this->userHoldsStepRoleAndScope($actingUserId, $requisition->planningEntityId, $matchedStep);
    }

    /**
     * Check if user has active creation/submission rights for a planning entity.
     */
    private function userCanCreateForEntity(int $userId, int $planningEntityId): bool
    {
        if ($this->userHasActiveResponsibilities($userId)) {
            return $this->userHasResponsibility($userId, ResponsibilityCode::CREATE_SUBMIT_OWN, $planningEntityId);
        }

        $sessionUser = AuthManager::user();
        if ($sessionUser !== null && (int)($sessionUser['id'] ?? 0) === $userId) {
            if (isset($sessionUser['entity_permissions'][$planningEntityId])) {
                $perms = $sessionUser['entity_permissions'][$planningEntityId];
                if (in_array(ExecutionPermissions::CREATE, $perms, true) || in_array(ExecutionPermissions::SUBMIT, $perms, true)) {
                    return true;
                }
            }
        }

        $stmt = $this->db->prepare("
            SELECT 1 FROM `user_entity_roles` uer
            JOIN `roles` r ON r.id = uer.role_id
            WHERE uer.user_id = :uid
              AND uer.planning_entity_id = :eid
              AND r.role_code IN ('REQUESTER', 'HOD')
              AND uer.status = 'ACTIVE'
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId, 'eid' => $planningEntityId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Retrieve the chronological workflow decision history for a requisition.
     *
     * @return WorkflowActionLogDTO[]
     */
    public function getWorkflowHistory(int $requisitionId, int $actingUserId): array
    {
        $requisition = $this->reqRepo->findById($requisitionId);
        if ($requisition === null) {
            throw new ValidationException("Requisition #{$requisitionId} does not exist.");
        }

        ExecutionAuthorizationGuard::requireCanViewRequisition($requisition->planningEntityId);

        return $this->actionLogRepo->findByDocument('REQUISITION', $requisitionId);
    }

    /**
     * Match a requested action against configured step rules and canonical state machine.
     *
     * @param WorkflowStepRuleDTO[] $stepRules
     */
    private function matchTransition(
        RequisitionDTO $requisition,
        RequisitionStatus $currentStatus,
        WorkflowAction $action,
        array $stepRules
    ): WorkflowTransition {
        // Resolve step rule matching source status
        $rule = $this->findRuleForStatus($stepRules, $currentStatus, $action);

        switch ($action) {
            case WorkflowAction::SUBMIT:
                if ($currentStatus !== RequisitionStatus::DRAFT && $currentStatus !== RequisitionStatus::RETURNED) {
                    throw new InvalidWorkflowTransitionException(
                        "Action 'SUBMIT' is only permitted from DRAFT or RETURNED status. Current status is {$currentStatus->value}.",
                        sourceStatus: $currentStatus->value,
                        action: $action->value
                    );
                }
                return new WorkflowTransition(
                    sourceStatus: $currentStatus,
                    action: $action,
                    targetStatus: RequisitionStatus::SUBMITTED,
                    stepRuleId: $rule?->id,
                    stepName: $rule?->stepName,
                    requiredPermission: ExecutionPermissions::SUBMIT
                );

            case WorkflowAction::ENDORSE:
                if ($currentStatus !== RequisitionStatus::SUBMITTED) {
                    throw new InvalidWorkflowTransitionException(
                        "Action 'ENDORSE' is only permitted from SUBMITTED status. Current status is {$currentStatus->value}.",
                        sourceStatus: $currentStatus->value,
                        action: $action->value
                    );
                }
                if ($rule === null) {
                    throw new WorkflowException("No workflow step rule found for ENDORSE from status SUBMITTED.");
                }
                return new WorkflowTransition(
                    sourceStatus: $currentStatus,
                    action: $action,
                    targetStatus: RequisitionStatus::ENDORSED,
                    stepRuleId: $rule->id,
                    stepName: $rule->stepName,
                    requiredPermission: ExecutionPermissions::ENDORSE,
                    requiredRoleId: $rule->requiredRoleId
                );

            case WorkflowAction::APPROVE:
                if ($currentStatus === RequisitionStatus::SUBMITTED) {
                    // Direct approval (e.g. Director's Office route without prior endorsement)
                    if ($rule === null) {
                        throw new WorkflowException("No workflow step rule found for APPROVE from status SUBMITTED.");
                    }
                    return new WorkflowTransition(
                        sourceStatus: $currentStatus,
                        action: $action,
                        targetStatus: RequisitionStatus::DEPARTMENT_APPROVED,
                        stepRuleId: $rule->id,
                        stepName: $rule->stepName,
                        requiredPermission: ExecutionPermissions::APPROVE,
                        requiredRoleId: $rule->requiredRoleId
                    );
                }
                if ($currentStatus === RequisitionStatus::ENDORSED) {
                    if ($rule === null) {
                        throw new WorkflowException("No workflow step rule found for APPROVE from status ENDORSED.");
                    }
                    return new WorkflowTransition(
                        sourceStatus: $currentStatus,
                        action: $action,
                        targetStatus: RequisitionStatus::DEPARTMENT_APPROVED,
                        stepRuleId: $rule->id,
                        stepName: $rule->stepName,
                        requiredPermission: ExecutionPermissions::APPROVE,
                        requiredRoleId: $rule->requiredRoleId
                    );
                }
                if ($currentStatus === RequisitionStatus::DEPARTMENT_APPROVED) {
                    // Finance Commitment Authorization
                    if ($rule === null) {
                        throw new WorkflowException("No workflow step rule found for APPROVE from status DEPARTMENT_APPROVED.");
                    }
                    return new WorkflowTransition(
                        sourceStatus: $currentStatus,
                        action: $action,
                        targetStatus: RequisitionStatus::COMMITMENT_AUTHORIZED,
                        stepRuleId: $rule->id,
                        stepName: $rule->stepName,
                        requiredPermission: ExecutionPermissions::APPROVE,
                        requiredRoleId: $rule->requiredRoleId
                    );
                }
                throw new InvalidWorkflowTransitionException(
                    "Action 'APPROVE' is not permitted from status {$currentStatus->value}.",
                    sourceStatus: $currentStatus->value,
                    action: $action->value
                );

            case WorkflowAction::RETURN:
                if ($currentStatus === RequisitionStatus::DRAFT || $currentStatus->isTerminal()) {
                    throw new InvalidWorkflowTransitionException(
                        "Cannot RETURN a requisition in {$currentStatus->value} status.",
                        sourceStatus: $currentStatus->value,
                        action: $action->value
                    );
                }
                return new WorkflowTransition(
                    sourceStatus: $currentStatus,
                    action: $action,
                    targetStatus: RequisitionStatus::RETURNED,
                    stepRuleId: $rule?->id,
                    stepName: $rule?->stepName,
                    requiredPermission: ExecutionPermissions::RETURN_REQ,
                    requiredRoleId: $rule?->requiredRoleId
                );

            case WorkflowAction::REJECT:
                if ($currentStatus === RequisitionStatus::DRAFT || $currentStatus->isTerminal()) {
                    throw new InvalidWorkflowTransitionException(
                        "Cannot REJECT a requisition in {$currentStatus->value} status.",
                        sourceStatus: $currentStatus->value,
                        action: $action->value
                    );
                }
                return new WorkflowTransition(
                    sourceStatus: $currentStatus,
                    action: $action,
                    targetStatus: RequisitionStatus::REJECTED,
                    stepRuleId: $rule?->id,
                    stepName: $rule?->stepName,
                    requiredPermission: ExecutionPermissions::REJECT,
                    requiredRoleId: $rule?->requiredRoleId
                );

            case WorkflowAction::RECEIVE:
                if ($currentStatus !== RequisitionStatus::COMMITMENT_AUTHORIZED) {
                    throw new InvalidWorkflowTransitionException(
                        "Action 'RECEIVE' is only permitted from COMMITMENT_AUTHORIZED status. Current status is {$currentStatus->value}.",
                        sourceStatus: $currentStatus->value,
                        action: $action->value
                    );
                }
                if ($rule === null) {
                    throw new WorkflowException("No workflow step rule found for RECEIVE from status COMMITMENT_AUTHORIZED.");
                }
                return new WorkflowTransition(
                    sourceStatus: $currentStatus,
                    action: $action,
                    targetStatus: RequisitionStatus::PROCUREMENT_RECEIVED,
                    stepRuleId: $rule->id,
                    stepName: $rule->stepName,
                    requiredPermission: ExecutionPermissions::RECEIVE,
                    requiredRoleId: $rule->requiredRoleId
                );
        }

        throw new InvalidWorkflowTransitionException(
            "Action '{$action->value}' is not valid from status '{$currentStatus->value}'.",
            sourceStatus: $currentStatus->value,
            action: $action->value
        );
    }

    /**
     * Find the configured workflow step rule corresponding to current status and action.
     *
     * @param WorkflowStepRuleDTO[] $stepRules
     */
    private function findRuleForStatus(array $stepRules, RequisitionStatus $status, WorkflowAction $action): ?WorkflowStepRuleDTO
    {
        // 1. Try matching by step_name keywords if configured
        foreach ($stepRules as $rule) {
            $nameUpper = strtoupper($rule->stepName);
            if ($status === RequisitionStatus::SUBMITTED && $action === WorkflowAction::ENDORSE && str_contains($nameUpper, 'ENDORSE')) {
                return $rule;
            }
            if ($status === RequisitionStatus::SUBMITTED && $action === WorkflowAction::APPROVE && (str_contains($nameUpper, 'APPROV') || str_contains($nameUpper, 'DIRECTOR'))) {
                return $rule;
            }
            if ($status === RequisitionStatus::ENDORSED && $action === WorkflowAction::APPROVE && (str_contains($nameUpper, 'APPROV') || str_contains($nameUpper, 'DEAN') || str_contains($nameUpper, 'DIRECTOR'))) {
                return $rule;
            }
            if ($status === RequisitionStatus::DEPARTMENT_APPROVED && (str_contains($nameUpper, 'COMMIT') || str_contains($nameUpper, 'BUDGET') || str_contains($nameUpper, 'FINANCE'))) {
                return $rule;
            }
            if ($status === RequisitionStatus::COMMITMENT_AUTHORIZED && (str_contains($nameUpper, 'RECEIV') || str_contains($nameUpper, 'PROCURE'))) {
                return $rule;
            }
        }

        // 2. Fall back to matching by step order sequence
        $targetOrder = match ($status) {
            RequisitionStatus::SUBMITTED => 1,
            RequisitionStatus::ENDORSED => 2,
            RequisitionStatus::DEPARTMENT_APPROVED => 3,
            RequisitionStatus::COMMITMENT_AUTHORIZED => 4,
            default => null,
        };

        if ($targetOrder !== null) {
            $matchingRules = array_values(array_filter($stepRules, fn($r) => $r->stepOrder === $targetOrder));
            if (count($matchingRules) > 1) {
                throw new WorkflowException("Ambiguous workflow configuration: Multiple step rules found with step order {$targetOrder}.");
            }
            if (!empty($matchingRules)) {
                return $matchingRules[0];
            }
        }

        // For RETURN and REJECT, we associate the current step if one exists, otherwise null
        if ($action->isNegative()) {
            $activeOrder = match ($status) {
                RequisitionStatus::SUBMITTED => 1,
                RequisitionStatus::ENDORSED => 2,
                RequisitionStatus::DEPARTMENT_APPROVED => 3,
                RequisitionStatus::COMMITMENT_AUTHORIZED => 4,
                default => 1,
            };
            foreach ($stepRules as $rule) {
                if ($rule->stepOrder === $activeOrder) {
                    return $rule;
                }
            }
            return !empty($stepRules) ? $stepRules[0] : null;
        }

        return null;
    }

    /**
     * @param WorkflowStepRuleDTO[] $stepRules
     */
    private function findStepRuleById(array $stepRules, int $ruleId): ?WorkflowStepRuleDTO
    {
        foreach ($stepRules as $rule) {
            if ($rule->id === $ruleId) {
                return $rule;
            }
        }
        return null;
    }

    private function validateThresholds(string $totalCost, WorkflowStepRuleDTO $step): void
    {
        if ($step->thresholdMinAmount !== null && Decimal::lt($totalCost, $step->thresholdMinAmount, 2)) {
            throw new WorkflowException("Requisition total cost ({$totalCost}) is below the minimum threshold ({$step->thresholdMinAmount}) required for step '{$step->stepName}'.");
        }

        if ($step->thresholdMaxAmount !== null && Decimal::gt($totalCost, $step->thresholdMaxAmount, 2)) {
            throw new WorkflowException("Requisition total cost ({$totalCost}) exceeds the maximum threshold ({$step->thresholdMaxAmount}) allowed for step '{$step->stepName}'.");
        }
    }

    /**
     * Verify whether a user holds the required role within the proper entity scope for a workflow step.
     */
    public function userHoldsStepRoleAndScope(int $userId, int $planningEntityId, WorkflowStepRuleDTO $step): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $stepNameUpper = strtoupper($step->stepName);
        $isHodStep = ($step->stepOrder === 1) || str_contains($stepNameUpper, 'ENDORSE') || str_contains($stepNameUpper, 'HEAD OF DEPARTMENT');
        $isDeanStep = ($step->stepOrder === 2) || str_contains($stepNameUpper, 'DEAN');
        $isFinanceStep = ($step->stepOrder === 3) || str_contains($stepNameUpper, 'FINANCE') || str_contains($stepNameUpper, 'COMMIT');
        $isProcurementStep = ($step->stepOrder === 4) || str_contains($stepNameUpper, 'PROCURE') || str_contains($stepNameUpper, 'RECEIV');

        // 1. Authoritative Individual Responsibilities Evaluation
        if ($this->userHasActiveResponsibilities($userId)) {
            if ($isHodStep) {
                // Check RECOMMEND_DEPT: strictly restricted to assigned department
                $stmt = $this->db->prepare("
                    SELECT 1 FROM `user_responsibilities` ur
                    WHERE ur.user_id = :uid
                      AND ur.responsibility_code = :code
                      AND ur.planning_entity_id = :eid
                      AND ur.is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([
                    'uid' => $userId,
                    'code' => ResponsibilityCode::RECOMMEND_DEPT,
                    'eid' => $planningEntityId,
                ]);
                if ($stmt->fetchColumn() !== false) {
                    return true;
                }

                // Check APPROVE_SUBORDINATE_UNITS: permits recommending/approving child units
                $stmtSub = $this->db->prepare("
                    SELECT 1 FROM `user_responsibilities` ur
                    WHERE ur.user_id = :uid
                      AND ur.responsibility_code = :code
                      AND ur.is_active = 1
                      AND ur.planning_entity_id IN (
                          SELECT ancestor_entity_id FROM entity_hierarchies 
                          WHERE descendant_entity_id = :eid AND depth > 0
                      )
                    LIMIT 1
                ");
                $stmtSub->execute([
                    'uid' => $userId,
                    'code' => ResponsibilityCode::APPROVE_SUBORDINATE_UNITS,
                    'eid' => $planningEntityId,
                ]);
                return $stmtSub->fetchColumn() !== false;
            }

            if ($isDeanStep) {
                // Check APPROVE_FACULTY_DEPTS: Dean faculty or valid descendant departments under that faculty
                $stmt = $this->db->prepare("
                    SELECT 1 FROM `user_responsibilities` ur
                    WHERE ur.user_id = :uid
                      AND ur.responsibility_code = :code
                      AND ur.is_active = 1
                      AND (
                          ur.planning_entity_id = :eid
                          OR ur.planning_entity_id IN (
                              SELECT ancestor_entity_id FROM entity_hierarchies WHERE descendant_entity_id = :eid2
                          )
                      )
                    LIMIT 1
                ");
                $stmt->execute([
                    'uid' => $userId,
                    'code' => ResponsibilityCode::APPROVE_FACULTY_DEPTS,
                    'eid' => $planningEntityId,
                    'eid2' => $planningEntityId,
                ]);
                if ($stmt->fetchColumn() !== false) {
                    return true;
                }

                // Check APPROVE_SUBORDINATE_UNITS: Subordinate units
                $stmtSub = $this->db->prepare("
                    SELECT 1 FROM `user_responsibilities` ur
                    WHERE ur.user_id = :uid
                      AND ur.responsibility_code = :code
                      AND ur.is_active = 1
                      AND ur.planning_entity_id IN (
                          SELECT ancestor_entity_id FROM entity_hierarchies 
                          WHERE descendant_entity_id = :eid AND depth > 0
                      )
                    LIMIT 1
                ");
                $stmtSub->execute([
                    'uid' => $userId,
                    'code' => ResponsibilityCode::APPROVE_SUBORDINATE_UNITS,
                    'eid' => $planningEntityId,
                ]);
                return $stmtSub->fetchColumn() !== false;
            }

            if ($isFinanceStep) {
                // APPROVE_FINANCIAL_COMMITMENTS: Institutional scope
                $stmt = $this->db->prepare("
                    SELECT 1 FROM `user_responsibilities` ur
                    WHERE ur.user_id = :uid
                      AND ur.responsibility_code = :code
                      AND ur.is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([
                    'uid' => $userId,
                    'code' => ResponsibilityCode::APPROVE_FINANCIAL_COMMITMENTS,
                ]);
                return $stmt->fetchColumn() !== false;
            }

            if ($isProcurementStep) {
                // RECEIVE_PURCHASED_ITEMS: Institutional scope
                $stmt = $this->db->prepare("
                    SELECT 1 FROM `user_responsibilities` ur
                    WHERE ur.user_id = :uid
                      AND ur.responsibility_code = :code
                      AND ur.is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([
                    'uid' => $userId,
                    'code' => ResponsibilityCode::RECEIVE_PURCHASED_ITEMS,
                ]);
                return $stmt->fetchColumn() !== false;
            }

            // User has active individual responsibilities, but none match this workflow step
            return false;
        }

        // 2. Controlled Legacy Fallback for users/fixtures without user_responsibilities records
        $sessionUser = AuthManager::user();
        if ($sessionUser !== null && (int)($sessionUser['id'] ?? 0) === $userId) {
            $roles = $sessionUser['roles'] ?? [];
            $roleIds = $sessionUser['role_ids'] ?? [];

            // Administrative access (ADMIN, SUPER_ADMIN) must NOT be treated as automatic workflow approval authority
            $isPureAdmin = (in_array('ADMIN', $roles, true) || in_array('SUPER_ADMIN', $roles, true))
                && !in_array('HOD', $roles, true)
                && !in_array('DEAN', $roles, true)
                && !in_array('FINANCE_OFFICER', $roles, true)
                && !in_array('PROCUREMENT_OFFICER', $roles, true);

            if (!$isPureAdmin) {
                // Check direct role ID match
                if (is_array($roleIds) && in_array($step->requiredRoleId, $roleIds, true)) {
                    $stepNameUpper = strtoupper($step->stepName);
                    // If step is HOD, verify entity match
                    if ($step->stepOrder === 1 || str_contains($stepNameUpper, 'ENDORSE') || str_contains($stepNameUpper, 'HEAD OF DEPARTMENT')) {
                        if (isset($sessionUser['entity_permissions'][$planningEntityId])) {
                            $perms = $sessionUser['entity_permissions'][$planningEntityId];
                            if (in_array(ExecutionPermissions::ENDORSE, $perms, true) || in_array(ExecutionPermissions::ALIAS_ENDORSE, $perms, true)) {
                                return true;
                            }
                        }
                        if (isset($sessionUser['entity_id']) && (int)$sessionUser['entity_id'] === $planningEntityId) {
                            return true;
                        }
                    } elseif ($step->stepOrder === 2 || str_contains($stepNameUpper, 'DEAN')) {
                        if (isset($sessionUser['entity_permissions'][$planningEntityId])) {
                            $perms = $sessionUser['entity_permissions'][$planningEntityId];
                            if (in_array(ExecutionPermissions::APPROVE, $perms, true) || in_array(ExecutionPermissions::ALIAS_APPROVE, $perms, true)) {
                                return true;
                            }
                        }
                    } else {
                        // Finance and Procurement are institutional
                        return true;
                    }
                }

                // Check role code match
                if (!empty($step->roleCode) && is_array($roles) && in_array($step->roleCode, $roles, true)) {
                    if ($step->roleCode === 'FINANCE_OFFICER' || $step->roleCode === 'PROCUREMENT_OFFICER') {
                        return true;
                    }
                }
            }
        }

        // 2. Database-backed role and entity-scoped verification
        $stepNameUpper = strtoupper($step->stepName);
        $isHodStep = ($step->stepOrder === 1) || str_contains($stepNameUpper, 'ENDORSE') || str_contains($stepNameUpper, 'HEAD OF DEPARTMENT');
        $isDeanStep = ($step->stepOrder === 2) || str_contains($stepNameUpper, 'DEAN');
        $isFinanceStep = ($step->stepOrder === 3) || str_contains($stepNameUpper, 'FINANCE') || str_contains($stepNameUpper, 'COMMIT');
        $isProcurementStep = ($step->stepOrder === 4) || str_contains($stepNameUpper, 'PROCURE') || str_contains($stepNameUpper, 'RECEIV');

        // HOD Verification: MUST hold active HOD role for this specific department entity
        if ($isHodStep) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM `user_entity_roles` uer
                JOIN `roles` r ON r.id = uer.role_id
                WHERE uer.user_id = :uid
                  AND uer.planning_entity_id = :eid
                  AND (uer.role_id = :rid OR r.role_code = 'HOD')
                  AND uer.status = 'ACTIVE'
                LIMIT 1
            ");
            $stmt->execute([
                'uid' => $userId,
                'eid' => $planningEntityId,
                'rid' => $step->requiredRoleId,
            ]);
            return $stmt->fetchColumn() !== false;
        }

        // DEAN Verification: MUST hold active DEAN role for this entity or ancestor faculty via hierarchy
        if ($isDeanStep) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM `user_entity_roles` uer
                JOIN `roles` r ON r.id = uer.role_id
                WHERE uer.user_id = :uid
                  AND (uer.role_id = :rid OR r.role_code = 'DEAN')
                  AND uer.status = 'ACTIVE'
                  AND (
                      uer.planning_entity_id = :eid
                      OR uer.planning_entity_id IN (
                          SELECT ancestor_entity_id FROM entity_hierarchies WHERE descendant_entity_id = :eid2
                      )
                  )
                LIMIT 1
            ");
            $stmt->execute([
                'uid' => $userId,
                'eid' => $planningEntityId,
                'eid2' => $planningEntityId,
                'rid' => $step->requiredRoleId,
            ]);
            return $stmt->fetchColumn() !== false;
        }

        // Finance Officer Verification: Institutional scope
        if ($isFinanceStep) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM `user_entity_roles` uer
                JOIN `roles` r ON r.id = uer.role_id
                WHERE uer.user_id = :uid
                  AND (uer.role_id = :rid OR r.role_code = 'FINANCE_OFFICER')
                  AND uer.status = 'ACTIVE'
                LIMIT 1
            ");
            $stmt->execute([
                'uid' => $userId,
                'rid' => $step->requiredRoleId,
            ]);
            return $stmt->fetchColumn() !== false;
        }

        // Procurement Officer Verification: Institutional scope
        if ($isProcurementStep) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM `user_entity_roles` uer
                JOIN `roles` r ON r.id = uer.role_id
                WHERE uer.user_id = :uid
                  AND (uer.role_id = :rid OR r.role_code = 'PROCUREMENT_OFFICER')
                  AND uer.status = 'ACTIVE'
                LIMIT 1
            ");
            $stmt->execute([
                'uid' => $userId,
                'rid' => $step->requiredRoleId,
            ]);
            return $stmt->fetchColumn() !== false;
        }

        // Generic fallback for custom step rules
        $stmt = $this->db->prepare("
            SELECT 1 FROM `user_entity_roles` uer
            WHERE uer.user_id = :uid
              AND uer.planning_entity_id = :eid
              AND uer.role_id = :rid
              AND uer.status = 'ACTIVE'
            LIMIT 1
        ");
        $stmt->execute([
            'uid' => $userId,
            'eid' => $planningEntityId,
            'rid' => $step->requiredRoleId,
        ]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }

        // Check global fallback
        $stmtG = $this->db->prepare("
            SELECT 1 FROM `user_entity_roles` uer
            WHERE uer.user_id = :uid
              AND uer.role_id = :rid
              AND uer.status = 'ACTIVE'
            LIMIT 1
        ");
        $stmtG->execute(['uid' => $userId, 'rid' => $step->requiredRoleId]);

        return $stmtG->fetchColumn() !== false;
    }

    private function validateRoleRequirement(int $userId, int $planningEntityId, WorkflowStepRuleDTO $step): void
    {
        if (!$this->userHoldsStepRoleAndScope($userId, $planningEntityId, $step)) {
            Logger::warning("Workflow authorization failed: User #{$userId} does not possess required role/scope for step '{$step->stepName}' in entity #{$planningEntityId}.");
            throw new UnauthorizedWorkflowActionException("User #{$userId} does not possess the required role for workflow step '{$step->stepName}'.");
        }
    }

    private function getEntityTypeIdForPlanningEntity(int $planningEntityId): ?int
    {
        $stmt = $this->db->prepare("SELECT `entity_type_id` FROM `planning_entities` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $planningEntityId]);
        $val = $stmt->fetchColumn();

        return $val !== false && $val !== null ? (int)$val : null;
    }

    private function lockRequisition(int $requisitionId): array|false
    {
        $sql = "SELECT `id`, `requisition_number`, `planning_entity_id`, `fiscal_year`, 
                       `approved_plan_version_id`, `status`, `total_estimated_cost`, 
                       `justification`, `submitted_at`, `submitted_by`, `created_at`, 
                       `created_by`, `updated_at`, `updated_by`
                FROM `requisitions`
                WHERE `id` = :id
                FOR UPDATE";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $requisitionId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check whether an acting user is explicitly authorized to self-approve a requisition.
     */
    public function userCanSelfApprove(int $userId, int $planningEntityId, WorkflowAction $action): bool
    {
        if ($userId <= 0) {
            return false;
        }

        // 1. Must hold active APPROVE_OWN responsibility explicitly assigned
        if (!$this->userHasResponsibility($userId, ResponsibilityCode::APPROVE_OWN, $planningEntityId)) {
            return false;
        }

        // 2. User must also possess the required operational capability for the specific action
        return match ($action) {
            WorkflowAction::ENDORSE => (
                $this->userHasResponsibility($userId, ResponsibilityCode::RECOMMEND_DEPT, $planningEntityId)
                || $this->userHasResponsibility($userId, ResponsibilityCode::APPROVE_SUBORDINATE_UNITS, $planningEntityId)
            ),
            WorkflowAction::APPROVE => (
                $this->userHasResponsibility($userId, ResponsibilityCode::APPROVE_FACULTY_DEPTS, $planningEntityId)
                || $this->userHasResponsibility($userId, ResponsibilityCode::APPROVE_SUBORDINATE_UNITS, $planningEntityId)
                || $this->userHasResponsibility($userId, ResponsibilityCode::APPROVE_FINANCIAL_COMMITMENTS)
            ),
            WorkflowAction::RECEIVE => (
                $this->userHasResponsibility($userId, ResponsibilityCode::RECEIVE_PURCHASED_ITEMS)
            ),
            WorkflowAction::RETURN => (
                $this->userHasResponsibility($userId, ResponsibilityCode::RETURN_REQUESTS, $planningEntityId)
            ),
            WorkflowAction::REJECT => (
                $this->userHasResponsibility($userId, ResponsibilityCode::REJECT_REQUESTS, $planningEntityId)
            ),
            default => false,
        };
    }

    /**
     * Check whether a user has any active individual responsibilities assigned.
     */
    public function userHasActiveResponsibilities(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT 1 FROM `user_responsibilities`
            WHERE `user_id` = :uid AND `is_active` = 1
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Check whether a user has an active responsibility code for an entity (or globally if entity is null).
     */
    public function userHasResponsibility(int $userId, string $code, ?int $planningEntityId = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($planningEntityId === null) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM `user_responsibilities`
                WHERE `user_id` = :uid AND `responsibility_code` = :code AND `is_active` = 1
                LIMIT 1
            ");
            $stmt->execute(['uid' => $userId, 'code' => $code]);

            return $stmt->fetchColumn() !== false;
        }

        $stmt = $this->db->prepare("
            SELECT 1 FROM `user_responsibilities` ur
            WHERE ur.user_id = :uid
              AND ur.responsibility_code = :code
              AND ur.is_active = 1
              AND (
                  ur.planning_entity_id = :eid
                  OR ur.planning_entity_id IN (
                      SELECT ancestor_entity_id FROM entity_hierarchies WHERE descendant_entity_id = :eid2
                  )
              )
            LIMIT 1
        ");
        $stmt->execute([
            'uid' => $userId,
            'code' => $code,
            'eid' => $planningEntityId,
            'eid2' => $planningEntityId,
        ]);

        return $stmt->fetchColumn() !== false;
    }
}
