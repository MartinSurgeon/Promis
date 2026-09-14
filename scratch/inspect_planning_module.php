<?php
require_once __DIR__ . '/../core/autoload.php';

$db = Promis\Core\Database\Connection::get();

echo "=== TABLES IN DB ===\n";
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\n=== PROCUREMENT PLANS COLUMNS ===\n";
$cols = $db->query("DESCRIBE procurement_plans")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);

echo "\n=== PROCUREMENT PLAN VERSIONS COLUMNS ===\n";
$cols = $db->query("DESCRIBE procurement_plan_versions")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);

echo "\n=== PROCUREMENT PLAN ITEMS COLUMNS ===\n";
$cols = $db->query("DESCRIBE procurement_plan_items")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);

echo "\n=== ITEM CATEGORIES COLUMNS ===\n";
$cols = $db->query("DESCRIBE item_categories")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
print_r($db->query("SELECT * FROM item_categories")->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== UNITS OF MEASURE COLUMNS ===\n";
$cols = $db->query("DESCRIBE units_of_measure")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
print_r($db->query("SELECT * FROM units_of_measure LIMIT 10")->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== PLANNING ENTITIES ===\n";
print_r($db->query("SELECT id, entity_code, entity_name FROM planning_entities WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC));
