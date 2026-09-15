<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Data Transfer Object for assigning a role scoped to a planning entity.
 */
class AssignRoleEntityDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly int $planningEntityId,
        public readonly int $roleId,
        public readonly bool $isPrimary = false,
        public readonly string $status = 'ACTIVE'
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int)($data['user_id'] ?? 0),
            planningEntityId: (int)($data['planning_entity_id'] ?? 0),
            roleId: (int)($data['role_id'] ?? 0),
            isPrimary: (bool)($data['is_primary'] ?? false),
            status: in_array($data['status'] ?? 'ACTIVE', ['ACTIVE', 'INACTIVE'], true) ? (string)$data['status'] : 'ACTIVE'
        );
    }
}
