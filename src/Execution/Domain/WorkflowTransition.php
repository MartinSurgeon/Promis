<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain;

/**
 * Immutable value object representing a validated workflow state transition.
 */
final class WorkflowTransition
{
    public function __construct(
        public readonly RequisitionStatus $sourceStatus,
        public readonly WorkflowAction $action,
        public readonly RequisitionStatus $targetStatus,
        public readonly ?int $stepRuleId = null,
        public readonly ?string $stepName = null,
        public readonly ?string $requiredPermission = null,
        public readonly ?int $requiredRoleId = null
    ) {
    }

    /**
     * Export transition details to array.
     */
    public function toArray(): array
    {
        return [
            'source_status' => $this->sourceStatus->value,
            'action' => $this->action->value,
            'target_status' => $this->targetStatus->value,
            'step_rule_id' => $this->stepRuleId,
            'step_name' => $this->stepName,
            'required_permission' => $this->requiredPermission,
            'required_role_id' => $this->requiredRoleId,
        ];
    }
}
