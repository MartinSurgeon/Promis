<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Service;

use Promis\Src\Execution\Domain\DTO\WorkflowActionLogDTO;
use Promis\Src\Execution\Domain\DTO\WorkflowActionRequest;
use Promis\Src\Execution\Domain\DTO\WorkflowActionResult;
use Promis\Src\Execution\Domain\DTO\WorkflowDefinitionDTO;
use Promis\Src\Execution\Domain\WorkflowTransition;

/**
 * Service contract for Requisition Workflow Processing and Multi-Stage Approvals.
 */
interface RequisitionWorkflowServiceInterface
{
    /**
     * Execute an atomic workflow state transition on a requisition.
     */
    public function executeAction(WorkflowActionRequest $request): WorkflowActionResult;

    /**
     * Resolve the active workflow definition governing a planning entity's requisitions.
     */
    public function resolveWorkflowDefinition(int $planningEntityId): WorkflowDefinitionDTO;

    /**
     * Resolve the set of permitted workflow transitions available for a requisition in its current state.
     *
     * @return WorkflowTransition[]
     */
    public function resolvePermittedTransitions(int $requisitionId, int $actingUserId): array;

    /**
     * Retrieve the chronological workflow decision history for a requisition.
     *
     * @return WorkflowActionLogDTO[]
     */
    public function getWorkflowHistory(int $requisitionId, int $actingUserId): array;
}
