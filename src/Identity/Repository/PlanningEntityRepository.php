<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use PDO;
use Promis\Core\Repository\BaseRepository;

/**
 * PDO Repository implementation for Planning Entities and System Roles lookups.
 */
class PlanningEntityRepository extends BaseRepository implements PlanningEntityRepositoryInterface
{
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
        $sql = "SELECT pe.*, et.type_name, et.type_code, c.campus_name
                FROM `planning_entities` pe
                LEFT JOIN `entity_types` et ON et.id = pe.entity_type_id
                LEFT JOIN `campuses` c ON c.id = pe.campus_id
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
}
