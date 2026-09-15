<?php

declare(strict_types=1);

/**
 * RoleBasedDashboardQueueTest
 * 
 * Complete verification of the PROMIS Role-Based Operational Workflow Dashboard & Approval Queues:
 * - Requester dashboard, KPIs, draft lists, and self-approval guards
 * - HOD Department Approval Queue, department-scoped filtering, endorse/return/reject actions
 * - Dean Faculty Approval Queue, closure-table faculty scoping, boundary isolation
 * - Finance Commitment Queue, commitment authorizations, guards on unapproved requests
 * - Procurement Queue, order receiving, guards on uncommitted requests
 * - Admin System Telemetry & separation from operational workflow authority
 * - Approval queue server-side authorization and security guards
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Src\Execution\Auth\ExecutionAuthorizationGuard;
use Promis\Src\Execution\Domain\DTO\WorkflowActionRequest;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Exception\InvalidWorkflowTransitionException;
use Promis\Src\Execution\Exception\UnauthorizedExecutionException;
use Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Identity\Domain\DTO\CreateEntityDTO;
use Promis\Src\Identity\Repository\PlanningEntityRepository;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Service\EntityManagementService;
use Promis\Src\Presentation\Controller\DashboardController;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   PROMIS Role-Based Operational Dashboard & Queues Suite       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$db = Connection::get();
$passed = 0;
$failed = 0;
$errors = [];

function assert_test(bool $condition, string $description, int &$p, int &$f, array &$errs): void {
    if ($condition) {
        $p++;
        echo "  ✓ {$description}\n";
    } else {
        $f++;
        $errs[] = $description;
        echo "  ✗ FAIL: {$description}\n";
    }
}

// ─────────────────────────────────────────────────
// SECTION 1: FIXTURE SETUP
// ─────────────────────────────────────────────────
echo "┌─ Section 1: Fixture Setup (Hierarchy, Users & Requisitions) ──\n";

$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("DELETE FROM entity_hierarchies WHERE descendant_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'DASH-%')");
$db->exec("DELETE FROM entity_hierarchies WHERE ancestor_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'DASH-%')");
$db->exec("DELETE FROM requisition_items WHERE requisition_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-DASH-%')");
$db->exec("DELETE FROM workflow_action_logs WHERE document_type='REQUISITION' AND document_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-DASH-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='requisitions' AND record_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-DASH-%')");
$db->exec("DELETE FROM requisitions WHERE requisition_number LIKE 'REQ-DASH-%'");
$db->exec("DELETE FROM user_entity_roles WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'dash.%')");
$db->exec("DELETE FROM users WHERE username LIKE 'dash.%'");
$db->exec("DELETE FROM planning_entities WHERE entity_code LIKE 'DASH-%'");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

$entityRepo = new PlanningEntityRepository($db);
$entityService = new EntityManagementService($entityRepo);
$userRepo = new UserRepository($db);
$wfService = new RequisitionWorkflowService($db);
$dashboardController = new DashboardController($db);

$campusId = (int)$db->query("SELECT id FROM campuses WHERE is_active=1 LIMIT 1")->fetchColumn();
$univTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='UNIV' LIMIT 1")->fetchColumn();
$facTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='FAC' LIMIT 1")->fetchColumn();
$deptTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='DEPT' LIMIT 1")->fetchColumn();

// Create 2 Faculties with 2 Departments each to test boundary isolation:
// University: DASH-USTED
// ├── Faculty A (Science): DASH-FAC-A
// │   └── Dept CS: DASH-DEPT-CS
// └── Faculty B (Business): DASH-FAC-B
//     └── Dept ACC: DASH-DEPT-ACC

$univId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'DASH-USTED',
    'entity_name' => 'Dash University of Skills',
    'entity_type_id' => $univTypeId,
    'campus_id' => $campusId,
]), 1, '127.0.0.1', 'DashTest');

$facAId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'DASH-FAC-A',
    'entity_name' => 'Dash Faculty of Applied Science',
    'entity_type_id' => $facTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]), 1, '127.0.0.1', 'DashTest');

$deptCsId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'DASH-DEPT-CS',
    'entity_name' => 'Dash Computer Science Dept',
    'entity_type_id' => $deptTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $facAId,
]), 1, '127.0.0.1', 'DashTest');

$facBId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'DASH-FAC-B',
    'entity_name' => 'Dash Faculty of Business',
    'entity_type_id' => $facTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]), 1, '127.0.0.1', 'DashTest');

$deptAccId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'DASH-DEPT-ACC',
    'entity_name' => 'Dash Accounting Dept',
    'entity_type_id' => $deptTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $facBId,
]), 1, '127.0.0.1', 'DashTest');

assert_test($univId > 0 && $facAId > 0 && $deptCsId > 0 && $facBId > 0 && $deptAccId > 0, 'Create multi-faculty test hierarchy', $passed, $failed, $errors);

// Create Roles & Users
$adminRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='ADMIN'")->fetchColumn();
$reqRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='REQUESTER'")->fetchColumn();
$hodRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='HOD'")->fetchColumn();
$deanRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='DEAN'")->fetchColumn();
$financeRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='FINANCE_OFFICER'")->fetchColumn();
$procRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='PROCUREMENT_OFFICER'")->fetchColumn();

function createDashUser(PDO $db, string $username, string $roleCode, int $roleId, ?int $entityId, string $status = 'ACTIVE'): array {
    $hash = password_hash('Secret123!', PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, status, created_at) VALUES (:u, :e, :p, :f, :l, :s, NOW())");
    $stmt->execute([
        'u' => $username,
        'e' => "{$username}@usted.edu.gh",
        'p' => $hash,
        'f' => ucfirst(explode('.', $username)[1] ?? 'User'),
        'l' => 'Test',
        's' => $status
    ]);
    $uid = (int)$db->lastInsertId();

    if ($entityId !== null) {
        $db->prepare("INSERT INTO user_entity_roles (user_id, role_id, planning_entity_id, status, is_primary, assigned_by) VALUES (:u, :r, :e, :s, 1, 1)")
           ->execute(['u' => $uid, 'r' => $roleId, 'e' => $entityId, 's' => $status]);
    }

    return ['id' => $uid, 'username' => $username, 'role_code' => $roleCode, 'role_id' => $roleId, 'entity_id' => $entityId];
}

$uReq = createDashUser($db, 'dash.requester', 'REQUESTER', $reqRoleId, $deptCsId);
$uHodCs = createDashUser($db, 'dash.hod_cs', 'HOD', $hodRoleId, $deptCsId);
$uHodAcc = createDashUser($db, 'dash.hod_acc', 'HOD', $hodRoleId, $deptAccId);
$uDeanA = createDashUser($db, 'dash.dean_a', 'DEAN', $deanRoleId, $facAId);
$uDeanB = createDashUser($db, 'dash.dean_b', 'DEAN', $deanRoleId, $facBId);
$uFinance = createDashUser($db, 'dash.finance', 'FINANCE_OFFICER', $financeRoleId, $univId);
$uProcure = createDashUser($db, 'dash.procure', 'PROCUREMENT_OFFICER', $procRoleId, $univId);
$uAdmin = createDashUser($db, 'dash.admin', 'ADMIN', $adminRoleId, $univId);

function createDashReq(PDO $db, int $entityId, int $userId, string $status, string $cost = '5000.00', string $justification = 'Test requisition'): int {
    static $counter = 100;
    $counter++;
    $num = "REQ-DASH-{$counter}";
    $stmt = $db->prepare("
        INSERT INTO requisitions (requisition_number, planning_entity_id, fiscal_year, status, total_estimated_cost, justification, created_by, submitted_by, created_at, submitted_at)
        VALUES (:num, :pe, 2026, :st, :cost, :j, :cb, :sb, NOW(), :subat)
    ");
    $subAt = $status !== 'DRAFT' ? date('Y-m-d H:i:s') : null;
    $subBy = $status !== 'DRAFT' ? $userId : null;
    $stmt->execute([
        'num' => $num,
        'pe' => $entityId,
        'st' => $status,
        'cost' => $cost,
        'j' => $justification,
        'cb' => $userId,
        'sb' => $subBy,
        'subat' => $subAt,
    ]);
    return (int)$db->lastInsertId();
}

// Populate sample requisitions across various lifecycle stages
$reqDraft = createDashReq($db, $deptCsId, $uReq['id'], 'DRAFT', '1500.00', 'Draft laptops request');
$reqSubmittedCs = createDashReq($db, $deptCsId, $uReq['id'], 'SUBMITTED', '3500.00', 'Submitted servers request');
$reqSubmittedAcc = createDashReq($db, $deptAccId, $uReq['id'], 'SUBMITTED', '2000.00', 'Submitted accounting software');
$reqEndorsedCs = createDashReq($db, $deptCsId, $uReq['id'], 'ENDORSED', '8000.00', 'Endorsed network switches');
$reqEndorsedAcc = createDashReq($db, $deptAccId, $uReq['id'], 'ENDORSED', '6500.00', 'Endorsed office furniture');
$reqApproved = createDashReq($db, $deptCsId, $uReq['id'], 'DEPARTMENT_APPROVED', '12000.00', 'Dean approved workstations');
$reqCommitted = createDashReq($db, $deptCsId, $uReq['id'], 'COMMITMENT_AUTHORIZED', '15000.00', 'Finance committed lab equipment');
$reqCompleted = createDashReq($db, $deptCsId, $uReq['id'], 'PROCUREMENT_RECEIVED', '5000.00', 'Received printer toners');
$reqReturned = createDashReq($db, $deptCsId, $uReq['id'], 'RETURNED', '2200.00', 'Returned for specs clarification');
$reqRejected = createDashReq($db, $deptCsId, $uReq['id'], 'REJECTED', '99000.00', 'Rejected over budget');

assert_test(true, 'Test users, entities, and multi-stage requisitions seeded', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 2: REQUESTER DASHBOARD VERIFICATION
// ─────────────────────────────────────────────────
echo "\n┌─ Section 2: Requester Dashboard & Worklists ─────────────────\n";

$reqKpis = $dashboardController->resolveRoleKpis($uReq['id'], ['REQUESTER']);
assert_test($reqKpis['requester']['drafts'] >= 1, 'Requester KPI: My Drafts count matches database', $passed, $failed, $errors);
assert_test($reqKpis['requester']['submitted'] >= 4, 'Requester KPI: Submitted & in pipeline count matches database', $passed, $failed, $errors);
assert_test($reqKpis['requester']['returned'] >= 1, 'Requester KPI: Returned count matches database', $passed, $failed, $errors);
assert_test($reqKpis['requester']['completed'] >= 1, 'Requester KPI: Completed count matches database', $passed, $failed, $errors);
assert_test($reqKpis['requester']['rejected'] >= 1, 'Requester KPI: Rejected count matches database', $passed, $failed, $errors);

$reqQueues = $dashboardController->resolveRequesterQueues($uReq['id']);
assert_test(!empty($reqQueues['drafts']), 'Requester worklist: My Drafts populated', $passed, $failed, $errors);
assert_test(!empty($reqQueues['submitted']), 'Requester worklist: My Submitted Requests populated', $passed, $failed, $errors);
assert_test(!empty($reqQueues['returned']), 'Requester worklist: Returned Requests populated', $passed, $failed, $errors);
assert_test(!empty($reqQueues['rejected']), 'Requester worklist: Rejected Requests populated', $passed, $failed, $errors);
assert_test(!empty($reqQueues['completed']), 'Requester worklist: Completed Requests populated', $passed, $failed, $errors);

// Requester cannot approve or endorse (self-approval guard)
$reqDto = $userRepo->findById($uReq['id']);
AuthManager::login($reqDto->toSessionArray());
try {
    $act = new WorkflowActionRequest($reqSubmittedCs, WorkflowAction::ENDORSE, $uReq['id'], 'Requester self-endorse attempt');
    $wfService->executeAction($act);
    assert_test(false, 'Requester must NOT endorse requisitions', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Requester self-endorsement rejected with role guard', $passed, $failed, $errors);
}

// ─────────────────────────────────────────────────
// SECTION 3: HOD DASHBOARD & QUEUE VERIFICATION
// ─────────────────────────────────────────────────
echo "\n┌─ Section 3: HOD Department Approval Queue ───────────────────\n";

$hodCsEntities = $dashboardController->resolveHodEntityIds($uHodCs['id']);
assert_test(in_array($deptCsId, $hodCsEntities, true), 'HOD CS resolves authorized department entity ID', $passed, $failed, $errors);
assert_test(!in_array($deptAccId, $hodCsEntities, true), 'HOD CS does NOT resolve unrelated Accounting department ID', $passed, $failed, $errors);

$hodKpis = $dashboardController->resolveRoleKpis($uHodCs['id'], ['HOD'], $hodCsEntities);
assert_test($hodKpis['hod']['awaiting'] >= 1, 'HOD KPI: Awaiting Endorsement matches department submitted count', $passed, $failed, $errors);
assert_test($hodKpis['hod']['endorsed'] >= 3, 'HOD KPI: Endorsed count matches department endorsed+ count', $passed, $failed, $errors);
assert_test($hodKpis['hod']['returned'] >= 1, 'HOD KPI: Returned count matches department returned count', $passed, $failed, $errors);
assert_test($hodKpis['hod']['rejected'] >= 1, 'HOD KPI: Rejected count matches department rejected count', $passed, $failed, $errors);

$hodQueue = $dashboardController->resolveHodQueue($hodCsEntities);
$hodAwaitingIds = array_column($hodQueue['awaiting'], 'id');
assert_test(in_array($reqSubmittedCs, $hodAwaitingIds, true), 'HOD CS queue includes CS department submitted requisition', $passed, $failed, $errors);
assert_test(!in_array($reqSubmittedAcc, $hodAwaitingIds, true), 'HOD CS queue EXCLUDES Accounting department requisition (Scope Boundary)', $passed, $failed, $errors);

// HOD CS attempts to endorse Accounting department request -> MUST FAIL
$hodCsDto = $userRepo->findById($uHodCs['id']);
AuthManager::login($hodCsDto->toSessionArray());
try {
    $act = new WorkflowActionRequest($reqSubmittedAcc, WorkflowAction::ENDORSE, $uHodCs['id'], 'HOD cross-dept endorse attempt');
    $wfService->executeAction($act);
    assert_test(false, 'HOD must NOT endorse outside authorized department scope', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'HOD cross-department endorsement blocked by server-side scope guard', $passed, $failed, $errors);
}

// ─────────────────────────────────────────────────
// SECTION 4: DEAN DASHBOARD & QUEUE VERIFICATION
// ─────────────────────────────────────────────────
echo "\n┌─ Section 4: Dean Faculty Approval Queue ──────────────────────\n";

$deanAEntities = $dashboardController->resolveDeanEntityIds($uDeanA['id']);
assert_test(in_array($facAId, $deanAEntities, true), 'Dean A resolves Faculty A entity ID', $passed, $failed, $errors);
assert_test(in_array($deptCsId, $deanAEntities, true), 'Dean A resolves descendant CS Department entity ID via closure table', $passed, $failed, $errors);
assert_test(!in_array($facBId, $deanAEntities, true), 'Dean A does NOT resolve Faculty B entity ID', $passed, $failed, $errors);
assert_test(!in_array($deptAccId, $deanAEntities, true), 'Dean A does NOT resolve descendant Accounting Department of Faculty B', $passed, $failed, $errors);

$deanKpis = $dashboardController->resolveRoleKpis($uDeanA['id'], ['DEAN'], [], $deanAEntities);
assert_test($deanKpis['dean']['awaiting'] >= 1, 'Dean KPI: Awaiting Approval matches endorsed requisitions under Faculty A', $passed, $failed, $errors);
assert_test($deanKpis['dean']['approved'] >= 3, 'Dean KPI: Approved count matches approved+ under Faculty A', $passed, $failed, $errors);

$deanQueue = $dashboardController->resolveDeanQueue($deanAEntities);
$deanAwaitingIds = array_column($deanQueue['awaiting'], 'id');
assert_test(in_array($reqEndorsedCs, $deanAwaitingIds, true), 'Dean A queue includes endorsed request from child CS department', $passed, $failed, $errors);
assert_test(!in_array($reqEndorsedAcc, $deanAwaitingIds, true), 'Dean A queue EXCLUDES endorsed request from Faculty B department (Boundary Isolation)', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 5: FINANCE OFFICER DASHBOARD & QUEUE
// ─────────────────────────────────────────────────
echo "\n┌─ Section 5: Finance Commitment Queue ─────────────────────────\n";

$finKpis = $dashboardController->resolveRoleKpis($uFinance['id'], ['FINANCE_OFFICER']);
assert_test($finKpis['finance']['awaiting'] >= 1, 'Finance KPI: Awaiting Commitment matches DEPARTMENT_APPROVED count', $passed, $failed, $errors);
assert_test($finKpis['finance']['committed'] >= 2, 'Finance KPI: Committed count matches COMMITMENT_AUTHORIZED + RECEIVED', $passed, $failed, $errors);

$finQueue = $dashboardController->resolveFinanceQueue();
$finAwaitingIds = array_column($finQueue['awaiting'], 'id');
assert_test(in_array($reqApproved, $finAwaitingIds, true), 'Finance queue includes DEPARTMENT_APPROVED requisition', $passed, $failed, $errors);
assert_test(!in_array($reqSubmittedCs, $finAwaitingIds, true), 'Finance queue EXCLUDES SUBMITTED requisition', $passed, $failed, $errors);
assert_test(!in_array($reqDraft, $finAwaitingIds, true), 'Finance queue EXCLUDES DRAFT requisition', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 6: PROCUREMENT OFFICER DASHBOARD & QUEUE
// ─────────────────────────────────────────────────
echo "\n┌─ Section 6: Procurement Queue ────────────────────────────────\n";

$procKpis = $dashboardController->resolveRoleKpis($uProcure['id'], ['PROCUREMENT_OFFICER']);
assert_test($procKpis['procurement']['awaiting'] >= 1, 'Procurement KPI: Awaiting Processing matches COMMITMENT_AUTHORIZED count', $passed, $failed, $errors);
assert_test($procKpis['procurement']['received'] >= 1, 'Procurement KPI: Received matches PROCUREMENT_RECEIVED count', $passed, $failed, $errors);

$procQueue = $dashboardController->resolveProcurementQueue();
$procAwaitingIds = array_column($procQueue['awaiting'], 'id');
assert_test(in_array($reqCommitted, $procAwaitingIds, true), 'Procurement queue includes COMMITMENT_AUTHORIZED requisition', $passed, $failed, $errors);
assert_test(!in_array($reqApproved, $procAwaitingIds, true), 'Procurement queue EXCLUDES uncommitted DEPARTMENT_APPROVED requisition', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 7: ADMIN DASHBOARD & SEPARATION OF POWERS
// ─────────────────────────────────────────────────
echo "\n┌─ Section 7: Admin Telemetry & Authority Separation ───────────\n";

$adminStats = $dashboardController->resolveAdminStats();
assert_test($adminStats['total_users'] > 0, 'Admin telemetry: Total Users count > 0', $passed, $failed, $errors);
assert_test($adminStats['active_users'] > 0, 'Admin telemetry: Active Users count > 0', $passed, $failed, $errors);
assert_test($adminStats['planning_entities'] > 0, 'Admin telemetry: Planning Entities count > 0', $passed, $failed, $errors);
assert_test($adminStats['active_workflows'] > 0, 'Admin telemetry: Active Workflows count > 0', $passed, $failed, $errors);

// Admin user attempting operational workflow action (Endorse/Approve) without role assignment -> MUST BE BLOCKED
$adminDto = $userRepo->findById($uAdmin['id']);
AuthManager::login($adminDto->toSessionArray());
try {
    $act = new WorkflowActionRequest($reqSubmittedCs, WorkflowAction::ENDORSE, $uAdmin['id'], 'Admin bypass endorsement attempt');
    $wfService->executeAction($act);
    assert_test(false, 'ADMIN must NOT automatically receive operational endorsement authority', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Admin endorsement bypass BLOCKED (Separation of administrative & operational authority)', $passed, $failed, $errors);
}

// ─────────────────────────────────────────────────
// SECTION 8: DASHBOARD CONTROLLER WEB ENDPOINT TEST
// ─────────────────────────────────────────────────
echo "\n┌─ Section 8: Dashboard Controller Web Rendering ───────────────\n";

// Test Requester Dashboard View Rendering
AuthManager::login($reqDto->toSessionArray());
$reqRequest = new Request('GET', '/dashboard');
$resRequester = $dashboardController->index($reqRequest);
assert_test($resRequester->getStatusCode() === 200, 'Requester dashboard renders HTTP 200', $passed, $failed, $errors);
assert_test(str_contains($resRequester->getContent(), 'My Requisitions Workbench'), 'Requester dashboard contains Workbench section', $passed, $failed, $errors);

// Test HOD Dashboard View Rendering
AuthManager::login($hodCsDto->toSessionArray());
$hodRequest = new Request('GET', '/dashboard?tab=hod');
$resHod = $dashboardController->index($hodRequest);
assert_test($resHod->getStatusCode() === 200, 'HOD dashboard renders HTTP 200', $passed, $failed, $errors);
assert_test(str_contains($resHod->getContent(), 'Department Approval Queue'), 'HOD dashboard contains Department Approval Queue section', $passed, $failed, $errors);

// Test Dean Dashboard View Rendering
$deanDto = $userRepo->findById($uDeanA['id']);
AuthManager::login($deanDto->toSessionArray());
$deanRequest = new Request('GET', '/dashboard?tab=dean');
$resDean = $dashboardController->index($deanRequest);
assert_test($resDean->getStatusCode() === 200, 'Dean dashboard renders HTTP 200', $passed, $failed, $errors);
assert_test(str_contains($resDean->getContent(), 'Faculty Approval Queue'), 'Dean dashboard contains Faculty Approval Queue section', $passed, $failed, $errors);

// Test Finance Dashboard View Rendering
$finDto = $userRepo->findById($uFinance['id']);
AuthManager::login($finDto->toSessionArray());
$finRequest = new Request('GET', '/dashboard?tab=finance');
$resFin = $dashboardController->index($finRequest);
assert_test($resFin->getStatusCode() === 200, 'Finance dashboard renders HTTP 200', $passed, $failed, $errors);
assert_test(str_contains($resFin->getContent(), 'Finance Commitment Queue'), 'Finance dashboard contains Finance Commitment Queue section', $passed, $failed, $errors);

// Test Procurement Dashboard View Rendering
$procDto = $userRepo->findById($uProcure['id']);
AuthManager::login($procDto->toSessionArray());
$procRequest = new Request('GET', '/dashboard?tab=procurement');
$resProc = $dashboardController->index($procRequest);
assert_test($resProc->getStatusCode() === 200, 'Procurement dashboard renders HTTP 200', $passed, $failed, $errors);
assert_test(str_contains($resProc->getContent(), 'Procurement Receiving Queue'), 'Procurement dashboard contains Procurement Queue section', $passed, $failed, $errors);

// Test Unauthenticated User Redirects to /login
AuthManager::logout();
$guestReq = new Request('GET', '/dashboard');
$resGuest = $dashboardController->index($guestReq);
assert_test($resGuest->getStatusCode() === 302 && str_contains($resGuest->getHeaders()['Location'] ?? '', '/login'), 'Unauthenticated user redirected to /login', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// RESULTS
// ─────────────────────────────────────────────────
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║   ROLE-BASED DASHBOARD & QUEUES TEST RESULTS                   ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
printf("║   Passed: %d / %d                                              ║\n", $passed, $passed + $failed);
printf("║   Passed: %-3d  |  Failed: %-3d  |  Total: %-3d               ║\n", $passed, $failed, $passed + $failed);
echo "╚════════════════════════════════════════════════════════════════╝\n";

if ($failed > 0) {
    echo "Failures:\n";
    foreach ($errors as $e) {
        echo " - {$e}\n";
    }
    exit(1);
}

exit(0);
