<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain\DTO;

/**
 * Data contract representing a Plan Revision Provenance Record (plan_revision_records).
 * Supports FR-050.
 */
final class PlanRevisionRecordDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $procurementPlanId,
        public readonly ?int $reviewCycleId,
        public readonly int $priorVersionId,
        public readonly int $newVersionId,
        public readonly string $revisionJustification,
        public readonly int $submittedByUserId,
        public readonly string $submittedAt,
        public readonly ?int $approvedByUserId = null,
        public readonly ?string $approvedAt = null
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
            reviewCycleId: isset($data['review_cycle_id']) ? (int)$data['review_cycle_id'] : null,
            priorVersionId: (int)($data['prior_version_id'] ?? 0),
            newVersionId: (int)($data['new_version_id'] ?? 0),
            revisionJustification: (string)($data['revision_justification'] ?? ''),
            submittedByUserId: (int)($data['submitted_by_user_id'] ?? 0),
            submittedAt: (string)($data['submitted_at'] ?? date('Y-m-d H:i:s')),
            approvedByUserId: isset($data['approved_by_user_id']) ? (int)$data['approved_by_user_id'] : null,
            approvedAt: isset($data['approved_at']) ? (string)$data['approved_at'] : null
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
            'review_cycle_id' => $this->reviewCycleId,
            'prior_version_id' => $this->priorVersionId,
            'new_version_id' => $this->newVersionId,
            'revision_justification' => $this->revisionJustification,
            'submitted_by_user_id' => $this->submittedByUserId,
            'submitted_at' => $this->submittedAt,
            'approved_by_user_id' => $this->approvedByUserId,
            'approved_at' => $this->approvedAt,
        ];
    }
}
