<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain\DTO;

use Promis\Src\Planning\Domain\PlanStatus;

/**
 * Data contract representing a Procurement Plan root record (procurement_plans).
 */
final class PlanDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $planNumber,
        public readonly int $planningEntityId,
        public readonly int $fiscalYear,
        public readonly ?int $currentVersionId,
        public readonly PlanStatus $status,
        public readonly string $createdAt,
        public readonly int $createdBy,
        public readonly ?string $updatedAt = null,
        public readonly ?int $updatedBy = null,
        public readonly ?PlanVersionDTO $currentVersion = null
    ) {
    }

    /**
     * Reconstruct DTO from database associative array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            planNumber: (string)($data['plan_number'] ?? ''),
            planningEntityId: (int)($data['planning_entity_id'] ?? 0),
            fiscalYear: (int)($data['fiscal_year'] ?? 0),
            currentVersionId: isset($data['current_version_id']) ? (int)$data['current_version_id'] : null,
            status: PlanStatus::from((string)($data['status'] ?? 'DRAFT')),
            createdAt: (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
            createdBy: (int)($data['created_by'] ?? 0),
            updatedAt: isset($data['updated_at']) ? (string)$data['updated_at'] : null,
            updatedBy: isset($data['updated_by']) ? (int)$data['updated_by'] : null
        );
    }

    /**
     * Convert DTO to associative array for persistence.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'plan_number' => $this->planNumber,
            'planning_entity_id' => $this->planningEntityId,
            'fiscal_year' => $this->fiscalYear,
            'current_version_id' => $this->currentVersionId,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
        ];
    }
}
