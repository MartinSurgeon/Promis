<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Core\Repository\BaseRepository;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\DTO\PlanItemDTO;

/**
 * Concrete PDO repository for procurement_plan_items table.
 */
final class ProcurementPlanItemRepository extends BaseRepository implements ProcurementPlanItemRepositoryInterface
{
    public function findById(int $id): ?PlanItemDTO
    {
        $sql = "SELECT ppi.*, 
                       si.item_code, 
                       ic.category_name, 
                       uom.uom_code
                FROM `procurement_plan_items` ppi
                LEFT JOIN `standard_items` si ON si.id = ppi.standard_item_id
                LEFT JOIN `item_categories` ic ON ic.id = ppi.category_id
                LEFT JOIN `units_of_measure` uom ON uom.id = ppi.uom_id
                WHERE ppi.id = :id 
                LIMIT 1";

        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? PlanItemDTO::fromArray($row) : null;
    }

    /**
     * @return PlanItemDTO[]
     */
    public function findByVersionId(int $versionId): array
    {
        $sql = "SELECT ppi.*, 
                       si.item_code, 
                       ic.category_name, 
                       uom.uom_code
                FROM `procurement_plan_items` ppi
                LEFT JOIN `standard_items` si ON si.id = ppi.standard_item_id
                LEFT JOIN `item_categories` ic ON ic.id = ppi.category_id
                LEFT JOIN `units_of_measure` uom ON uom.id = ppi.uom_id
                WHERE ppi.plan_version_id = :version_id 
                ORDER BY ppi.id ASC";

        $rows = $this->fetchAll($sql, ['version_id' => $versionId]);

        return array_map(fn(array $row) => PlanItemDTO::fromArray($row), $rows);
    }

    public function create(array $data): int
    {
        $targetQuarter = $data['target_quarter'] ?? 'Q1';
        if ($targetQuarter instanceof \BackedEnum) {
            $targetQuarter = $targetQuarter->value;
        }

        $sql = "INSERT INTO `procurement_plan_items` (
                    `plan_version_id`,
                    `standard_item_id`,
                    `item_description`,
                    `category_id`,
                    `uom_id`,
                    `planned_quantity`,
                    `estimated_unit_cost`,
                    `estimated_total_cost`,
                    `target_quarter`,
                    `funding_source`,
                    `justification`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :plan_version_id,
                    :standard_item_id,
                    :item_description,
                    :category_id,
                    :uom_id,
                    :planned_quantity,
                    :estimated_unit_cost,
                    :estimated_total_cost,
                    :target_quarter,
                    :funding_source,
                    :justification,
                    :created_at,
                    :created_by
                )";

        $plannedQty = Decimal::normalize($data['planned_quantity'] ?? '0.00', 2);
        $unitCost = Decimal::normalize($data['estimated_unit_cost'] ?? '0.00', 2);
        $totalCost = isset($data['estimated_total_cost']) 
            ? Decimal::normalize($data['estimated_total_cost'], 2) 
            : Decimal::mul($plannedQty, $unitCost, 2);
        $justification = isset($data['justification']) && $data['justification'] !== '' ? (string)$data['justification'] : null;

        $this->execute($sql, [
            'plan_version_id' => (int)$data['plan_version_id'],
            'standard_item_id' => (int)$data['standard_item_id'],
            'item_description' => (string)$data['item_description'],
            'category_id' => (int)$data['category_id'],
            'uom_id' => (int)$data['uom_id'],
            'planned_quantity' => $plannedQty,
            'estimated_unit_cost' => $unitCost,
            'estimated_total_cost' => $totalCost,
            'target_quarter' => $targetQuarter,
            'funding_source' => (string)$data['funding_source'],
            'justification' => $justification,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => (int)$data['created_by'],
        ]);

        return $this->lastInsertId();
    }

    public function createBatch(int $versionId, array $items, int $createdBy): int
    {
        $inserted = 0;
        foreach ($items as $item) {
            $item['plan_version_id'] = $versionId;
            $item['created_by'] = $createdBy;
            $this->create($item);
            $inserted++;
        }

        return $inserted;
    }

    public function deleteByVersionId(int $versionId): int
    {
        $sql = "DELETE FROM `procurement_plan_items` WHERE `plan_version_id` = :version_id";
        return $this->execute($sql, ['version_id' => $versionId]);
    }
}
