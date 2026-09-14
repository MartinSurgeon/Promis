<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain\DTO;

use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\PlanVersionStatus;

/**
 * Data contract representing a Procurement Plan Version snapshot (procurement_plan_versions).
 * Supports FR-049, FR-050. Uses arbitrary-precision decimal string for totalEstimatedCost.
 */
final class PlanVersionDTO
{
    /**
     * @param PlanItemDTO[] $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $procurementPlanId,
        public readonly string $versionNumber,
        public readonly PlanVersionStatus $status,
        public readonly string $totalEstimatedCost,
        public readonly ?string $approvalDate = null,
        public readonly ?int $approvedByUserId = null,
        public readonly ?string $revisionReason = null,
        public readonly string $createdAt = '',
        public readonly int $createdBy = 0,
        public readonly array $items = []
    ) {
    }

    /**
     * Reconstruct DTO from database associative array.
     */
    public static function fromArray(array $data, array $items = []): self
    {
        $totalCost = isset($data['total_estimated_cost'])
            ? Decimal::normalize($data['total_estimated_cost'], 2)
            : '0.00';

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            procurementPlanId: (int)($data['procurement_plan_id'] ?? 0),
            versionNumber: (string)($data['version_number'] ?? '1.0'),
            status: PlanVersionStatus::from((string)($data['status'] ?? 'DRAFT')),
            totalEstimatedCost: $totalCost,
            approvalDate: isset($data['approval_date']) ? (string)$data['approval_date'] : null,
            approvedByUserId: isset($data['approved_by_user_id']) ? (int)$data['approved_by_user_id'] : null,
            revisionReason: isset($data['revision_reason']) ? (string)$data['revision_reason'] : null,
            createdAt: (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
            createdBy: (int)($data['created_by'] ?? 0),
            items: $items
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
            'version_number' => $this->versionNumber,
            'status' => $this->status->value,
            'total_estimated_cost' => $this->totalEstimatedCost,
            'approval_date' => $this->approvalDate,
            'approved_by_user_id' => $this->approvedByUserId,
            'revision_reason' => $this->revisionReason,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
        ];
    }
}
