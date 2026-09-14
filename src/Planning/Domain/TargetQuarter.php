<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain;

/**
 * Target implementation quarter for plan line items (procurement_plan_items.target_quarter).
 */
enum TargetQuarter: string
{
    case Q1 = 'Q1';
    case Q2 = 'Q2';
    case Q3 = 'Q3';
    case Q4 = 'Q4';

    /**
     * All allowed target quarters.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
