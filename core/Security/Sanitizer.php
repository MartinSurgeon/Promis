<?php

declare(strict_types=1);

namespace Promis\Core\Security;

/**
 * UTF-8 Output Sanitizer for XSS Prevention.
 */
final class Sanitizer
{
    /**
     * Escape a string or value for safe HTML output.
     */
    public static function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escape an array of values recursively.
     */
    public static function escapeArray(array $data): array
    {
        $escaped = [];
        foreach ($data as $key => $value) {
            $escapedKey = is_string($key) ? self::escape($key) : $key;
            if (is_array($value)) {
                $escaped[$escapedKey] = self::escapeArray($value);
            } else {
                $escaped[$escapedKey] = self::escape($value);
            }
        }
        return $escaped;
    }
}
