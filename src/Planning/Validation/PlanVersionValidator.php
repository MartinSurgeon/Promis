<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Validation;

use Promis\Core\Exception\ValidationException;

/**
 * Validates Plan Versioning and Revision Provenance creation (FR-050).
 */
final class PlanVersionValidator
{
    /**
     * Validate plan revision submission parameters.
     */
    public static function validateRevision(
        int $planId,
        int $priorVersionId,
        string $justification,
        array $revisedItems
    ): void {
        $errors = [];

        if ($planId <= 0) {
            $errors['procurement_plan_id'] = ['A valid procurement plan ID is required.'];
        }

        if ($priorVersionId <= 0) {
            $errors['prior_version_id'] = ['A valid prior approved version ID is required.'];
        }

        $trimmedJustification = trim($justification);
        if ($trimmedJustification === '') {
            $errors['revision_justification'] = ['A detailed justification explaining the rationale for the plan revision is mandatory.'];
        } elseif (mb_strlen($trimmedJustification) < 10) {
            $errors['revision_justification'] = ['Revision justification must be at least 10 characters in length.'];
        }

        if (empty($revisedItems)) {
            $errors['items'] = ['The revised plan must contain at least one line item.'];
        } else {
            $itemErrors = [];
            foreach ($revisedItems as $index => $item) {
                $lineErrors = PlanItemValidator::validate($item, $index + 1);
                if (!empty($lineErrors)) {
                    $itemErrors = array_merge($itemErrors, $lineErrors);
                }
            }
            if (!empty($itemErrors)) {
                $errors['items'] = $itemErrors;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Plan revision validation failed.', $errors);
        }
    }

    /**
     * Calculate next version string based on current version number.
     * E.g. '1.0' -> '2.0'.
     */
    public static function nextVersionNumber(string $currentVersion): string
    {
        if (preg_match('/^(\d+)(\.\d+)?$/', $currentVersion, $matches)) {
            $major = (int)$matches[1];
            return ($major + 1) . '.0';
        }

        return '2.0';
    }
}
