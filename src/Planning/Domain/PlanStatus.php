<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain;

/**
 * Lifecycle statuses for Annual Procurement Plans (procurement_plans.status).
 */
enum PlanStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case APPROVED = 'APPROVED';
    case RETURNED = 'RETURNED';
    case REJECTED = 'REJECTED';
    case REVISED = 'REVISED';

    /**
     * Determine if the plan is editable by planning officers.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::DRAFT, self::RETURNED => true,
            default => false,
        };
    }

    /**
     * Determine if the plan is in an active approved state.
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED || $this === self::REVISED;
    }

    /**
     * All allowed status values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
