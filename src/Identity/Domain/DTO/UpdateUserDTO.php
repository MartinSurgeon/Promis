<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Data Transfer Object for updating an existing user's profile.
 */
class UpdateUserDTO
{
    /**
     * @param string[]|null $responsibilities
     */
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $password = null,
        public readonly ?string $status = null,
        public readonly ?int $positionId = null,
        public readonly ?int $assignedPlanningEntityId = null,
        public readonly ?array $responsibilities = null
    ) {
    }

    public static function fromArray(int $id, array $data): self
    {
        $rawResponsibilities = isset($data['responsibilities']) && is_array($data['responsibilities'])
            ? array_values(array_filter(array_map('strval', $data['responsibilities'])))
            : null;

        $positionId = isset($data['position_id']) && $data['position_id'] !== ''
            ? (int)$data['position_id']
            : null;

        $assignedEntityId = isset($data['assigned_planning_entity_id']) && $data['assigned_planning_entity_id'] !== ''
            ? (int)$data['assigned_planning_entity_id']
            : (isset($data['planning_entity_id']) && $data['planning_entity_id'] !== '' ? (int)$data['planning_entity_id'] : null);

        return new self(
            id: $id,
            firstName: trim((string)($data['first_name'] ?? '')),
            lastName: trim((string)($data['last_name'] ?? '')),
            email: trim((string)($data['email'] ?? '')),
            phone: !empty($data['phone']) ? trim((string)$data['phone']) : null,
            password: !empty($data['password']) ? (string)$data['password'] : null,
            status: !empty($data['status']) && in_array($data['status'], ['ACTIVE', 'INACTIVE', 'PENDING'], true) ? (string)$data['status'] : null,
            positionId: $positionId,
            assignedPlanningEntityId: $assignedEntityId,
            responsibilities: $rawResponsibilities
        );
    }
}
