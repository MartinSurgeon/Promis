<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use PDO;
use Promis\Core\Repository\BaseRepository;
use Promis\Src\Identity\Domain\DTO\UserDTO;

/**
 * PDO Repository for User authentication, identity records, and role hydration.
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    private const BASE_SELECT = "
        SELECT `id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, 
               `phone`, `status`, `last_login_at`, `created_at`, `created_by`
        FROM `users`
    ";

    public function findById(int $id): ?UserDTO
    {
        $sql = self::BASE_SELECT . " WHERE `id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByUsername(string $username): ?UserDTO
    {
        $sql = self::BASE_SELECT . " WHERE `username` = :username LIMIT 1";
        $row = $this->fetchOne($sql, ['username' => $username]);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?UserDTO
    {
        $sql = self::BASE_SELECT . " WHERE `email` = :email LIMIT 1";
        $row = $this->fetchOne($sql, ['email' => $email]);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByUsernameOrEmail(string $identifier): ?UserDTO
    {
        $sql = self::BASE_SELECT . " WHERE `username` = :id1 OR `email` = :id2 LIMIT 1";
        $row = $this->fetchOne($sql, ['id1' => $identifier, 'id2' => $identifier]);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO `users` (
                    `username`, `email`, `password_hash`, `first_name`, `last_name`,
                    `phone`, `status`, `created_by`, `created_at`
                ) VALUES (
                    :username, :email, :password_hash, :first_name, :last_name,
                    :phone, :status, :created_by, :created_at
                )";

        $this->execute($sql, [
            'username' => trim($data['username']),
            'email' => strtolower(trim($data['email'])),
            'password_hash' => $data['password_hash'],
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'status' => $data['status'] ?? 'PENDING',
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE `users` SET `status` = :status, `updated_at` = :updated_at WHERE `id` = :id";
        return $this->execute($sql, [
            'id' => $id,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $sql = "UPDATE `users` SET `password_hash` = :password_hash, `updated_at` = :updated_at WHERE `id` = :id";
        return $this->execute($sql, [
            'id' => $id,
            'password_hash' => $passwordHash,
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function updateLastLogin(int $id, ?string $timestamp = null): bool
    {
        $sql = "UPDATE `users` SET `last_login_at` = :last_login WHERE `id` = :id";
        return $this->execute($sql, [
            'id' => $id,
            'last_login' => $timestamp ?? date('Y-m-d H:i:s'),
        ]) > 0;
    }

    /**
     * Hydrate raw row into UserDTO with attached active roles and permissions.
     */
    private function hydrate(array $row): UserDTO
    {
        $userId = (int)$row['id'];

        // 1. Fetch user roles across planning entities
        $roleSql = "SELECT uer.`role_id`, r.`role_code`, uer.`planning_entity_id`
                    FROM `user_entity_roles` uer
                    JOIN `roles` r ON r.`id` = uer.`role_id`
                    WHERE uer.`user_id` = :uid 
                      AND uer.`status` = 'ACTIVE' 
                      AND r.`is_active` = 1";
        $roleRows = $this->fetchAll($roleSql, ['uid' => $userId]);

        $roles = [];
        $roleIds = [];
        foreach ($roleRows as $r) {
            $code = (string)$r['role_code'];
            $rid = (int)$r['role_id'];
            if (!in_array($code, $roles, true)) {
                $roles[] = $code;
            }
            if (!in_array($rid, $roleIds, true)) {
                $roleIds[] = $rid;
            }
        }

        // 2. Fetch user permissions mapped to planning entities
        $permSql = "SELECT uer.`planning_entity_id`, p.`permission_code`
                    FROM `user_entity_roles` uer
                    JOIN `role_permissions` rp ON rp.`role_id` = uer.`role_id`
                    JOIN `permissions` p ON p.`id` = rp.`permission_id`
                    WHERE uer.`user_id` = :uid 
                      AND uer.`status` = 'ACTIVE'";
        $permRows = $this->fetchAll($permSql, ['uid' => $userId]);

        $globalPermissions = [];
        $entityPermissions = [];

        foreach ($permRows as $p) {
            $code = (string)$p['permission_code'];
            $entityId = (int)$p['planning_entity_id'];

            if (!isset($entityPermissions[$entityId])) {
                $entityPermissions[$entityId] = [];
            }
            if (!in_array($code, $entityPermissions[$entityId], true)) {
                $entityPermissions[$entityId][] = $code;
            }

            if (!in_array($code, $globalPermissions, true)) {
                $globalPermissions[] = $code;
            }
        }

        return new UserDTO(
            id: $userId,
            username: (string)$row['username'],
            email: (string)$row['email'],
            passwordHash: (string)$row['password_hash'],
            firstName: (string)$row['first_name'],
            lastName: (string)$row['last_name'],
            phone: $row['phone'] !== null ? (string)$row['phone'] : null,
            status: (string)$row['status'],
            lastLoginAt: $row['last_login_at'] !== null ? (string)$row['last_login_at'] : null,
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            createdBy: $row['created_by'] !== null ? (int)$row['created_by'] : null,
            roles: $roles,
            roleIds: $roleIds,
            permissions: $globalPermissions,
            entityPermissions: $entityPermissions
        );
    }
}
