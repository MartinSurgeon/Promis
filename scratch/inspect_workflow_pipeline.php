<?php
require_once __DIR__ . '/../core/autoload.php';

$db = \Promis\Core\Database\Connection::get();

echo "=== ROLES ===\n";
foreach ($db->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID: {$r['id']}, Code: {$r['role_code']}, Name: {$r['role_name']}, Active: {$r['is_active']}\n";
}

echo "\n=== ROLE PERMISSIONS ===\n";
$rolePerms = $db->query("
    SELECT r.role_code, p.permission_code 
    FROM role_permissions rp 
    JOIN roles r ON r.id = rp.role_id 
    JOIN permissions p ON p.id = rp.permission_id 
    ORDER BY r.role_code, p.permission_code
")->fetchAll(PDO::FETCH_ASSOC);
$byRole = [];
foreach ($rolePerms as $rp) {
    $byRole[$rp['role_code']][] = $rp['permission_code'];
}
foreach ($byRole as $role => $perms) {
    echo "Role [{$role}]: " . implode(', ', $perms) . "\n";
}

echo "\n=== WORKFLOW DEFINITIONS ===\n";
foreach ($db->query("SELECT * FROM workflow_definitions")->fetchAll(PDO::FETCH_ASSOC) as $w) {
    $eType = $w['entity_type_id'] !== null ? $w['entity_type_id'] : 'GLOBAL(NULL)';
    echo "Def #{$w['id']}: {$w['workflow_name']} | Doc: {$w['document_type']} | EntityType: {$eType} | Active: {$w['is_active']}\n";
}

echo "\n=== WORKFLOW STEP RULES ===\n";
foreach ($db->query("
    SELECT s.*, r.role_code 
    FROM workflow_step_rules s 
    JOIN roles r ON r.id = s.required_role_id 
    ORDER BY s.workflow_definition_id, s.step_order
")->fetchAll(PDO::FETCH_ASSOC) as $s) {
    echo "Def #{$s['workflow_definition_id']} | Step {$s['step_order']}: {$s['step_name']} | Role: {$s['role_code']} | Min: {$s['threshold_min_amount']} | Max: {$s['threshold_max_amount']}\n";
}

echo "\n=== EXISTING PLANNING ENTITIES ===\n";
foreach ($db->query("
    SELECT pe.id, pe.entity_code, pe.entity_name, pe.parent_entity_id, et.type_code 
    FROM planning_entities pe 
    JOIN entity_types et ON et.id = pe.entity_type_id 
    ORDER BY pe.id
")->fetchAll(PDO::FETCH_ASSOC) as $pe) {
    $parent = $pe['parent_entity_id'] ? "#{$pe['parent_entity_id']}" : 'ROOT';
    echo "Entity #{$pe['id']} [{$pe['entity_code']}] {$pe['entity_name']} (Type: {$pe['type_code']}, Parent: {$parent})\n";
}
