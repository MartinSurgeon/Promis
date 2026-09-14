<?php

declare(strict_types=1);

namespace Promis\Core\Support;

/**
 * Lightweight Zero-Dependency Environment File (.env) Loader.
 */
final class Env
{
    private static array $variables = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from a .env file.
     */
    public static function load(string $filePath): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip enclosing quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            self::$variables[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$variables[$key])) {
            return self::castValue(self::$variables[$key]);
        }

        $val = getenv($key);
        if ($val !== false) {
            return self::castValue($val);
        }

        if (isset($_ENV[$key])) {
            return self::castValue($_ENV[$key]);
        }

        return $default;
    }

    /**
     * Cast string representation to primitive type.
     */
    private static function castValue(string $value): mixed
    {
        $lower = strtolower($value);
        return match ($lower) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => is_numeric($value) ? (str_contains($value, '.') ? (float)$value : (int)$value) : $value,
        };
    }
}
