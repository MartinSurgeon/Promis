<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\Database\Connection;

$db = Connection::getInstance();

echo "========================================================\n";
echo " PROMIS SAFE DATABASE MIGRATION: POSITIONS & RESPONSIBILITIES\n";
echo "========================================================\n\n";

// 1. Create `positions` table
echo "1. Creating `positions` table (if not exists)...\n";
$db->exec("
    CREATE TABLE IF NOT EXISTS `positions` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `position_code` VARCHAR(50) NOT NULL UNIQUE,
        `position_title` VARCHAR(100) NOT NULL,
        `description` VARCHAR(255) NULL,
        `default_scope_type` VARCHAR(20) NOT NULL DEFAULT 'DEPT',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "   `positions` table verified.\n";

// 2. Seed `positions` table (Strictly excluding REQUESTER)
echo "2. Seeding official appointments into `positions`...\n";
$positions = [
    [
        'code' => 'DEAN',
        'title' => 'Dean',
        'desc' => 'Academic Head of Faculty with jurisdiction over member departments',
        'scope' => 'FAC',
    ],
    [
        'code' => 'HOD',
        'title' => 'Head of Department',
        'desc' => 'Executive administrator for academic department operations',
        'scope' => 'DEPT',
    ],
    [
        'code' => 'DIRECTOR',
        'title' => 'Director',
        'desc' => 'Head of specialized directorate or administrative division',
        'scope' => 'UNIT',
    ],
    [
        'code' => 'COORDINATOR',
        'title' => 'Coordinator',
        'desc' => 'Program or section coordinator within department or unit',
        'scope' => 'DEPT',
    ],
    [
        'code' => 'FINANCE_OFFICER',
        'title' => 'Finance Officer',
        'desc' => 'Institutional financial controller with commitment authority',
        'scope' => 'UNIV',
    ],
    [
        'code' => 'PROCUREMENT_OFFICER',
        'title' => 'Procurement Officer',
        'desc' => 'Institutional procurement specialist managing orders and receipt',
        'scope' => 'UNIV',
    ],
    [
        'code' => 'ADMINISTRATOR',
        'title' => 'Administrator',
        'desc' => 'System administrator managing staff accounts and governance',
        'scope' => 'UNIV',
    ],
    [
        'code' => 'STAFF_OFFICER',
        'title' => 'Staff Officer',
        'desc' => 'Departmental or unit operational officer',
        'scope' => 'DEPT',
    ],
];

$posStmt = $db->prepare("
    INSERT INTO `positions` (`position_code`, `position_title`, `description`, `default_scope_type`, `is_active`)
    VALUES (:code, :title, :desc, :scope, 1)
    ON DUPLICATE KEY UPDATE
        `position_title` = VALUES(`position_title`),
        `description` = VALUES(`description`),
        `default_scope_type` = VALUES(`default_scope_type`)
");

foreach ($positions as $p) {
    $posStmt->execute([
        ':code' => $p['code'],
        ':title' => $p['title'],
        ':desc' => $p['desc'],
        ':scope' => $p['scope'],
    ]);
    echo "   Seeded position: {$p['code']} ({$p['title']})\n";
}

// 3. Add `position_id` and `assigned_planning_entity_id` to `users`
echo "\n3. Checking `users` table columns...\n";
$userCols = $db->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('position_id', $userCols, true)) {
    echo "   Adding `position_id` column to `users`...\n";
    $db->exec("ALTER TABLE `users` ADD COLUMN `position_id` INT UNSIGNED NULL AFTER `phone`");
    $db->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_position_id` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL");
    echo "   `position_id` added successfully.\n";
} else {
    echo "   `position_id` already exists on `users`.\n";
}

if (!in_array('assigned_planning_entity_id', $userCols, true)) {
    echo "   Adding `assigned_planning_entity_id` column to `users`...\n";
    $db->exec("ALTER TABLE `users` ADD COLUMN `assigned_planning_entity_id` INT UNSIGNED NULL AFTER `position_id`");
    $db->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_assigned_entity` FOREIGN KEY (`assigned_planning_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE SET NULL");
    echo "   `assigned_planning_entity_id` added successfully.\n";
} else {
    echo "   `assigned_planning_entity_id` already exists on `users`.\n";
}

// 4. Create `user_responsibilities` table
echo "\n4. Creating `user_responsibilities` table (if not exists)...\n";
$db->exec("
    CREATE TABLE IF NOT EXISTS `user_responsibilities` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `planning_entity_id` INT UNSIGNED NOT NULL,
        `responsibility_code` VARCHAR(100) NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `assigned_by` INT UNSIGNED NOT NULL,
        `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` INT UNSIGNED NULL,
        UNIQUE KEY `uq_user_entity_resp` (`user_id`, `planning_entity_id`, `responsibility_code`),
        KEY `idx_user_resp_code` (`responsibility_code`, `is_active`),
        KEY `idx_user_resp_entity` (`planning_entity_id`, `is_active`),
        CONSTRAINT `fk_user_resp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_user_resp_entity` FOREIGN KEY (`planning_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_user_resp_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "   `user_responsibilities` table verified.\n";

echo "\n>>> DATABASE MIGRATION COMPLETED SUCCESSFULLY <<<\n";
