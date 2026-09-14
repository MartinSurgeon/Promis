<?php
require_once 'core/autoload.php';
Promis\Core\App::bootstrap('.');
$db = Promis\Core\Database\Connection::get();

$perms = [
    'procurement_plan.view' => 'View Procurement Plan',
    'procurement_plan.create' => 'Formulate Procurement Plan',
    'procurement_plan.edit' => 'Edit Procurement Plan',
    'procurement_plan.submit' => 'Submit Procurement Plan',
    'procurement_plan.approve' => 'Approve Procurement Plan',
    'procurement_plan.reject' => 'Reject Procurement Plan',
    'procurement_plan.return' => 'Return Procurement Plan Query',
    'procurement_plan.review' => 'Review Procurement Plan',
    'procurement_plan.revise' => 'Revise Procurement Plan',
];

$permIds = [];
foreach ($perms as $code => $desc) {
    $stmt = $db->prepare("SELECT id FROM permissions WHERE permission_code = :code");
    $stmt->execute(['code' => $code]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $ins = $db->prepare("INSERT INTO permissions (permission_code, module_area, description, created_at) VALUES (:code, 'PLANNING', :desc, NOW())");
        $ins->execute(['code' => $code, 'desc' => $desc]);
        $id = $db->lastInsertId();
    }
    $permIds[$code] = (int)$id;
}

echo "Permissions map:\n";
print_r($permIds);

// Get role IDs
$roles = $db->query("SELECT id, role_code FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
echo "Roles map:\n";
print_r($roles);

// Role mappings:
// HOD (3): view, create, edit, submit, revise
// DEAN (4): view, review, approve, return, reject
// PROCUREMENT_OFFICER (6): view, review, approve, return, reject
// ADMIN (1): all permissions
$rolePermMap = [
    'ADMIN' => array_keys($perms),
    'HOD' => ['procurement_plan.view', 'procurement_plan.create', 'procurement_plan.edit', 'procurement_plan.submit', 'procurement_plan.revise'],
    'DEAN' => ['procurement_plan.view', 'procurement_plan.review', 'procurement_plan.approve', 'procurement_plan.return', 'procurement_plan.reject'],
    'PROCUREMENT_OFFICER' => ['procurement_plan.view', 'procurement_plan.review', 'procurement_plan.approve', 'procurement_plan.return', 'procurement_plan.reject'],
    'REQUESTER' => ['procurement_plan.view', 'procurement_plan.create', 'procurement_plan.edit', 'procurement_plan.submit'],
];

foreach ($rolePermMap as $roleCode => $pList) {
    $rId = array_search($roleCode, $roles, true);
    if ($rId !== false) {
        foreach ($pList as $pCode) {
            $pId = $permIds[$pCode];
            $chk = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = :rid AND permission_id = :pid");
            $chk->execute(['rid' => $rId, 'pid' => $pId]);
            if ($chk->fetchColumn() == 0) {
                $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)")->execute(['rid' => $rId, 'pid' => $pId]);
            }
        }
    }
}

echo "Role permissions seeded successfully.\n";
