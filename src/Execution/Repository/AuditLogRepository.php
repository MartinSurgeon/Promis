<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use PDO;
use Promis\Core\Repository\BaseRepository;
use Promis\Src\Execution\Domain\DTO\AuditLogDTO;

/**
 * PDO repository implementation for Institutional Audit Logs (audit_logs).
 */
class AuditLogRepository extends BaseRepository implements AuditLogRepositoryInterface
{
    public function create(array $data): int
    {
        $sql = "INSERT INTO `audit_logs` (
                    `event_timestamp`,
                    `actor_user_id`,
                    `planning_entity_id`,
                    `action`,
                    `record_type`,
                    `record_id`,
                    `ip_address`,
                    `user_agent`,
                    `previous_state_json`,
                    `new_state_json`
                ) VALUES (
                    :event_timestamp,
                    :actor_user_id,
                    :planning_entity_id,
                    :action,
                    :record_type,
                    :record_id,
                    :ip_address,
                    :user_agent,
                    :previous_state_json,
                    :new_state_json
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':event_timestamp', $data['event_timestamp'] ?? date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':actor_user_id', (int)$data['actor_user_id'], PDO::PARAM_INT);

        if (!empty($data['planning_entity_id'])) {
            $stmt->bindValue(':planning_entity_id', (int)$data['planning_entity_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':planning_entity_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':action', (string)$data['action'], PDO::PARAM_STR);
        $stmt->bindValue(':record_type', (string)($data['record_type'] ?? 'requisitions'), PDO::PARAM_STR);
        $stmt->bindValue(':record_id', (int)$data['record_id'], PDO::PARAM_INT);
        $stmt->bindValue(':ip_address', (string)($data['ip_address'] ?? '127.0.0.1'), PDO::PARAM_STR);

        if (isset($data['user_agent']) && $data['user_agent'] !== null) {
            $stmt->bindValue(':user_agent', (string)$data['user_agent'], PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':user_agent', null, PDO::PARAM_NULL);
        }

        if (isset($data['previous_state_json']) && $data['previous_state_json'] !== null) {
            $stmt->bindValue(':previous_state_json', (string)$data['previous_state_json'], PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':previous_state_json', null, PDO::PARAM_NULL);
        }

        if (isset($data['new_state_json']) && $data['new_state_json'] !== null) {
            $stmt->bindValue(':new_state_json', (string)$data['new_state_json'], PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':new_state_json', null, PDO::PARAM_NULL);
        }

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?AuditLogDTO
    {
        $sql = "SELECT * FROM `audit_logs` WHERE `id` = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row ? AuditLogDTO::fromArray($row) : null;
    }

    /**
     * @return AuditLogDTO[]
     */
    public function findByRecord(string $recordType, int $recordId): array
    {
        $sql = "SELECT * FROM `audit_logs` 
                WHERE `record_type` = :record_type 
                  AND `record_id` = :record_id
                ORDER BY `event_timestamp` ASC, `id` ASC";

        $rows = $this->fetchAll($sql, [
            'record_type' => trim($recordType),
            'record_id' => $recordId,
        ]);

        return array_map(fn(array $row) => AuditLogDTO::fromArray($row), $rows);
    }

    public function deleteByRecord(string $recordType, int $recordId): int
    {
        $sql = "DELETE FROM `audit_logs` WHERE `record_type` = :record_type AND `record_id` = :record_id";

        return $this->execute($sql, [
            'record_type' => trim($recordType),
            'record_id' => $recordId,
        ]);
    }
}
