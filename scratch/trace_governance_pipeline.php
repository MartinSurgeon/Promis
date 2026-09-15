<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Auth\AuthManager;
use Promis\Core\Auth\Authorization;
use Promis\Core\Database\Connection;
use Promis\Src\Execution\Auth\ExecutionAuthorizationGuard;
use Promis\Src\Execution\Auth\ExecutionPermissions;
use Promis\Src\Execution\Domain\DTO\WorkflowActionRequest;
use Promis\Src\Execution\Domain\RequisitionStatus;
use Promis\Src\Execution\Domain\WorkflowAction;
use Promis\Src\Execution\Service\RequisitionWorkflowService;
use Promis\Src\Identity\Repository\PlanningEntityRepository;
use Promis\Src\Identity\Service\EntityManagementService;

$db = Connection::get();
echo "===============================================================\n";
echo " PROMIS APPROVAL LIFECYCLE & GOVERNANCE PIPELINE TRACE\n";
echo "===============================================================\n\n";

// 1. Inspect existing users & roles
$users = $db->query("
    SELECT u.id, u.username, u.email, u.status, 
           GROUP_CONCAT(DISTINCT r.role_code) as roles,
           GROUP_CONCAT(DISTINCT CONCAT(r.role_code, '@PE:', COALESCE(uer.planning_entity_id, 'GLOBAL'))) as assignments
    FROM users u
    LEFT JOIN user_entity_roles uer ON uer.user_id = u.id AND uer.status = 'ACTIVE'
    LEFT JOIN roles r ON r.id = uer.role_id
    GROUP BY u.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "--- Current Users and Assignments ---\n";
foreach ($users as $u) {
    echo "User #{$u['id']} ({$u['username']}) [Status: {$u['status']}] Roles: {$u['roles']} | Scopes: {$u['assignments']}\n";
}

echo "\n--- Inspecting ExecutionPermissions vs Role Permissions in DB ---\n";
$perms = $db->query("SELECT * FROM permissions WHERE permission_code LIKE 'req%' OR permission_code LIKE 'procurement%' OR permission_code LIKE 'budget%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($perms as $p) {
    echo "Perm #{$p['id']}: {$p['permission_code']}\n";
}

echo "\n--- Inspecting Workflow Engine Logic in RequisitionWorkflowService ---\n";
$wfService = new RequisitionWorkflowService($db);

// Check resolveWorkflowDefinition for entity types
$entityTypes = $db->query("SELECT * FROM entity_types")->fetchAll(PDO::FETCH_ASSOC);
foreach ($entityTypes as $et) {
    echo "Entity Type #{$et['id']} [{$et['type_code']}]: {$et['type_name']}\n";
    // Check if there is an entity with this type
    $pe = $db->query("SELECT id FROM planning_entities WHERE entity_type_id = {$et['id']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($pe) {
        try {
            $def = $wfService->resolveWorkflowDefinition((int)$pe['id']);
            echo "  -> Resolves WorkflowDef #{$def->id}: '{$def->workflowName}'\n";
        } catch (\Throwable $e) {
            echo "  -> WorkflowDef resolution error: {$e->getMessage()}\n";
        }
    } else {
        echo "  -> No planning entity currently exists for this type\n";
    }
}
