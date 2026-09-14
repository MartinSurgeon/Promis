<?php
require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Database\Connection;

App::bootstrap(dirname(__DIR__));
$db = Connection::getInstance();

echo "=== PERMISSIONS IN DB ===\n";
$stmt = $db->query('SELECT p.id, p.permission_code, p.module_area, p.description FROM permissions p');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "=== ROLES IN DB ===\n";
$stmtRoles = $db->query('SELECT * FROM roles');
print_r($stmtRoles->fetchAll(PDO::FETCH_ASSOC));

echo "=== ROLE PERMISSIONS IN DB ===\n";
$stmt2 = $db->query('SELECT r.role_code, p.permission_code FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id ORDER BY r.role_code, p.permission_code');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "=== USER ENTITY ROLES IN DB ===\n";
$stmt3 = $db->query('SELECT uer.id, u.username, pe.entity_name, r.role_code, uer.status FROM user_entity_roles uer JOIN users u ON u.id = uer.user_id JOIN planning_entities pe ON pe.id = uer.planning_entity_id JOIN roles r ON r.id = uer.role_id');
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
