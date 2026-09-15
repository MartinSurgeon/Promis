<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Service;

use PDO;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\NotFoundException;
use Promis\Core\Exception\ValidationException;
use Promis\Src\Execution\Repository\AuditLogRepository;
use Promis\Src\Execution\Repository\AuditLogRepositoryInterface;
use Promis\Src\Identity\Domain\DTO\AssignRoleEntityDTO;
use Promis\Src\Identity\Domain\DTO\CreateUserDTO;
use Promis\Src\Identity\Domain\DTO\UpdateUserDTO;
use Promis\Src\Identity\Repository\PlanningEntityRepository;
use Promis\Src\Identity\Repository\PlanningEntityRepositoryInterface;
use Promis\Src\Identity\Repository\UserEntityRoleRepository;
use Promis\Src\Identity\Repository\UserEntityRoleRepositoryInterface;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Repository\UserRepositoryInterface;
use Throwable;

/**
 * Enterprise Service implementation for User Onboarding, Profile Lifecycle,
 * and Entity-Scoped Role Allocation with Audit Logging.
 */
class UserManagementService implements UserManagementServiceInterface
{
    private PDO $db;

    public function __construct(
        private ?UserRepositoryInterface $userRepository = null,
        private ?UserEntityRoleRepositoryInterface $uerRepository = null,
        private ?PlanningEntityRepositoryInterface $entityRepository = null,
        private ?AuditLogRepositoryInterface $auditLogRepository = null
    ) {
        $this->db = Connection::get();
        $this->userRepository = $this->userRepository ?? new UserRepository();
        $this->uerRepository = $this->uerRepository ?? new UserEntityRoleRepository();
        $this->entityRepository = $this->entityRepository ?? new PlanningEntityRepository();
        $this->auditLogRepository = $this->auditLogRepository ?? new AuditLogRepository();
    }

    public function getUsersPaginated(
        int $page = 1,
        int $limit = 15,
        string $search = '',
        string $roleFilter = '',
        ?int $entityFilter = null,
        string $statusFilter = ''
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $totalRecords = $this->userRepository->countFilteredUsers($search, $roleFilter, $entityFilter, $statusFilter);
        $totalPages = max(1, (int)ceil($totalRecords / $limit));

        $rawUsers = $this->userRepository->findPaginatedUsers($page, $limit, $search, $roleFilter, $entityFilter, $statusFilter);

        $userIds = array_map(fn($u) => (int)$u['id'], $rawUsers);
        $groupedAssignments = $this->uerRepository->getGroupedAssignmentsByUserIds($userIds);

        $users = [];
        foreach ($rawUsers as $u) {
            $uid = (int)$u['id'];
            $assignData = $groupedAssignments[$uid] ?? ['roles' => [], 'entities' => [], 'assignments' => []];

            $u['roles'] = $assignData['roles'];
            $u['entities'] = $assignData['entities'];
            $u['assignments'] = $assignData['assignments'];
            $users[] = $u;
        }

        return [
            'users' => $users,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit,
            'metrics' => $this->userRepository->getSummaryMetrics(),
        ];
    }

    public function getUserDetails(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException("Staff member with ID #{$userId} not found.");
        }

        $assignments = $this->uerRepository->findByUserId($userId);

        return [
            'user' => $user->toArray(),
            'assignments' => array_map(fn($a) => $a->toArray(), $assignments),
        ];
    }

    public function onboardUser(
        CreateUserDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): int {
        // 1. Validation
        if ($dto->username === '') {
            throw new ValidationException('Username is required.');
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $dto->username)) {
            throw new ValidationException('Username must be 3-50 alphanumeric characters and may contain dots, hyphens, or underscores.');
        }
        if ($dto->email === '' || !filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('A valid institutional email address is required.');
        }
        if ($dto->firstName === '') {
            throw new ValidationException('First name is required.');
        }
        if ($dto->lastName === '') {
            throw new ValidationException('Last name is required.');
        }
        if (strlen($dto->password) < 8) {
            throw new ValidationException('Temporary password must be at least 8 characters long.');
        }

        // Check uniqueness
        if ($this->userRepository->findByUsername($dto->username) !== null) {
            throw new ValidationException("Username '{$dto->username}' is already registered.");
        }
        if ($this->userRepository->findByEmail($dto->email) !== null) {
            throw new ValidationException("Email '{$dto->email}' is already in use by another user account.");
        }

        $this->db->beginTransaction();
        try {
            $hashedPassword = password_hash($dto->password, PASSWORD_BCRYPT, ['cost' => 12]);

            $userId = $this->userRepository->create([
                'username' => $dto->username,
                'email' => $dto->email,
                'password_hash' => $hashedPassword,
                'first_name' => $dto->firstName,
                'last_name' => $dto->lastName,
                'phone' => $dto->phone,
                'status' => $dto->status,
                'created_by' => $actorUserId,
            ]);

            // Optional Initial Role & Department Allocation
            if ($dto->initialRoleId && $dto->initialPlanningEntityId) {
                $role = $this->entityRepository->findRoleById($dto->initialRoleId);
                if (!$role) {
                    throw new ValidationException('Selected initial role is invalid.');
                }
                $entity = $this->entityRepository->findById($dto->initialPlanningEntityId);
                if (!$entity) {
                    throw new ValidationException('Selected initial planning entity is invalid.');
                }

                $this->uerRepository->create([
                    'user_id' => $userId,
                    'planning_entity_id' => $dto->initialPlanningEntityId,
                    'role_id' => $dto->initialRoleId,
                    'is_primary' => $dto->isPrimary ? 1 : 0,
                    'status' => 'ACTIVE',
                    'assigned_by' => $actorUserId,
                ]);
            }

            // Institutional Audit Log
            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => $dto->initialPlanningEntityId,
                'action' => 'USER_ONBOARDED',
                'record_type' => 'users',
                'record_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => null,
                'new_state_json' => json_encode([
                    'id' => $userId,
                    'username' => $dto->username,
                    'email' => $dto->email,
                    'first_name' => $dto->firstName,
                    'last_name' => $dto->lastName,
                    'status' => $dto->status,
                    'initial_role_id' => $dto->initialRoleId,
                    'initial_entity_id' => $dto->initialPlanningEntityId,
                ], JSON_THROW_ON_ERROR),
            ]);

            $this->db->commit();
            return $userId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateUser(
        UpdateUserDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool {
        $existing = $this->userRepository->findById($dto->id);
        if (!$existing) {
            throw new NotFoundException("Staff member with ID #{$dto->id} does not exist.");
        }

        if ($dto->firstName === '' || $dto->lastName === '') {
            throw new ValidationException('First and last names cannot be blank.');
        }
        if ($dto->email === '' || !filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('A valid email address is required.');
        }

        // Email uniqueness check
        $emailOwner = $this->userRepository->findByEmail($dto->email);
        if ($emailOwner !== null && $emailOwner->id !== $dto->id) {
            throw new ValidationException("Email '{$dto->email}' is already registered to another staff member.");
        }

        $this->db->beginTransaction();
        try {
            $dataToUpdate = [
                'first_name' => $dto->firstName,
                'last_name' => $dto->lastName,
                'email' => $dto->email,
                'phone' => $dto->phone,
            ];

            if (!empty($dto->password)) {
                if (strlen($dto->password) < 8) {
                    throw new ValidationException('New password must be at least 8 characters long.');
                }
                $dataToUpdate['password_hash'] = password_hash($dto->password, PASSWORD_BCRYPT, ['cost' => 12]);
            }

            if (!empty($dto->status)) {
                $dataToUpdate['status'] = $dto->status;
            }

            $this->userRepository->updateProfile($dto->id, $dataToUpdate, $actorUserId);

            // Audit Log
            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => null,
                'action' => 'USER_PROFILE_UPDATED',
                'record_type' => 'users',
                'record_id' => $dto->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => json_encode([
                    'first_name' => $existing->firstName,
                    'last_name' => $existing->lastName,
                    'email' => $existing->email,
                    'phone' => $existing->phone,
                    'status' => $existing->status,
                ], JSON_THROW_ON_ERROR),
                'new_state_json' => json_encode($dataToUpdate, JSON_THROW_ON_ERROR),
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function toggleUserStatus(
        int $userId,
        string $status,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool {
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'PENDING'], true)) {
            throw new ValidationException("Invalid status value '{$status}'.");
        }

        if ($userId === $actorUserId && $status !== 'ACTIVE') {
            throw new ValidationException('Security restriction: You cannot deactivate your own administrative account.');
        }

        $existing = $this->userRepository->findById($userId);
        if (!$existing) {
            throw new NotFoundException("Staff member with ID #{$userId} not found.");
        }

        if ($existing->status === $status) {
            return true;
        }

        $this->db->beginTransaction();
        try {
            $this->userRepository->updateStatus($userId, $status);

            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => null,
                'action' => 'USER_STATUS_CHANGED',
                'record_type' => 'users',
                'record_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => json_encode(['status' => $existing->status], JSON_THROW_ON_ERROR),
                'new_state_json' => json_encode(['status' => $status], JSON_THROW_ON_ERROR),
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function assignRoleEntity(
        AssignRoleEntityDTO $dto,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): int {
        $user = $this->userRepository->findById($dto->userId);
        if (!$user) {
            throw new NotFoundException("User #{$dto->userId} does not exist.");
        }

        $role = $this->entityRepository->findRoleById($dto->roleId);
        if (!$role) {
            throw new ValidationException("Selected role #{$dto->roleId} does not exist or is inactive.");
        }

        $entity = $this->entityRepository->findById($dto->planningEntityId);
        if (!$entity) {
            throw new ValidationException("Selected planning entity #{$dto->planningEntityId} does not exist.");
        }

        // Check for duplicate active assignment
        $existing = $this->uerRepository->findExisting($dto->userId, $dto->planningEntityId, $dto->roleId);
        if ($existing) {
            if ($existing->status === 'ACTIVE') {
                throw new ValidationException("Staff member already holds the '{$role['role_title']}' role for '{$entity['entity_name']}'.");
            }
            // If inactive, reactivate it
            $this->db->beginTransaction();
            try {
                $this->uerRepository->updateStatus($existing->id, 'ACTIVE', $actorUserId);
                if ($dto->isPrimary) {
                    $this->uerRepository->setPrimary($dto->userId, $existing->id, $actorUserId);
                }

                $this->auditLogRepository->create([
                    'event_timestamp' => date('Y-m-d H:i:s'),
                    'actor_user_id' => $actorUserId,
                    'planning_entity_id' => $dto->planningEntityId,
                    'action' => 'ROLE_ENTITY_REACTIVATED',
                    'record_type' => 'user_entity_roles',
                    'record_id' => $existing->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'previous_state_json' => json_encode($existing->toArray(), JSON_THROW_ON_ERROR),
                    'new_state_json' => json_encode(['status' => 'ACTIVE', 'is_primary' => $dto->isPrimary], JSON_THROW_ON_ERROR),
                ]);

                $this->db->commit();
                return $existing->id;
            } catch (Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        $this->db->beginTransaction();
        try {
            if ($dto->isPrimary) {
                $this->uerRepository->clearPrimaryForUser($dto->userId, $actorUserId);
            }

            $assignmentId = $this->uerRepository->create([
                'user_id' => $dto->userId,
                'planning_entity_id' => $dto->planningEntityId,
                'role_id' => $dto->roleId,
                'is_primary' => $dto->isPrimary ? 1 : 0,
                'status' => $dto->status,
                'assigned_by' => $actorUserId,
            ]);

            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => $dto->planningEntityId,
                'action' => 'ROLE_ENTITY_ASSIGNED',
                'record_type' => 'user_entity_roles',
                'record_id' => $assignmentId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => null,
                'new_state_json' => json_encode([
                    'id' => $assignmentId,
                    'user_id' => $dto->userId,
                    'role' => $role['role_code'],
                    'entity' => $entity['entity_name'],
                    'is_primary' => $dto->isPrimary,
                ], JSON_THROW_ON_ERROR),
            ]);

            $this->db->commit();
            return $assignmentId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function revokeRoleAssignment(
        int $assignmentId,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool {
        $assignment = $this->uerRepository->findById($assignmentId);
        if (!$assignment) {
            throw new NotFoundException("Role assignment #{$assignmentId} not found.");
        }

        $this->db->beginTransaction();
        try {
            $this->uerRepository->delete($assignmentId);

            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => $assignment->planningEntityId,
                'action' => 'ROLE_ENTITY_REVOKED',
                'record_type' => 'user_entity_roles',
                'record_id' => $assignmentId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => json_encode($assignment->toArray(), JSON_THROW_ON_ERROR),
                'new_state_json' => null,
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function setPrimaryAssignment(
        int $userId,
        int $assignmentId,
        int $actorUserId,
        string $ipAddress,
        ?string $userAgent = null
    ): bool {
        $assignment = $this->uerRepository->findById($assignmentId);
        if (!$assignment || $assignment->userId !== $userId) {
            throw new NotFoundException("Assignment #{$assignmentId} does not belong to user #{$userId}.");
        }

        $this->db->beginTransaction();
        try {
            $this->uerRepository->setPrimary($userId, $assignmentId, $actorUserId);

            $this->auditLogRepository->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $actorUserId,
                'planning_entity_id' => $assignment->planningEntityId,
                'action' => 'PRIMARY_ENTITY_UPDATED',
                'record_type' => 'user_entity_roles',
                'record_id' => $assignmentId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'previous_state_json' => json_encode(['user_id' => $userId, 'previous_primary_id' => null]),
                'new_state_json' => json_encode(['user_id' => $userId, 'new_primary_id' => $assignmentId]),
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getLookups(): array
    {
        return [
            'entities' => $this->entityRepository->findAllActive(),
            'roles' => $this->entityRepository->findAllActiveRoles(),
            'metrics' => $this->userRepository->getSummaryMetrics(),
        ];
    }
}
