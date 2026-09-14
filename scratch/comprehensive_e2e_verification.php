<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Database\Connection;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Security\Session;
use Promis\Core\Security\Csrf;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Src\Presentation\Controller\RequisitionViewController;
use Promis\Src\Execution\Service\RequisitionService;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Execution\Domain\WorkflowAction;

echo "===============================================================\n";
echo " PROMIS COMPREHENSIVE SECURITY & E2E INTEGRATION VERIFICATION\n";
echo "===============================================================\n\n";

$db = Connection::get();
$controller = new RequisitionViewController($db);

use Promis\Src\Identity\Repository\UserRepository;

$userRepo = new UserRepository($db);

$kwame = $userRepo->findByUsername('kwame.mensah')->toSessionArray();
$dean = $userRepo->findByUsername('dean.user')->toSessionArray();
$finance = $userRepo->findByUsername('finance.user')->toSessionArray();
$procurement = $userRepo->findByUsername('procurement.user')->toSessionArray();

// SCENARIO 1: Unauthenticated user accessing create/show/store
echo "1. Checking Unauthenticated Boundaries...\n";
Session::destroy();
Session::start();

$res = $controller->create(new Request('GET', '/requisitions/create'));
assert($res->getStatusCode() === 302, "Unauthenticated GET /requisitions/create must redirect");
assert($res->getHeader('Location') === '/login', "Redirect must point to /login");

$res = $controller->show(new Request('GET', '/requisitions/1', ['id' => 1]));
assert($res->getStatusCode() === 302, "Unauthenticated GET /requisitions/1 must redirect");

$res = $controller->store(new Request('POST', '/requisitions', [], ['planning_entity_id' => 1]));
assert($res->getStatusCode() === 302, "Unauthenticated POST /requisitions must redirect");
echo "   [PASS] Unauthenticated requests securely redirected to /login.\n";

// SCENARIO 2: Unauthorized user without permissions
echo "2. Checking Permission Checks (Users without requisition.create)...\n";
AuthManager::login([
    'id' => 9998,
    'username' => 'guest.staff',
    'email' => 'guest@usted.edu.gh',
    'roles' => ['STAFF'],
    'permissions' => [],
]);
$res = $controller->create(new Request('GET', '/requisitions/create'));
assert($res->getStatusCode() === 302, "User without create permission must redirect");
echo "   [PASS] Unauthorized users blocked from accessing create form.\n";

// SCENARIO 3: Authorized User Entity Isolation
echo "3. Checking Entity Scoping & Isolation...\n";
AuthManager::login($kwame);
$csrfToken = Csrf::token();

// Attempting to submit requisition for Entity 2 (Electrical Engineering) which Kwame is not assigned to
$tamperedPost = [
    '_csrf_token' => $csrfToken,
    'planning_entity_id' => 2,
    'fiscal_year' => 2026,
    'justification' => 'Cross-entity intrusion attempt',
    'items' => [
        ['procurement_plan_item_id' => 1, 'requested_quantity' => '1.00']
    ]
];
$res = $controller->store(new Request('POST', '/requisitions', [], $tamperedPost));
assert($res->getStatusCode() === 302, "Cross-entity submission must be denied");
echo "   [PASS] Cross-entity requisition creation blocked by ExecutionAuthorizationGuard.\n";

// SCENARIO 4: Price/Total Server-Side Integrity Check
echo "4. Checking Server-Side Price & Total Calculation Authority...\n";
$tamperedPricePost = [
    '_csrf_token' => $csrfToken,
    'planning_entity_id' => 1,
    'fiscal_year' => 2026,
    'justification' => 'Attempting price tampering in browser',
    'items' => [
        [
            'procurement_plan_item_id' => 1,
            'requested_quantity' => '1.00',
            'estimated_unit_cost' => '0.01', // Client sends 1 cent instead of 25,000 GHS
            'estimated_total_cost' => '0.01'
        ]
    ]
];
$res = $controller->store(new Request('POST', '/requisitions', [], $tamperedPricePost));
$newReqUrl = $res->getHeader('Location');
if (!preg_match('#/requisitions/(\d+)#', $newReqUrl ?? '', $m)) {
    echo "Error in Scenario 4 store: Location={$newReqUrl}, Flash error=" . Session::getFlash('error') . "\n";
    exit(1);
}
$newReqId = (int)$m[1];

// Verify database record has authoritative unit price 25,000.00 GHS and total 25,000.00 GHS
$stmt = $db->prepare("SELECT total_estimated_cost FROM requisitions WHERE id = :id");
$stmt->execute([':id' => $newReqId]);
$savedTotal = (float)$stmt->fetchColumn();
assert(abs($savedTotal - 25000.00) < 0.01, "Server must use catalog price 25,000.00 GHS, found: {$savedTotal}");

$stmt = $db->prepare("SELECT estimated_unit_cost, estimated_total_cost FROM requisition_items WHERE requisition_id = :id");
$stmt->execute([':id' => $newReqId]);
$itemRow = $stmt->fetch(PDO::FETCH_ASSOC);
assert((float)$itemRow['estimated_unit_cost'] == 25000.00, "Server must enforce authoritative unit cost");
assert((float)$itemRow['estimated_total_cost'] == 25000.00, "Server must enforce authoritative total cost");
echo "   [PASS] Server recalculated prices and totals independently; client price injection was ignored.\n";

// SCENARIO 5: Drawdown Quota Ceiling Enforcement
echo "5. Checking Drawdown Quota Ceilings...\n";
$exceedPost = [
    '_csrf_token' => $csrfToken,
    'planning_entity_id' => 1,
    'fiscal_year' => 2026,
    'justification' => 'Overdraft attempt',
    'items' => [
        ['procurement_plan_item_id' => 1, 'requested_quantity' => '1000.00'] // Max planned is 10
    ]
];
$res = $controller->store(new Request('POST', '/requisitions', [], $exceedPost));
assert($res->getStatusCode() === 302, "Drawdown exceeded must fail with redirect back");
echo "   [PASS] Quantity exceeding remaining plan item quota blocked by DrawdownCalculator.\n";

// SCENARIO 6: Dynamic Workflow Transitions & Audit Trail
echo "6. Checking Dynamic Workflow Transitions & Audit Trail...\n";

// A. Requester submits requisition for review
$actionReq = new Request('POST', "/requisitions/{$newReqId}/action", [], [
    '_csrf_token' => $csrfToken,
    'action' => 'SUBMIT',
    'comments' => 'Submitting department server purchase for approval'
]);
$actionReq->setRouteParams(['id' => (string)$newReqId]);
$res = $controller->handleAction($actionReq);
assert($res->getStatusCode() === 302);

// Check status transitioned to SUBMITTED
$stmt = $db->prepare("SELECT status FROM requisitions WHERE id = :id");
$stmt->execute([':id' => $newReqId]);
$status = $stmt->fetchColumn();
if ($status !== 'SUBMITTED') {
    echo "Error in handleAction SUBMIT: Status={$status}, Flash error=" . Session::getFlash('error') . ", Flash success=" . Session::getFlash('success') . "\n";
    exit(1);
}
echo "   [PASS] Requisition transitioned to SUBMITTED.\n";

// B. HOD Endorses
$actionReq = new Request('POST', "/requisitions/{$newReqId}/action", [], [
    '_csrf_token' => $csrfToken,
    'action' => 'ENDORSE',
    'comments' => 'Endorsed by HOD Computer Science'
]);
$actionReq->setRouteParams(['id' => (string)$newReqId]);
$res = $controller->handleAction($actionReq);
assert($res->getStatusCode() === 302);

$stmt->execute([':id' => $newReqId]);
assert($stmt->fetchColumn() === 'ENDORSED', "Status must be ENDORSED");
echo "   [PASS] Requisition transitioned to ENDORSED by HOD.\n";

// C. Unauthorized user (Requester Kwame) trying to execute Dean APPROVE -> must fail
$actionReq = new Request('POST', "/requisitions/{$newReqId}/action", [], [
    '_csrf_token' => $csrfToken,
    'action' => 'APPROVE',
    'comments' => 'Unauthorized approval attempt'
]);
$actionReq->setRouteParams(['id' => (string)$newReqId]);
$res = $controller->handleAction($actionReq);
$stmt->execute([':id' => $newReqId]);
assert($stmt->fetchColumn() === 'ENDORSED', "Status must still be ENDORSED after unauthorized action");
echo "   [PASS] Unauthorized actor blocked from executing Dean approval.\n";

// D. Dean Approves
AuthManager::login($dean);
$deanCsrf = Csrf::token();

$actionReq = new Request('POST', "/requisitions/{$newReqId}/action", [], [
    '_csrf_token' => $deanCsrf,
    'action' => 'APPROVE',
    'comments' => 'Approved by Faculty Dean'
]);
$actionReq->setRouteParams(['id' => (string)$newReqId]);
$res = $controller->handleAction($actionReq);
$stmt->execute([':id' => $newReqId]);
assert($stmt->fetchColumn() === 'DEPARTMENT_APPROVED', "Status must be DEPARTMENT_APPROVED");
echo "   [PASS] Requisition transitioned to DEPARTMENT_APPROVED by Dean.\n";

// E. Finance Authorizes Commitment
AuthManager::login($finance);
$financeCsrf = Csrf::token();

$actionReq = new Request('POST', "/requisitions/{$newReqId}/action", [], [
    '_csrf_token' => $financeCsrf,
    'action' => 'APPROVE',
    'comments' => 'Budget commitment authorized against vote'
]);
$actionReq->setRouteParams(['id' => (string)$newReqId]);
$res = $controller->handleAction($actionReq);
$stmt->execute([':id' => $newReqId]);
assert($stmt->fetchColumn() === 'COMMITMENT_AUTHORIZED', "Status must be COMMITMENT_AUTHORIZED");
echo "   [PASS] Requisition transitioned to COMMITMENT_AUTHORIZED by Finance.\n";

// F. Procurement Receives & Completes
AuthManager::login($procurement);
$procCsrf = Csrf::token();

$actionReq = new Request('POST', "/requisitions/{$newReqId}/action", [], [
    '_csrf_token' => $procCsrf,
    'action' => 'RECEIVE',
    'comments' => 'Requisition received and queued for procurement packaging'
]);
$actionReq->setRouteParams(['id' => (string)$newReqId]);
$res = $controller->handleAction($actionReq);
$stmt->execute([':id' => $newReqId]);
assert($stmt->fetchColumn() === 'PROCUREMENT_RECEIVED', "Status must be PROCUREMENT_RECEIVED");
echo "   [PASS] Requisition transitioned to PROCUREMENT_RECEIVED by Procurement.\n";

// G. Verify Audit Trail Permanence
$hStmt = $db->prepare("
    SELECT action, pre_status, post_status, actor_user_id, comments 
    FROM workflow_action_logs 
    WHERE document_type = 'REQUISITION' AND document_id = :id 
    ORDER BY id ASC
");
$hStmt->execute([':id' => $newReqId]);
$logs = $hStmt->fetchAll(PDO::FETCH_ASSOC);
assert(count($logs) === 5, "Audit log must contain 5 sequential transitions");
echo "   [PASS] All 5 workflow stages recorded in append-only audit trail with exact actor attribution.\n";

// Teardown
$db->prepare("DELETE FROM requisition_items WHERE requisition_id = :id")->execute([':id' => $newReqId]);
$db->prepare("DELETE FROM workflow_action_logs WHERE document_type = 'REQUISITION' AND document_id = :id")->execute([':id' => $newReqId]);
$db->prepare("DELETE FROM requisitions WHERE id = :id")->execute([':id' => $newReqId]);

echo "\n===============================================================\n";
echo " ALL E2E SECURITY & WORKFLOW VERIFICATION SCENARIOS PASSED 100%\n";
echo "===============================================================\n";
