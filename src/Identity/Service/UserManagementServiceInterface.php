<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Service;

use Promis\Src\Identity\Domain\DTO\AssignRoleEntityDTO;
use Promis\Src\Identity\Domain\DTO\CreateUserDTO;
use Promis\Src\Identity\Domain\DTO\UpdateUserDTO;

/**
 * Service contract for User and Entity Scoped Role Management.
 */
interface UserManagementServiceInterface
{
    public function getUsersPaginated(
        int $page = 1,
        int $limit = 15,
        string $search = '',
        string $roleFilter = '',
        ?int $entityFilter = null,
        string $statusFilter = ''
    ): array;

    public function getUserDetails(int $userId): array;

    public function onboardUser(
        CreateUserDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): int;

    public function updateUser(
        UpdateUserDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool;

    public function toggleUserStatus(
        int $userId,
        string $status,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool;

    public function assignRoleEntity(
        AssignRoleEntityDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): int;

    public function revokeRoleAssignment(
        int $assignmentId,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool;

    public function setPrimaryAssignment(
        int $userId,
        int $assignmentId,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool;

    public function getLookups(): array;
}
