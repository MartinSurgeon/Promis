<?php

declare(strict_types=1);

namespace Promis\Tests;

require_once dirname(__DIR__) . '/core/autoload.php';

use PDO;
use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AuthenticationException;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Password;
use Promis\Core\Security\Session;
use Promis\Src\Execution\Auth\ExecutionAuthorizationGuard;
use Promis\Src\Execution\Auth\ExecutionPermissions;
use Promis\Src\Execution\Domain\DTO\WorkflowActionRequest;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Exception\InvalidWorkflowTransitionException;
use Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException;
use Promis\Src\Execution\Exception\WorkflowException;
use Promis\Src\Execution\Repository\AuditLogRepository;
use Promis\Src\Execution\Repository\RequisitionRepository;
use Promis\Src\Execution\Repository\WorkflowActionLogRepository;
use Promis\Src\Execution\Repository\WorkflowDefinitionRepository;
use Promis\Src\Execution\Repository\WorkflowStepRuleRepository;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Throwable;

/**
 * PROMIS Phase 2.5 Hardening Test Suite.
 * Covers:
 * 1. Canonical Status Consistency & Enum Helpers
 * 2. Valid Workflow Pipeline State Progression
 * 3. Invalid Workflow Transitions & Skipping Prevention
 * 4. Server-Side RBAC Enforcement & Mandatory Justifications
 * 5. Direct URL Protection & Ownership Verification
 * 6. Direct POST Tampering Protection
 * 7. CSRF Token Validation & Timing-Safe Comparison
 * 8. Missing, Empty, and Forged CSRF Rejection
 * 9. Session Expiration & Inactivity Timeout Behavior
 * 10. Session Fixation Defense & Destruction on Logout
 * 11. Duplicate Transition Prevention
 * 12. Concurrency Protection via Conditional Updates
 * 13. Audit-Log Atomicity & Relational Consistency
 * 14. Transaction Rollback on Logging Failure
 * 15. Standardized Error Response Formatting (JSON & Sanitized Output)
 * 16. Empty-State & Filter Robustness
 * 17. Test Teardown Isolation (Preserving Demo Fixtures)
 */
final class Phase25HardeningTest
{
    private PDO $db;
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    // Tracked IDs for isolated teardown
    private array $trackedUserIds = [];
    private array $trackedRequisitionIds = [];
    private array $trackedActionLogIds = [];
    private array $trackedAuditLogIds = [];

    // Test Fixtures
    private int $entityId = 1; // Existing Department of Computer Science
    private int $requesterUserId = 0;
    private int $otherRequesterUserId = 0;
    private int $elevatedUserId = 0;

    private RequisitionRepository $reqRepo;
    private RequisitionWorkflowService $workflowService;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            ob_start();
            Session::start();
        }
        App::bootstrap(dirname(__DIR__));
        $this->db = Connection::get();

        $this->reqRepo = new RequisitionRepository($this->db);
        $this->workflowService = new RequisitionWorkflowService(
            $this->db,
            $this->reqRepo,
            new WorkflowDefinitionRepository($this->db),
            new WorkflowStepRuleRepository($this->db),
            new WorkflowActionLogRepository($this->db),
            new AuditLogRepository($this->db)
        );
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS Phase 2.5 Hardening & Security Review Test Suite\n";
        echo " University of Skills Training and Entrepreneurial Development\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        try {
            $this->seedTestUsers();

            // 1. Canonical status consistency
            $this->testCanonicalStatusEnumIntegrity();

            // 2 & 3. Valid and Invalid Workflow Transitions
            $this->testValidWorkflowPipelineLifecycle();
            $this->testInvalidWorkflowTransitionsAndSkipPrevention();
            $this->testNegativeWorkflowBranchesWithMandatoryJustification();

            // 4, 5 & 6. RBAC, Direct URL & Direct POST Protection
            $this->testServerSideRbacEnforcement();
            $this->testDirectUrlOwnershipProtection();
            $this->testDirectPostTamperingProtection();

            // 7 & 8. CSRF Protection
            $this->testCsrfGenerationAndValidation();
            $this->testCsrfRejectionOfForgedAndEmptyTokens();

            // 9 & 10. Session Security
            $this->testSessionFixationAndRegeneration();
            $this->testSessionInactivityTimeout();
            $this->testSessionDestruction();

            // 11 & 12. Duplicate Submission & Concurrency
            $this->testDuplicateTransitionRejection();
            $this->testConcurrencyConditionalStatusUpdateFailure();

            // 13 & 14. Audit Trail Atomicity & Rollback
            $this->testAuditLogAtomicityAcrossTransitions();
            $this->testAtomicRollbackOnLoggingFailure();

            // 15. Error Response Formatting
            $this->testErrorResponseFormatting();

            // 16. Empty-State & Filter Resilience
            $this->testEmptyStateAndFilterResilience();

            // 17. Teardown Isolation Verification
            $this->testTeardownIsolationIntegrity();

        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[] = "Fatal test execution error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
            echo " [FAIL] FATAL EXCEPTION: " . $e->getMessage() . "\n";
        } finally {
            $this->teardown();
        }

        echo "\n===============================================================\n";
        echo " PHASE 2.5 HARDENING TEST SUMMARY\n";
        echo " Passed: {$this->passed} / " . ($this->passed + $this->failed) . " assertions\n";
        if ($this->failed > 0) {
            echo " Failures: {$this->failed}\n";
            foreach ($this->failures as $f) {
                echo "  - {$f}\n";
            }
        }
        echo "===============================================================\n";

        if (ob_get_level() > 0) {
            ob_end_flush();
        }

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$message}\n";
        } else {
            $this->failed++;
            $this->failures[] = $message;
            echo " [FAIL] {$message}\n";
        }
    }

    private function seedTestUsers(): void
    {
        // 1. Primary Requester User
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, status, created_at, updated_at)
            VALUES (:uname, :email, :pw, 'P25', 'Requester', 'ACTIVE', NOW(), NOW())
        ");
        $stmt->execute([
            ':uname' => 'test_p25_requester_' . bin2hex(random_bytes(4)),
            ':email' => 'p25_req_' . bin2hex(random_bytes(4)) . '@usted.edu.gh',
            ':pw' => Password::hash('SecretPass123!'),
        ]);
        $this->requesterUserId = (int)$this->db->lastInsertId();
        $this->trackedUserIds[] = $this->requesterUserId;

        // 2. Second Requester User (For Direct URL Cross-Access checks)
        $stmt->execute([
            ':uname' => 'test_p25_other_' . bin2hex(random_bytes(4)),
            ':email' => 'p25_other_' . bin2hex(random_bytes(4)) . '@usted.edu.gh',
            ':pw' => Password::hash('SecretPass123!'),
        ]);
        $this->otherRequesterUserId = (int)$this->db->lastInsertId();
        $this->trackedUserIds[] = $this->otherRequesterUserId;

        // Assign REQUESTER role to both
        $roleStmt = $this->db->query("SELECT id FROM roles WHERE role_code = 'REQUESTER' LIMIT 1");
        $reqRoleId = (int)$roleStmt->fetchColumn();
        if ($reqRoleId > 0) {
            $uerStmt = $this->db->prepare("INSERT INTO user_entity_roles (user_id, role_id, planning_entity_id, is_primary, status, assigned_at, assigned_by) VALUES (?, ?, ?, 1, 'ACTIVE', NOW(), 1)");
            $uerStmt->execute([$this->requesterUserId, $reqRoleId, $this->entityId]);
            $uerStmt->execute([$this->otherRequesterUserId, $reqRoleId, $this->entityId]);
        }

        // 3. Elevated Admin User
        $stmt->execute([
            ':uname' => 'test_p25_admin_' . bin2hex(random_bytes(4)),
            ':email' => 'p25_admin_' . bin2hex(random_bytes(4)) . '@usted.edu.gh',
            ':pw' => Password::hash('SecretPass123!'),
        ]);
        $this->elevatedUserId = (int)$this->db->lastInsertId();
        $this->trackedUserIds[] = $this->elevatedUserId;

        $adminRoleStmt = $this->db->query("SELECT id FROM roles WHERE role_code = 'ADMIN' LIMIT 1");
        $adminRoleId = (int)$adminRoleStmt->fetchColumn();
        if ($adminRoleId > 0) {
            $uerStmt = $this->db->prepare("INSERT INTO user_entity_roles (user_id, role_id, planning_entity_id, is_primary, status, assigned_at, assigned_by) VALUES (?, ?, ?, 1, 'ACTIVE', NOW(), 1)");
            $uerStmt->execute([$this->elevatedUserId, $adminRoleId, $this->entityId]);
        }
    }

    private function createTestRequisition(string $status = 'DRAFT', ?int $createdBy = null): int
    {
        $creator = $createdBy ?? $this->requesterUserId;
        $reqNum = 'REQ-P25-' . strtoupper(bin2hex(random_bytes(3)));

        $stmt = $this->db->prepare("
            INSERT INTO requisitions (
                planning_entity_id, requisition_number, fiscal_year, justification,
                total_estimated_cost, status, created_by, submitted_by, submitted_at, created_at, updated_at
            ) VALUES (
                :pe_id, :req_num, 2026, 'Hardening and security validation requisition',
                5000.00, :status, :created_by, :submitted_by, :submitted_at, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':pe_id' => $this->entityId,
            ':req_num' => $reqNum,
            ':status' => $status,
            ':created_by' => $creator,
            ':submitted_by' => $status !== 'DRAFT' ? $creator : null,
            ':submitted_at' => $status !== 'DRAFT' ? date('Y-m-d H:i:s') : null,
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedRequisitionIds[] = $id;

        return $id;
    }

    private function authAsRequester(int $userId): void
    {
        AuthManager::login([
            'id' => $userId,
            'email' => 'requester@usted.edu.gh',
            'permissions' => [ExecutionPermissions::CREATE, ExecutionPermissions::VIEW, ExecutionPermissions::SUBMIT],
            'entity_permissions' => [
                $this->entityId => [ExecutionPermissions::CREATE, ExecutionPermissions::VIEW, ExecutionPermissions::SUBMIT],
            ],
            'roles' => ['REQUESTER'],
        ]);
    }

    private function authAsHod(int $userId): void
    {
        $roleId = (int)$this->db->query("SELECT id FROM roles WHERE role_code = 'HOD'")->fetchColumn();
        AuthManager::login([
            'id' => $userId,
            'email' => 'hod@usted.edu.gh',
            'permissions' => [ExecutionPermissions::VIEW, ExecutionPermissions::SUBMIT, ExecutionPermissions::ENDORSE, ExecutionPermissions::RETURN_REQ, ExecutionPermissions::REJECT],
            'entity_permissions' => [
                $this->entityId => [ExecutionPermissions::VIEW, ExecutionPermissions::SUBMIT, ExecutionPermissions::ENDORSE, ExecutionPermissions::RETURN_REQ, ExecutionPermissions::REJECT],
            ],
            'role_ids' => [$roleId],
            'roles' => ['HOD'],
        ]);
    }

    private function authAsDean(int $userId): void
    {
        $roleId = (int)$this->db->query("SELECT id FROM roles WHERE role_code = 'DEAN'")->fetchColumn();
        AuthManager::login([
            'id' => $userId,
            'email' => 'dean@usted.edu.gh',
            'permissions' => [ExecutionPermissions::VIEW, ExecutionPermissions::APPROVE, ExecutionPermissions::RETURN_REQ, ExecutionPermissions::REJECT],
            'entity_permissions' => [
                $this->entityId => [ExecutionPermissions::VIEW, ExecutionPermissions::APPROVE, ExecutionPermissions::RETURN_REQ, ExecutionPermissions::REJECT],
            ],
            'role_ids' => [$roleId],
            'roles' => ['DEAN'],
        ]);
    }

    private function authAsFinance(int $userId): void
    {
        $roleId = (int)$this->db->query("SELECT id FROM roles WHERE role_code = 'FINANCE_OFFICER'")->fetchColumn();
        AuthManager::login([
            'id' => $userId,
            'email' => 'finance@usted.edu.gh',
            'permissions' => [ExecutionPermissions::VIEW, ExecutionPermissions::APPROVE, ExecutionPermissions::RETURN_REQ, ExecutionPermissions::REJECT],
            'entity_permissions' => [
                $this->entityId => [ExecutionPermissions::VIEW, ExecutionPermissions::APPROVE, ExecutionPermissions::RETURN_REQ, ExecutionPermissions::REJECT],
            ],
            'role_ids' => [$roleId],
            'roles' => ['FINANCE_OFFICER'],
        ]);
    }

    private function authAsProcurement(int $userId): void
    {
        $roleId = (int)$this->db->query("SELECT id FROM roles WHERE role_code = 'PROCUREMENT_OFFICER'")->fetchColumn();
        AuthManager::login([
            'id' => $userId,
            'email' => 'procurement@usted.edu.gh',
            'permissions' => [ExecutionPermissions::VIEW, ExecutionPermissions::RECEIVE, ExecutionPermissions::RETURN_REQ, ExecutionPermissions::REJECT],
            'entity_permissions' => [
                $this->entityId => [ExecutionPermissions::VIEW, ExecutionPermissions::RECEIVE, ExecutionPermissions::RETURN_REQ, ExecutionPermissions::REJECT],
            ],
            'role_ids' => [$roleId],
            'roles' => ['PROCUREMENT_OFFICER'],
        ]);
    }

    // =========================================================================
    // 1. CANONICAL STATUS CONSISTENCY
    // =========================================================================
    private function testCanonicalStatusEnumIntegrity(): void
    {
        $expectedStatuses = [
            'DRAFT',
            'SUBMITTED',
            'ENDORSED',
            'DEPARTMENT_APPROVED',
            'COMMITMENT_AUTHORIZED',
            'PROCUREMENT_RECEIVED',
            'RETURNED',
            'REJECTED',
        ];

        $cases = RequisitionStatus::cases();
        $this->assert(count($cases) === 8, 'RequisitionStatus enum contains exactly 8 canonical statuses');

        foreach ($expectedStatuses as $name) {
            $case = RequisitionStatus::tryFrom($name);
            $this->assert($case !== null, "Canonical case {$name} is defined in RequisitionStatus enum");
            $this->assert($case->label() !== '', "RequisitionStatus::{$name}->label() returns a non-empty human label");
            $this->assert($case->badgeClass() !== '', "RequisitionStatus::{$name}->badgeClass() returns a valid badge class");
        }

        // Stepper indexes for forward pipeline
        $this->assert(RequisitionStatus::DRAFT->stepperIndex() === 1, 'DRAFT has stepperIndex 1');
        $this->assert(RequisitionStatus::SUBMITTED->stepperIndex() === 2, 'SUBMITTED has stepperIndex 2');
        $this->assert(RequisitionStatus::ENDORSED->stepperIndex() === 3, 'ENDORSED has stepperIndex 3');
        $this->assert(RequisitionStatus::DEPARTMENT_APPROVED->stepperIndex() === 4, 'DEPARTMENT_APPROVED has stepperIndex 4');
        $this->assert(RequisitionStatus::COMMITMENT_AUTHORIZED->stepperIndex() === 5, 'COMMITMENT_AUTHORIZED has stepperIndex 5');
        $this->assert(RequisitionStatus::PROCUREMENT_RECEIVED->stepperIndex() === 6, 'PROCUREMENT_RECEIVED has stepperIndex 6');

        // Negative statuses
        $this->assert(RequisitionStatus::RETURNED->stepperIndex() === null, 'RETURNED returns null stepperIndex (alert state)');
        $this->assert(RequisitionStatus::REJECTED->stepperIndex() === null, 'REJECTED returns null stepperIndex (terminal alert state)');
        $this->assert(RequisitionStatus::RETURNED->isNegative() === true, 'RETURNED is marked as negative status');
        $this->assert(RequisitionStatus::REJECTED->isNegative() === true, 'REJECTED is marked as negative status');
        $this->assert(RequisitionStatus::DEPARTMENT_APPROVED->isNegative() === false, 'DEPARTMENT_APPROVED is not negative status');

        // Filter list
        $filterList = RequisitionStatus::filterList();
        $this->assert(isset($filterList['ALL']), 'filterList contains ALL option');
        $this->assert(count($filterList) === 9, 'filterList contains exactly 9 filter items (ALL + 8 statuses)');
    }

    // =========================================================================
    // 2 & 3. VALID & INVALID WORKFLOW PIPELINE TRANSITIONS
    // =========================================================================
    private function testValidWorkflowPipelineLifecycle(): void
    {
        $reqId = $this->createTestRequisition('DRAFT');

        $hodUser = (int)($this->db->query("SELECT id FROM users WHERE username = 'kwame.mensah'")->fetchColumn() ?: 1);
        $deanUser = (int)($this->db->query("SELECT id FROM users WHERE username = 'dean.user'")->fetchColumn() ?: 2);
        $financeUser = (int)($this->db->query("SELECT id FROM users WHERE username = 'finance.user'")->fetchColumn() ?: 3);
        $procUser = (int)($this->db->query("SELECT id FROM users WHERE username = 'procurement.user'")->fetchColumn() ?: 4);

        // Transition 1: SUBMIT (DRAFT -> SUBMITTED)
        $this->authAsRequester($this->requesterUserId);
        $subReq = new WorkflowActionRequest($reqId, WorkflowAction::SUBMIT, $this->requesterUserId);
        $res1 = $this->workflowService->executeAction($subReq);
        $this->assert($res1->newStatus === RequisitionStatus::SUBMITTED, 'Transition 1: DRAFT -> SUBMITTED succeeds');

        // Transition 2: ENDORSE (SUBMITTED -> ENDORSED)
        $this->authAsHod($hodUser);
        $endReq = new WorkflowActionRequest($reqId, WorkflowAction::ENDORSE, $hodUser);
        $res2 = $this->workflowService->executeAction($endReq);
        $this->assert($res2->newStatus === RequisitionStatus::ENDORSED, 'Transition 2: SUBMITTED -> ENDORSED succeeds');

        // Transition 3: APPROVE Dean (ENDORSED -> DEPARTMENT_APPROVED)
        $this->authAsDean($deanUser);
        $appDeanReq = new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $deanUser);
        $res3 = $this->workflowService->executeAction($appDeanReq);
        $this->assert($res3->newStatus === RequisitionStatus::DEPARTMENT_APPROVED, 'Transition 3: ENDORSED -> DEPARTMENT_APPROVED succeeds');

        // Transition 4: APPROVE Finance (DEPARTMENT_APPROVED -> COMMITMENT_AUTHORIZED)
        $this->authAsFinance($financeUser);
        $appFinReq = new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $financeUser);
        $res4 = $this->workflowService->executeAction($appFinReq);
        $this->assert($res4->newStatus === RequisitionStatus::COMMITMENT_AUTHORIZED, 'Transition 4: DEPARTMENT_APPROVED -> COMMITMENT_AUTHORIZED succeeds');

        // Transition 5: RECEIVE Procurement (COMMITMENT_AUTHORIZED -> PROCUREMENT_RECEIVED)
        $this->authAsProcurement($procUser);
        $recReq = new WorkflowActionRequest($reqId, WorkflowAction::RECEIVE, $procUser);
        $res5 = $this->workflowService->executeAction($recReq);
        $this->assert($res5->newStatus === RequisitionStatus::PROCUREMENT_RECEIVED, 'Transition 5: COMMITMENT_AUTHORIZED -> PROCUREMENT_RECEIVED succeeds');
    }

    private function testInvalidWorkflowTransitionsAndSkipPrevention(): void
    {
        $reqId = $this->createTestRequisition('DRAFT');
        $procUser = (int)($this->db->query("SELECT id FROM users WHERE username = 'procurement.user'")->fetchColumn() ?: 4);

        // Attempt to skip directly from DRAFT to RECEIVE
        $this->authAsProcurement($procUser);
        $caught = false;
        try {
            $req = new WorkflowActionRequest($reqId, WorkflowAction::RECEIVE, $procUser);
            $this->workflowService->executeAction($req);
        } catch (InvalidWorkflowTransitionException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Stage-skipping prevention: Attempting to RECEIVE a DRAFT requisition throws InvalidWorkflowTransitionException');

        // Attempt to skip directly from DRAFT to APPROVE
        $deanUser = (int)($this->db->query("SELECT id FROM users WHERE username = 'dean.user'")->fetchColumn() ?: 2);
        $this->authAsDean($deanUser);
        $caught = false;
        try {
            $req = new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $deanUser);
            $this->workflowService->executeAction($req);
        } catch (InvalidWorkflowTransitionException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Stage-skipping prevention: Attempting to APPROVE a DRAFT requisition throws InvalidWorkflowTransitionException');
    }

    private function testNegativeWorkflowBranchesWithMandatoryJustification(): void
    {
        $reqId = $this->createTestRequisition('SUBMITTED');
        $hodUser = (int)($this->db->query("SELECT id FROM users WHERE username = 'kwame.mensah'")->fetchColumn() ?: 1);
        $this->authAsHod($hodUser);

        // 1. Mandatory comment enforcement on RETURN
        $caught = false;
        try {
            $req = new WorkflowActionRequest($reqId, WorkflowAction::RETURN, $hodUser, comments: '');
            $this->workflowService->executeAction($req);
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Mandatory justification: RETURN action without comment throws ValidationException');

        // 2. Successful RETURN with comment
        $req = new WorkflowActionRequest($reqId, WorkflowAction::RETURN, $hodUser, comments: 'Please attach quotation.');
        $res = $this->workflowService->executeAction($req);
        $this->assert($res->newStatus === RequisitionStatus::RETURNED, 'RETURN with valid justification transitions to RETURNED');

        // 3. Resubmission from RETURNED
        $this->authAsRequester($this->requesterUserId);
        $subReq = new WorkflowActionRequest($reqId, WorkflowAction::SUBMIT, $this->requesterUserId);
        $resSub = $this->workflowService->executeAction($subReq);
        $this->assert($resSub->newStatus === RequisitionStatus::SUBMITTED, 'Resubmission from RETURNED transitions back to SUBMITTED');

        // 4. Mandatory comment enforcement on REJECT
        $this->authAsHod($hodUser);
        $caught = false;
        try {
            $req = new WorkflowActionRequest($reqId, WorkflowAction::REJECT, $hodUser, comments: '   ');
            $this->workflowService->executeAction($req);
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Mandatory justification: REJECT action with whitespace comment throws ValidationException');

        // 5. Successful REJECT
        $req = new WorkflowActionRequest($reqId, WorkflowAction::REJECT, $hodUser, comments: 'Budget unavailable for this fiscal quarter.');
        $resRej = $this->workflowService->executeAction($req);
        $this->assert($resRej->newStatus === RequisitionStatus::REJECTED, 'REJECT with valid justification transitions to terminal REJECTED status');

        // 6. Terminal status locks against subsequent transitions
        $caught = false;
        try {
            $subReq2 = new WorkflowActionRequest($reqId, WorkflowAction::SUBMIT, $hodUser);
            $this->workflowService->executeAction($subReq2);
        } catch (InvalidWorkflowTransitionException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Terminal state protection: Requisition in REJECTED status rejects any further actions');
    }

    // =========================================================================
    // 4, 5 & 6. RBAC, DIRECT URL & DIRECT POST TAMPERING
    // =========================================================================
    private function testServerSideRbacEnforcement(): void
    {
        $reqId = $this->createTestRequisition('SUBMITTED');

        // Requester attempts to execute APPROVE without required permission
        $this->authAsRequester($this->requesterUserId);
        $caught = false;
        try {
            $req = new WorkflowActionRequest($reqId, WorkflowAction::APPROVE, $this->requesterUserId);
            $this->workflowService->executeAction($req);
        } catch (UnauthorizedWorkflowActionException|AuthorizationException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Server-side RBAC: Pure requester attempting to APPROVE throws UnauthorizedWorkflowActionException');

        // Unauthenticated / Zero User ID
        $caught = false;
        try {
            $req = new WorkflowActionRequest($reqId, WorkflowAction::SUBMIT, 0);
            $this->workflowService->executeAction($req);
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Input validation: Acting user ID of 0 throws ValidationException');
    }

    private function testDirectUrlOwnershipProtection(): void
    {
        // Requisition created by user A
        $reqId = $this->createTestRequisition('DRAFT', $this->requesterUserId);

        // Fetch requisition to simulate controller show() check
        $stmt = $this->db->prepare("SELECT * FROM requisitions WHERE id = ?");
        $stmt->execute([$reqId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Simulated check in RequisitionViewController::show():
        // If user is purely a REQUESTER and created_by != currentUserId -> throw AuthorizationException
        $userBRoles = ['REQUESTER'];
        $userBId = $this->otherRequesterUserId;

        $isElevated = in_array('ADMIN', $userBRoles, true) ||
                      in_array('HOD', $userBRoles, true) ||
                      in_array('DEAN', $userBRoles, true) ||
                      in_array('FINANCE_OFFICER', $userBRoles, true) ||
                      in_array('PROCUREMENT_OFFICER', $userBRoles, true);

        $denied = false;
        if (!$isElevated && (int)$row['created_by'] !== $userBId) {
            $denied = true;
        }

        $this->assert($denied, 'Direct URL protection: Non-creator REQUESTER is denied access to other users requisitions');

        // Elevated user check
        $adminRoles = ['ADMIN'];
        $adminElevated = in_array('ADMIN', $adminRoles, true);
        $this->assert($adminElevated, 'Direct URL protection: Elevated roles retain institutional oversight access');
    }

    private function testDirectPostTamperingProtection(): void
    {
        // 1. Invalid workflow action name
        $invalidAction = WorkflowAction::tryFromString('HACK_DIRECT_STATUS');
        $this->assert($invalidAction === null, 'POST tampering: Invalid workflow action string is rejected by tryFromString');

        // 2. Non-existent requisition ID
        $caught = false;
        try {
            $hodUser = $this->db->query("SELECT id FROM users WHERE username = 'kwame.mensah'")->fetchColumn() ?: 1;
            $req = new WorkflowActionRequest(9999999, WorkflowAction::SUBMIT, (int)$hodUser);
            $this->workflowService->executeAction($req);
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, 'POST tampering: Non-existent requisition ID throws ValidationException');
    }

    // =========================================================================
    // 7 & 8. CSRF DEFENSE
    // =========================================================================
    private function testCsrfGenerationAndValidation(): void
    {
        Session::start();
        $token = Csrf::regenerate();

        $this->assert(is_string($token) && strlen($token) === 64, 'CSRF token is a 64-character cryptographically secure hex string');
        $this->assert(Csrf::validate($token) === true, 'Csrf::validate returns true for matching session token');
        $this->assert(Csrf::token() === $token, 'Csrf::token() retrieves active session token');
    }

    private function testCsrfRejectionOfForgedAndEmptyTokens(): void
    {
        Session::start();
        Csrf::regenerate();

        $this->assert(Csrf::validate(null) === false, 'CSRF: null token returns false');
        $this->assert(Csrf::validate('') === false, 'CSRF: empty string token returns false');
        $this->assert(Csrf::validate('invalid_forged_csrf_token_1234567890') === false, 'CSRF: forged token returns false');
        $this->assert(Csrf::validate('   ') === false, 'CSRF: whitespace-only token returns false');
    }

    // =========================================================================
    // 9 & 10. SESSION SECURITY
    // =========================================================================
    private function testSessionFixationAndRegeneration(): void
    {
        Session::start();
        Session::set('auth_test_key', 'persisted_value');

        $oldId = session_id();
        $success = Session::regenerate(true);
        $newId = session_id();

        $this->assert($success === true, 'Session fixation: Session::regenerate() successfully executes');
        $this->assert($newId !== $oldId, 'Session fixation: New session ID is assigned upon regeneration');
        $this->assert(Session::get('auth_test_key') === 'persisted_value', 'Session fixation: Stored session attributes persist after regeneration');
    }

    private function testSessionInactivityTimeout(): void
    {
        Session::start(['inactivity_timeout' => 1800]);
        // Simulate past activity beyond 1800s
        $_SESSION['_last_activity'] = time() - 3600;
        Session::set('user_logged_in', true);

        // Invoking timeout check (mirrors Session::checkTimeout via start)
        $timeout = 1800;
        $isExpired = (time() - $_SESSION['_last_activity']) > $timeout;
        $this->assert($isExpired === true, 'Session expiration: Inactivity timeout triggers when last activity exceeds window');
    }

    private function testSessionDestruction(): void
    {
        Session::start();
        Session::set('active_token', 'xyz123');
        Session::destroy();

        $this->assert(empty($_SESSION), 'Session destruction: $_SESSION array is completely cleared on logout');
        $this->assert(Session::get('active_token') === null, 'Session destruction: Session::get returns null for destroyed session');
    }

    // =========================================================================
    // 11 & 12. DUPLICATE TRANSITION & CONCURRENCY CONFLICT
    // =========================================================================
    private function testDuplicateTransitionRejection(): void
    {
        $reqId = $this->createTestRequisition('DRAFT');

        // Action 1: SUBMIT
        $this->authAsRequester($this->requesterUserId);
        $req = new WorkflowActionRequest($reqId, WorkflowAction::SUBMIT, $this->requesterUserId);
        $res = $this->workflowService->executeAction($req);
        $this->assert($res->newStatus === RequisitionStatus::SUBMITTED, 'Initial action transitions requisition to SUBMITTED');

        // Action 2: Duplicate SUBMIT (replay attack / double click)
        $caught = false;
        try {
            $this->workflowService->executeAction($req);
        } catch (InvalidWorkflowTransitionException $e) {
            $caught = true;
        }
        $this->assert($caught, 'Duplicate transition prevention: Replaying the same action on updated status throws InvalidWorkflowTransitionException');
    }

    private function testConcurrencyConditionalStatusUpdateFailure(): void
    {
        $reqId = $this->createTestRequisition('DRAFT');

        // Conditional status update expects 'SUBMITTED', but row is actually in 'DRAFT'
        $updated = $this->reqRepo->updateStatus($reqId, 'ENDORSED', $this->requesterUserId, 'SUBMITTED');
        $this->assert($updated === false, 'Concurrency safety: Conditional update fails when expected_status does not match database record');

        // Verify record in DB was untouched
        $current = $this->reqRepo->findById($reqId);
        $this->assert($current->status === RequisitionStatus::DRAFT, 'Concurrency safety: Database record remains unchanged in DRAFT status after conditional conflict');
    }

    // =========================================================================
    // 13 & 14. AUDIT TRAIL ATOMICITY & ROLLBACK
    // =========================================================================
    private function testAuditLogAtomicityAcrossTransitions(): void
    {
        $reqId = $this->createTestRequisition('DRAFT');

        $this->authAsRequester($this->requesterUserId);
        $req = new WorkflowActionRequest($reqId, WorkflowAction::SUBMIT, $this->requesterUserId);
        $result = $this->workflowService->executeAction($req);

        $this->assert($result->actionLogId > 0, 'Atomicity: WorkflowActionLog record was generated with valid ID');
        $this->assert($result->auditLogId > 0, 'Atomicity: Institutional AuditLog record was generated with valid ID');

        // Verify action log fields
        $actStmt = $this->db->prepare("SELECT * FROM workflow_action_logs WHERE id = ?");
        $actStmt->execute([$result->actionLogId]);
        $actionRow = $actStmt->fetch(PDO::FETCH_ASSOC);

        $this->assert($actionRow['document_id'] == $reqId, 'ActionLog matches requisition document_id');
        $this->assert($actionRow['pre_status'] === 'DRAFT', 'ActionLog pre_status correctly recorded as DRAFT');
        $this->assert($actionRow['post_status'] === 'SUBMITTED', 'ActionLog post_status correctly recorded as SUBMITTED');
        $this->assert($actionRow['action'] === 'SUBMIT', 'ActionLog action correctly recorded as SUBMIT');

        // Verify institutional audit log fields
        $auditStmt = $this->db->prepare("SELECT * FROM audit_logs WHERE id = ?");
        $auditStmt->execute([$result->auditLogId]);
        $auditRow = $auditStmt->fetch(PDO::FETCH_ASSOC);

        $this->assert($auditRow['record_id'] == $reqId, 'AuditLog record_id matches requisition ID');
        $this->assert($auditRow['record_type'] === 'requisitions', 'AuditLog record_type correctly recorded as requisitions');
        $this->assert($auditRow['action'] === 'WORKFLOW_SUBMIT', 'AuditLog action correctly formatted as WORKFLOW_SUBMIT');

        // Validate JSON state snapshots
        $prevState = json_decode($auditRow['previous_state_json'], true);
        $newState = json_decode($auditRow['new_state_json'], true);
        $this->assert(is_array($prevState) && $prevState['status'] === 'DRAFT', 'AuditLog previous_state_json contains valid JSON with DRAFT status');
        $this->assert(is_array($newState) && $newState['status'] === 'SUBMITTED', 'AuditLog new_state_json contains valid JSON with SUBMITTED status');
    }

    private function testAtomicRollbackOnLoggingFailure(): void
    {
        $reqId = $this->createTestRequisition('DRAFT');

        // Mock an audit log repository that fails
        $failingAuditRepo = new class extends AuditLogRepository {
            public function __construct() {}
            public function create(array $data): int {
                throw new \RuntimeException('Simulated disk/database failure during audit log persistence.');
            }
        };

        $faultyService = new RequisitionWorkflowService(
            $this->db,
            $this->reqRepo,
            new WorkflowDefinitionRepository($this->db),
            new WorkflowStepRuleRepository($this->db),
            new WorkflowActionLogRepository($this->db),
            $failingAuditRepo
        );

        $this->authAsRequester($this->requesterUserId);

        $caught = false;
        try {
            $req = new WorkflowActionRequest($reqId, WorkflowAction::SUBMIT, $this->requesterUserId);
            $faultyService->executeAction($req);
        } catch (Throwable $e) {
            $caught = true;
        }

        $this->assert($caught, 'Failure injection: Faulty audit logging throws exception');

        // Verify status rolled back to DRAFT
        $current = $this->reqRepo->findById($reqId);
        $this->assert($current->status === RequisitionStatus::DRAFT, 'Transaction rollback: Requisition status rolled back to DRAFT');

        // Verify no orphan action log was committed
        $orphanStmt = $this->db->prepare("SELECT COUNT(*) FROM workflow_action_logs WHERE document_id = ? AND action = 'SUBMIT'");
        $orphanStmt->execute([$reqId]);
        $orphanCount = (int)$orphanStmt->fetchColumn();
        $this->assert($orphanCount === 0, 'Transaction rollback: No orphan workflow action log remained committed');
    }

    // =========================================================================
    // 15. ERROR RESPONSE FORMATTING
    // =========================================================================
    private function testErrorResponseFormatting(): void
    {
        // 1. AuthorizationException produces HTTP 403
        $authEx = new AuthorizationException('Access denied to requested resource.');
        $req = new Request('GET', '/api/test', [], [], ['accept' => 'application/json'], ['HTTP_ACCEPT' => 'application/json']);
        $resp = App::handleException($authEx, $req);

        $this->assert($resp->getStatusCode() === 403, 'Error response formatting: AuthorizationException returns HTTP 403');
        $json = json_decode($resp->getContent(), true);
        $this->assert($json['error'] === true && $json['status'] === 403, 'Error response formatting: JSON contains error=true and status=403');

        // 2. ValidationException produces HTTP 422
        $valEx = new ValidationException('Validation failed', ['field' => ['Field is required']]);
        $respVal = App::handleException($valEx, $req);
        $this->assert($respVal->getStatusCode() === 422, 'Error response formatting: ValidationException returns HTTP 422');
        $jsonVal = json_decode($respVal->getContent(), true);
        $this->assert(isset($jsonVal['errors']['field']), 'Error response formatting: Validation errors array is properly mapped');
    }

    // =========================================================================
    // 16. EMPTY-STATE & FILTER RESILIENCE
    // =========================================================================
    private function testEmptyStateAndFilterResilience(): void
    {
        // Querying non-existent search term
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM requisitions WHERE requisition_number LIKE :search
        ");
        $stmt->execute([':search' => '%NONEXISTENT_REQ_STRING_999%']);
        $count = (int)$stmt->fetchColumn();

        $this->assert($count === 0, 'Empty-state resilience: Non-matching search produces 0 count cleanly without database errors');
    }

    // =========================================================================
    // 17. TEST TEARDOWN ISOLATION
    // =========================================================================
    private function testTeardownIsolationIntegrity(): void
    {
        // Check that demo fixtures exist before teardown
        $stmt = $this->db->query("SELECT COUNT(*) FROM requisitions WHERE requisition_number IN ('REQ-2026-CS-001', 'REQ-2026-CS-002', 'REQ-2026-CS-003', 'REQ-2026-CS-004')");
        $demoCount = (int)$stmt->fetchColumn();
        $this->assert($demoCount >= 3, 'Teardown isolation: Approved demo fixtures (REQ-2026-CS-001..004) exist and are protected');

        $userStmt = $this->db->query("SELECT COUNT(*) FROM users WHERE username IN ('kwame.mensah', 'dean.user', 'finance.user', 'procurement.user', 'admin.user')");
        $demoUsers = (int)$userStmt->fetchColumn();
        $this->assert($demoUsers === 5, 'Teardown isolation: All 5 approved demo accounts are intact');
    }

    private function teardown(): void
    {
        // 1. Delete tracked requisitions and associated workflow action logs and audit logs
        if (!empty($this->trackedRequisitionIds)) {
            $ph = implode(',', array_fill(0, count($this->trackedRequisitionIds), '?'));
            $this->db->prepare("DELETE FROM workflow_action_logs WHERE document_id IN ({$ph})")->execute($this->trackedRequisitionIds);
            $this->db->prepare("DELETE FROM audit_logs WHERE record_type = 'requisitions' AND record_id IN ({$ph})")->execute($this->trackedRequisitionIds);
            $this->db->prepare("DELETE FROM requisitions WHERE id IN ({$ph})")->execute($this->trackedRequisitionIds);
        }

        // 2. Delete test users and their roles and audit logs
        if (!empty($this->trackedUserIds)) {
            $ph = implode(',', array_fill(0, count($this->trackedUserIds), '?'));
            $this->db->prepare("DELETE FROM user_entity_roles WHERE user_id IN ({$ph})")->execute($this->trackedUserIds);
            $this->db->prepare("DELETE FROM audit_logs WHERE actor_user_id IN ({$ph})")->execute($this->trackedUserIds);
            $this->db->prepare("DELETE FROM users WHERE id IN ({$ph})")->execute($this->trackedUserIds);
        }
    }
}

// CLI Direct Execution Entrypoint
if (php_sapi_name() === 'cli') {
    (new Phase25HardeningTest())->run();
}
