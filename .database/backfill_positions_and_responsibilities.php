<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Database\Connection;

$db = Connection::getInstance();

$isDryRun = !in_array('--apply', $argv, true);

echo "========================================================\n";
echo " PROMIS SAFE BACKFILL: POSITIONS & RESPONSIBILITIES\n";
echo " Mode: " . ($isDryRun ? "DRY-RUN (No changes applied)" : "APPLY (Committing changes)") . "\n";
echo "========================================================\n\n";

// Load position IDs
$positions = $db->query("SELECT id, position_code, position_title FROM positions")->fetchAll(PDO::FETCH_ASSOC);
$posMap = [];
foreach ($positions as $p) {
    $posMap[$p['position_code']] = (int)$p['id'];
}

// Load all users with their current user_entity_roles
$users = $db->query("
    SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.position_id, u.assigned_planning_entity_id
    FROM users u
    ORDER BY u.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$proposals = [];

foreach ($users as $u) {
    $uid = (int)$u['id'];
    
    // Get active role assignments
    $stmt = $db->prepare("
        SELECT uer.planning_entity_id, r.role_code, uer.is_primary
        FROM user_entity_roles uer
        JOIN roles r ON r.id = uer.role_id
        WHERE uer.user_id = :uid AND uer.status = 'ACTIVE'
        ORDER BY uer.is_primary DESC, uer.id ASC
    ");
    $stmt->execute([':uid' => $uid]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $assignedEntityId = (int)($u['assigned_planning_entity_id'] ?? 0);
    if ($assignedEntityId === 0 && !empty($assignments)) {
        $assignedEntityId = (int)$assignments[0]['planning_entity_id'];
    }
    
    // Determine position and default operational responsibilities
    $roleCodes = array_column($assignments, 'role_code');
    $proposedPositionCode = null;
    $proposedResponsibilities = [];
    
    if (in_array('ADMIN', $roleCodes, true)) {
        $proposedPositionCode = 'ADMINISTRATOR';
        $proposedResponsibilities[] = 'MANAGE_USERS_SYSTEM';
    } elseif (in_array('DEAN', $roleCodes, true)) {
        $proposedPositionCode = 'DEAN';
        $proposedResponsibilities[] = 'APPROVE_FACULTY_DEPTS';
        $proposedResponsibilities[] = 'RETURN_REQUESTS';
        $proposedResponsibilities[] = 'REJECT_REQUESTS';
        $proposedResponsibilities[] = 'PREPARE_PROCUREMENT_PLANS';
        if (in_array('REQUESTER', $roleCodes, true)) {
            $proposedResponsibilities[] = 'CREATE_SUBMIT_OWN';
        }
    } elseif (in_array('HOD', $roleCodes, true)) {
        $proposedPositionCode = 'HOD';
        $proposedResponsibilities[] = 'RECOMMEND_DEPT';
        $proposedResponsibilities[] = 'RETURN_REQUESTS';
        $proposedResponsibilities[] = 'REJECT_REQUESTS';
        $proposedResponsibilities[] = 'PREPARE_PROCUREMENT_PLANS';
        if (in_array('REQUESTER', $roleCodes, true)) {
            $proposedResponsibilities[] = 'CREATE_SUBMIT_OWN';
        }
    } elseif (in_array('FINANCE_OFFICER', $roleCodes, true)) {
        $proposedPositionCode = 'FINANCE_OFFICER';
        $proposedResponsibilities[] = 'APPROVE_FINANCIAL_COMMITMENTS';
        $proposedResponsibilities[] = 'RETURN_REQUESTS';
        $proposedResponsibilities[] = 'REJECT_REQUESTS';
    } elseif (in_array('PROCUREMENT_OFFICER', $roleCodes, true)) {
        $proposedPositionCode = 'PROCUREMENT_OFFICER';
        $proposedResponsibilities[] = 'RECEIVE_PURCHASED_ITEMS';
        $proposedResponsibilities[] = 'APPROVE_PROCUREMENT_PLANS';
        $proposedResponsibilities[] = 'RETURN_REQUESTS';
        $proposedResponsibilities[] = 'REJECT_REQUESTS';
    } elseif (in_array('REQUESTER', $roleCodes, true)) {
        $proposedPositionCode = 'STAFF_OFFICER';
        $proposedResponsibilities[] = 'CREATE_SUBMIT_OWN';
    } else {
        // Staff without roles
        $proposedPositionCode = 'STAFF_OFFICER';
    }
    
    // Important: APPROVE_OWN is NEVER backfilled!
    $proposedResponsibilities = array_values(array_diff($proposedResponsibilities, ['APPROVE_OWN']));
    
    $proposals[] = [
        'user_id' => $uid,
        'username' => $u['username'],
        'name' => trim($u['first_name'] . ' ' . $u['last_name']),
        'current_position_id' => $u['position_id'],
        'proposed_position' => $proposedPositionCode,
        'proposed_position_id' => $posMap[$proposedPositionCode] ?? null,
        'assigned_entity_id' => $assignedEntityId,
        'proposed_responsibilities' => $proposedResponsibilities,
    ];
}

echo "DRY-RUN REPORT FOR EXISTING USERS (" . count($proposals) . " users inspected):\n";
echo "--------------------------------------------------------------------------------\n";
printf("%-5s | %-16s | %-20s | %-18s | %-8s | %s\n", "ID", "Username", "Name", "Proposed Position", "Entity", "Responsibilities");
echo "--------------------------------------------------------------------------------\n";

foreach ($proposals as $p) {
    $respStr = empty($p['proposed_responsibilities']) ? '[NONE]' : implode(', ', $p['proposed_responsibilities']);
    printf(
        "%-5d | %-16s | %-20s | %-18s | %-8s | %s\n",
        $p['user_id'],
        substr($p['username'], 0, 16),
        substr($p['name'], 0, 20),
        $p['proposed_position'],
        (string)$p['assigned_entity_id'],
        $respStr
    );
}
echo "--------------------------------------------------------------------------------\n";

if ($isDryRun) {
    echo "\nNOTE: This was a dry-run report. No changes were written.\n";
    echo "To apply these changes, re-run with: php .database/backfill_positions_and_responsibilities.php --apply\n";
    exit(0);
}

// APPLY
echo "\nApplying backfill to database...\n";
$db->beginTransaction();
try {
    $updateUserStmt = $db->prepare("
        UPDATE `users`
        SET `position_id` = :pid, `assigned_planning_entity_id` = :eid
        WHERE `id` = :uid
    ");

    $insertRespStmt = $db->prepare("
        INSERT INTO `user_responsibilities` (`user_id`, `planning_entity_id`, `responsibility_code`, `is_active`, `assigned_by`)
        VALUES (:uid, :eid, :code, 1, 1)
        ON DUPLICATE KEY UPDATE `is_active` = 1
    ");

    foreach ($proposals as $p) {
        if ($p['proposed_position_id'] !== null && $p['assigned_entity_id'] > 0) {
            $updateUserStmt->execute([
                ':pid' => $p['proposed_position_id'],
                ':eid' => $p['assigned_entity_id'],
                ':uid' => $p['user_id'],
            ]);
        }

        if ($p['assigned_entity_id'] > 0 && !empty($p['proposed_responsibilities'])) {
            foreach ($p['proposed_responsibilities'] as $code) {
                $insertRespStmt->execute([
                    ':uid' => $p['user_id'],
                    ':eid' => $p['assigned_entity_id'],
                    ':code' => $code,
                ]);
            }
        }
    }

    $db->commit();
    echo ">>> BACKFILL APPLIED SUCCESSFULLY: All users updated with positions, assigned areas, and responsibilities. <<<\n";
} catch (Throwable $e) {
    $db->rollBack();
    echo "ERROR APPLYING BACKFILL: " . $e->getMessage() . "\n";
    exit(1);
}
