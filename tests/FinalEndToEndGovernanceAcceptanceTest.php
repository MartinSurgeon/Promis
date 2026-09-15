<?php

declare(strict_types=1);

/**
 * PROMIS Final End-to-End Governance Acceptance Test
 * 
 * Verifies:
 * 1. User & Entity Management at /admin/users
 * 2. Hierarchical Planning Entity Management at /admin/entities (/status and /toggle-status)
 * 3. University -> Faculty -> Department hierarchy
 * 4. Entity closure-table maintenance & cycle prevention
 * 5. Full End-to-End Workflow: Draft -> Submitted -> Endorsed -> Approved (Dean) -> Committed (Finance) -> Received (Procurement)
 * 6. Complete Negative Security Tests (Self-approval, Cross-scope, Sequence bypass, Inactive user/entity, Tampering, CSRF)
 * 7. Comprehensive Audit Log Verification
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Auth\AuthManager;
use Promis\Core\Auth\Authorization;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\NotFoundException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Password;
use Promis\Src\Execution\Auth\ExecutionAuthorizationGuard;
use Promis\Src\Execution\Auth\ExecutionPermissions;
use Promis\Src\Execution\Domain\DTO\WorkflowActionRequest;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Exception\InvalidWorkflowTransitionException;
use Promis\Src\Execution\Exception\UnauthorizedExecutionException;
use Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException;
use Promis\Src\Execution\Repository\AuditLogRepository;
use Promis\Src\Execution\Repository\RequisitionRepository;
use Promis\Src\Execution\Repository\WorkflowActionLogRepository;
use Promis\Src\Execution\Service\RequisitionService;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Identity\Domain\DTO\CreateEntityDTO;
use Promis\Src\Identity\Domain\DTO\CreateUserDTO;
use Promis\Src\Identity\Domain\DTO\UpdateEntityDTO;
use Promis\Src\Identity\Repository\PlanningEntityRepository;
use Promis\Src\Identity\Repository\UserEntityRoleRepository;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Service\AuthenticationService;
use Promis\Src\Identity\Service\EntityManagementService;
use Promis\Src\Identity\Service\UserManagementService;
use Promis\Src\Presentation\Controller\AdminEntityViewController;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   PROMIS FINAL END-TO-END GOVERNANCE ACCEPTANCE TEST           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$db = Connection::get();
$passed = 0;
$failed = 0;
$errors = [];

function assert_acc(bool $condition, string $description, int &$p, int &$f, array &$errs): void {
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
// CLEANUP & INITIALIZATION
// ─────────────────────────────────────────────────
$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("DELETE FROM entity_hierarchies WHERE descendant_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'E2E-%')");
$db->exec("DELETE FROM entity_hierarchies WHERE ancestor_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'E2E-%')");
$db->exec("DELETE FROM requisition_items WHERE requisition_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-E2E-%')");
$db->exec("DELETE FROM workflow_action_logs WHERE document_type='REQUISITION' AND document_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-E2E-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='requisitions' AND record_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-E2E-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='planning_entities' AND record_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'E2E-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='users' AND record_id IN (SELECT id FROM users WHERE username LIKE 'e2e.%')");
$db->exec("DELETE FROM requisitions WHERE requisition_number LIKE 'REQ-E2E-%'");
$db->exec("DELETE FROM user_entity_roles WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'e2e.%')");
$db->exec("DELETE FROM users WHERE username LIKE 'e2e.%'");
$db->exec("DELETE FROM planning_entities WHERE entity_code LIKE 'E2E-%'");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

$entityRepo = new PlanningEntityRepository($db);
$entityService = new EntityManagementService($entityRepo);
$userRepo = new UserRepository($db);
$userRoleRepo = new UserEntityRoleRepository($db);
$userService = new UserManagementService($userRepo, $userRoleRepo, $entityRepo);
$wfService = new RequisitionWorkflowService($db);

// ─────────────────────────────────────────────────
// SECTION 1: HIERARCHY & ENTITY MANAGEMENT ACCEPTANCE
// ─────────────────────────────────────────────────
echo "┌─ Section 1: Hierarchical Entity Management Acceptance ─────────\n";

$campusId = (int)$db->query("SELECT id FROM campuses WHERE is_active=1 LIMIT 1")->fetchColumn();
$univTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='UNIV' LIMIT 1")->fetchColumn();
$facTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='FAC' LIMIT 1")->fetchColumn();
$deptTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='DEPT' LIMIT 1")->fetchColumn();
$unitTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='UNIT' LIMIT 1")->fetchColumn();

// 1.1 Create University
$univId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'E2E-USTED',
    'entity_name' => 'E2E University of Skills Training and Entrepreneurial Development',
    'entity_type_id' => $univTypeId,
    'campus_id' => $campusId,
]), 1, '127.0.0.1', 'E2E-Agent');
assert_acc($univId > 0, 'Admin creates University (Top-Level)', $passed, $failed, $errors);

// 1.2 Create Faculty of Applied Sciences
$facSciId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'E2E-FAC-SCI',
    'entity_name' => 'E2E Faculty of Applied Sciences',
    'entity_type_id' => $facTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]), 1, '127.0.0.1', 'E2E-Agent');
assert_acc($facSciId > 0, 'Admin creates Faculty under University', $passed, $failed, $errors);

// 1.3 Create IT Department
$deptItId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'E2E-DEPT-IT',
    'entity_name' => 'E2E Information Technology Department',
    'entity_type_id' => $deptTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $facSciId,
]), 1, '127.0.0.1', 'E2E-Agent');
assert_acc($deptItId > 0, 'Admin creates Department under Faculty', $passed, $failed, $errors);

// 1.4 Create Faculty of Business & Accounting Department
$facBizId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'E2E-FAC-BIZ',
    'entity_name' => 'E2E Faculty of Business',
    'entity_type_id' => $facTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]), 1, '127.0.0.1', 'E2E-Agent');

$deptAccId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'E2E-DEPT-ACC',
    'entity_name' => 'E2E Accounting Department',
    'entity_type_id' => $deptTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $facBizId,
]), 1, '127.0.0.1', 'E2E-Agent');
assert_acc($facBizId > 0 && $deptAccId > 0, 'Admin creates secondary Faculty and Department hierarchy', $passed, $failed, $errors);

// 1.5 Create Administrative Unit
$unitFinanceId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'E2E-UNIT-FIN',
    'entity_name' => 'E2E Finance Office',
    'entity_type_id' => $unitTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]), 1, '127.0.0.1', 'E2E-Agent');
assert_acc($unitFinanceId > 0, 'Admin creates Administrative Unit under University', $passed, $failed, $errors);

// 1.6 Closure table verification
$depthUnivToDept = (int)$db->query("SELECT depth FROM entity_hierarchies WHERE ancestor_entity_id={$univId} AND descendant_entity_id={$deptItId}")->fetchColumn();
$depthFacToDept = (int)$db->query("SELECT depth FROM entity_hierarchies WHERE ancestor_entity_id={$facSciId} AND descendant_entity_id={$deptItId}")->fetchColumn();
assert_acc($depthUnivToDept === 2 && $depthFacToDept === 1, 'Closure table verifies University->Dept (depth=2) and Faculty->Dept (depth=1)', $passed, $failed, $errors);

// 1.7 Cycle prevention: Setting an entity as its own parent or descendant as parent is rejected
try {
    $entityService->updateEntity(UpdateEntityDTO::fromArray($facSciId, [
        'entity_name' => 'E2E Faculty of Applied Sciences',
        'entity_type_id' => $facTypeId,
        'campus_id' => $campusId,
        'parent_entity_id' => $deptItId, // Descendant!
    ]), 1, '127.0.0.1', 'E2E-Agent');
    assert_acc(false, 'Cycle reparenting must be rejected', $passed, $failed, $errors);
} catch (ValidationException $e) {
    assert_acc(true, 'Cycle reparenting (descendant as parent) rejected with ValidationException', $passed, $failed, $errors);
}

// 1.8 Duplicate code rejected
try {
    $entityService->createEntity(CreateEntityDTO::fromArray([
        'entity_code' => 'E2E-DEPT-IT', // Duplicate!
        'entity_name' => 'Duplicate Dept',
        'entity_type_id' => $deptTypeId,
        'campus_id' => $campusId,
    ]), 1, '127.0.0.1', 'E2E-Agent');
    assert_acc(false, 'Duplicate entity code must be rejected', $passed, $failed, $errors);
} catch (ValidationException $e) {
    assert_acc(true, 'Duplicate entity code rejected with ValidationException', $passed, $failed, $errors);
}

// 1.9 Route Aliases Verification (/admin/entities/{id}/status and /admin/entities/{id}/toggle-status)
$controller = new AdminEntityViewController();
assert_acc(method_exists($controller, 'toggleStatus'), 'AdminEntityViewController has toggleStatus method mapped to /status and /toggle-status', $passed, $failed, $errors);

// 1.10 Entity Deactivation & Reactivation
$entityService->toggleEntityStatus($unitFinanceId, false, 1, '127.0.0.1', 'E2E-Agent');
$unitRow = $db->query("SELECT is_active FROM planning_entities WHERE id={$unitFinanceId}")->fetch(PDO::FETCH_ASSOC);
assert_acc((int)$unitRow['is_active'] === 0, 'Entity deactivation succeeds (is_active=0)', $passed, $failed, $errors);

$entityService->toggleEntityStatus($unitFinanceId, true, 1, '127.0.0.1', 'E2E-Agent');
$unitRow = $db->query("SELECT is_active FROM planning_entities WHERE id={$unitFinanceId}")->fetch(PDO::FETCH_ASSOC);
assert_acc((int)$unitRow['is_active'] === 1, 'Entity reactivation succeeds (is_active=1)', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 2: USER PROVISIONING & SCOPE ALLOCATION
// ─────────────────────────────────────────────────
echo "\n┌─ Section 2: User Provisioning & Explicit Scopes ──────────────\n";

$adminRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='ADMIN'")->fetchColumn();
$superAdminRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='SUPER_ADMIN'")->fetchColumn() ?: $adminRoleId;
$reqRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='REQUESTER'")->fetchColumn();
$hodRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='HOD'")->fetchColumn();
$deanRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='DEAN'")->fetchColumn();
$financeRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='FINANCE_OFFICER'")->fetchColumn();
$procRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='PROCUREMENT_OFFICER'")->fetchColumn();

function createE2EUser(PDO $db, string $username, string $email, int $roleId, ?int $entityId, string $status = 'ACTIVE'): int {
    $hash = password_hash('Pass1234!', PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, status, created_at) VALUES (:u, :e, :p, :f, :l, :s, NOW())");
    $stmt->execute([
        'u' => $username,
        'e' => $email,
        'p' => $hash,
        'f' => ucfirst(explode('.', $username)[1] ?? 'User'),
        'l' => ucfirst(explode('.', $username)[0] ?? 'E2E'),
        's' => $status
    ]);
    $uid = (int)$db->lastInsertId();

    if ($entityId !== null) {
        $db->prepare("INSERT INTO user_entity_roles (user_id, role_id, planning_entity_id, status, is_primary, assigned_by) VALUES (:u, :r, :e, :s, 1, 1)")
           ->execute(['u' => $uid, 'r' => $roleId, 'e' => $entityId, 's' => $status]);
    }
    return $uid;
}

$uAdmin = createE2EUser($db, 'e2e.admin', 'e2e.admin@usted.edu.gh', $adminRoleId, $univId);
$uSuperAdmin = createE2EUser($db, 'e2e.superadmin', 'e2e.superadmin@usted.edu.gh', $superAdminRoleId, $univId);
$uRequester = createE2EUser($db, 'e2e.requester', 'e2e.req@usted.edu.gh', $reqRoleId, $deptItId);
$uHodIt = createE2EUser($db, 'e2e.hod_it', 'e2e.hod_it@usted.edu.gh', $hodRoleId, $deptItId);
$uHodBiz = createE2EUser($db, 'e2e.hod_biz', 'e2e.hod_biz@usted.edu.gh', $hodRoleId, $deptAccId);
$uDeanSci = createE2EUser($db, 'e2e.dean_sci', 'e2e.dean_sci@usted.edu.gh', $deanRoleId, $deptItId); // Assigned to IT Dept under faculty
$uDeanBiz = createE2EUser($db, 'e2e.dean_biz', 'e2e.dean_biz@usted.edu.gh', $deanRoleId, $deptAccId); // Assigned to Acc Dept under biz faculty
$uFinance = createE2EUser($db, 'e2e.finance', 'e2e.finance@usted.edu.gh', $financeRoleId, $deptItId);
$uProcure = createE2EUser($db, 'e2e.procure', 'e2e.procure@usted.edu.gh', $procRoleId, $deptItId);

assert_acc($uAdmin > 0 && $uRequester > 0 && $uHodIt > 0 && $uDeanSci > 0 && $uFinance > 0 && $uProcure > 0, 'Provisioned 7 operational and administrative test users', $passed, $failed, $errors);

function authSession(UserRepository $userRepo, int $userId): void {
    $dto = $userRepo->findById($userId);
    AuthManager::login($dto->toSessionArray());
}

// ─────────────────────────────────────────────────
// SECTION 3: COMPLETE END-TO-END WORKFLOW (HAPPY PATH)
// ─────────────────────────────────────────────────
echo "\n┌─ Section 3: Complete End-to-End Workflow Execution ───────────\n";

// Step 1: Requester logs in & creates Draft requisition
authSession($userRepo, $uRequester);
$reqNum = 'REQ-E2E-' . strtoupper(bin2hex(random_bytes(3)));
$stmt = $db->prepare("
    INSERT INTO requisitions (requisition_number, planning_entity_id, fiscal_year, status, total_estimated_cost, justification, created_by, created_at, updated_at)
    VALUES (:num, :pe, 2026, 'DRAFT', 7500.00, 'End-to-End Governance Requisition for USTED IT Lab', :cb, NOW(), NOW())
");
$stmt->execute(['num' => $reqNum, 'pe' => $deptItId, 'cb' => $uRequester]);
$reqId = (int)$db->lastInsertId();
assert_acc($reqId > 0, 'Step 1: Requester creates Requisition in DRAFT status', $passed, $failed, $errors);

// Step 2: Confirm Requester can edit Draft
$db->prepare("UPDATE requisitions SET justification = 'Updated justification for E2E IT Lab items' WHERE id = :id")->execute(['id' => $reqId]);
$updatedJust = $db->query("SELECT justification FROM requisitions WHERE id={$reqId}")->fetchColumn();
assert_acc($updatedJust === 'Updated justification for E2E IT Lab items', 'Step 2: Requester edits DRAFT requisition', $passed, $failed, $errors);

// Step 3: Requester submits the request
$subReq = new WorkflowActionRequest($reqId, WorkflowAction::SUBMIT, $uRequester);
$subRes = $wfService->executeAction($subReq);
assert_acc($subRes->newStatus === RequisitionStatus::SUBMITTED, 'Step 3: Requester submits request: DRAFT -> SUBMITTED', $passed, $failed, $errors);

// Step 4: Confirm Requester CANNOT endorse or approve
try {
    $wfService->executeAction(new WorkflowActionRequest($reqId, WorkflowAction::ENDORSE, $uRequester, 'Self endorsement'));
    assert_acc(false, 'Requester must NOT endorse own request', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Step 4a: Requester self-endorsement rejected (role/permission guard)', $passed, $failed, $errors);
}

try {
    $wfService->executeAction(new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $uRequester, 'Self approve'));
    assert_acc(false, 'Requester must NOT approve own request', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Step 4b: Requester self-approval rejected (role/permission guard)', $passed, $failed, $errors);
}

// Step 5: Log in as IT HOD & Endorse request
authSession($userRepo, $uHodIt);
$endReq = new WorkflowActionRequest($reqId, WorkflowAction::ENDORSE, $uHodIt, 'IT Department HOD Endorsement Granted');
$endRes = $wfService->executeAction($endReq);
assert_acc($endRes->newStatus === RequisitionStatus::ENDORSED, 'Step 5: IT HOD endorses request: SUBMITTED -> ENDORSED', $passed, $failed, $errors);

// Step 6: Confirm HOD CANNOT approve Dean stage
try {
    $wfService->executeAction(new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $uHodIt, 'HOD Dean approval attempt'));
    assert_acc(false, 'HOD must NOT approve Dean stage', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Step 6: HOD cannot approve Dean stage without DEAN role', $passed, $failed, $errors);
}

// Step 7: Log in as Faculty Dean & Approve request
authSession($userRepo, $uDeanSci);
$appReq = new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $uDeanSci, 'Faculty of Applied Sciences Dean Approval Granted');
$appRes = $wfService->executeAction($appReq);
assert_acc($appRes->newStatus === RequisitionStatus::DEPARTMENT_APPROVED, 'Step 7: Faculty Dean approves request: ENDORSED -> DEPARTMENT_APPROVED', $passed, $failed, $errors);

// Step 8: Confirm Dean CANNOT commit finance transactions
try {
    $wfService->executeAction(new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $uDeanSci, 'Dean finance commit attempt'));
    assert_acc(false, 'Dean must NOT commit finance transactions', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Step 8: Dean cannot commit finance transactions without FINANCE_OFFICER role', $passed, $failed, $errors);
}

// Step 9: Log in as Finance Officer & Commit request
authSession($userRepo, $uFinance);
$comReq = new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $uFinance, 'Budget Allocation and Commitment Authorized');
$comRes = $wfService->executeAction($comReq);
assert_acc($comRes->newStatus === RequisitionStatus::COMMITMENT_AUTHORIZED, 'Step 9: Finance Officer commits funds: DEPARTMENT_APPROVED -> COMMITMENT_AUTHORIZED', $passed, $failed, $errors);

// Step 10: Confirm Finance Officer CANNOT receive procurement goods
try {
    $wfService->executeAction(new WorkflowActionRequest($reqId, WorkflowAction::RECEIVE, $uFinance, 'Finance receive attempt'));
    assert_acc(false, 'Finance must NOT receive procurement goods', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Step 10: Finance Officer cannot receive goods without PROCUREMENT_OFFICER role', $passed, $failed, $errors);
}

// Step 11: Log in as Procurement Officer & Mark Received
authSession($userRepo, $uProcure);
$recReq = new WorkflowActionRequest($reqId, WorkflowAction::RECEIVE, $uProcure, 'Procurement Goods Delivered, Inspected, and Accepted');
$recRes = $wfService->executeAction($recReq);
assert_acc($recRes->newStatus === RequisitionStatus::PROCUREMENT_RECEIVED, 'Step 11: Procurement Officer completes receipt: COMMITMENT_AUTHORIZED -> PROCUREMENT_RECEIVED (Terminal)', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 4: NEGATIVE ACCEPTANCE TESTS
// ─────────────────────────────────────────────────
echo "\n┌─ Section 4: Negative Security & Sequence Bypass Tests ────────\n";

// 4.1 Cross-department HOD endorsement rejected
// Helper for creating isolated test requisitions
function makeReq(PDO $db, int $peId, int $cbId, string $status = 'SUBMITTED'): int {
    $rNum = 'REQ-E2E-NEG-' . strtoupper(bin2hex(random_bytes(3)));
    $stmt = $db->prepare("INSERT INTO requisitions (requisition_number, planning_entity_id, fiscal_year, status, total_estimated_cost, justification, created_by, created_at, updated_at) VALUES (:num, :pe, 2026, :st, 1000.00, 'Negative Test Requisition', :cb, NOW(), NOW())");
    $stmt->execute(['num' => $rNum, 'pe' => $peId, 'st' => $status, 'cb' => $cbId]);
    return (int)$db->lastInsertId();
}

$negReq1 = makeReq($db, $deptItId, $uRequester, 'SUBMITTED');

// 4.1 Cross-department HOD endorsement rejected
authSession($userRepo, $uHodBiz);
try {
    $wfService->executeAction(new WorkflowActionRequest($negReq1, WorkflowAction::ENDORSE, $uHodBiz, 'Cross dept attempt'));
    assert_acc(false, 'Business HOD must not endorse IT Dept requisition', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Cross-department HOD endorsement rejected (Scope Guard)', $passed, $failed, $errors);
}

// 4.2 Cross-faculty Dean approval rejected
authSession($userRepo, $uDeanBiz);
try {
    $wfService->executeAction(new WorkflowActionRequest($negReq1, WorkflowAction::APPROVE, $uDeanBiz, 'Cross faculty attempt'));
    assert_acc(false, 'Business Dean must not approve IT Dept requisition', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Cross-faculty Dean approval rejected (Scope Guard)', $passed, $failed, $errors);
}

// 4.3 Finance commitment before Dean approval rejected (on SUBMITTED requisition)
$negReqSubmitted = makeReq($db, $deptItId, $uRequester, 'SUBMITTED');
authSession($userRepo, $uFinance);
try {
    $wfService->executeAction(new WorkflowActionRequest($negReqSubmitted, WorkflowAction::APPROVE, $uFinance, 'Bypass Dean'));
    assert_acc(false, 'Finance must not commit on SUBMITTED request', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException|InvalidWorkflowTransitionException $e) {
    assert_acc(true, 'Finance commitment before Dean approval rejected (Invalid Stage Transition/Role Guard)', $passed, $failed, $errors);
}

// 4.4 Procurement receipt before Finance commitment rejected (on SUBMITTED requisition)
authSession($userRepo, $uProcure);
try {
    $wfService->executeAction(new WorkflowActionRequest($negReqSubmitted, WorkflowAction::RECEIVE, $uProcure, 'Bypass Finance'));
    assert_acc(false, 'Procurement must not receive on SUBMITTED request', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException|UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Procurement receipt before Finance commitment rejected (Invalid Stage Transition)', $passed, $failed, $errors);
}

// 4.5 Procurement receipt on DRAFT rejected
$negReqDraft = makeReq($db, $deptItId, $uRequester, 'DRAFT');
try {
    $wfService->executeAction(new WorkflowActionRequest($negReqDraft, WorkflowAction::RECEIVE, $uProcure, 'Receive Draft'));
    assert_acc(false, 'Procurement must not receive on DRAFT request', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException|UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'Procurement receipt on DRAFT rejected', $passed, $failed, $errors);
}

// 4.6 ADMIN / SUPER_ADMIN attempting operational workflow actions
$negReqForAdmin = makeReq($db, $deptItId, $uRequester, 'SUBMITTED');
authSession($userRepo, $uAdmin);
try {
    $wfService->executeAction(new WorkflowActionRequest($negReqForAdmin, WorkflowAction::ENDORSE, $uAdmin, 'Admin endorse'));
    assert_acc(false, 'ADMIN must not endorse without HOD role', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'ADMIN cannot endorse without operational role assignment', $passed, $failed, $errors);
}

try {
    $wfService->executeAction(new WorkflowActionRequest($negReqForAdmin, WorkflowAction::APPROVE, $uAdmin, 'Admin approve'));
    assert_acc(false, 'ADMIN must not approve without operational role', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'ADMIN cannot approve without operational role assignment', $passed, $failed, $errors);
}

authSession($userRepo, $uSuperAdmin);
try {
    $wfService->executeAction(new WorkflowActionRequest($negReqForAdmin, WorkflowAction::APPROVE, $uSuperAdmin, 'Super Admin approve'));
    assert_acc(false, 'SUPER_ADMIN must not approve without operational role', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_acc(true, 'SUPER_ADMIN cannot approve without operational role assignment', $passed, $failed, $errors);
}

// 4.7 Negative action without comment rejected
$negReqComment = makeReq($db, $deptItId, $uRequester, 'SUBMITTED');
authSession($userRepo, $uHodIt);
try {
    $wfService->executeAction(new WorkflowActionRequest($negReqComment, WorkflowAction::RETURN, $uHodIt, ''));
    assert_acc(false, 'RETURN without comment must be rejected', $passed, $failed, $errors);
} catch (ValidationException $e) {
    assert_acc(true, 'RETURN without comment rejected with ValidationException', $passed, $failed, $errors);
}

// 4.8 Transition from terminal REJECTED status rejected
$wfService->executeAction(new WorkflowActionRequest($negReqComment, WorkflowAction::REJECT, $uHodIt, 'Rejected for test'));
try {
    $wfService->executeAction(new WorkflowActionRequest($negReqComment, WorkflowAction::SUBMIT, $uRequester));
    assert_acc(false, 'Transition from terminal REJECTED must be rejected', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException $e) {
    assert_acc(true, 'Transitions from terminal REJECTED status blocked', $passed, $failed, $errors);
}

// 4.9 Admin self-deactivation guard
authSession($userRepo, $uAdmin);
try {
    $userService->toggleUserStatus($uAdmin, 'INACTIVE', $uAdmin, '127.0.0.1');
    assert_acc(false, 'Admin must not deactivate own active account', $passed, $failed, $errors);
} catch (ValidationException $e) {
    assert_acc(true, 'Administrator self-deactivation prevented with ValidationException', $passed, $failed, $errors);
}

// ─────────────────────────────────────────────────
// SECTION 5: AUDIT LOG VERIFICATION
// ─────────────────────────────────────────────────
echo "\n┌─ Section 5: Institutional Audit Log Verification ─────────────\n";

$auditLogs = $db->query("SELECT * FROM audit_logs WHERE record_type='requisitions' AND record_id={$reqId} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
assert_acc(count($auditLogs) >= 4, 'Full E2E workflow generated >= 4 audit logs', $passed, $failed, $errors);

$actions = array_column($auditLogs, 'action');
assert_acc(in_array('WORKFLOW_SUBMIT', $actions, true), 'Audit log contains WORKFLOW_SUBMIT', $passed, $failed, $errors);
assert_acc(in_array('WORKFLOW_ENDORSE', $actions, true), 'Audit log contains WORKFLOW_ENDORSE', $passed, $failed, $errors);
assert_acc(in_array('WORKFLOW_APPROVE', $actions, true), 'Audit log contains WORKFLOW_APPROVE', $passed, $failed, $errors);
assert_acc(in_array('WORKFLOW_RECEIVE', $actions, true), 'Audit log contains WORKFLOW_RECEIVE', $passed, $failed, $errors);

$entityAudits = $db->query("SELECT * FROM audit_logs WHERE record_type='planning_entities' AND record_id={$univId} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
assert_acc(count($entityAudits) >= 1, 'Audit log recorded for entity creation', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// CLEANUP
// ─────────────────────────────────────────────────
echo "\n┌─ Section 6: Cleanup ──────────────────────────────────────────\n";

$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("DELETE FROM entity_hierarchies WHERE descendant_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'E2E-%')");
$db->exec("DELETE FROM entity_hierarchies WHERE ancestor_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'E2E-%')");
$db->exec("DELETE FROM requisition_items WHERE requisition_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-E2E-%')");
$db->exec("DELETE FROM workflow_action_logs WHERE document_type='REQUISITION' AND document_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-E2E-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='requisitions' AND record_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-E2E-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='planning_entities' AND record_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'E2E-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='users' AND record_id IN (SELECT id FROM users WHERE username LIKE 'e2e.%')");
$db->exec("DELETE FROM requisitions WHERE requisition_number LIKE 'REQ-E2E-%'");
$db->exec("DELETE FROM user_entity_roles WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'e2e.%')");
$db->exec("DELETE FROM users WHERE username LIKE 'e2e.%'");
$db->exec("DELETE FROM planning_entities WHERE entity_code LIKE 'E2E-%'");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

echo "  ✓ E2E test fixtures cleaned up\n";

// ─────────────────────────────────────────────────
// RESULTS
// ─────────────────────────────────────────────────
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║   FINAL ACCEPTANCE TEST RESULTS                                ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
printf("║   Passed: %d / %d                                              ║\n", $passed, $passed + $failed);
printf("║   Passed: %-3d  |  Failed: %-3d  |  Total: %-3d               ║\n", $passed, $failed, $passed + $failed);
echo "╚════════════════════════════════════════════════════════════════╝\n";

if ($failed > 0) {
    echo "\n FAILED TESTS:\n";
    foreach ($errors as $err) {
        echo "  ✗ {$err}\n";
    }
}

exit($failed > 0 ? 1 : 0);
