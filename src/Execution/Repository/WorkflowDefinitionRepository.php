<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use PDO;
use Promis\Core\Repository\BaseRepository;
use Promis\Src\Execution\Domain\DTO\WorkflowDefinitionDTO;

/**
 * PDO repository implementation for Workflow Definitions (workflow_definitions).
 */
class WorkflowDefinitionRepository extends BaseRepository implements WorkflowDefinitionRepositoryInterface
{
    public function findById(int $id): ?WorkflowDefinitionDTO
    {
        $sql = "SELECT wd.*, et.type_name AS entity_type_name
                FROM `workflow_definitions` wd
                LEFT JOIN `entity_types` et ON et.id = wd.entity_type_id
                WHERE wd.`id` = :id
                LIMIT 1";

        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row ? WorkflowDefinitionDTO::fromArray($row) : null;
    }

    public function findByCode(string $code): ?WorkflowDefinitionDTO
    {
        $sql = "SELECT wd.*, et.type_name AS entity_type_name
                FROM `workflow_definitions` wd
                LEFT JOIN `entity_types` et ON et.id = wd.entity_type_id
                WHERE wd.`workflow_code` = :code
                LIMIT 1";

        $row = $this->fetchOne($sql, ['code' => trim($code)]);

        return $row ? WorkflowDefinitionDTO::fromArray($row) : null;
    }

    /**
     * @return WorkflowDefinitionDTO[]
     */
    public function findActiveByDocumentAndEntityType(string $documentType, int $entityTypeId): array
    {
        $sql = "SELECT wd.*, et.type_name AS entity_type_name
                FROM `workflow_definitions` wd
                LEFT JOIN `entity_types` et ON et.id = wd.entity_type_id
                WHERE wd.`document_type` = :doc_type
                  AND wd.`entity_type_id` = :entity_type_id
                  AND wd.`is_active` = 1
                ORDER BY wd.`id` ASC";

        $rows = $this->fetchAll($sql, [
            'doc_type' => trim($documentType),
            'entity_type_id' => $entityTypeId,
        ]);

        return array_map(fn(array $row) => WorkflowDefinitionDTO::fromArray($row), $rows);
    }

    /**
     * @return WorkflowDefinitionDTO[]
     */
    public function findActiveGlobalByDocument(string $documentType): array
    {
        $sql = "SELECT wd.*, NULL AS entity_type_name
                FROM `workflow_definitions` wd
                WHERE wd.`document_type` = :doc_type
                  AND wd.`entity_type_id` IS NULL
                  AND wd.`is_active` = 1
                ORDER BY wd.`id` ASC";

        $rows = $this->fetchAll($sql, [
            'doc_type' => trim($documentType),
        ]);

        return array_map(fn(array $row) => WorkflowDefinitionDTO::fromArray($row), $rows);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO `workflow_definitions` (
                    `workflow_code`,
                    `document_type`,
                    `entity_type_id`,
                    `workflow_name`,
                    `is_active`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :workflow_code,
                    :document_type,
                    :entity_type_id,
                    :workflow_name,
                    :is_active,
                    :created_at,
                    :created_by
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':workflow_code', (string)$data['workflow_code'], PDO::PARAM_STR);
        $stmt->bindValue(':document_type', (string)($data['document_type'] ?? 'REQUISITION'), PDO::PARAM_STR);

        if (!empty($data['entity_type_id'])) {
            $stmt->bindValue(':entity_type_id', (int)$data['entity_type_id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':entity_type_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':workflow_name', (string)$data['workflow_name'], PDO::PARAM_STR);
        $stmt->bindValue(':is_active', isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1, PDO::PARAM_INT);
        $stmt->bindValue(':created_at', $data['created_at'] ?? date('Y-m-d H:i:s'), PDO::PARAM_STR);

        if (!empty($data['created_by'])) {
            $stmt->bindValue(':created_by', (int)$data['created_by'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
        }

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('workflow_name', $data)) {
            $fields[] = "`workflow_name` = :workflow_name";
            $params['workflow_name'] = (string)$data['workflow_name'];
        }

        if (array_key_exists('is_active', $data)) {
            $fields[] = "`is_active` = :is_active";
            $params['is_active'] = (int)(bool)$data['is_active'];
        }

        if (array_key_exists('updated_by', $data)) {
            $fields[] = "`updated_by` = :updated_by";
            $params['updated_by'] = !empty($data['updated_by']) ? (int)$data['updated_by'] : null;
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "`updated_at` = :updated_at";
        $params['updated_at'] = date('Y-m-d H:i:s');

        $sql = "UPDATE `workflow_definitions` SET " . implode(', ', $fields) . " WHERE `id` = :id";

        return $this->execute($sql, $params) > 0;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `workflow_definitions` WHERE `id` = :id";

        return $this->execute($sql, ['id' => $id]) > 0;
    }
}
