<?php

declare(strict_types=1);

namespace Promis\Core\Database;

use PDO;
use PDOException;
use Promis\Core\Exception\DatabaseException;
use Promis\Core\Support\Logger;

/**
 * PDO Database Connection Factory.
 * Manages singleton connection instances with strict security settings for MySQL 8.x and MariaDB 10.4+.
 */
final class Connection
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Set database configuration parameters.
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Retrieve or instantiate the PDO connection.
     */
    public static function get(): PDO
    {
        return self::getInstance();
    }

    /**
     * Retrieve or instantiate the PDO connection singleton.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (empty(self::$config)) {
            $configPath = dirname(__DIR__, 2) . '/config/database.php';
            if (file_exists($configPath)) {
                self::$config = require $configPath;
            }
        }

        $defaultEngine = self::$config['default'] ?? 'mariadb';
        $connConfig = self::$config['connections'][$defaultEngine] ?? [];

        if (empty($connConfig)) {
            throw new DatabaseException("Database configuration for engine '{$defaultEngine}' is missing.");
        }

        $driver = $connConfig['driver'] ?? 'mysql';
        $host = $connConfig['host'] ?? '127.0.0.1';
        $port = $connConfig['port'] ?? 3306;
        $database = $connConfig['database'] ?? 'promis';
        $username = $connConfig['username'] ?? 'root';
        $password = $connConfig['password'] ?? '';
        $charset = $connConfig['charset'] ?? 'utf8mb4';
        $options = $connConfig['options'] ?? [];

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

        // Enforce mandatory security and driver options
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];

        $finalOptions = $options + $defaultOptions;

        try {
            self::$instance = new PDO($dsn, $username, $password, $finalOptions);
            return self::$instance;
        } catch (PDOException $e) {
            // Log masked error details for diagnostics
            Logger::error('Database connection failed', [
                'engine' => $defaultEngine,
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);

            // Throw sanitized exception preventing technical / credential leakage to users
            throw new DatabaseException('Unable to connect to the institutional database server.', $e);
        }
    }

    /**
     * Check database connectivity status safely without throwing.
     */
    public static function ping(): bool
    {
        try {
            $pdo = self::get();
            $stmt = $pdo->query('SELECT 1');
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Close the active connection.
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
