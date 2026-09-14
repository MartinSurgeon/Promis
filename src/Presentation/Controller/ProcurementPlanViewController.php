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
use Promis\Core\Support\Logger;
use Promis\Core\Support\View;
use Promis\Src\Planning\Auth\PlanAuthorizationGuard;
use Promis\Src\Planning\Auth\PlanningPermissions;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\DTO\PlanDTO;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Planning\Repository\PlanReviewCycleRepository;
use Promis\Src\Planning\Repository\PlanReviewCycleRepositoryInterface;
use Promis\Src\Planning\Repository\PlanRevisionRecordRepository;
use Promis\Src\Planning\Repository\PlanRevisionRecordRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepository;
use Promis\Src\Planning\Repository\ProcurementPlanItemRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanRepository;
use Promis\Src\Planning\Repository\ProcurementPlanRepositoryInterface;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepository;
use Promis\Src\Planning\Repository\ProcurementPlanVersionRepositoryInterface;
use Promis\Src\Planning\Service\PlanReviewCycleService;
use Promis\Src\Planning\Service\PlanReviewCycleServiceInterface;
use Promis\Src\Planning\Service\PlanVersionService;
use Promis\Src\Planning\Service\PlanVersionServiceInterface;
use Promis\Src\Planning\Service\ProcurementPlanService;
use Promis\Src\Planning\Service\ProcurementPlanServiceInterface;
use Promis\Src\Planning\Validation\PlanItemValidator;
use Promis\Src\Planning\Validation\PlanValidator;
use Throwable;

/**
 * Web Presentation Controller for Annual Procurement Plans.
 * Implements Plan Formulation, Item Categorization, Cost Calculation,
 * Approval Workflow, Printing (mirroring Resources.pdf), and Version History.
 */
final class ProcurementPlanViewController
{
    private PDO $db;
    private ProcurementPlanServiceInterface $planService;
    private PlanVersionServiceInterface $versionService;
    private PlanReviewCycleServiceInterface $reviewCycleService;
    private ProcurementPlanRepositoryInterface $planRepo;
    private ProcurementPlanVersionRepositoryInterface $versionRepo;
    private ProcurementPlanItemRepositoryInterface $itemRepo;
    private PlanRevisionRecordRepositoryInterface $revisionRepo;
    private PlanReviewCycleRepositoryInterface $reviewCycleRepo;

    public function __construct(
        ?PDO $db = null,
        ?ProcurementPlanServiceInterface $planService = null,
        ?PlanVersionServiceInterface $versionService = null,
        ?PlanReviewCycleServiceInterface $reviewCycleService = null,
        ?ProcurementPlanRepositoryInterface $planRepo = null,
        ?ProcurementPlanVersionRepositoryInterface $versionRepo = null,
        ?ProcurementPlanItemRepositoryInterface $itemRepo = null,
        ?PlanRevisionRecordRepositoryInterface $revisionRepo = null,
        ?PlanReviewCycleRepositoryInterface $reviewCycleRepo = null
    ) {
        $this->db = $db ?? Connection::get();
        $this->planRepo = $planRepo ?? new ProcurementPlanRepository($this->db);
        $this->versionRepo = $versionRepo ?? new ProcurementPlanVersionRepository($this->db);
        $this->itemRepo = $itemRepo ?? new ProcurementPlanItemRepository($this->db);
        $this->revisionRepo = $revisionRepo ?? new PlanRevisionRecordRepository($this->db);
        $this->reviewCycleRepo = $reviewCycleRepo ?? new PlanReviewCycleRepository($this->db);

        $this->planService = $planService ?? new ProcurementPlanService(
            $this->db,
            $this->planRepo,
            $this->versionRepo,
            $this->itemRepo
        );
        $this->versionService = $versionService ?? new PlanVersionService(
            $this->db,
            $this->planRepo,
            $this->versionRepo,
            $this->itemRepo,
            $this->revisionRepo,
            $this->reviewCycleRepo
        );
        $this->reviewCycleService = $reviewCycleService ?? new PlanReviewCycleService(
            $this->db,
            $this->reviewCycleRepo,
            $this->planRepo,
            $this->versionRepo
        );
    }

    /**
     * List procurement plans with status tabs, search, entity scoping, and pagination.
     */
    public function index(Request $request): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $roles = $user['roles'] ?? [];
        $isAdmin = in_array('ADMIN', $roles, true);
        $isProcurement = in_array('PROCUREMENT_OFFICER', $roles, true);

        // Extract user's authorized entity IDs
        $userEntityIds = $this->getUserAuthorizedEntityIds($userId);

        $search = trim((string)$request->query('search', ''));
        $status = strtoupper(trim((string)$request->query('status', '')));
        $fiscalYear = trim((string)$request->query('fiscal_year', ''));
        $entityFilter = (int)$request->query('entity_id', 0);
        $page = max(1, (int)$request->query('page', 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        // Base Query
        $where = ['1=1'];
        $params = [];

        // Entity scoping: non-global users can ONLY see their assigned entities
        if (!$isAdmin && !$isProcurement) {
            if (empty($userEntityIds)) {
                $where[] = '1=0'; // No authorized entities assigned
            } else {
                $placeholders = [];
                foreach ($userEntityIds as $idx => $eid) {
                    $key = ":ueid_{$idx}";
                    $placeholders[] = $key;
                    $params[$key] = $eid;
                }
                $where[] = 'pp.planning_entity_id IN (' . implode(',', $placeholders) . ')';
            }
        }

        if ($entityFilter > 0) {
            if (!$isAdmin && !$isProcurement && !in_array($entityFilter, $userEntityIds, true)) {
                throw new AuthorizationException("Access denied. You do not have permission to view plans for this entity.");
            }
            $where[] = 'pp.planning_entity_id = :entity_id';
            $params[':entity_id'] = $entityFilter;
        }

        if ($search !== '') {
            $where[] = '(pp.plan_number LIKE :search OR pe.entity_name LIKE :search_pe OR pe.entity_code LIKE :search_pc)';
            $params[':search'] = "%{$search}%";
            $params[':search_pe'] = "%{$search}%";
            $params[':search_pc'] = "%{$search}%";
        }

        if ($status !== '' && $status !== 'ALL') {
            $where[] = 'pp.status = :status';
            $params[':status'] = $status;
        }

        if ($fiscalYear !== '') {
            $where[] = 'pp.fiscal_year = :fiscal_year';
            $params[':fiscal_year'] = $fiscalYear;
        }

        $whereClause = implode(' AND ', $where);

        // Count total matching
        $countSql = "SELECT COUNT(*) 
                     FROM `procurement_plans` pp
                     JOIN `planning_entities` pe ON pe.id = pp.planning_entity_id
                     WHERE {$whereClause}";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRows = (int)$stmtCount->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $limit));

        // Fetch paginated rows with version details
        $sql = "SELECT pp.*, 
                       pe.entity_name, 
                       pe.entity_code,
                       pv.version_number,
                       pv.total_estimated_cost,
                       pv.approval_date,
                       u.first_name AS creator_first_name,
                       u.last_name AS creator_last_name,
                       (SELECT COUNT(*) FROM `procurement_plan_items` ppi WHERE ppi.plan_version_id = pp.current_version_id) AS item_count
                FROM `procurement_plans` pp
                JOIN `planning_entities` pe ON pe.id = pp.planning_entity_id
                LEFT JOIN `procurement_plan_versions` pv ON pv.id = pp.current_version_id
                LEFT JOIN `users` u ON u.id = pp.created_by
                WHERE {$whereClause}
                ORDER BY pp.fiscal_year DESC, pp.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Status counts for tabs
        $statusCounts = $this->getStatusCounts($userEntityIds, $isAdmin || $isProcurement);

        // List user's entities for dropdown filter
        $availableEntities = $this->getAvailableEntities($userEntityIds, $isAdmin || $isProcurement);

        return Response::html(View::render('procurement_plans/index', [
            'title' => 'Annual Procurement Plans - USTED PROMIS',
            'activeNav' => 'procurement_plans',
            'plans' => $plans,
            'totalRows' => $totalRows,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'status' => $status,
            'fiscalYear' => $fiscalYear,
            'entityFilter' => $entityFilter,
            'statusCounts' => $statusCounts,
            'availableEntities' => $availableEntities,
            'canCreate' => !empty($availableEntities),
        ], 'app'));
    }

    /**
     * Display the Procurement Plan formulation form.
     */
    public function create(Request $request): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $roles = $user['roles'] ?? [];
        $isAdmin = in_array('ADMIN', $roles, true);
        $isProcurement = in_array('PROCUREMENT_OFFICER', $roles, true);

        $userEntityIds = $this->getUserAuthorizedEntityIds($userId);
        $availableEntities = $this->getAvailableEntities($userEntityIds, $isAdmin || $isProcurement);

        if (empty($availableEntities)) {
            Session::flash('error', 'You are not authorized to formulate procurement plans for any entity.');
            return Response::redirect('/procurement-plans');
        }

        $categories = $this->getActiveCategories();
        $uoms = $this->getActiveUnitsOfMeasure();
        $standardItems = $this->getActiveStandardItems();

        $selectedEntityId = (int)$request->query('entity_id', $availableEntities[0]['id'] ?? 0);
        $defaultFiscalYear = (int)date('Y') + ((int)date('n') >= 9 ? 1 : 0); // Next fiscal year if Q4

        return Response::html(View::render('procurement_plans/create', [
            'title' => 'Formulate Annual Procurement Plan - USTED PROMIS',
            'activeNav' => 'procurement_plans',
            'availableEntities' => $availableEntities,
            'selectedEntityId' => $selectedEntityId,
            'defaultFiscalYear' => $defaultFiscalYear,
            'categories' => $categories,
            'uoms' => $uoms,
            'standardItems' => $standardItems,
        ], 'app'));
    }

    /**
     * Persist a new Procurement Plan (Draft or Immediate Submission).
     */
    public function store(Request $request): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $this->validateCsrf($request);

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);

        $planningEntityId = (int)$request->input('planning_entity_id', 0);
        $fiscalYear = (int)$request->input('fiscal_year', 0);
        $action = strtolower(trim((string)$request->input('action', 'draft'))); // 'draft' or 'submit'

        // Server-Side Authorization Guard
        PlanAuthorizationGuard::requireCanCreatePlan($planningEntityId);

        // Parse items from submission
        $rawItems = $request->input('items', []);
        $items = $this->parseAndSanitizeItems($rawItems);

        if (empty($items)) {
            Session::flash('error', 'Please add at least one line item to your procurement plan.');
            return Response::redirect('/procurement-plans/create?entity_id=' . $planningEntityId);
        }

        try {
            // Step 1: Create Draft Plan and Initial Version 1.0
            $planDto = $this->planService->createDraftPlan(
                planningEntityId: $planningEntityId,
                fiscalYear: $fiscalYear,
                items: $items,
                userId: $userId
            );

            // Step 2: If action is "submit", submit for review
            if ($action === 'submit') {
                $planDto = $this->planService->submitPlanForReview($planDto->id, $userId);

                $this->logWorkflowAction(
                    documentType: 'PROCUREMENT_PLAN',
                    documentId: $planDto->id,
                    stepId: 1,
                    actorUserId: $userId,
                    action: 'SUBMIT',
                    preStatus: 'DRAFT',
                    postStatus: 'SUBMITTED',
                    comments: 'Annual Procurement Plan formulated and submitted for approval.'
                );

                Session::flash('success', "Procurement Plan {$planDto->planNumber} has been formulated and submitted for institutional review.");
            } else {
                $this->logWorkflowAction(
                    documentType: 'PROCUREMENT_PLAN',
                    documentId: $planDto->id,
                    stepId: null,
                    actorUserId: $userId,
                    action: 'SAVE_DRAFT',
                    preStatus: 'DRAFT',
                    postStatus: 'DRAFT',
                    comments: 'Initial baseline draft saved.'
                );

                Session::flash('success', "Procurement Plan {$planDto->planNumber} draft saved successfully.");
            }

            return Response::redirect("/procurement-plans/{$planDto->id}");
        } catch (ValidationException $ve) {
            $errorMsg = $ve->getMessage();
            $errors = $ve->getErrors();
            if (!empty($errors['items'])) {
                $errorMsg .= ' ' . implode(' ', (array)$errors['items']);
            }
            Session::flash('error', $errorMsg);
            return Response::redirect('/procurement-plans/create?entity_id=' . $planningEntityId);
        } catch (Throwable $e) {
            Logger::error("Failed to store procurement plan: {$e->getMessage()}", ['exception' => $e]);
            Session::flash('error', 'An error occurred while saving the procurement plan: ' . $e->getMessage());
            return Response::redirect('/procurement-plans/create?entity_id=' . $planningEntityId);
        }
    }

    /**
     * Display complete details of a Procurement Plan.
     */
    public function show(Request $request, array $params = []): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $id = (int)($params['id'] ?? $request->routeParam('id', 0));
        if ($id <= 0) {
            throw new NotFoundException("Invalid Procurement Plan ID.");
        }

        $plan = $this->fetchPlanWithRelations($id);
        if ($plan === null) {
            throw new NotFoundException("Procurement Plan #{$id} not found.");
        }

        // Authorization check
        PlanAuthorizationGuard::requireCanViewPlan((int)$plan['planning_entity_id']);

        $versionId = (int)($plan['current_version_id'] ?? 0);
        $items = $versionId > 0 ? $this->itemRepo->findByVersionId($versionId) : [];

        // Group items by category
        $groupedItems = [];
        $categoryTotals = [];
        $grandTotal = '0.00';

        foreach ($items as $item) {
            $catName = $item->categoryName ?? 'General Items';
            if (!isset($groupedItems[$catName])) {
                $groupedItems[$catName] = [];
                $categoryTotals[$catName] = '0.00';
            }
            $groupedItems[$catName][] = $item;
            $categoryTotals[$catName] = Decimal::add($categoryTotals[$catName], $item->estimatedTotalCost, 2);
            $grandTotal = Decimal::add($grandTotal, $item->estimatedTotalCost, 2);
        }

        // Workflow action history
        $actionLogs = $this->getActionLogs('PROCUREMENT_PLAN', $id);

        // Determine user permissions and available actions
        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $entityId = (int)$plan['planning_entity_id'];
        $status = PlanStatus::tryFrom($plan['status']) ?? PlanStatus::DRAFT;

        $canEdit = $status->isEditable() && (PlanAuthorizationGuard::canEditPlan($entityId) || PlanAuthorizationGuard::canCreatePlan($entityId));
        $canSubmit = $status->isEditable() && PlanAuthorizationGuard::canSubmitPlan($entityId) && !empty($items);
        $canApprove = in_array($status, [PlanStatus::SUBMITTED, PlanStatus::UNDER_REVIEW], true) && PlanAuthorizationGuard::canApprovePlan($entityId);
        $canReturn = in_array($status, [PlanStatus::SUBMITTED, PlanStatus::UNDER_REVIEW], true) && (PlanAuthorizationGuard::canReviewPlan($entityId) || PlanAuthorizationGuard::canApprovePlan($entityId));
        $canReject = in_array($status, [PlanStatus::SUBMITTED, PlanStatus::UNDER_REVIEW], true) && PlanAuthorizationGuard::canApprovePlan($entityId);

        return Response::html(View::render('procurement_plans/show', [
            'title' => "Plan {$plan['plan_number']} - USTED PROMIS",
            'activeNav' => 'procurement_plans',
            'plan' => $plan,
            'items' => $items,
            'groupedItems' => $groupedItems,
            'categoryTotals' => $categoryTotals,
            'grandTotal' => $grandTotal,
            'actionLogs' => $actionLogs,
            'canEdit' => $canEdit,
            'canSubmit' => $canSubmit,
            'canApprove' => $canApprove,
            'canReturn' => $canReturn,
            'canReject' => $canReject,
        ], 'app'));
    }

    /**
     * Display the Edit form for a Draft or Returned Procurement Plan.
     */
    public function edit(Request $request, array $params = []): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $id = (int)($params['id'] ?? $request->routeParam('id', 0));
        $plan = $this->fetchPlanWithRelations($id);
        if ($plan === null) {
            throw new NotFoundException("Procurement Plan #{$id} not found.");
        }

        $entityId = (int)$plan['planning_entity_id'];
        PlanAuthorizationGuard::requireCanEditPlan($entityId);

        $status = PlanStatus::tryFrom($plan['status']) ?? PlanStatus::DRAFT;
        if (!$status->isEditable()) {
            Session::flash('warning', "Plan is currently in '{$status->value}' status and cannot be edited directly. To make changes to an approved plan, initiate a quarterly review or formal revision.");
            return Response::redirect("/procurement-plans/{$id}");
        }

        $versionId = (int)($plan['current_version_id'] ?? 0);
        $items = $versionId > 0 ? $this->itemRepo->findByVersionId($versionId) : [];

        $categories = $this->getActiveCategories();
        $uoms = $this->getActiveUnitsOfMeasure();
        $standardItems = $this->getActiveStandardItems();

        return Response::html(View::render('procurement_plans/edit', [
            'title' => "Edit Plan {$plan['plan_number']} - USTED PROMIS",
            'activeNav' => 'procurement_plans',
            'plan' => $plan,
            'items' => $items,
            'categories' => $categories,
            'uoms' => $uoms,
            'standardItems' => $standardItems,
        ], 'app'));
    }

    /**
     * Update items in an editable (Draft or Returned) Procurement Plan.
     */
    public function update(Request $request, array $params = []): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $this->validateCsrf($request);

        $id = (int)($params['id'] ?? $request->routeParam('id', 0));
        $plan = $this->fetchPlanWithRelations($id);
        if ($plan === null) {
            throw new NotFoundException("Procurement Plan #{$id} not found.");
        }

        $entityId = (int)$plan['planning_entity_id'];
        PlanAuthorizationGuard::requireCanEditPlan($entityId);

        $status = PlanStatus::tryFrom($plan['status']) ?? PlanStatus::DRAFT;
        if (!$status->isEditable()) {
            Session::flash('error', "Plan is in '{$status->value}' status and cannot be modified.");
            return Response::redirect("/procurement-plans/{$id}");
        }

        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $action = strtolower(trim((string)$request->input('action', 'save')));

        $rawItems = $request->input('items', []);
        $items = $this->parseAndSanitizeItems($rawItems);

        if (empty($items)) {
            Session::flash('error', 'Plan must contain at least one line item.');
            return Response::redirect("/procurement-plans/{$id}/edit");
        }

        $versionId = (int)($plan['current_version_id'] ?? 0);
        if ($versionId <= 0) {
            throw new ValidationException('No active version container attached to this plan.');
        }

        try {
            // Validate all items
            $errors = [];
            $preparedItems = [];
            foreach ($items as $idx => $item) {
                $lineErrors = PlanItemValidator::validate($item, $idx + 1);
                if (!empty($lineErrors)) {
                    $errors = array_merge($errors, $lineErrors);
                }
                $qtyStr = Decimal::normalize($item['planned_quantity'] ?? '0.00', 2);
                $costStr = Decimal::normalize($item['estimated_unit_cost'] ?? '0.00', 2);
                $lineTotal = Decimal::mul($qtyStr, $costStr, 2);
                $item['planned_quantity'] = $qtyStr;
                $item['estimated_unit_cost'] = $costStr;
                $item['estimated_total_cost'] = $lineTotal;
                $preparedItems[] = $item;
            }

            if (!empty($errors)) {
                throw new ValidationException('Line item validation failed.', ['items' => $errors]);
            }

            // Transactional item replacement and total recalculation
            $this->db->beginTransaction();

            $this->itemRepo->deleteByVersionId($versionId);
            $this->itemRepo->createBatch($versionId, $preparedItems, $userId);
            $this->planService->calculateAndPersistVersionTotal($versionId);

            if ($action === 'submit') {
                $this->planService->submitPlanForReview($id, $userId);

                $this->logWorkflowAction(
                    documentType: 'PROCUREMENT_PLAN',
                    documentId: $id,
                    stepId: 1,
                    actorUserId: $userId,
                    action: 'SUBMIT',
                    preStatus: $status->value,
                    postStatus: 'SUBMITTED',
                    comments: 'Plan revisions completed and resubmitted for approval.'
                );

                $this->db->commit();
                Session::flash('success', "Procurement Plan {$plan['plan_number']} updated and submitted for approval.");
            } else {
                $this->logWorkflowAction(
                    documentType: 'PROCUREMENT_PLAN',
                    documentId: $id,
                    stepId: null,
                    actorUserId: $userId,
                    action: 'UPDATE_DRAFT',
                    preStatus: $status->value,
                    postStatus: $status->value,
                    comments: 'Draft items updated.'
                );

                $this->db->commit();
                Session::flash('success', "Procurement Plan {$plan['plan_number']} changes saved.");
            }

            return Response::redirect("/procurement-plans/{$id}");
        } catch (ValidationException $ve) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $errorMsg = $ve->getMessage();
            $errs = $ve->getErrors();
            if (!empty($errs['items'])) {
                $errorMsg .= ' ' . implode(' ', (array)$errs['items']);
            }
            Session::flash('error', $errorMsg);
            return Response::redirect("/procurement-plans/{$id}/edit");
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error("Failed to update procurement plan #{$id}: {$e->getMessage()}", ['exception' => $e]);
            Session::flash('error', 'Error updating plan: ' . $e->getMessage());
            return Response::redirect("/procurement-plans/{$id}/edit");
        }
    }

    /**
     * Process a workflow transition action (Submit, Review, Approve, Return, Reject).
     */
    public function handleAction(Request $request, array $params = []): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $this->validateCsrf($request);

        $id = (int)($params['id'] ?? $request->routeParam('id', 0));
        $plan = $this->fetchPlanWithRelations($id);
        if ($plan === null) {
            throw new NotFoundException("Procurement Plan #{$id} not found.");
        }

        $entityId = (int)$plan['planning_entity_id'];
        $user = AuthManager::user();
        $userId = (int)($user['id'] ?? 0);
        $action = strtoupper(trim((string)$request->input('action', '')));
        $comments = trim((string)$request->input('comments', ''));
        $currentStatus = $plan['status'];
        $versionId = (int)($plan['current_version_id'] ?? 0);

        try {
            switch ($action) {
                case 'SUBMIT':
                    PlanAuthorizationGuard::requireCanSubmitPlan($entityId);
                    $this->planService->submitPlanForReview($id, $userId);

                    $this->logWorkflowAction(
                        documentType: 'PROCUREMENT_PLAN',
                        documentId: $id,
                        stepId: 1,
                        actorUserId: $userId,
                        action: 'SUBMIT',
                        preStatus: $currentStatus,
                        postStatus: 'SUBMITTED',
                        comments: $comments ?: 'Plan submitted for review.'
                    );

                    Session::flash('success', "Procurement Plan {$plan['plan_number']} submitted for approval.");
                    break;

                case 'UNDER_REVIEW':
                    PlanAuthorizationGuard::requireCanReviewPlan($entityId);
                    $this->planRepo->updateStatus($id, PlanStatus::UNDER_REVIEW->value, $userId);
                    if ($versionId > 0) {
                        $this->versionRepo->updateStatus($versionId, PlanVersionStatus::SUBMITTED->value);
                    }

                    $this->logWorkflowAction(
                        documentType: 'PROCUREMENT_PLAN',
                        documentId: $id,
                        stepId: 2,
                        actorUserId: $userId,
                        action: 'UNDER_REVIEW',
                        preStatus: $currentStatus,
                        postStatus: 'UNDER_REVIEW',
                        comments: $comments ?: 'Plan taken up for administrative review.'
                    );

                    Session::flash('info', "Plan {$plan['plan_number']} is now under review.");
                    break;

                case 'APPROVE':
                    PlanAuthorizationGuard::requireCanApprovePlan($entityId);

                    $this->db->beginTransaction();

                    // Approve plan and version
                    $this->planRepo->updateStatus($id, PlanStatus::APPROVED->value, $userId);
                    if ($versionId > 0) {
                        $this->versionRepo->updateStatus(
                            $versionId,
                            PlanVersionStatus::APPROVED->value,
                            $userId,
                            date('Y-m-d H:i:s')
                        );
                    }

                    $this->logWorkflowAction(
                        documentType: 'PROCUREMENT_PLAN',
                        documentId: $id,
                        stepId: 3,
                        actorUserId: $userId,
                        action: 'APPROVE',
                        preStatus: $currentStatus,
                        postStatus: 'APPROVED',
                        comments: $comments ?: 'Annual Procurement Plan formally approved. Eligible for requisition drawdowns.'
                    );

                    $this->db->commit();
                    Session::flash('success', "Procurement Plan {$plan['plan_number']} has been APPROVED. Items are now available for requisition drawdowns.");
                    break;

                case 'RETURN':
                    if (!PlanAuthorizationGuard::canReviewPlan($entityId) && !PlanAuthorizationGuard::canApprovePlan($entityId)) {
                        PlanAuthorizationGuard::requireCanReviewPlan($entityId);
                    }

                    if ($comments === '') {
                        throw new ValidationException('Please provide specific feedback and reasons when returning a plan for correction.', [
                            'comments' => ['Feedback remarks are mandatory for returned plans.']
                        ]);
                    }

                    $this->db->beginTransaction();

                    $this->planRepo->updateStatus($id, PlanStatus::RETURNED->value, $userId);
                    if ($versionId > 0) {
                        $this->versionRepo->updateStatus($versionId, PlanVersionStatus::DRAFT->value);
                    }

                    $this->logWorkflowAction(
                        documentType: 'PROCUREMENT_PLAN',
                        documentId: $id,
                        stepId: 2,
                        actorUserId: $userId,
                        action: 'RETURN',
                        preStatus: $currentStatus,
                        postStatus: 'RETURNED',
                        comments: $comments
                    );

                    $this->db->commit();
                    Session::flash('warning', "Plan {$plan['plan_number']} returned to department for correction.");
                    break;

                case 'REJECT':
                    PlanAuthorizationGuard::requireCanApprovePlan($entityId);

                    if ($comments === '') {
                        throw new ValidationException('Please provide a justification for rejecting the procurement plan.', [
                            'comments' => ['Justification remarks are mandatory for plan rejections.']
                        ]);
                    }

                    $this->db->beginTransaction();

                    $this->planRepo->updateStatus($id, PlanStatus::REJECTED->value, $userId);
                    if ($versionId > 0) {
                        $this->versionRepo->updateStatus($versionId, PlanVersionStatus::REJECTED->value);
                    }

                    $this->logWorkflowAction(
                        documentType: 'PROCUREMENT_PLAN',
                        documentId: $id,
                        stepId: 3,
                        actorUserId: $userId,
                        action: 'REJECT',
                        preStatus: $currentStatus,
                        postStatus: 'REJECTED',
                        comments: $comments
                    );

                    $this->db->commit();
                    Session::flash('error', "Procurement Plan {$plan['plan_number']} has been REJECTED.");
                    break;

                default:
                    throw new ValidationException("Unsupported workflow action: {$action}");
            }

            return Response::redirect("/procurement-plans/{$id}");
        } catch (ValidationException $ve) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $ve->getMessage());
            return Response::redirect("/procurement-plans/{$id}");
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error("Workflow action error on plan #{$id}: {$e->getMessage()}", ['exception' => $e]);
            Session::flash('error', 'Workflow action failed: ' . $e->getMessage());
            return Response::redirect("/procurement-plans/{$id}");
        }
    }

    /**
     * Render the clean, formal printable view matching Resources.pdf layout.
     */
    public function print(Request $request, array $params = []): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $id = (int)($params['id'] ?? $request->routeParam('id', 0));
        $plan = $this->fetchPlanWithRelations($id);
        if ($plan === null) {
            throw new NotFoundException("Procurement Plan #{$id} not found.");
        }

        // Authorization check
        PlanAuthorizationGuard::requireCanViewPlan((int)$plan['planning_entity_id']);

        $versionId = (int)($plan['current_version_id'] ?? 0);
        $items = $versionId > 0 ? $this->itemRepo->findByVersionId($versionId) : [];

        // Group items by category / contract package
        $groupedItems = [];
        $categoryTotals = [];
        $grandTotal = '0.00';

        foreach ($items as $item) {
            $catName = $item->categoryName ?? 'General Items';
            if (!isset($groupedItems[$catName])) {
                $groupedItems[$catName] = [];
                $categoryTotals[$catName] = '0.00';
            }
            $groupedItems[$catName][] = $item;
            $categoryTotals[$catName] = Decimal::add($categoryTotals[$catName], $item->estimatedTotalCost, 2);
            $grandTotal = Decimal::add($grandTotal, $item->estimatedTotalCost, 2);
        }

        // Approver information
        $approver = null;
        if (!empty($plan['approved_by_user_id'])) {
            $stmtAppr = $this->db->prepare("SELECT first_name, last_name, email FROM `users` WHERE `id` = :id");
            $stmtAppr->execute(['id' => $plan['approved_by_user_id']]);
            $approver = $stmtAppr->fetch(PDO::FETCH_ASSOC);
        }

        return Response::html(View::render('procurement_plans/print', [
            'title' => "Procurement Plan {$plan['fiscal_year']} - {$plan['entity_name']}",
            'plan' => $plan,
            'items' => $items,
            'groupedItems' => $groupedItems,
            'categoryTotals' => $categoryTotals,
            'grandTotal' => $grandTotal,
            'approver' => $approver,
        ], null)); // Renders standalone without main navigation wrapper for crisp PDF printing
    }

    /**
     * Display version history, revisions, and quarterly review cycles.
     */
    public function versions(Request $request, array $params = []): Response
    {
        if (AuthManager::guest()) {
            return Response::redirect('/login');
        }

        $id = (int)($params['id'] ?? $request->routeParam('id', 0));
        $plan = $this->fetchPlanWithRelations($id);
        if ($plan === null) {
            throw new NotFoundException("Procurement Plan #{$id} not found.");
        }

        PlanAuthorizationGuard::requireCanViewPlan((int)$plan['planning_entity_id']);

        // Fetch all versions of this plan
        $sqlVersions = "SELECT pv.*, 
                               u.first_name AS creator_first_name, 
                               u.last_name AS creator_last_name,
                               appr.first_name AS approver_first_name,
                               appr.last_name AS approver_last_name,
                               (SELECT COUNT(*) FROM `procurement_plan_items` ppi WHERE ppi.plan_version_id = pv.id) AS item_count
                        FROM `procurement_plan_versions` pv
                        LEFT JOIN `users` u ON u.id = pv.created_by
                        LEFT JOIN `users` appr ON appr.id = pv.approved_by_user_id
                        WHERE pv.procurement_plan_id = :plan_id
                        ORDER BY pv.id DESC";
        $stmtV = $this->db->prepare($sqlVersions);
        $stmtV->execute(['plan_id' => $id]);
        $versions = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        // Fetch revision records
        $sqlRevisions = "SELECT prr.*, 
                                pv_base.version_number AS prior_version_number,
                                pv_new.version_number AS new_version_number,
                                u.first_name AS creator_first_name,
                                u.last_name AS creator_last_name
                         FROM `plan_revision_records` prr
                         JOIN `procurement_plan_versions` pv_base ON pv_base.id = prr.prior_version_id
                         JOIN `procurement_plan_versions` pv_new ON pv_new.id = prr.new_version_id
                         LEFT JOIN `users` u ON u.id = prr.submitted_by_user_id
                         WHERE prr.procurement_plan_id = :plan_id
                         ORDER BY prr.submitted_at DESC";
        $stmtR = $this->db->prepare($sqlRevisions);
        $stmtR->execute(['plan_id' => $id]);
        $revisions = $stmtR->fetchAll(PDO::FETCH_ASSOC);

        // Fetch review cycles
        $sqlCycles = "SELECT prc.*, 
                             pv.version_number AS active_version_number,
                             u.first_name AS reviewer_first_name,
                             u.last_name AS reviewer_last_name
                      FROM `plan_review_cycles` prc
                      JOIN `procurement_plan_versions` pv ON pv.id = prc.active_version_id
                      LEFT JOIN `users` u ON u.id = prc.reviewed_by_user_id
                      WHERE prc.procurement_plan_id = :plan_id
                      ORDER BY prc.review_quarter ASC";
        $stmtC = $this->db->prepare($sqlCycles);
        $stmtC->execute(['plan_id' => $id]);
        $cycles = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        return Response::html(View::render('procurement_plans/versions', [
            'title' => "Version History - Plan {$plan['plan_number']}",
            'activeNav' => 'procurement_plans',
            'plan' => $plan,
            'versions' => $versions,
            'revisions' => $revisions,
            'cycles' => $cycles,
        ], 'app'));
    }

    // =========================================================================
    // Private Query & Helper Utilities
    // =========================================================================

    private function fetchPlanWithRelations(int $id): ?array
    {
        $sql = "SELECT pp.*, 
                       pe.entity_name, 
                       pe.entity_code,
                       pe.campus_id,
                       pv.version_number,
                       pv.total_estimated_cost,
                       pv.approval_date,
                       pv.approved_by_user_id,
                       u.first_name AS creator_first_name,
                       u.last_name AS creator_last_name,
                       u.email AS creator_email
                FROM `procurement_plans` pp
                JOIN `planning_entities` pe ON pe.id = pp.planning_entity_id
                LEFT JOIN `procurement_plan_versions` pv ON pv.id = pp.current_version_id
                LEFT JOIN `users` u ON u.id = pp.created_by
                WHERE pp.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function getUserAuthorizedEntityIds(int $userId): array
    {
        $sql = "SELECT DISTINCT `planning_entity_id` 
                FROM `user_entity_roles` 
                WHERE `user_id` = :uid AND `status` = 'ACTIVE'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function getAvailableEntities(array $authorizedEntityIds, bool $isSuper): array
    {
        if ($isSuper) {
            $sql = "SELECT id, entity_code, entity_name FROM `planning_entities` WHERE `is_active` = 1 ORDER BY entity_name ASC";
            return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($authorizedEntityIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($authorizedEntityIds), '?'));
        $sql = "SELECT id, entity_code, entity_name 
                FROM `planning_entities` 
                WHERE `is_active` = 1 AND `id` IN ({$placeholders}) 
                ORDER BY entity_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($authorizedEntityIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getActiveCategories(): array
    {
        $sql = "SELECT id, category_code, category_name FROM `item_categories` WHERE `is_active` = 1 ORDER BY category_name ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getActiveUnitsOfMeasure(): array
    {
        $sql = "SELECT id, uom_code, uom_name FROM `units_of_measure` WHERE `is_active` = 1 ORDER BY uom_name ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getActiveStandardItems(): array
    {
        $sql = "SELECT id, item_code, item_name, category_id, default_uom_id AS uom_id, estimated_unit_price 
                FROM `standard_items` 
                WHERE `is_active` = 1 
                ORDER BY item_name ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getStatusCounts(array $userEntityIds, bool $isSuper): array
    {
        $where = '1=1';
        $params = [];

        if (!$isSuper) {
            if (empty($userEntityIds)) {
                return ['ALL' => 0, 'DRAFT' => 0, 'SUBMITTED' => 0, 'UNDER_REVIEW' => 0, 'APPROVED' => 0, 'RETURNED' => 0, 'REJECTED' => 0];
            }
            $placeholders = [];
            foreach ($userEntityIds as $idx => $eid) {
                $key = ":ue_{$idx}";
                $placeholders[] = $key;
                $params[$key] = $eid;
            }
            $where = 'planning_entity_id IN (' . implode(',', $placeholders) . ')';
        }

        $sql = "SELECT status, COUNT(*) as cnt FROM `procurement_plans` WHERE {$where} GROUP BY status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $total = array_sum($rows);

        return [
            'ALL' => $total,
            'DRAFT' => (int)($rows['DRAFT'] ?? 0),
            'SUBMITTED' => (int)($rows['SUBMITTED'] ?? 0),
            'UNDER_REVIEW' => (int)($rows['UNDER_REVIEW'] ?? 0),
            'APPROVED' => (int)($rows['APPROVED'] ?? 0),
            'RETURNED' => (int)($rows['RETURNED'] ?? 0),
            'REJECTED' => (int)($rows['REJECTED'] ?? 0),
        ];
    }

    private function getActionLogs(string $documentType, int $documentId): array
    {
        $sql = "SELECT wal.*, u.first_name, u.last_name, u.username
                FROM `workflow_action_logs` wal
                LEFT JOIN `users` u ON u.id = wal.actor_user_id
                WHERE wal.document_type = :doc_type AND wal.document_id = :doc_id
                ORDER BY wal.action_timestamp ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['doc_type' => $documentType, 'doc_id' => $documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function logWorkflowAction(
        string $documentType,
        int $documentId,
        ?int $stepId,
        int $actorUserId,
        string $action,
        string $preStatus,
        string $postStatus,
        ?string $comments = null
    ): void {
        try {
            $sql = "INSERT INTO `workflow_action_logs` (
                        `document_type`,
                        `document_id`,
                        `step_id`,
                        `actor_user_id`,
                        `action`,
                        `pre_status`,
                        `post_status`,
                        `comments`,
                        `action_timestamp`
                    ) VALUES (
                        :doc_type,
                        :doc_id,
                        :step_id,
                        :actor,
                        :action,
                        :pre,
                        :post,
                        :comments,
                        NOW()
                    )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'doc_type' => $documentType,
                'doc_id' => $documentId,
                'step_id' => $stepId,
                'actor' => $actorUserId,
                'action' => $action,
                'pre' => $preStatus,
                'post' => $postStatus,
                'comments' => $comments,
            ]);
        } catch (Throwable $e) {
            Logger::warning("Failed to write workflow action log: {$e->getMessage()}");
        }
    }

    private function parseAndSanitizeItems(array $rawItems): array
    {
        $sanitized = [];
        $defaultStandardItemId = 1;

        // Try to get a valid default standard item id if available
        try {
            $sStmt = $this->db->query("SELECT id FROM `standard_items` WHERE is_active = 1 LIMIT 1");
            $defaultStandardItemId = (int)($sStmt->fetchColumn() ?: 1);
        } catch (\Throwable) {
            $defaultStandardItemId = 1;
        }

        // Support array-of-rows or transposed column arrays
        if (isset($rawItems['item_description']) && is_array($rawItems['item_description'])) {
            $count = count($rawItems['item_description']);
            for ($i = 0; $i < $count; $i++) {
                $desc = trim((string)($rawItems['item_description'][$i] ?? ''));
                if ($desc === '') {
                    continue; // Skip empty rows
                }

                $sanitized[] = [
                    'category_id' => (int)($rawItems['category_id'][$i] ?? 1),
                    'standard_item_id' => (int)($rawItems['standard_item_id'][$i] ?? $defaultStandardItemId),
                    'item_description' => $desc,
                    'justification' => trim((string)($rawItems['justification'][$i] ?? $rawItems['specification'][$i] ?? '')),
                    'uom_id' => (int)($rawItems['uom_id'][$i] ?? 1),
                    'planned_quantity' => trim((string)($rawItems['planned_quantity'][$i] ?? '1.00')),
                    'estimated_unit_cost' => trim((string)($rawItems['estimated_unit_cost'][$i] ?? '0.00')),
                    'target_quarter' => strtoupper(trim((string)($rawItems['target_quarter'][$i] ?? 'Q1'))),
                    'funding_source' => trim((string)($rawItems['funding_source'][$i] ?? 'GoG Consolidated Fund')),
                ];
            }
        } elseif (is_array($rawItems)) {
            foreach ($rawItems as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $desc = trim((string)($row['item_description'] ?? ''));
                if ($desc === '') {
                    continue;
                }

                $sanitized[] = [
                    'category_id' => (int)($row['category_id'] ?? 1),
                    'standard_item_id' => (int)($row['standard_item_id'] ?? $defaultStandardItemId),
                    'item_description' => $desc,
                    'justification' => trim((string)($row['justification'] ?? $row['specification'] ?? '')),
                    'uom_id' => (int)($row['uom_id'] ?? 1),
                    'planned_quantity' => trim((string)($row['planned_quantity'] ?? '1.00')),
                    'estimated_unit_cost' => trim((string)($row['estimated_unit_cost'] ?? '0.00')),
                    'target_quarter' => strtoupper(trim((string)($row['target_quarter'] ?? 'Q1'))),
                    'funding_source' => trim((string)($row['funding_source'] ?? 'GoG Consolidated Fund')),
                ];
            }
        }

        return $sanitized;
    }

    private function validateCsrf(Request $request): void
    {
        $token = $request->input('_csrf_token') ?? $request->header('X-CSRF-Token');
        if (!Csrf::validate($token)) {
            throw new ValidationException('Invalid or expired security token. Please refresh the page and try again.', [
                'csrf' => ['Security verification failed.'],
            ]);
        }
    }
}
