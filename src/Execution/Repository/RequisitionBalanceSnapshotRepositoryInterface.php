<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Src\Execution\Domain\DTO\RequisitionBalanceSnapshotDTO;

/**
 * Contract for Requisition Balance Snapshot persistence operations (requisition_balance_snapshots table).
 */
interface RequisitionBalanceSnapshotRepositoryInterface
{
    public function findById(int $id): ?RequisitionBalanceSnapshotDTO;

    /**
     * @return RequisitionBalanceSnapshotDTO[]
     */
    public function findByRequisitionId(int $requisitionId): array;

    /**
     * @return RequisitionBalanceSnapshotDTO[]
     */
    public function findByRequisitionItemId(int $requisitionItemId): array;

    public function findLatestByRequisitionItemId(int $requisitionItemId): ?RequisitionBalanceSnapshotDTO;

    public function create(array $data): int;

    /**
     * @param array<int, array> $snapshots
     */
    public function createBatch(array $snapshots): int;
}
