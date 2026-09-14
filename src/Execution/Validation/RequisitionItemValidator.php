<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Validation;

use Promis\Src\Planning\Domain\Decimal;

/**
 * Validates individual requisition line item inputs.
 * Strictly enforces arbitrary-precision decimal validation without floating-point arithmetic.
 */
final class RequisitionItemValidator
{
    /**
     * Validate an array of requisition line item data.
     *
     * @param array $item Line item attributes
     * @param int $index Index in the items array for contextual error reporting
     * @return array List of error messages for this item, if any
     */
    public static function validate(array $item, int $index = 0): array
    {
        $errors = [];
        $prefix = $index > 0 ? "Item #{$index}: " : "";

        // 1. Procurement Plan Item ID
        $planItemId = $item['procurement_plan_item_id'] ?? null;
        if (empty($planItemId) || !is_numeric($planItemId) || (int)$planItemId <= 0) {
            $errors[] = $prefix . 'A valid procurement plan item reference is required.';
        }

        // 2. Standard Item ID
        $standardItemId = $item['standard_item_id'] ?? null;
        if (empty($standardItemId) || !is_numeric($standardItemId) || (int)$standardItemId <= 0) {
            $errors[] = $prefix . 'A valid standard catalogue item must be selected.';
        }

        // 3. Item Description
        $description = trim((string)($item['item_description'] ?? ''));
        if ($description === '') {
            $errors[] = $prefix . 'Item description is required.';
        } elseif (mb_strlen($description) > 255) {
            $errors[] = $prefix . 'Item description must not exceed 255 characters.';
        }

        // 4. Unit of Measure ID
        $uomId = $item['uom_id'] ?? null;
        if (empty($uomId) || !is_numeric($uomId) || (int)$uomId <= 0) {
            $errors[] = $prefix . 'A valid unit of measure (UOM) is required.';
        }

        // 5. Requested Quantity (Must be strictly > 0.00)
        $quantity = $item['requested_quantity'] ?? null;
        if (!Decimal::isValid($quantity)) {
            $errors[] = $prefix . 'Requested quantity must be a positive number greater than zero.';
        } else {
            $qtyStr = Decimal::normalize($quantity, 2);
            if (Decimal::lte($qtyStr, '0.00', 2)) {
                $errors[] = $prefix . 'Requested quantity must be a positive number greater than zero.';
            }
        }

        // 6. Estimated Unit Cost (Must be >= 0.00)
        $unitCost = $item['estimated_unit_cost'] ?? null;
        if (!Decimal::isValid($unitCost)) {
            $errors[] = $prefix . 'Estimated unit cost must be a non-negative number.';
        } else {
            $costStr = Decimal::normalize($unitCost, 2);
            if (Decimal::lt($costStr, '0.00', 2)) {
                $errors[] = $prefix . 'Estimated unit cost must be a non-negative number.';
            }
        }

        return $errors;
    }
}
