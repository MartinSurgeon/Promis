<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

/**
 * Command DTO for submitting a requisition for approval.
 */
final class SubmitRequisitionRequest
{
    public function __construct(
        public readonly int $requisitionId,
        public readonly int $actingUserId
    ) {
    }
}
