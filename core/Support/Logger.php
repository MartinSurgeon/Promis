<?php

declare(strict_types=1);

namespace Promis\Core\Support;

/**
 * Protected Diagnostic Application Logger.
 * Strictly masks passwords, session IDs, CSRF tokens, and credentials from log files.
 */
final class Logger
{
    private static ?string $logFile = null;

    private static array $sensitiveKeys = [
        'password',
        'password_hash',
        'password_confirmation',
        'token',
        'csrf',
        '_csrf_token',
        'secret',
        'app_key',
        'key',
        'authorization',
        'cookie',
        'session_id',
    ];

    /**
     * Initialize logger configuration.
     */
    public static function init(string $logFilePath): void
    {
        $dir = dirname($logFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        self::$logFile = $logFilePath;
    }

    /**
     * Log an informational message.
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    /**
     * Log a warning message.
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log an error message.
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * Core log write operation with sensitive value masking.
     */
    private static function log(string $level, string $message, array $context): void
    {
        if (self::$logFile === null) {
            $defaultPath = dirname(__DIR__, 2) . '/storage/logs/app.log';
            self::init($defaultPath);
        }

        $maskedContext = self::maskSensitive($context);
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($maskedContext) ? ' ' . json_encode($maskedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        $entry = sprintf("[%s] %s: %s%s\n", $timestamp, $level, $message, $contextJson);

        file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Recursively mask sensitive fields.
     */
    public static function maskSensitive(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            $lowerKey = is_string($key) ? strtolower($key) : '';
            $isSensitive = false;

            foreach (self::$sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $masked[$key] = '********';
            } elseif (is_array($value)) {
                $masked[$key] = self::maskSensitive($value);
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }
}
