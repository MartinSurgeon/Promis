-- =============================================================================
-- PROMIS PRODUCTION PHYSICAL DATABASE DDL SPECIFICATION
-- Procurement Management Information System (PROMIS)
-- University of Science and Technology, Dedicated (USTED)
--
-- Target Relational Engine : MySQL 8.x (InnoDB)
-- Collation Standard      : utf8mb4_0900_ai_ci (Production Default)
-- Default Character Set    : utf8mb4
-- Default Collation        : utf8mb4_0900_ai_ci
-- Total Physical Tables    : 36
-- Total Physical FKs       : 122 (CASCADE = 4, RESTRICT = 84, SET NULL = 34)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =============================================================================
-- CLUSTER 1: IDENTITY AND ACCESS CONTROL
-- =============================================================================

-- Table 1.1: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(25) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 1.2: roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_code` VARCHAR(50) NOT NULL UNIQUE,
    `role_title` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_system_reserved` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_roles_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_roles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 1.3: permissions
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `permission_code` VARCHAR(100) NOT NULL UNIQUE,
    `module_area` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 1.4: role_permissions (Pure Associative Junction Table)
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `granted_by` INT UNSIGNED NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT `fk_role_permissions_permission_id` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT `fk_role_permissions_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 1.5: user_entity_roles
CREATE TABLE IF NOT EXISTS `user_entity_roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `planning_entity_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
    `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    UNIQUE KEY `uq_user_entity_role` (`user_id`, `planning_entity_id`, `role_id`),
    CONSTRAINT `fk_user_entity_roles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_user_entity_roles_planning_entity_id` FOREIGN KEY (`planning_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_user_entity_roles_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_user_entity_roles_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_user_entity_roles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 2: ORGANIZATIONAL MASTER DATA
-- =============================================================================

-- Table 2.1: campuses
CREATE TABLE IF NOT EXISTS `campuses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campus_code` VARCHAR(20) NOT NULL UNIQUE,
    `campus_name` VARCHAR(100) NOT NULL,
    `location_description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_campuses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_campuses_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 2.2: entity_types
CREATE TABLE IF NOT EXISTS `entity_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type_code` VARCHAR(50) NOT NULL UNIQUE,
    `type_name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_entity_types_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_entity_types_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 2.3: planning_entities
CREATE TABLE IF NOT EXISTS `planning_entities` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entity_code` VARCHAR(50) NOT NULL UNIQUE,
    `entity_name` VARCHAR(150) NOT NULL,
    `entity_type_id` INT UNSIGNED NOT NULL,
    `campus_id` INT UNSIGNED NOT NULL,
    `parent_entity_id` INT UNSIGNED NULL,
    `head_user_id` INT UNSIGNED NULL,
    `planning_officer_id` INT UNSIGNED NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_planning_entities_entity_type_id` FOREIGN KEY (`entity_type_id`) REFERENCES `entity_types` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_planning_entities_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_planning_entities_parent_entity_id` FOREIGN KEY (`parent_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_planning_entities_head_user_id` FOREIGN KEY (`head_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_planning_entities_planning_officer_id` FOREIGN KEY (`planning_officer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_planning_entities_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_planning_entities_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 2.4: entity_hierarchies (Pure Associative Closure Table)
CREATE TABLE IF NOT EXISTS `entity_hierarchies` (
    `ancestor_entity_id` INT UNSIGNED NOT NULL,
    `descendant_entity_id` INT UNSIGNED NOT NULL,
    `depth` TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (`ancestor_entity_id`, `descendant_entity_id`),
    CONSTRAINT `fk_entity_hierarchies_ancestor_entity_id` FOREIGN KEY (`ancestor_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT `fk_entity_hierarchies_descendant_entity_id` FOREIGN KEY (`descendant_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 3: STANDARD PROCUREMENT CATALOGUE
-- =============================================================================

-- Table 3.1: item_categories
CREATE TABLE IF NOT EXISTS `item_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_code` VARCHAR(50) NOT NULL UNIQUE,
    `category_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_item_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_item_categories_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 3.2: units_of_measure
CREATE TABLE IF NOT EXISTS `units_of_measure` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uom_code` VARCHAR(20) NOT NULL UNIQUE,
    `uom_name` VARCHAR(50) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_units_of_measure_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_units_of_measure_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 3.3: standard_items
CREATE TABLE IF NOT EXISTS `standard_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_code` VARCHAR(50) NOT NULL UNIQUE,
    `item_name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `default_uom_id` INT UNSIGNED NOT NULL,
    `estimated_unit_price` DECIMAL(15,2) NULL CHECK (`estimated_unit_price` >= 0.00),
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_standard_items_category_id` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_standard_items_default_uom_id` FOREIGN KEY (`default_uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_standard_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_standard_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 4: BUDGET AND PROCUREMENT PLANNING
-- =============================================================================

-- Table 4.1: budget_allocations
CREATE TABLE IF NOT EXISTS `budget_allocations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `planning_entity_id` INT UNSIGNED NOT NULL,
    `fiscal_year` YEAR NOT NULL,
    `funding_source` VARCHAR(100) NOT NULL,
    `allocated_amount` DECIMAL(15,2) NOT NULL CHECK (`allocated_amount` >= 0.00),
    `currency` CHAR(3) NOT NULL DEFAULT 'GHS',
    `reference_code` VARCHAR(100) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    UNIQUE KEY `uq_entity_year_source` (`planning_entity_id`, `fiscal_year`, `funding_source`),
    CONSTRAINT `fk_budget_allocations_planning_entity_id` FOREIGN KEY (`planning_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_budget_allocations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_budget_allocations_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 4.2: procurement_plans
CREATE TABLE IF NOT EXISTS `procurement_plans` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plan_number` VARCHAR(50) NOT NULL UNIQUE,
    `planning_entity_id` INT UNSIGNED NOT NULL,
    `fiscal_year` YEAR NOT NULL,
    `current_version_id` INT UNSIGNED NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    UNIQUE KEY `uq_entity_fiscal_year` (`planning_entity_id`, `fiscal_year`),
    CONSTRAINT `fk_procurement_plans_planning_entity_id` FOREIGN KEY (`planning_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plans_current_version_id` FOREIGN KEY (`current_version_id`) REFERENCES `procurement_plan_versions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plans_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plans_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 4.3: procurement_plan_versions (FR-050)
CREATE TABLE IF NOT EXISTS `procurement_plan_versions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `procurement_plan_id` INT UNSIGNED NOT NULL,
    `version_number` VARCHAR(10) NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    `total_estimated_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00 CHECK (`total_estimated_cost` >= 0.00),
    `approval_date` DATETIME NULL,
    `approved_by_user_id` INT UNSIGNED NULL,
    `revision_reason` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    UNIQUE KEY `uq_plan_version` (`procurement_plan_id`, `version_number`),
    UNIQUE KEY `uq_version_plan` (`id`, `procurement_plan_id`),
    CONSTRAINT `fk_procurement_plan_versions_procurement_plan_id` FOREIGN KEY (`procurement_plan_id`) REFERENCES `procurement_plans` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plan_versions_approved_by_user_id` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plan_versions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 4.4: procurement_plan_items
CREATE TABLE IF NOT EXISTS `procurement_plan_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plan_version_id` INT UNSIGNED NOT NULL,
    `standard_item_id` INT UNSIGNED NOT NULL,
    `item_description` VARCHAR(255) NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `uom_id` INT UNSIGNED NOT NULL,
    `planned_quantity` DECIMAL(12,2) NOT NULL CHECK (`planned_quantity` >= 0.00),
    `estimated_unit_cost` DECIMAL(15,2) NOT NULL CHECK (`estimated_unit_cost` >= 0.00),
    `estimated_total_cost` DECIMAL(15,2) NOT NULL CHECK (`estimated_total_cost` >= 0.00),
    `target_quarter` VARCHAR(5) NOT NULL,
    `funding_source` VARCHAR(100) NOT NULL,
    `justification` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    CONSTRAINT `fk_procurement_plan_items_plan_version_id` FOREIGN KEY (`plan_version_id`) REFERENCES `procurement_plan_versions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plan_items_standard_item_id` FOREIGN KEY (`standard_item_id`) REFERENCES `standard_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plan_items_category_id` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plan_items_uom_id` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_plan_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 4.5: plan_review_cycles (FR-049)
CREATE TABLE IF NOT EXISTS `plan_review_cycles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `procurement_plan_id` INT UNSIGNED NOT NULL,
    `active_version_id` INT UNSIGNED NOT NULL,
    `fiscal_year` YEAR NOT NULL,
    `review_quarter` VARCHAR(5) NOT NULL,
    `review_status` VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    `review_outcome` VARCHAR(30) NULL,
    `reviewed_by_user_id` INT UNSIGNED NULL,
    `completed_at` DATETIME NULL,
    `review_notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    UNIQUE KEY `uq_plan_quarter_review` (`procurement_plan_id`, `fiscal_year`, `review_quarter`),
    CONSTRAINT `fk_plan_review_cycles_procurement_plan_id` FOREIGN KEY (`procurement_plan_id`) REFERENCES `procurement_plans` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_review_cycles_active_version_id` FOREIGN KEY (`active_version_id`) REFERENCES `procurement_plan_versions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_review_cycles_active_version_plan` FOREIGN KEY (`active_version_id`, `procurement_plan_id`) REFERENCES `procurement_plan_versions` (`id`, `procurement_plan_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_review_cycles_reviewed_by_user_id` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_review_cycles_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 4.6: plan_revision_records (FR-050)
CREATE TABLE IF NOT EXISTS `plan_revision_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `procurement_plan_id` INT UNSIGNED NOT NULL,
    `review_cycle_id` INT UNSIGNED NULL,
    `prior_version_id` INT UNSIGNED NOT NULL,
    `new_version_id` INT UNSIGNED NOT NULL,
    `revision_justification` TEXT NOT NULL,
    `submitted_by_user_id` INT UNSIGNED NOT NULL,
    `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `approved_by_user_id` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    CONSTRAINT `fk_plan_revision_records_procurement_plan_id` FOREIGN KEY (`procurement_plan_id`) REFERENCES `procurement_plans` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_revision_records_review_cycle_id` FOREIGN KEY (`review_cycle_id`) REFERENCES `plan_review_cycles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_revision_records_prior_version_id` FOREIGN KEY (`prior_version_id`) REFERENCES `procurement_plan_versions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_revision_records_new_version_id` FOREIGN KEY (`new_version_id`) REFERENCES `procurement_plan_versions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_revision_records_prior_version_plan` FOREIGN KEY (`prior_version_id`, `procurement_plan_id`) REFERENCES `procurement_plan_versions` (`id`, `procurement_plan_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_revision_records_new_version_plan` FOREIGN KEY (`new_version_id`, `procurement_plan_id`) REFERENCES `procurement_plan_versions` (`id`, `procurement_plan_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_revision_records_submitted_by_user_id` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_plan_revision_records_approved_by_user_id` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 5: REQUISITIONS AND DRAWDOWN TRACKING
-- =============================================================================

-- Table 5.1: requisitions (FR-051)
CREATE TABLE IF NOT EXISTS `requisitions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `requisition_number` VARCHAR(50) NOT NULL UNIQUE,
    `planning_entity_id` INT UNSIGNED NOT NULL,
    `fiscal_year` YEAR NOT NULL,
    `approved_plan_version_id` INT UNSIGNED NULL,
    `status` VARCHAR(35) NOT NULL DEFAULT 'DRAFT',
    `total_estimated_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00 CHECK (`total_estimated_cost` >= 0.00),
    `justification` TEXT NOT NULL,
    `submitted_at` DATETIME NULL,
    `submitted_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_requisitions_planning_entity_id` FOREIGN KEY (`planning_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisitions_approved_plan_version_id` FOREIGN KEY (`approved_plan_version_id`) REFERENCES `procurement_plan_versions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisitions_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisitions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisitions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 5.2: requisition_items
CREATE TABLE IF NOT EXISTS `requisition_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `requisition_id` BIGINT UNSIGNED NOT NULL,
    `procurement_plan_item_id` INT UNSIGNED NOT NULL,
    `standard_item_id` INT UNSIGNED NOT NULL,
    `item_description` VARCHAR(255) NOT NULL,
    `uom_id` INT UNSIGNED NOT NULL,
    `requested_quantity` DECIMAL(12,2) NOT NULL CHECK (`requested_quantity` > 0.00),
    `estimated_unit_cost` DECIMAL(15,2) NOT NULL CHECK (`estimated_unit_cost` >= 0.00),
    `estimated_total_cost` DECIMAL(15,2) NOT NULL CHECK (`estimated_total_cost` >= 0.00),
    `item_justification` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_requisition_items_requisition_id` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisition_items_procurement_plan_item_id` FOREIGN KEY (`procurement_plan_item_id`) REFERENCES `procurement_plan_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisition_items_standard_item_id` FOREIGN KEY (`standard_item_id`) REFERENCES `standard_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisition_items_uom_id` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisition_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisition_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 5.3: requisition_balance_snapshots (TBC)
CREATE TABLE IF NOT EXISTS `requisition_balance_snapshots` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `requisition_id` BIGINT UNSIGNED NOT NULL,
    `requisition_item_id` BIGINT UNSIGNED NOT NULL,
    `plan_item_id` INT UNSIGNED NOT NULL,
    `workflow_event` VARCHAR(35) NOT NULL,
    `approved_planned_quantity` DECIMAL(12,2) NOT NULL CHECK (`approved_planned_quantity` >= 0.00),
    `previously_requested_quantity` DECIMAL(12,2) NOT NULL CHECK (`previously_requested_quantity` >= 0.00),
    `current_request_quantity` DECIMAL(12,2) NOT NULL CHECK (`current_request_quantity` >= 0.00),
    `remaining_before` DECIMAL(12,2) NOT NULL,
    `remaining_after` DECIMAL(12,2) NOT NULL,
    `snapshot_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `recorded_by_user_id` INT UNSIGNED NOT NULL,
    CONSTRAINT `fk_requisition_balance_snapshots_requisition_id` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisition_balance_snapshots_requisition_item_id` FOREIGN KEY (`requisition_item_id`) REFERENCES `requisition_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisition_balance_snapshots_plan_item_id` FOREIGN KEY (`plan_item_id`) REFERENCES `procurement_plan_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_requisition_balance_snapshots_recorded_by_user_id` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 5.4: supporting_documents
CREATE TABLE IF NOT EXISTS `supporting_documents` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `record_type` VARCHAR(50) NOT NULL,
    `record_id` BIGINT UNSIGNED NOT NULL,
    `storage_uuid` CHAR(36) NOT NULL UNIQUE,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_size_bytes` BIGINT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `uploaded_by` INT UNSIGNED NOT NULL,
    INDEX `idx_supporting_docs_record` (`record_type`, `record_id`),
    CONSTRAINT `fk_supporting_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 6: CONFIGURABLE WORKFLOW AND APPROVALS
-- =============================================================================

-- Table 6.1: workflow_definitions
CREATE TABLE IF NOT EXISTS `workflow_definitions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workflow_code` VARCHAR(50) NOT NULL UNIQUE,
    `document_type` VARCHAR(50) NOT NULL,
    `entity_type_id` INT UNSIGNED NULL,
    `workflow_name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_workflow_definitions_entity_type_id` FOREIGN KEY (`entity_type_id`) REFERENCES `entity_types` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_workflow_definitions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_workflow_definitions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 6.2: workflow_step_rules
CREATE TABLE IF NOT EXISTS `workflow_step_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `workflow_definition_id` INT UNSIGNED NOT NULL,
    `step_order` TINYINT UNSIGNED NOT NULL,
    `step_name` VARCHAR(100) NOT NULL,
    `required_role_id` INT UNSIGNED NOT NULL,
    `threshold_min_amount` DECIMAL(15,2) NULL CHECK (`threshold_min_amount` >= 0.00),
    `threshold_max_amount` DECIMAL(15,2) NULL CHECK (`threshold_max_amount` >= 0.00),
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    UNIQUE KEY `uq_wf_step_order` (`workflow_definition_id`, `step_order`),
    CONSTRAINT `fk_workflow_step_rules_workflow_definition_id` FOREIGN KEY (`workflow_definition_id`) REFERENCES `workflow_definitions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_workflow_step_rules_required_role_id` FOREIGN KEY (`required_role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_workflow_step_rules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_workflow_step_rules_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 6.3: workflow_action_logs
CREATE TABLE IF NOT EXISTS `workflow_action_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `document_type` VARCHAR(50) NOT NULL,
    `document_id` BIGINT UNSIGNED NOT NULL,
    `step_id` INT UNSIGNED NULL,
    `actor_user_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(30) NOT NULL,
    `pre_status` VARCHAR(35) NOT NULL,
    `post_status` VARCHAR(35) NOT NULL,
    `comments` TEXT NULL,
    `action_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_workflow_action_logs_doc` (`document_type`, `document_id`),
    CONSTRAINT `fk_workflow_action_logs_actor_user_id` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_workflow_action_logs_step_id` FOREIGN KEY (`step_id`) REFERENCES `workflow_step_rules` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 6.4: commitment_authorizations
CREATE TABLE IF NOT EXISTS `commitment_authorizations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `requisition_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `finance_officer_id` INT UNSIGNED NOT NULL,
    `budget_allocation_id` INT UNSIGNED NOT NULL,
    `authorized_amount` DECIMAL(15,2) NOT NULL CHECK (`authorized_amount` >= 0.00),
    `vote_code` VARCHAR(50) NULL,
    `commitment_reference` VARCHAR(100) NULL,
    `authorization_status` VARCHAR(30) NOT NULL DEFAULT 'AUTHORIZED',
    `authorized_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    CONSTRAINT `fk_commitment_authorizations_requisition_id` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_commitment_authorizations_finance_officer_id` FOREIGN KEY (`finance_officer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_commitment_authorizations_budget_allocation_id` FOREIGN KEY (`budget_allocation_id`) REFERENCES `budget_allocations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_commitment_authorizations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 7: DEMAND CONSOLIDATION AND GHANEPS HANDOVER
-- =============================================================================

-- Table 7.1: consolidation_batches
CREATE TABLE IF NOT EXISTS `consolidation_batches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `batch_number` VARCHAR(50) NOT NULL UNIQUE,
    `fiscal_year` YEAR NOT NULL,
    `consolidation_period` VARCHAR(10) NOT NULL,
    `category_id` INT UNSIGNED NULL,
    `batch_status` VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    `compiled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `compiled_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    CONSTRAINT `fk_consolidation_batches_category_id` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consolidation_batches_compiled_by` FOREIGN KEY (`compiled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consolidation_batches_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 7.2: consolidation_items
CREATE TABLE IF NOT EXISTS `consolidation_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `consolidation_batch_id` INT UNSIGNED NOT NULL,
    `source_entity_id` INT UNSIGNED NOT NULL,
    `source_campus_id` INT UNSIGNED NOT NULL,
    `source_requisition_id` BIGINT UNSIGNED NULL,
    `source_plan_item_id` INT UNSIGNED NOT NULL,
    `standard_item_id` INT UNSIGNED NOT NULL,
    `consolidated_quantity` DECIMAL(12,2) NOT NULL CHECK (`consolidated_quantity` > 0.00),
    `estimated_unit_cost` DECIMAL(15,2) NOT NULL CHECK (`estimated_unit_cost` >= 0.00),
    `total_estimated_cost` DECIMAL(15,2) NOT NULL CHECK (`total_estimated_cost` >= 0.00),
    `package_assignment_code` VARCHAR(50) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    CONSTRAINT `fk_consolidation_items_consolidation_batch_id` FOREIGN KEY (`consolidation_batch_id`) REFERENCES `consolidation_batches` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consolidation_items_source_entity_id` FOREIGN KEY (`source_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consolidation_items_source_campus_id` FOREIGN KEY (`source_campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consolidation_items_source_requisition_id` FOREIGN KEY (`source_requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consolidation_items_source_plan_item_id` FOREIGN KEY (`source_plan_item_id`) REFERENCES `procurement_plan_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consolidation_items_standard_item_id` FOREIGN KEY (`standard_item_id`) REFERENCES `standard_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consolidation_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 7.3: ghaneps_export_packages
CREATE TABLE IF NOT EXISTS `ghaneps_export_packages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `consolidation_batch_id` INT UNSIGNED NOT NULL,
    `export_reference` VARCHAR(100) NOT NULL UNIQUE,
    `export_format` VARCHAR(20) NOT NULL,
    `export_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `exported_by_user_id` INT UNSIGNED NOT NULL,
    `checksum_hash` VARCHAR(64) NULL,
    `export_notes` TEXT NULL,
    CONSTRAINT `fk_ghaneps_export_packages_consolidation_batch_id` FOREIGN KEY (`consolidation_batch_id`) REFERENCES `consolidation_batches` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ghaneps_export_packages_exported_by_user_id` FOREIGN KEY (`exported_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 8: PROCUREMENT OPERATIONS (POST-REQUISITION)
-- =============================================================================

-- Table 8.1: procurement_packages (TBC)
CREATE TABLE IF NOT EXISTS `procurement_packages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `package_number` VARCHAR(50) NOT NULL UNIQUE,
    `consolidation_batch_id` INT UNSIGNED NOT NULL,
    `package_title` VARCHAR(150) NOT NULL,
    `procurement_method` VARCHAR(50) NOT NULL,
    `estimated_cost` DECIMAL(15,2) NOT NULL CHECK (`estimated_cost` >= 0.00),
    `status` VARCHAR(30) NOT NULL DEFAULT 'CREATED',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_procurement_packages_consolidation_batch_id` FOREIGN KEY (`consolidation_batch_id`) REFERENCES `consolidation_batches` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_packages_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_procurement_packages_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 8.2: purchase_orders (Proposed Later Phase)
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `package_id` INT UNSIGNED NOT NULL,
    `supplier_name` VARCHAR(150) NOT NULL,
    `contract_reference` VARCHAR(100) NULL,
    `total_order_amount` DECIMAL(15,2) NOT NULL CHECK (`total_order_amount` >= 0.00),
    `order_date` DATE NOT NULL,
    `order_status` VARCHAR(30) NOT NULL DEFAULT 'ISSUED',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT UNSIGNED NULL,
    CONSTRAINT `fk_purchase_orders_package_id` FOREIGN KEY (`package_id`) REFERENCES `procurement_packages` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_purchase_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_purchase_orders_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 8.3: order_items (Proposed Later Phase)
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` BIGINT UNSIGNED NOT NULL,
    `standard_item_id` INT UNSIGNED NOT NULL,
    `ordered_quantity` DECIMAL(12,2) NOT NULL CHECK (`ordered_quantity` > 0.00),
    `agreed_unit_price` DECIMAL(15,2) NOT NULL CHECK (`agreed_unit_price` >= 0.00),
    `total_line_amount` DECIMAL(15,2) NOT NULL CHECK (`total_line_amount` >= 0.00),
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    CONSTRAINT `fk_order_items_purchase_order_id` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_order_items_standard_item_id` FOREIGN KEY (`standard_item_id`) REFERENCES `standard_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_order_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 9: CONSUMPTION AND DELIVERY
-- =============================================================================

-- Table 9.1: delivery_records (TBC)
CREATE TABLE IF NOT EXISTS `delivery_records` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `delivery_number` VARCHAR(50) NOT NULL UNIQUE,
    `requisition_id` BIGINT UNSIGNED NULL,
    `delivery_date` DATE NOT NULL,
    `waybill_number` VARCHAR(100) NULL,
    `received_by_user_id` INT UNSIGNED NOT NULL,
    `inspection_status` VARCHAR(30) NOT NULL DEFAULT 'ACCEPTED',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL,
    CONSTRAINT `fk_delivery_records_requisition_id` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_delivery_records_received_by_user_id` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_delivery_records_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table 9.2: consumption_records
CREATE TABLE IF NOT EXISTS `consumption_records` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `planning_entity_id` INT UNSIGNED NOT NULL,
    `campus_id` INT UNSIGNED NOT NULL,
    `standard_item_id` INT UNSIGNED NOT NULL,
    `fiscal_year` YEAR NOT NULL,
    `period_quarter` VARCHAR(5) NOT NULL,
    `quantity_consumed` DECIMAL(12,2) NOT NULL CHECK (`quantity_consumed` >= 0.00),
    `requisition_id` BIGINT UNSIGNED NULL,
    `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `recorded_by` INT UNSIGNED NOT NULL,
    INDEX `idx_consumption_records_lookup` (`planning_entity_id`, `campus_id`, `standard_item_id`, `fiscal_year`, `period_quarter`),
    CONSTRAINT `fk_consumption_records_planning_entity_id` FOREIGN KEY (`planning_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consumption_records_campus_id` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consumption_records_standard_item_id` FOREIGN KEY (`standard_item_id`) REFERENCES `standard_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_consumption_records_requisition_id` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_consumption_records_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 10: PROTECTED INSTITUTIONAL AUDIT LOGGING
-- =============================================================================

-- Table 10.1: audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actor_user_id` INT UNSIGNED NOT NULL,
    `planning_entity_id` INT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `record_type` VARCHAR(50) NOT NULL,
    `record_id` BIGINT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `previous_state_json` JSON NULL,
    `new_state_json` JSON NULL,
    INDEX `idx_audit_logs_record` (`record_type`, `record_id`),
    INDEX `idx_audit_logs_actor` (`actor_user_id`, `event_timestamp`),
    CONSTRAINT `fk_audit_logs_actor_user_id` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_audit_logs_planning_entity_id` FOREIGN KEY (`planning_entity_id`) REFERENCES `planning_entities` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =============================================================================
-- CLUSTER 11: SYSTEM NOTIFICATIONS
-- =============================================================================

-- Table 11.1: notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recipient_user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `notification_type` VARCHAR(30) NOT NULL DEFAULT 'INFO',
    `reference_type` VARCHAR(50) NULL,
    `reference_id` BIGINT UNSIGNED NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notifications_recipient` (`recipient_user_id`, `is_read`, `created_at`),
    CONSTRAINT `fk_notifications_recipient_user_id` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
