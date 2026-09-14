# PROMIS Physical Schema Specification & Data Dictionary

## 1. Physical Schema Overview & Global Standards

This document establishes the authoritative physical schema specification and data dictionary for all **36 physical tables** across the 11 functional clusters of the **Procurement Management Information System (PROMIS)** for the **University of Science and Technology, Dedicated (USTED)**.

### 1.1 Global Database Standards
- **Relational Storage Engine**: `ENGINE=InnoDB` across all 36 tables (ACID compliance, row-level concurrency, foreign key referential integrity).
- **Authoritative Character Set & Collation**:
  - `CHARACTER SET utf8mb4`
  - `COLLATE utf8mb4_0900_ai_ci`
  - Standardized authoritatively across all tables, string columns, and PDO connection parameters in accordance with Decision `DBD-002`.
- **Foreign Key Update Policy**:
  - Blanket `ON UPDATE CASCADE` is strictly **rejected** in accordance with Decision `DBD-005`.
  - All foreign keys enforce `ON UPDATE RESTRICT`. Because primary keys are stable, synthetic surrogate identifiers (`AUTO_INCREMENT`), surrogate values are immutable and never updated during normal business operations. `ON UPDATE RESTRICT` prevents accidental key corruption from cascading to dependent child tables.
- **Foreign Key Delete Policy**:
  - `ON DELETE RESTRICT` is enforced across all statutory, financial, planning, workflow, notification, and transactional records (`procurement_plans`, `procurement_plan_versions`, `procurement_plan_items`, `requisitions`, `requisition_items`, `workflow_definitions`, `workflow_step_rules`, `budget_allocations`, `commitment_authorizations`, `planning_entities`, `users`, `notifications`, `purchase_orders`, `order_items`).
  - *Requisition Line Item & Order Line Item Safety*: Enforce `ON DELETE RESTRICT` on `requisition_items.requisition_id` and `order_items.purchase_order_id`. MySQL engine-level cascade cannot evaluate business state (e.g. `WHERE status = 'DRAFT'`); an unrestricted cascade would permit silent destruction of submitted, approved, or audited items. `ON DELETE RESTRICT` physically protects child lines. Draft line items are safely managed by the application Service Layer executing explicit child item deletion inside a managed database transaction strictly when the parent header is in `DRAFT` status.
  - *User and Workflow Rules Preservation*: `notifications.recipient_user_id` and `workflow_step_rules.workflow_definition_id` enforce `ON DELETE RESTRICT`. User accounts and workflow routing definitions are deactivated/archived rather than physically deleted, preserving historical alerts, audit trails, and execution context.
  - `ON DELETE CASCADE` is restricted strictly and exclusively to pure associative junction tables (`role_permissions`, `entity_hierarchies`).
- **Timestamp Strategy by Table Category**:
  In accordance with Decision `DBD-014`, timestamps are determined by table category rather than applying a blanket set of columns:
  1. **Master-Data Records**: `created_at`, `created_by`, `updated_at`, `updated_by` (tracks administrative modifications over time).
  2. **Transactional Business Records**: `created_at`, `created_by`, `updated_at`, `updated_by`, plus stage timestamps (`submitted_at`, `authorized_at`).
  3. **Version Baseline Records**: `created_at`, `created_by`, `approval_date`, `approved_by_user_id`. (Frozen upon approval; **zero generic `updated_at`/`updated_by`**).
  4. **Event & Decision Log Records**: `action_timestamp` (or `event_timestamp`, `completed_at`, `submitted_at`), `actor_user_id`. (Append-only; **zero `updated_at`/`updated_by`**).
  5. **Protected Audit Records**: Single immutable `event_timestamp`, `actor_user_id`. (Append-only; **zero `updated_at`/`updated_by`**).
  6. **System Notification Records**: `created_at`, `read_at`. (Targeted state; **zero generic `updated_at`/`updated_by`**).
- **Authoritative Table Count & Phased Status**:
  - **31 Tables in `CONFIRMED PHASE 1`**: Core operational scope.
  - **3 Tables in `TO BE CONFIRMED`**: `requisition_balance_snapshots`, `procurement_packages`, `delivery_records`.
  - **2 Tables in `PROPOSED LATER PHASE`**: `purchase_orders`, `order_items`.
  - **Total**: Exactly **36 Tables**.
  - *Distinction*: "Required for Phase 1 database support" establishes physical table structures for Phase 1 execution; where specific field-level business rules or external formats remain unconfirmed, they are explicitly designated as `TO BE CONFIRMED (TBC)`.

---

## Cluster 1: Identity and Access Control

### 1.1 `users`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Master-Data Record / User Account
- **Purpose**: Authenticated user accounts across all USTED directorates, faculties, and departments.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `username`: `VARCHAR(50) NOT NULL UNIQUE`
  - `email`: `VARCHAR(100) NOT NULL UNIQUE`
  - `password_hash`: `VARCHAR(255) NOT NULL` (Argon2id / bcrypt hash)
  - `first_name`: `VARCHAR(50) NOT NULL`
  - `last_name`: `VARCHAR(50) NOT NULL`
  - `phone`: `VARCHAR(25) NULL`
  - `status`: `VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'` (`ACTIVE`, `INACTIVE`, `LOCKED`, `PASSWORD_RESET_REQUIRED`)
  - `last_login_at`: `DATETIME NULL DEFAULT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 1.2 `roles`
- **Phase**: `CONFIRMED PHASE 1` (Table structure confirmed; role titles and hierarchy are configurable master data)
- **Category**: Master-Data Record
- **Purpose**: System and organizational role definitions. Zero University-specific roles are hard-coded.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `role_code`: `VARCHAR(50) NOT NULL UNIQUE` (e.g., `PLANNING_OFFICER_ROLE`, `HEAD_OF_ENTITY_ROLE`)
  - `role_title`: `VARCHAR(100) NOT NULL`
  - `description`: `TEXT NULL`
  - `is_system_reserved`: `TINYINT(1) NOT NULL DEFAULT 0`
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 1.3 `permissions`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Master-Data Record (System Technical Definition)
- **Purpose**: Granular system privileges governing access to application actions and endpoints.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `permission_code`: `VARCHAR(100) NOT NULL UNIQUE` (e.g. `plan.create`, `plan.review`, `req.approve`, `consolidation.execute`)
  - `module_area`: `VARCHAR(50) NOT NULL` (e.g. `PLANNING`, `REQUISITION`, `CONSOLIDATION`, `AUDIT`)
  - `description`: `VARCHAR(255) NOT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- **Notes**: System permissions are seeded technical identifiers, isolated from organizational master data.

### 1.4 `role_permissions`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Master-Data Junction Record
- **Purpose**: Many-to-many junction mapping permissions to roles.
- **Columns**:
  - `role_id`: `INT UNSIGNED NOT NULL`
  - `permission_id`: `INT UNSIGNED NOT NULL`
  - `granted_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `granted_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `PRIMARY KEY (role_id, permission_id)`
  - `FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE ON UPDATE RESTRICT`
  - `FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE ON UPDATE RESTRICT`
  - `FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 1.5 `user_entity_roles`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Master-Data Record / Scoped RBAC Assignment
- **Purpose**: Entity-Scoped RBAC. Associates users with roles strictly scoped to a specific planning entity, supporting dual appointments.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `user_id`: `INT UNSIGNED NOT NULL`
  - `planning_entity_id`: `INT UNSIGNED NOT NULL`
  - `role_id`: `INT UNSIGNED NOT NULL`
  - `is_primary`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `status`: `VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'` (`ACTIVE`, `DELEGATED`, `REVOKED`)
  - `assigned_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `assigned_by`: `INT UNSIGNED NOT NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `UNIQUE KEY uq_user_entity_role (user_id, planning_entity_id, role_id)`
  - `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (planning_entity_id) REFERENCES planning_entities(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

---

## Cluster 2: Organizational Master Data

### 2.1 `campuses`
- **Phase**: `CONFIRMED PHASE 1` (Structure confirmed; campus names and locations are configurable master data)
- **Category**: Master-Data Record
- **Purpose**: University campus locations. Zero campus names are hard-coded.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `campus_code`: `VARCHAR(20) NOT NULL UNIQUE`
  - `campus_name`: `VARCHAR(100) NOT NULL`
  - `location_description`: `VARCHAR(255) NULL`
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 2.2 `entity_types`
- **Phase**: `CONFIRMED PHASE 1` (Structure confirmed; types are configurable master data)
- **Category**: Master-Data Record
- **Purpose**: Operational classification of university units (e.g., Academic Department, Directorate, Faculty, Centre).
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `type_code`: `VARCHAR(50) NOT NULL UNIQUE` (e.g. `ACADEMIC_DEPT`, `DIRECTORATE`, `FACULTY`)
  - `type_name`: `VARCHAR(100) NOT NULL`
  - `description`: `VARCHAR(255) NULL`
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 2.3 `planning_entities`
- **Phase**: `CONFIRMED PHASE 1` (The ~62 planning entities are configurable master data; zero hard-coding)
- **Category**: Master-Data Record
- **Purpose**: The operational planning, cost center, and requisitioning units of USTED (~62 entities).
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `entity_code`: `VARCHAR(50) NOT NULL UNIQUE`
  - `entity_name`: `VARCHAR(150) NOT NULL`
  - `entity_type_id`: `INT UNSIGNED NOT NULL`
  - `campus_id`: `INT UNSIGNED NOT NULL`
  - `parent_entity_id`: `INT UNSIGNED NULL` (Self-referencing logical hierarchy)
  - `head_user_id`: `INT UNSIGNED NULL` (Configured approving authority)
  - `planning_officer_id`: `INT UNSIGNED NULL` (Designated plan compiler)
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (entity_type_id) REFERENCES entity_types(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (parent_entity_id) REFERENCES planning_entities(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (planning_officer_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 2.4 `entity_hierarchies`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Master-Data Record (Structural Closure Table)
- **Purpose**: Closure table supporting fast recursive queries of nested multi-tier academic and administrative structures (e.g. Department -> Faculty -> College).
- **Columns**:
  - `ancestor_entity_id`: `INT UNSIGNED NOT NULL`
  - `descendant_entity_id`: `INT UNSIGNED NOT NULL`
  - `depth`: `TINYINT UNSIGNED NOT NULL`
- **Constraints**:
  - `PRIMARY KEY (ancestor_entity_id, descendant_entity_id)`
  - `FOREIGN KEY (ancestor_entity_id) REFERENCES planning_entities(id) ON DELETE CASCADE ON UPDATE RESTRICT`
  - `FOREIGN KEY (descendant_entity_id) REFERENCES planning_entities(id) ON DELETE CASCADE ON UPDATE RESTRICT`
- **Timestamp Semantics**: Pure structural graph closure table; regenerated/maintained via database triggers or repository service; no timestamp columns required.

---

## Cluster 3: Standard Procurement Catalogue

### 3.1 `item_categories`
- **Phase**: `CONFIRMED PHASE 1` (Categories are configurable master data)
- **Category**: Master-Data Record
- **Purpose**: High-level statutory procurement categories.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `category_code`: `VARCHAR(50) NOT NULL UNIQUE` (e.g. `GOODS`, `WORKS`, `SERVICES`, `CONSULTING`)
  - `category_name`: `VARCHAR(100) NOT NULL`
  - `description`: `TEXT NULL`
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 3.2 `units_of_measure`
- **Phase**: `CONFIRMED PHASE 1` (Units are configurable master data)
- **Category**: Master-Data Record
- **Purpose**: Standardized measurement units preventing free-text ambiguity.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `uom_code`: `VARCHAR(20) NOT NULL UNIQUE` (e.g. `PCS`, `REAM`, `BOX`, `METRE`, `HR`)
  - `uom_name`: `VARCHAR(50) NOT NULL`
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 3.3 `standard_items`
- **Phase**: `CONFIRMED PHASE 1` (Catalogue items are configurable master data; zero hard-coded items)
- **Category**: Master-Data Record
- **Purpose**: Controlled item catalogue for standardized planning and requisitioning.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `item_code`: `VARCHAR(50) NOT NULL UNIQUE`
  - `item_name`: `VARCHAR(150) NOT NULL`
  - `description`: `TEXT NULL`
  - `category_id`: `INT UNSIGNED NOT NULL`
  - `default_uom_id`: `INT UNSIGNED NOT NULL`
  - `estimated_unit_price`: `DECIMAL(15,2) NULL CHECK (estimated_unit_price >= 0.00)`
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (default_uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

---

## Cluster 4: Budget and Procurement Planning

### 4.1 `budget_allocations`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Transactional Record
- **Purpose**: Departmental budget ceiling touchpoint recorded per fiscal year.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `planning_entity_id`: `INT UNSIGNED NOT NULL`
  - `fiscal_year`: `YEAR NOT NULL`
  - `funding_source`: `VARCHAR(100) NOT NULL` (e.g. `GOG`, `IGF`, `DONOR`)
  - `allocated_amount`: `DECIMAL(15,2) NOT NULL CHECK (allocated_amount >= 0.00)`
  - `currency`: `CHAR(3) NOT NULL DEFAULT 'GHS'`
  - `reference_code`: `VARCHAR(100) NULL` (Financial ledger reference; mechanism TBC)
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `UNIQUE KEY uq_entity_year_source (planning_entity_id, fiscal_year, funding_source)`
  - `FOREIGN KEY (planning_entity_id) REFERENCES planning_entities(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 4.2 `procurement_plans`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Transactional Record
- **Purpose**: Root annual procurement plan container for a planning entity.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `plan_number`: `VARCHAR(50) NOT NULL UNIQUE` (e.g. `PLAN-2026-CS001`)
  - `planning_entity_id`: `INT UNSIGNED NOT NULL`
  - `fiscal_year`: `YEAR NOT NULL`
  - `current_version_id`: `INT UNSIGNED NULL` (Active approved version reference — Single Authoritative Source of Truth)
  - `status`: `VARCHAR(30) NOT NULL DEFAULT 'DRAFT'` (`DRAFT`, `SUBMITTED`, `APPROVED`, `UNDER_REVIEW`)
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `UNIQUE KEY uq_entity_fiscal_year (planning_entity_id, fiscal_year)`
  - `FOREIGN KEY (planning_entity_id) REFERENCES planning_entities(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (current_version_id) REFERENCES procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
- **Active Version Pointer & Circular Relationship Handling**:
  - `current_version_id` is the single authoritative source of truth for the active approved plan version.
  - *Mutual Dependency Resolution*: A new plan begins in `DRAFT` status with `current_version_id = NULL`. Its initial draft version (v1.0) is inserted into `procurement_plan_versions` with `procurement_plan_id = procurement_plans.id`. When v1.0 is formally approved, an atomic transaction sets `procurement_plan_versions.status = 'APPROVED'`, `procurement_plans.current_version_id = procurement_plan_versions.id`, and `procurement_plans.status = 'APPROVED'`. In initial DDL execution, tables are created with foreign key checks deferred (`SET FOREIGN_KEY_CHECKS = 0;`) or via post-table `ALTER TABLE` constraint addition. `ON DELETE RESTRICT` ensures an active approved version cannot be deleted while referenced by the root plan container.

### 4.3 `procurement_plan_versions` (FR-050)
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Version Baseline Record
- **Purpose**: Stores distinct approved versions (`v1.0`, `v2.0`). Historical approved versions are preserved and never overwritten once versioning is approved.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `procurement_plan_id`: `INT UNSIGNED NOT NULL`
  - `version_number`: `VARCHAR(10) NOT NULL` (e.g. `1.0`, `2.0`)
  - `status`: `VARCHAR(30) NOT NULL DEFAULT 'DRAFT'` (`DRAFT`, `SUBMITTED`, `APPROVED`, `SUPERSEDED`)
  - `total_estimated_cost`: `DECIMAL(15,2) NOT NULL DEFAULT 0.00 CHECK (total_estimated_cost >= 0.00)`
  - `approval_date`: `DATETIME NULL`
  - `approved_by_user_id`: `INT UNSIGNED NULL`
  - `revision_reason`: `TEXT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `UNIQUE KEY uq_plan_version (procurement_plan_id, version_number)`
  - `UNIQUE KEY uq_version_plan (id, procurement_plan_id)` (Composite key supporting foreign key cross-plan consistency)
  - `FOREIGN KEY (procurement_plan_id) REFERENCES procurement_plans(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
- **Active Version Source of Truth Resolution**:
  - Column `is_current_active` is explicitly **eliminated** from `procurement_plan_versions` to resolve competing sources of truth with `procurement_plans.current_version_id`.
  - In MySQL InnoDB, partial/filtered unique indexes (`WHERE is_current_active = 1`) do not exist; maintaining a boolean flag across multiple rows risks dual-active state anomalies.
  - The scalar foreign key `procurement_plans.current_version_id` structurally guarantees that at most one version is active per procurement plan at any time.
  - Active version queries evaluate: `WHERE p.current_version_id = v.id`.
- **Timestamp Semantics**: Version baseline container; tracks creation and formal approval. In accordance with Decision `DBD-014`, once approved, version baselines are frozen; modifications require publishing a new version row.

### 4.4 `procurement_plan_items`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Version Baseline Record
- **Purpose**: Planned line items tied to a specific plan version.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `plan_version_id`: `INT UNSIGNED NOT NULL`
  - `standard_item_id`: `INT UNSIGNED NOT NULL`
  - `item_description`: `VARCHAR(255) NOT NULL`
  - `category_id`: `INT UNSIGNED NOT NULL`
  - `uom_id`: `INT UNSIGNED NOT NULL`
  - `planned_quantity`: `DECIMAL(12,2) NOT NULL CHECK (planned_quantity >= 0.00)`
  - `estimated_unit_cost`: `DECIMAL(15,2) NOT NULL CHECK (estimated_unit_cost >= 0.00)`
  - `estimated_total_cost`: `DECIMAL(15,2) NOT NULL CHECK (estimated_total_cost >= 0.00)`
  - `target_quarter`: `VARCHAR(5) NOT NULL` (`Q1`, `Q2`, `Q3`, `Q4`)
  - `funding_source`: `VARCHAR(100) NOT NULL`
  - `justification`: `TEXT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `FOREIGN KEY (plan_version_id) REFERENCES procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (standard_item_id) REFERENCES standard_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
- **Timestamp Semantics**: Version line item records are frozen once the parent version is approved. No generic `updated_at`/`updated_by` columns exist; changes are effected exclusively by creating a new version.

### 4.5 `plan_review_cycles` (FR-049)
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Event & Decision Log Record
- **Purpose**: Quarterly review tracking for approved plans. A quarterly review does not automatically imply a revision; it records review certification resulting in either `NO_CHANGE` or `REVISION_REQUIRED`.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `procurement_plan_id`: `INT UNSIGNED NOT NULL`
  - `active_version_id`: `INT UNSIGNED NOT NULL`
  - `fiscal_year`: `YEAR NOT NULL`
  - `review_quarter`: `VARCHAR(5) NOT NULL` (`Q1`, `Q2`, `Q3`, `Q4`)
  - `review_status`: `VARCHAR(30) NOT NULL DEFAULT 'PENDING'` (`PENDING`, `IN_PROGRESS`, `COMPLETED`)
  - `review_outcome`: `VARCHAR(30) NULL` (`NO_CHANGE`, `REVISION_REQUIRED`)
  - `reviewed_by_user_id`: `INT UNSIGNED NULL`
  - `completed_at`: `DATETIME NULL`
  - `review_notes`: `TEXT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `UNIQUE KEY uq_plan_quarter_review (procurement_plan_id, fiscal_year, review_quarter)`
  - `FOREIGN KEY (procurement_plan_id) REFERENCES procurement_plans(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (active_version_id) REFERENCES procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (active_version_id, procurement_plan_id) REFERENCES procurement_plan_versions(id, procurement_plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
- **Cross-Plan / Version Consistency Invariants**:
  - *Invariant*: The version undergoing review (`active_version_id`) MUST belong directly to the plan being reviewed (`procurement_plan_id`).
  - *Physical & Application Guarantees*:
    - The composite foreign key `(active_version_id, procurement_plan_id) REFERENCES procurement_plan_versions(id, procurement_plan_id)` physically prevents a quarterly review cycle from referencing a plan version belonging to another planning entity or different plan root at the database engine level.
    - Application Service Layer additionally validates that `active_version_id` matches `procurement_plans.current_version_id` at the time the quarterly review is initiated.
- **Timestamp Semantics**: Tracks initiation (`created_at`) and formal review completion (`completed_at`). Zero generic `updated_at`/`updated_by` columns.

### 4.6 `plan_revision_records` (FR-050)
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Event & Decision Log Record
- **Purpose**: Documents authorized plan revisions linking prior version to new version.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `procurement_plan_id`: `INT UNSIGNED NOT NULL`
  - `review_cycle_id`: `INT UNSIGNED NULL`
  - `prior_version_id`: `INT UNSIGNED NOT NULL`
  - `new_version_id`: `INT UNSIGNED NOT NULL`
  - `revision_justification`: `TEXT NOT NULL`
  - `submitted_by_user_id`: `INT UNSIGNED NOT NULL`
  - `submitted_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `approved_by_user_id`: `INT UNSIGNED NULL`
  - `approved_at`: `DATETIME NULL`
- **Constraints**:
  - `FOREIGN KEY (procurement_plan_id) REFERENCES procurement_plans(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (review_cycle_id) REFERENCES plan_review_cycles(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (prior_version_id) REFERENCES procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (new_version_id) REFERENCES procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (prior_version_id, procurement_plan_id) REFERENCES procurement_plan_versions(id, procurement_plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (new_version_id, procurement_plan_id) REFERENCES procurement_plan_versions(id, procurement_plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
- **Cross-Plan / Version Consistency Invariants**:
  - *Invariants*:
    1. **Plan Ownership Invariant**: Both `prior_version_id` and `new_version_id` MUST belong to `procurement_plan_id`. Enforced physically at the MySQL engine level via composite foreign keys `(prior_version_id, procurement_plan_id)` and `(new_version_id, procurement_plan_id)` referencing `procurement_plan_versions(id, procurement_plan_id)`.
    2. **Review Cycle Alignment Invariant**: If `review_cycle_id` is populated, that review cycle MUST belong to `procurement_plan_id` (`plan_review_cycles.procurement_plan_id = plan_revision_records.procurement_plan_id`), its certified `active_version_id` must match `prior_version_id`, and its outcome must be `REVISION_REQUIRED`.
    3. **Version Progression Invariant**: `prior_version_id != new_version_id`. Version progression must be strictly monotonic (e.g. `v1.0` -> `v2.0`). Prior version status transitions to `'SUPERSEDED'` when new version status transitions to `'APPROVED'`. Handled by the Application Service Layer transactional boundary.
- **Timestamp Semantics**: Capture submission event (`submitted_at`) and approval event (`approved_at`). Append-only audit record of revision governance; zero `updated_at`/`updated_by`.

---

## Cluster 5: Requisitions and Drawdown Tracking

### 5.1 `requisitions` (FR-051)
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Transactional Business Record
- **Purpose**: Operational departmental procurement requests.
- **FR-051 Specification & Implementation**:
  - **Business Requirement (FR-051)**: *"Each plan-linked requisition shall retain a historical reference to the approved procurement-plan version under which it was submitted, and that historical association shall not be silently changed."* (Status: PROPOSED / TO BE CONFIRMED). The requirement mandates the business behavior, not an exact physical column name.
  - **Proposed Physical Implementation**: Column `approved_plan_version_id INT UNSIGNED NULL` on table `requisitions`, referencing `procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`.
  - **Nullability Dependency (TBC / Proposed)**:
    - Column nullability is formally classified as `TO BE CONFIRMED / PROPOSED`.
    - **Guardrail**: A proposed business relationship must **not** be converted into an unconditional `NOT NULL` database constraint unless requirements explicitly confirm that 100% of requisitions created in PROMIS must be associated with an approved procurement-plan version.
    - If the University permits emergency requisitions, contingency items, or off-plan requests, or allows draft requisitions to exist prior to plan-version assignment, the column must remain nullable (`NULL`).
    - If future business rules confirm strict plan linkage for all submitted requisitions, the constraint will be enforced as `NOT NULL` in the final DDL or validated at the application/service layer upon submission.
  - **Rationale for Selected Column & Relationship**:
    1. Direct reference to `procurement_plan_versions` (the version record) rather than `procurement_plans` (the annual plan container) binds the requisition directly to the exact approved version snapshot active at the time of submission.
    2. When subsequent quarterly reviews publish newer versions (e.g. Version 2.0 under FR-050), existing requisitions remain permanently anchored to Version 1.0.
    3. `ON DELETE RESTRICT` physically guarantees that referenced historical version rows cannot be deleted while dependent requisitions exist.
    4. `ON UPDATE RESTRICT` ensures primary surrogate keys cannot be modified.
    5. The historical association is protected against silent modification: the Service and Controller layers enforce that `approved_plan_version_id` is set once upon submission and is excluded from all subsequent `UPDATE` operations.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `requisition_number`: `VARCHAR(50) NOT NULL UNIQUE` (e.g. `REQ-2026-0042`)
  - `planning_entity_id`: `INT UNSIGNED NOT NULL`
  - `fiscal_year`: `YEAR NOT NULL`
  - `approved_plan_version_id`: `INT UNSIGNED NULL` (Proposed Physical Implementation of FR-051; Nullability is `TO BE CONFIRMED / PROPOSED`)
  - `status`: `VARCHAR(35) NOT NULL DEFAULT 'DRAFT'` (`DRAFT`, `SUBMITTED`, `ENDORSED`, `DEPARTMENT_APPROVED`, `COMMITMENT_AUTHORIZED`, `PROCUREMENT_RECEIVED`, `RETURNED`, `REJECTED`)
  - `total_estimated_cost`: `DECIMAL(15,2) NOT NULL DEFAULT 0.00 CHECK (total_estimated_cost >= 0.00)`
  - `justification`: `TEXT NOT NULL`
  - `submitted_at`: `DATETIME NULL`
  - `submitted_by`: `INT UNSIGNED NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (planning_entity_id) REFERENCES planning_entities(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (approved_plan_version_id) REFERENCES procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 5.2 `requisition_items`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Transactional Business Record
- **Purpose**: Line items requested against a specific planned item.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `requisition_id`: `BIGINT UNSIGNED NOT NULL`
  - `procurement_plan_item_id`: `INT UNSIGNED NOT NULL`
  - `standard_item_id`: `INT UNSIGNED NOT NULL`
  - `item_description`: `VARCHAR(255) NOT NULL`
  - `uom_id`: `INT UNSIGNED NOT NULL`
  - `requested_quantity`: `DECIMAL(12,2) NOT NULL CHECK (requested_quantity > 0.00)`
  - `estimated_unit_cost`: `DECIMAL(15,2) NOT NULL CHECK (estimated_unit_cost >= 0.00)`
  - `estimated_total_cost`: `DECIMAL(15,2) NOT NULL CHECK (estimated_total_cost >= 0.00)`
  - `item_justification`: `TEXT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (procurement_plan_item_id) REFERENCES procurement_plan_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (standard_item_id) REFERENCES standard_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
- **Deletion Safety & Draft Management**:
  - Replaced unrestricted `ON DELETE CASCADE` with `ON DELETE RESTRICT` as the authoritative physical database rule. MySQL foreign key constraints cannot inspect workflow status (such as `DRAFT` vs `SUBMITTED`/`APPROVED`); an unrestricted cascade would permit silent, catastrophic deletion of historical and audited line items.
  - *Draft-Only Deletion Physical Guarantee*: If an unsubmitted draft requisition is discarded, the application Service Layer explicitly verifies `WHERE status = 'DRAFT'` and deletes the child line items inside a managed database transaction prior to deleting the requisition header row. This provides total physical protection for all submitted and active records.

### 5.3 `requisition_balance_snapshots`
- **Phase**: `TO BE CONFIRMED`
- **Category**: Event / Historical Evidentiary Record
- **Purpose & Governance Rules**:
  - **Operational Source of Truth**: Real-time balance calculation remains the operational source of truth for available quotas:
    $$\text{Remaining Before} = \text{Approved Planned Quantity} - \text{Previously Requested Quantity}$$
    $$\text{Remaining After} = \text{Remaining Before} - \text{Current Request Quantity}$$
  - **Evidentiary Role**: Snapshots, if eventually approved, are historical evidence of balance at a defined workflow event (`SUBMISSION` or `COMMITMENT_AUTHORIZED`).
  - **Explicit Guardrail**: Snapshots must not become an alternative source of truth without an explicit business decision.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `requisition_id`: `BIGINT UNSIGNED NOT NULL`
  - `requisition_item_id`: `BIGINT UNSIGNED NOT NULL`
  - `plan_item_id`: `INT UNSIGNED NOT NULL`
  - `workflow_event`: `VARCHAR(35) NOT NULL` (`SUBMISSION`, `COMMITMENT_AUTHORIZED`)
  - `approved_planned_quantity`: `DECIMAL(12,2) NOT NULL CHECK (approved_planned_quantity >= 0.00)`
  - `previously_requested_quantity`: `DECIMAL(12,2) NOT NULL CHECK (previously_requested_quantity >= 0.00)`
  - `current_request_quantity`: `DECIMAL(12,2) NOT NULL CHECK (current_request_quantity >= 0.00)`
  - `remaining_before`: `DECIMAL(12,2) NOT NULL`
  - `remaining_after`: `DECIMAL(12,2) NOT NULL`
  - `snapshot_timestamp`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `recorded_by_user_id`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (requisition_item_id) REFERENCES requisition_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (plan_item_id) REFERENCES procurement_plan_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
- **Timestamp Semantics**: Single immutable `snapshot_timestamp`; zero `updated_at`/`updated_by`.

### 5.4 `supporting_documents`
- **Phase**: `CONFIRMED PHASE 1` (UUID file storage is proposed approach)
- **Category**: Transactional / Document Metadata Record
- **Purpose**: Metadata for supporting files stored securely outside the web root.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `record_type`: `VARCHAR(50) NOT NULL` (`PROCUREMENT_PLAN`, `REQUISITION`, `PLAN_REVISION`)
  - `record_id`: `BIGINT UNSIGNED NOT NULL`
  - `storage_uuid`: `CHAR(36) NOT NULL UNIQUE`
  - `original_filename`: `VARCHAR(255) NOT NULL`
  - `file_size_bytes`: `BIGINT UNSIGNED NOT NULL`
  - `mime_type`: `VARCHAR(100) NOT NULL`
  - `uploaded_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `uploaded_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

---

## Cluster 6: Configurable Workflow and Approvals

### 6.1 `workflow_definitions`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Master-Data Record
- **Purpose**: Configurable approval routing definitions per document type and entity type.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `workflow_code`: `VARCHAR(50) NOT NULL UNIQUE`
  - `document_type`: `VARCHAR(50) NOT NULL` (`PROCUREMENT_PLAN`, `REQUISITION`, `PLAN_REVISION`)
  - `entity_type_id`: `INT UNSIGNED NULL`
  - `workflow_name`: `VARCHAR(100) NOT NULL`
  - `is_active`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (entity_type_id) REFERENCES entity_types(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 6.2 `workflow_step_rules`
- **Phase**: `CONFIRMED PHASE 1` (Supports configurable thresholds; actual thresholds remain unseeded)
- **Category**: Master-Data Record
- **Purpose**: Ordered approval stages within a routing definition. Zero statutory financial thresholds are hard-coded.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `workflow_definition_id`: `INT UNSIGNED NOT NULL`
  - `step_order`: `TINYINT UNSIGNED NOT NULL`
  - `step_name`: `VARCHAR(100) NOT NULL` (e.g. `Head of Department Approval`, `Finance Commitment Authorization`)
  - `required_role_id`: `INT UNSIGNED NOT NULL`
  - `threshold_min_amount`: `DECIMAL(15,2) NULL CHECK (threshold_min_amount >= 0.00)` (Configurable ceiling; unseeded)
  - `threshold_max_amount`: `DECIMAL(15,2) NULL CHECK (threshold_max_amount >= 0.00)` (Configurable ceiling; unseeded)
  - `is_mandatory`: `TINYINT(1) NOT NULL DEFAULT 1`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `UNIQUE KEY uq_wf_step_order (workflow_definition_id, step_order)`
  - `FOREIGN KEY (workflow_definition_id) REFERENCES workflow_definitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (required_role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 6.3 `workflow_action_logs`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Event & Decision Log Record
- **Purpose**: Append-only log of human workflow decisions (`APPROVE`, `REJECT`, `RETURN`, `COMMIT`).
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `document_type`: `VARCHAR(50) NOT NULL`
  - `document_id`: `BIGINT UNSIGNED NOT NULL`
  - `step_id`: `INT UNSIGNED NULL`
  - `actor_user_id`: `INT UNSIGNED NOT NULL`
  - `action`: `VARCHAR(30) NOT NULL` (`SUBMIT`, `ENDORSE`, `APPROVE`, `RETURN`, `REJECT`, `AUTHORIZE_COMMITMENT`)
  - `pre_status`: `VARCHAR(35) NOT NULL`
  - `post_status`: `VARCHAR(35) NOT NULL`
  - `comments`: `TEXT NULL`
  - `action_timestamp`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- **Constraints**:
  - `FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (step_id) REFERENCES workflow_step_rules(id) ON DELETE SET NULL ON UPDATE RESTRICT`
- **Timestamp Semantics**: Single immutable `action_timestamp`; zero `updated_at`/`updated_by`.

### 6.4 `commitment_authorizations`
- **Phase**: `CONFIRMED PHASE 1 (Structural Touchpoint Only / Detailed Finance Fields TO BE CONFIRMED)`
- **Category**: Transactional Business Record
- **Purpose & Governance Rules**:
  - Kept in Phase 1 **only as a structural finance/authorization touchpoint**.
  - Does **not** invent University-specific financial fields, codes, vote numbers, expenditure sub-heads, limits, references, or GL integration mechanisms.
  - Core structural columns record who authorized how much against which allocation and when.
  - Specific accounting fields (`vote_code`, `commitment_reference`) are marked as `TO BE CONFIRMED (TBC)` by University Finance and modeled as nullable alpha-numeric strings without premature validation rules.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `requisition_id`: `BIGINT UNSIGNED NOT NULL UNIQUE`
  - `finance_officer_id`: `INT UNSIGNED NOT NULL`
  - `budget_allocation_id`: `INT UNSIGNED NOT NULL`
  - `authorized_amount`: `DECIMAL(15,2) NOT NULL CHECK (authorized_amount >= 0.00)`
  - `vote_code`: `VARCHAR(50) NULL` (`TO BE CONFIRMED` by University Finance; structural placeholder only)
  - `commitment_reference`: `VARCHAR(100) NULL` (`TO BE CONFIRMED` by University Finance; structural placeholder only)
  - `authorization_status`: `VARCHAR(30) NOT NULL DEFAULT 'AUTHORIZED'` (`AUTHORIZED`, `RELEASED`, `CANCELLED`)
  - `authorized_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `notes`: `TEXT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (finance_officer_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (budget_allocation_id) REFERENCES budget_allocations(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

---

## Cluster 7: Demand Consolidation and GHANEPS Handover

### 7.1 `consolidation_batches`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Transactional Business Record
- **Purpose**: Aggregated institutional consolidation packages compiled by Procurement Directorate.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `batch_number`: `VARCHAR(50) NOT NULL UNIQUE` (e.g. `CONS-2026-Q1-GOODS`)
  - `fiscal_year`: `YEAR NOT NULL`
  - `consolidation_period`: `VARCHAR(10) NOT NULL` (`ANNUAL`, `Q1`, `Q2`, `Q3`, `Q4`)
  - `category_id`: `INT UNSIGNED NULL`
  - `batch_status`: `VARCHAR(30) NOT NULL DEFAULT 'DRAFT'` (`DRAFT`, `CONSOLIDATED`, `PACKAGED`, `EXPORTED`)
  - `compiled_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `compiled_by`: `INT UNSIGNED NOT NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
  - `notes`: `TEXT NULL`
- **Constraints**:
  - `FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (compiled_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 7.2 `consolidation_items`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Transactional Business Record (Provenance Preservation)
- **Purpose**: Maps source items into consolidated lots, strictly preserving originating department, campus, requisition, and plan item provenance.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `consolidation_batch_id`: `INT UNSIGNED NOT NULL`
  - `source_entity_id`: `INT UNSIGNED NOT NULL` (Provenance: Originating Department)
  - `source_campus_id`: `INT UNSIGNED NOT NULL` (Provenance: Originating Campus)
  - `source_requisition_id`: `BIGINT UNSIGNED NULL` (Provenance: Source Requisition)
  - `source_plan_item_id`: `INT UNSIGNED NOT NULL` (Provenance: Source Plan Item)
  - `standard_item_id`: `INT UNSIGNED NOT NULL`
  - `consolidated_quantity`: `DECIMAL(12,2) NOT NULL CHECK (consolidated_quantity > 0.00)`
  - `estimated_unit_cost`: `DECIMAL(15,2) NOT NULL CHECK (estimated_unit_cost >= 0.00)`
  - `total_estimated_cost`: `DECIMAL(15,2) NOT NULL CHECK (total_estimated_cost >= 0.00)`
  - `package_assignment_code`: `VARCHAR(50) NULL` (Tender lot assignment)
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `FOREIGN KEY (consolidation_batch_id) REFERENCES consolidation_batches(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (source_entity_id) REFERENCES planning_entities(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (source_campus_id) REFERENCES campuses(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (source_requisition_id) REFERENCES requisitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (source_plan_item_id) REFERENCES procurement_plan_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (standard_item_id) REFERENCES standard_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

### 7.3 `ghaneps_export_packages`
- **Phase**: `CONFIRMED PHASE 1 (Information Handover) / TO BE CONFIRMED (File/API Format)`
- **Category**: Transactional / Export Log Record
- **Purpose**: Metadata recording exported procurement information handover packages. Technical format remains TO BE CONFIRMED.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `consolidation_batch_id`: `INT UNSIGNED NOT NULL`
  - `export_reference`: `VARCHAR(100) NOT NULL UNIQUE`
  - `export_format`: `VARCHAR(20) NOT NULL` (`FORMAT_TBC`)
  - `export_timestamp`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `exported_by_user_id`: `INT UNSIGNED NOT NULL`
  - `checksum_hash`: `VARCHAR(64) NULL`
  - `export_notes`: `TEXT NULL`
- **Constraints**:
  - `FOREIGN KEY (consolidation_batch_id) REFERENCES consolidation_batches(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (exported_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
- **Timestamp Semantics**: Single immutable `export_timestamp`; zero `updated_at`/`updated_by`.

---

## Cluster 8: Procurement Operations (Post-Requisition)

### 8.1 `procurement_packages`
- **Phase**: `TO BE CONFIRMED` (Post-Requisition Directorate Packaging)
- **Category**: Transactional Business Record
- **Purpose**: Grouping of consolidated demands into formal procurement lots for tendering. Not expanded into Phase 1 procurement operations.
- **Columns**:
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `package_number`: `VARCHAR(50) NOT NULL UNIQUE`
  - `consolidation_batch_id`: `INT UNSIGNED NOT NULL`
  - `package_title`: `VARCHAR(150) NOT NULL`
  - `procurement_method`: `VARCHAR(50) NOT NULL` (e.g. `NATIONAL_COMPETITIVE_TENDER`, `PRICE_QUOTATION`)
  - `estimated_cost`: `DECIMAL(15,2) NOT NULL CHECK (estimated_cost >= 0.00)`
  - `status`: `VARCHAR(30) NOT NULL DEFAULT 'CREATED'` (`CREATED`, `IN_TENDER`, `AWARDED`, `CANCELLED`)
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (consolidation_batch_id) REFERENCES consolidation_batches(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 8.2 `purchase_orders`
- **Phase**: `PROPOSED LATER PHASE`
- **Category**: Transactional Business Record
- **Purpose**: External vendor purchase order record following tender award. Not part of Phase 1 operations.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `order_number`: `VARCHAR(50) NOT NULL UNIQUE`
  - `package_id`: `INT UNSIGNED NOT NULL`
  - `supplier_name`: `VARCHAR(150) NOT NULL`
  - `contract_reference`: `VARCHAR(100) NULL`
  - `total_order_amount`: `DECIMAL(15,2) NOT NULL CHECK (total_order_amount >= 0.00)`
  - `order_date`: `DATE NOT NULL`
  - `order_status`: `VARCHAR(30) NOT NULL DEFAULT 'ISSUED'` (`ISSUED`, `FULFILLED`, `CANCELLED`)
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
  - `updated_at`: `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP`
  - `updated_by`: `INT UNSIGNED NULL`
- **Constraints**:
  - `FOREIGN KEY (package_id) REFERENCES procurement_packages(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE RESTRICT`

### 8.3 `order_items`
- **Phase**: `PROPOSED LATER PHASE`
- **Category**: Transactional Business Record
- **Purpose**: Line items of awarded external purchase orders. Not part of Phase 1 operations.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `purchase_order_id`: `BIGINT UNSIGNED NOT NULL`
  - `standard_item_id`: `INT UNSIGNED NOT NULL`
  - `ordered_quantity`: `DECIMAL(12,2) NOT NULL CHECK (ordered_quantity > 0.00)`
  - `agreed_unit_price`: `DECIMAL(15,2) NOT NULL CHECK (agreed_unit_price >= 0.00)`
  - `total_line_amount`: `DECIMAL(15,2) NOT NULL CHECK (total_line_amount >= 0.00)`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (standard_item_id) REFERENCES standard_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

---

## Cluster 9: Consumption and Delivery

### 9.1 `delivery_records`
- **Phase**: `TO BE CONFIRMED` (FR-041: Phase TO BE CONFIRMED)
- **Category**: Event / Transactional Record
- **Purpose**: Recording receipt of goods and delivery notes from suppliers. Not expanded into Phase 1 operations.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `delivery_number`: `VARCHAR(50) NOT NULL UNIQUE`
  - `requisition_id`: `BIGINT UNSIGNED NULL`
  - `delivery_date`: `DATE NOT NULL`
  - `waybill_number`: `VARCHAR(100) NULL`
  - `received_by_user_id`: `INT UNSIGNED NOT NULL`
  - `inspection_status`: `VARCHAR(30) NOT NULL DEFAULT 'ACCEPTED'` (`ACCEPTED`, `REJECTED`, `PARTIAL`)
  - `notes`: `TEXT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `created_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (received_by_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`

### 9.2 `consumption_records`
- **Phase**: `CONFIRMED PHASE 1` (FR-042)
- **Category**: Transactional Record
- **Purpose**: Historical consumption tracking by entity, campus, standard item, and period.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `planning_entity_id`: `INT UNSIGNED NOT NULL`
  - `campus_id`: `INT UNSIGNED NOT NULL`
  - `standard_item_id`: `INT UNSIGNED NOT NULL`
  - `fiscal_year`: `YEAR NOT NULL`
  - `period_quarter`: `VARCHAR(5) NOT NULL` (`Q1`, `Q2`, `Q3`, `Q4`)
  - `quantity_consumed`: `DECIMAL(12,2) NOT NULL CHECK (quantity_consumed >= 0.00)`
  - `requisition_id`: `BIGINT UNSIGNED NULL`
  - `recorded_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `recorded_by`: `INT UNSIGNED NOT NULL`
- **Constraints**:
  - `FOREIGN KEY (planning_entity_id) REFERENCES planning_entities(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (standard_item_id) REFERENCES standard_items(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - `FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
- **Timestamp Semantics**: Single immutable `recorded_at`; zero `updated_at`/`updated_by`.

---

## Cluster 10: Protected Institutional Audit Logging

### 10.1 `audit_logs` (FR-039)
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: Protected Audit Record
- **Purpose**: Append-only protected institutional audit trail. Captures administrative, planning, and financial transactions with pre/post states.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `event_timestamp`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
  - `actor_user_id`: `INT UNSIGNED NOT NULL`
  - `planning_entity_id`: `INT UNSIGNED NULL`
  - `action`: `VARCHAR(50) NOT NULL` (e.g. `LOGIN`, `PLAN_APPROVED`, `REVISION_CREATED`, `REQ_COMMITMENT_AUTHORIZED`)
  - `record_type`: `VARCHAR(50) NOT NULL` (e.g. `PROCUREMENT_PLAN`, `REQUISITION`, `USER`)
  - `record_id`: `BIGINT UNSIGNED NOT NULL`
  - `ip_address`: `VARCHAR(45) NOT NULL` (IPv4 or IPv6)
  - `user_agent`: `VARCHAR(255) NULL`
  - `previous_state_json`: `JSON NULL` (Pre-change snapshot)
  - `new_state_json`: `JSON NULL` (Post-change snapshot)
- **Constraints & Security Protections**:
  - `FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
  - `FOREIGN KEY (planning_entity_id) REFERENCES planning_entities(id) ON DELETE SET NULL ON UPDATE RESTRICT`
  - **Audit Protection**: Application database credentials have zero `UPDATE` or `DELETE` privileges on `audit_logs`.
- **Timestamp Semantics**: Single immutable `event_timestamp`; zero `updated_at`/`updated_by`.

---

## Cluster 11: System Notifications

### 11.1 `notifications`
- **Phase**: `CONFIRMED PHASE 1`
- **Category**: System Notification Record
- **Purpose**: In-system user alerts for workflow, approval, and administrative events.
- **Columns**:
  - `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
  - `recipient_user_id`: `INT UNSIGNED NOT NULL`
  - `title`: `VARCHAR(150) NOT NULL`
  - `message`: `TEXT NOT NULL`
  - `notification_type`: `VARCHAR(30) NOT NULL DEFAULT 'INFO'` (`INFO`, `ACTION_REQUIRED`, `WARNING`, `SUCCESS`)
  - `reference_type`: `VARCHAR(50) NULL` (`REQUISITION`, `PLAN`, `REVIEW`)
  - `reference_id`: `BIGINT UNSIGNED NULL`
  - `is_read`: `TINYINT(1) NOT NULL DEFAULT 0`
  - `read_at`: `DATETIME NULL DEFAULT NULL`
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- **Constraints**:
  - `FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT`
- **Timestamp Semantics**: Tracks alert generation (`created_at`) and when the user reads the alert (`read_at`). Zero generic `updated_at`/`updated_by`.
