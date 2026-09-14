<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Service;

use Promis\Src\Planning\Domain\DTO\PlanDTO;
use Promis\Src\Planning\Domain\DTO\PlanVersionDTO;

/**
 * Service contract for Annual Procurement Plan formulation and inquiry.
 * Supports FR-007, FR-008, FR-009, FR-014.
 * Uses arbitrary-precision decimal strings for monetary values.
 */
interface ProcurementPlanServiceInterface
{
    /**
     * Create a new Annual Procurement Plan header container.
     */
    public function createPlan(int $planningEntityId, int $fiscalYear, int $userId): PlanDTO;

    /**
     * Create initial or subsequent plan version container.
     */
    public function createInitialVersion(
        int $planId,
        int $userId,
        string $versionNumber = '1.0',
        ?string $totalEstimatedCost = null
    ): PlanVersionDTO;

    /**
     * Add line items to a specific plan version and update total cost.
     *
     * @param int $planId
     * @param int $versionId
     * @param array[] $items
     * @param int $userId
     * @return int Count of inserted items
     */
    public function addPlanItems(int $planId, int $versionId, array $items, int $userId): int;

    /**
     * Calculate and persist the total estimated cost for a plan version using decimal-safe arithmetic.
     *
     * @return string Total estimated cost as a decimal string
     */
    public function calculateAndPersistVersionTotal(int $versionId): string;

    /**
     * Set the current active version pointer of a plan.
     */
    public function setCurrentVersion(int $planId, int $versionId, int $userId): bool;

    /**
     * Formulate a new Annual Procurement Plan draft along with initial Version 1.0 baseline items.
     *
     * @param int $planningEntityId
     * @param int $fiscalYear
     * @param array[] $items
     * @param int $userId
     * @param string|float|int|null $budgetCeiling
     * @return PlanDTO
     */
    public function createDraftPlan(
        int $planningEntityId,
        int $fiscalYear,
        array $items,
        int $userId,
        string|float|int|null $budgetCeiling = null
    ): PlanDTO;

    /**
     * Submit an Annual Procurement Plan for administrative review.
     */
    public function submitPlanForReview(int $planId, int $userId): PlanDTO;

    /**
     * Retrieve complete plan details with active or requested version items.
     */
    public function getPlanDetails(int $planId, ?int $versionId = null): ?PlanDTO;

    /**
     * Retrieve a specific plan version with its item lines.
     */
    public function getPlanVersion(int $versionId): ?PlanVersionDTO;

    /**
     * Validate a plan for workflow submission readiness.
     *
     * @param int $planId
     * @return array List of blocking validation errors, if any
     */
    public function validatePlanForSubmission(int $planId): array;
}
