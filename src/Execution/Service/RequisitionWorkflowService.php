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

        $permitted = [];
        $candidateActions = [
            WorkflowAction::SUBMIT,
            WorkflowAction::ENDORSE,
            WorkflowAction::APPROVE,
            WorkflowAction::RETURN,
            WorkflowAction::REJECT,
            WorkflowAction::RECEIVE,
        ];

        foreach ($candidateActions as $action) {
            try {
                $transition = $this->matchTransition($requisition, $requisition->status, $action, $stepRules);
                $permitted[] = $transition;
            } catch (\Throwable) {
                // Not permitted from current state or rule
            }
        }

        return $permitted;
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

    private function validateRoleRequirement(int $userId, int $planningEntityId, WorkflowStepRuleDTO $step): void
    {
        // 1. Check user session context
        $sessionUser = AuthManager::user();
        if ($sessionUser !== null) {
            $roleIds = $sessionUser['role_ids'] ?? [];
            if (is_array($roleIds) && in_array($step->requiredRoleId, $roleIds, true)) {
                return;
            }

            $roles = $sessionUser['roles'] ?? [];
            if (is_array($roles) && !empty($step->roleCode) && in_array($step->roleCode, $roles, true)) {
                return;
            }
        }

        // 2. Check physical user_entity_roles database table
        $sql = "SELECT 1 FROM `user_entity_roles` 
                WHERE `user_id` = :uid 
                  AND `planning_entity_id` = :eid 
                  AND `role_id` = :rid 
                  AND `status` = 'ACTIVE' 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'uid' => $userId,
            'eid' => $planningEntityId,
            'rid' => $step->requiredRoleId,
        ]);

        if ($stmt->fetchColumn() !== false) {
            return;
        }

        // Also check if user holds role globally or at campus level
        $sqlGlobal = "SELECT 1 FROM `user_entity_roles` 
                      WHERE `user_id` = :uid 
                        AND `role_id` = :rid 
                        AND `status` = 'ACTIVE' 
                      LIMIT 1";
        $stmtG = $this->db->prepare($sqlGlobal);
        $stmtG->execute(['uid' => $userId, 'rid' => $step->requiredRoleId]);

        if ($stmtG->fetchColumn() !== false) {
            return;
        }

        throw new UnauthorizedWorkflowActionException("User #{$userId} does not possess the required role for workflow step '{$step->stepName}'.");
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
}
