<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Service;

use Promis\Src\Planning\Domain\DTO\PlanReviewCycleDTO;

/**
 * Service contract for Quarterly Plan Review Sessions (FR-049).
 */
interface PlanReviewCycleServiceInterface
{
    /**
     * Initiate a quarterly review session for an active approved procurement plan.
     */
    public function initiateReviewCycle(
        int $planId,
        int $fiscalYear,
        string $quarter,
        int $userId,
        ?int $activeVersionId = null
    ): PlanReviewCycleDTO;

    /**
     * Conclude a quarterly review cycle with one of the two statutory outcomes:
     * - NO_CHANGE: Plan remains valid and active Version 1.0.
     * - REVISION_REQUIRED: A plan revision workflow is initiated.
     */
    public function recordReviewOutcome(
        int $cycleId,
        string $outcome,
        ?string $notes,
        int $userId
    ): PlanReviewCycleDTO;

    /**
     * Retrieve all review cycles for a plan.
     *
     * @return PlanReviewCycleDTO[]
     */
    public function getReviewCyclesForPlan(int $planId): array;
}
