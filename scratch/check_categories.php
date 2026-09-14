<?php
require_once 'core/autoload.php';
Promis\Core\App::bootstrap('.');
$db = Promis\Core\Database\Connection::get();
echo "--- ITEM CATEGORIES ---\n";
$cats = $db->query('SELECT id, category_code, category_name FROM item_categories')->fetchAll(PDO::FETCH_ASSOC);
print_r($cats);
echo "--- UNITS OF MEASURE ---\n";
$uoms = $db->query('SELECT id, uom_code, uom_name FROM units_of_measure')->fetchAll(PDO::FETCH_ASSOC);
print_r($uoms);
