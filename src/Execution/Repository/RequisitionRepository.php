<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Core\Repository\BaseRepository;
use Promis\Src\Execution\Domain\DTO\RequisitionDTO;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Planning\Domain\Decimal;

/**
 * Concrete PDO repository for requisitions table.
 * Adheres strictly to native prepared statements and zero business logic.
 */
final class RequisitionRepository extends BaseRepository implements RequisitionRepositoryInterface
{
    private const BASE_SELECT = "
        SELECT r.`id`, r.`requisition_number`, r.`planning_entity_id`, r.`fiscal_year`, 
               r.`approved_plan_version_id`, r.`status`, r.`total_estimated_cost`, 
               r.`justification`, r.`submitted_at`, r.`submitted_by`,
               r.`created_at`, r.`created_by`, r.`updated_at`, r.`updated_by`,
               pe.`entity_name`, pe.`entity_code`, ppv.`version_number`
        FROM `requisitions` r
        JOIN `planning_entities` pe ON pe.`id` = r.`planning_entity_id`
        LEFT JOIN `procurement_plan_versions` ppv ON ppv.`id` = r.`approved_plan_version_id`
    ";

    public function findById(int $id): ?RequisitionDTO
    {
        $sql = self::BASE_SELECT . " WHERE r.`id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? RequisitionDTO::fromArray($row) : null;
    }

    public function findByRequisitionNumber(string $requisitionNumber): ?RequisitionDTO
    {
        $sql = self::BASE_SELECT . " WHERE r.`requisition_number` = :requisition_number LIMIT 1";
        $row = $this->fetchOne($sql, ['requisition_number' => $requisitionNumber]);

        return $row !== null ? RequisitionDTO::fromArray($row) : null;
    }

    /**
     * @return RequisitionDTO[]
     */
    public function findByPlanningEntity(int $planningEntityId, ?int $fiscalYear = null): array
    {
        if ($fiscalYear !== null) {
            $sql = self::BASE_SELECT . " 
                WHERE r.`planning_entity_id` = :planning_entity_id 
                  AND r.`fiscal_year` = :fiscal_year
                ORDER BY r.`id` DESC";
            $rows = $this->fetchAll($sql, [
                'planning_entity_id' => $planningEntityId,
                'fiscal_year' => $fiscalYear,
            ]);
        } else {
            $sql = self::BASE_SELECT . " 
                WHERE r.`planning_entity_id` = :planning_entity_id 
                ORDER BY r.`fiscal_year` DESC, r.`id` DESC";
            $rows = $this->fetchAll($sql, [
                'planning_entity_id' => $planningEntityId,
            ]);
        }

        return array_map(fn(array $row) => RequisitionDTO::fromArray($row), $rows);
    }

    /**
     * @return RequisitionDTO[]
     */
    public function findByApprovedPlanVersion(int $approvedPlanVersionId): array
    {
        $sql = self::BASE_SELECT . " 
            WHERE r.`approved_plan_version_id` = :approved_plan_version_id 
            ORDER BY r.`id` ASC";
        $rows = $this->fetchAll($sql, ['approved_plan_version_id' => $approvedPlanVersionId]);

        return array_map(fn(array $row) => RequisitionDTO::fromArray($row), $rows);
    }

    public function create(array $data): int
    {
        $status = $data['status'] ?? 'DRAFT';
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        $totalCost = isset($data['total_estimated_cost'])
            ? Decimal::normalize($data['total_estimated_cost'], 2)
            : '0.00';

        $approvedPlanVersionId = !empty($data['approved_plan_version_id'])
            ? (int)$data['approved_plan_version_id']
            : null;

        $submittedAt = !empty($data['submitted_at']) ? (string)$data['submitted_at'] : null;
        $submittedBy = !empty($data['submitted_by']) ? (int)$data['submitted_by'] : null;

        $sql = "INSERT INTO `requisitions` (
                    `requisition_number`,
                    `planning_entity_id`,
                    `fiscal_year`,
                    `approved_plan_version_id`,
                    `status`,
                    `total_estimated_cost`,
                    `justification`,
                    `submitted_at`,
                    `submitted_by`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :requisition_number,
                    :planning_entity_id,
                    :fiscal_year,
                    :approved_plan_version_id,
                    :status,
                    :total_estimated_cost,
                    :justification,
                    :submitted_at,
                    :submitted_by,
                    :created_at,
                    :created_by
                )";

        $this->execute($sql, [
            'requisition_number' => (string)$data['requisition_number'],
            'planning_entity_id' => (int)$data['planning_entity_id'],
            'fiscal_year' => (int)$data['fiscal_year'],
            'approved_plan_version_id' => $approvedPlanVersionId,
            'status' => (string)$status,
            'total_estimated_cost' => $totalCost,
            'justification' => (string)($data['justification'] ?? ''),
            'submitted_at' => $submittedAt,
            'submitted_by' => $submittedBy,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => (int)$data['created_by'],
        ]);

        return $this->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('justification', $data)) {
            $fields[] = "`justification` = :justification";
            $params['justification'] = (string)$data['justification'];
        }

        if (array_key_exists('total_estimated_cost', $data)) {
            $fields[] = "`total_estimated_cost` = :total_estimated_cost";
            $params['total_estimated_cost'] = Decimal::normalize($data['total_estimated_cost'], 2);
        }

        if (array_key_exists('approved_plan_version_id', $data)) {
            $fields[] = "`approved_plan_version_id` = :approved_plan_version_id";
            $params['approved_plan_version_id'] = !empty($data['approved_plan_version_id'])
                ? (int)$data['approved_plan_version_id']
                : null;
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

        $sql = "UPDATE `requisitions` SET " . implode(', ', $fields) . " WHERE `id` = :id";

        return $this->execute($sql, $params) > 0;
    }

    public function updateStatus(int $id, string $status, ?int $updatedBy = null, ?string $expectedPreStatus = null): bool
    {
        $params = [
            'id' => $id,
            'status' => $status,
            'updated_by' => $updatedBy,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $extraSet = "";
        if ($status === RequisitionStatus::SUBMITTED->value) {
            $extraSet = ", `submitted_at` = :submitted_at, `submitted_by` = :submitted_by";
            $params['submitted_at'] = date('Y-m-d H:i:s');
            $params['submitted_by'] = $updatedBy;
        }

        $whereClause = "WHERE `id` = :id";
        if ($expectedPreStatus !== null) {
            $whereClause .= " AND `status` = :expected_pre_status";
            $params['expected_pre_status'] = $expectedPreStatus;
        }

        $sql = "UPDATE `requisitions` 
                SET `status` = :status, 
                    `updated_by` = :updated_by, 
                    `updated_at` = :updated_at 
                    {$extraSet}
                {$whereClause}";

        return $this->execute($sql, $params) > 0;
    }

    public function updateTotalCost(int $id, string $totalCost, ?int $updatedBy = null): bool
    {
        $sql = "UPDATE `requisitions` 
                SET `total_estimated_cost` = :total_cost, 
                    `updated_by` = :updated_by, 
                    `updated_at` = :updated_at 
                WHERE `id` = :id";

        return $this->execute($sql, [
            'id' => $id,
            'total_cost' => Decimal::normalize($totalCost, 2),
            'updated_by' => $updatedBy,
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `requisitions` WHERE `id` = :id";

        return $this->execute($sql, ['id' => $id]) > 0;
    }

    public function existsByRequisitionNumber(string $requisitionNumber): bool
    {
        $sql = "SELECT 1 FROM `requisitions` WHERE `requisition_number` = :requisition_number LIMIT 1";

        return $this->fetchOne($sql, ['requisition_number' => $requisitionNumber]) !== null;
    }
}
