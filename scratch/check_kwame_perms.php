<?php
require_once __DIR__ . '/../core/autoload.php';
$db = Promis\Core\Database\Connection::get();
$userRepo = new Promis\Src\Identity\Repository\UserRepository($db);
$kwame = $userRepo->findByUsername('kwame.mensah');
echo "Kwame user roles: " . implode(', ', $kwame->roles) . "\n";
echo "Kwame permissions: " . implode(', ', $kwame->permissions) . "\n";
echo "Kwame entity permissions: " . print_r($kwame->entityPermissions, true) . "\n";
