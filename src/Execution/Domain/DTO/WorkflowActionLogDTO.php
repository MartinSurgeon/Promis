<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

/**
 * Data Transfer Object representing an append-only workflow action log (workflow_action_logs).
 */
final class WorkflowActionLogDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $documentType,
        public readonly int $documentId,
        public readonly ?int $stepId,
        public readonly int $actorUserId,
        public readonly string $action,
        public readonly string $preStatus,
        public readonly string $postStatus,
        public readonly ?string $comments = null,
        public readonly string $actionTimestamp = '',
        public readonly ?string $actorName = null,
        public readonly ?string $stepName = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            documentType: (string)$data['document_type'],
            documentId: (int)$data['document_id'],
            stepId: !empty($data['step_id']) ? (int)$data['step_id'] : null,
            actorUserId: (int)$data['actor_user_id'],
            action: (string)$data['action'],
            preStatus: (string)$data['pre_status'],
            postStatus: (string)$data['post_status'],
            comments: isset($data['comments']) ? (string)$data['comments'] : null,
            actionTimestamp: (string)($data['action_timestamp'] ?? ''),
            actorName: $data['actor_name'] ?? null,
            stepName: $data['step_name'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'step_id' => $this->stepId,
            'actor_user_id' => $this->actorUserId,
            'action' => $this->action,
            'pre_status' => $this->preStatus,
            'post_status' => $this->postStatus,
            'comments' => $this->comments,
            'action_timestamp' => $this->actionTimestamp,
            'actor_name' => $this->actorName,
            'step_name' => $this->stepName,
        ];
    }
}
