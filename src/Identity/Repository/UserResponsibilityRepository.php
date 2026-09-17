<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use PDO;
use Promis\Core\Database\Connection;
use Promis\Src\Identity\Domain\Model\ResponsibilityCode;

class UserResponsibilityRepository implements UserResponsibilityRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @return string[]
     */
    public function getActiveResponsibilityCodes(int $userId, ?int $planningEntityId = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT `responsibility_code`
                FROM `user_responsibilities`
                WHERE `user_id` = :uid AND `is_active` = 1";
        $params = [':uid' => $userId];

        if ($planningEntityId !== null && $planningEntityId > 0) {
            $sql .= " AND `planning_entity_id` = :eid";
            $params[':eid'] = $planningEntityId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function hasResponsibility(int $userId, string $responsibilityCode, ?int $planningEntityId = null): bool
    {
        if ($userId <= 0 || !ResponsibilityCode::isValid($responsibilityCode)) {
            return false;
        }

        $sql = "SELECT 1
                FROM `user_responsibilities`
                WHERE `user_id` = :uid
                  AND `responsibility_code` = :code
                  AND `is_active` = 1";
        $params = [
            ':uid' => $userId,
            ':code' => $responsibilityCode,
        ];

        if ($planningEntityId !== null && $planningEntityId > 0) {
            $sql .= " AND `planning_entity_id` = :eid";
            $params[':eid'] = $planningEntityId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Synchronize a user's active responsibilities for an assigned planning entity.
     * Deactivates unchecked ones and inserts/reactivates checked ones.
     *
     * @param string[] $responsibilityCodes
     */
    public function syncUserResponsibilities(
        int $userId,
        int $planningEntityId,
        array $responsibilityCodes,
        int $actorUserId
    ): void {
        // Validate codes
        $validCodes = [];
        foreach ($responsibilityCodes as $code) {
            $cleanCode = strtoupper(trim((string)$code));
            if (ResponsibilityCode::isValid($cleanCode)) {
                $validCodes[] = $cleanCode;
            }
        }
        $validCodes = array_values(array_unique($validCodes));

        // 1. Deactivate responsibilities no longer checked for this user and entity
        if (empty($validCodes)) {
            $deactStmt = $this->db->prepare("
                UPDATE `user_responsibilities`
                SET `is_active` = 0, `updated_by` = :actor, `updated_at` = NOW()
                WHERE `user_id` = :uid AND `planning_entity_id` = :eid AND `is_active` = 1
            ");
            $deactStmt->execute([
                ':actor' => $actorUserId,
                ':uid' => $userId,
                ':eid' => $planningEntityId,
            ]);
        } else {
            $inPlaceholders = implode(',', array_fill(0, count($validCodes), '?'));
            $deactSql = "
                UPDATE `user_responsibilities`
                SET `is_active` = 0, `updated_by` = ?, `updated_at` = NOW()
                WHERE `user_id` = ? AND `planning_entity_id` = ? AND `is_active` = 1
                  AND `responsibility_code` NOT IN ({$inPlaceholders})
            ";
            $params = array_merge([$actorUserId, $userId, $planningEntityId], $validCodes);
            $stmt = $this->db->prepare($deactSql);
            $stmt->execute($params);
        }

        // 2. Insert or reactivate checked responsibilities
        if (!empty($validCodes)) {
            $upsertStmt = $this->db->prepare("
                INSERT INTO `user_responsibilities` (
                    `user_id`, `planning_entity_id`, `responsibility_code`, `is_active`, `assigned_by`
                ) VALUES (
                    :uid, :eid, :code, 1, :assigned_by
                ) ON DUPLICATE KEY UPDATE
                    `is_active` = 1,
                    `updated_by` = VALUES(`assigned_by`),
                    `updated_at` = NOW()
            ");

            foreach ($validCodes as $code) {
                $upsertStmt->execute([
                    ':uid' => $userId,
                    ':eid' => $planningEntityId,
                    ':code' => $code,
                    ':assigned_by' => $actorUserId,
                ]);
            }
        }
    }

    /**
     * @param int[] $userIds
     * @return array<int, array<string>>
     */
    public function getGroupedResponsibilitiesByUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $inPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "
            SELECT `user_id`, `responsibility_code`
            FROM `user_responsibilities`
            WHERE `user_id` IN ({$inPlaceholders}) AND `is_active` = 1
            ORDER BY `id` ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($userIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($userIds as $uid) {
            $grouped[(int)$uid] = [];
        }

        foreach ($rows as $r) {
            $grouped[(int)$r['user_id']][] = (string)$r['responsibility_code'];
        }

        return $grouped;
    }
}
