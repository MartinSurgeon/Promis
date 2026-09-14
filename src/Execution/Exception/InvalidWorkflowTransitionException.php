<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Exception;

/**
 * Thrown when an illegal, duplicate, terminal, or stale workflow status transition is attempted.
 */
class InvalidWorkflowTransitionException extends WorkflowException
{
    public function __construct(
        string $message,
        public readonly ?string $sourceStatus = null,
        public readonly ?string $action = null,
        public readonly ?string $targetStatus = null,
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        $context = [
            'source_status' => $sourceStatus,
            'action' => $action,
            'target_status' => $targetStatus,
        ];
        parent::__construct($message, $code, $context, $previous);
    }
}
