<?php
require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Database\Connection;

App::bootstrap(dirname(__DIR__));
$db = Connection::getInstance();

$tables = [
    'procurement_plans',
    'procurement_plan_versions',
    'procurement_plan_items',
    'planning_entities',
    'item_categories',
    'units_of_measure',
    'workflow_definitions',
    'workflow_instances',
    'workflow_steps',
    'workflow_transitions',
    'workflow_action_logs',
    'plan_review_cycles',
    'plan_revision_records',
    'users',
    'user_entity_roles',
    'roles',
    'permissions',
    'role_permissions'
];

$report = [
    'tables' => [],
    'foreign_keys' => [],
    'unique_keys' => [],
    'workflow_definitions' => [],
    'planning_permissions' => [],
    'existing_plan_statuses' => [],
    'existing_version_statuses' => []
];

foreach ($tables as $t) {
    try {
        $stmt = $db->query("SHOW FULL COLUMNS FROM {$t}");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $report['tables'][$t] = array_map(function($c) {
            return [
                'field' => $c['Field'],
                'type' => $c['Type'],
                'null' => $c['Null'],
                'key' => $c['Key'],
                'default' => $c['Default']
            ];
        }, $cols);
        
        $stmtKeys = $db->query("SHOW KEYS FROM {$t}");
        $keys = $stmtKeys->fetchAll(PDO::FETCH_ASSOC);
        $report['unique_keys'][$t] = array_values(array_filter($keys, function($k) {
            return $k['Non_unique'] == 0;
        }));
    } catch (\Throwable $e) {
        $report['tables'][$t] = 'ERROR: ' . $e->getMessage();
    }
}

// Check Foreign Keys
$fkQuery = "
    SELECT 
        TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        TABLE_SCHEMA = 'promis_db' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND TABLE_NAME IN ('" . implode("','", $tables) . "')
";
$stmtFk = $db->query($fkQuery);
$report['foreign_keys'] = $stmtFk->fetchAll(PDO::FETCH_ASSOC);

// Check workflow definitions for procurement plans
try {
    $stmtWf = $db->query("SELECT * FROM workflow_definitions WHERE entity_type IN ('PROCUREMENT_PLAN', 'PLAN', 'PLAN_VERSION') OR entity_type LIKE '%PLAN%'");
    $report['workflow_definitions'] = $stmtWf->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $report['workflow_definitions'] = 'ERROR: ' . $e->getMessage();
}

// Check permissions related to planning
try {
    $stmtPerms = $db->query("SELECT * FROM permissions WHERE name LIKE '%plan%'");
    $report['planning_permissions'] = $stmtPerms->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $report['planning_permissions'] = 'ERROR: ' . $e->getMessage();
}

// Check sample plans and status values in database
try {
    $stmtPlanStatuses = $db->query("SELECT DISTINCT status FROM procurement_plans");
    $report['existing_plan_statuses'] = $stmtPlanStatuses->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    $report['existing_plan_statuses'] = 'ERROR: ' . $e->getMessage();
}

try {
    $stmtVersionStatuses = $db->query("SELECT DISTINCT status FROM procurement_plan_versions");
    $report['existing_version_statuses'] = $stmtVersionStatuses->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    $report['existing_version_statuses'] = 'ERROR: ' . $e->getMessage();
}

// Check workflow transitions for all workflow definitions
try {
    $stmtWfTrans = $db->query("SELECT wd.name as def_name, wd.entity_type, ws1.name as from_step, ws2.name as to_step, wt.action_name, wt.required_permission FROM workflow_transitions wt JOIN workflow_steps ws1 ON wt.from_step_id = ws1.id JOIN workflow_steps ws2 ON wt.to_step_id = ws2.id JOIN workflow_definitions wd ON wt.workflow_definition_id = wd.id");
    $report['all_workflow_transitions'] = $stmtWfTrans->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $report['all_workflow_transitions'] = 'ERROR: ' . $e->getMessage();
}

file_put_contents(__DIR__ . '/schema_verification_output.json', json_encode($report, JSON_PRETTY_PRINT));
echo "Verification schema dump complete. Written to scratch/schema_verification_output.json\n";
