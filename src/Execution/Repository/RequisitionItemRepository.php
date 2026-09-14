<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Core\Repository\BaseRepository;
use Promis\Src\Execution\Domain\DTO\RequisitionItemDTO;
use Promis\Src\Planning\Domain\Decimal;

/**
 * Concrete PDO repository for requisition_items table.
 */
final class RequisitionItemRepository extends BaseRepository implements RequisitionItemRepositoryInterface
{
    private const BASE_SELECT = "
        SELECT ri.`id`, ri.`requisition_id`, ri.`procurement_plan_item_id`, ri.`standard_item_id`,
               ri.`item_description`, ri.`uom_id`, ri.`requested_quantity`, ri.`estimated_unit_cost`,
               ri.`estimated_total_cost`, ri.`item_justification`, ri.`created_at`, ri.`created_by`,
               ri.`updated_at`, ri.`updated_by`,
               si.`item_code`, si.`item_name`, u.`uom_code`, u.`uom_name`,
               ppi.`planned_quantity`, ppi.`target_quarter`
        FROM `requisition_items` ri
        JOIN `standard_items` si ON si.`id` = ri.`standard_item_id`
        JOIN `units_of_measure` u ON u.`id` = ri.`uom_id`
        JOIN `procurement_plan_items` ppi ON ppi.`id` = ri.`procurement_plan_item_id`
    ";

    public function findById(int $id): ?RequisitionItemDTO
    {
        $sql = self::BASE_SELECT . " WHERE ri.`id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? RequisitionItemDTO::fromArray($row) : null;
    }

    /**
     * @return RequisitionItemDTO[]
     */
    public function findByRequisitionId(int $requisitionId): array
    {
        $sql = self::BASE_SELECT . " WHERE ri.`requisition_id` = :requisition_id ORDER BY ri.`id` ASC";
        $rows = $this->fetchAll($sql, ['requisition_id' => $requisitionId]);

        return array_map(fn(array $row) => RequisitionItemDTO::fromArray($row), $rows);
    }

    /**
     * @return RequisitionItemDTO[]
     */
    public function findByPlanItemId(int $planItemId): array
    {
        $sql = self::BASE_SELECT . " WHERE ri.`procurement_plan_item_id` = :plan_item_id ORDER BY ri.`id` ASC";
        $rows = $this->fetchAll($sql, ['plan_item_id' => $planItemId]);

        return array_map(fn(array $row) => RequisitionItemDTO::fromArray($row), $rows);
    }

    public function create(array $data): int
    {
        $qty = isset($data['requested_quantity'])
            ? Decimal::normalize($data['requested_quantity'], 2)
            : '0.00';

        $unitCost = isset($data['estimated_unit_cost'])
            ? Decimal::normalize($data['estimated_unit_cost'], 2)
            : '0.00';

        $totalCost = isset($data['estimated_total_cost'])
            ? Decimal::normalize($data['estimated_total_cost'], 2)
            : Decimal::mul($qty, $unitCost, 2);

        $sql = "INSERT INTO `requisition_items` (
                    `requisition_id`,
                    `procurement_plan_item_id`,
                    `standard_item_id`,
                    `item_description`,
                    `uom_id`,
                    `requested_quantity`,
                    `estimated_unit_cost`,
                    `estimated_total_cost`,
                    `item_justification`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :requisition_id,
                    :procurement_plan_item_id,
                    :standard_item_id,
                    :item_description,
                    :uom_id,
                    :requested_quantity,
                    :estimated_unit_cost,
                    :estimated_total_cost,
                    :item_justification,
                    :created_at,
                    :created_by
                )";

        $this->execute($sql, [
            'requisition_id' => (int)$data['requisition_id'],
            'procurement_plan_item_id' => (int)$data['procurement_plan_item_id'],
            'standard_item_id' => (int)$data['standard_item_id'],
            'item_description' => (string)$data['item_description'],
            'uom_id' => (int)$data['uom_id'],
            'requested_quantity' => $qty,
            'estimated_unit_cost' => $unitCost,
            'estimated_total_cost' => $totalCost,
            'item_justification' => isset($data['item_justification']) ? (string)$data['item_justification'] : null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => (int)$data['created_by'],
        ]);

        return $this->lastInsertId();
    }

    public function createBatch(int $requisitionId, array $items, int $createdBy): int
    {
        $insertedCount = 0;
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO `requisition_items` (
                    `requisition_id`,
                    `procurement_plan_item_id`,
                    `standard_item_id`,
                    `item_description`,
                    `uom_id`,
                    `requested_quantity`,
                    `estimated_unit_cost`,
                    `estimated_total_cost`,
                    `item_justification`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :requisition_id,
                    :procurement_plan_item_id,
                    :standard_item_id,
                    :item_description,
                    :uom_id,
                    :requested_quantity,
                    :estimated_unit_cost,
                    :estimated_total_cost,
                    :item_justification,
                    :created_at,
                    :created_by
                )";

        $stmt = $this->db->prepare($sql);

        foreach ($items as $item) {
            $qty = Decimal::normalize($item['requested_quantity'], 2);
            $unitCost = Decimal::normalize($item['estimated_unit_cost'], 2);
            $totalCost = isset($item['estimated_total_cost'])
                ? Decimal::normalize($item['estimated_total_cost'], 2)
                : Decimal::mul($qty, $unitCost, 2);

            $stmt->bindValue(':requisition_id', $requisitionId, \PDO::PARAM_INT);
            $stmt->bindValue(':procurement_plan_item_id', (int)$item['procurement_plan_item_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':standard_item_id', (int)$item['standard_item_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':item_description', (string)$item['item_description'], \PDO::PARAM_STR);
            $stmt->bindValue(':uom_id', (int)$item['uom_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':requested_quantity', $qty, \PDO::PARAM_STR);
            $stmt->bindValue(':estimated_unit_cost', $unitCost, \PDO::PARAM_STR);
            $stmt->bindValue(':estimated_total_cost', $totalCost, \PDO::PARAM_STR);

            $justification = isset($item['item_justification']) ? (string)$item['item_justification'] : null;
            if ($justification !== null) {
                $stmt->bindValue(':item_justification', $justification, \PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':item_justification', null, \PDO::PARAM_NULL);
            }

            $stmt->bindValue(':created_at', $now, \PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $createdBy, \PDO::PARAM_INT);

            $stmt->execute();
            $insertedCount++;
        }

        return $insertedCount;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('item_description', $data)) {
            $fields[] = "`item_description` = :item_description";
            $params['item_description'] = (string)$data['item_description'];
        }

        if (array_key_exists('requested_quantity', $data)) {
            $fields[] = "`requested_quantity` = :requested_quantity";
            $params['requested_quantity'] = Decimal::normalize($data['requested_quantity'], 2);
        }

        if (array_key_exists('estimated_unit_cost', $data)) {
            $fields[] = "`estimated_unit_cost` = :estimated_unit_cost";
            $params['estimated_unit_cost'] = Decimal::normalize($data['estimated_unit_cost'], 2);
        }

        if (array_key_exists('estimated_total_cost', $data)) {
            $fields[] = "`estimated_total_cost` = :estimated_total_cost";
            $params['estimated_total_cost'] = Decimal::normalize($data['estimated_total_cost'], 2);
        }

        if (array_key_exists('item_justification', $data)) {
            $fields[] = "`item_justification` = :item_justification";
            $params['item_justification'] = isset($data['item_justification']) ? (string)$data['item_justification'] : null;
        }

        if (array_key_exists('updated_by', $data)) {
            $fields[] = "`updated_by` = :updated_by";
            $params['updated_by'] = !empty($data['updated_by']) ? (int)$data['updated_by'] : null;
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "`updated_at` = :updated_at";
        $params['updated_at'] = date('Y-m-d H:i:s');

        $sql = "UPDATE `requisition_items` SET " . implode(', ', $fields) . " WHERE `id` = :id";

        return $this->execute($sql, $params) > 0;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `requisition_items` WHERE `id` = :id";

        return $this->execute($sql, ['id' => $id]) > 0;
    }

    public function deleteByRequisitionId(int $requisitionId): int
    {
        $sql = "DELETE FROM `requisition_items` WHERE `requisition_id` = :requisition_id";

        return $this->execute($sql, ['requisition_id' => $requisitionId]);
    }

    public function calculateRequestedQuantityByPlanItem(int $planItemId, ?int $excludeRequisitionId = null): string
    {
        $params = ['plan_item_id' => $planItemId];
        $excludeClause = "";

        if ($excludeRequisitionId !== null) {
            $excludeClause = " AND r.`id` != :exclude_id";
            $params['exclude_id'] = $excludeRequisitionId;
        }

        $sql = "SELECT COALESCE(SUM(ri.`requested_quantity`), '0.00') AS `total_requested`
                FROM `requisition_items` ri
                JOIN `requisitions` r ON r.`id` = ri.`requisition_id`
                WHERE ri.`procurement_plan_item_id` = :plan_item_id
                  AND r.`status` != 'REJECTED'
                  {$excludeClause}";

        $row = $this->fetchOne($sql, $params);

        return Decimal::normalize($row['total_requested'] ?? '0.00', 2);
    }
}
