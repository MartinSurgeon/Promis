<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Core\Repository\BaseRepository;
use Promis\Src\Planning\Domain\DTO\PlanRevisionRecordDTO;

/**
 * Concrete PDO repository for plan_revision_records table.
 */
final class PlanRevisionRecordRepository extends BaseRepository implements PlanRevisionRecordRepositoryInterface
{
    public function findById(int $id): ?PlanRevisionRecordDTO
    {
        $sql = "SELECT * FROM `plan_revision_records` WHERE `id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? PlanRevisionRecordDTO::fromArray($row) : null;
    }

    /**
     * @return PlanRevisionRecordDTO[]
     */
    public function findByPlanId(int $planId): array
    {
        $sql = "SELECT * FROM `plan_revision_records` 
                WHERE `procurement_plan_id` = :plan_id 
                ORDER BY `id` ASC";

        $rows = $this->fetchAll($sql, ['plan_id' => $planId]);

        return array_map(fn(array $row) => PlanRevisionRecordDTO::fromArray($row), $rows);
    }

    public function findByNewVersionId(int $newVersionId): ?PlanRevisionRecordDTO
    {
        $sql = "SELECT * FROM `plan_revision_records` 
                WHERE `new_version_id` = :new_version_id 
                LIMIT 1";

        $row = $this->fetchOne($sql, ['new_version_id' => $newVersionId]);

        return $row !== null ? PlanRevisionRecordDTO::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO `plan_revision_records` (
                    `procurement_plan_id`,
                    `review_cycle_id`,
                    `prior_version_id`,
                    `new_version_id`,
                    `revision_justification`,
                    `submitted_by_user_id`,
                    `submitted_at`,
                    `approved_by_user_id`,
                    `approved_at`
                ) VALUES (
                    :procurement_plan_id,
                    :review_cycle_id,
                    :prior_version_id,
                    :new_version_id,
                    :revision_justification,
                    :submitted_by_user_id,
                    :submitted_at,
                    :approved_by_user_id,
                    :approved_at
                )";

        $reviewCycleId = !empty($data['review_cycle_id']) ? (int)$data['review_cycle_id'] : null;
        $approvedBy = !empty($data['approved_by_user_id']) ? (int)$data['approved_by_user_id'] : null;
        $approvedAt = !empty($data['approved_at']) ? (string)$data['approved_at'] : null;

        $this->execute($sql, [
            'procurement_plan_id' => (int)$data['procurement_plan_id'],
            'review_cycle_id' => $reviewCycleId,
            'prior_version_id' => (int)$data['prior_version_id'],
            'new_version_id' => (int)$data['new_version_id'],
            'revision_justification' => (string)$data['revision_justification'],
            'submitted_by_user_id' => (int)$data['submitted_by_user_id'],
            'submitted_at' => $data['submitted_at'] ?? date('Y-m-d H:i:s'),
            'approved_by_user_id' => $approvedBy,
            'approved_at' => $approvedAt,
        ]);

        return $this->lastInsertId();
    }

    public function markApproved(int $id, int $approvedByUserId, ?string $approvedAt = null): bool
    {
        $sql = "UPDATE `plan_revision_records` 
                SET `approved_by_user_id` = :approved_by,
                    `approved_at` = :approved_at 
                WHERE `id` = :id AND `approved_by_user_id` IS NULL";

        return $this->execute($sql, [
            'id' => $id,
            'approved_by' => $approvedByUserId,
            'approved_at' => !empty($approvedAt) ? $approvedAt : date('Y-m-d H:i:s'),
        ]) > 0;
    }
}
