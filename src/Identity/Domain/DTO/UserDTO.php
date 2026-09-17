<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Hydrated User Domain DTO.
 */
final class UserDTO
{
    /**
     * @param string[] $roles List of role codes
     * @param int[] $roleIds List of role IDs
     * @param string[] $permissions List of global permission codes
     * @param array<int, string[]> $entityPermissions Map of planningEntityId => permission codes
     * @param string[] $responsibilities List of individual assigned responsibility codes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $phone = null,
        public readonly string $status = 'ACTIVE',
        public readonly ?string $lastLoginAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?int $createdBy = null,
        public readonly array $roles = [],
        public readonly array $roleIds = [],
        public readonly array $permissions = [],
        public readonly array $entityPermissions = [],
        public readonly ?int $positionId = null,
        public readonly ?string $positionCode = null,
        public readonly ?string $positionTitle = null,
        public readonly ?int $assignedPlanningEntityId = null,
        public readonly ?string $assignedEntityName = null,
        public readonly array $responsibilities = []
    ) {
    }

    public function getFullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    public function isActive(): bool
    {
        return strtoupper($this->status) === 'ACTIVE';
    }

    public function isPending(): bool
    {
        return strtoupper($this->status) === 'PENDING';
    }

    public function isSuspended(): bool
    {
        return in_array(strtoupper($this->status), ['SUSPENDED', 'INACTIVE'], true);
    }

    public function getPrimaryRole(): string
    {
        return $this->roles[0] ?? 'Staff';
    }

    public function toSessionArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->getFullName(),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'status' => $this->status,
            'roles' => $this->roles,
            'role_ids' => $this->roleIds,
            'permissions' => $this->permissions,
            'entity_permissions' => $this->entityPermissions,
            'primary_role' => $this->getPrimaryRole(),
            'position_id' => $this->positionId,
            'position_code' => $this->positionCode,
            'position_title' => $this->positionTitle,
            'assigned_planning_entity_id' => $this->assignedPlanningEntityId,
            'assigned_entity_name' => $this->assignedEntityName,
            'responsibilities' => $this->responsibilities,
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'phone' => $this->phone,
            'status' => $this->status,
            'last_login_at' => $this->lastLoginAt,
            'created_at' => $this->createdAt,
            'roles' => $this->roles,
            'role_ids' => $this->roleIds,
            'permissions' => $this->permissions,
            'entity_permissions' => $this->entityPermissions,
            'primary_role' => $this->getPrimaryRole(),
            'position_id' => $this->positionId,
            'position_code' => $this->positionCode,
            'position_title' => $this->positionTitle,
            'assigned_planning_entity_id' => $this->assignedPlanningEntityId,
            'assigned_entity_name' => $this->assignedEntityName,
            'responsibilities' => $this->responsibilities,
        ];
    }
}
