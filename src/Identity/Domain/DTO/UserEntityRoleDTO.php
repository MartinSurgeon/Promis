<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Data Transfer Object representing an entity-scoped user role assignment (user_entity_roles).
 */
class UserEntityRoleDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $planningEntityId,
        public readonly int $roleId,
        public readonly bool $isPrimary,
        public readonly string $status,
        public readonly ?string $assignedAt,
        public readonly ?int $assignedBy,
        public readonly ?string $updatedAt = null,
        public readonly ?int $updatedBy = null,
        public readonly ?string $roleCode = null,
        public readonly ?string $roleTitle = null,
        public readonly ?string $entityCode = null,
        public readonly ?string $entityName = null
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            planningEntityId: (int)$row['planning_entity_id'],
            roleId: (int)$row['role_id'],
            isPrimary: (bool)($row['is_primary'] ?? false),
            status: (string)($row['status'] ?? 'ACTIVE'),
            assignedAt: $row['assigned_at'] ?? null,
            assignedBy: isset($row['assigned_by']) ? (int)$row['assigned_by'] : null,
            updatedAt: $row['updated_at'] ?? null,
            updatedBy: isset($row['updated_by']) ? (int)$row['updated_by'] : null,
            roleCode: $row['role_code'] ?? null,
            roleTitle: $row['role_title'] ?? null,
            entityCode: $row['entity_code'] ?? null,
            entityName: $row['entity_name'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'planning_entity_id' => $this->planningEntityId,
            'role_id' => $this->roleId,
            'is_primary' => $this->isPrimary,
            'status' => $this->status,
            'assigned_at' => $this->assignedAt,
            'assigned_by' => $this->assignedBy,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'role_code' => $this->roleCode,
            'role_title' => $this->roleTitle,
            'entity_code' => $this->entityCode,
            'entity_name' => $this->entityName,
        ];
    }
}
