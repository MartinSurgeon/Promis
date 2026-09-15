<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use Promis\Src\Identity\Domain\DTO\UserEntityRoleDTO;

/**
 * Persistence contract for entity-scoped user role assignments (user_entity_roles).
 */
interface UserEntityRoleRepositoryInterface
{
    /**
     * @return UserEntityRoleDTO[]
     */
    public function findByUserId(int $userId): array;

    public function findById(int $id): ?UserEntityRoleDTO;

    public function findExisting(int $userId, int $planningEntityId, int $roleId): ?UserEntityRoleDTO;

    public function create(array $data): int;

    public function updateStatus(int $id, string $status, int $updatedBy): bool;

    public function delete(int $id): bool;

    public function setPrimary(int $userId, int $assignmentId, int $updatedBy): bool;

    public function clearPrimaryForUser(int $userId, int $updatedBy): bool;

    /**
     * @return array<int, array{user_id: int, roles: array, entities: array}>
     */
    public function getGroupedAssignmentsByUserIds(array $userIds): array;
}
