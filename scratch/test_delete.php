<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
Promis\Core\App::bootstrap(dirname(__DIR__));

use Promis\Core\Database\Connection;

$db = Connection::getInstance();
try {
    $db->exec("DELETE FROM procurement_plans WHERE planning_entity_id = 1 AND fiscal_year = 2028");
    echo "DELETE SUCCESS\n";
} catch (\Throwable $e) {
    echo "DELETE FAILED: " . $e->getMessage() . "\n";
}
