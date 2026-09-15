<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use PDO;
use Promis\Core\Repository\BaseRepository;

/**
 * PDO Repository implementation for Planning Entities, Entity Types, Campuses,
 * System Roles, and Hierarchy Closure Table management.
 */
class PlanningEntityRepository extends BaseRepository implements PlanningEntityRepositoryInterface
{
    // ───────────────────────────────────────────────
    // Existing methods (preserved)
    // ───────────────────────────────────────────────

    public function findAllActive(): array
    {
        $sql = "SELECT pe.*, et.type_name, et.type_code, c.campus_name
                FROM `planning_entities` pe
                LEFT JOIN `entity_types` et ON et.id = pe.entity_type_id
                LEFT JOIN `campuses` c ON c.id = pe.campus_id
                WHERE pe.is_active = 1
                ORDER BY pe.entity_name ASC";

        return $this->fetchAll($sql);
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT pe.*, et.type_name, et.type_code, c.campus_name,
                       parent.entity_name AS parent_entity_name, parent.entity_code AS parent_entity_code,
                       CONCAT(hu.first_name, ' ', hu.last_name) AS head_user_name,
                       CONCAT(po.first_name, ' ', po.last_name) AS planning_officer_name
                FROM `planning_entities` pe
                LEFT JOIN `entity_types` et ON et.id = pe.entity_type_id
                LEFT JOIN `campuses` c ON c.id = pe.campus_id
                LEFT JOIN `planning_entities` parent ON parent.id = pe.parent_entity_id
                LEFT JOIN `users` hu ON hu.id = pe.head_user_id
                LEFT JOIN `users` po ON po.id = pe.planning_officer_id
                WHERE pe.id = :id
                LIMIT 1";

        return $this->fetchOne($sql, ['id' => $id]);
    }

    public function findAllActiveRoles(): array
    {
        $sql = "SELECT id, role_code, role_title, description, is_system_reserved, is_active
                FROM `roles`
                WHERE is_active = 1
                ORDER BY id ASC";

        return $this->fetchAll($sql);
    }

    public function findRoleById(int $id): ?array
    {
        $sql = "SELECT id, role_code, role_title, description, is_system_reserved, is_active
                FROM `roles`
                WHERE id = :id
                LIMIT 1";

        return $this->fetchOne($sql, ['id' => $id]);
    }

    // ───────────────────────────────────────────────
    // Hierarchical Entity Management
    // ───────────────────────────────────────────────

    public function findAll(?string $search = null, ?int $typeFilter = null, ?int $campusFilter = null, ?string $statusFilter = null): array
    {
        $sql = "SELECT pe.*, et.type_name, et.type_code, c.campus_name,
                       parent.entity_name AS parent_entity_name
                FROM `planning_entities` pe
                LEFT JOIN `entity_types` et ON et.id = pe.entity_type_id
                LEFT JOIN `campuses` c ON c.id = pe.campus_id
                LEFT JOIN `planning_entities` parent ON parent.id = pe.parent_entity_id
                WHERE 1=1";

        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= " AND (pe.entity_code LIKE :search OR pe.entity_name LIKE :search2)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }

        if ($typeFilter !== null) {
            $sql .= " AND pe.entity_type_id = :type_filter";
            $params['type_filter'] = $typeFilter;
        }

        if ($campusFilter !== null) {
            $sql .= " AND pe.campus_id = :campus_filter";
            $params['campus_filter'] = $campusFilter;
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            if ($statusFilter === 'ACTIVE') {
                $sql .= " AND pe.is_active = 1";
            } elseif ($statusFilter === 'INACTIVE') {
                $sql .= " AND pe.is_active = 0";
            }
        }

        $sql .= " ORDER BY pe.entity_name ASC";

        return $this->fetchAll($sql, $params);
    }

    public function findAllWithHierarchy(?string $search = null, ?int $typeFilter = null, ?int $campusFilter = null, ?string $statusFilter = null): array
    {
        $sql = "SELECT pe.*, et.type_name, et.type_code, c.campus_name,
                       parent.entity_name AS parent_entity_name, parent.entity_code AS parent_entity_code,
                       CONCAT(hu.first_name, ' ', hu.last_name) AS head_user_name,
                       CONCAT(po.first_name, ' ', po.last_name) AS planning_officer_name,
                       (SELECT COUNT(*) FROM `planning_entities` child WHERE child.parent_entity_id = pe.id) AS child_count,
                       (SELECT COUNT(*) FROM `user_entity_roles` uer WHERE uer.planning_entity_id = pe.id AND uer.status = 'ACTIVE') AS assigned_users_count
                FROM `planning_entities` pe
                LEFT JOIN `entity_types` et ON et.id = pe.entity_type_id
                LEFT JOIN `campuses` c ON c.id = pe.campus_id
                LEFT JOIN `planning_entities` parent ON parent.id = pe.parent_entity_id
                LEFT JOIN `users` hu ON hu.id = pe.head_user_id
                LEFT JOIN `users` po ON po.id = pe.planning_officer_id
                WHERE 1=1";

        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= " AND (pe.entity_code LIKE :search OR pe.entity_name LIKE :search2)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }

        if ($typeFilter !== null) {
            $sql .= " AND pe.entity_type_id = :type_filter";
            $params['type_filter'] = $typeFilter;
        }

        if ($campusFilter !== null) {
            $sql .= " AND pe.campus_id = :campus_filter";
            $params['campus_filter'] = $campusFilter;
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            if ($statusFilter === 'ACTIVE') {
                $sql .= " AND pe.is_active = 1";
            } elseif ($statusFilter === 'INACTIVE') {
                $sql .= " AND pe.is_active = 0";
            }
        }

        $sql .= " ORDER BY pe.parent_entity_id ASC, pe.entity_name ASC";

        return $this->fetchAll($sql, $params);
    }

    public function findByCode(string $code): ?array
    {
        $sql = "SELECT * FROM `planning_entities` WHERE entity_code = :code LIMIT 1";
        return $this->fetchOne($sql, ['code' => $code]);
    }

    public function findChildren(int $parentId): array
    {
        $sql = "SELECT pe.*, et.type_name, et.type_code, c.campus_name
                FROM `planning_entities` pe
                LEFT JOIN `entity_types` et ON et.id = pe.entity_type_id
                LEFT JOIN `campuses` c ON c.id = pe.campus_id
                WHERE pe.parent_entity_id = :parent_id
                ORDER BY pe.entity_name ASC";

        return $this->fetchAll($sql, ['parent_id' => $parentId]);
    }

    public function findDescendantIds(int $entityId): array
    {
        $sql = "SELECT descendant_entity_id
                FROM `entity_hierarchies`
                WHERE ancestor_entity_id = :entity_id AND descendant_entity_id != :entity_id2";

        $rows = $this->fetchAll($sql, ['entity_id' => $entityId, 'entity_id2' => $entityId]);

        return array_map(fn(array $row) => (int)$row['descendant_entity_id'], $rows);
    }

    public function findAllEntityTypes(): array
    {
        $sql = "SELECT * FROM `entity_types` WHERE is_active = 1 ORDER BY id ASC";
        return $this->fetchAll($sql);
    }

    public function findAllCampuses(): array
    {
        $sql = "SELECT * FROM `campuses` WHERE is_active = 1 ORDER BY campus_name ASC";
        return $this->fetchAll($sql);
    }

    public function createEntity(array $data): int
    {
        $sql = "INSERT INTO `planning_entities` (
                    `entity_code`, `entity_name`, `entity_type_id`, `campus_id`,
                    `parent_entity_id`, `head_user_id`, `planning_officer_id`,
                    `is_active`, `created_by`
                ) VALUES (
                    :entity_code, :entity_name, :entity_type_id, :campus_id,
                    :parent_entity_id, :head_user_id, :planning_officer_id,
                    1, :created_by
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':entity_code', (string)$data['entity_code'], PDO::PARAM_STR);
        $stmt->bindValue(':entity_name', (string)$data['entity_name'], PDO::PARAM_STR);
        $stmt->bindValue(':entity_type_id', (int)$data['entity_type_id'], PDO::PARAM_INT);
        $stmt->bindValue(':campus_id', (int)$data['campus_id'], PDO::PARAM_INT);

        if (!empty($data['parent_entity_id'])) {
            $stmt->bindValue(':parent_entity_id', (int)$data['parent_entity_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':parent_entity_id', null, PDO::PARAM_NULL);
        }

        if (!empty($data['head_user_id'])) {
            $stmt->bindValue(':head_user_id', (int)$data['head_user_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':head_user_id', null, PDO::PARAM_NULL);
        }

        if (!empty($data['planning_officer_id'])) {
            $stmt->bindValue(':planning_officer_id', (int)$data['planning_officer_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':planning_officer_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    public function updateEntity(int $id, array $data, int $actorId): bool
    {
        $sql = "UPDATE `planning_entities` SET
                    `entity_name` = :entity_name,
                    `entity_type_id` = :entity_type_id,
                    `campus_id` = :campus_id,
                    `parent_entity_id` = :parent_entity_id,
                    `head_user_id` = :head_user_id,
                    `planning_officer_id` = :planning_officer_id,
                    `updated_by` = :updated_by
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':entity_name', (string)$data['entity_name'], PDO::PARAM_STR);
        $stmt->bindValue(':entity_type_id', (int)$data['entity_type_id'], PDO::PARAM_INT);
        $stmt->bindValue(':campus_id', (int)$data['campus_id'], PDO::PARAM_INT);

        if (!empty($data['parent_entity_id'])) {
            $stmt->bindValue(':parent_entity_id', (int)$data['parent_entity_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':parent_entity_id', null, PDO::PARAM_NULL);
        }

        if (!empty($data['head_user_id'])) {
            $stmt->bindValue(':head_user_id', (int)$data['head_user_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':head_user_id', null, PDO::PARAM_NULL);
        }

        if (!empty($data['planning_officer_id'])) {
            $stmt->bindValue(':planning_officer_id', (int)$data['planning_officer_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':planning_officer_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':updated_by', $actorId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updateEntityStatus(int $id, bool $isActive): bool
    {
        $sql = "UPDATE `planning_entities` SET `is_active` = :is_active WHERE `id` = :id";
        return $this->execute($sql, ['is_active' => $isActive ? 1 : 0, 'id' => $id]) > 0;
    }

    public function insertHierarchyPaths(int $entityId, ?int $parentId): void
    {
        // Self-reference: every entity is its own ancestor at depth 0
        $selfSql = "INSERT INTO `entity_hierarchies` (ancestor_entity_id, descendant_entity_id, depth)
                    VALUES (:eid, :eid2, 0)";
        $this->execute($selfSql, ['eid' => $entityId, 'eid2' => $entityId]);

        // If has parent, copy all ancestor paths from parent, incrementing depth
        if ($parentId !== null) {
            $ancestorSql = "INSERT INTO `entity_hierarchies` (ancestor_entity_id, descendant_entity_id, depth)
                            SELECT ancestor_entity_id, :eid, depth + 1
                            FROM `entity_hierarchies`
                            WHERE descendant_entity_id = :parent_id";
            $this->execute($ancestorSql, ['eid' => $entityId, 'parent_id' => $parentId]);
        }
    }

    public function removeHierarchyPaths(int $entityId): void
    {
        // Remove all paths where this entity is a descendant (its position in the tree)
        $sql = "DELETE FROM `entity_hierarchies` WHERE descendant_entity_id = :eid";
        $this->execute($sql, ['eid' => $entityId]);
    }

    public function rebuildDescendantPaths(int $entityId): void
    {
        // Get all direct children
        $childrenSql = "SELECT id, parent_entity_id FROM `planning_entities` WHERE parent_entity_id = :pid";
        $children = $this->fetchAll($childrenSql, ['pid' => $entityId]);

        foreach ($children as $child) {
            $childId = (int)$child['id'];
            // Remove old paths for this child
            $this->removeHierarchyPaths($childId);
            // Re-insert with corrected parent
            $this->insertHierarchyPaths($childId, $entityId);
            // Recursively rebuild descendants
            $this->rebuildDescendantPaths($childId);
        }
    }

    public function countEntitiesByType(): array
    {
        $sql = "SELECT et.type_code, et.type_name, COUNT(pe.id) as count
                FROM `entity_types` et
                LEFT JOIN `planning_entities` pe ON pe.entity_type_id = et.id
                WHERE et.is_active = 1
                GROUP BY et.id, et.type_code, et.type_name
                ORDER BY et.id ASC";

        return $this->fetchAll($sql);
    }

    public function countEntitiesByStatus(): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
                FROM `planning_entities`";

        return $this->fetchOne($sql) ?? ['total' => 0, 'active' => 0, 'inactive' => 0];
    }

    public function hasActiveChildren(int $entityId): bool
    {
        $sql = "SELECT COUNT(*) as cnt FROM `planning_entities`
                WHERE parent_entity_id = :pid AND is_active = 1";
        $row = $this->fetchOne($sql, ['pid' => $entityId]);
        return ($row['cnt'] ?? 0) > 0;
    }

    public function countAssignedUsers(int $entityId): int
    {
        $sql = "SELECT COUNT(DISTINCT user_id) as cnt FROM `user_entity_roles`
                WHERE planning_entity_id = :eid AND status = 'ACTIVE'";
        $row = $this->fetchOne($sql, ['eid' => $entityId]);
        return (int)($row['cnt'] ?? 0);
    }

    public function getMaxHierarchyDepth(): int
    {
        $sql = "SELECT MAX(depth) as max_depth FROM `entity_hierarchies`";
        $row = $this->fetchOne($sql);
        return (int)($row['max_depth'] ?? 0);
    }
}
