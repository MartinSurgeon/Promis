<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Validation;

use Promis\Core\Exception\ValidationException;
use Promis\Src\Planning\Domain\ReviewOutcome;
use Promis\Src\Planning\Domain\ReviewQuarter;

/**
 * Validates Quarterly Plan Review cycle initiation and completion (FR-049).
 */
final class ReviewCycleValidator
{
    /**
     * Validate review cycle initiation parameters.
     */
    public static function validateInitiation(int $planId, int $activeVersionId, int $fiscalYear, string $quarter): void
    {
        $errors = [];

        if ($planId <= 0) {
            $errors['procurement_plan_id'] = ['A valid procurement plan must be specified.'];
        }

        if ($activeVersionId <= 0) {
            $errors['active_version_id'] = ['A valid approved plan version must be active for quarterly review.'];
        }

        if ($fiscalYear < 2000 || $fiscalYear > 2100) {
            $errors['fiscal_year'] = ['Fiscal year must be a valid 4-digit year.'];
        }

        if (!in_array($quarter, ReviewQuarter::values(), true)) {
            $errors['review_quarter'] = ['Review quarter must be one of: ' . implode(', ', ReviewQuarter::values()) . '.'];
        }

        if (!empty($errors)) {
            throw new ValidationException('Review cycle initiation validation failed.', $errors);
        }
    }

    /**
     * Validate review cycle completion outcome.
     */
    public static function validateOutcome(string $outcome, ?string $notes = null): void
    {
        $errors = [];

        if (!in_array($outcome, ReviewOutcome::values(), true)) {
            $errors['review_outcome'] = [
                'Review outcome must be either NO_CHANGE or REVISION_REQUIRED.'
            ];
        }

        if ($outcome === ReviewOutcome::REVISION_REQUIRED->value && (empty($notes) || trim($notes) === '')) {
            $errors['review_notes'] = [
                'Review notes/justification are mandatory when a revision is required.'
            ];
        }

        if (!empty($errors)) {
            throw new ValidationException('Review cycle completion validation failed.', $errors);
        }
    }
}
