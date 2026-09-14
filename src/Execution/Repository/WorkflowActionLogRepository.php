<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use PDO;
use Promis\Core\Repository\BaseRepository;
use Promis\Src\Execution\Domain\DTO\WorkflowActionLogDTO;

/**
 * PDO repository implementation for append-only Workflow Action Logs (workflow_action_logs).
 */
class WorkflowActionLogRepository extends BaseRepository implements WorkflowActionLogRepositoryInterface
{
    public function create(array $data): int
    {
        $sql = "INSERT INTO `workflow_action_logs` (
                    `document_type`,
                    `document_id`,
                    `step_id`,
                    `actor_user_id`,
                    `action`,
                    `pre_status`,
                    `post_status`,
                    `comments`,
                    `action_timestamp`
                ) VALUES (
                    :document_type,
                    :document_id,
                    :step_id,
                    :actor_user_id,
                    :action,
                    :pre_status,
                    :post_status,
                    :comments,
                    :action_timestamp
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':document_type', (string)($data['document_type'] ?? 'REQUISITION'), PDO::PARAM_STR);
        $stmt->bindValue(':document_id', (int)$data['document_id'], PDO::PARAM_INT);

        if (!empty($data['step_id'])) {
            $stmt->bindValue(':step_id', (int)$data['step_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':step_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':actor_user_id', (int)$data['actor_user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':action', (string)$data['action'], PDO::PARAM_STR);
        $stmt->bindValue(':pre_status', (string)$data['pre_status'], PDO::PARAM_STR);
        $stmt->bindValue(':post_status', (string)$data['post_status'], PDO::PARAM_STR);

        if (isset($data['comments']) && $data['comments'] !== null && $data['comments'] !== '') {
            $stmt->bindValue(':comments', (string)$data['comments'], PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':comments', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':action_timestamp', $data['action_timestamp'] ?? date('Y-m-d H:i:s'), PDO::PARAM_STR);

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?WorkflowActionLogDTO
    {
        $sql = "SELECT wal.*, 
                       CONCAT(u.first_name, ' ', u.last_name) AS actor_name,
                       wsr.step_name
                FROM `workflow_action_logs` wal
                JOIN `users` u ON u.id = wal.actor_user_id
                LEFT JOIN `workflow_step_rules` wsr ON wsr.id = wal.step_id
                WHERE wal.`id` = :id
                LIMIT 1";

        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row ? WorkflowActionLogDTO::fromArray($row) : null;
    }

    /**
     * @return WorkflowActionLogDTO[]
     */
    public function findByDocument(string $documentType, int $documentId): array
    {
        $sql = "SELECT wal.*, 
                       CONCAT(u.first_name, ' ', u.last_name) AS actor_name,
                       wsr.step_name
                FROM `workflow_action_logs` wal
                JOIN `users` u ON u.id = wal.actor_user_id
                LEFT JOIN `workflow_step_rules` wsr ON wsr.id = wal.step_id
                WHERE wal.`document_type` = :doc_type
                  AND wal.`document_id` = :doc_id
                ORDER BY wal.`action_timestamp` ASC, wal.`id` ASC";

        $rows = $this->fetchAll($sql, [
            'doc_type' => trim($documentType),
            'doc_id' => $documentId,
        ]);

        return array_map(fn(array $row) => WorkflowActionLogDTO::fromArray($row), $rows);
    }

    public function deleteByDocument(string $documentType, int $documentId): int
    {
        $sql = "DELETE FROM `workflow_action_logs` WHERE `document_type` = :doc_type AND `document_id` = :doc_id";

        return $this->execute($sql, [
            'doc_type' => trim($documentType),
            'doc_id' => $documentId,
        ]);
    }
}
