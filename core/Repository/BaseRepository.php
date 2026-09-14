<?php

declare(strict_types=1);

namespace Promis\Core\Repository;

use PDO;
use PDOStatement;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\DatabaseException;
use Promis\Core\Support\Logger;

/**
 * Base Repository Class.
 * Encapsulates all database query execution exclusively using PDO prepared statements.
 * Zero business logic allowed at this layer.
 */
abstract class BaseRepository
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Prepare and execute a parameterized SQL query.
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $paramType = match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_null($value) => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };
                // Support both positional (1-indexed or 0-indexed) and named parameters
                $paramKey = is_int($key) ? $key + 1 : $key;
                $stmt->bindValue($paramKey, $value, $paramType);
            }
            $stmt->execute();
            return $stmt;
        } catch (\PDOException $e) {
            Logger::error('Repository query execution failed', [
                'sql' => $sql,
                'params' => Logger::maskSensitive($params),
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);
            throw new DatabaseException('Database operation failed.', $e);
        }
    }

    /**
     * Fetch all matching rows as an associative array.
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single row as an associative array or null if not found.
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query and return affected rows.
     */
    protected function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Retrieve the last inserted surrogate auto-increment ID.
     */
    protected function lastInsertId(): int
    {
        return (int)$this->db->lastInsertId();
    }
}
