<?php
require_once __DIR__ . '/../core/autoload.php';
Promis\Core\App::bootstrap('.');
$db = Promis\Core\Database\Connection::get();

echo "=== ENTITY_HIERARCHIES TABLE STRUCTURE ===\n";
$cols = $db->query("SHOW FULL COLUMNS FROM entity_hierarchies")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  {$c['Field']} | {$c['Type']} | Null:{$c['Null']} | Key:{$c['Key']} | Default:{$c['Default']}\n";
}

echo "\n=== ENTITY_HIERARCHIES DATA ===\n";
print_r($db->query("SELECT * FROM entity_hierarchies")->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== ENTITY_HIERARCHIES INDEXES ===\n";
$idxs = $db->query("SHOW INDEX FROM entity_hierarchies")->fetchAll(PDO::FETCH_ASSOC);
foreach ($idxs as $i) {
    echo "  {$i['Key_name']} | Col:{$i['Column_name']} | Unique:" . ($i['Non_unique'] ? 'NO' : 'YES') . "\n";
}

echo "\n=== ROLES TABLE ===\n";
print_r($db->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== PERMISSIONS ===\n";
print_r($db->query("SELECT * FROM permissions WHERE permission_code LIKE '%entity%' OR permission_code LIKE '%admin%' OR permission_code LIKE '%manage%'")->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== ALL ROUTES (from App.php) ===\n";
// Just list route-related sections
