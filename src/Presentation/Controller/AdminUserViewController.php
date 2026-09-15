<?php

declare(strict_types=1);

namespace Promis\Src\Presentation\Controller;

use Promis\Core\Auth\AuthManager;
use Promis\Core\Exception\AuthenticationException;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\NotFoundException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Session;
use Promis\Core\Support\View;
use Promis\Src\Identity\Domain\DTO\AssignRoleEntityDTO;
use Promis\Src\Identity\Domain\DTO\CreateUserDTO;
use Promis\Src\Identity\Domain\DTO\UpdateUserDTO;
use Promis\Src\Identity\Service\UserManagementService;
use Promis\Src\Identity\Service\UserManagementServiceInterface;
use Throwable;

/**
 * Controller for Administrative User Management, Staff Onboarding,
 * and Entity-Scoped Role Provisioning.
 */
final class AdminUserViewController
{
    private UserManagementServiceInterface $userService;

    public function __construct(?UserManagementServiceInterface $userService = null)
    {
        $this->userService = $userService ?? new UserManagementService();
    }

    /**
     * Display the searchable staff directory with department badges, role chips, and KPI metrics.
     */
    public function index(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }

        $page = (int)$request->query('page', 1);
        $limit = 15;
        $search = trim((string)$request->query('search', ''));
        $roleFilter = trim((string)$request->query('role', ''));
        $entityParam = $request->query('entity_id', '');
        $entityFilter = $entityParam !== '' ? (int)$entityParam : null;
        $statusFilter = trim((string)$request->query('status', ''));

        $data = $this->userService->getUsersPaginated(
            page: $page,
            limit: $limit,
            search: $search,
            roleFilter: $roleFilter,
            entityFilter: $entityFilter,
            statusFilter: $statusFilter
        );

        $lookups = $this->userService->getLookups();
        $baseAppUrl = $this->resolveAppUrl($request);

        $html = View::render('admin/users/index', [
            'title' => 'PROMIS - User & Entity Management',
            'users' => $data['users'],
            'totalRecords' => $data['total_records'],
            'totalPages' => $data['total_pages'],
            'page' => $data['current_page'],
            'search' => $search,
            'roleFilter' => $roleFilter,
            'entityFilter' => $entityFilter,
            'statusFilter' => $statusFilter,
            'metrics' => $data['metrics'],
            'entities' => $lookups['entities'],
            'roles' => $lookups['roles'],
            'appUrl' => $baseAppUrl,
            'activeNav' => 'admin_users',
            'pageTitle' => 'User & Entity Management',
            'breadcrumbs' => [
                ['label' => 'Administration', 'url' => ''],
                ['label' => 'User & Entity Management', 'url' => ''],
            ],
            'user' => AuthManager::user(),
        ], 'app');

        return Response::html($html);
    }

    /**
     * Handle staff onboarding form submission.
     */
    public function store(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }
        $this->validateCsrf($request);

        $actor = AuthManager::user();
        $actorId = (int)($actor['id'] ?? 1);

        try {
            $dto = CreateUserDTO::fromArray([
                'username' => $request->post('username'),
                'email' => $request->post('email'),
                'first_name' => $request->post('first_name'),
                'last_name' => $request->post('last_name'),
                'phone' => $request->post('phone'),
                'password' => $request->post('password'),
                'status' => $request->post('status', 'ACTIVE'),
                'initial_role_id' => $request->post('initial_role_id'),
                'initial_planning_entity_id' => $request->post('initial_planning_entity_id'),
                'is_primary' => (bool)$request->post('is_primary', true),
            ]);

            $userId = $this->userService->onboardUser(
                dto: $dto,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', "Staff member '{$dto->firstName} {$dto->lastName}' ({$dto->username}) onboarded successfully!");

            if ($request->isJson()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Staff account created successfully.',
                    'user_id' => $userId,
                ]);
            }

            return Response::redirect('/admin/users');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            if ($request->isJson()) {
                return Response::json(['error' => true, 'message' => $e->getMessage()], 422);
            }
            return Response::redirect('/admin/users');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to onboard user: ' . $e->getMessage());
            if ($request->isJson()) {
                return Response::json(['error' => true, 'message' => $e->getMessage()], 500);
            }
            return Response::redirect('/admin/users');
        }
    }

    /**
     * Handle staff profile updates.
     */
    public function update(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }
        $this->validateCsrf($request);

        $actor = AuthManager::user();
        $actorId = (int)($actor['id'] ?? 1);
        $userId = (int)$request->param('id', 0);

        try {
            $dto = UpdateUserDTO::fromArray($userId, [
                'first_name' => $request->post('first_name'),
                'last_name' => $request->post('last_name'),
                'email' => $request->post('email'),
                'phone' => $request->post('phone'),
                'password' => $request->post('password'),
                'status' => $request->post('status'),
            ]);

            $this->userService->updateUser(
                dto: $dto,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', "Staff account #{$userId} updated successfully.");

            if ($request->isJson()) {
                return Response::json(['success' => true, 'message' => 'Staff profile updated successfully.']);
            }

            return Response::redirect('/admin/users');
        } catch (ValidationException | NotFoundException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/admin/users');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update user: ' . $e->getMessage());
            return Response::redirect('/admin/users');
        }
    }

    /**
     * Handle staff account activation / deactivation.
     */
    public function toggleStatus(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }
        $this->validateCsrf($request);

        $actor = AuthManager::user();
        $actorId = (int)($actor['id'] ?? 1);
        $userId = (int)$request->param('id', 0);
        $status = (string)$request->post('status', '');

        try {
            $this->userService->toggleUserStatus(
                userId: $userId,
                status: $status,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', "Staff status updated to '{$status}'.");

            if ($request->isJson()) {
                return Response::json(['success' => true, 'status' => $status]);
            }

            return Response::redirect('/admin/users');
        } catch (ValidationException | NotFoundException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/admin/users');
        } catch (Throwable $e) {
            Session::flash('error', 'Status update failed: ' . $e->getMessage());
            return Response::redirect('/admin/users');
        }
    }

    /**
     * Handle assigning a new role scoped to a planning entity.
     */
    public function assignRole(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }
        $this->validateCsrf($request);

        $actor = AuthManager::user();
        $actorId = (int)($actor['id'] ?? 1);
        $userId = (int)$request->param('id', 0);

        try {
            $dto = AssignRoleEntityDTO::fromArray([
                'user_id' => $userId,
                'role_id' => (int)$request->post('role_id', 0),
                'planning_entity_id' => (int)$request->post('planning_entity_id', 0),
                'is_primary' => (bool)$request->post('is_primary', false),
                'status' => 'ACTIVE',
            ]);

            $this->userService->assignRoleEntity(
                dto: $dto,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', 'Role and Department assignment created successfully.');

            if ($request->isJson()) {
                return Response::json(['success' => true, 'message' => 'Role assigned successfully.']);
            }

            return Response::redirect('/admin/users');
        } catch (ValidationException | NotFoundException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/admin/users');
        } catch (Throwable $e) {
            Session::flash('error', 'Role assignment failed: ' . $e->getMessage());
            return Response::redirect('/admin/users');
        }
    }

    /**
     * Handle revoking/deleting a role assignment.
     */
    public function revokeRole(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }
        $this->validateCsrf($request);

        $actor = AuthManager::user();
        $actorId = (int)($actor['id'] ?? 1);
        $assignmentId = (int)$request->param('assignment_id', 0);

        try {
            $this->userService->revokeRoleAssignment(
                assignmentId: $assignmentId,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', 'Role assignment revoked successfully.');

            if ($request->isJson()) {
                return Response::json(['success' => true, 'message' => 'Assignment revoked.']);
            }

            return Response::redirect('/admin/users');
        } catch (ValidationException | NotFoundException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/admin/users');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to revoke role assignment: ' . $e->getMessage());
            return Response::redirect('/admin/users');
        }
    }

    /**
     * Handle setting a role assignment as primary for the user.
     */
    public function setPrimaryRole(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }
        $this->validateCsrf($request);

        $actor = AuthManager::user();
        $actorId = (int)($actor['id'] ?? 1);
        $userId = (int)$request->param('id', 0);
        $assignmentId = (int)$request->param('assignment_id', 0);

        try {
            $this->userService->setPrimaryAssignment(
                userId: $userId,
                assignmentId: $assignmentId,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', 'Primary Department updated successfully.');

            if ($request->isJson()) {
                return Response::json(['success' => true, 'message' => 'Primary Department updated.']);
            }

            return Response::redirect('/admin/users');
        } catch (ValidationException | NotFoundException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/admin/users');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to set primary assignment: ' . $e->getMessage());
            return Response::redirect('/admin/users');
        }
    }

    /**
     * API endpoint returning user details and entity assignments for modal population.
     */
    public function getUserJson(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }

        $userId = (int)$request->param('id', 0);
        try {
            $details = $this->userService->getUserDetails($userId);
            return Response::json([
                'success' => true,
                'data' => $details,
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    private function requireAdmin(Request $request): ?Response
    {
        if (AuthManager::guest()) {
            if ($request->isJson()) {
                throw new AuthenticationException('Unauthenticated.');
            }
            return Response::redirect('/login');
        }

        $user = AuthManager::user();
        $roles = $user['roles'] ?? [];
        $isAdmin = in_array('ADMIN', $roles, true) || in_array('SYS_ADMIN', $roles, true);

        if (!$isAdmin) {
            if ($request->isJson()) {
                throw new AuthorizationException('Access denied. Administrative role required.');
            }
            Session::flash('error', 'Access denied. You do not possess administrative permissions.');
            return Response::redirect('/dashboard');
        }

        return null;
    }

    private function validateCsrf(Request $request): void
    {
        $token = (string)$request->post('_csrf_token', '');
        if ($token === '') {
            $token = (string)$request->header('X-CSRF-Token', '');
        }

        if (!Csrf::validate($token)) {
            if ($request->isJson()) {
                throw new ValidationException('Security validation failed: Invalid or expired CSRF token.');
            }
            Session::flash('error', 'Security token expired. Please refresh and try again.');
            Response::redirect('/admin/users')->send();
            exit;
        }
    }

    private function resolveAppUrl(Request $request): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(dirname($scriptName), '/\\');
        return ($base === '' || $base === '/' || $base === '\\') ? '' : $base;
    }
}
