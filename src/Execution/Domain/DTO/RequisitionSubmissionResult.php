<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

/**
 * Result DTO returned upon successful requisition submission.
 */
final class RequisitionSubmissionResult
{
    /**
     * @param RequisitionBalanceSnapshotDTO[] $snapshots
     * @param DrawdownResult[] $drawdownResults
     */
    public function __construct(
        public readonly RequisitionDTO $requisition,
        public readonly array $snapshots,
        public readonly array $drawdownResults
    ) {
    }
}
