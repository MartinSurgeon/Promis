<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain;

/**
 * Review quarter for quarterly review cycles (plan_review_cycles.review_quarter).
 * Supports FR-049.
 */
enum ReviewQuarter: string
{
    case Q1 = 'Q1';
    case Q2 = 'Q2';
    case Q3 = 'Q3';
    case Q4 = 'Q4';

    /**
     * All allowed review quarters.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
