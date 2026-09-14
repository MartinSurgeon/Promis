<?php
require_once __DIR__ . '/../core/autoload.php';
$db = \Promis\Core\Database\Connection::getInstance();

echo "--- workflow_definitions ---\n";
$stmt1 = $db->query("SELECT * FROM workflow_definitions");
print_r($stmt1->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- workflow_step_rules ---\n";
$stmt2 = $db->query("SELECT * FROM workflow_step_rules ORDER BY workflow_definition_id, step_order");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
