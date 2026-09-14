<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain;

/**
 * Outcome of a quarterly review cycle (plan_review_cycles.review_outcome).
 * Supports FR-049: Dual outcomes (No change vs Revision required).
 */
enum ReviewOutcome: string
{
    case NO_CHANGE = 'NO_CHANGE';
    case REVISION_REQUIRED = 'REVISION_REQUIRED';

    /**
     * Determine if a plan revision is mandated by this review outcome.
     */
    public function requiresRevision(): bool
    {
        return $this === self::REVISION_REQUIRED;
    }

    /**
     * All allowed outcome values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
