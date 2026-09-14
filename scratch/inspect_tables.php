<?php
require_once __DIR__ . '/../core/autoload.php';
$db = \Promis\Core\Database\Connection::getInstance();
$stmt = $db->query('SHOW CREATE TABLE procurement_plans');
echo $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

$stmt2 = $db->query('SHOW CREATE TABLE procurement_plan_versions');
echo $stmt2->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

$stmt3 = $db->query('SHOW CREATE TABLE procurement_plan_items');
echo $stmt3->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

$stmt4 = $db->query('SHOW CREATE TABLE requisitions');
echo $stmt4->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

$stmt5 = $db->query('SHOW CREATE TABLE requisition_items');
echo $stmt5->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";
