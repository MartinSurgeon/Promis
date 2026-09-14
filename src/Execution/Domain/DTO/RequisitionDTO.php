<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Planning\Domain\Decimal;

/**
 * Data contract representing a Requisition header record (requisitions table).
 * Preserves monetary amounts as exact decimal strings.
 */
final class RequisitionDTO
{
    /**
     * @param RequisitionItemDTO[]|null $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $requisitionNumber,
        public readonly int $planningEntityId,
        public readonly int $fiscalYear,
        public readonly ?int $approvedPlanVersionId,
        public readonly RequisitionStatus $status,
        public readonly string $totalEstimatedCost,
        public readonly string $justification,
        public readonly ?string $submittedAt = null,
        public readonly ?int $submittedBy = null,
        public readonly string $createdAt = '',
        public readonly int $createdBy = 0,
        public readonly ?string $updatedAt = null,
        public readonly ?int $updatedBy = null,
        public readonly ?array $items = null,
        public readonly ?string $planningEntityName = null,
        public readonly ?string $planningEntityCode = null,
        public readonly ?string $planVersionNumber = null
    ) {
    }

    /**
     * Reconstruct DTO from database associative array.
     */
    public static function fromArray(array $data): self
    {
        $totalCost = isset($data['total_estimated_cost'])
            ? Decimal::normalize($data['total_estimated_cost'], 2)
            : '0.00';

        $rawStatus = $data['status'] ?? 'DRAFT';
        $status = $rawStatus instanceof RequisitionStatus
            ? $rawStatus
            : RequisitionStatus::from((string)$rawStatus);

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            requisitionNumber: (string)($data['requisition_number'] ?? ''),
            planningEntityId: (int)($data['planning_entity_id'] ?? 0),
            fiscalYear: (int)($data['fiscal_year'] ?? 0),
            approvedPlanVersionId: isset($data['approved_plan_version_id']) ? (int)$data['approved_plan_version_id'] : null,
            status: $status,
            totalEstimatedCost: $totalCost,
            justification: (string)($data['justification'] ?? ''),
            submittedAt: isset($data['submitted_at']) ? (string)$data['submitted_at'] : null,
            submittedBy: isset($data['submitted_by']) ? (int)$data['submitted_by'] : null,
            createdAt: (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
            createdBy: (int)($data['created_by'] ?? 0),
            updatedAt: isset($data['updated_at']) ? (string)$data['updated_at'] : null,
            updatedBy: isset($data['updated_by']) ? (int)$data['updated_by'] : null,
            items: null,
            planningEntityName: isset($data['entity_name']) ? (string)$data['entity_name'] : null,
            planningEntityCode: isset($data['entity_code']) ? (string)$data['entity_code'] : null,
            planVersionNumber: isset($data['version_number']) ? (string)$data['version_number'] : null
        );
    }

    /**
     * Convert DTO to associative array for persistence.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'requisition_number' => $this->requisitionNumber,
            'planning_entity_id' => $this->planningEntityId,
            'fiscal_year' => $this->fiscalYear,
            'approved_plan_version_id' => $this->approvedPlanVersionId,
            'status' => $this->status->value,
            'total_estimated_cost' => $this->totalEstimatedCost,
            'justification' => $this->justification,
            'submitted_at' => $this->submittedAt,
            'submitted_by' => $this->submittedBy,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
        ];
    }
}
