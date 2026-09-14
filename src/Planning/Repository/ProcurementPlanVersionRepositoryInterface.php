<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Src\Planning\Domain\DTO\PlanVersionDTO;

/**
 * Contract for Procurement Plan Version snapshot operations (procurement_plan_versions).
 * Supports FR-049, FR-050.
 */
interface ProcurementPlanVersionRepositoryInterface
{
    public function findById(int $id): ?PlanVersionDTO;

    /**
     * @return PlanVersionDTO[]
     */
    public function findByPlanId(int $planId): array;

    public function findByPlanAndVersion(int $planId, string $versionNumber): ?PlanVersionDTO;

    public function findLatestVersion(int $planId): ?PlanVersionDTO;

    public function create(array $data): int;

    public function updateStatus(
        int $id,
        string $status,
        ?int $approvedByUserId = null,
        ?string $approvalDate = null
    ): bool;

    public function updateTotalCost(int $id, string $totalCost): bool;
}
