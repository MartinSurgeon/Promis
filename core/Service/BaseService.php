<?php

declare(strict_types=1);

namespace Promis\Core\Service;

use PDO;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AppException;
use Promis\Core\Support\Logger;
use Throwable;

/**
 * Base Service Class.
 * Orchestrates business logic and controls database transactions.
 * Services must never output HTML or contain raw presentation artifacts.
 */
abstract class BaseService
{
    protected PDO $db;
    protected int $transactionDepth = 0;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Begin a database transaction.
     */
    protected function beginTransaction(): bool
    {
        if ($this->transactionDepth === 0 || !$this->db->inTransaction()) {
            $this->transactionDepth = 1;
            return $this->db->beginTransaction();
        }

        $this->transactionDepth++;
        return false;
    }

    /**
     * Commit the active transaction.
     */
    protected function commit(): bool
    {
        if ($this->transactionDepth <= 1) {
            $this->transactionDepth = 0;
            if ($this->db->inTransaction()) {
                return $this->db->commit();
            }
            return false;
        }

        $this->transactionDepth--;
        return true;
    }

    /**
     * Roll back the active transaction.
     */
    protected function rollBack(): bool
    {
        $this->transactionDepth = 0;
        if ($this->db->inTransaction()) {
            return $this->db->rollBack();
        }
        return false;
    }

    /**
     * Check if currently within a database transaction.
     */
    protected function inTransaction(): bool
    {
        return $this->db->inTransaction();
    }

    /**
     * Execute a closure safely inside a managed transaction boundary.
     * Automatically commits on success and rolls back on exception.
     */
    protected function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            Logger::error('Transaction rolled back due to error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
