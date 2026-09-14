<?php
require_once __DIR__ . '/../core/autoload.php';
$db = Promis\Core\Database\Connection::get();
// Find test requisitions created with 'test' or 'AI' or 'justification'
$stmt = $db->query("SELECT id, requisition_number, justification FROM requisitions WHERE justification LIKE '%AI%' OR justification LIKE '%test%' OR justification LIKE '%purchase attempt%' OR justification LIKE '%browser%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " test requisitions to clean:\n";
foreach ($rows as $r) {
    echo " - Requisition #{$r['id']}: {$r['requisition_number']} ({$r['justification']})\n";
    $db->prepare("DELETE FROM requisition_items WHERE requisition_id = :id")->execute([':id' => $r['id']]);
    $db->prepare("DELETE FROM workflow_action_logs WHERE document_type = 'REQUISITION' AND document_id = :id")->execute([':id' => $r['id']]);
    $db->prepare("DELETE FROM requisitions WHERE id = :id")->execute([':id' => $r['id']]);
}
echo "Cleaned successfully.\n";
