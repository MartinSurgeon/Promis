<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain;

use InvalidArgumentException;
use Promis\Src\Execution\Domain\DTO\DrawdownResult;
use Promis\Src\Planning\Domain\Decimal;

/**
 * Service-owned Drawdown Engine for procurement plan items.
 * Computes exact cumulative balances using arbitrary-precision bcmath at scale 2.
 * Zero floating-point arithmetic allowed.
 */
final class DrawdownCalculator
{
    /**
     * Compute remaining balance and quota enforcement for a planned item.
     *
     * @param string|int|float $approvedPlannedQuantity Total planned quantity in the approved plan version
     * @param string|int|float $previouslyRequestedQuantity Cumulative requested quantity across prior non-rejected requisitions
     * @param string|int|float $currentRequestQuantity The quantity being requested in the current requisition
     * @param int $planItemId Optional plan item ID
     * @return DrawdownResult
     * @throws InvalidArgumentException
     */
    public static function calculate(
        mixed $approvedPlannedQuantity,
        mixed $previouslyRequestedQuantity,
        mixed $currentRequestQuantity,
        int $planItemId = 0
    ): DrawdownResult {
        if (!Decimal::isValid($approvedPlannedQuantity)) {
            throw new InvalidArgumentException("Invalid approved planned quantity: " . var_export($approvedPlannedQuantity, true));
        }
        if (!Decimal::isValid($previouslyRequestedQuantity)) {
            throw new InvalidArgumentException("Invalid previously requested quantity: " . var_export($previouslyRequestedQuantity, true));
        }
        if (!Decimal::isValid($currentRequestQuantity)) {
            throw new InvalidArgumentException("Invalid current request quantity: " . var_export($currentRequestQuantity, true));
        }

        $planned = Decimal::normalize($approvedPlannedQuantity, 2);
        $previous = Decimal::normalize($previouslyRequestedQuantity, 2);
        $current = Decimal::normalize($currentRequestQuantity, 2);

        if (Decimal::lt($planned, '0.00')) {
            throw new InvalidArgumentException("Approved planned quantity cannot be negative ({$planned}).");
        }
        if (Decimal::lt($previous, '0.00')) {
            throw new InvalidArgumentException("Previously requested quantity cannot be negative ({$previous}).");
        }
        if (Decimal::lte($current, '0.00')) {
            throw new InvalidArgumentException("Current requested quantity must be greater than zero ({$current}).");
        }

        // Remaining before current request
        $remainingBefore = Decimal::sub($planned, $previous, 2);

        // Remaining after current request
        $remainingAfter = Decimal::sub($remainingBefore, $current, 2);

        $isExceeded = Decimal::lt($remainingAfter, '0.00');
        $effectiveRemaining = Decimal::gte($remainingBefore, '0.00') ? $remainingBefore : '0.00';
        $excessQuantity = $isExceeded
            ? Decimal::sub($current, $effectiveRemaining, 2)
            : '0.00';

        return new DrawdownResult(
            planItemId: $planItemId,
            approvedPlannedQuantity: $planned,
            previouslyRequestedQuantity: $previous,
            currentRequestQuantity: $current,
            remainingBefore: $remainingBefore,
            remainingAfter: $remainingAfter,
            isExceeded: $isExceeded,
            excessQuantity: $excessQuantity
        );
    }
}
