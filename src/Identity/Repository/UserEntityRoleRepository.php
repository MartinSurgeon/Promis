<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use PDO;
use Promis\Core\Repository\BaseRepository;
use Promis\Src\Identity\Domain\DTO\UserEntityRoleDTO;

/**
 * PDO repository implementation for entity-scoped user role assignments (user_entity_roles).
 */
class UserEntityRoleRepository extends BaseRepository implements UserEntityRoleRepositoryInterface
{
    /**
     * @return UserEntityRoleDTO[]
     */
    public function findByUserId(int $userId): array
    {
        $sql = "SELECT uer.*, r.role_code, r.role_title, pe.entity_code, pe.entity_name
                FROM `user_entity_roles` uer
                JOIN `roles` r ON r.id = uer.role_id
                JOIN `planning_entities` pe ON pe.id = uer.planning_entity_id
                WHERE uer.user_id = :uid
                ORDER BY uer.is_primary DESC, r.id ASC";

        $rows = $this->fetchAll($sql, ['uid' => $userId]);

        return array_map(fn(array $row) => UserEntityRoleDTO::fromArray($row), $rows);
    }

    public function findById(int $id): ?UserEntityRoleDTO
    {
        $sql = "SELECT uer.*, r.role_code, r.role_title, pe.entity_code, pe.entity_name
                FROM `user_entity_roles` uer
                JOIN `roles` r ON r.id = uer.role_id
                JOIN `planning_entities` pe ON pe.id = uer.planning_entity_id
                WHERE uer.id = :id
                LIMIT 1";

        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row ? UserEntityRoleDTO::fromArray($row) : null;
    }

    public function findExisting(int $userId, int $planningEntityId, int $roleId): ?UserEntityRoleDTO
    {
        $sql = "SELECT uer.*, r.role_code, r.role_title, pe.entity_code, pe.entity_name
                FROM `user_entity_roles` uer
                JOIN `roles` r ON r.id = uer.role_id
                JOIN `planning_entities` pe ON pe.id = uer.planning_entity_id
                WHERE uer.user_id = :uid 
                  AND uer.planning_entity_id = :pe_id 
                  AND uer.role_id = :rid
                LIMIT 1";

        $row = $this->fetchOne($sql, [
            'uid' => $userId,
            'pe_id' => $planningEntityId,
            'rid' => $roleId
        ]);

        return $row ? UserEntityRoleDTO::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO `user_entity_roles` (
                    `user_id`,
                    `planning_entity_id`,
                    `role_id`,
                    `is_primary`,
                    `status`,
                    `assigned_at`,
                    `assigned_by`
                ) VALUES (
                    :user_id,
                    :planning_entity_id,
                    :role_id,
                    :is_primary,
                    :status,
                    :assigned_at,
                    :assigned_by
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', (int)$data['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':planning_entity_id', (int)$data['planning_entity_id'], PDO::PARAM_INT);
        $stmt->bindValue(':role_id', (int)$data['role_id'], PDO::PARAM_INT);
        $stmt->bindValue(':is_primary', !empty($data['is_primary']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':status', (string)($data['status'] ?? 'ACTIVE'), PDO::PARAM_STR);
        $stmt->bindValue(':assigned_at', $data['assigned_at'] ?? date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':assigned_by', (int)$data['assigned_by'], PDO::PARAM_INT);

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, int $updatedBy): bool
    {
        $sql = "UPDATE `user_entity_roles` 
                SET `status` = :status, `updated_at` = NOW(), `updated_by` = :ub 
                WHERE `id` = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':ub' => $updatedBy,
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `user_entity_roles` WHERE `id` = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function clearPrimaryForUser(int $userId, int $updatedBy): bool
    {
        $sql = "UPDATE `user_entity_roles` 
                SET `is_primary` = 0, `updated_at` = NOW(), `updated_by` = :ub 
                WHERE `user_id` = :uid AND `is_primary` = 1";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':ub' => $updatedBy, ':uid' => $userId]);
    }

    public function setPrimary(int $userId, int $assignmentId, int $updatedBy): bool
    {
        $this->clearPrimaryForUser($userId, $updatedBy);

        $sql = "UPDATE `user_entity_roles` 
                SET `is_primary` = 1, `updated_at` = NOW(), `updated_by` = :ub 
                WHERE `id` = :id AND `user_id` = :uid";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':ub' => $updatedBy,
            ':id' => $assignmentId,
            ':uid' => $userId
        ]);
    }

    /**
     * Fetch all active role and entity assignments for a list of user IDs in a single query.
     * @return array<int, array{roles: array, entities: array}>
     */
    public function getGroupedAssignmentsByUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT uer.user_id, uer.id as assignment_id, uer.is_primary, uer.status as assignment_status,
                       r.id as role_id, r.role_code, r.role_title,
                       pe.id as entity_id, pe.entity_code, pe.entity_name
                FROM `user_entity_roles` uer
                JOIN `roles` r ON r.id = uer.role_id
                JOIN `planning_entities` pe ON pe.id = uer.planning_entity_id
                WHERE uer.user_id IN ({$placeholders})
                ORDER BY uer.user_id ASC, uer.is_primary DESC, r.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($userIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($userIds as $uid) {
            $result[(int)$uid] = [
                'roles' => [],
                'entities' => [],
                'assignments' => []
            ];
        }

        foreach ($rows as $r) {
            $uid = (int)$r['user_id'];
            $roleCode = (string)$r['role_code'];
            $roleTitle = (string)$r['role_title'];
            $entityName = (string)$r['entity_name'];
            $entityCode = (string)$r['entity_code'];

            if (!in_array($roleCode, $result[$uid]['roles'], true)) {
                $result[$uid]['roles'][] = $roleCode;
            }

            if (!in_array($entityName, $result[$uid]['entities'], true)) {
                $result[$uid]['entities'][] = $entityName;
            }

            $result[$uid]['assignments'][] = [
                'id' => (int)$r['assignment_id'],
                'role_id' => (int)$r['role_id'],
                'role_code' => $roleCode,
                'role_title' => $roleTitle,
                'entity_id' => (int)$r['entity_id'],
                'entity_code' => $entityCode,
                'entity_name' => $entityName,
                'is_primary' => (bool)$r['is_primary'],
                'status' => (string)$r['assignment_status'],
            ];
        }

        return $result;
    }
}
