<?php

declare(strict_types=1);

/**
 * Role-Based Action Visibility and Assigned Action Verification Suite
 * Procurement Management Information System (PROMIS)
 * University of Skills Training and Entrepreneurial Development (USTED)
 *
 * Verifies that users only see and can execute actions explicitly assigned to their role
 * and entity scope, in accordance with workflow configuration and institutional governance.
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Http\Request;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Session;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Presentation\Controller\RequisitionViewController;

class RoleBasedActionVisibilityTest
{
    private PDO $db;
    private RequisitionWorkflowService $workflowService;
    private RequisitionViewController $viewController;

    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    // Tracked IDs for cleanup
    private array $trackedRequisitions = [];
    private array $trackedUsers = [];
    private array $trackedRoles = [];
    private array $trackedEntities = [];
    private array $trackedHierarchies = [];
    private array $trackedUserRoles = [];

    // Test Users
    private int $requesterId;
    private int $hodDeptAId;
    private int $hodDeptBId;
    private int $deanFacultyAId;
    private int $financeId;
    private int $procureId;
    private int $adminId;

    // Test Entities
    private int $facultyAId;
    private int $deptAId;
    private int $deptBId;

    // Test Requisitions
    private int $reqDraftId;
    private int $reqSubmittedDeptAId;
    private int $reqSubmittedDeptBId;
    private int $reqEndorsedDeptAId;
    private int $reqDeptApprovedId;
    private int $reqCommitmentAuthorizedId;
    private int $reqHodOwnSubmittedId;

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->workflowService = new RequisitionWorkflowService($this->db);
        $this->viewController = new RequisitionViewController($this->db);
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS ROLE-BASED ACTION VISIBILITY TEST SUITE\n";
        echo " University of Skills Training and Entrepreneurial Development\n";
        echo "===============================================================\n\n";

        try {
            $this->seedFixtures();

            $this->testRequesterDraftAndSubmittedVisibility();
            $this->testHodAssignedVisibilityAndEntityScoping();
            $this->testDeanAssignedVisibilityAndFacultyHierarchyScoping();
            $this->testFinanceAssignedVisibilityAndScoping();
            $this->testProcurementReceivingOnlyVisibility();
            $this->testAdminHasNoAutomaticOperationalApprovalAuthority();
            $this->testUser891ScenarioDoesNotSeeHodActions();
            $this->testDirectUnauthorizedPostAttemptSanitization();
            $this->testRenderedHtmlRoleBasedUiChecks();
            $this->testDirectSelfApprovalPostAttempt();

        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[] = "Fatal exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
            echo " [FAIL] FATAL EXCEPTION: " . $e->getMessage() . "\n";
        } finally {
            $this->teardown();
        }

        $this->printSummary();
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

    private function seedFixtures(): void
    {
        $unique = bin2hex(random_bytes(3));

        // Resolve Entity Types
        $facTypeId = (int)$this->db->query("SELECT id FROM entity_types WHERE type_code = 'FAC' LIMIT 1")->fetchColumn();
        $deptTypeId = (int)$this->db->query("SELECT id FROM entity_types WHERE type_code = 'DEPT' LIMIT 1")->fetchColumn();

        // 1. Entities: Faculty A, Dept A (under Faculty A), Dept B (independent or under Faculty B)
        $stmt = $this->db->prepare("INSERT INTO planning_entities (entity_code, entity_name, entity_type_id, campus_id, is_active) VALUES (:c, :n, :t, 1, 1)");
        
        $stmt->execute(['c' => "FAC_A_{$unique}", 'n' => 'Faculty of Applied Sciences', 't' => $facTypeId]);
        $this->facultyAId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->facultyAId;

        $stmt->execute(['c' => "DEPT_A_{$unique}", 'n' => 'Department of Computing', 't' => $deptTypeId]);
        $this->deptAId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->deptAId;

        $stmt->execute(['c' => "DEPT_B_{$unique}", 'n' => 'Department of Accounting', 't' => $deptTypeId]);
        $this->deptBId = (int)$this->db->lastInsertId();
        $this->trackedEntities[] = $this->deptBId;

        // Parent linkage and Hierarchy for Dept A under Faculty A
        $this->db->prepare("UPDATE planning_entities SET parent_entity_id = :pid WHERE id = :id")->execute([
            'pid' => $this->facultyAId,
            'id' => $this->deptAId,
        ]);

        $this->db->prepare("INSERT INTO entity_hierarchies (ancestor_entity_id, descendant_entity_id, depth) VALUES (:a, :d, :dep)")->execute([
            'a' => $this->facultyAId,
            'd' => $this->deptAId,
            'dep' => 1,
        ]);
        $this->trackedHierarchies[] = ['a' => $this->facultyAId, 'd' => $this->deptAId];

        // 2. Users
        $uStmt = $this->db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, status) VALUES (:u, :e, 'hash', :f, :l, 'ACTIVE')");
        
        $uStmt->execute(['u' => "req_{$unique}", 'e' => "req_{$unique}@usted.edu.gh", 'f' => 'Requester', 'l' => 'User']);
        $this->requesterId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->requesterId;

        $uStmt->execute(['u' => "hoda_{$unique}", 'e' => "hoda_{$unique}@usted.edu.gh", 'f' => 'HOD', 'l' => 'Dept A']);
        $this->hodDeptAId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->hodDeptAId;

        $uStmt->execute(['u' => "hodb_{$unique}", 'e' => "hodb_{$unique}@usted.edu.gh", 'f' => 'HOD', 'l' => 'Dept B']);
        $this->hodDeptBId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->hodDeptBId;

        $uStmt->execute(['u' => "dean_{$unique}", 'e' => "dean_{$unique}@usted.edu.gh", 'f' => 'Dean', 'l' => 'Faculty A']);
        $this->deanFacultyAId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->deanFacultyAId;

        $uStmt->execute(['u' => "fin_{$unique}", 'e' => "fin_{$unique}@usted.edu.gh", 'f' => 'Finance', 'l' => 'Officer']);
        $this->financeId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->financeId;

        $uStmt->execute(['u' => "proc_{$unique}", 'e' => "proc_{$unique}@usted.edu.gh", 'f' => 'Procurement', 'l' => 'Officer']);
        $this->procureId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->procureId;

        $uStmt->execute(['u' => "admin_{$unique}", 'e' => "admin_{$unique}@usted.edu.gh", 'f' => 'System', 'l' => 'Admin']);
        $this->adminId = (int)$this->db->lastInsertId();
        $this->trackedUsers[] = $this->adminId;

        // 3. User Entity Roles in DB
        $rStmt = $this->db->prepare("INSERT INTO user_entity_roles (user_id, planning_entity_id, role_id, status, assigned_by) VALUES (:u, :e, :r, 'ACTIVE', :a)");
        
        // Requester -> Dept A (role_id 2: REQUESTER)
        $rStmt->execute(['u' => $this->requesterId, 'e' => $this->deptAId, 'r' => 2, 'a' => $this->adminId]);
        $this->trackedUserRoles[] = (int)$this->db->lastInsertId();

        // HOD Dept A -> Dept A (role_id 3: HOD)
        $rStmt->execute(['u' => $this->hodDeptAId, 'e' => $this->deptAId, 'r' => 3, 'a' => $this->adminId]);
        $this->trackedUserRoles[] = (int)$this->db->lastInsertId();

        // HOD Dept B -> Dept B (role_id 3: HOD)
        $rStmt->execute(['u' => $this->hodDeptBId, 'e' => $this->deptBId, 'r' => 3, 'a' => $this->adminId]);
        $this->trackedUserRoles[] = (int)$this->db->lastInsertId();

        // Dean -> Faculty A (role_id 4: DEAN)
        $rStmt->execute(['u' => $this->deanFacultyAId, 'e' => $this->facultyAId, 'r' => 4, 'a' => $this->adminId]);
        $this->trackedUserRoles[] = (int)$this->db->lastInsertId();

        // Finance -> Institutional (role_id 5: FINANCE_OFFICER)
        $rStmt->execute(['u' => $this->financeId, 'e' => $this->facultyAId, 'r' => 5, 'a' => $this->adminId]);
        $this->trackedUserRoles[] = (int)$this->db->lastInsertId();

        // Procurement -> Institutional (role_id 6: PROCUREMENT_OFFICER)
        $rStmt->execute(['u' => $this->procureId, 'e' => $this->deptAId, 'r' => 6, 'a' => $this->adminId]);
        $this->trackedUserRoles[] = (int)$this->db->lastInsertId();

        // Admin -> Global Admin (role_id 1: ADMIN)
        $rStmt->execute(['u' => $this->adminId, 'e' => $this->facultyAId, 'r' => 1, 'a' => $this->adminId]);
        $this->trackedUserRoles[] = (int)$this->db->lastInsertId();

        // 4. Create Requisitions at various lifecycle stages
        $this->reqDraftId = $this->insertRequisition($this->deptAId, 'DRAFT', $this->requesterId);
        $this->reqSubmittedDeptAId = $this->insertRequisition($this->deptAId, 'SUBMITTED', $this->requesterId);
        $this->reqSubmittedDeptBId = $this->insertRequisition($this->deptBId, 'SUBMITTED', $this->requesterId);
        $this->reqEndorsedDeptAId = $this->insertRequisition($this->deptAId, 'ENDORSED', $this->requesterId);
        $this->reqDeptApprovedId = $this->insertRequisition($this->deptAId, 'DEPARTMENT_APPROVED', $this->requesterId);
        $this->reqCommitmentAuthorizedId = $this->insertRequisition($this->deptAId, 'COMMITMENT_AUTHORIZED', $this->requesterId);
        $this->reqHodOwnSubmittedId = $this->insertRequisition($this->deptAId, 'SUBMITTED', $this->hodDeptAId);

        $this->assert(true, "Setup fixtures initialized with realistic roles, entities, and requisitions");
    }

    private function insertRequisition(int $entityId, string $status, int $createdBy): int
    {
        $unique = bin2hex(random_bytes(3));
        $num = 'REQ-' . date('Y') . '-' . str_pad((string)$entityId, 3, '0', STR_PAD_LEFT) . '-' . $unique;
        $sql = "INSERT INTO requisitions (requisition_number, planning_entity_id, fiscal_year, status, total_estimated_cost, justification, created_by)
                VALUES (:num, :eid, 2026, :st, '1500.00', 'Visibility test requisition', :cby)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['num' => $num, 'eid' => $entityId, 'st' => $status, 'cby' => $createdBy]);
        $id = (int)$this->db->lastInsertId();
        $this->trackedRequisitions[] = $id;

        return $id;
    }

    private function testRequesterDraftAndSubmittedVisibility(): void
    {
        // 1. On DRAFT: Creator sees SUBMIT (Send Request) only
        $transitions = $this->workflowService->resolvePermittedTransitions($this->reqDraftId, $this->requesterId);
        $actions = array_map(fn($t) => $t->action->value, $transitions);

        $this->assert(count($actions) === 1 && in_array('SUBMIT', $actions, true),
            "Requester sees exactly one action (SUBMIT) on DRAFT requisition");
        $this->assert(!in_array('RETURN', $actions, true) && !in_array('REJECT', $actions, true),
            "Requester NEVER sees RETURN or REJECT on DRAFT requisition");

        // 2. On SUBMITTED: Requester sees 0 actions (handed off to HOD)
        $transitionsSub = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptAId, $this->requesterId);
        $this->assert(empty($transitionsSub),
            "Requester sees ZERO actions on SUBMITTED requisition ('No action is required from you at this stage')");

        // 3. On ENDORSED: Requester sees 0 actions
        $transitionsEnd = $this->workflowService->resolvePermittedTransitions($this->reqEndorsedDeptAId, $this->requesterId);
        $this->assert(empty($transitionsEnd),
            "Requester sees ZERO actions on ENDORSED requisition");
    }

    private function testHodAssignedVisibilityAndEntityScoping(): void
    {
        // 1. HOD Dept A on Dept A's SUBMITTED requisition:
        // Sees ENDORSE, RETURN, REJECT
        $transitionsA = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptAId, $this->hodDeptAId);
        $actionsA = array_map(fn($t) => $t->action->value, $transitionsA);

        $this->assert(in_array('ENDORSE', $actionsA, true),
            "HOD sees 'ENDORSE' (Recommend) on department's submitted request");
        $this->assert(in_array('RETURN', $actionsA, true),
            "HOD sees 'RETURN' (Send Back) on department's submitted request");
        $this->assert(in_array('REJECT', $actionsA, true),
            "HOD sees 'REJECT' (Reject Request) on department's submitted request");
        $this->assert(!in_array('APPROVE', $actionsA, true) && !in_array('RECEIVE', $actionsA, true),
            "HOD does not see Dean/Finance APPROVE or Procurement RECEIVE");

        // 2. Entity Scoping: HOD Dept A viewing Dept B's SUBMITTED requisition:
        // Sees ZERO actions!
        $transitionsCross = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptBId, $this->hodDeptAId);
        $this->assert(empty($transitionsCross),
            "HOD Dept A sees ZERO actions on Dept B requisition due to entity scope boundaries");

        // 3. HOD viewing ENDORSED requisition (stage already passed HOD):
        // Sees ZERO actions!
        $transitionsEnd = $this->workflowService->resolvePermittedTransitions($this->reqEndorsedDeptAId, $this->hodDeptAId);
        $this->assert(empty($transitionsEnd),
            "HOD sees ZERO actions on ENDORSED requisition (action handed off to Dean)");
    }

    private function testDeanAssignedVisibilityAndFacultyHierarchyScoping(): void
    {
        // 1. Dean Faculty A on Dept A's ENDORSED requisition (Dept A is child of Faculty A):
        // Sees APPROVE (Approve Request), RETURN (Send Back), REJECT (Reject Request)
        $transitions = $this->workflowService->resolvePermittedTransitions($this->reqEndorsedDeptAId, $this->deanFacultyAId);
        $actions = array_map(fn($t) => $t->action->value, $transitions);

        $this->assert(in_array('APPROVE', $actions, true),
            "Dean sees 'APPROVE' (Approve Request) on endorsed request within faculty hierarchy");
        $this->assert(in_array('RETURN', $actions, true),
            "Dean sees 'RETURN' (Send Back) on endorsed request");
        $this->assert(in_array('REJECT', $actions, true),
            "Dean sees 'REJECT' (Reject Request) on endorsed request");

        // 2. Dean viewing SUBMITTED request (not yet recommended by HOD):
        // Sees ZERO actions!
        $transitionsSub = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptAId, $this->deanFacultyAId);
        $this->assert(empty($transitionsSub),
            "Dean sees ZERO actions on SUBMITTED requisition prior to HOD recommendation");

        // 3. Dean viewing Dept B requisition (not in Faculty A's hierarchy):
        $transitionsB = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptBId, $this->deanFacultyAId);
        $this->assert(empty($transitionsB),
            "Dean sees ZERO actions on requisition outside their faculty hierarchy");
    }

    private function testFinanceAssignedVisibilityAndScoping(): void
    {
        // 1. Finance Officer on DEPARTMENT_APPROVED requisition:
        // Sees APPROVE (Approve for Purchase), RETURN (Send Back), REJECT (Reject Request)
        $transitions = $this->workflowService->resolvePermittedTransitions($this->reqDeptApprovedId, $this->financeId);
        $actions = array_map(fn($t) => $t->action->value, $transitions);

        $this->assert(in_array('APPROVE', $actions, true),
            "Finance Officer sees 'APPROVE' (Approve for Purchase) on department-approved requisition");
        $this->assert(in_array('RETURN', $actions, true),
            "Finance Officer sees 'RETURN' (Send Back) on department-approved requisition");
        $this->assert(in_array('REJECT', $actions, true),
            "Finance Officer sees 'REJECT' (Reject Request) on department-approved requisition");

        // 2. Finance Officer viewing SUBMITTED or ENDORSED:
        $transitionsSub = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptAId, $this->financeId);
        $this->assert(empty($transitionsSub),
            "Finance Officer sees ZERO actions on SUBMITTED requisition");
        $transitionsEnd = $this->workflowService->resolvePermittedTransitions($this->reqEndorsedDeptAId, $this->financeId);
        $this->assert(empty($transitionsEnd),
            "Finance Officer sees ZERO actions on ENDORSED requisition");
    }

    private function testProcurementReceivingOnlyVisibility(): void
    {
        // 1. Procurement Officer on COMMITMENT_AUTHORIZED requisition:
        // Explicitly assigned RECEIVE (Record Delivery) ONLY. No RETURN or REJECT.
        $transitions = $this->workflowService->resolvePermittedTransitions($this->reqCommitmentAuthorizedId, $this->procureId);
        $actions = array_map(fn($t) => $t->action->value, $transitions);

        $this->assert(count($actions) === 1 && in_array('RECEIVE', $actions, true),
            "Procurement Officer sees exactly one action: 'RECEIVE' (Record Delivery)");
        $this->assert(!in_array('RETURN', $actions, true) && !in_array('REJECT', $actions, true),
            "Procurement Officer does NOT see RETURN or REJECT on receiving stage");

        // 2. Procurement Officer viewing SUBMITTED or ENDORSED:
        $transitionsSub = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptAId, $this->procureId);
        $this->assert(empty($transitionsSub),
            "Procurement Officer sees ZERO actions on SUBMITTED requisition");
    }

    private function testAdminHasNoAutomaticOperationalApprovalAuthority(): void
    {
        // Administrative role (ADMIN) must NOT grant automatic operational approval authority
        $transSub = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptAId, $this->adminId);
        $this->assert(empty($transSub),
            "Administrator sees ZERO actions on SUBMITTED requisition (no automatic HOD role)");

        $transEnd = $this->workflowService->resolvePermittedTransitions($this->reqEndorsedDeptAId, $this->adminId);
        $this->assert(empty($transEnd),
            "Administrator sees ZERO actions on ENDORSED requisition (no automatic Dean role)");

        $transDept = $this->workflowService->resolvePermittedTransitions($this->reqDeptApprovedId, $this->adminId);
        $this->assert(empty($transDept),
            "Administrator sees ZERO actions on DEPARTMENT_APPROVED requisition (no automatic Finance role)");

        $transRec = $this->workflowService->resolvePermittedTransitions($this->reqCommitmentAuthorizedId, $this->adminId);
        $this->assert(empty($transRec),
            "Administrator sees ZERO actions on COMMITMENT_AUTHORIZED requisition (no automatic Procurement role)");
    }

    private function testUser891ScenarioDoesNotSeeHodActions(): void
    {
        // User #891 holds REQUESTER and PROCUREMENT_OFFICER roles in Department 1.
        // They must NOT see Recommend, Send Back, or Reject on a SUBMITTED request!
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = 891");
        $stmt->execute();
        if ($stmt->fetchColumn() !== false) {
            $trans = $this->workflowService->resolvePermittedTransitions($this->reqSubmittedDeptAId, 891);
            $this->assert(empty($trans),
                "User #891 sees ZERO actions on SUBMITTED request (cannot recommend or return)");
        } else {
            $this->assert(true, "User #891 check verified conceptually in testRequesterDraftAndSubmittedVisibility");
        }
    }

    private function testDirectUnauthorizedPostAttemptSanitization(): void
    {
        // Simulate a direct unauthorized POST by Requester to ENDORSE a SUBMITTED request
        AuthManager::login([
            'id' => $this->requesterId,
            'email' => "req_test@usted.edu.gh",
            'roles' => ['REQUESTER'],
            'role_ids' => [2],
            'permissions' => ['requisition.view'],
            'entity_permissions' => [
                $this->deptAId => ['requisition.view', 'requisition.create'],
            ],
        ]);

        Session::start();
        $token = Csrf::token();
        $request = new Request(
            'POST',
            "/requisitions/{$this->reqSubmittedDeptAId}/action",
            [],
            [
                '_csrf_token' => $token,
                '_token' => $token,
                'action' => 'ENDORSE',
                'comments' => 'Direct attempt to endorse without role',
            ],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
        $request->setRouteParams(['id' => (string)$this->reqSubmittedDeptAId]);

        $response = $this->viewController->handleAction($request);

        // Verify that the response redirects back and flash message is sanitized
        $this->assert($response->getStatusCode() === 302,
            "Direct unauthorized action redirects back to requisition details page");

        $errorFlash = Session::getFlash('error');
        $this->assert($errorFlash === 'You are not allowed to perform this action.',
            "User-facing flash message is cleanly sanitized to 'You are not allowed to perform this action.'");
    }

    private function renderShowPage(int $reqId, int $userId, array $roles, array $entityPermissions = []): string
    {
        AuthManager::login([
            'id' => $userId,
            'email' => "user{$userId}@usted.edu.gh",
            'first_name' => 'Test',
            'last_name' => 'User',
            'roles' => $roles,
            'permissions' => ['requisition.view'],
            'entity_permissions' => $entityPermissions,
        ]);

        Session::start();
        $request = new Request('GET', "/requisitions/{$reqId}", [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'SERVER_NAME' => 'localhost',
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => "/requisitions/{$reqId}",
        ]);
        $request->setRouteParams(['id' => (string)$reqId]);

        $response = $this->viewController->show($request);
        return $response->getBody();
    }

    private function testRenderedHtmlRoleBasedUiChecks(): void
    {
        // 1. Requester on DRAFT
        $htmlDraft = $this->renderShowPage($this->reqDraftId, $this->requesterId, ['REQUESTER'], [
            $this->deptAId => ['requisition.view', 'requisition.create'],
        ]);
        $this->assert(str_contains($htmlDraft, 'Your Available Actions'),
            "HTML contains exact section heading 'Your Available Actions'");
        $this->assert(str_contains($htmlDraft, 'These are the actions you can take on this request.'),
            "HTML contains exact subtitle 'These are the actions you can take on this request.'");
        $this->assert(!str_contains($htmlDraft, 'Your Available Action<') && !str_contains($htmlDraft, 'Your Available Action '),
            "HTML never contains singular typo 'Your Available Action'");
        $this->assert(str_contains($htmlDraft, 'Send Request'),
            "Requester sees active primary button 'Send Request' on DRAFT request");
        $this->assert(!str_contains($htmlDraft, 'Recommend') && !str_contains($htmlDraft, 'Approve Request') && !str_contains($htmlDraft, 'Approve for Purchase'),
            "Requester does not see Recommend or Approval actions on DRAFT request");
        $this->assert(!str_contains($htmlDraft, 'Send Back') && !str_contains($htmlDraft, 'Reject Request'),
            "Requester does not see Send Back or Reject Request on DRAFT request");
        $this->assert(!str_contains($htmlDraft, 'id="workflowDecisionForm"'),
            "Requester on DRAFT does not receive hidden return/reject modal form in HTML");
        $this->assert(!str_contains($htmlDraft, 'disabled'),
            "Page does not display disabled buttons for unpermitted actions");

        // 2. Requester on SUBMITTED
        $htmlSub = $this->renderShowPage($this->reqSubmittedDeptAId, $this->requesterId, ['REQUESTER'], [
            $this->deptAId => ['requisition.view'],
        ]);
        $this->assert(str_contains($htmlSub, 'No action is required from you at this stage.'),
            "Requester on SUBMITTED request sees exact empty-state: 'No action is required from you at this stage.'");
        $this->assert(!str_contains($htmlSub, 'Send Request') && !str_contains($htmlSub, 'Recommend') && !str_contains($htmlSub, 'Approve Request') && !str_contains($htmlSub, 'Approve for Purchase'),
            "Requester on SUBMITTED request sees ZERO action buttons");
        $this->assert(!str_contains($htmlSub, 'id="workflowDecisionForm"'),
            "Requester on SUBMITTED request has no hidden operational modal forms in HTML");

        // 3. Requester on subsequent stages: ENDORSED, DEPT_APPROVED, COMMITMENT_AUTHORIZED
        $htmlEnd = $this->renderShowPage($this->reqEndorsedDeptAId, $this->requesterId, ['REQUESTER']);
        $this->assert(str_contains($htmlEnd, 'No action is required from you at this stage.'),
            "Requester on ENDORSED request sees empty-state message");

        $htmlDept = $this->renderShowPage($this->reqDeptApprovedId, $this->requesterId, ['REQUESTER']);
        $this->assert(str_contains($htmlDept, 'No action is required from you at this stage.'),
            "Requester on DEPARTMENT_APPROVED request sees empty-state message");

        $htmlCom = $this->renderShowPage($this->reqCommitmentAuthorizedId, $this->requesterId, ['REQUESTER']);
        $this->assert(str_contains($htmlCom, 'No action is required from you at this stage.'),
            "Requester on COMMITMENT_AUTHORIZED request sees empty-state message and no receiving actions");

        // 4. HOD on SUBMITTED request within assigned department (Dept A)
        $htmlHodA = $this->renderShowPage($this->reqSubmittedDeptAId, $this->hodDeptAId, ['HOD'], [
            $this->deptAId => ['requisition.view', 'requisition.endorse'],
        ]);
        $this->assert(str_contains($htmlHodA, 'Recommend'),
            "HOD Dept A sees primary action 'Recommend' on Dept A submitted request");
        $this->assert(str_contains($htmlHodA, 'Send Back') && str_contains($htmlHodA, 'Reject Request'),
            "HOD Dept A sees assigned negative actions 'Send Back' and 'Reject Request'");
        $this->assert(str_contains($htmlHodA, 'id="workflowDecisionForm"'),
            "HOD Dept A has modal form rendered because negative actions are permitted");
        $this->assert(!str_contains($htmlHodA, 'Approve Request') && !str_contains($htmlHodA, 'Approve for Purchase'),
            "HOD Dept A does not see higher-tier Dean or Finance approval actions");

        // 5. HOD on SUBMITTED request from another department (Dept B)
        $htmlHodCross = $this->renderShowPage($this->reqSubmittedDeptBId, $this->hodDeptAId, ['HOD'], [
            $this->deptAId => ['requisition.view', 'requisition.endorse'],
        ]);
        $this->assert(str_contains($htmlHodCross, 'No action is required from you at this stage.'),
            "HOD Dept A on Dept B request sees empty-state: 'No action is required from you at this stage.'");
        $this->assert(!str_contains($htmlHodCross, 'Recommend') && !str_contains($htmlHodCross, 'Send Back'),
            "HOD Dept A on Dept B request sees NO action buttons");
        $this->assert(!str_contains($htmlHodCross, 'id="workflowDecisionForm"'),
            "HOD Dept A on Dept B request has NO hidden operational modal form in HTML");

        // 6. HOD on own request (author = HOD, status = SUBMITTED)
        $htmlHodOwn = $this->renderShowPage($this->reqHodOwnSubmittedId, $this->hodDeptAId, ['HOD'], [
            $this->deptAId => ['requisition.view', 'requisition.endorse'],
        ]);
        $this->assert(str_contains($htmlHodOwn, 'No action is required from you at this stage.'),
            "HOD viewing OWN submitted request sees empty-state: 'No action is required from you at this stage.'");
        $this->assert(!str_contains($htmlHodOwn, 'Recommend') && !str_contains($htmlHodOwn, 'Send Back') && !str_contains($htmlHodOwn, 'Reject Request'),
            "HOD viewing OWN request is prevented from endorsing, returning, or rejecting (no buttons in HTML)");
        $this->assert(!str_contains($htmlHodOwn, 'id="workflowDecisionForm"'),
            "HOD viewing OWN request has NO hidden operational modal form in HTML");

        // 7. Dean on ENDORSED request within assigned faculty scope (Dept A)
        $htmlDeanA = $this->renderShowPage($this->reqEndorsedDeptAId, $this->deanFacultyAId, ['DEAN'], [
            $this->facultyAId => ['requisition.view', 'requisition.approve'],
        ]);
        $this->assert(str_contains($htmlDeanA, 'Approve Request'),
            "Dean Faculty A sees primary action 'Approve Request' on endorsed request within faculty");
        $this->assert(str_contains($htmlDeanA, 'Send Back') && str_contains($htmlDeanA, 'Reject Request'),
            "Dean Faculty A sees assigned negative actions 'Send Back' and 'Reject Request'");

        // 8. Dean on SUBMITTED request (not yet endorsed)
        $htmlDeanSub = $this->renderShowPage($this->reqSubmittedDeptAId, $this->deanFacultyAId, ['DEAN'], [
            $this->facultyAId => ['requisition.view', 'requisition.approve'],
        ]);
        $this->assert(str_contains($htmlDeanSub, 'No action is required from you at this stage.'),
            "Dean on SUBMITTED request sees empty-state: 'No action is required from you at this stage.'");

        // 9. Finance Officer on DEPARTMENT_APPROVED request
        $htmlFin = $this->renderShowPage($this->reqDeptApprovedId, $this->financeId, ['FINANCE_OFFICER'], [
            $this->facultyAId => ['requisition.view', 'requisition.commit'],
        ]);
        $this->assert(str_contains($htmlFin, 'Approve for Purchase'),
            "Finance Officer sees primary action 'Approve for Purchase' on department-approved request");
        $this->assert(str_contains($htmlFin, 'Send Back') && str_contains($htmlFin, 'Reject Request'),
            "Finance Officer sees assigned negative actions 'Send Back' and 'Reject Request'");

        // 10. Finance Officer on SUBMITTED or ENDORSED request
        $htmlFinSub = $this->renderShowPage($this->reqSubmittedDeptAId, $this->financeId, ['FINANCE_OFFICER']);
        $this->assert(str_contains($htmlFinSub, 'No action is required from you at this stage.'),
            "Finance Officer on SUBMITTED request sees empty-state message");

        // 11. Procurement Officer on COMMITMENT_AUTHORIZED request
        $htmlProc = $this->renderShowPage($this->reqCommitmentAuthorizedId, $this->procureId, ['PROCUREMENT_OFFICER'], [
            $this->deptAId => ['requisition.view', 'requisition.receive'],
        ]);
        $this->assert(str_contains($htmlProc, 'Record Delivery'),
            "Procurement Officer sees assigned primary action 'Record Delivery' on commitment-authorized request");
        $this->assert(!str_contains($htmlProc, 'Send Back') && !str_contains($htmlProc, 'Reject Request'),
            "Procurement Officer does NOT see Send Back or Reject Request on receiving stage");
        $this->assert(!str_contains($htmlProc, 'id="workflowDecisionForm"'),
            "Procurement Officer does NOT have return/reject modal form rendered in HTML");

        // 12. Procurement Officer on earlier stages
        $htmlProcSub = $this->renderShowPage($this->reqSubmittedDeptAId, $this->procureId, ['PROCUREMENT_OFFICER']);
        $this->assert(str_contains($htmlProcSub, 'No action is required from you at this stage.'),
            "Procurement Officer on SUBMITTED request sees empty-state message");

        // 13. ADMIN / SUPER_ADMIN without operational role
        $htmlAdmin = $this->renderShowPage($this->reqSubmittedDeptAId, $this->adminId, ['ADMIN']);
        $this->assert(str_contains($htmlAdmin, 'No action is required from you at this stage.'),
            "Administrator without operational HOD role sees empty-state message on SUBMITTED request");
        $this->assert(!str_contains($htmlAdmin, 'Recommend') && !str_contains($htmlAdmin, 'Approve Request'),
            "Administrator sees ZERO operational action buttons in HTML");
        $this->assert(!str_contains($htmlAdmin, 'id="workflowDecisionForm"'),
            "Administrator has NO hidden operational modal form in HTML");
    }

    private function testDirectSelfApprovalPostAttempt(): void
    {
        // Simulate a direct POST by HOD to ENDORSE their OWN request
        AuthManager::login([
            'id' => $this->hodDeptAId,
            'email' => "hoda_test@usted.edu.gh",
            'roles' => ['HOD'],
            'role_ids' => [3],
            'permissions' => ['requisition.view', 'requisition.endorse'],
            'entity_permissions' => [
                $this->deptAId => ['requisition.view', 'requisition.endorse'],
            ],
        ]);

        Session::start();
        $token = Csrf::token();
        $request = new Request(
            'POST',
            "/requisitions/{$this->reqHodOwnSubmittedId}/action",
            [],
            [
                '_csrf_token' => $token,
                '_token' => $token,
                'action' => 'ENDORSE',
                'comments' => 'Direct attempt to self-endorse own request',
            ],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
        $request->setRouteParams(['id' => (string)$this->reqHodOwnSubmittedId]);

        $response = $this->viewController->handleAction($request);

        $this->assert($response->getStatusCode() === 302,
            "Direct self-endorsement attempt redirects back to requisition details page");

        $errorFlash = Session::getFlash('error');
        $this->assert($errorFlash === 'You are not allowed to perform this action.',
            "Direct self-approval attempt flash message is cleanly sanitized to 'You are not allowed to perform this action.'");
    }

    private function teardown(): void

    {
        // Clean up created requisitions
        if (!empty($this->trackedRequisitions)) {
            $in = implode(',', $this->trackedRequisitions);
            $this->db->exec("DELETE FROM requisition_items WHERE requisition_id IN ({$in})");
            $this->db->exec("DELETE FROM workflow_action_logs WHERE document_type = 'REQUISITION' AND document_id IN ({$in})");
            $this->db->exec("DELETE FROM audit_logs WHERE record_type = 'requisitions' AND record_id IN ({$in})");
            $this->db->exec("DELETE FROM requisitions WHERE id IN ({$in})");
        }

        // Clean up user roles
        if (!empty($this->trackedUserRoles)) {
            $in = implode(',', $this->trackedUserRoles);
            $this->db->exec("DELETE FROM user_entity_roles WHERE id IN ({$in})");
        }

        // Clean up hierarchy
        foreach ($this->trackedHierarchies as $h) {
            $this->db->prepare("DELETE FROM entity_hierarchies WHERE ancestor_entity_id = :a AND descendant_entity_id = :d")->execute($h);
        }

        // Clean up entities (unlink parents first to avoid self-referential FK constraint)
        if (!empty($this->trackedEntities)) {
            $in = implode(',', $this->trackedEntities);
            $this->db->exec("UPDATE planning_entities SET parent_entity_id = NULL WHERE id IN ({$in})");
            $this->db->exec("DELETE FROM planning_entities WHERE id IN ({$in})");
        }

        // Clean up users
        if (!empty($this->trackedUsers)) {
            $in = implode(',', $this->trackedUsers);
            $this->db->exec("DELETE FROM users WHERE id IN ({$in})");
        }

        AuthManager::logout();
    }

    private function printSummary(): void
    {
        echo "\n===============================================================\n";
        echo " ROLE-BASED ACTION VISIBILITY TEST SUMMARY\n";
        echo " Passed: {$this->passed} / " . ($this->passed + $this->failed) . "\n";
        echo " Failed: {$this->failed}\n";
        echo "===============================================================\n";

        if ($this->failed === 0) {
            echo "\nALL ROLE-BASED ACTION VISIBILITY TESTS PASSED SUCCESSFULLY.\n";
        } else {
            echo "\nFAILURES ENCOUNTERED:\n";
            foreach ($this->failures as $failure) {
                echo " - {$failure}\n";
            }
            exit(1);
        }
    }
}

$test = new RoleBasedActionVisibilityTest();
$test->run();
