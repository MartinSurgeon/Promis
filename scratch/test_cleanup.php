<?php
require_once 'core/autoload.php';
Promis\Core\App::bootstrap('.');
$db = Promis\Core\Database\Connection::get();

echo "--- plan_revision_records columns ---\n";
print_r($db->query('DESCRIBE plan_revision_records')->fetchAll(PDO::FETCH_ASSOC));

echo "--- plan_review_cycles columns ---\n";
print_r($db->query('DESCRIBE plan_review_cycles')->fetchAll(PDO::FETCH_ASSOC));
