<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

use Promis\Src\Planning\Domain\Decimal;

/**
 * Data contract representing a point-in-time drawdown evidentiary snapshot (requisition_balance_snapshots).
 * Preserves all planned, requested, and remaining balance quantities as exact decimal strings.
 */
final class RequisitionBalanceSnapshotDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $requisitionId,
        public readonly int $requisitionItemId,
        public readonly int $planItemId,
        public readonly string $workflowEvent,
        public readonly string $approvedPlannedQuantity,
        public readonly string $previouslyRequestedQuantity,
        public readonly string $currentRequestQuantity,
        public readonly string $remainingBefore,
        public readonly string $remainingAfter,
        public readonly string $snapshotTimestamp,
        public readonly int $recordedByUserId
    ) {
    }

    /**
     * Reconstruct DTO from database associative array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            requisitionId: (int)($data['requisition_id'] ?? 0),
            requisitionItemId: (int)($data['requisition_item_id'] ?? 0),
            planItemId: (int)($data['plan_item_id'] ?? 0),
            workflowEvent: (string)($data['workflow_event'] ?? ''),
            approvedPlannedQuantity: Decimal::normalize($data['approved_planned_quantity'] ?? '0.00', 2),
            previouslyRequestedQuantity: Decimal::normalize($data['previously_requested_quantity'] ?? '0.00', 2),
            currentRequestQuantity: Decimal::normalize($data['current_request_quantity'] ?? '0.00', 2),
            remainingBefore: Decimal::normalize($data['remaining_before'] ?? '0.00', 2),
            remainingAfter: Decimal::normalize($data['remaining_after'] ?? '0.00', 2),
            snapshotTimestamp: (string)($data['snapshot_timestamp'] ?? date('Y-m-d H:i:s')),
            recordedByUserId: (int)($data['recorded_by_user_id'] ?? 0)
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
            'requisition_item_id' => $this->requisitionItemId,
            'plan_item_id' => $this->planItemId,
            'workflow_event' => $this->workflowEvent,
            'approved_planned_quantity' => $this->approvedPlannedQuantity,
            'previously_requested_quantity' => $this->previouslyRequestedQuantity,
            'current_request_quantity' => $this->currentRequestQuantity,
            'remaining_before' => $this->remainingBefore,
            'remaining_after' => $this->remainingAfter,
            'snapshot_timestamp' => $this->snapshotTimestamp,
            'recorded_by_user_id' => $this->recordedByUserId,
        ];
    }
}
