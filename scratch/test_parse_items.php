<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
Promis\Core\App::bootstrap(dirname(__DIR__));

use Promis\Src\Presentation\Controller\ProcurementPlanViewController;
use Promis\Src\Planning\Service\ProcurementPlanService;
use Promis\Core\Database\Connection;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Security\Session;

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

$controller = new ProcurementPlanViewController();
$ref = new ReflectionClass($controller);
$m = $ref->getMethod('parseAndSanitizeItems');
$m->setAccessible(true);

$raw = [
    'category_id' => [1, 2],
    'item_description' => ['Test LED Tube Lights', 'Office Multifunction Printer'],
    'specification' => ['4FT LED fittings', 'Heavy duty copier'],
    'uom_id' => [1, 1],
    'planned_quantity' => ['50.00', '2.00'],
    'estimated_unit_cost' => ['60.00', '10000.00'],
    'target_quarter' => ['Q1', 'Q2'],
    'funding_source' => ['GoG Consolidated Fund', 'IGF']
];

$res = $m->invoke($controller, $raw);

$service = new ProcurementPlanService();
$db = Connection::get();
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("DELETE FROM procurement_plans WHERE planning_entity_id = 1 AND fiscal_year = 2028");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");

try {
    $plan = $service->createDraftPlan(1, 2028, $res, 2);
    echo "Created plan: {$plan->id}\n";
} catch (\Throwable $e) {
    echo "Error in createDraftPlan: " . $e->getMessage() . "\n";
    $curr = $e;
    while ($curr = $curr->getPrevious()) {
        echo "Caused by: " . $curr->getMessage() . "\n";
    }
}
