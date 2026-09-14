<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use PDO;
use Promis\Core\Repository\BaseRepository;
use Promis\Src\Execution\Domain\DTO\WorkflowStepRuleDTO;
use Promis\Src\Planning\Domain\Decimal;

/**
 * PDO repository implementation for Workflow Step Rules (workflow_step_rules).
 */
class WorkflowStepRuleRepository extends BaseRepository implements WorkflowStepRuleRepositoryInterface
{
    public function findById(int $id): ?WorkflowStepRuleDTO
    {
        $sql = "SELECT wsr.*, r.role_code, r.role_title
                FROM `workflow_step_rules` wsr
                JOIN `roles` r ON r.id = wsr.required_role_id
                WHERE wsr.`id` = :id
                LIMIT 1";

        $row = $this->fetchOne($sql, ['id' => $id]);

        return $row ? WorkflowStepRuleDTO::fromArray($row) : null;
    }

    /**
     * @return WorkflowStepRuleDTO[]
     */
    public function findByWorkflowDefinitionId(int $definitionId): array
    {
        $sql = "SELECT wsr.*, r.role_code, r.role_title
                FROM `workflow_step_rules` wsr
                JOIN `roles` r ON r.id = wsr.required_role_id
                WHERE wsr.`workflow_definition_id` = :definition_id
                ORDER BY wsr.`step_order` ASC";

        $rows = $this->fetchAll($sql, ['definition_id' => $definitionId]);

        return array_map(fn(array $row) => WorkflowStepRuleDTO::fromArray($row), $rows);
    }

    public function findByStepOrder(int $definitionId, int $stepOrder): ?WorkflowStepRuleDTO
    {
        $sql = "SELECT wsr.*, r.role_code, r.role_title
                FROM `workflow_step_rules` wsr
                JOIN `roles` r ON r.id = wsr.required_role_id
                WHERE wsr.`workflow_definition_id` = :definition_id
                  AND wsr.`step_order` = :step_order
                LIMIT 1";

        $row = $this->fetchOne($sql, [
            'definition_id' => $definitionId,
            'step_order' => $stepOrder,
        ]);

        return $row ? WorkflowStepRuleDTO::fromArray($row) : null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO `workflow_step_rules` (
                    `workflow_definition_id`,
                    `step_order`,
                    `step_name`,
                    `required_role_id`,
                    `threshold_min_amount`,
                    `threshold_max_amount`,
                    `is_mandatory`,
                    `created_at`,
                    `created_by`
                ) VALUES (
                    :workflow_definition_id,
                    :step_order,
                    :step_name,
                    :required_role_id,
                    :threshold_min_amount,
                    :threshold_max_amount,
                    :is_mandatory,
                    :created_at,
                    :created_by
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':workflow_definition_id', (int)$data['workflow_definition_id'], PDO::PARAM_INT);
        $stmt->bindValue(':step_order', (int)$data['step_order'], PDO::PARAM_INT);
        $stmt->bindValue(':step_name', (string)$data['step_name'], PDO::PARAM_STR);
        $stmt->bindValue(':required_role_id', (int)$data['required_role_id'], PDO::PARAM_INT);

        if (isset($data['threshold_min_amount']) && $data['threshold_min_amount'] !== null && $data['threshold_min_amount'] !== '') {
            $stmt->bindValue(':threshold_min_amount', Decimal::normalize($data['threshold_min_amount'], 2), PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':threshold_min_amount', null, PDO::PARAM_NULL);
        }

        if (isset($data['threshold_max_amount']) && $data['threshold_max_amount'] !== null && $data['threshold_max_amount'] !== '') {
            $stmt->bindValue(':threshold_max_amount', Decimal::normalize($data['threshold_max_amount'], 2), PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':threshold_max_amount', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':is_mandatory', isset($data['is_mandatory']) ? (int)(bool)$data['is_mandatory'] : 1, PDO::PARAM_INT);
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

        if (array_key_exists('step_name', $data)) {
            $fields[] = "`step_name` = :step_name";
            $params['step_name'] = (string)$data['step_name'];
        }

        if (array_key_exists('required_role_id', $data)) {
            $fields[] = "`required_role_id` = :required_role_id";
            $params['required_role_id'] = (int)$data['required_role_id'];
        }

        if (array_key_exists('threshold_min_amount', $data)) {
            $fields[] = "`threshold_min_amount` = :threshold_min_amount";
            $params['threshold_min_amount'] = $data['threshold_min_amount'] !== null ? Decimal::normalize($data['threshold_min_amount'], 2) : null;
        }

        if (array_key_exists('threshold_max_amount', $data)) {
            $fields[] = "`threshold_max_amount` = :threshold_max_amount";
            $params['threshold_max_amount'] = $data['threshold_max_amount'] !== null ? Decimal::normalize($data['threshold_max_amount'], 2) : null;
        }

        if (array_key_exists('is_mandatory', $data)) {
            $fields[] = "`is_mandatory` = :is_mandatory";
            $params['is_mandatory'] = (int)(bool)$data['is_mandatory'];
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

        $sql = "UPDATE `workflow_step_rules` SET " . implode(', ', $fields) . " WHERE `id` = :id";

        return $this->execute($sql, $params) > 0;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `workflow_step_rules` WHERE `id` = :id";

        return $this->execute($sql, ['id' => $id]) > 0;
    }

    public function deleteByWorkflowDefinitionId(int $definitionId): int
    {
        $sql = "DELETE FROM `workflow_step_rules` WHERE `workflow_definition_id` = :def_id";

        return $this->execute($sql, ['def_id' => $definitionId]);
    }
}
