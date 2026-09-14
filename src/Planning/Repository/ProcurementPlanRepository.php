<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Core\Repository\BaseRepository;
use Promis\Src\Planning\Domain\DTO\PlanDTO;

/**
 * Concrete PDO repository for procurement_plans table.
 */
final class ProcurementPlanRepository extends BaseRepository implements ProcurementPlanRepositoryInterface
{
    public function findById(int $id): ?PlanDTO
    {
        $sql = "SELECT * FROM `procurement_plans` WHERE `id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? PlanDTO::fromArray($row) : null;
    }

    public function findByEntityAndYear(int $planningEntityId, int $fiscalYear): ?PlanDTO
    {
        $sql = "SELECT * FROM `procurement_plans` 
                WHERE `planning_entity_id` = :planning_entity_id 
                  AND `fiscal_year` = :fiscal_year 
                LIMIT 1";
        $row = $this->fetchOne($sql, [
            'planning_entity_id' => $planningEntityId,
            'fiscal_year' => $fiscalYear,
        ]);

        return $row !== null ? PlanDTO::fromArray($row) : null;
    }

    public function findByPlanNumber(string $planNumber): ?PlanDTO
    {
        $sql = "SELECT * FROM `procurement_plans` WHERE `plan_number` = :plan_number LIMIT 1";
        $row = $this->fetchOne($sql, ['plan_number' => $planNumber]);

        return $row !== null ? PlanDTO::fromArray($row) : null;
    }

    /**
     * @return PlanDTO[]
     */
    public function findByEntity(int $planningEntityId): array
    {
        $sql = "SELECT * FROM `procurement_plans` 
                WHERE `planning_entity_id` = :planning_entity_id 
                ORDER BY `fiscal_year` DESC";
        $rows = $this->fetchAll($sql, ['planning_entity_id' => $planningEntityId]);

        return array_map(fn(array $row) => PlanDTO::fromArray($row), $rows);
    }

    public function create(array $data): int
    {
        $status = $data['status'] ?? 'DRAFT';
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        $sql = "INSERT INTO `procurement_plans` (
                    `plan_number`,
                    `planning_entity_id`,
                    `fiscal_year`,
                    `current_version_id`,
                    `status`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :plan_number,
                    :planning_entity_id,
                    :fiscal_year,
                    :current_version_id,
                    :status,
                    :created_at,
                    :created_by
                )";

        $currentVersionId = !empty($data['current_version_id']) ? (int)$data['current_version_id'] : null;

        $this->execute($sql, [
            'plan_number' => (string)$data['plan_number'],
            'planning_entity_id' => (int)$data['planning_entity_id'],
            'fiscal_year' => (int)$data['fiscal_year'],
            'current_version_id' => $currentVersionId,
            'status' => $status,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => (int)$data['created_by'],
        ]);

        return $this->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?int $updatedBy = null): bool
    {
        $statusValue = $status;
        if ($statusValue instanceof \BackedEnum) {
            $statusValue = $statusValue->value;
        }

        $sql = "UPDATE `procurement_plans` 
                SET `status` = :status,
                    `updated_by` = :updated_by,
                    `updated_at` = NOW()
                WHERE `id` = :id";

        return $this->execute($sql, [
            'id' => $id,
            'status' => $statusValue,
            'updated_by' => $updatedBy,
        ]) > 0;
    }

    public function setCurrentVersion(int $id, int $versionId, ?int $updatedBy = null, ?int $expectedCurrentVersionId = null): bool
    {
        $sql = "UPDATE `procurement_plans` 
                SET `current_version_id` = :version_id,
                    `updated_by` = :updated_by,
                    `updated_at` = NOW()
                WHERE `id` = :id
                  AND EXISTS (
                      SELECT 1 FROM `procurement_plan_versions` 
                      WHERE `id` = :ver_id AND `procurement_plan_id` = :plan_id
                  )";

        $params = [
            'id' => $id,
            'version_id' => $versionId,
            'updated_by' => $updatedBy,
            'ver_id' => $versionId,
            'plan_id' => $id,
        ];

        if ($expectedCurrentVersionId !== null) {
            $sql .= " AND `current_version_id` = :expected_current_version_id";
            $params['expected_current_version_id'] = $expectedCurrentVersionId;
        }

        return $this->execute($sql, $params) > 0;
    }
}
