<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain\DTO;

/**
 * Data contract representing the itemized variance delta between two plan versions (FR-050).
 * Uses arbitrary-precision decimal strings for all quantities, costs, and deltas.
 */
final class ItemVarianceDTO
{
    public function __construct(
        public readonly int $standardItemId,
        public readonly string $itemDescription,
        public readonly string $changeType, // 'ADDED', 'REMOVED', 'MODIFIED', 'UNCHANGED'
        public readonly string $previousQuantity,
        public readonly string $revisedQuantity,
        public readonly string $quantityDelta,
        public readonly string $previousTotalCost,
        public readonly string $revisedTotalCost,
        public readonly string $costDelta,
        public readonly ?string $previousQuarter = null,
        public readonly ?string $revisedQuarter = null
    ) {
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'standard_item_id' => $this->standardItemId,
            'item_description' => $this->itemDescription,
            'change_type' => $this->changeType,
            'previous_quantity' => $this->previousQuantity,
            'revised_quantity' => $this->revisedQuantity,
            'quantity_delta' => $this->quantityDelta,
            'previous_total_cost' => $this->previousTotalCost,
            'revised_total_cost' => $this->revisedTotalCost,
            'cost_delta' => $this->costDelta,
            'previous_quarter' => $this->previousQuarter,
            'revised_quarter' => $this->revisedQuarter,
        ];
    }
}
