<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;

/**
 * Result DTO returned upon successful workflow action execution.
 */
final class WorkflowActionResult
{
    public function __construct(
        public readonly RequisitionDTO $requisition,
        public readonly int $actionLogId,
        public readonly int $auditLogId,
        public readonly RequisitionStatus $previousStatus,
        public readonly RequisitionStatus $newStatus,
        public readonly WorkflowAction $action,
        public readonly ?int $stepRuleId = null,
        public readonly ?string $stepName = null,
        public readonly string $timestamp = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'requisition' => $this->requisition->toArray(),
            'action_log_id' => $this->actionLogId,
            'audit_log_id' => $this->auditLogId,
            'previous_status' => $this->previousStatus->value,
            'new_status' => $this->newStatus->value,
            'action' => $this->action->value,
            'step_rule_id' => $this->stepRuleId,
            'step_name' => $this->stepName,
            'timestamp' => $this->timestamp,
        ];
    }
}
