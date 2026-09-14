<?php
require_once 'core/autoload.php';
Promis\Core\App::bootstrap('.');
$db = Promis\Core\Database\Connection::get();
$rows = $db->query('SELECT id, plan_number, planning_entity_id, status FROM procurement_plans')->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
