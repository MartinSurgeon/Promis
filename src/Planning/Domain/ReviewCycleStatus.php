<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain;

/**
 * Status of a quarterly review cycle session (plan_review_cycles.review_status).
 * Supports FR-049.
 */
enum ReviewCycleStatus: string
{
    case PENDING = 'PENDING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';

    /**
     * Determine if review cycle is concluded.
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
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
