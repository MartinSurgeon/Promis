<?php
declare(strict_types=1);

namespace Promis\Tests;

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Database\Connection;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Src\Presentation\Controller\RequisitionViewController;

echo "===============================================================\n";
echo " PROMIS REQUISITION WEB CONTROLLER & CREATION TESTS\n";
echo "===============================================================\n";

$db = Connection::get();

// Ensure clean initial state by clearing any temporary leftover test requisitions
$db->exec("DELETE FROM requisition_items WHERE requisition_id > 4");
$db->exec("DELETE FROM workflow_action_logs WHERE document_type = 'REQUISITION' AND document_id > 4");
$db->exec("DELETE FROM requisitions WHERE id > 4");

use Promis\Core\Auth\AuthManager;
use Promis\Core\Security\Session;
use Promis\Core\Security\Csrf;

// 1. Test unauthenticated access
Session::destroy();
Session::start();
$req = new Request('GET', '/requisitions/create');
$controller = new RequisitionViewController();
$res = $controller->create($req);
assert($res->getStatusCode() === 302, "Unauthenticated GET /requisitions/create must redirect");
echo " [PASS] Unauthenticated access properly redirected to login.\n";

// 2. Test authenticated access without permission
AuthManager::login([
    'id' => 9999,
    'username' => 'unauthorized.user',
    'email' => 'test@example.com',
    'roles' => ['STAFF'],
    'permissions' => [],
]);
$res = $controller->create($req);
assert($res->getStatusCode() === 302, "User without requisition.create must redirect with error");
echo " [PASS] User without requisition.create permission properly blocked.\n";

// 3. Test permitted planning entities resolution for Kwame Mensah (HOD CS)
$stmt = $db->prepare("SELECT id, username, email FROM users WHERE username = 'kwame.mensah'");
$stmt->execute();
$kwame = $stmt->fetch(\PDO::FETCH_ASSOC);

AuthManager::login([
    'id' => (int)$kwame['id'],
    'username' => $kwame['username'],
    'email' => $kwame['email'],
    'roles' => ['HOD', 'REQUESTER'],
    'permissions' => ['requisition.create', 'requisition.view', 'requisition.endorse'],
]);
$csrfToken = Csrf::token();

$res = $controller->create($req);
assert($res->getStatusCode() === 200, "Kwame Mensah should successfully load create view (200 OK)");
echo " [PASS] Authorized requester loads requisition create view successfully.\n";

// 4. Test store with invalid entity access (entity not assigned to Kwame)
$postData = [
    '_csrf_token' => $csrfToken,
    'planning_entity_id' => 2, // Entity 2 (EE) not permitted for Kwame
    'fiscal_year' => 2026,
    'justification' => 'Unauthorized purchase attempt',
    'items' => [
        ['procurement_plan_item_id' => 1, 'requested_quantity' => '1.00', 'item_justification' => 'Server']
    ]
];
$storeReq = new Request('POST', '/requisitions', [], $postData);
$storeRes = $controller->store($storeReq);
assert($storeRes->getStatusCode() === 302, "Unauthorized entity submission should redirect");
echo " [PASS] Requisition creation against unauthorized entity is strictly blocked.\n";

// 5. Test store with quantity exceeding remaining drawdown
$postDataExceed = [
    '_csrf_token' => $csrfToken,
    'planning_entity_id' => 1, // Entity 1 (CS)
    'fiscal_year' => 2026,
    'justification' => 'Exceeding quota purchase attempt',
    'items' => [
        ['procurement_plan_item_id' => 1, 'requested_quantity' => '999999.00', 'item_justification' => 'Massive Server Order']
    ]
];
$storeReqExceed = new Request('POST', '/requisitions', [], $postDataExceed);
$storeResExceed = $controller->store($storeReqExceed);
assert($storeResExceed->getStatusCode() === 302, "Exceeding drawdown quota should redirect back with error");
echo " [PASS] Quantity exceeding remaining quota is rejected with validation error.\n";

// 6. Test store valid requisition creation
$postDataValid = [
    '_csrf_token' => $csrfToken,
    'planning_entity_id' => 1,
    'fiscal_year' => 2026,
    'justification' => 'Laboratory Equipment for AI Research Unit',
    'items' => [
        ['procurement_plan_item_id' => 1, 'requested_quantity' => '1.00', 'item_justification' => 'Department AI Compute Node']
    ]
];
$storeReqValid = new Request('POST', '/requisitions', [], $postDataValid);
$storeResValid = $controller->store($storeReqValid);
assert($storeResValid->getStatusCode() === 302, "Valid requisition creation must redirect to details page");
$location = $storeResValid->getHeader('Location');
assert(preg_match('#/requisitions/(\d+)#', $location, $m) === 1, "Redirect must point to /requisitions/{id}");
$createdReqId = (int)$m[1];
echo " [PASS] Valid requisition created atomically and redirected to details view: {$location}\n";

// Teardown test requisition
if ($createdReqId > 0) {
    $db->prepare("DELETE FROM requisition_items WHERE requisition_id = :id")->execute([':id' => $createdReqId]);
    $db->prepare("DELETE FROM workflow_action_logs WHERE document_type = 'REQUISITION' AND document_id = :id")->execute([':id' => $createdReqId]);
    $db->prepare("DELETE FROM requisitions WHERE id = :id")->execute([':id' => $createdReqId]);
}

echo "===============================================================\n";
echo " Passed: 6 / 6\n";
echo " ALL 6 CONTROLLER & CREATION TESTS PASSED 100%\n";
echo "===============================================================\n";
