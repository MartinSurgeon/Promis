<?php
require_once __DIR__ . '/../core/autoload.php';
$db = \Promis\Core\Database\Connection::getInstance();

echo "--- All Procurement Plans ---\n";
$stmt1 = $db->query("SELECT * FROM procurement_plans");
print_r($stmt1->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- All Procurement Plan Versions ---\n";
$stmt2 = $db->query("SELECT * FROM procurement_plan_versions");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- All Planning Entities ---\n";
$stmtPe = $db->query("SELECT * FROM planning_entities");
print_r($stmtPe->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Existing Requisitions in DB ---\n";
$stmtReq = $db->query("SELECT id, requisition_number, planning_entity_id, fiscal_year, approved_plan_version_id, status FROM requisitions");
print_r($stmtReq->fetchAll(PDO::FETCH_ASSOC));
