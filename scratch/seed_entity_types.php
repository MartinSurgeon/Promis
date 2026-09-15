<?php
require_once __DIR__ . '/../core/autoload.php';
Promis\Core\App::bootstrap('.');
$db = Promis\Core\Database\Connection::get();

$seeds = [
    ['UNIV', 'University', 'Top-level institutional entity'],
    ['FAC', 'Faculty', 'Academic faculty grouping multiple departments'],
    ['UNIT', 'Administrative Unit', 'Non-academic administrative office'],
];

foreach ($seeds as [$code, $name, $desc]) {
    $existing = $db->prepare("SELECT id FROM entity_types WHERE type_code = :code");
    $existing->execute(['code' => $code]);
    if ($existing->fetch()) {
        echo "[SKIP] entity_type '{$code}' already exists.\n";
    } else {
        $stmt = $db->prepare("INSERT INTO entity_types (type_code, type_name, description) VALUES (:code, :name, :desc)");
        $stmt->execute(['code' => $code, 'name' => $name, 'desc' => $desc]);
        echo "[INSERTED] entity_type '{$code}' - {$name}\n";
    }
}

echo "\n=== ALL ENTITY TYPES ===\n";
$all = $db->query("SELECT * FROM entity_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $row) {
    echo "  ID:{$row['id']} | Code:{$row['type_code']} | Name:{$row['type_name']} | Desc:{$row['description']} | Active:{$row['is_active']}\n";
}
