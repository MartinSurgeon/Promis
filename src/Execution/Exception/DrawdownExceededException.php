<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Exception;

/**
 * Exception thrown when a requested requisition quantity exceeds the remaining approved plan quota.
 */
class DrawdownExceededException extends RequisitionException
{
    public function __construct(
        string $message,
        public readonly int $planItemId = 0,
        public readonly string $requestedQuantity = '0.00',
        public readonly string $remainingBalance = '0.00'
    ) {
        parent::__construct($message);
    }
}
