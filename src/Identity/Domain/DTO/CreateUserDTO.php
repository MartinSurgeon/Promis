<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Data Transfer Object for onboarding a new user.
 */
class CreateUserDTO
{
    /**
     * @param string[] $responsibilities
     */
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $phone,
        public readonly string $password,
        public readonly string $status = 'ACTIVE',
        public readonly ?int $initialRoleId = null,
        public readonly ?int $initialPlanningEntityId = null,
        public readonly bool $isPrimary = true,
        public readonly ?int $positionId = null,
        public readonly ?int $assignedPlanningEntityId = null,
        public readonly array $responsibilities = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        $rawResponsibilities = $data['responsibilities'] ?? [];
        if (!is_array($rawResponsibilities)) {
            $rawResponsibilities = [];
        }

        $positionId = !empty($data['position_id']) ? (int)$data['position_id'] : null;
        $assignedEntityId = !empty($data['assigned_planning_entity_id']) 
            ? (int)$data['assigned_planning_entity_id'] 
            : (!empty($data['initial_planning_entity_id']) ? (int)$data['initial_planning_entity_id'] : null);

        return new self(
            username: trim((string)($data['username'] ?? '')),
            email: trim((string)($data['email'] ?? '')),
            firstName: trim((string)($data['first_name'] ?? '')),
            lastName: trim((string)($data['last_name'] ?? '')),
            phone: !empty($data['phone']) ? trim((string)$data['phone']) : null,
            password: (string)($data['password'] ?? ''),
            status: in_array($data['status'] ?? 'ACTIVE', ['ACTIVE', 'INACTIVE', 'PENDING'], true) ? (string)$data['status'] : 'ACTIVE',
            initialRoleId: !empty($data['initial_role_id']) ? (int)$data['initial_role_id'] : null,
            initialPlanningEntityId: $assignedEntityId,
            isPrimary: (bool)($data['is_primary'] ?? true),
            positionId: $positionId,
            assignedPlanningEntityId: $assignedEntityId,
            responsibilities: array_values(array_filter(array_map('strval', $rawResponsibilities)))
        );
    }
}
