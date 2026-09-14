<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Src\Execution\Domain\DTO\WorkflowDefinitionDTO;

/**
 * Persistence contract for Workflow Definitions (workflow_definitions).
 */
interface WorkflowDefinitionRepositoryInterface
{
    public function findById(int $id): ?WorkflowDefinitionDTO;

    public function findByCode(string $code): ?WorkflowDefinitionDTO;

    /**
     * Look up active workflow definitions for a document type and specific entity type.
     * @return WorkflowDefinitionDTO[]
     */
    public function findActiveByDocumentAndEntityType(string $documentType, int $entityTypeId): array;

    /**
     * Look up active global fallback workflow definitions for a document type (entity_type_id IS NULL).
     * @return WorkflowDefinitionDTO[]
     */
    public function findActiveGlobalByDocument(string $documentType): array;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
