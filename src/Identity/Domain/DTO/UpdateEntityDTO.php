<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Data Transfer Object for updating an existing Planning Entity.
 * Entity code is intentionally excluded — read-only after creation.
 */
final class UpdateEntityDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $entityName,
        public readonly int $entityTypeId,
        public readonly int $campusId,
        public readonly ?int $parentEntityId = null,
        public readonly ?int $headUserId = null,
        public readonly ?int $planningOfficerId = null
    ) {}

    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            entityName: trim((string)($data['entity_name'] ?? '')),
            entityTypeId: (int)($data['entity_type_id'] ?? 0),
            campusId: (int)($data['campus_id'] ?? 0),
            parentEntityId: !empty($data['parent_entity_id']) ? (int)$data['parent_entity_id'] : null,
            headUserId: !empty($data['head_user_id']) ? (int)$data['head_user_id'] : null,
            planningOfficerId: !empty($data['planning_officer_id']) ? (int)$data['planning_officer_id'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'entity_name' => $this->entityName,
            'entity_type_id' => $this->entityTypeId,
            'campus_id' => $this->campusId,
            'parent_entity_id' => $this->parentEntityId,
            'head_user_id' => $this->headUserId,
            'planning_officer_id' => $this->planningOfficerId,
        ];
    }
}
