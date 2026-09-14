<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
Promis\Core\App::bootstrap(dirname(__DIR__));

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

$db = Connection::getInstance();
$db->exec("DELETE FROM procurement_plans WHERE planning_entity_id = 1 AND fiscal_year = 2028");

$service = new ProcurementPlanService();

try {
    $plan = $service->createDraftPlan(
        planningEntityId: 1,
        fiscalYear: 2028,
        items: [
            [
                'standard_item_id' => 1,
                'category_id' => 1,
                'item_description' => 'Test LED Tube Lights',
                'specification' => '4FT LED fittings',
                'uom_id' => 1,
                'planned_quantity' => '50.00',
                'estimated_unit_cost' => '60.00',
                'target_quarter' => 'Q1',
                'funding_source' => 'GoG'
            ]
        ],
        userId: 2
    );
    echo "SUCCESS: ID={$plan->id}, Number={$plan->planNumber}\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    if ($e->getPrevious()) {
        echo "PREVIOUS: " . $e->getPrevious()->getMessage() . "\n";
    }
}
