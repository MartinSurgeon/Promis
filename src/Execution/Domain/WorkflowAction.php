<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain;

/**
 * Standard institutional actions for Requisition Workflow transitions.
 */
enum WorkflowAction: string
{
    case SUBMIT = 'SUBMIT';
    case ENDORSE = 'ENDORSE';
    case APPROVE = 'APPROVE';
    case RETURN = 'RETURN';
    case REJECT = 'REJECT';
    case RECEIVE = 'RECEIVE';

    /**
     * Determine if this action requires a mandatory justification comment.
     */
    public function requiresComment(): bool
    {
        return match ($this) {
            self::RETURN, self::REJECT => true,
            default => false,
        };
    }

    /**
     * Determine if this action represents a negative workflow branch (return or rejection).
     */
    public function isNegative(): bool
    {
        return match ($this) {
            self::RETURN, self::REJECT => true,
            default => false,
        };
    }

    /**
     * Parse and normalize input into a valid WorkflowAction, or return null if invalid.
     */
    public static function tryFromString(?string $action): ?self
    {
        if ($action === null) {
            return null;
        }

        $normalized = strtoupper(trim($action));

        return self::tryFrom($normalized);
    }
}
