<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

/**
 * Interface for querying planning entities, entity types, campuses, and hierarchy management.
 */
interface PlanningEntityRepositoryInterface
{
    // --- Existing methods ---
    public function findAllActive(): array;
    public function findById(int $id): ?array;
    public function findAllActiveRoles(): array;
    public function findRoleById(int $id): ?array;

    // --- Hierarchical Entity Management ---
    public function findAll(?string $search = null, ?int $typeFilter = null, ?int $campusFilter = null, ?string $statusFilter = null): array;
    public function findAllWithHierarchy(?string $search = null, ?int $typeFilter = null, ?int $campusFilter = null, ?string $statusFilter = null): array;
    public function findByCode(string $code): ?array;
    public function findChildren(int $parentId): array;
    public function findDescendantIds(int $entityId): array;
    public function findAllEntityTypes(): array;
    public function findAllCampuses(): array;
    public function createEntity(array $data): int;
    public function updateEntity(int $id, array $data, int $actorId): bool;
    public function updateEntityStatus(int $id, bool $isActive): bool;
    public function insertHierarchyPaths(int $entityId, ?int $parentId): void;
    public function removeHierarchyPaths(int $entityId): void;
    public function rebuildDescendantPaths(int $entityId): void;
    public function countEntitiesByType(): array;
    public function countEntitiesByStatus(): array;
    public function hasActiveChildren(int $entityId): bool;
    public function countAssignedUsers(int $entityId): int;
    public function getMaxHierarchyDepth(): int;
}
