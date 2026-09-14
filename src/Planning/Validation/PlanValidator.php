<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Validation;

use Promis\Core\Exception\ValidationException;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\PlanStatus;

/**
 * Validates Annual Procurement Plan creation, updates, and submission (FR-007, FR-008, FR-009).
 * Uses arbitrary-precision decimal arithmetic for all monetary accumulation and ceiling checks.
 */
final class PlanValidator
{
    /**
     * Validate procurement plan draft creation payload.
     *
     * @param array $data Input data
     * @param array $items Array of line item inputs
     * @param string|float|int|null $budgetCeiling Optional allocated budget limit for fiscal year
     * @throws ValidationException If validation fails
     */
    public static function validate(array $data, array $items = [], string|float|int|null $budgetCeiling = null): void
    {
        $errors = [];

        // 1. Planning Entity ID
        $entityId = $data['planning_entity_id'] ?? null;
        if (empty($entityId) || !is_numeric($entityId) || (int)$entityId <= 0) {
            $errors['planning_entity_id'] = ['A valid planning entity is required.'];
        }

        // 2. Fiscal Year (e.g. 2026)
        $fiscalYear = $data['fiscal_year'] ?? null;
        if (empty($fiscalYear) || !is_numeric($fiscalYear) || (int)$fiscalYear < 2000 || (int)$fiscalYear > 2100) {
            $errors['fiscal_year'] = ['Fiscal year must be a valid 4-digit year between 2000 and 2100.'];
        }

        // 3. Line Items Validation
        if (empty($items)) {
            $errors['items'] = ['A procurement plan must contain at least one line item.'];
        } else {
            $itemErrors = [];
            $totalPlanCost = '0.00';

            foreach ($items as $index => $item) {
                $lineErrors = PlanItemValidator::validate($item, $index + 1);
                if (!empty($lineErrors)) {
                    $itemErrors = array_merge($itemErrors, $lineErrors);
                }

                $rawQty = $item['planned_quantity'] ?? null;
                $rawCost = $item['estimated_unit_cost'] ?? null;

                if (Decimal::isValid($rawQty) && Decimal::isValid($rawCost)) {
                    $qtyStr = Decimal::normalize($rawQty, 2);
                    $costStr = Decimal::normalize($rawCost, 2);

                    if (Decimal::gt($qtyStr, '0.00', 2) && Decimal::gte($costStr, '0.00', 2)) {
                        $lineTotal = Decimal::mul($qtyStr, $costStr, 2);
                        $totalPlanCost = Decimal::add($totalPlanCost, $lineTotal, 2);
                    }
                }
            }

            if (!empty($itemErrors)) {
                $errors['items'] = $itemErrors;
            }

            // 4. Budget Ceiling Check (if budget allocation is configured)
            if ($budgetCeiling !== null && Decimal::isValid($budgetCeiling)) {
                $ceilingStr = Decimal::normalize($budgetCeiling, 2);
                if (Decimal::gt($ceilingStr, '0.00', 2) && Decimal::gt($totalPlanCost, $ceilingStr, 2)) {
                    $formattedTotal = number_format((float)$totalPlanCost, 2);
                    $formattedCeiling = number_format((float)$ceilingStr, 2);
                    $errors['budget_ceiling'] = [
                        "Total plan estimated cost (GHS {$formattedTotal}) exceeds the approved departmental budget allocation ceiling (GHS {$formattedCeiling})."
                    ];
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Procurement plan validation failed.', $errors);
        }
    }
}
