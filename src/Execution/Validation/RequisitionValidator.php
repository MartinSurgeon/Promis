<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Validation;

use Promis\Core\Exception\ValidationException;
use Promis\Src\Execution\Domain\RequisitionStatus;

/**
 * Validates departmental requisition inputs and structural correctness.
 */
final class RequisitionValidator
{
    /**
     * Validate requisition creation or update payload.
     *
     * @param array $data Input attributes
     * @param array $items Optional line items
     * @throws ValidationException
     */
    public static function validate(array $data, array $items = []): void
    {
        $errors = [];

        // 1. Planning Entity ID
        $entityId = $data['planning_entity_id'] ?? null;
        if (empty($entityId) || !is_numeric($entityId) || (int)$entityId <= 0) {
            $errors['planning_entity_id'] = ['A valid planning entity must be specified.'];
        }

        // 2. Fiscal Year (between 2000 and 2100)
        $fiscalYear = $data['fiscal_year'] ?? null;
        if (empty($fiscalYear) || !is_numeric($fiscalYear) || (int)$fiscalYear < 2000 || (int)$fiscalYear > 2100) {
            $errors['fiscal_year'] = ['Fiscal year must be a valid 4-digit year between 2000 and 2100.'];
        }

        // 3. Justification
        $justification = trim((string)($data['justification'] ?? ''));
        if ($justification === '') {
            $errors['justification'] = ['Requisition justification is required.'];
        }

        // 4. Status (if provided)
        if (isset($data['status'])) {
            $status = $data['status'];
            $statusStr = $status instanceof \BackedEnum ? $status->value : (string)$status;
            if (RequisitionStatus::tryFrom($statusStr) === null) {
                $errors['status'] = ["Invalid requisition status '{$statusStr}'."];
            }
        }

        // 5. Approved Plan Version ID (if provided, must be positive integer)
        if (isset($data['approved_plan_version_id']) && $data['approved_plan_version_id'] !== null && $data['approved_plan_version_id'] !== '') {
            $versionId = $data['approved_plan_version_id'];
            if (!is_numeric($versionId) || (int)$versionId <= 0) {
                $errors['approved_plan_version_id'] = ['Approved plan version ID must be a positive integer.'];
            }
        }

        // 6. Line Items Validation (if provided)
        if (!empty($items)) {
            $itemErrors = [];
            foreach ($items as $index => $item) {
                $lineErrors = RequisitionItemValidator::validate($item, $index + 1);
                if (!empty($lineErrors)) {
                    $itemErrors = array_merge($itemErrors, $lineErrors);
                }
            }
            if (!empty($itemErrors)) {
                $errors['items'] = $itemErrors;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Requisition validation failed.', $errors);
        }
    }
}
