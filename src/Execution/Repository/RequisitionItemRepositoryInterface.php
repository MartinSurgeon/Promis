<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Src\Execution\Domain\DTO\RequisitionItemDTO;

/**
 * Contract for Requisition Item persistence operations (requisition_items table).
 */
interface RequisitionItemRepositoryInterface
{
    public function findById(int $id): ?RequisitionItemDTO;

    /**
     * @return RequisitionItemDTO[]
     */
    public function findByRequisitionId(int $requisitionId): array;

    /**
     * @return RequisitionItemDTO[]
     */
    public function findByPlanItemId(int $planItemId): array;

    public function create(array $data): int;

    /**
     * Batch insert items for a requisition.
     *
     * @param array<int, array> $items
     */
    public function createBatch(int $requisitionId, array $items, int $createdBy): int;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    public function deleteByRequisitionId(int $requisitionId): int;

    /**
     * Calculate cumulative requested quantity against a plan item across non-rejected requisitions.
     */
    public function calculateRequestedQuantityByPlanItem(int $planItemId, ?int $excludeRequisitionId = null): string;
}
