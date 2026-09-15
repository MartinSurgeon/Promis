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
use Promis\Src\Identity\Domain\DTO\CreateEntityDTO;
use Promis\Src\Identity\Domain\DTO\UpdateEntityDTO;
use Promis\Src\Identity\Service\EntityManagementService;
use Promis\Src\Identity\Service\EntityManagementServiceInterface;
use Throwable;

/**
 * Controller for Hierarchical Planning Entity Management.
 * Provides CRUD operations, tree visualization, and hierarchy restructuring.
 */
final class AdminEntityViewController
{
    private EntityManagementServiceInterface $entityService;

    public function __construct(?EntityManagementServiceInterface $entityService = null)
    {
        $this->entityService = $entityService ?? new EntityManagementService();
    }

    /**
     * Display the entity management page with tree view, table, and KPI metrics.
     */
    public function index(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }

        $page = (int)$request->query('page', 1);
        $limit = 20;
        $search = trim((string)$request->query('search', ''));
        $typeParam = $request->query('entity_type_id', '');
        $typeFilter = $typeParam !== '' ? (int)$typeParam : null;
        $campusParam = $request->query('campus_id', '');
        $campusFilter = $campusParam !== '' ? (int)$campusParam : null;
        $statusFilter = trim((string)$request->query('status', ''));

        $data = $this->entityService->getEntitiesPaginated(
            page: $page,
            limit: $limit,
            search: $search ?: null,
            typeFilter: $typeFilter,
            campusFilter: $campusFilter,
            statusFilter: $statusFilter ?: null
        );

        $lookups = $this->entityService->getLookups();
        $tree = $this->entityService->getEntityTree();
        $baseAppUrl = $this->resolveAppUrl($request);

        $html = View::render('admin/entities/index', [
            'title' => 'PROMIS - Departments and Units',
            'entities' => $data['entities'],
            'totalRecords' => $data['total_records'],
            'totalPages' => $data['total_pages'],
            'page' => $data['current_page'],
            'search' => $search,
            'typeFilter' => $typeFilter,
            'campusFilter' => $campusFilter,
            'statusFilter' => $statusFilter,
            'metrics' => $data['metrics'],
            'entityTypes' => $lookups['entity_types'],
            'campuses' => $lookups['campuses'],
            'allEntities' => $lookups['entities'],
            'tree' => $tree,
            'appUrl' => $baseAppUrl,
            'activeNav' => 'admin_entities',
            'pageTitle' => 'Departments and Units',
            'breadcrumbs' => [
                ['label' => 'Administration', 'url' => ''],
                ['label' => 'Departments and Units', 'url' => ''],
            ],
            'user' => AuthManager::user(),
        ], 'app');

        return Response::html($html);
    }

    /**
     * Handle entity creation form submission.
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
            $dto = CreateEntityDTO::fromArray([
                'entity_code' => $request->post('entity_code'),
                'entity_name' => $request->post('entity_name'),
                'entity_type_id' => $request->post('entity_type_id'),
                'campus_id' => $request->post('campus_id'),
                'parent_entity_id' => $request->post('parent_entity_id'),
                'head_user_id' => $request->post('head_user_id'),
                'planning_officer_id' => $request->post('planning_officer_id'),
            ]);

            $entityId = $this->entityService->createEntity(
                dto: $dto,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', "Department or unit '{$dto->entityName}' ({$dto->entityCode}) created successfully.");

            if ($request->isJson()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Entity created successfully.',
                    'entity_id' => $entityId,
                ]);
            }

            return Response::redirect('/admin/entities');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            if ($request->isJson()) {
                return Response::json(['error' => true, 'message' => $e->getMessage()], 422);
            }
            return Response::redirect('/admin/entities');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to create entity: ' . $e->getMessage());
            if ($request->isJson()) {
                return Response::json(['error' => true, 'message' => $e->getMessage()], 500);
            }
            return Response::redirect('/admin/entities');
        }
    }

    /**
     * Handle entity update form submission.
     */
    public function update(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }
        $this->validateCsrf($request);

        $actor = AuthManager::user();
        $actorId = (int)($actor['id'] ?? 1);
        $entityId = (int)$request->param('id', 0);

        try {
            $dto = UpdateEntityDTO::fromArray($entityId, [
                'entity_name' => $request->post('entity_name'),
                'entity_type_id' => $request->post('entity_type_id'),
                'campus_id' => $request->post('campus_id'),
                'parent_entity_id' => $request->post('parent_entity_id'),
                'head_user_id' => $request->post('head_user_id'),
                'planning_officer_id' => $request->post('planning_officer_id'),
            ]);

            $this->entityService->updateEntity(
                dto: $dto,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', "Department or unit #{$entityId} updated successfully.");

            if ($request->isJson()) {
                return Response::json(['success' => true, 'message' => 'Department or unit updated successfully.']);
            }

            return Response::redirect('/admin/entities');
        } catch (ValidationException | NotFoundException $e) {
            Session::flash('error', $e->getMessage());
            if ($request->isJson()) {
                return Response::json(['error' => true, 'message' => $e->getMessage()], 422);
            }
            return Response::redirect('/admin/entities');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update department or unit: ' . $e->getMessage());
            if ($request->isJson()) {
                return Response::json(['error' => true, 'message' => $e->getMessage()], 500);
            }
            return Response::redirect('/admin/entities');
        }
    }

    /**
     * Handle entity activation / deactivation.
     */
    public function toggleStatus(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }
        $this->validateCsrf($request);

        $actor = AuthManager::user();
        $actorId = (int)($actor['id'] ?? 1);
        $entityId = (int)$request->param('id', 0);
        $isActive = (bool)$request->post('is_active', false);

        try {
            // Check for active children warning
            if (!$isActive) {
                $details = $this->entityService->getEntityDetails($entityId);
                $hasActiveChildren = false;
                foreach ($details['children'] as $child) {
                    if ((bool)$child['is_active']) {
                        $hasActiveChildren = true;
                        break;
                    }
                }
                // Allow deactivation but include warning info in response
                if ($hasActiveChildren && $request->isJson()) {
                    // The client-side should have already confirmed
                }
            }

            $this->entityService->toggleEntityStatus(
                id: $entityId,
                isActive: $isActive,
                actorUserId: $actorId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $statusLabel = $isActive ? 'activated' : 'deactivated';
            Session::flash('success', "Department or unit {$statusLabel} successfully.");

            if ($request->isJson()) {
                return Response::json(['success' => true, 'is_active' => $isActive]);
            }

            return Response::redirect('/admin/entities');
        } catch (ValidationException | NotFoundException $e) {
            Session::flash('error', $e->getMessage());
            if ($request->isJson()) {
                return Response::json(['error' => true, 'message' => $e->getMessage()], 422);
            }
            return Response::redirect('/admin/entities');
        } catch (Throwable $e) {
            Session::flash('error', 'Status update failed: ' . $e->getMessage());
            if ($request->isJson()) {
                return Response::json(['error' => true, 'message' => $e->getMessage()], 500);
            }
            return Response::redirect('/admin/entities');
        }
    }

    /**
     * API endpoint returning entity details for modal population.
     */
    public function getEntityJson(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }

        $entityId = (int)$request->param('id', 0);
        try {
            $details = $this->entityService->getEntityDetails($entityId);
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

    /**
     * API endpoint returning the full entity hierarchy tree as JSON.
     */
    public function getEntityTreeJson(Request $request): Response
    {
        if ($auth = $this->requireAdmin($request)) {
            return $auth;
        }

        try {
            $tree = $this->entityService->getEntityTree();
            return Response::json([
                'success' => true,
                'tree' => $tree,
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
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
            Response::redirect('/admin/entities')->send();
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
