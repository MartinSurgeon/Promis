<?php

declare(strict_types=1);

namespace Promis\Src\Presentation\Controller;

use PDO;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Auth\Authorization;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AuthenticationException;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\NotFoundException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Session;
use Promis\Core\Support\View;
use Promis\Src\Execution\Auth\ExecutionAuthorizationGuard;
use Promis\Src\Execution\Auth\ExecutionPermissions;
use Promis\Src\Execution\Domain\DTO\CreateRequisitionRequest;
use Promis\Src\Execution\Domain\DTO\WorkflowActionRequest;
use Promis\Src\Execution\Domain\DrawdownCalculator;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Exception\DrawdownExceededException;
use Promis\Src\Execution\Exception\UnauthorizedExecutionException;
use Promis\Src\Execution\Exception\WorkflowException;
use Promis\Src\Execution\Service\RequisitionService;
use Promis\Src\Execution\Service\RequisitionServiceInterface;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Execution\Service\RequisitionWorkflowServiceInterface;
use Promis\Src\Planning\Domain\Decimal;
use Throwable;

/**
 * Requisition Presentation Controller.
 * Handles requisition listings, itemized details, budget status verification,
 * and multi-stage workflow actions via the Stage 2.3 Workflow Engine.
 */
final class RequisitionViewController
{
    private PDO $db;
    private RequisitionServiceInterface $requisitionService;
    private RequisitionWorkflowServiceInterface $workflowService;

    public function __construct(
        ?PDO $db = null,
        ?RequisitionServiceInterface $requisitionService = null,
        ?RequisitionWorkflowServiceInterface $workflowService = null
    ) {
        $this->db = $db ?? Connection::get();
        $this->requisitionService = $requisitionService ?? new RequisitionService($this->db);
        $this->workflowService = $workflowService ?? new RequisitionWorkflowService($this->db);
    }

    /**
     * List requisitions with search, status filtering, and pagination.
     */
    public function index(Request $request): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $roles = $user['roles'] ?? [];

        $search = trim((string)$request->query('search', ''));
        $status = strtoupper(trim((string)$request->query('status', '')));
        $filter = trim((string)$request->query('filter', ''));
        $page = max(1, (int)$request->query('page', 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        // Base Query
        $where = ['1=1'];
        $params = [];

        // Role-based visibility
        if (in_array('REQUESTER', $roles, true) && !in_array('ADMIN', $roles, true) && !in_array('DEAN', $roles, true) && !in_array('FINANCE_OFFICER', $roles, true) && !in_array('PROCUREMENT_OFFICER', $roles, true)) {
            $where[] = 'r.created_by = :user_id';
            $params[':user_id'] = $userId;
        }

        if ($search !== '') {
            $where[] = '(r.requisition_number LIKE :search OR r.justification LIKE :search_j OR pe.entity_name LIKE :search_pe)';
            $params[':search'] = "%{$search}%";
            $params[':search_j'] = "%{$search}%";
            $params[':search_pe'] = "%{$search}%";
        }

        if ($status !== '' && $status !== 'ALL') {
            $where[] = 'r.status = :status';
            $params[':status'] = $status;
        }

        if ($filter === 'pending') {
            if (in_array('HOD', $roles, true)) {
                $where[] = "r.status = 'SUBMITTED'";
            } elseif (in_array('DEAN', $roles, true)) {
                $where[] = "r.status = 'ENDORSED'";
            } elseif (in_array('FINANCE_OFFICER', $roles, true)) {
                $where[] = "r.status = 'DEPARTMENT_APPROVED'";
            } elseif (in_array('PROCUREMENT_OFFICER', $roles, true)) {
                $where[] = "r.status = 'COMMITMENT_AUTHORIZED'";
            } else {
                $where[] = "r.status IN ('SUBMITTED', 'ENDORSED', 'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED')";
            }
        }

        $whereSql = implode(' AND ', $where);

        // Count Total
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM requisitions r
            JOIN planning_entities pe ON pe.id = r.planning_entity_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRecords / $limit));

        // Total Estimated Cost of Matching Records
        $sumStmt = $this->db->prepare("
            SELECT COALESCE(SUM(r.total_estimated_cost), 0)
            FROM requisitions r
            JOIN planning_entities pe ON pe.id = r.planning_entity_id
            WHERE {$whereSql}
        ");
        $sumStmt->execute($params);
        $totalQueueCost = (float)$sumStmt->fetchColumn();

        // Role-Specific Pending Approvals Count
        $pendingWhere = "1=1";
        if (in_array('HOD', $roles, true)) {
            $pendingWhere = "r.status = 'SUBMITTED'";
        } elseif (in_array('DEAN', $roles, true)) {
            $pendingWhere = "r.status = 'ENDORSED'";
        } elseif (in_array('FINANCE_OFFICER', $roles, true)) {
            $pendingWhere = "r.status = 'DEPARTMENT_APPROVED'";
        } elseif (in_array('PROCUREMENT_OFFICER', $roles, true)) {
            $pendingWhere = "r.status = 'COMMITMENT_AUTHORIZED'";
        } else {
            $pendingWhere = "r.status IN ('SUBMITTED', 'ENDORSED', 'DEPARTMENT_APPROVED', 'COMMITMENT_AUTHORIZED')";
        }

        $pendingCountStmt = $this->db->query("
            SELECT COUNT(*) 
            FROM requisitions r 
            WHERE {$pendingWhere}
        ");
        $pendingCount = (int)$pendingCountStmt->fetchColumn();

        // Fetch Page Records
        $queryStmt = $this->db->prepare("
            SELECT r.*, pe.entity_name as entity_name, CONCAT(u.first_name, ' ', u.last_name) as requester_name
            FROM requisitions r
            JOIN planning_entities pe ON pe.id = r.planning_entity_id
            JOIN users u ON u.id = r.created_by
            WHERE {$whereSql}
            ORDER BY r.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $queryStmt->execute($params);
        $requisitions = $queryStmt->fetchAll(PDO::FETCH_ASSOC);

        $baseAppUrl = $this->resolveAppUrl($request);
        $breadcrumbs = [
            ['label' => 'Requisitions', 'url' => $filter === 'pending' ? "{$baseAppUrl}/requisitions" : ''],
        ];
        if ($filter === 'pending') {
            $breadcrumbs[] = ['label' => 'Approval Queues', 'url' => ''];
        }

        $html = View::render('requisitions/index', [
            'title' => $filter === 'pending' ? 'PROMIS - Pending Approval Queues' : 'PROMIS - Requisitions',
            'requisitions' => $requisitions,
            'totalRecords' => $totalRecords,
            'totalQueueCost' => $totalQueueCost,
            'pendingCount' => $pendingCount,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'status' => $status,
            'filter' => $filter,
            'appUrl' => $baseAppUrl,
            'activeNav' => $filter === 'pending' ? 'approvals' : 'requisitions',
            'pageTitle' => $filter === 'pending' ? 'Approval Queues' : 'Departmental Requisitions',
            'breadcrumbs' => $breadcrumbs,
        ], 'app');

        return Response::html($html);
    }

    /**
     * Display the Requisition Creation Form.
     */
    public function create(Request $request): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $roles = $user['roles'] ?? [];
        $isAdmin = in_array('ADMIN', $roles, true) || in_array('SUPER_ADMIN', $roles, true);

        // Resolve permitted planning entities
        if ($isAdmin) {
            $peStmt = $this->db->query("
                SELECT pe.id, pe.entity_code, pe.entity_name, et.type_name as entity_type, c.campus_name
                FROM planning_entities pe
                LEFT JOIN entity_types et ON et.id = pe.entity_type_id
                LEFT JOIN campuses c ON c.id = pe.campus_id
                WHERE pe.is_active = 1
                ORDER BY pe.entity_name ASC
            ");
            $permittedEntities = $peStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $peStmt = $this->db->prepare("
                SELECT DISTINCT pe.id, pe.entity_code, pe.entity_name, et.type_name as entity_type, c.campus_name
                FROM planning_entities pe
                JOIN user_entity_roles uer ON uer.planning_entity_id = pe.id
                LEFT JOIN entity_types et ON et.id = pe.entity_type_id
                LEFT JOIN campuses c ON c.id = pe.campus_id
                WHERE uer.user_id = :uid AND uer.status = 'ACTIVE' AND pe.is_active = 1
                ORDER BY pe.entity_name ASC
            ");
            $peStmt->execute([':uid' => $userId]);
            $permittedEntities = $peStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Filter entities by ExecutionAuthorizationGuard
        $authorizedEntities = [];
        foreach ($permittedEntities as $pe) {
            $peId = (int)$pe['id'];
            if (ExecutionAuthorizationGuard::canCreateRequisition($peId)) {
                $authorizedEntities[] = $pe;
            }
        }

        if (empty($authorizedEntities)) {
            Session::flash('error', 'You do not have authorization to create requisitions for any department or planning entity.');
            return Response::redirect('/requisitions');
        }

        // Selected entity resolution
        $requestedEntityId = (int)$request->query('entity_id', 0);
        $selectedEntity = null;
        if ($requestedEntityId > 0) {
            foreach ($authorizedEntities as $ae) {
                if ((int)$ae['id'] === $requestedEntityId) {
                    $selectedEntity = $ae;
                    break;
                }
            }
        }
        if ($selectedEntity === null) {
            $selectedEntity = $authorizedEntities[0];
        }

        $entityId = (int)$selectedEntity['id'];
        $fiscalYear = (int)$request->query('fiscal_year', (int)date('Y'));

        // Load Approved Procurement Plan & Approved Plan Version for this entity and fiscal year
        $planStmt = $this->db->prepare("
            SELECT pp.id as plan_id, pp.plan_number, pp.planning_entity_id, pp.fiscal_year, pp.status as plan_status, pp.current_version_id,
                   ppv.id as version_id, ppv.version_number, ppv.status as version_status, ppv.total_estimated_cost as version_total_cost,
                   ppv.approval_date, u.first_name, u.last_name
            FROM procurement_plans pp
            JOIN procurement_plan_versions ppv ON ppv.id = pp.current_version_id AND ppv.procurement_plan_id = pp.id
            LEFT JOIN users u ON u.id = ppv.approved_by_user_id
            WHERE pp.planning_entity_id = :pe_id 
              AND pp.fiscal_year = :year 
              AND pp.status = 'APPROVED'
              AND ppv.status = 'APPROVED'
            LIMIT 1
        ");
        $planStmt->execute([':pe_id' => $entityId, ':year' => $fiscalYear]);
        $planData = $planStmt->fetch(PDO::FETCH_ASSOC);

        // Fallback: If current_version_id was not explicitly set on the plan header but an approved version exists
        if (!$planData) {
            $fallbackStmt = $this->db->prepare("
                SELECT pp.id as plan_id, pp.plan_number, pp.planning_entity_id, pp.fiscal_year, pp.status as plan_status, pp.current_version_id,
                       ppv.id as version_id, ppv.version_number, ppv.status as version_status, ppv.total_estimated_cost as version_total_cost,
                       ppv.approval_date, u.first_name, u.last_name
                FROM procurement_plans pp
                JOIN procurement_plan_versions ppv ON ppv.procurement_plan_id = pp.id AND ppv.status = 'APPROVED'
                LEFT JOIN users u ON u.id = ppv.approved_by_user_id
                WHERE pp.planning_entity_id = :pe_id 
                  AND pp.fiscal_year = :year 
                  AND pp.status = 'APPROVED'
                ORDER BY ppv.id DESC
                LIMIT 1
            ");
            $fallbackStmt->execute([':pe_id' => $entityId, ':year' => $fiscalYear]);
            $planData = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        }

        // Load Plan Items & Compute Remaining Balances
        $planItems = [];
        if ($planData) {
            $versionId = (int)$planData['version_id'];
            $itemsStmt = $this->db->prepare("
                SELECT ppi.id as plan_item_id, ppi.plan_version_id, ppi.standard_item_id, ppi.item_description,
                       ppi.planned_quantity, ppi.estimated_unit_cost, ppi.estimated_total_cost,
                       ppi.target_quarter, ppi.funding_source,
                       si.item_code, si.item_name,
                       ic.category_name, uom.uom_name, uom.uom_code
                FROM procurement_plan_items ppi
                LEFT JOIN standard_items si ON si.id = ppi.standard_item_id
                LEFT JOIN item_categories ic ON ic.id = ppi.category_id
                LEFT JOIN units_of_measure uom ON uom.id = ppi.uom_id
                WHERE ppi.plan_version_id = :version_id
                ORDER BY ppi.id ASC
            ");
            $itemsStmt->execute([':version_id' => $versionId]);
            $rawItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawItems as $ri) {
                $planItemId = (int)$ri['plan_item_id'];
                $usedStmt = $this->db->prepare("
                    SELECT COALESCE(SUM(ri.requested_quantity), 0.00) as used_qty
                    FROM requisition_items ri
                    JOIN requisitions r ON r.id = ri.requisition_id
                    WHERE ri.procurement_plan_item_id = :pi_id 
                      AND r.status != 'REJECTED'
                ");
                $usedStmt->execute([':pi_id' => $planItemId]);
                $usedQty = (string)$usedStmt->fetchColumn();

                $plannedQty = (string)$ri['planned_quantity'];
                $remainingQty = Decimal::sub($plannedQty, $usedQty, 2);
                if (Decimal::lt($remainingQty, '0.00', 2)) {
                    $remainingQty = '0.00';
                }

                $ri['used_quantity'] = $usedQty;
                $ri['remaining_quantity'] = $remainingQty;
                $ri['is_available'] = Decimal::gt($remainingQty, '0.00', 2);

                $planItems[] = $ri;
            }
        }

        $budgetInfo = $this->resolveBudgetInfo($entityId, $fiscalYear, '0.00');

        $baseAppUrl = $this->resolveAppUrl($request);
        $breadcrumbs = [
            ['label' => 'Requisitions', 'url' => "{$baseAppUrl}/requisitions"],
            ['label' => 'New Requisition', 'url' => ''],
        ];

        $html = View::render('requisitions/create', [
            'title' => 'Make a Requisition - USTED PROMIS',
            'authorizedEntities' => $authorizedEntities,
            'selectedEntity' => $selectedEntity,
            'fiscalYear' => $fiscalYear,
            'planData' => $planData,
            'planItems' => $planItems,
            'budgetInfo' => $budgetInfo,
            'breadcrumbs' => $breadcrumbs,
            'appUrl' => $baseAppUrl,
            'activeNav' => 'requisitions',
            'pageTitle' => 'Make a Requisition',
            'user' => $user,
        ], 'app');

        return Response::html($html);
    }

    /**
     * Store a newly created Requisition in database.
     */
    public function store(Request $request): Response
    {
        if (AuthManager::guest()) {
            if ($request->isJson()) {
                throw new AuthenticationException('Unauthenticated.');
            }
            return Response::redirect('/login');
        }

        $this->validateCsrf($request);

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);

        $entityId = (int)$request->post('planning_entity_id', 0);
        $fiscalYear = (int)$request->post('fiscal_year', (int)date('Y'));
        $justification = trim((string)$request->post('justification', ''));
        $submitNow = (bool)$request->post('submit_now', false);
        $itemsInput = $request->post('items', []);

        // Store old inputs for re-populating if validation fails
        Session::flash('old_planning_entity_id', (string)$entityId);
        Session::flash('old_fiscal_year', (string)$fiscalYear);
        Session::flash('old_justification', $justification);

        try {
            if ($entityId <= 0) {
                throw new ValidationException('Please select a valid department or planning entity.');
            }

            // 1. Enforce Server-Side Authorization Check
            ExecutionAuthorizationGuard::requireCanCreateRequisition($entityId);

            // 2. Validate Justification
            if ($justification === '') {
                throw new ValidationException('Please provide a reason or justification explaining why these items are required.');
            }

            // 3. Resolve Approved Procurement Plan & Approved Version
            $planStmt = $this->db->prepare("
                SELECT pp.id as plan_id, pp.plan_number, pp.status as plan_status, pp.current_version_id,
                       ppv.id as version_id, ppv.version_number, ppv.status as version_status
                FROM procurement_plans pp
                JOIN procurement_plan_versions ppv ON ppv.procurement_plan_id = pp.id AND ppv.status = 'APPROVED'
                WHERE pp.planning_entity_id = :pe_id 
                  AND pp.fiscal_year = :year 
                  AND pp.status = 'APPROVED'
                ORDER BY ppv.id DESC
                LIMIT 1
            ");
            $planStmt->execute([':pe_id' => $entityId, ':year' => $fiscalYear]);
            $planRow = $planStmt->fetch(PDO::FETCH_ASSOC);

            if (!$planRow) {
                throw new ValidationException("Selected department does not have an active approved procurement plan for fiscal year {$fiscalYear}.");
            }

            $approvedPlanVersionId = (int)$planRow['version_id'];

            // If current_version_id was not set on the plan, ensure it matches approvedPlanVersionId
            if (empty($planRow['current_version_id'])) {
                $this->db->prepare("UPDATE procurement_plans SET current_version_id = :vid WHERE id = :pid")->execute([
                    ':vid' => $approvedPlanVersionId,
                    ':pid' => (int)$planRow['plan_id']
                ]);
            }

            // 4. Validate and prepare line items from authoritative database catalog
            if (!is_array($itemsInput) || empty($itemsInput)) {
                throw new ValidationException('Please select at least one item from the approved plan to create a requisition.');
            }

            $preparedItems = [];
            foreach ($itemsInput as $rawItem) {
                $planItemId = (int)($rawItem['procurement_plan_item_id'] ?? 0);
                $qtyRaw = trim((string)($rawItem['requested_quantity'] ?? '0'));
                $itemJust = trim((string)($rawItem['item_justification'] ?? ''));

                if ($planItemId <= 0 || !Decimal::isValid($qtyRaw) || Decimal::lte($qtyRaw, '0.00', 2)) {
                    continue; // skip unselected or 0-quantity rows
                }

                $qty = Decimal::normalize($qtyRaw, 2);

                // Fetch authoritative plan item from DB
                $piStmt = $this->db->prepare("
                    SELECT id, plan_version_id, standard_item_id, item_description, uom_id,
                           planned_quantity, estimated_unit_cost
                    FROM procurement_plan_items
                    WHERE id = :id AND plan_version_id = :vid
                    LIMIT 1
                ");
                $piStmt->execute([':id' => $planItemId, ':vid' => $approvedPlanVersionId]);
                $pi = $piStmt->fetch(PDO::FETCH_ASSOC);

                if (!$pi) {
                    throw new ValidationException("Selected item #{$planItemId} does not belong to the approved plan version.");
                }

                // Check remaining quota
                $usedStmt = $this->db->prepare("
                    SELECT COALESCE(SUM(ri.requested_quantity), 0.00) as used_qty
                    FROM requisition_items ri
                    JOIN requisitions r ON r.id = ri.requisition_id
                    WHERE ri.procurement_plan_item_id = :pi_id 
                      AND r.status != 'REJECTED'
                ");
                $usedStmt->execute([':pi_id' => $planItemId]);
                $usedQty = (string)$usedStmt->fetchColumn();

                $drawdown = DrawdownCalculator::calculate(
                    $pi['planned_quantity'],
                    $usedQty,
                    $qty,
                    $planItemId
                );

                if ($drawdown->isExceeded) {
                    $desc = htmlspecialchars($pi['item_description'], ENT_QUOTES, 'UTF-8');
                    throw new ValidationException("Requested quantity ({$qty}) for '{$desc}' exceeds the remaining available plan balance ({$drawdown->remainingBefore}).");
                }

                $preparedItems[] = [
                    'procurement_plan_item_id' => $planItemId,
                    'standard_item_id' => (int)$pi['standard_item_id'],
                    'item_description' => (string)$pi['item_description'],
                    'uom_id' => (int)$pi['uom_id'],
                    'requested_quantity' => $qty,
                    'estimated_unit_cost' => (string)$pi['estimated_unit_cost'],
                    'item_justification' => $itemJust !== '' ? $itemJust : null,
                ];
            }

            if (empty($preparedItems)) {
                throw new ValidationException('Please enter a quantity greater than zero for at least one item.');
            }

            // 5. Create Requisition via RequisitionService
            $createReq = new CreateRequisitionRequest(
                planningEntityId: $entityId,
                fiscalYear: $fiscalYear,
                approvedPlanVersionId: $approvedPlanVersionId,
                justification: $justification,
                items: $preparedItems,
                actingUserId: $userId
            );

            $dto = $this->requisitionService->createRequisition($createReq);

            // 6. Immediate Submit if requested
            if ($submitNow) {
                $this->workflowService->executeAction(new WorkflowActionRequest(
                    requisitionId: $dto->id,
                    action: WorkflowAction::SUBMIT,
                    actingUserId: $userId,
                    comments: 'Submitted upon creation.',
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                ));
                Session::flash('success', "Purchase request {$dto->requisitionNumber} created and sent for approval successfully!");
            } else {
                Session::flash('success', "Purchase request {$dto->requisitionNumber} created and saved as draft.");
            }

            if ($request->isJson()) {
                return Response::json([
                    'success' => true,
                    'message' => "Purchase request {$dto->requisitionNumber} created successfully.",
                    'requisition_id' => $dto->id,
                    'requisition_number' => $dto->requisitionNumber,
                ]);
            }

            return Response::redirect("/requisitions/{$dto->id}");

        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect("/requisitions/create?entity_id={$entityId}&fiscal_year={$fiscalYear}");
        } catch (DrawdownExceededException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect("/requisitions/create?entity_id={$entityId}&fiscal_year={$fiscalYear}");
        } catch (UnauthorizedExecutionException | AuthorizationException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/requisitions');
        } catch (Throwable $e) {
            Session::flash('error', "Failed to create purchase request: " . $e->getMessage());
            return Response::redirect("/requisitions/create?entity_id={$entityId}&fiscal_year={$fiscalYear}");
        }
    }

    /**
     * Display detailed requisition information, item breakdown,
     * budget availability, workflow progress, and permitted action panel.
     */
    public function show(Request $request): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $id = (int)$request->param('id', 0);
        if ($id <= 0) {
            throw new NotFoundException('Invalid requisition identifier.');
        }

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $roles = $user['roles'] ?? [];

        // Query Requisition
        $stmt = $this->db->prepare("
            SELECT r.*, pe.entity_name as entity_name, pe.entity_code as entity_code, 
                   parent_pe.entity_name as faculty_name, parent_pe.entity_code as faculty_code,
                   CONCAT(u.first_name, ' ', u.last_name) as requester_name, u.email as requester_email,
                   CONCAT(sb.first_name, ' ', sb.last_name) as submitter_name
            FROM requisitions r
            JOIN planning_entities pe ON pe.id = r.planning_entity_id
            LEFT JOIN planning_entities parent_pe ON parent_pe.id = pe.parent_entity_id
            JOIN users u ON u.id = r.created_by
            LEFT JOIN users sb ON sb.id = r.submitted_by
            WHERE r.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $requisition = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$requisition) {
            throw new NotFoundException("Requisition #{$id} was not found.");
        }

        // Direct URL authorization: Non-elevated requesters may only view requisitions they created.
        $isElevated = in_array('ADMIN', $roles, true) ||
                      in_array('HOD', $roles, true) ||
                      in_array('DEAN', $roles, true) ||
                      in_array('FINANCE_OFFICER', $roles, true) ||
                      in_array('PROCUREMENT_OFFICER', $roles, true);

        if (!$isElevated && (int)$requisition['created_by'] !== $userId) {
            throw new AuthorizationException('Access denied. You do not have permission to view this requisition.');
        }

        // Fetch Requisition Items with Standard Item info & UOM
        $itemsStmt = $this->db->prepare("
            SELECT ri.*, si.item_code, si.item_name as standard_name, uom.uom_name as uom_name, uom.uom_code as uom_code
            FROM requisition_items ri
            JOIN standard_items si ON si.id = ri.standard_item_id
            JOIN units_of_measure uom ON uom.id = ri.uom_id
            WHERE ri.requisition_id = :id
            ORDER BY ri.id ASC
        ");
        $itemsStmt->execute([':id' => $id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Resolve Permitted Workflow Transitions via Stage 2.3 Service
        $permittedTransitions = [];
        try {
            $permittedTransitions = $this->workflowService->resolvePermittedTransitions($id, $userId);
        } catch (Throwable $e) {
            // Unpermitted or unhandled edge
        }

        // Resolve Chronological Workflow History
        $workflowHistory = [];
        try {
            $workflowHistory = $this->workflowService->getWorkflowHistory($id, $userId);
        } catch (Throwable $e) {
            // Fallback
        }

        // Resolve Budget Availability Information
        $budgetInfo = $this->resolveBudgetInfo((int)$requisition['planning_entity_id'], (int)$requisition['fiscal_year'], (string)$requisition['total_estimated_cost']);

        $html = View::render('requisitions/show', [
            'title' => "PROMIS - Purchase Request {$requisition['requisition_number']}",
            'requisition' => $requisition,
            'items' => $items,
            'permittedTransitions' => $permittedTransitions,
            'workflowHistory' => $workflowHistory,
            'budgetInfo' => $budgetInfo,
            'appUrl' => $this->resolveAppUrl($request),
            'activeNav' => 'requisitions',
            'pageTitle' => "Purchase Request {$requisition['requisition_number']}",
            'breadcrumbs' => [
                ['label' => 'Purchase Requests', 'url' => $this->resolveAppUrl($request) . '/requisitions'],
                ['label' => $requisition['requisition_number'], 'url' => ''],
            ],
        ], 'app');

        return Response::html($html);
    }

    /**
     * Execute a workflow state transition (SUBMIT, ENDORSE, APPROVE, RETURN, REJECT, RECEIVE).
     */
    public function handleAction(Request $request): Response
    {
        if (AuthManager::guest()) {
            if ($request->isJson()) {
                throw new AuthenticationException('Unauthenticated.');
            }
            return Response::redirect('/login');
        }

        $id = (int)$request->param('id', 0);
        $this->validateCsrf($request);

        $actionRaw = trim((string)$request->post('action', ''));
        $comments = trim((string)$request->post('comments', ''));

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);

        try {
            $action = WorkflowAction::tryFromString($actionRaw);
            if ($action === null) {
                throw new ValidationException("Invalid workflow action '{$actionRaw}'.");
            }

            if ($action->requiresComment() && $comments === '') {
                throw new ValidationException("An official non-empty explanation is required when choosing {$action->value}.");
            }

            $req = new WorkflowActionRequest(
                requisitionId: $id,
                action: $action,
                actingUserId: $userId,
                comments: $comments !== '' ? $comments : null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $result = $this->workflowService->executeAction($req);

            if ($request->isJson()) {
                return Response::json([
                    'success' => true,
                    'message' => "Action '{$action->value}' executed successfully. Status transitioned to {$result->newStatus->value}.",
                    'status' => $result->newStatus->value,
                    'requisition_id' => $id,
                ]);
            }

            Session::flash('success', "Action '{$action->value}' executed successfully. Status transitioned to {$result->newStatus->value}.");
            return Response::redirect("/requisitions/{$id}");
        } catch (ValidationException|WorkflowException|AuthorizationException $e) {
            if ($request->isJson()) {
                throw $e;
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect("/requisitions/{$id}");
        } catch (Throwable $e) {
            if ($request->isJson()) {
                throw $e;
            }
            Session::flash('error', "Workflow action failed: {$e->getMessage()}");
            return Response::redirect("/requisitions/{$id}");
        }
    }

    /**
     * Resolve Budget Information for an entity and fiscal year.
     */
    private function resolveBudgetInfo(int $entityId, int $fiscalYear, string $requisitionAmount): array
    {
        $info = [
            'fiscal_year' => $fiscalYear,
            'allocated_amount' => '0.00',
            'used_amount' => '0.00',
            'committed_amount' => '0.00',
            'available_balance' => '0.00',
            'requisition_amount' => $requisitionAmount,
            'is_available' => true,
            'has_budget' => false,
            'funding_source' => 'Consolidated Fund / IGF',
        ];

        try {
            // 1. Get allocated budget
            $bStmt = $this->db->prepare("
                SELECT allocated_amount, funding_source, currency 
                FROM budget_allocations 
                WHERE planning_entity_id = :pe_id AND fiscal_year = :year AND is_active = 1
                LIMIT 1
            ");
            $bStmt->execute([':pe_id' => $entityId, ':year' => $fiscalYear]);
            $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);

            if ($bRow) {
                $info['has_budget'] = true;
                $info['allocated_amount'] = (string)$bRow['allocated_amount'];
                $info['funding_source'] = (string)$bRow['funding_source'];
            }

            // 2. Get committed amount
            $cStmt = $this->db->prepare("
                SELECT COALESCE(SUM(ca.authorized_amount), 0.00) as total_committed
                FROM commitment_authorizations ca
                JOIN budget_allocations ba ON ba.id = ca.budget_allocation_id
                WHERE ba.planning_entity_id = :pe_id AND ba.fiscal_year = :year AND ca.authorization_status = 'AUTHORIZED'
            ");
            $cStmt->execute([':pe_id' => $entityId, ':year' => $fiscalYear]);
            $info['committed_amount'] = (string)$cStmt->fetchColumn();

            // 3. Compute available balance: allocated - committed
            if ($info['has_budget']) {
                $info['available_balance'] = bcsub($info['allocated_amount'], $info['committed_amount'], 2);
                $info['is_available'] = bccomp($info['available_balance'], $requisitionAmount, 2) >= 0;
            }
        } catch (\Throwable $e) {
            // Non-breaking fallback
        }

        return $info;
    }

    private function validateCsrf(Request $request): void
    {
        $token = $request->post('_csrf_token') ?? $request->header('X-CSRF-TOKEN');
        if (!Csrf::validate($token)) {
            throw new AuthorizationException('Session expired or invalid security token. Please try again.');
        }
    }

    private function resolveAppUrl(Request $request): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = dirname($scriptName);
        if ($base === '/' || $base === '\\') {
            return '';
        }
        return rtrim($base, '/\\');
    }
}
