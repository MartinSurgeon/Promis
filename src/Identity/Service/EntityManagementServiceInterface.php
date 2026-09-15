<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Service;

use Promis\Src\Identity\Domain\DTO\CreateEntityDTO;
use Promis\Src\Identity\Domain\DTO\UpdateEntityDTO;

/**
 * Interface for Planning Entity Management business logic.
 */
interface EntityManagementServiceInterface
{
    /**
     * Retrieve paginated entities with hierarchy details.
     */
    public function getEntitiesPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?int $typeFilter = null,
        ?int $campusFilter = null,
        ?string $statusFilter = null
    ): array;

    /**
     * Retrieve full details for a single entity including children and assignments.
     */
    public function getEntityDetails(int $id): array;

    /**
     * Create a new planning entity with hierarchy closure table management.
     */
    public function createEntity(
        CreateEntityDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): int;

    /**
     * Update an existing planning entity with circular-reference prevention.
     */
    public function updateEntity(
        UpdateEntityDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool;

    /**
     * Activate or deactivate an entity with active-child warnings.
     */
    public function toggleEntityStatus(
        int $id,
        bool $isActive,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool;

    /**
     * Retrieve the full entity tree for hierarchical rendering.
     */
    public function getEntityTree(): array;

    /**
     * Retrieve lookup data for forms: entity types, campuses, metrics, entities for parent dropdown.
     */
    public function getLookups(): array;
}
