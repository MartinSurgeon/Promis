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
            'phone' => isset($data['phone']) && $data['phone'] !== '' ? trim($data['phone']) : null,
            'status' => $data['status'] ?? 'ACTIVE',
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

    public function updateProfile(int $id, array $data, ?int $updatedBy = null): bool
    {
        $sets = [
            '`first_name` = :first_name',
            '`last_name` = :last_name',
            '`email` = :email',
            '`phone` = :phone',
            '`updated_at` = NOW()',
        ];

        $params = [
            'id' => $id,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => !empty($data['phone']) ? trim($data['phone']) : null,
        ];

        if (!empty($data['password_hash'])) {
            $sets[] = '`password_hash` = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }

        if (!empty($data['status'])) {
            $sets[] = '`status` = :status';
            $params['status'] = $data['status'];
        }

        if ($updatedBy !== null) {
            $sets[] = '`updated_by` = :updated_by';
            $params['updated_by'] = $updatedBy;
        }

        $setSql = implode(', ', $sets);
        $sql = "UPDATE `users` SET {$setSql} WHERE `id` = :id";

        return $this->execute($sql, $params) > 0;
    }

    public function findPaginatedUsers(
        int $page = 1,
        int $limit = 15,
        string $search = '',
        string $roleFilter = '',
        ?int $entityFilter = null,
        string $statusFilter = ''
    ): array {
        $offset = max(0, ($page - 1) * $limit);
        [$whereSql, $params] = $this->buildFilterConditions($search, $roleFilter, $entityFilter, $statusFilter);

        $sql = "SELECT DISTINCT u.`id`, u.`username`, u.`email`, u.`first_name`, u.`last_name`, 
                               u.`phone`, u.`status`, u.`last_login_at`, u.`created_at`, u.`created_by`
                FROM `users` u
                LEFT JOIN `user_entity_roles` uer ON uer.`user_id` = u.`id`
                LEFT JOIN `roles` r ON r.`id` = uer.`role_id`
                WHERE {$whereSql}
                ORDER BY u.`id` DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFilteredUsers(
        string $search = '',
        string $roleFilter = '',
        ?int $entityFilter = null,
        string $statusFilter = ''
    ): int {
        [$whereSql, $params] = $this->buildFilterConditions($search, $roleFilter, $entityFilter, $statusFilter);

        $sql = "SELECT COUNT(DISTINCT u.`id`)
                FROM `users` u
                LEFT JOIN `user_entity_roles` uer ON uer.`user_id` = u.`id`
                LEFT JOIN `roles` r ON r.`id` = uer.`role_id`
                WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getSummaryMetrics(): array
    {
        $totalUsers = (int)$this->db->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        $activeUsers = (int)$this->db->query("SELECT COUNT(*) FROM `users` WHERE `status` = 'ACTIVE'")->fetchColumn();
        $pendingUsers = (int)$this->db->query("SELECT COUNT(*) FROM `users` WHERE `status` = 'PENDING'")->fetchColumn();
        $inactiveUsers = (int)$this->db->query("SELECT COUNT(*) FROM `users` WHERE `status` = 'INACTIVE'")->fetchColumn();
        $assignedEntitiesCount = (int)$this->db->query("SELECT COUNT(DISTINCT `planning_entity_id`) FROM `user_entity_roles` WHERE `status` = 'ACTIVE'")->fetchColumn();
        $totalAssignments = (int)$this->db->query("SELECT COUNT(*) FROM `user_entity_roles` WHERE `status` = 'ACTIVE'")->fetchColumn();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'pending_users' => $pendingUsers,
            'inactive_users' => $inactiveUsers,
            'assigned_entities' => $assignedEntitiesCount,
            'total_assignments' => $totalAssignments,
        ];
    }

    private function buildFilterConditions(
        string $search,
        string $roleFilter,
        ?int $entityFilter,
        string $statusFilter
    ): array {
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = "(u.`first_name` LIKE :search1 OR u.`last_name` LIKE :search2 OR u.`username` LIKE :search3 OR u.`email` LIKE :search4)";
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
            $params['search4'] = "%{$search}%";
        }

        if ($roleFilter !== '') {
            $where[] = "r.`role_code` = :role_code AND uer.`status` = 'ACTIVE'";
            $params['role_code'] = $roleFilter;
        }

        if ($entityFilter !== null && $entityFilter > 0) {
            $where[] = "uer.`planning_entity_id` = :pe_id AND uer.`status` = 'ACTIVE'";
            $params['pe_id'] = $entityFilter;
        }

        if ($statusFilter !== '') {
            $where[] = "u.`status` = :user_status";
            $params['user_status'] = $statusFilter;
        }

        return [implode(' AND ', $where), $params];
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
