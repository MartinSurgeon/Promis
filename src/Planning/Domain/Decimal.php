<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain;

use InvalidArgumentException;

/**
 * Arbitrary-precision decimal arithmetic utility for financial calculations.
 * Encapsulates PHP bcmath functions to guarantee consistent scale and eliminate float drift.
 */
final class Decimal
{
    public const DEFAULT_SCALE = 2;

    /**
     * Determine if a value is a valid numeric representation for decimal conversion.
     */
    public static function isValid(mixed $value): bool
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return false;
        }

        $str = trim((string)$value);
        if ($str === '') {
            return false;
        }

        return (bool)preg_match('/^-?\d+(\.\d+)?$/', $str);
    }

    /**
     * Normalize a numeric value into a fixed-scale decimal string.
     * Uses pure BCMath arithmetic with half-up rounding for extra fractional digits.
     *
     * @throws InvalidArgumentException If value is null, empty, or non-numeric
     */
    public static function normalize(mixed $value, int $scale = self::DEFAULT_SCALE): string
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException('Invalid numeric value for decimal normalization: ' . var_export($value, true));
        }

        $str = trim((string)$value);
        $parts = explode('.', $str, 2);

        // Apply half-up rounding if input has more fractional digits than target scale
        if (isset($parts[1]) && strlen($parts[1]) > $scale) {
            $isNegative = str_starts_with($str, '-');
            $adder = ($isNegative ? '-0.' : '0.') . str_repeat('0', $scale) . '5';
            return bcadd($str, $adder, $scale);
        }

        return bcadd($str, '0', $scale);
    }

    /**
     * Addition: $a + $b
     */
    public static function add(string $a, string $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcadd(self::normalize($a, $scale), self::normalize($b, $scale), $scale);
    }

    /**
     * Subtraction: $a - $b
     */
    public static function sub(string $a, string $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcsub(self::normalize($a, $scale), self::normalize($b, $scale), $scale);
    }

    /**
     * Multiplication: $a * $b
     */
    public static function mul(string $a, string $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcmul(self::normalize($a, $scale), self::normalize($b, $scale), $scale);
    }

    /**
     * Compare two decimal strings: returns -1 if $a < $b, 0 if $a == $b, 1 if $a > $b.
     */
    public static function comp(string $a, string $b, int $scale = self::DEFAULT_SCALE): int
    {
        return bccomp(self::normalize($a, $scale), self::normalize($b, $scale), $scale);
    }

    /**
     * Check if $a == $b
     */
    public static function eq(string $a, string $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) === 0;
    }

    /**
     * Check if $a > $b
     */
    public static function gt(string $a, string $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) === 1;
    }

    /**
     * Check if $a >= $b
     */
    public static function gte(string $a, string $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) >= 0;
    }

    /**
     * Check if $a < $b
     */
    public static function lt(string $a, string $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) === -1;
    }

    /**
     * Check if $a <= $b
     */
    public static function lte(string $a, string $b, int $scale = self::DEFAULT_SCALE): bool
    {
        return self::comp($a, $b, $scale) <= 0;
    }
}
