<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Src\Execution\Domain\DTO\RequisitionDTO;

/**
 * Contract for Requisition persistence operations (requisitions table).
 */
interface RequisitionRepositoryInterface
{
    public function findById(int $id): ?RequisitionDTO;

    public function findByRequisitionNumber(string $requisitionNumber): ?RequisitionDTO;

    /**
     * @return RequisitionDTO[]
     */
    public function findByPlanningEntity(int $planningEntityId, ?int $fiscalYear = null): array;

    /**
     * @return RequisitionDTO[]
     */
    public function findByApprovedPlanVersion(int $approvedPlanVersionId): array;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function updateStatus(int $id, string $status, ?int $updatedBy = null, ?string $expectedPreStatus = null): bool;

    public function updateTotalCost(int $id, string $totalCost, ?int $updatedBy = null): bool;

    public function delete(int $id): bool;

    public function existsByRequisitionNumber(string $requisitionNumber): bool;
}
