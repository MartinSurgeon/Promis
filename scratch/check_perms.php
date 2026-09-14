<?php
require_once 'core/autoload.php';
Promis\Core\App::bootstrap('.');
$db = Promis\Core\Database\Connection::get();

echo "--- permissions table ---\n";
print_r($db->query("SELECT * FROM permissions")->fetchAll(PDO::FETCH_ASSOC));

echo "--- role_permissions for roles 2,3 ---\n";
print_r($db->query("SELECT rp.*, p.name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id IN (1,2,3,4,5,6)")->fetchAll(PDO::FETCH_ASSOC));
