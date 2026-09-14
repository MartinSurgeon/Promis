<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

/**
 * Value object representing the outcome of a plan item drawdown quota calculation.
 * Preserves all values as normalized scale-2 decimal strings.
 */
final class DrawdownResult
{
    public function __construct(
        public readonly int $planItemId,
        public readonly string $approvedPlannedQuantity,
        public readonly string $previouslyRequestedQuantity,
        public readonly string $currentRequestQuantity,
        public readonly string $remainingBefore,
        public readonly string $remainingAfter,
        public readonly bool $isExceeded,
        public readonly string $excessQuantity = '0.00'
    ) {
    }

    public function toArray(): array
    {
        return [
            'plan_item_id' => $this->planItemId,
            'approved_planned_quantity' => $this->approvedPlannedQuantity,
            'previously_requested_quantity' => $this->previouslyRequestedQuantity,
            'current_request_quantity' => $this->currentRequestQuantity,
            'remaining_before' => $this->remainingBefore,
            'remaining_after' => $this->remainingAfter,
            'is_exceeded' => $this->isExceeded,
            'excess_quantity' => $this->excessQuantity,
        ];
    }
}
