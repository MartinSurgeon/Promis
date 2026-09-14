<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

use Promis\Src\Execution\Domain\WorkflowAction;

/**
 * Command DTO for executing a requisition workflow action.
 */
final class WorkflowActionRequest
{
    public readonly WorkflowAction $action;

    public function __construct(
        public readonly int $requisitionId,
        WorkflowAction|string $action,
        public readonly int $actingUserId,
        public readonly ?string $comments = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null
    ) {
        if ($action instanceof WorkflowAction) {
            $this->action = $action;
        } else {
            $parsed = WorkflowAction::tryFromString((string)$action);
            if ($parsed === null) {
                throw new \InvalidArgumentException("Invalid or unrecognized workflow action: '{$action}'.");
            }
            $this->action = $parsed;
        }
    }
}
