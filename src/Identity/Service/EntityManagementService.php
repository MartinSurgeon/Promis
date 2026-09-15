<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Service;

use PDO;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\NotFoundException;
use Promis\Core\Exception\ValidationException;
use Promis\Src\Execution\Repository\AuditLogRepository;
use Promis\Src\Execution\Repository\AuditLogRepositoryInterface;
use Promis\Src\Identity\Domain\DTO\CreateEntityDTO;
use Promis\Src\Identity\Domain\DTO\UpdateEntityDTO;
use Promis\Src\Identity\Repository\PlanningEntityRepository;
use Promis\Src\Identity\Repository\PlanningEntityRepositoryInterface;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Repository\UserRepositoryInterface;
use Throwable;

/**
 * Enterprise Service for Planning Entity Lifecycle Management,
 * Hierarchical Organization, and Closure Table Integrity.
 */
class EntityManagementService implements EntityManagementServiceInterface
{
    private PDO $db;

    public function __construct(
        private ?PlanningEntityRepositoryInterface $entityRepository = null,
        private ?AuditLogRepositoryInterface $auditLogRepository = null,
        private ?UserRepositoryInterface $userRepository = null
    ) {
        $this->db = Connection::get();
        $this->entityRepository = $this->entityRepository ?? new PlanningEntityRepository();
        $this->auditLogRepository = $this->auditLogRepository ?? new AuditLogRepository();
        $this->userRepository = $this->userRepository ?? new UserRepository();
    }

    public function getEntitiesPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?int $typeFilter = null,
        ?int $campusFilter = null,
        ?string $statusFilter = null
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $entities = $this->entityRepository->findAllWithHierarchy($search, $typeFilter, $campusFilter, $statusFilter);

        $totalRecords = count($entities);
        $totalPages = max(1, (int)ceil($totalRecords / $limit));
        $offset = ($page - 1) * $limit;
        $paginatedEntities = array_slice($entities, $offset, $limit);

        $statusMetrics = $this->entityRepository->countEntitiesByStatus();
        $typeMetrics = $this->entityRepository->countEntitiesByType();
        $maxDepth = $this->entityRepository->getMaxHierarchyDepth();

        return [
            'entities' => $paginatedEntities,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit,
            'metrics' => [
                'total_entities' => (int)($statusMetrics['total'] ?? 0),
                'active_entities' => (int)($statusMetrics['active'] ?? 0),
                'inactive_entities' => (int)($statusMetrics['inactive'] ?? 0),
                'entity_types_count' => count($typeMetrics),
                'max_hierarchy_depth' => $maxDepth,
                'type_breakdown' => $typeMetrics,
            ],
        ];
    }

    public function getEntityDetails(int $id): array
    {
        $entity = $this->entityRepository->findById($id);
        if (!$entity) {
            throw new NotFoundException("Planning entity #{$id} not found.");
        }

        $children = $this->entityRepository->findChildren($id);
        $descendantIds = $this->entityRepository->findDescendantIds($id);
        $assignedUsers = $this->entityRepository->countAssignedUsers($id);

        return [
            'entity' => $entity,
            'children' => $children,
            'descendant_count' => count($descendantIds),
            'assigned_users' => $assignedUsers,
        ];
    }

    public function createEntity(
        CreateEntityDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): int {
        // Validation
        if ($dto->entityCode === '') {
            throw new ValidationException('Entity code is required.');
        }
        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,48}[A-Z0-9]$/', $dto->entityCode)
            && !preg_match('/^[A-Z0-9]{2,50}$/', $dto->entityCode)) {
            // Allow codes like "CS-DEPT", "UNIV-001", "FAC_BUS"
            if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{0,48}[A-Z0-9]?$/', $dto->entityCode)) {
                throw new ValidationException('Entity code must be 2-50 uppercase alphanumeric characters (dots, hyphens, underscores allowed).');
            }
        }
        if ($dto->entityName === '') {
            throw new ValidationException('Entity name is required.');
        }
        if (strlen($dto->entityName) > 150) {
            throw new ValidationException('Entity name must not exceed 150 characters.');
        }

        // Uniqueness check
        $existingCode = $this->entityRepository->findByCode($dto->entityCode);
        if ($existingCode) {
            throw new ValidationException("Entity code '{$dto->entityCode}' is already in use by '{$existingCode['entity_name']}'.");
        }

        // Entity type validation
        $entityTypes = $this->entityRepository->findAllEntityTypes();
        $validTypeIds = array_column($entityTypes, 'id');
        if (!in_array($dto->entityTypeId, array_map('intval', $validTypeIds), true)) {
            throw new ValidationException('Selected entity type is invalid.');
        }

        // Campus validation
        $campuses = $this->entityRepository->findAllCampuses();
        $validCampusIds = array_column($campuses, 'id');
        if (!in_array($dto->campusId, array_map('intval', $validCampusIds), true)) {
            throw new ValidationException('Selected campus is invalid.');
        }

        // Parent validation
        if ($dto->parentEntityId !== null) {
            $parent = $this->entityRepository->findById($dto->parentEntityId);
            if (!$parent) {
                throw new ValidationException("Selected parent entity #{$dto->parentEntityId} does not exist.");
            }
            if (!$parent['is_active']) {
                throw new ValidationException("Cannot create under inactive parent entity '{$parent['entity_name']}'.");
            }
        }

        // Optional user validation
        if ($dto->headUserId !== null) {
            $headUser = $this->userRepository->findById($dto->headUserId);
            if (!$headUser) {
                throw new ValidationException("Head user #{$dto->headUserId} does not exist.");
            }
        }
        if ($dto->planningOfficerId !== null) {
            $planningOfficer = $this->userRepository->findById($dto->planningOfficerId);
            if (!$planningOfficer) {
                throw new ValidationException("Planning officer #{$dto->planningOfficerId} does not exist.");
            }
        }

        $this->db->beginTransaction();
        try {
            $entityId = $this->entityRepository->createEntity([
                'entity_code' => $dto->entityCode,
                'entity_name' => $dto->entityName,
                'entity_type_id' => $dto->entityTypeId,
                'campus_id' => $dto->campusId,
                'parent_entity_id' => $dto->parentEntityId,
                'head_user_id' => $dto->headUserId,
                'planning_officer_id' => $dto->planningOfficerId,
                'created_by' => $actorUserId,
            ]);

            // Populate closure table
            $this->entityRepository->insertHierarchyPaths($entityId, $dto->parentEntityId);

            // Audit log
            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => $entityId,
                'action' => 'ENTITY_CREATED',
                'record_type' => 'planning_entities',
                'record_id' => $entityId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => null,
                'new_state_json' => json_encode([
                    'id' => $entityId,
                    'entity_code' => $dto->entityCode,
                    'entity_name' => $dto->entityName,
                    'entity_type_id' => $dto->entityTypeId,
                    'campus_id' => $dto->campusId,
                    'parent_entity_id' => $dto->parentEntityId,
                ], JSON_THROW_ON_ERROR),
            ]);

            $this->db->commit();
            return $entityId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateEntity(
        UpdateEntityDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool {
        $existing = $this->entityRepository->findById($dto->id);
        if (!$existing) {
            throw new NotFoundException("Planning entity #{$dto->id} does not exist.");
        }

        if ($dto->entityName === '') {
            throw new ValidationException('Entity name is required.');
        }
        if (strlen($dto->entityName) > 150) {
            throw new ValidationException('Entity name must not exceed 150 characters.');
        }

        // Entity type validation
        $entityTypes = $this->entityRepository->findAllEntityTypes();
        $validTypeIds = array_column($entityTypes, 'id');
        if (!in_array($dto->entityTypeId, array_map('intval', $validTypeIds), true)) {
            throw new ValidationException('Selected entity type is invalid.');
        }

        // Campus validation
        $campuses = $this->entityRepository->findAllCampuses();
        $validCampusIds = array_column($campuses, 'id');
        if (!in_array($dto->campusId, array_map('intval', $validCampusIds), true)) {
            throw new ValidationException('Selected campus is invalid.');
        }

        // Parent validation — circular-reference prevention
        $parentChanged = ((int)($existing['parent_entity_id'] ?? 0)) !== ($dto->parentEntityId ?? 0);

        if ($dto->parentEntityId !== null) {
            // Cannot be own parent
            if ($dto->parentEntityId === $dto->id) {
                throw new ValidationException('An entity cannot be its own parent.');
            }

            $parent = $this->entityRepository->findById($dto->parentEntityId);
            if (!$parent) {
                throw new ValidationException("Selected parent entity #{$dto->parentEntityId} does not exist.");
            }
            if (!$parent['is_active']) {
                throw new ValidationException("Cannot move under inactive parent entity '{$parent['entity_name']}'.");
            }

            // Cannot set a descendant as parent (circular reference)
            $descendantIds = $this->entityRepository->findDescendantIds($dto->id);
            if (in_array($dto->parentEntityId, $descendantIds, true)) {
                throw new ValidationException("Circular reference detected: the selected parent is a descendant of this entity.");
            }
        }

        // Optional user validation
        if ($dto->headUserId !== null) {
            $headUser = $this->userRepository->findById($dto->headUserId);
            if (!$headUser) {
                throw new ValidationException("Head user #{$dto->headUserId} does not exist.");
            }
        }
        if ($dto->planningOfficerId !== null) {
            $planningOfficer = $this->userRepository->findById($dto->planningOfficerId);
            if (!$planningOfficer) {
                throw new ValidationException("Planning officer #{$dto->planningOfficerId} does not exist.");
            }
        }

        $this->db->beginTransaction();
        try {
            $this->entityRepository->updateEntity($dto->id, [
                'entity_name' => $dto->entityName,
                'entity_type_id' => $dto->entityTypeId,
                'campus_id' => $dto->campusId,
                'parent_entity_id' => $dto->parentEntityId,
                'head_user_id' => $dto->headUserId,
                'planning_officer_id' => $dto->planningOfficerId,
            ], $actorUserId);

            // Rebuild hierarchy if parent changed
            if ($parentChanged) {
                $this->entityRepository->removeHierarchyPaths($dto->id);
                $this->entityRepository->insertHierarchyPaths($dto->id, $dto->parentEntityId);
                $this->entityRepository->rebuildDescendantPaths($dto->id);

                // Separate audit for parent change
                $this->auditLogRepository->create([
                    'event_timestamp' => date('Y-m-d H:i:s'),
                    'actor_user_id' => $actorUserId,
                    'planning_entity_id' => $dto->id,
                    'action' => 'ENTITY_PARENT_CHANGED',
                    'record_type' => 'planning_entities',
                    'record_id' => $dto->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'previous_state_json' => json_encode([
                        'parent_entity_id' => $existing['parent_entity_id'],
                    ], JSON_THROW_ON_ERROR),
                    'new_state_json' => json_encode([
                        'parent_entity_id' => $dto->parentEntityId,
                    ], JSON_THROW_ON_ERROR),
                ]);
            }

            // General update audit
            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => $dto->id,
                'action' => 'ENTITY_UPDATED',
                'record_type' => 'planning_entities',
                'record_id' => $dto->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => json_encode([
                    'entity_name' => $existing['entity_name'],
                    'entity_type_id' => $existing['entity_type_id'],
                    'campus_id' => $existing['campus_id'],
                    'parent_entity_id' => $existing['parent_entity_id'],
                    'head_user_id' => $existing['head_user_id'],
                    'planning_officer_id' => $existing['planning_officer_id'],
                ], JSON_THROW_ON_ERROR),
                'new_state_json' => json_encode($dto->toArray(), JSON_THROW_ON_ERROR),
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function toggleEntityStatus(
        int $id,
        bool $isActive,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool {
        $existing = $this->entityRepository->findById($id);
        if (!$existing) {
            throw new NotFoundException("Planning entity #{$id} not found.");
        }

        $currentActive = (bool)$existing['is_active'];
        if ($currentActive === $isActive) {
            return true; // No change needed
        }

        $this->db->beginTransaction();
        try {
            $this->entityRepository->updateEntityStatus($id, $isActive);

            $action = $isActive ? 'ENTITY_ACTIVATED' : 'ENTITY_DEACTIVATED';

            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => $id,
                'action' => $action,
                'record_type' => 'planning_entities',
                'record_id' => $id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => json_encode([
                    'is_active' => $currentActive,
                    'has_active_children' => $this->entityRepository->hasActiveChildren($id),
                ], JSON_THROW_ON_ERROR),
                'new_state_json' => json_encode([
                    'is_active' => $isActive,
                ], JSON_THROW_ON_ERROR),
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getEntityTree(): array
    {
        $allEntities = $this->entityRepository->findAllWithHierarchy();

        // Build a lookup by ID
        $byId = [];
        foreach ($allEntities as $entity) {
            $entity['children_nodes'] = [];
            $byId[(int)$entity['id']] = $entity;
        }

        // Build tree structure
        $tree = [];
        foreach ($byId as $id => &$entity) {
            $parentId = $entity['parent_entity_id'] ? (int)$entity['parent_entity_id'] : null;
            if ($parentId !== null && isset($byId[$parentId])) {
                $byId[$parentId]['children_nodes'][] = &$entity;
            } else {
                $tree[] = &$entity;
            }
        }
        unset($entity);

        return $tree;
    }

    public function getLookups(): array
    {
        return [
            'entity_types' => $this->entityRepository->findAllEntityTypes(),
            'campuses' => $this->entityRepository->findAllCampuses(),
            'entities' => $this->entityRepository->findAllActive(),
            'metrics' => $this->entityRepository->countEntitiesByStatus(),
        ];
    }
}
