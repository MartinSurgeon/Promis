<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain\DTO;

use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\TargetQuarter;

/**
 * Data contract representing a line item in a Procurement Plan Version (procurement_plan_items).
 * Supports FR-008. Uses arbitrary-precision decimal strings for all quantities and costs.
 */
final class PlanItemDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $planVersionId,
        public readonly int $standardItemId,
        public readonly string $itemDescription,
        public readonly int $categoryId,
        public readonly int $uomId,
        public readonly string $plannedQuantity,
        public readonly string $estimatedUnitCost,
        public readonly string $estimatedTotalCost,
        public readonly TargetQuarter $targetQuarter,
        public readonly string $fundingSource,
        public readonly ?string $justification = null,
        public readonly string $createdAt = '',
        public readonly int $createdBy = 0,
        public readonly ?string $categoryName = null,
        public readonly ?string $uomCode = null,
        public readonly ?string $itemCode = null
    ) {
    }

    /**
     * Reconstruct DTO from database associative array.
     */
    public static function fromArray(array $data): self
    {
        $qty = isset($data['planned_quantity']) ? Decimal::normalize($data['planned_quantity'], 2) : '0.00';
        $unitCost = isset($data['estimated_unit_cost']) ? Decimal::normalize($data['estimated_unit_cost'], 2) : '0.00';
        $totalCost = isset($data['estimated_total_cost']) 
            ? Decimal::normalize($data['estimated_total_cost'], 2) 
            : Decimal::mul($qty, $unitCost, 2);

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            planVersionId: (int)($data['plan_version_id'] ?? 0),
            standardItemId: (int)($data['standard_item_id'] ?? 0),
            itemDescription: (string)($data['item_description'] ?? ''),
            categoryId: (int)($data['category_id'] ?? 0),
            uomId: (int)($data['uom_id'] ?? 0),
            plannedQuantity: $qty,
            estimatedUnitCost: $unitCost,
            estimatedTotalCost: $totalCost,
            targetQuarter: TargetQuarter::from((string)($data['target_quarter'] ?? 'Q1')),
            fundingSource: (string)($data['funding_source'] ?? ''),
            justification: isset($data['justification']) ? (string)$data['justification'] : null,
            createdAt: (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
            createdBy: (int)($data['created_by'] ?? 0),
            categoryName: isset($data['category_name']) ? (string)$data['category_name'] : null,
            uomCode: isset($data['uom_code']) ? (string)$data['uom_code'] : null,
            itemCode: isset($data['item_code']) ? (string)$data['item_code'] : null
        );
    }

    /**
     * Convert DTO to associative array for persistence.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'plan_version_id' => $this->planVersionId,
            'standard_item_id' => $this->standardItemId,
            'item_description' => $this->itemDescription,
            'category_id' => $this->categoryId,
            'uom_id' => $this->uomId,
            'planned_quantity' => $this->plannedQuantity,
            'estimated_unit_cost' => $this->estimatedUnitCost,
            'estimated_total_cost' => $this->estimatedTotalCost,
            'target_quarter' => $this->targetQuarter->value,
            'funding_source' => $this->fundingSource,
            'justification' => $this->justification,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
        ];
    }
}
