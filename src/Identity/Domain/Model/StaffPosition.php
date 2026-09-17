<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\Model;

/**
 * Value Object representing a formal University Appointment or Office.
 * Positions strictly define title and organizational level, never operational permissions.
 */
final class StaffPosition
{
    public function __construct(
        public readonly int $id,
        public readonly string $positionCode,
        public readonly string $positionTitle,
        public readonly ?string $description = null,
        public readonly string $defaultScopeType = 'DEPT',
        public readonly bool $isActive = true
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            positionCode: (string)$row['position_code'],
            positionTitle: (string)$row['position_title'],
            description: isset($row['description']) ? (string)$row['description'] : null,
            defaultScopeType: (string)($row['default_scope_type'] ?? 'DEPT'),
            isActive: (bool)($row['is_active'] ?? true)
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position_code' => $this->positionCode,
            'position_title' => $this->positionTitle,
            'description' => $this->description,
            'default_scope_type' => $this->defaultScopeType,
            'is_active' => $this->isActive,
        ];
    }
}
