<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Validation;

use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\TargetQuarter;

/**
 * Validates individual procurement plan line item inputs (FR-008, FR-009).
 * Enforces arbitrary-precision decimal validation without float arithmetic.
 */
final class PlanItemValidator
{
    /**
     * Validate an array of plan line item data.
     *
     * @param array $item
     * @param int $index Index in the items array for contextual error reporting
     * @return array List of error messages for this item, if any
     */
    public static function validate(array $item, int $index = 0): array
    {
        $errors = [];
        $prefix = "Item #{$index}: ";

        // 1. Standard Item ID
        $standardItemId = $item['standard_item_id'] ?? null;
        if (empty($standardItemId) || !is_numeric($standardItemId) || (int)$standardItemId <= 0) {
            $errors[] = $prefix . 'A valid standard catalogue item must be selected.';
        }

        // 2. Item Description
        $description = trim((string)($item['item_description'] ?? ''));
        if ($description === '') {
            $errors[] = $prefix . 'Item description is required.';
        } elseif (mb_strlen($description) > 255) {
            $errors[] = $prefix . 'Item description must not exceed 255 characters.';
        }

        // 3. Category & Unit of Measure IDs
        $categoryId = $item['category_id'] ?? null;
        if (empty($categoryId) || !is_numeric($categoryId) || (int)$categoryId <= 0) {
            $errors[] = $prefix . 'A valid item category is required.';
        }

        $uomId = $item['uom_id'] ?? null;
        if (empty($uomId) || !is_numeric($uomId) || (int)$uomId <= 0) {
            $errors[] = $prefix . 'A valid unit of measure (UOM) is required.';
        }

        // 4. Planned Quantity (Must be strictly > 0)
        $quantity = $item['planned_quantity'] ?? null;
        if (!Decimal::isValid($quantity)) {
            $errors[] = $prefix . 'Planned quantity must be a positive number greater than zero.';
        } else {
            $qtyStr = Decimal::normalize($quantity, 2);
            if (Decimal::lte($qtyStr, '0.00', 2)) {
                $errors[] = $prefix . 'Planned quantity must be a positive number greater than zero.';
            }
        }

        // 5. Estimated Unit Cost (Must be >= 0)
        $unitCost = $item['estimated_unit_cost'] ?? null;
        if (!Decimal::isValid($unitCost)) {
            $errors[] = $prefix . 'Estimated unit cost must be a non-negative number.';
        } else {
            $costStr = Decimal::normalize($unitCost, 2);
            if (Decimal::lt($costStr, '0.00', 2)) {
                $errors[] = $prefix . 'Estimated unit cost must be a non-negative number.';
            }
        }

        // 6. Target Quarter
        $targetQuarter = $item['target_quarter'] ?? '';
        if (!in_array($targetQuarter, TargetQuarter::values(), true)) {
            $errors[] = $prefix . 'Target quarter must be one of: ' . implode(', ', TargetQuarter::values()) . '.';
        }

        // 7. Funding Source
        $fundingSource = trim((string)($item['funding_source'] ?? ''));
        if ($fundingSource === '') {
            $errors[] = $prefix . 'Funding source is required.';
        } elseif (mb_strlen($fundingSource) > 100) {
            $errors[] = $prefix . 'Funding source must not exceed 100 characters.';
        }

        return $errors;
    }
}
