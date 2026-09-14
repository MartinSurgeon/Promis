<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Core\Repository\BaseRepository;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\DTO\PlanVersionDTO;

/**
 * Concrete PDO repository for procurement_plan_versions table.
 */
final class ProcurementPlanVersionRepository extends BaseRepository implements ProcurementPlanVersionRepositoryInterface
{
    public function findById(int $id): ?PlanVersionDTO
    {
        $sql = "SELECT * FROM `procurement_plan_versions` WHERE `id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? PlanVersionDTO::fromArray($row) : null;
    }

    /**
     * @return PlanVersionDTO[]
     */
    public function findByPlanId(int $planId): array
    {
        $sql = "SELECT * FROM `procurement_plan_versions` 
                WHERE `procurement_plan_id` = :plan_id 
                ORDER BY `id` ASC";
        $rows = $this->fetchAll($sql, ['plan_id' => $planId]);

        return array_map(fn(array $row) => PlanVersionDTO::fromArray($row), $rows);
    }

    public function findByPlanAndVersion(int $planId, string $versionNumber): ?PlanVersionDTO
    {
        $sql = "SELECT * FROM `procurement_plan_versions` 
                WHERE `procurement_plan_id` = :plan_id 
                  AND `version_number` = :version_number 
                LIMIT 1";
        $row = $this->fetchOne($sql, [
            'plan_id' => $planId,
            'version_number' => $versionNumber,
        ]);

        return $row !== null ? PlanVersionDTO::fromArray($row) : null;
    }

    public function findLatestVersion(int $planId): ?PlanVersionDTO
    {
        $sql = "SELECT * FROM `procurement_plan_versions` 
                WHERE `procurement_plan_id` = :plan_id 
                ORDER BY `id` DESC 
                LIMIT 1";
        $row = $this->fetchOne($sql, ['plan_id' => $planId]);

        return $row !== null ? PlanVersionDTO::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $status = $data['status'] ?? 'DRAFT';
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        $sql = "INSERT INTO `procurement_plan_versions` (
                    `procurement_plan_id`,
                    `version_number`,
                    `status`,
                    `total_estimated_cost`,
                    `approval_date`,
                    `approved_by_user_id`,
                    `revision_reason`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :procurement_plan_id,
                    :version_number,
                    :status,
                    :total_estimated_cost,
                    :approval_date,
                    :approved_by_user_id,
                    :revision_reason,
                    :created_at,
                    :created_by
                )";

        $approvalDate = !empty($data['approval_date']) ? (string)$data['approval_date'] : null;
        $approvedBy = !empty($data['approved_by_user_id']) ? (int)$data['approved_by_user_id'] : null;
        $revisionReason = isset($data['revision_reason']) && $data['revision_reason'] !== '' ? (string)$data['revision_reason'] : null;

        $this->execute($sql, [
            'procurement_plan_id' => (int)$data['procurement_plan_id'],
            'version_number' => (string)($data['version_number'] ?? '1.0'),
            'status' => $status,
            'total_estimated_cost' => Decimal::normalize($data['total_estimated_cost'] ?? '0.00', 2),
            'approval_date' => $approvalDate,
            'approved_by_user_id' => $approvedBy,
            'revision_reason' => $revisionReason,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => (int)$data['created_by'],
        ]);

        return $this->lastInsertId();
    }

    public function updateStatus(
        int $id,
        string $status,
        ?int $approvedByUserId = null,
        ?string $approvalDate = null,
        ?string $expectedCurrentStatus = null
    ): bool {
        $statusValue = $status;
        if ($statusValue instanceof \BackedEnum) {
            $statusValue = $statusValue->value;
        }

        $expectedValue = $expectedCurrentStatus;
        if ($expectedValue instanceof \BackedEnum) {
            $expectedValue = $expectedValue->value;
        }

        $sql = "UPDATE `procurement_plan_versions` 
                SET `status` = :status,
                    `approved_by_user_id` = :approved_by_user_id,
                    `approval_date` = :approval_date
                WHERE `id` = :id";

        $params = [
            'id' => $id,
            'status' => $statusValue,
            'approved_by_user_id' => $approvedByUserId,
            'approval_date' => !empty($approvalDate) ? $approvalDate : null,
        ];

        if ($expectedValue !== null) {
            $sql .= " AND `status` = :expected_status";
            $params['expected_status'] = $expectedValue;
        }

        return $this->execute($sql, $params) > 0;
    }

    public function updateTotalCost(int $id, string $totalCost): bool
    {
        $sql = "UPDATE `procurement_plan_versions` 
                SET `total_estimated_cost` = :total_cost 
                WHERE `id` = :id";

        return $this->execute($sql, [
            'id' => $id,
            'total_cost' => Decimal::normalize($totalCost, 2),
        ]) > 0;
    }
}
