<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

/**
 * Command DTO for creating a new requisition.
 */
final class CreateRequisitionRequest
{
    /**
     * @param array<int, array> $items
     */
    public function __construct(
        public readonly int $planningEntityId,
        public readonly int $fiscalYear,
        public readonly int $approvedPlanVersionId,
        public readonly string $justification,
        public readonly array $items,
        public readonly int $actingUserId,
        public readonly ?string $requisitionNumber = null
    ) {
    }
}
