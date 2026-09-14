<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Src\Planning\Domain\DTO\PlanItemDTO;

/**
 * Contract for Procurement Plan line items persistence operations (procurement_plan_items).
 * Supports FR-008.
 */
interface ProcurementPlanItemRepositoryInterface
{
    public function findById(int $id): ?PlanItemDTO;

    /**
     * @return PlanItemDTO[]
     */
    public function findByVersionId(int $versionId): array;

    public function create(array $data): int;

    /**
     * Insert multiple items within a single batch operation.
     *
     * @param int $versionId
     * @param array[] $items
     * @param int $createdBy
     * @return int Number of inserted rows
     */
    public function createBatch(int $versionId, array $items, int $createdBy): int;

    public function deleteByVersionId(int $versionId): int;
}
