<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Src\Execution\Domain\DTO\AuditLogDTO;

/**
 * Persistence contract for Institutional Audit Logs (audit_logs).
 */
interface AuditLogRepositoryInterface
{
    public function create(array $data): int;

    public function findById(int $id): ?AuditLogDTO;

    /**
     * @return AuditLogDTO[]
     */
    public function findByRecord(string $recordType, int $recordId): array;

    /**
     * Test teardown only.
     */
    public function deleteByRecord(string $recordType, int $recordId): int;
}
