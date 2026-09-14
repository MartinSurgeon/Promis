<?php
require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Database\Connection;

App::bootstrap(dirname(__DIR__));
$db = Connection::getInstance();

$out = [];

// 1. All Tables
$stmt = $db->query("SHOW TABLES");
$out['all_tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Planning specific tables schema
$planningTables = [
    'procurement_plans',
    'procurement_plan_versions',
    'procurement_plan_items',
    'plan_review_cycles',
    'plan_revision_records',
    'planning_entities',
    'item_categories',
    'units_of_measure',
    'workflow_definitions',
    'workflow_action_logs',
    'users',
    'user_entity_roles',
    'roles',
    'permissions',
    'role_permissions'
];

foreach ($planningTables as $t) {
    $stmt = $db->query("SHOW FULL COLUMNS FROM {$t}");
    $out['columns'][$t] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtKeys = $db->query("SHOW KEYS FROM {$t}");
    $out['indexes'][$t] = $stmtKeys->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Foreign Keys
$fkQuery = "
    SELECT 
        TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        TABLE_SCHEMA = 'promis_db' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND TABLE_NAME IN ('" . implode("','", $planningTables) . "')
";
$out['foreign_keys'] = $db->query($fkQuery)->fetchAll(PDO::FETCH_ASSOC);

// 4. Workflow Definitions & Details in DB
$out['workflow_definitions_rows'] = $db->query("SELECT * FROM workflow_definitions")->fetchAll(PDO::FETCH_ASSOC);

// Check if there are other workflow tables
$wfTables = array_filter($out['all_tables'], function($tbl) {
    return strpos($tbl, 'workflow') !== false;
});
$out['workflow_related_tables'] = array_values($wfTables);

foreach ($out['workflow_related_tables'] as $wft) {
    $out['workflow_table_rows'][$wft] = $db->query("SELECT * FROM {$wft} LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
}

// 5. Check permissions
$out['all_permissions'] = $db->query("SELECT * FROM permissions")->fetchAll(PDO::FETCH_ASSOC);

// 6. Check existing plans and versions
$out['existing_plans_sample'] = $db->query("SELECT * FROM procurement_plans LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$out['existing_versions_sample'] = $db->query("SELECT * FROM procurement_plan_versions LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$out['existing_items_sample'] = $db->query("SELECT * FROM procurement_plan_items LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

file_put_contents(__DIR__ . '/planning_pre_verification_report.json', json_encode($out, JSON_PRETTY_PRINT));
echo "Planning pre-verification detailed extraction complete!\n";
