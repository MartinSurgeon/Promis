<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use PDO;
use Promis\Core\Database\Connection;
use Promis\Src\Identity\Domain\Model\StaffPosition;

class PositionRepository implements PositionRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @return StaffPosition[]
     */
    public function findAllActive(): array
    {
        $stmt = $this->db->query("
            SELECT `id`, `position_code`, `position_title`, `description`, `default_scope_type`, `is_active`
            FROM `positions`
            WHERE `is_active` = 1
            ORDER BY `id` ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => StaffPosition::fromArray($r), $rows);
    }

    public function findById(int $id): ?StaffPosition
    {
        $stmt = $this->db->prepare("
            SELECT `id`, `position_code`, `position_title`, `description`, `default_scope_type`, `is_active`
            FROM `positions`
            WHERE `id` = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? StaffPosition::fromArray($row) : null;
    }

    public function findByCode(string $code): ?StaffPosition
    {
        $stmt = $this->db->prepare("
            SELECT `id`, `position_code`, `position_title`, `description`, `default_scope_type`, `is_active`
            FROM `positions`
            WHERE `position_code` = :code
            LIMIT 1
        ");
        $stmt->execute([':code' => strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? StaffPosition::fromArray($row) : null;
    }
}
