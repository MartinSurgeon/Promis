<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain\DTO;

/**
 * Data Transfer Object representing an institutional audit log entry (audit_logs).
 */
final class AuditLogDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $eventTimestamp,
        public readonly int $actorUserId,
        public readonly ?int $planningEntityId,
        public readonly string $action,
        public readonly string $recordType,
        public readonly int $recordId,
        public readonly string $ipAddress,
        public readonly ?string $userAgent = null,
        public readonly ?string $previousStateJson = null,
        public readonly ?string $newStateJson = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            eventTimestamp: (string)($data['event_timestamp'] ?? ''),
            actorUserId: (int)$data['actor_user_id'],
            planningEntityId: !empty($data['planning_entity_id']) ? (int)$data['planning_entity_id'] : null,
            action: (string)$data['action'],
            recordType: (string)$data['record_type'],
            recordId: (int)$data['record_id'],
            ipAddress: (string)$data['ip_address'],
            userAgent: isset($data['user_agent']) ? (string)$data['user_agent'] : null,
            previousStateJson: isset($data['previous_state_json']) ? (string)$data['previous_state_json'] : null,
            newStateJson: isset($data['new_state_json']) ? (string)$data['new_state_json'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_timestamp' => $this->eventTimestamp,
            'actor_user_id' => $this->actorUserId,
            'planning_entity_id' => $this->planningEntityId,
            'action' => $this->action,
            'record_type' => $this->recordType,
            'record_id' => $this->recordId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'previous_state_json' => $this->previousStateJson,
            'new_state_json' => $this->newStateJson,
        ];
    }
}
