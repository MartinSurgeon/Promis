<?php
require_once 'core/autoload.php';
Promis\Core\App::bootstrap('.');
$userRepo = new Promis\Src\Identity\Repository\UserRepository();
$user = $userRepo->findById(2);
print_r($user->toSessionArray());
