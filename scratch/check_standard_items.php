<?php
require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Database\Connection;

App::bootstrap(dirname(__DIR__));
$db = Connection::getInstance();

$stmt = $db->query('SHOW COLUMNS FROM standard_items');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $db->query('SELECT * FROM standard_items LIMIT 5');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
