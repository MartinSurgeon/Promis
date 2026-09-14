<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Repository;

use Promis\Src\Execution\Domain\DTO\WorkflowStepRuleDTO;

/**
 * Persistence contract for Workflow Step Rules (workflow_step_rules).
 */
interface WorkflowStepRuleRepositoryInterface
{
    public function findById(int $id): ?WorkflowStepRuleDTO;

    /**
     * @return WorkflowStepRuleDTO[]
     */
    public function findByWorkflowDefinitionId(int $definitionId): array;

    public function findByStepOrder(int $definitionId, int $stepOrder): ?WorkflowStepRuleDTO;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    public function deleteByWorkflowDefinitionId(int $definitionId): int;
}
