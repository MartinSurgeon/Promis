<?php
require_once 'core/autoload.php';
Promis\Core\App::bootstrap('.');
$db = Promis\Core\Database\Connection::get();
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
