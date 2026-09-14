<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Src\Planning\Domain\DTO\PlanDTO;

/**
 * Contract for Procurement Plan persistence operations (procurement_plans).
 */
interface ProcurementPlanRepositoryInterface
{
    public function findById(int $id): ?PlanDTO;

    public function findByEntityAndYear(int $planningEntityId, int $fiscalYear): ?PlanDTO;

    public function findByPlanNumber(string $planNumber): ?PlanDTO;

    /**
     * @return PlanDTO[]
     */
    public function findByEntity(int $planningEntityId): array;

    public function create(array $data): int;

    public function updateStatus(int $id, string $status, ?int $updatedBy = null): bool;

    public function setCurrentVersion(int $id, int $versionId, ?int $updatedBy = null): bool;
}
