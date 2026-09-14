<?php
require_once __DIR__ . '/../core/autoload.php';
$db = \Promis\Core\Database\Connection::getInstance();

echo "--- All Planning Entities ---\n";
$stmtPe = $db->query("SELECT * FROM planning_entities");
print_r($stmtPe->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Approved Procurement Plans ---\n";
$stmt2 = $db->query("SELECT pp.id, pp.plan_number, pp.planning_entity_id, pp.fiscal_year, pp.status, pp.current_version_id, ppv.version_number, ppv.status as version_status
                     FROM procurement_plans pp
                     JOIN procurement_plan_versions ppv ON ppv.id = pp.current_version_id");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Plan Items (Sample) ---\n";
$stmt3 = $db->query("SELECT ppi.id, ppi.plan_version_id, ppi.item_description, ppi.requested_quantity, ppi.estimated_unit_cost, ppi.total_cost
                     FROM procurement_plan_items ppi LIMIT 10");
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Permissions per Role (req%) ---\n";
$stmt4 = $db->query("SELECT r.role_code, p.permission_code 
                     FROM roles r 
                     JOIN role_permissions rp ON rp.role_id = r.id 
                     JOIN permissions p ON p.id = rp.permission_id 
                     WHERE p.permission_code LIKE '%req%'");
print_r($stmt4->fetchAll(PDO::FETCH_ASSOC));
