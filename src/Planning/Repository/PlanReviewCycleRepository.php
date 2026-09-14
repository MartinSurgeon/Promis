<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Repository;

use Promis\Core\Repository\BaseRepository;
use Promis\Src\Planning\Domain\DTO\PlanReviewCycleDTO;
use Promis\Src\Planning\Domain\ReviewCycleStatus;

/**
 * Concrete PDO repository for plan_review_cycles table.
 */
final class PlanReviewCycleRepository extends BaseRepository implements PlanReviewCycleRepositoryInterface
{
    public function findById(int $id): ?PlanReviewCycleDTO
    {
        $sql = "SELECT * FROM `plan_review_cycles` WHERE `id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? PlanReviewCycleDTO::fromArray($row) : null;
    }

    public function findByPlanQuarter(int $planId, int $fiscalYear, string $quarter): ?PlanReviewCycleDTO
    {
        $quarterValue = $quarter;
        if ($quarterValue instanceof \BackedEnum) {
            $quarterValue = $quarterValue->value;
        }

        $sql = "SELECT * FROM `plan_review_cycles` 
                WHERE `procurement_plan_id` = :plan_id 
                  AND `fiscal_year` = :fiscal_year 
                  AND `review_quarter` = :quarter 
                LIMIT 1";

        $row = $this->fetchOne($sql, [
            'plan_id' => $planId,
            'fiscal_year' => $fiscalYear,
            'quarter' => $quarterValue,
        ]);

        return $row !== null ? PlanReviewCycleDTO::fromArray($row) : null;
    }

    /**
     * @return PlanReviewCycleDTO[]
     */
    public function findByPlanId(int $planId): array
    {
        $sql = "SELECT * FROM `plan_review_cycles` 
                WHERE `procurement_plan_id` = :plan_id 
                ORDER BY `fiscal_year` DESC, `review_quarter` ASC";

        $rows = $this->fetchAll($sql, ['plan_id' => $planId]);

        return array_map(fn(array $row) => PlanReviewCycleDTO::fromArray($row), $rows);
    }

    public function create(array $data): int
    {
        $reviewQuarter = $data['review_quarter'] ?? 'Q1';
        if ($reviewQuarter instanceof \BackedEnum) {
            $reviewQuarter = $reviewQuarter->value;
        }

        $reviewStatus = $data['review_status'] ?? ReviewCycleStatus::PENDING->value;
        if ($reviewStatus instanceof \BackedEnum) {
            $reviewStatus = $reviewStatus->value;
        }

        $reviewOutcome = $data['review_outcome'] ?? null;
        if ($reviewOutcome instanceof \BackedEnum) {
            $reviewOutcome = $reviewOutcome->value;
        } elseif ($reviewOutcome === '') {
            $reviewOutcome = null;
        }

        $sql = "INSERT INTO `plan_review_cycles` (
                    `procurement_plan_id`,
                    `active_version_id`,
                    `fiscal_year`,
                    `review_quarter`,
                    `review_status`,
                    `review_outcome`,
                    `reviewed_by_user_id`,
                    `completed_at`,
                    `review_notes`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :procurement_plan_id,
                    :active_version_id,
                    :fiscal_year,
                    :review_quarter,
                    :review_status,
                    :review_outcome,
                    :reviewed_by_user_id,
                    :completed_at,
                    :review_notes,
                    :created_at,
                    :created_by
                )";

        $reviewedBy = !empty($data['reviewed_by_user_id']) ? (int)$data['reviewed_by_user_id'] : null;
        $completedAt = !empty($data['completed_at']) ? (string)$data['completed_at'] : null;
        $notes = isset($data['review_notes']) && $data['review_notes'] !== '' ? (string)$data['review_notes'] : null;

        $this->execute($sql, [
            'procurement_plan_id' => (int)$data['procurement_plan_id'],
            'active_version_id' => (int)$data['active_version_id'],
            'fiscal_year' => (int)$data['fiscal_year'],
            'review_quarter' => $reviewQuarter,
            'review_status' => $reviewStatus,
            'review_outcome' => $reviewOutcome,
            'reviewed_by_user_id' => $reviewedBy,
            'completed_at' => $completedAt,
            'review_notes' => $notes,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => (int)$data['created_by'],
        ]);

        return $this->lastInsertId();
    }

    public function completeReview(
        int $id,
        string $outcome,
        ?string $notes,
        int $reviewedByUserId,
        ?string $completedAt = null
    ): bool {
        $outcomeValue = $outcome;
        if ($outcomeValue instanceof \BackedEnum) {
            $outcomeValue = $outcomeValue->value;
        }

        $sql = "UPDATE `plan_review_cycles` 
                SET `review_status` = :status,
                    `review_outcome` = :outcome,
                    `review_notes` = :notes,
                    `reviewed_by_user_id` = :reviewed_by,
                    `completed_at` = :completed_at
                WHERE `id` = :id";

        $cleanedNotes = isset($notes) && $notes !== '' ? $notes : null;

        return $this->execute($sql, [
            'id' => $id,
            'status' => ReviewCycleStatus::COMPLETED->value,
            'outcome' => $outcomeValue,
            'notes' => $cleanedNotes,
            'reviewed_by' => $reviewedByUserId,
            'completed_at' => !empty($completedAt) ? $completedAt : date('Y-m-d H:i:s'),
        ]) > 0;
    }
}
