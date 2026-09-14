<?php
require_once __DIR__ . '/../core/autoload.php';
$db = Promis\Core\Database\Connection::get();
$rows = $db->query("SELECT ppi.id, ppi.plan_version_id, ppi.item_description, ppi.planned_quantity, ppi.estimated_unit_cost,
                           COALESCE(SUM(ri.requested_quantity), 0.00) as used_qty
                    FROM procurement_plan_items ppi
                    LEFT JOIN requisition_items ri ON ri.procurement_plan_item_id = ppi.id
                    LEFT JOIN requisitions r ON r.id = ri.requisition_id AND r.status != 'REJECTED'
                    WHERE ppi.plan_version_id = 1
                    GROUP BY ppi.id")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
