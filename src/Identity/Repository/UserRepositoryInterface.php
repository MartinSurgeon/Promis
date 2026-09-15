<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use Promis\Src\Identity\Domain\DTO\UserDTO;

/**
 * Contract for User identity and role persistence operations.
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?UserDTO;

    public function findByUsername(string $username): ?UserDTO;

    public function findByEmail(string $email): ?UserDTO;

    public function findByUsernameOrEmail(string $identifier): ?UserDTO;

    public function create(array $data): int;

    public function updateStatus(int $id, string $status): bool;

    public function updatePassword(int $id, string $passwordHash): bool;

    public function updateLastLogin(int $id, ?string $timestamp = null): bool;

    public function updateProfile(int $id, array $data, ?int $updatedBy = null): bool;

    public function findPaginatedUsers(
        int $page = 1,
        int $limit = 15,
        string $search = '',
        string $roleFilter = '',
        ?int $entityFilter = null,
        string $statusFilter = ''
    ): array;

    public function countFilteredUsers(
        string $search = '',
        string $roleFilter = '',
        ?int $entityFilter = null,
        string $statusFilter = ''
    ): int;

    public function getSummaryMetrics(): array;
}
