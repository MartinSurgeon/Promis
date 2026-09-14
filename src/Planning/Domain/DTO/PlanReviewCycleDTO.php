<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain\DTO;

use Promis\Src\Planning\Domain\ReviewCycleStatus;
use Promis\Src\Planning\Domain\ReviewOutcome;
use Promis\Src\Planning\Domain\ReviewQuarter;

/**
 * Data contract representing a Quarterly Plan Review Session (plan_review_cycles).
 * Supports FR-049.
 */
final class PlanReviewCycleDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $procurementPlanId,
        public readonly int $activeVersionId,
        public readonly int $fiscalYear,
        public readonly ReviewQuarter $reviewQuarter,
        public readonly ReviewCycleStatus $reviewStatus,
        public readonly ?ReviewOutcome $reviewOutcome = null,
        public readonly ?int $reviewedByUserId = null,
        public readonly ?string $completedAt = null,
        public readonly ?string $reviewNotes = null,
        public readonly string $createdAt = '',
        public readonly int $createdBy = 0
    ) {
    }

    /**
     * Reconstruct DTO from database associative array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            procurementPlanId: (int)($data['procurement_plan_id'] ?? 0),
            activeVersionId: (int)($data['active_version_id'] ?? 0),
            fiscalYear: (int)($data['fiscal_year'] ?? 0),
            reviewQuarter: ReviewQuarter::from((string)($data['review_quarter'] ?? 'Q1')),
            reviewStatus: ReviewCycleStatus::from((string)($data['review_status'] ?? 'PENDING')),
            reviewOutcome: !empty($data['review_outcome']) ? ReviewOutcome::from((string)$data['review_outcome']) : null,
            reviewedByUserId: isset($data['reviewed_by_user_id']) ? (int)$data['reviewed_by_user_id'] : null,
            completedAt: isset($data['completed_at']) ? (string)$data['completed_at'] : null,
            reviewNotes: isset($data['review_notes']) ? (string)$data['review_notes'] : null,
            createdAt: (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
            createdBy: (int)($data['created_by'] ?? 0)
        );
    }

    /**
     * Convert DTO to associative array for persistence.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'procurement_plan_id' => $this->procurementPlanId,
            'active_version_id' => $this->activeVersionId,
            'fiscal_year' => $this->fiscalYear,
            'review_quarter' => $this->reviewQuarter->value,
            'review_status' => $this->reviewStatus->value,
            'review_outcome' => $this->reviewOutcome?->value,
            'reviewed_by_user_id' => $this->reviewedByUserId,
            'completed_at' => $this->completedAt,
            'review_notes' => $this->reviewNotes,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
        ];
    }
}
