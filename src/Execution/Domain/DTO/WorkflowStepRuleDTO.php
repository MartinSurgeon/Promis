<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

use Promis\Src\Planning\Domain\Decimal;

/**
 * Data Transfer Object representing a configured workflow step rule (workflow_step_rules).
 */
final class WorkflowStepRuleDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $workflowDefinitionId,
        public readonly int $stepOrder,
        public readonly string $stepName,
        public readonly int $requiredRoleId,
        public readonly ?string $thresholdMinAmount = null,
        public readonly ?string $thresholdMaxAmount = null,
        public readonly bool $isMandatory = true,
        public readonly ?string $createdAt = null,
        public readonly ?int $createdBy = null,
        public readonly ?string $updatedAt = null,
        public readonly ?int $updatedBy = null,
        public readonly ?string $roleCode = null,
        public readonly ?string $roleTitle = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            workflowDefinitionId: (int)$data['workflow_definition_id'],
            stepOrder: (int)$data['step_order'],
            stepName: (string)$data['step_name'],
            requiredRoleId: (int)$data['required_role_id'],
            thresholdMinAmount: isset($data['threshold_min_amount']) && $data['threshold_min_amount'] !== null && $data['threshold_min_amount'] !== ''
                ? Decimal::normalize($data['threshold_min_amount'], 2)
                : null,
            thresholdMaxAmount: isset($data['threshold_max_amount']) && $data['threshold_max_amount'] !== null && $data['threshold_max_amount'] !== ''
                ? Decimal::normalize($data['threshold_max_amount'], 2)
                : null,
            isMandatory: !empty($data['is_mandatory']),
            createdAt: $data['created_at'] ?? null,
            createdBy: !empty($data['created_by']) ? (int)$data['created_by'] : null,
            updatedAt: $data['updated_at'] ?? null,
            updatedBy: !empty($data['updated_by']) ? (int)$data['updated_by'] : null,
            roleCode: $data['role_code'] ?? null,
            roleTitle: $data['role_title'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workflow_definition_id' => $this->workflowDefinitionId,
            'step_order' => $this->stepOrder,
            'step_name' => $this->stepName,
            'required_role_id' => $this->requiredRoleId,
            'threshold_min_amount' => $this->thresholdMinAmount,
            'threshold_max_amount' => $this->thresholdMaxAmount,
            'is_mandatory' => $this->isMandatory,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'role_code' => $this->roleCode,
            'role_title' => $this->roleTitle,
        ];
    }
}
