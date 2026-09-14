<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Src\Execution\Domain\DTO\WorkflowActionLogDTO;

/**
 * Persistence contract for append-only Workflow Action Logs (workflow_action_logs).
 */
interface WorkflowActionLogRepositoryInterface
{
    public function create(array $data): int;

    public function findById(int $id): ?WorkflowActionLogDTO;

    /**
     * @return WorkflowActionLogDTO[]
     */
    public function findByDocument(string $documentType, int $documentId): array;

    /**
     * Test teardown only.
     */
    public function deleteByDocument(string $documentType, int $documentId): int;
}
