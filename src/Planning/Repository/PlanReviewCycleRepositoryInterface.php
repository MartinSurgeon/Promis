<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Src\Planning\Domain\DTO\PlanReviewCycleDTO;

/**
 * Contract for Quarterly Plan Review Sessions persistence operations (plan_review_cycles).
 * Supports FR-049.
 */
interface PlanReviewCycleRepositoryInterface
{
    public function findById(int $id): ?PlanReviewCycleDTO;

    public function findByPlanQuarter(int $planId, int $fiscalYear, string $quarter): ?PlanReviewCycleDTO;

    /**
     * @return PlanReviewCycleDTO[]
     */
    public function findByPlanId(int $planId): array;

    public function create(array $data): int;

    public function completeReview(
        int $id,
        string $outcome,
        ?string $notes,
        int $reviewedByUserId,
        ?string $completedAt = null
    ): bool;
}
