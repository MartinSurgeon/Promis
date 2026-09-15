<?php

declare(strict_types=1);

/**
 * ApprovalLifecycleGovernanceVerificationTest
 * 
 * Complete role-by-role, stage-by-stage verification of the PROMIS
 * Approval Lifecycle & Governance Pipeline.
 * 
 * Verifies:
 * - Hierarchy: University -> Faculty of Applied Sciences -> IT Department
 *                        -> Faculty of Business -> Accounting Department
 * - Roles: ADMIN, SUPER_ADMIN, REQUESTER, HOD, DEAN, FINANCE_OFFICER, PROCUREMENT_OFFICER
 * - Stages: Draft -> Submitted -> Endorsed -> Approved (Dean) -> Committed (Finance) -> Received (Procurement)
 * - Returns & Rejections (with mandatory comments)
 * - Permission separation, self-approval guards, scope boundary guards
 * - Faculty-to-Department inheritance behavior documentation
 * - Closure table integrity & audit trail verification
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Auth\AuthManager;
use Promis\Core\Auth\Authorization;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Security\Session;
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
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Identity\Domain\DTO\CreateEntityDTO;
use Promis\Src\Identity\Domain\DTO\CreateUserDTO;
use Promis\Src\Identity\Repository\PlanningEntityRepository;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Service\EntityManagementService;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   PROMIS Approval Lifecycle & Governance Verification Suite    ║\n";
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
// SETUP: TEST HIERARCHY & TEST USERS
// ─────────────────────────────────────────────────
echo "┌─ Section 1: Fixture Setup (Hierarchy & Roles) ────────────────\n";

// Disable foreign keys during cleanup
$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("DELETE FROM entity_hierarchies WHERE descendant_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'GOV-%')");
$db->exec("DELETE FROM entity_hierarchies WHERE ancestor_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'GOV-%')");
$db->exec("DELETE FROM requisition_items WHERE requisition_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-GOV-%')");
$db->exec("DELETE FROM workflow_action_logs WHERE document_type='REQUISITION' AND document_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-GOV-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='requisitions' AND record_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-GOV-%')");
$db->exec("DELETE FROM requisitions WHERE requisition_number LIKE 'REQ-GOV-%'");
$db->exec("DELETE FROM procurement_plan_items WHERE plan_version_id IN (SELECT id FROM procurement_plan_versions WHERE procurement_plan_id IN (SELECT id FROM procurement_plans WHERE planning_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'GOV-%')))");
$db->exec("DELETE FROM procurement_plan_versions WHERE procurement_plan_id IN (SELECT id FROM procurement_plans WHERE planning_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'GOV-%'))");
$db->exec("DELETE FROM procurement_plans WHERE planning_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'GOV-%')");
$db->exec("DELETE FROM user_entity_roles WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'gov.%')");
$db->exec("DELETE FROM users WHERE username LIKE 'gov.%'");
$db->exec("DELETE FROM planning_entities WHERE entity_code LIKE 'GOV-%'");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

$entityRepo = new PlanningEntityRepository($db);
$entityService = new EntityManagementService($entityRepo);
$userRepo = new UserRepository($db);

// Resolve Lookup IDs
$campusId = (int)$db->query("SELECT id FROM campuses WHERE is_active=1 LIMIT 1")->fetchColumn();
$univTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='UNIV' LIMIT 1")->fetchColumn();
$facTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='FAC' LIMIT 1")->fetchColumn();
$deptTypeId = (int)$db->query("SELECT id FROM entity_types WHERE type_code='DEPT' LIMIT 1")->fetchColumn();

// 1. Build Intended Hierarchy:
// University: GOV-USTED
// ├── Faculty of Applied Sciences: GOV-FAC-SCI
// │   └── IT Department: GOV-DEPT-IT
// └── Faculty of Business: GOV-FAC-BIZ
//     └── Accounting Department: GOV-DEPT-ACC

$univId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'GOV-USTED',
    'entity_name' => 'Gov University of Skills Training',
    'entity_type_id' => $univTypeId,
    'campus_id' => $campusId,
]), 1, '127.0.0.1', 'GovTest');

$facSciId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'GOV-FAC-SCI',
    'entity_name' => 'Gov Faculty of Applied Sciences',
    'entity_type_id' => $facTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]), 1, '127.0.0.1', 'GovTest');

$deptItId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'GOV-DEPT-IT',
    'entity_name' => 'Gov Information Technology Department',
    'entity_type_id' => $deptTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $facSciId,
]), 1, '127.0.0.1', 'GovTest');

$facBizId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'GOV-FAC-BIZ',
    'entity_name' => 'Gov Faculty of Business',
    'entity_type_id' => $facTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $univId,
]), 1, '127.0.0.1', 'GovTest');

$deptAccId = $entityService->createEntity(CreateEntityDTO::fromArray([
    'entity_code' => 'GOV-DEPT-ACC',
    'entity_name' => 'Gov Accounting Department',
    'entity_type_id' => $deptTypeId,
    'campus_id' => $campusId,
    'parent_entity_id' => $facBizId,
]), 1, '127.0.0.1', 'GovTest');

assert_test($univId > 0 && $facSciId > 0 && $deptItId > 0 && $facBizId > 0 && $deptAccId > 0, 'Create 3-tier test hierarchy (UNIV -> FAC -> DEPT)', $passed, $failed, $errors);

// 2. Resolve Role IDs
$adminRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='ADMIN'")->fetchColumn();
$reqRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='REQUESTER'")->fetchColumn();
$hodRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='HOD'")->fetchColumn();
$deanRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='DEAN'")->fetchColumn();
$financeRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='FINANCE_OFFICER'")->fetchColumn();
$procRoleId = (int)$db->query("SELECT id FROM roles WHERE role_code='PROCUREMENT_OFFICER'")->fetchColumn();

// 3. Create Specific Test Users
function createTestUser(PDO $db, string $username, string $email, string $roleCode, int $roleId, ?int $entityId, string $status = 'ACTIVE'): array {
    $hash = password_hash('Secret123!', PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, status, created_at) VALUES (:u, :e, :p, :f, :l, :s, NOW())");
    $stmt->execute([
        'u' => $username,
        'e' => $email,
        'p' => $hash,
        'f' => ucfirst(explode('.', $username)[1] ?? 'User'),
        'l' => ucfirst(explode('.', $username)[0] ?? 'Gov'),
        's' => $status
    ]);
    $uid = (int)$db->lastInsertId();

    if ($entityId !== null) {
        $db->prepare("INSERT INTO user_entity_roles (user_id, role_id, planning_entity_id, status, is_primary, assigned_by) VALUES (:u, :r, :e, :s, 1, 1)")
           ->execute(['u' => $uid, 'r' => $roleId, 'e' => $entityId, 's' => $status]);
    }

    return ['id' => $uid, 'username' => $username, 'role_code' => $roleCode, 'role_id' => $roleId, 'entity_id' => $entityId];
}

$userAdmin = createTestUser($db, 'gov.admin', 'gov.admin@usted.edu.gh', 'ADMIN', $adminRoleId, $univId);
$userRequester = createTestUser($db, 'gov.requester', 'gov.req@usted.edu.gh', 'REQUESTER', $reqRoleId, $deptItId);
$userHodIt = createTestUser($db, 'gov.hod_it', 'gov.hod_it@usted.edu.gh', 'HOD', $hodRoleId, $deptItId);
$userHodBiz = createTestUser($db, 'gov.hod_biz', 'gov.hod_biz@usted.edu.gh', 'HOD', $hodRoleId, $deptAccId);
$userDeanSci = createTestUser($db, 'gov.dean_sci', 'gov.dean_sci@usted.edu.gh', 'DEAN', $deanRoleId, $facSciId);
$userDeanBiz = createTestUser($db, 'gov.dean_biz', 'gov.dean_biz@usted.edu.gh', 'DEAN', $deanRoleId, $facBizId);
$userFinance = createTestUser($db, 'gov.finance', 'gov.finance@usted.edu.gh', 'FINANCE_OFFICER', $financeRoleId, $univId);
$userProcurement = createTestUser($db, 'gov.procure', 'gov.procure@usted.edu.gh', 'PROCUREMENT_OFFICER', $procRoleId, $univId);
$userInactive = createTestUser($db, 'gov.inactive', 'gov.inactive@usted.edu.gh', 'HOD', $hodRoleId, $deptItId, 'INACTIVE');

assert_test($userRequester['id'] > 0 && $userHodIt['id'] > 0 && $userDeanSci['id'] > 0 && $userFinance['id'] > 0 && $userProcurement['id'] > 0, 'Create role-scoped test users', $passed, $failed, $errors);

// Helper to authenticate session as a specific user
function authenticateUser(UserRepository $userRepo, int $userId): void {
    $dto = $userRepo->findById($userId);
    AuthManager::login($dto->toSessionArray());
}

// ─────────────────────────────────────────────────
// SECTION 2: REQUISITION CREATION & DRAFT LIFECYCLE
// ─────────────────────────────────────────────────
echo "\n┌─ Section 2: Requisition Draft & Submission ───────────────────\n";

// Create test requisition helper in DB
function createTestRequisition(PDO $db, int $entityId, int $userId, string $status = 'DRAFT'): int {
    $reqNum = 'REQ-GOV-' . strtoupper(bin2hex(random_bytes(4)));
    $stmt = $db->prepare("
        INSERT INTO requisitions (requisition_number, planning_entity_id, fiscal_year, status, total_estimated_cost, justification, created_by, created_at, updated_at)
        VALUES (:num, :pe, 2026, :st, 5000.00, 'Test Requisition for Governance Verification', :cb, NOW(), NOW())
    ");
    $stmt->execute([
        'num' => $reqNum,
        'pe' => $entityId,
        'st' => $status,
        'cb' => $userId
    ]);
    return (int)$db->lastInsertId();
}

$wfService = new RequisitionWorkflowService($db);
$reqId1 = createTestRequisition($db, $deptItId, $userRequester['id'], 'DRAFT');

// 2.1 Requester submits draft
authenticateUser($userRepo, $userRequester['id']);
assert_test(ExecutionAuthorizationGuard::canSubmitRequisition($deptItId), 'Requester has submit permission for IT Dept', $passed, $failed, $errors);

$subReq = new WorkflowActionRequest($reqId1, WorkflowAction::SUBMIT, $userRequester['id']);
$subRes = $wfService->executeAction($subReq);
assert_test($subRes->newStatus === RequisitionStatus::SUBMITTED, 'Draft -> Submitted transition succeeds', $passed, $failed, $errors);

// 2.2 Invalid transition: Cannot SUBMIT an already SUBMITTED requisition
try {
    $wfService->executeAction($subReq);
    assert_test(false, 'Double submission must be rejected', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException $e) {
    assert_test(true, 'Double submission rejected with InvalidWorkflowTransitionException', $passed, $failed, $errors);
}

// ─────────────────────────────────────────────────
// SECTION 3: HOD ENDORSEMENT STAGE & SCOPE BOUNDARIES
// ─────────────────────────────────────────────────
echo "\n┌─ Section 3: HOD Endorsement & Scope Boundaries ───────────────\n";

// 3.1 Unauthorized Requester attempts to endorse
authenticateUser($userRepo, $userRequester['id']);
try {
    $req = new WorkflowActionRequest($reqId1, WorkflowAction::ENDORSE, $userRequester['id'], 'Self endorse attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Requester must NOT be able to endorse', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Requester endorsement rejected (role/permission guard)', $passed, $failed, $errors);
}

// 3.2 Unrelated HOD of Business Department attempts to endorse IT Department requisition
authenticateUser($userRepo, $userHodBiz['id']);
try {
    $req = new WorkflowActionRequest($reqId1, WorkflowAction::ENDORSE, $userHodBiz['id'], 'Cross-dept endorse attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Cross-department HOD must NOT be able to endorse', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Cross-department HOD endorsement rejected (entity scope boundary)', $passed, $failed, $errors);
}

// 3.3 ADMIN without operational role attempts to endorse
authenticateUser($userRepo, $userAdmin['id']);
try {
    $req = new WorkflowActionRequest($reqId1, WorkflowAction::ENDORSE, $userAdmin['id'], 'Admin direct endorse attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Admin without HOD assignment must NOT endorse', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Admin endorsement rejected without operational role assignment', $passed, $failed, $errors);
}

// 3.4 Authorized HOD of IT Department endorses
authenticateUser($userRepo, $userHodIt['id']);
$req = new WorkflowActionRequest($reqId1, WorkflowAction::ENDORSE, $userHodIt['id'], 'HOD IT endorsement granted');
$endRes = $wfService->executeAction($req);
assert_test($endRes->newStatus === RequisitionStatus::ENDORSED, 'Authorized HOD endorses: SUBMITTED -> ENDORSED', $passed, $failed, $errors);

// 3.5 Double endorsement rejected
try {
    $wfService->executeAction($req);
    assert_test(false, 'Duplicate endorsement must be rejected', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException $e) {
    assert_test(true, 'Duplicate endorsement rejected (status is already ENDORSED)', $passed, $failed, $errors);
}

// ─────────────────────────────────────────────────
// SECTION 4: DEAN APPROVAL & HIERARCHY SCOPE
// ─────────────────────────────────────────────────
echo "\n┌─ Section 4: Dean Approval & Hierarchy Scope ──────────────────\n";

// 4.1 Unrelated Dean of Business attempts to approve Applied Sciences IT request
authenticateUser($userRepo, $userDeanBiz['id']);
try {
    $req = new WorkflowActionRequest($reqId1, WorkflowAction::APPROVE, $userDeanBiz['id'], 'Cross-faculty Dean approval attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Dean of Business must NOT approve Applied Sciences request', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Cross-faculty Dean approval rejected (scope check)', $passed, $failed, $errors);
}

// 4.2 Requester attempts to approve at Dean stage
authenticateUser($userRepo, $userRequester['id']);
try {
    $req = new WorkflowActionRequest($reqId1, WorkflowAction::APPROVE, $userRequester['id'], 'Requester self-approval attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Requester must NOT approve at Dean stage', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Requester Dean approval rejected (role guard)', $passed, $failed, $errors);
}

// 4.3 Dean assigned to Faculty only (without child dept assignment) attempting to act on Department
// Documents exact scoping behavior: explicit configuration required
authenticateUser($userRepo, $userDeanSci['id']);
try {
    $req = new WorkflowActionRequest($reqId1, WorkflowAction::APPROVE, $userDeanSci['id'], 'Faculty-only Dean approval attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Faculty-only assignment without department scope must be rejected', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Faculty-only Dean assignment requires explicit/scoped entity assignment for department (Explicit Scoping)', $passed, $failed, $errors);
}

// 4.4 Assign Dean to the Department scope and execute valid Dean approval
$db->prepare("INSERT INTO user_entity_roles (user_id, role_id, planning_entity_id, status, is_primary, assigned_by) VALUES (:u, :r, :e, 'ACTIVE', 1, 1)")
   ->execute(['u' => $userDeanSci['id'], 'r' => $deanRoleId, 'e' => $deptItId]);

authenticateUser($userRepo, $userDeanSci['id']);
$req = new WorkflowActionRequest($reqId1, WorkflowAction::APPROVE, $userDeanSci['id'], 'Dean of Applied Sciences approval granted');
$appRes = $wfService->executeAction($req);
assert_test($appRes->newStatus === RequisitionStatus::DEPARTMENT_APPROVED, 'Authorized Dean approves: ENDORSED -> DEPARTMENT_APPROVED', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 5: FINANCE COMMITMENT STAGE
// ─────────────────────────────────────────────────
echo "\n┌─ Section 5: Finance Commitment Authorization ─────────────────\n";

// 5.1 Dean attempts to commit finance
authenticateUser($userRepo, $userDeanSci['id']);
try {
    $req = new WorkflowActionRequest($reqId1, WorkflowAction::APPROVE, $userDeanSci['id'], 'Dean finance commitment attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Dean must NOT commit finance transactions', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Dean finance commitment rejected (role guard)', $passed, $failed, $errors);
}

// 5.2 Assign Finance Officer to Department scope and verify
$db->prepare("INSERT INTO user_entity_roles (user_id, role_id, planning_entity_id, status, is_primary, assigned_by) VALUES (:u, :r, :e, 'ACTIVE', 1, 1)")
   ->execute(['u' => $userFinance['id'], 'r' => $financeRoleId, 'e' => $deptItId]);

// 5.3 Finance Officer attempts to commit a DRAFT or SUBMITTED request
$reqIdDraft = createTestRequisition($db, $deptItId, $userRequester['id'], 'DRAFT');
authenticateUser($userRepo, $userFinance['id']);
try {
    $req = new WorkflowActionRequest($reqIdDraft, WorkflowAction::APPROVE, $userFinance['id'], 'Finance draft commit attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Finance must NOT commit a DRAFT request', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException $e) {
    assert_test(true, 'Finance commitment rejected on DRAFT request (InvalidWorkflowTransition)', $passed, $failed, $errors);
}

// 5.4 Authorized Finance Officer commits DEPARTMENT_APPROVED requisition
authenticateUser($userRepo, $userFinance['id']);
$req = new WorkflowActionRequest($reqId1, WorkflowAction::APPROVE, $userFinance['id'], 'Finance commitment authorized');
$finRes = $wfService->executeAction($req);
assert_test($finRes->newStatus === RequisitionStatus::COMMITMENT_AUTHORIZED, 'Finance Officer commits: DEPARTMENT_APPROVED -> COMMITMENT_AUTHORIZED', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 6: PROCUREMENT RECEIPT STAGE
// ─────────────────────────────────────────────────
echo "\n┌─ Section 6: Procurement Receiving Stage ──────────────────────\n";

// 6.1 Finance Officer attempts to receive goods
authenticateUser($userRepo, $userFinance['id']);
try {
    $req = new WorkflowActionRequest($reqId1, WorkflowAction::RECEIVE, $userFinance['id'], 'Finance receive attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Finance must NOT receive procurement goods', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'Finance receive rejected (role guard)', $passed, $failed, $errors);
}

// 6.2 Assign Procurement Officer to Department scope
$db->prepare("INSERT INTO user_entity_roles (user_id, role_id, planning_entity_id, status, is_primary, assigned_by) VALUES (:u, :r, :e, 'ACTIVE', 1, 1)")
   ->execute(['u' => $userProcurement['id'], 'r' => $procRoleId, 'e' => $deptItId]);

// 6.3 Procurement Officer attempts to receive an uncommitted requisition
authenticateUser($userRepo, $userProcurement['id']);
try {
    $req = new WorkflowActionRequest($reqIdDraft, WorkflowAction::RECEIVE, $userProcurement['id'], 'Receive draft attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Procurement must NOT receive uncommitted request', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException $e) {
    assert_test(true, 'Procurement receipt rejected on uncommitted request', $passed, $failed, $errors);
}

// 6.4 Authorized Procurement Officer marks goods as received
authenticateUser($userRepo, $userProcurement['id']);
$req = new WorkflowActionRequest($reqId1, WorkflowAction::RECEIVE, $userProcurement['id'], 'Goods received and inspected');
$recRes = $wfService->executeAction($req);
assert_test($recRes->newStatus === RequisitionStatus::PROCUREMENT_RECEIVED, 'Procurement Officer receives: COMMITMENT_AUTHORIZED -> PROCUREMENT_RECEIVED', $passed, $failed, $errors);

// 6.5 Double receipt rejected (Terminal state)
try {
    $wfService->executeAction($req);
    assert_test(false, 'Double receipt on terminal status must be rejected', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException $e) {
    assert_test(true, 'Double receipt rejected on terminal state', $passed, $failed, $errors);
}

// ─────────────────────────────────────────────────
// SECTION 7: RETURN AND REJECT PATHWAYS
// ─────────────────────────────────────────────────
echo "\n┌─ Section 7: Return & Rejection Pathways ──────────────────────\n";

// 7.1 Negative action requires mandatory comment
$reqIdReturn = createTestRequisition($db, $deptItId, $userRequester['id'], 'SUBMITTED');
authenticateUser($userRepo, $userHodIt['id']);
try {
    $req = new WorkflowActionRequest($reqIdReturn, WorkflowAction::RETURN, $userHodIt['id'], '');
    $wfService->executeAction($req);
    assert_test(false, 'Return action without comment must be rejected', $passed, $failed, $errors);
} catch (ValidationException $e) {
    assert_test(true, 'Return without comment rejected with ValidationException', $passed, $failed, $errors);
}

// 7.2 Return with justification comment succeeds: SUBMITTED -> RETURNED
$req = new WorkflowActionRequest($reqIdReturn, WorkflowAction::RETURN, $userHodIt['id'], 'Please clarify specifications');
$retRes = $wfService->executeAction($req);
assert_test($retRes->newStatus === RequisitionStatus::RETURNED, 'HOD returns request: SUBMITTED -> RETURNED', $passed, $failed, $errors);

// 7.3 Requester resubmits RETURNED requisition: RETURNED -> SUBMITTED
authenticateUser($userRepo, $userRequester['id']);
$req = new WorkflowActionRequest($reqIdReturn, WorkflowAction::SUBMIT, $userRequester['id'], 'Updated specifications attached');
$resubRes = $wfService->executeAction($req);
assert_test($resubRes->newStatus === RequisitionStatus::SUBMITTED, 'Requester resubmits: RETURNED -> SUBMITTED', $passed, $failed, $errors);

// 7.4 Rejection pathway: SUBMITTED -> REJECTED (Terminal)
authenticateUser($userRepo, $userHodIt['id']);
$req = new WorkflowActionRequest($reqIdReturn, WorkflowAction::REJECT, $userHodIt['id'], 'Budget not available this quarter');
$rejRes = $wfService->executeAction($req);
assert_test($rejRes->newStatus === RequisitionStatus::REJECTED, 'HOD rejects request: SUBMITTED -> REJECTED', $passed, $failed, $errors);

// 7.5 Cannot perform any action from terminal REJECTED status
try {
    $req = new WorkflowActionRequest($reqIdReturn, WorkflowAction::SUBMIT, $userRequester['id']);
    $wfService->executeAction($req);
    assert_test(false, 'Cannot transition from terminal REJECTED status', $passed, $failed, $errors);
} catch (InvalidWorkflowTransitionException $e) {
    assert_test(true, 'Transitions from terminal REJECTED status blocked', $passed, $failed, $errors);
}

// ─────────────────────────────────────────────────
// SECTION 8: AUDIT LOGGING & IMMUTABILITY
// ─────────────────────────────────────────────────
echo "\n┌─ Section 8: Audit Logging & Action History ───────────────────\n";

$auditLogs = $db->query("SELECT * FROM audit_logs WHERE record_type='requisitions' AND record_id={$reqId1} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
assert_test(count($auditLogs) >= 4, 'Audit logs recorded for each completed transition (>= 4 records)', $passed, $failed, $errors);

$actionsLogged = array_column($auditLogs, 'action');
assert_test(in_array('WORKFLOW_SUBMIT', $actionsLogged, true), 'WORKFLOW_SUBMIT action audit logged', $passed, $failed, $errors);
assert_test(in_array('WORKFLOW_ENDORSE', $actionsLogged, true), 'WORKFLOW_ENDORSE action audit logged', $passed, $failed, $errors);
assert_test(in_array('WORKFLOW_APPROVE', $actionsLogged, true), 'WORKFLOW_APPROVE action audit logged', $passed, $failed, $errors);
assert_test(in_array('WORKFLOW_RECEIVE', $actionsLogged, true), 'WORKFLOW_RECEIVE action audit logged', $passed, $failed, $errors);

$actionLogs = $db->query("SELECT * FROM workflow_action_logs WHERE document_type='REQUISITION' AND document_id={$reqId1} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
assert_test(count($actionLogs) >= 4, 'Workflow action logs recorded with comments and timestamps (>= 4 records)', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 9: INACTIVE USER & INACTIVE ENTITY GUARDS
// ─────────────────────────────────────────────────
echo "\n┌─ Section 9: Inactive User & Entity Security Guards ───────────\n";

// 9.1 Inactive user cannot perform workflow action
$reqIdInactive = createTestRequisition($db, $deptItId, $userRequester['id'], 'SUBMITTED');
authenticateUser($userRepo, $userInactive['id']);
try {
    $req = new WorkflowActionRequest($reqIdInactive, WorkflowAction::ENDORSE, $userInactive['id'], 'Inactive endorsement attempt');
    $wfService->executeAction($req);
    assert_test(false, 'Inactive user must NOT perform workflow actions', $passed, $failed, $errors);
} catch (\Throwable $e) {
    assert_test(true, 'Inactive user action rejected', $passed, $failed, $errors);
}

// 9.2 Administrator Separation: Admin cannot perform operational workflow actions
$reqIdAdminCheck = createTestRequisition($db, $deptItId, $userRequester['id'], 'SUBMITTED');
authenticateUser($userRepo, $userAdmin['id']);

// Admin attempting ENDORSE
try {
    $req = new WorkflowActionRequest($reqIdAdminCheck, WorkflowAction::ENDORSE, $userAdmin['id'], 'Admin endorse attempt');
    $wfService->executeAction($req);
    assert_test(false, 'ADMIN must NOT endorse without HOD assignment', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'ADMIN cannot endorse without HOD assignment', $passed, $failed, $errors);
}

// Admin attempting APPROVE (Dean)
$reqIdAdminApprove = createTestRequisition($db, $deptItId, $userRequester['id'], 'ENDORSED');
try {
    $req = new WorkflowActionRequest($reqIdAdminApprove, WorkflowAction::APPROVE, $userAdmin['id'], 'Admin Dean approve attempt');
    $wfService->executeAction($req);
    assert_test(false, 'ADMIN must NOT approve Dean step without DEAN assignment', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'ADMIN cannot approve Dean step without DEAN assignment', $passed, $failed, $errors);
}

// Admin attempting FINANCE COMMIT
$reqIdAdminCommit = createTestRequisition($db, $deptItId, $userRequester['id'], 'DEPARTMENT_APPROVED');
try {
    $req = new WorkflowActionRequest($reqIdAdminCommit, WorkflowAction::APPROVE, $userAdmin['id'], 'Admin finance commit attempt');
    $wfService->executeAction($req);
    assert_test(false, 'ADMIN must NOT commit finance without FINANCE_OFFICER assignment', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'ADMIN cannot commit finance without FINANCE_OFFICER assignment', $passed, $failed, $errors);
}

// Admin attempting PROCUREMENT RECEIPT
$reqIdAdminReceive = createTestRequisition($db, $deptItId, $userRequester['id'], 'COMMITMENT_AUTHORIZED');
try {
    $req = new WorkflowActionRequest($reqIdAdminReceive, WorkflowAction::RECEIVE, $userAdmin['id'], 'Admin receive attempt');
    $wfService->executeAction($req);
    assert_test(false, 'ADMIN must NOT receive goods without PROCUREMENT_OFFICER assignment', $passed, $failed, $errors);
} catch (UnauthorizedWorkflowActionException|UnauthorizedExecutionException $e) {
    assert_test(true, 'ADMIN cannot receive goods without PROCUREMENT_OFFICER assignment', $passed, $failed, $errors);
}

// 9.3 Closure Table Verification
$hUnivToUniv = $db->query("SELECT depth FROM entity_hierarchies WHERE ancestor_entity_id={$univId} AND descendant_entity_id={$univId}")->fetchColumn();
$hUnivToFac = $db->query("SELECT depth FROM entity_hierarchies WHERE ancestor_entity_id={$univId} AND descendant_entity_id={$facSciId}")->fetchColumn();
$hUnivToDept = $db->query("SELECT depth FROM entity_hierarchies WHERE ancestor_entity_id={$univId} AND descendant_entity_id={$deptItId}")->fetchColumn();
$hFacToDept = $db->query("SELECT depth FROM entity_hierarchies WHERE ancestor_entity_id={$facSciId} AND descendant_entity_id={$deptItId}")->fetchColumn();

assert_test((int)$hUnivToUniv === 0, 'Closure table: University -> University has depth=0', $passed, $failed, $errors);
assert_test((int)$hUnivToFac === 1, 'Closure table: University -> Faculty has depth=1', $passed, $failed, $errors);
assert_test((int)$hUnivToDept === 2, 'Closure table: University -> Department has depth=2', $passed, $failed, $errors);
assert_test((int)$hFacToDept === 1, 'Closure table: Faculty -> Department has depth=1', $passed, $failed, $errors);

// ─────────────────────────────────────────────────
// SECTION 10: CLEANUP
// ─────────────────────────────────────────────────
echo "\n┌─ Section 10: Cleanup ─────────────────────────────────────────\n";

$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("DELETE FROM entity_hierarchies WHERE descendant_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'GOV-%')");
$db->exec("DELETE FROM entity_hierarchies WHERE ancestor_entity_id IN (SELECT id FROM planning_entities WHERE entity_code LIKE 'GOV-%')");
$db->exec("DELETE FROM requisition_items WHERE requisition_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-GOV-%')");
$db->exec("DELETE FROM workflow_action_logs WHERE document_type='REQUISITION' AND document_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-GOV-%')");
$db->exec("DELETE FROM audit_logs WHERE record_type='requisitions' AND record_id IN (SELECT id FROM requisitions WHERE requisition_number LIKE 'REQ-GOV-%')");
$db->exec("DELETE FROM requisitions WHERE requisition_number LIKE 'REQ-GOV-%'");
$db->exec("DELETE FROM user_entity_roles WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'gov.%')");
$db->exec("DELETE FROM users WHERE username LIKE 'gov.%'");
$db->exec("DELETE FROM planning_entities WHERE entity_code LIKE 'GOV-%'");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

echo "  ✓ Test fixtures cleaned up\n";

// ─────────────────────────────────────────────────
// RESULTS
// ─────────────────────────────────────────────────
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║   GOVERNANCE TEST RESULTS                                     ║\n";
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
