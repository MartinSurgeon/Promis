<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Data Transfer Object for updating an existing user's profile.
 */
class UpdateUserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $password = null,
        public readonly ?string $status = null
    ) {
    }

    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            firstName: trim((string)($data['first_name'] ?? '')),
            lastName: trim((string)($data['last_name'] ?? '')),
            email: trim((string)($data['email'] ?? '')),
            phone: !empty($data['phone']) ? trim((string)$data['phone']) : null,
            password: !empty($data['password']) ? (string)$data['password'] : null,
            status: !empty($data['status']) && in_array($data['status'], ['ACTIVE', 'INACTIVE', 'PENDING'], true) ? (string)$data['status'] : null
        );
    }
}
