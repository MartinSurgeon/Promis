<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Core\Repository\BaseRepository;
use Promis\Src\Execution\Domain\DTO\RequisitionBalanceSnapshotDTO;
use Promis\Src\Planning\Domain\Decimal;

/**
 * Concrete PDO repository for requisition_balance_snapshots table.
 */
final class RequisitionBalanceSnapshotRepository extends BaseRepository implements RequisitionBalanceSnapshotRepositoryInterface
{
    private const BASE_SELECT = "
        SELECT `id`, `requisition_id`, `requisition_item_id`, `plan_item_id`,
               `workflow_event`, `approved_planned_quantity`, `previously_requested_quantity`,
               `current_request_quantity`, `remaining_before`, `remaining_after`,
               `snapshot_timestamp`, `recorded_by_user_id`
        FROM `requisition_balance_snapshots`
    ";

    public function findById(int $id): ?RequisitionBalanceSnapshotDTO
    {
        $sql = self::BASE_SELECT . " WHERE `id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? RequisitionBalanceSnapshotDTO::fromArray($row) : null;
    }

    /**
     * @return RequisitionBalanceSnapshotDTO[]
     */
    public function findByRequisitionId(int $requisitionId): array
    {
        $sql = self::BASE_SELECT . " WHERE `requisition_id` = :requisition_id ORDER BY `id` ASC";
        $rows = $this->fetchAll($sql, ['requisition_id' => $requisitionId]);

        return array_map(fn(array $row) => RequisitionBalanceSnapshotDTO::fromArray($row), $rows);
    }

    /**
     * @return RequisitionBalanceSnapshotDTO[]
     */
    public function findByRequisitionItemId(int $requisitionItemId): array
    {
        $sql = self::BASE_SELECT . " WHERE `requisition_item_id` = :requisition_item_id ORDER BY `id` ASC";
        $rows = $this->fetchAll($sql, ['requisition_item_id' => $requisitionItemId]);

        return array_map(fn(array $row) => RequisitionBalanceSnapshotDTO::fromArray($row), $rows);
    }

    public function findLatestByRequisitionItemId(int $requisitionItemId): ?RequisitionBalanceSnapshotDTO
    {
        $sql = self::BASE_SELECT . " WHERE `requisition_item_id` = :requisition_item_id ORDER BY `id` DESC LIMIT 1";
        $row = $this->fetchOne($sql, ['requisition_item_id' => $requisitionItemId]);

        return $row !== null ? RequisitionBalanceSnapshotDTO::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO `requisition_balance_snapshots` (
                    `requisition_id`,
                    `requisition_item_id`,
                    `plan_item_id`,
                    `workflow_event`,
                    `approved_planned_quantity`,
                    `previously_requested_quantity`,
                    `current_request_quantity`,
                    `remaining_before`,
                    `remaining_after`,
                    `snapshot_timestamp`,
                    `recorded_by_user_id`
                ) VALUES (
                    :requisition_id,
                    :requisition_item_id,
                    :plan_item_id,
                    :workflow_event,
                    :approved_planned_quantity,
                    :previously_requested_quantity,
                    :current_request_quantity,
                    :remaining_before,
                    :remaining_after,
                    :snapshot_timestamp,
                    :recorded_by_user_id
                )";

        $this->execute($sql, [
            'requisition_id' => (int)$data['requisition_id'],
            'requisition_item_id' => (int)$data['requisition_item_id'],
            'plan_item_id' => (int)$data['plan_item_id'],
            'workflow_event' => (string)$data['workflow_event'],
            'approved_planned_quantity' => Decimal::normalize($data['approved_planned_quantity'], 2),
            'previously_requested_quantity' => Decimal::normalize($data['previously_requested_quantity'], 2),
            'current_request_quantity' => Decimal::normalize($data['current_request_quantity'], 2),
            'remaining_before' => Decimal::normalize($data['remaining_before'], 2),
            'remaining_after' => Decimal::normalize($data['remaining_after'], 2),
            'snapshot_timestamp' => $data['snapshot_timestamp'] ?? date('Y-m-d H:i:s'),
            'recorded_by_user_id' => (int)$data['recorded_by_user_id'],
        ]);

        return $this->lastInsertId();
    }

    public function createBatch(array $snapshots): int
    {
        $insertedCount = 0;
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO `requisition_balance_snapshots` (
                    `requisition_id`,
                    `requisition_item_id`,
                    `plan_item_id`,
                    `workflow_event`,
                    `approved_planned_quantity`,
                    `previously_requested_quantity`,
                    `current_request_quantity`,
                    `remaining_before`,
                    `remaining_after`,
                    `snapshot_timestamp`,
                    `recorded_by_user_id`
                ) VALUES (
                    :requisition_id,
                    :requisition_item_id,
                    :plan_item_id,
                    :workflow_event,
                    :approved_planned_quantity,
                    :previously_requested_quantity,
                    :current_request_quantity,
                    :remaining_before,
                    :remaining_after,
                    :snapshot_timestamp,
                    :recorded_by_user_id
                )";

        $stmt = $this->db->prepare($sql);

        foreach ($snapshots as $snap) {
            $stmt->bindValue(':requisition_id', (int)$snap['requisition_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':requisition_item_id', (int)$snap['requisition_item_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':plan_item_id', (int)$snap['plan_item_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':workflow_event', (string)$snap['workflow_event'], \PDO::PARAM_STR);
            $stmt->bindValue(':approved_planned_quantity', Decimal::normalize($snap['approved_planned_quantity'], 2), \PDO::PARAM_STR);
            $stmt->bindValue(':previously_requested_quantity', Decimal::normalize($snap['previously_requested_quantity'], 2), \PDO::PARAM_STR);
            $stmt->bindValue(':current_request_quantity', Decimal::normalize($snap['current_request_quantity'], 2), \PDO::PARAM_STR);
            $stmt->bindValue(':remaining_before', Decimal::normalize($snap['remaining_before'], 2), \PDO::PARAM_STR);
            $stmt->bindValue(':remaining_after', Decimal::normalize($snap['remaining_after'], 2), \PDO::PARAM_STR);
            $stmt->bindValue(':snapshot_timestamp', $snap['snapshot_timestamp'] ?? $now, \PDO::PARAM_STR);
            $stmt->bindValue(':recorded_by_user_id', (int)$snap['recorded_by_user_id'], \PDO::PARAM_INT);

            $stmt->execute();
            $insertedCount++;
        }

        return $insertedCount;
    }
}
