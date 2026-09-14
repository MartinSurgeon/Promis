<?php
require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Http\Request;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Session;
use Promis\Src\Presentation\Controller\ProcurementPlanViewController;

App::bootstrap(dirname(__DIR__));
$db = Connection::get();
$controller = new ProcurementPlanViewController($db);

Session::start();
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

// Clean up child tables first
$db->exec("DELETE FROM procurement_plan_items WHERE plan_version_id IN (SELECT id FROM procurement_plan_versions WHERE procurement_plan_id IN (SELECT id FROM procurement_plans WHERE planning_entity_id = 1 AND fiscal_year = {$testYear}))");
$db->exec("DELETE FROM procurement_plan_versions WHERE procurement_plan_id IN (SELECT id FROM procurement_plans WHERE planning_entity_id = 1 AND fiscal_year = {$testYear})");
$db->exec("DELETE FROM procurement_plans WHERE planning_entity_id = 1 AND fiscal_year = {$testYear}");

$postData = [
    '_csrf_token' => $csrfToken,
    'planning_entity_id' => 1,
    'fiscal_year' => $testYear,
    'action' => 'draft',
    'items' => [
        'category_id' => [1, 2],
        'item_description' => ['Test LED Tube Lights', 'Office Multifunction Printer'],
        'specification' => ['4FT LED fittings', 'Heavy duty copier'],
        'uom_id' => [1, 1],
        'planned_quantity' => ['50.00', '2.00'],
        'estimated_unit_cost' => ['60.00', '10000.00'],
        'target_quarter' => ['Q1', 'Q2'],
        'funding_source' => ['GoG Consolidated Fund', 'IGF']
    ]
];

$req = new Request('POST', '/procurement-plans', [], $postData);
try {
    $res = $controller->store($req);
    echo "Status Code: " . $res->getStatusCode() . "\n";
    echo "Flash error: " . Session::getFlash('error') . "\n";
    echo "Flash success: " . Session::getFlash('success') . "\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
