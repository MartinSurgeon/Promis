<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

use Promis\Src\Planning\Domain\Decimal;

/**
 * Data contract representing a line item of a Requisition (requisition_items table).
 * Preserves requested quantity and estimated costs as exact decimal strings.
 */
final class RequisitionItemDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $requisitionId,
        public readonly int $procurementPlanItemId,
        public readonly int $standardItemId,
        public readonly string $itemDescription,
        public readonly int $uomId,
        public readonly string $requestedQuantity,
        public readonly string $estimatedUnitCost,
        public readonly string $estimatedTotalCost,
        public readonly ?string $itemJustification = null,
        public readonly string $createdAt = '',
        public readonly int $createdBy = 0,
        public readonly ?string $updatedAt = null,
        public readonly ?int $updatedBy = null,
        public readonly ?string $itemCode = null,
        public readonly ?string $itemName = null,
        public readonly ?string $uomCode = null,
        public readonly ?string $uomName = null,
        public readonly ?string $plannedQuantity = null,
        public readonly ?string $targetQuarter = null
    ) {
    }

    /**
     * Reconstruct DTO from database associative array.
     */
    public static function fromArray(array $data): self
    {
        $qty = isset($data['requested_quantity'])
            ? Decimal::normalize($data['requested_quantity'], 2)
            : '0.00';

        $unitCost = isset($data['estimated_unit_cost'])
            ? Decimal::normalize($data['estimated_unit_cost'], 2)
            : '0.00';

        $totalCost = isset($data['estimated_total_cost'])
            ? Decimal::normalize($data['estimated_total_cost'], 2)
            : Decimal::mul($qty, $unitCost, 2);

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            requisitionId: (int)($data['requisition_id'] ?? 0),
            procurementPlanItemId: (int)($data['procurement_plan_item_id'] ?? 0),
            standardItemId: (int)($data['standard_item_id'] ?? 0),
            itemDescription: (string)($data['item_description'] ?? ''),
            uomId: (int)($data['uom_id'] ?? 0),
            requestedQuantity: $qty,
            estimatedUnitCost: $unitCost,
            estimatedTotalCost: $totalCost,
            itemJustification: isset($data['item_justification']) ? (string)$data['item_justification'] : null,
            createdAt: (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
            createdBy: (int)($data['created_by'] ?? 0),
            updatedAt: isset($data['updated_at']) ? (string)$data['updated_at'] : null,
            updatedBy: isset($data['updated_by']) ? (int)$data['updated_by'] : null,
            itemCode: isset($data['item_code']) ? (string)$data['item_code'] : null,
            itemName: isset($data['item_name']) ? (string)$data['item_name'] : null,
            uomCode: isset($data['uom_code']) ? (string)$data['uom_code'] : null,
            uomName: isset($data['uom_name']) ? (string)$data['uom_name'] : null,
            plannedQuantity: isset($data['planned_quantity']) ? Decimal::normalize($data['planned_quantity'], 2) : null,
            targetQuarter: isset($data['target_quarter']) ? (string)$data['target_quarter'] : null
        );
    }

    /**
     * Convert DTO to associative array for persistence.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'requisition_id' => $this->requisitionId,
            'procurement_plan_item_id' => $this->procurementPlanItemId,
            'standard_item_id' => $this->standardItemId,
            'item_description' => $this->itemDescription,
            'uom_id' => $this->uomId,
            'requested_quantity' => $this->requestedQuantity,
            'estimated_unit_cost' => $this->estimatedUnitCost,
            'estimated_total_cost' => $this->estimatedTotalCost,
            'item_justification' => $this->itemJustification,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
        ];
    }
}
