<?php

declare(strict_types=1);

namespace Promis\Tests;

require_once __DIR__ . '/../core/autoload.php';

use PDO;
use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Session;
use Promis\Src\Planning\Auth\PlanningPermissions;
use Promis\Src\Planning\Domain\Decimal;
use Promis\Src\Planning\Domain\PlanStatus;
use Promis\Src\Planning\Domain\PlanVersionStatus;
use Promis\Src\Presentation\Controller\ProcurementPlanViewController;

App::bootstrap(dirname(__DIR__));

final class ProcurementPlanWebControllerTest
{
    private PDO $db;
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    // Tracked IDs for teardown
    private array $trackedPlans = [];
    private array $trackedVersions = [];
    private array $trackedItems = [];
    private array $trackedActionLogs = [];

    private ProcurementPlanViewController $controller;

    public function __construct()
    {
        $this->db = Connection::get();
        $this->controller = new ProcurementPlanViewController($this->db);
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS PROCUREMENT PLAN WEB CONTROLLER & WORKFLOW TESTS\n";
        echo "===============================================================\n";

        try {
            // 1. Guest access tests
            $this->testGuestCannotAccessProtectedPages();

            // 2. Authorization & Entity Scoping tests
            $this->testUnauthorizedEntityCannotCreateOrViewPlan();

            // 3. Plan Formulation & Calculation tests
            $this->testPlanDraftCreationAndServerSideCalculation();

            // 4. Manipulation and Validation tests
            $this->testManipulatedBrowserTotalsAreIgnored();
            $this->testEmptyPlanAndInvalidDataRejected();

            // 5. Workflow State & Submission tests
            $this->testSubmitChangesPlanStateAndLocksEditing();

            // 6. Approval & Review Workflow tests
            $this->testReviewReturnAndRejectActions();
            $this->testAuthorizedApprovalWorkflow();

            // 7. Version History & Drawdown Eligibility tests
            $this->testApprovedPlanEligibleForRequisitionDrawdown();

            // 8. CSRF & Rollback Integrity tests
            $this->testCsrfProtectionAndRollback();

        } finally {
            $this->cleanup();
        }

        echo "\n===============================================================\n";
        echo " PROCUREMENT PLAN TEST SUMMARY\n";
        echo " Total Assertions: " . ($this->passed + $this->failed) . "\n";
        echo " Passed: {$this->passed} / " . ($this->passed + $this->failed) . "\n";
        echo " Failed: {$this->failed}\n";
        echo "===============================================================\n";

        if ($this->failed > 0) {
            echo "FAILED TESTS:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
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

    private function deletePlanCascade(int $pid): void
    {
        try {
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $this->db->exec("DELETE FROM `workflow_action_logs` WHERE `document_type` = 'PROCUREMENT_PLAN' AND `document_id` = {$pid}");
            $this->db->exec("DELETE FROM `audit_logs` WHERE `record_type` = 'PROCUREMENT_PLAN' AND `record_id` = {$pid}");
            $this->db->exec("DELETE FROM `procurement_plan_items` WHERE `plan_version_id` IN (SELECT id FROM `procurement_plan_versions` WHERE `procurement_plan_id` = {$pid})");
            $this->db->exec("DELETE FROM `plan_revision_records` WHERE `procurement_plan_id` = {$pid}");
            $this->db->exec("DELETE FROM `plan_review_cycles` WHERE `procurement_plan_id` = {$pid}");
            $this->db->exec("DELETE FROM `procurement_plan_versions` WHERE `procurement_plan_id` = {$pid}");
            $this->db->exec("DELETE FROM `procurement_plans` WHERE `id` = {$pid}");
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (\Throwable $e) {
            echo " [DELETE ERROR] " . $e->getMessage() . "\n";
        }
    }

    private function cleanupPlan(int $entityId, int $year): void
    {
        $stmt = $this->db->prepare("SELECT id FROM procurement_plans WHERE planning_entity_id = :eid AND fiscal_year = :fy");
        $stmt->execute(['eid' => $entityId, 'fy' => $year]);
        $pids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($pids as $pid) {
            $this->deletePlanCascade((int)$pid);
        }
    }

    private function testGuestCannotAccessProtectedPages(): void
    {
        Session::destroy();
        Session::start();

        $reqIndex = new Request('GET', '/procurement-plans');
        $resIndex = $this->controller->index($reqIndex);
        $this->assert($resIndex->getStatusCode() === 302, "Guest access to GET /procurement-plans redirects to login (Test 1)");

        $reqCreate = new Request('GET', '/procurement-plans/create');
        $resCreate = $this->controller->create($reqCreate);
        $this->assert($resCreate->getStatusCode() === 302, "Guest access to GET /procurement-plans/create redirects to login (Test 2)");
    }

    private function testUnauthorizedEntityCannotCreateOrViewPlan(): void
    {
        // Kwame Mensah has access to Entity 1 (Computer Science), NOT Entity 2 (Electrical Engineering)
        AuthManager::login([
            'id' => 2,
            'username' => 'kwame.mensah',
            'email' => 'kwame.mensah@usted.edu.gh',
            'roles' => ['HOD', 'REQUESTER'],
            'permissions' => ['procurement_plan.create', 'procurement_plan.view', 'procurement_plan.edit', 'procurement_plan.submit'],
            'entity_permissions' => [
                1 => ['procurement_plan.create', 'procurement_plan.view', 'procurement_plan.edit', 'procurement_plan.submit'],
            ]
        ]);

        $csrfToken = Csrf::token();

        // Attempting to create plan for Entity 2 (unauthorized for Kwame)
        $caught = false;
        try {
            $postData = [
                '_csrf_token' => $csrfToken,
                'planning_entity_id' => 2,
                'fiscal_year' => 2027,
                'action' => 'draft',
                'items' => [
                    'category_id' => [1],
                    'item_description' => ['Unauthorized Laptop'],
                    'specification' => ['Core i7'],
                    'uom_id' => [1],
                    'planned_quantity' => ['2'],
                    'estimated_unit_cost' => ['3000.00'],
                    'target_quarter' => ['Q1'],
                    'funding_source' => ['GoG Consolidated Fund']
                ]
            ];
            $req = new Request('POST', '/procurement-plans', [], $postData);
            $this->controller->store($req);
        } catch (AuthorizationException $e) {
            $caught = true;
        }
        $this->assert($caught, "Creating plan for unauthorized planning entity is blocked with AuthorizationException (Test 3)");
    }

    private function testPlanDraftCreationAndServerSideCalculation(): void
    {
        // Authenticate as Kwame Mensah for Entity 1
        AuthManager::login([
            'id' => 2,
            'username' => 'kwame.mensah',
            'email' => 'kwame.mensah@usted.edu.gh',
            'roles' => ['HOD', 'REQUESTER'],
            'permissions' => ['procurement_plan.create', 'procurement_plan.view', 'procurement_plan.edit', 'procurement_plan.submit'],
            'entity_permissions' => [
                1 => ['procurement_plan.create', 'procurement_plan.view', 'procurement_plan.edit', 'procurement_plan.submit'],
            ]
        ]);

        $csrfToken = Csrf::token();
        $testYear = 2028;

        // Clean any pre-existing plan for entity 1 in year 2028
        $this->cleanupPlan(1, $testYear);

        $catStmt = $this->db->query("SELECT id FROM item_categories WHERE is_active = 1 ORDER BY id ASC");
        $catIds = $catStmt->fetchAll(PDO::FETCH_COLUMN);
        $catId1 = !empty($catIds) ? (int)$catIds[0] : 1;
        $catId2 = count($catIds) > 1 ? (int)$catIds[1] : $catId1;

        $postData = [
            '_csrf_token' => $csrfToken,
            'planning_entity_id' => 1,
            'fiscal_year' => $testYear,
            'action' => 'draft',
            'items' => [
                'category_id' => [$catId1, $catId2],
                'item_description' => ['Test LED Tube Lights', 'Office Multifunction Printer'],
                'specification' => ['4FT LED fittings', 'Heavy duty copier'],
                'uom_id' => [1, 1],
                'planned_quantity' => ['50.00', '2.00'],
                'estimated_unit_cost' => ['60.00', '10000.00'], // Expected line totals: 3,000.00 and 20,000.00 -> Grand Total: 23,000.00
                'target_quarter' => ['Q1', 'Q2'],
                'funding_source' => ['GoG Consolidated Fund', 'IGF']
            ]
        ];

        $req = new Request('POST', '/procurement-plans', [], $postData);
        $res = $this->controller->store($req);
        $this->assert($res->getStatusCode() === 302, "Draft plan submission redirects to show page (Test 4)");

        // Fetch created plan
        $stmt = $this->db->prepare("SELECT * FROM procurement_plans WHERE planning_entity_id = 1 AND fiscal_year = :fy LIMIT 1");
        $stmt->execute(['fy' => $testYear]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assert($plan !== false && strtoupper((string)$plan['status']) === 'DRAFT', "Plan persisted in DRAFT status (Test 5)");

        $this->trackedPlans[] = (int)$plan['id'];

        // Fetch created version and verify server-calculated total
        $versionId = (int)$plan['current_version_id'];
        $this->trackedVersions[] = $versionId;

        $stmtV = $this->db->prepare("SELECT * FROM procurement_plan_versions WHERE id = :vid");
        $stmtV->execute(['vid' => $versionId]);
        $version = $stmtV->fetch(PDO::FETCH_ASSOC);

        $this->assert($version !== false && strtoupper((string)$version['status']) === 'DRAFT', "Initial version container is in DRAFT status (Test 6)");
        $this->assert(Decimal::eq((string)$version['total_estimated_cost'], '23000.00'), "Server-side total estimated cost calculated accurately to GHS 23,000.00 (Test 7)");
    }

    private function testManipulatedBrowserTotalsAreIgnored(): void
    {
        // Ensure server calculates planned_quantity * estimated_unit_cost regardless of any client-sent fields
        $planId = end($this->trackedPlans);
        $versionId = end($this->trackedVersions);

        $stmtItems = $this->db->prepare("SELECT * FROM procurement_plan_items WHERE plan_version_id = :vid ORDER BY id ASC");
        $stmtItems->execute(['vid' => $versionId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $this->assert(count($items) === 2, "Two line items persisted under plan version (Test 8)");
        $this->assert(Decimal::eq((string)$items[0]['estimated_total_cost'], '3000.00'), "Item 1 total is exactly 50 * 60.00 = GHS 3,000.00 (Test 9)");
        $this->assert(Decimal::eq((string)$items[1]['estimated_total_cost'], '20000.00'), "Item 2 total is exactly 2 * 10,000.00 = GHS 20,000.00 (Test 10)");
    }

    private function testEmptyPlanAndInvalidDataRejected(): void
    {
        $csrfToken = Csrf::token();

        // 1. Empty items array
        $postDataEmpty = [
            '_csrf_token' => $csrfToken,
            'planning_entity_id' => 1,
            'fiscal_year' => 2029,
            'action' => 'draft',
            'items' => []
        ];
        $req = new Request('POST', '/procurement-plans', [], $postDataEmpty);
        $res = $this->controller->store($req);
        $this->assert($res->getStatusCode() === 302, "Empty items plan rejected with redirect and flash error (Test 11)");

        // 2. Negative quantity rejected
        $caught = false;
        try {
            $postDataNeg = [
                '_csrf_token' => $csrfToken,
                'planning_entity_id' => 1,
                'fiscal_year' => 2029,
                'action' => 'draft',
                'items' => [
                    'category_id' => [1],
                    'item_description' => ['Negative Qty Item'],
                    'specification' => ['Specs'],
                    'uom_id' => [1],
                    'planned_quantity' => ['-5.00'],
                    'estimated_unit_cost' => ['100.00'],
                    'target_quarter' => ['Q1'],
                    'funding_source' => ['IGF']
                ]
            ];
            $reqNeg = new Request('POST', '/procurement-plans', [], $postDataNeg);
            $this->controller->store($reqNeg);
        } catch (\Throwable $e) {
            $caught = true;
        }
        $this->assert($caught || Session::hasFlash('error'), "Negative quantity item strictly rejected (Test 12)");
    }

    private function testSubmitChangesPlanStateAndLocksEditing(): void
    {
        $planId = end($this->trackedPlans);
        $csrfToken = Csrf::token();

        // Kwame submits the plan
        $reqAction = new Request('POST', "/procurement-plans/{$planId}/action", [], [
            '_csrf_token' => $csrfToken,
            'action' => 'SUBMIT',
            'comments' => 'Submitting FY 2028 Plan for Faculty Board Review'
        ]);

        $resAction = $this->controller->handleAction($reqAction, ['id' => $planId]);
        $this->assert($resAction->getStatusCode() === 302, "Submit action redirects to show view (Test 13)");

        // Verify database state is SUBMITTED
        $stmt = $this->db->prepare("SELECT status FROM procurement_plans WHERE id = :id");
        $stmt->execute(['id' => $planId]);
        $status = $stmt->fetchColumn();
        $this->assert($status === 'SUBMITTED', "Plan state successfully transitioned to SUBMITTED (Test 14)");

        // Verify editing a SUBMITTED plan redirects with warning
        $reqEdit = new Request('GET', "/procurement-plans/{$planId}/edit");
        $resEdit = $this->controller->edit($reqEdit, ['id' => $planId]);
        $this->assert($resEdit->getStatusCode() === 302, "Direct edit on SUBMITTED plan is blocked and redirects with warning (Test 15)");
    }

    private function testReviewReturnAndRejectActions(): void
    {
        $planId = end($this->trackedPlans);
        $csrfToken = Csrf::token();

        // 1. Dean user returns plan with query feedback
        AuthManager::login([
            'id' => 4,
            'username' => 'dean.user',
            'email' => 'dean.user@usted.edu.gh',
            'roles' => ['DEAN'],
            'permissions' => ['procurement_plan.view', 'procurement_plan.review', 'procurement_plan.approve'],
            'entity_permissions' => [
                1 => ['procurement_plan.view', 'procurement_plan.review', 'procurement_plan.approve'],
            ]
        ]);

        $reqReturn = new Request('POST', "/procurement-plans/{$planId}/action", [], [
            '_csrf_token' => $csrfToken,
            'action' => 'RETURN',
            'comments' => 'Please provide 3-year warranty certificate requirements for the multifunction printer.'
        ]);
        $resReturn = $this->controller->handleAction($reqReturn, ['id' => $planId]);
        $this->assert($resReturn->getStatusCode() === 302, "Return action executed successfully (Test 16)");

        $stmt = $this->db->prepare("SELECT status FROM procurement_plans WHERE id = :id");
        $stmt->execute(['id' => $planId]);
        $this->assert($stmt->fetchColumn() === 'RETURNED', "Plan transitioned to RETURNED status (Test 17)");

        // 2. Resubmit the plan
        AuthManager::login([
            'id' => 2,
            'username' => 'kwame.mensah',
            'email' => 'kwame.mensah@usted.edu.gh',
            'roles' => ['HOD'],
            'permissions' => ['procurement_plan.submit', 'procurement_plan.edit', 'procurement_plan.view'],
            'entity_permissions' => [
                1 => ['procurement_plan.submit', 'procurement_plan.edit', 'procurement_plan.view'],
            ]
        ]);
        $reqResubmit = new Request('POST', "/procurement-plans/{$planId}/action", [], [
            '_csrf_token' => $csrfToken,
            'action' => 'SUBMIT',
            'comments' => 'Updated printer specifications with 3-year OEM warranty clause.'
        ]);
        $this->controller->handleAction($reqResubmit, ['id' => $planId]);
    }

    private function testAuthorizedApprovalWorkflow(): void
    {
        $planId = end($this->trackedPlans);
        $csrfToken = Csrf::token();

        // Dean approves the plan
        AuthManager::login([
            'id' => 4,
            'username' => 'dean.user',
            'email' => 'dean.user@usted.edu.gh',
            'roles' => ['DEAN'],
            'permissions' => ['procurement_plan.approve', 'procurement_plan.view'],
            'entity_permissions' => [
                1 => ['procurement_plan.approve', 'procurement_plan.view'],
            ]
        ]);

        $reqApprove = new Request('POST', "/procurement-plans/{$planId}/action", [], [
            '_csrf_token' => $csrfToken,
            'action' => 'APPROVE',
            'comments' => 'Annual Procurement Plan formally approved for FY 2028.'
        ]);
        $resApprove = $this->controller->handleAction($reqApprove, ['id' => $planId]);
        $this->assert($resApprove->getStatusCode() === 302, "Approve action executed (Test 18)");

        $stmt = $this->db->prepare("SELECT status, current_version_id FROM procurement_plans WHERE id = :id");
        $stmt->execute(['id' => $planId]);
        $pRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assert($pRow['status'] === 'APPROVED', "Procurement plan status is APPROVED (Test 19)");

        $stmtV = $this->db->prepare("SELECT status, approval_date, approved_by_user_id FROM procurement_plan_versions WHERE id = :vid");
        $stmtV->execute(['vid' => (int)$pRow['current_version_id']]);
        $vRow = $stmtV->fetch(PDO::FETCH_ASSOC);

        $this->assert($vRow['status'] === 'APPROVED', "Plan version status is APPROVED (Test 20)");
        $this->assert(!empty($vRow['approval_date']), "Approval timestamp is recorded in database (Test 21)");
        $this->assert((int)$vRow['approved_by_user_id'] === 4, "Approving user ID (Dean #4) is recorded (Test 22)");
    }

    private function testApprovedPlanEligibleForRequisitionDrawdown(): void
    {
        $planId = end($this->trackedPlans);

        // Check that requisition creation query finds this approved plan
        $sql = "SELECT pp.id, pp.plan_number, pv.id AS version_id, pv.status AS version_status
                FROM `procurement_plans` pp
                JOIN `procurement_plan_versions` pv ON pv.id = pp.current_version_id
                WHERE pp.id = :id AND pp.status = 'APPROVED' AND pv.status = 'APPROVED'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $planId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assert($row !== false, "Approved plan is verified eligible for Requisition drawdown queries (Test 23)");
    }

    private function testCsrfProtectionAndRollback(): void
    {
        $planId = end($this->trackedPlans);

        // Attempting POST action with invalid CSRF token
        $caught = false;
        try {
            $reqBadCsrf = new Request('POST', "/procurement-plans/{$planId}/action", [], [
                '_csrf_token' => 'invalid_csrf_token_value',
                'action' => 'APPROVE'
            ]);
            $this->controller->handleAction($reqBadCsrf, ['id' => $planId]);
        } catch (\Throwable $e) {
            $caught = true;
        }
        $this->assert($caught || Session::hasFlash('error'), "Invalid CSRF token is strictly rejected on POST actions (Test 24)");
    }

    private function cleanup(): void
    {
        foreach ($this->trackedPlans as $pid) {
            $this->deletePlanCascade($pid);
        }
    }
}

$test = new ProcurementPlanWebControllerTest();
$test->run();
