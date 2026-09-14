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
}
