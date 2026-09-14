<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

/**
 * Data Transfer Object representing a configured workflow definition (workflow_definitions).
 */
final class WorkflowDefinitionDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $workflowCode,
        public readonly string $documentType,
        public readonly ?int $entityTypeId,
        public readonly string $workflowName,
        public readonly bool $isActive,
        public readonly ?string $createdAt = null,
        public readonly ?int $createdBy = null,
        public readonly ?string $updatedAt = null,
        public readonly ?int $updatedBy = null,
        public readonly ?string $entityTypeName = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            workflowCode: (string)$data['workflow_code'],
            documentType: (string)$data['document_type'],
            entityTypeId: !empty($data['entity_type_id']) ? (int)$data['entity_type_id'] : null,
            workflowName: (string)$data['workflow_name'],
            isActive: !empty($data['is_active']),
            createdAt: $data['created_at'] ?? null,
            createdBy: !empty($data['created_by']) ? (int)$data['created_by'] : null,
            updatedAt: $data['updated_at'] ?? null,
            updatedBy: !empty($data['updated_by']) ? (int)$data['updated_by'] : null,
            entityTypeName: $data['entity_type_name'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workflow_code' => $this->workflowCode,
            'document_type' => $this->documentType,
            'entity_type_id' => $this->entityTypeId,
            'workflow_name' => $this->workflowName,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'entity_type_name' => $this->entityTypeName,
        ];
    }
}
