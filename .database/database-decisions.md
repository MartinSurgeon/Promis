# PROMIS Physical Database Decisions (DBDs)

## 1. Overview & Purpose
This document formally records the Architecture and Database Decision Records (DBDs) governing the physical database schema of PROMIS.

---

## 2. Decision Log Index

| Decision ID | Decision Title | Status | Primary Rationale |
| :--- | :--- | :--- | :--- |
| **DBD-001** | MySQL 8.x + InnoDB as Standard Relational Engine | Accepted | ACID compliance, row-level locking, foreign key integrity |
| **DBD-002** | Authoritative Character Set (`utf8mb4`) & Collation (`utf8mb4_0900_ai_ci`) | Accepted | Single standard: Unicode 9.0+ UCA, Ghanaian orthography, case/accent insensitivity |
| **DBD-003** | Authoritative 36-Table Inventory & Phased Classification | Accepted | Resolves count discrepancy; isolates Phase 1 (31) from TBC (3) and Later (2) |
| **DBD-004** | Primary Key Typing Strategy (`BIGINT` vs `INT`) | Accepted | Prevents key exhaustion on high-volume logs while optimizing master indexes |
| **DBD-005** | Foreign Key Update Policy & Surrogate Key Stability | Accepted | Rejects blanket `ON UPDATE CASCADE`; surrogate keys are immutable; uses `RESTRICT` |
| **DBD-006** | Dedicated Plan Versioning Structure (FR-049, FR-050) | Accepted | Preserves historical approved versions; eliminates silent overwriting |
| **DBD-007** | Proposed Physical Implementation of FR-051 Plan Version Association | Accepted (Proposed / Nullability TBC) | Preserves historical link; nullability retained as TBC/proposed pending plan-link exclusivity |
| **DBD-008** | Evaluation of Requisition Balance Snapshots (Operational Truth vs Snapshot) | Accepted (TBC) | Dynamic calculation is source of truth; snapshot is historical evidence at approval |
| **DBD-009** | Structural Commitment Authorization Touchpoint | Accepted (Fields TBC) | Structural finance checkpoint; unresolved finance fields marked TBC |
| **DBD-010** | Consolidation Provenance Preservation Architecture | Accepted | Preserves source requisition, entity, campus, and period references |
| **DBD-011** | Protected Institutional Audit Trail Architecture | Accepted | Audit records protected from unauthorized modification or deletion |
| **DBD-012** | Zero Hard-Coding of Institutional Master Data | Accepted | ~62 planning entities, campuses, roles, and thresholds remain configurable |
| **DBD-013** | Separation of System Seeds from University Master Data | Accepted | Isolates technical system permissions from unconfirmed University seed mocks |
| **DBD-014** | Functional Timestamp Strategy by Table Category | Accepted | Tailors timestamps across 6 distinct record types; rejects blind 4-timestamp rule |
| **DBD-015** | Financial and Quantity Numeric Precision Tied to Requirements | Accepted | Exact decimal types tied to approved planning and budget requirements |
| **DBD-016** | Resolution of Active Plan Version Source of Truth & Mutual Dependency | Accepted | `procurement_plans.current_version_id` is sole truth; eliminates `is_current_active` |
| **DBD-017** | Cross-Plan / Version Consistency Invariants for Plan Review & Revision | Accepted | Composite FKs guarantee versions belong to same plan root; prevents cross-plan pollution |
| **DBD-018** | Environment-Specific DDL Builds (MySQL 8.x vs XAMPP MariaDB 10.4) | Accepted | Maintains `schema.mysql8.sql` and `schema.mariadb10.sql` with identical topology and engine-compatible collations |

---

## 3. Detailed Database Decision Records

### DBD-001: MySQL 8.x + InnoDB as Relational Engine
- **Context**: PROMIS handles multi-stage institutional procurement approvals, quantity balance calculations, and statutory financial checkpoints.
- **Decision**: Standardize exclusively on MySQL 8.x with `ENGINE=InnoDB` across all 36 tables.
- **Consequences**: Guarantees transaction atomicity, foreign key enforcement, row-level concurrency, and point-in-time recovery.

---

### DBD-002: Authoritative Character Set (`utf8mb4`) & Collation (`utf8mb4_0900_ai_ci`)
- **Context**: Previous documentation drafts contained conflicting mentions of `utf8mb4_unicode_ci` and `utf8mb4_0900_ai_ci`. The database requires a single authoritative standard across all tables and textual columns.
- **Decision**: Standardize authoritatively and exclusively on `CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci` across all tables.
- **Detailed Rationale**:
  1. **Rejection of Legacy `utf8mb4_unicode_ci`**: The `utf8mb4_unicode_ci` collation is based on the obsolete Unicode Collation Algorithm (UCA) 4.0.0 (dating back over two decades). It lacks modern Unicode weightings and uses expansive mapping tables that incur measurable performance overhead in MySQL 8.0+.
  2. **Adoption of `utf8mb4_0900_ai_ci`**: This is the native, optimized default collation of MySQL 8.0, based on UCA 9.0.0. It provides:
     - **Ghanaian Linguistic Fidelity**: Correct sorting and non-destructive storage of Ghanaian orthographic characters and diacritics (e.g., Akan/Twi, Ga, Ewe letters such as Ɛ/ɛ, Ɔ/ɔ, Ɖ/ɖ, Ƒ/ƒ, Ŋ/ŋ).
     - **Accent-Insensitive (`ai`) & Case-Insensitive (`ci`) Search**: Ensures that institutional lookups (e.g. searching "Adjei" vs "adjei", or items with accents) resolve consistently.
     - **Execution Speed**: Benchmarks in MySQL 8.x demonstrate up to 2x faster string comparisons and sorting for `_0900_` collations compared to legacy `_unicode_ci`.
  3. **Full 4-Byte Unicode**: Eliminates silent data truncation vulnerabilities associated with legacy 3-byte `utf8mb3`.
- **Enforcement**: Applied uniformly throughout all table definitions, column types, and connection strings.

---

### DBD-003: Authoritative 36-Table Inventory & Phased Classification
- **Context**: Previous drafts contained a numerical contradiction stating 32 tables while detailing 36 tables across clusters.
- **Decision**: Establish **36 physical tables** as the authoritative total physical inventory, partitioned strictly by phase:
  - **31 Tables in `CONFIRMED PHASE 1`**: Core operational scope (users, roles, permissions, role_permissions, user_entity_roles, campuses, entity_types, planning_entities, entity_hierarchies, item_categories, units_of_measure, standard_items, budget_allocations, procurement_plans, procurement_plan_versions, procurement_plan_items, plan_review_cycles, plan_revision_records, requisitions, requisition_items, supporting_documents, workflow_definitions, workflow_step_rules, workflow_action_logs, commitment_authorizations, consolidation_batches, consolidation_items, ghaneps_export_packages, consumption_records, audit_logs, notifications).
  - **3 Tables in `TO BE CONFIRMED`**: `requisition_balance_snapshots`, `procurement_packages`, `delivery_records`.
  - **2 Tables in `PROPOSED LATER PHASE`**: `purchase_orders`, `order_items`.
- **Consequences**: Resolves the inventory discrepancy definitively. Clearly distinguishes tables required for Phase 1 database support from downstream procurement operations.

---

### DBD-004: Primary Key Typing Strategy
- **Context**: Different tables experience vastly different write volumes over multi-year institutional lifecycles.
- **Decision**:
  - `BIGINT UNSIGNED AUTO_INCREMENT` for high-volume transactional and append-only log tables (`audit_logs`, `workflow_action_logs`, `requisitions`, `requisition_items`, `notifications`, `consolidation_items`, `consumption_records`).
  - `INT UNSIGNED AUTO_INCREMENT` for master data, planning containers, and configuration tables (`planning_entities`, `campuses`, `procurement_plans`, `procurement_plan_versions`, `procurement_plan_items`, `standard_items`, `roles`, `users`).
  - Composite Primary Keys for pure junction tables (`role_permissions` using `(role_id, permission_id)`, `entity_hierarchies` using `(ancestor_entity_id, descendant_entity_id)`).
- **Consequences**: Eliminates integer overflow risks on high-throughput audit and transaction tables without wasting memory on smaller master lookups.

---

### DBD-005: Foreign Key Update & Delete Policy (Surrogate Key Stability, Historical Context & Deletion Safety)
- **Context**: Using `ON UPDATE CASCADE` or `ON DELETE CASCADE` as blanket policies is dangerous in an enterprise procurement system. The schema relies on synthetic surrogate keys and contains statutory financial, workflow configuration, notification context, and transactional history.
- **Decision**: 
  - **Foreign Key Updates**: Reject `ON UPDATE CASCADE` as a blanket rule across foreign keys. Standardize on `ON UPDATE RESTRICT`.
  - **Foreign Key Deletes**: Standardize on `ON DELETE RESTRICT` across all statutory, financial, planning, workflow, notification, and transactional tables (`procurement_plans`, `procurement_plan_versions`, `procurement_plan_items`, `requisitions`, `requisition_items`, `workflow_definitions`, `workflow_step_rules`, `budget_allocations`, `commitment_authorizations`, `planning_entities`, `users`, `notifications`, `purchase_orders`, `order_items`).
  - **Requisition Line Items & Order Line Items Deletion Policy**: Enforce `ON DELETE RESTRICT` on `requisition_items.requisition_id` and `order_items.purchase_order_id`. Unrestricted cascading deletes on transactional child tables are rejected.
  - **Workflow Rules & Notifications Deletion Policy**: Enforce `ON DELETE RESTRICT` on `workflow_step_rules.workflow_definition_id` and `notifications.recipient_user_id`.
  - **Permitted Cascade**: `ON DELETE CASCADE` is restricted strictly and exclusively to pure associative junction tables (`role_permissions`, `entity_hierarchies`).
- **Detailed Rationale**:
  1. **Surrogate Key Immutability**: All parent tables use synthetic integer surrogate keys (`AUTO_INCREMENT`). Surrogate keys are assigned once upon row insertion and possess no business meaning; they are **never updated** during legitimate application lifecycles. A cascading update on surrogate primary keys is meaningless and risks propagating corruption.
  2. **Audit Immutability & Anti-Corruption**: Historical child records (e.g. audit logs, historical plan-linked requisitions) must maintain fixed pointers. `RESTRICT` ensures that parent identifiers cannot be altered underneath existing historical child records.
  3. **Prevention of Unrestricted Transactional Deletions**: MySQL engine-level cascading deletes cannot inspect business state (such as `WHERE status = 'DRAFT'`). If `ON DELETE CASCADE` were left on `requisition_items` or `order_items`, deleting a parent header would silently wipe out all child lines, destroying financial auditability, order tracking, and balance history.
  4. **Draft-Only Deletion Physical Guarantee**: By enforcing `ON DELETE RESTRICT` at the physical database layer, deletion of any requisition or purchase order with line items is physically blocked. When an unsubmitted draft requisition is intentionally discarded, the application Service Layer verifies `status = 'DRAFT'` and explicitly deletes the draft child lines within a managed transaction before deleting the header row.
  5. **Workflow Step Rules Configuration Safety**: Table `workflow_step_rules` is a master configuration table defining approval sequences and threshold limits, not a pure junction table. Furthermore, historical decisions in `workflow_action_logs` reference specific workflow step rules. Applying `ON DELETE RESTRICT` on `workflow_step_rules.workflow_definition_id` ensures that active or historical routing hierarchies cannot be accidentally purged; workflow definitions are deactivated rather than physically dropped.
  6. **User Context & Notification History Preservation**: Table `notifications` stores transactional alert records and historical communication context, not pure junction mappings. In accordance with institutional identity governance, user accounts are deactivated (`status = 'INACTIVE'`) rather than physically deleted. Applying `ON DELETE RESTRICT` on `notifications.recipient_user_id` protects user notification history and audit context from silent destruction.
  7. **Strict Confinement of CASCADE**: Only pure associative junction tables (`role_permissions` and `entity_hierarchies`), which exist solely to link two independent primary entities without holding independent business lifecycles, are permitted to declare `ON DELETE CASCADE`.
- **Consequences**: Maximum structural stability; surrogate relationships remain permanently locked and referentially intact. Submitted and approved requisition items, order lines, workflow rules, and notification alerts are physically shielded against accidental destruction. Zero unexplained CASCADE rules exist in the schema.

---

### DBD-006: Dedicated Plan Versioning Structure (FR-049, FR-050)
- **Context**: Approved annual procurement plans undergo quarterly reviews (FR-049) which may result in either `No Change` or `Revision Required` (FR-050).
- **Decision**:
  - `procurement_plans` serves as the fiscal-year root container.
  - `procurement_plan_versions` stores discrete version records (`v1.0`, `v2.0`).
  - `plan_review_cycles` tracks quarterly review events and outcomes.
  - `plan_revision_records` documents authorized revisions linking `prior_version_id` to `new_version_id`.
  - **Rule**: Historical approved versions are preserved and never overwritten.

---

### DBD-007: Proposed Physical Implementation of FR-051 Plan Version Association
- **Context**: FR-051 specifies: *"Each plan-linked requisition shall retain a historical reference to the approved procurement-plan version under which it was submitted, and that historical association shall not be silently changed."* The requirement status is `PROPOSED / TO BE CONFIRMED`. The exact physical column name is an architectural design choice, not an unchangeable requirement.
- **Decision**:
  - **Business Requirement**: Requisitions submitted against an approved plan version must retain an immutable historical association with that specific version, even after subsequent quarterly revisions create newer active versions.
  - **Proposed Physical Implementation**: Implement this relationship by adding a dedicated foreign key column on the `requisitions` table, designated as:
    ```sql
    approved_plan_version_id INT UNSIGNED NULL
    ```
    referencing `procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`.
  - **Nullability Dependency (TBC / Proposed)**:
    - Column nullability is formally classified as `TO BE CONFIRMED / PROPOSED`.
    - **Guardrail**: A proposed business relationship must **not** be converted into an unconditional `NOT NULL` database constraint unless client requirements explicitly confirm that 100% of requisitions created in PROMIS must be associated with an approved procurement-plan version.
    - If the University permits emergency requisitions, contingency requests, or off-plan items, or if draft requisitions may be saved prior to plan version selection, the column must remain nullable (`NULL`).
    - If future business rules mandate strict plan linkage upon submission, the constraint will be enforced as `NOT NULL` in the final schema or validated at the application service layer prior to submission.
  - **Rationale for Proposed Column & Relationship**:
    1. **Direct Version Binding**: Referencing `procurement_plan_versions` (the version record) rather than `procurement_plans` (the annual plan container) binds the requisition directly to the exact version snapshot active at submission.
    2. **Immunity to Subsequent Revisions**: When a quarterly revision is approved, a new version row (e.g. Version 2.0) is inserted into `procurement_plan_versions`. The existing requisition's `approved_plan_version_id` continues to point to Version 1.0.
    3. **Prevention of Deletion**: The `ON DELETE RESTRICT` constraint ensures the historical Version 1.0 row cannot be dropped or deleted from the database as long as requisitions reference it.
    4. **Application Immutability**: The Service Layer and Controller Layer enforce that `approved_plan_version_id` is set once during submission and is omitted from all subsequent `UPDATE` queries.

---

### DBD-008: Evaluation of Requisition Balance Snapshots (Operational Truth vs. Snapshot)
- **Context**: The relationship between real-time balance computation and historical balance snapshots must be clearly demarcated.
- **Decision**: Retain `requisition_balance_snapshots` with status `TO BE CONFIRMED`.
- **Architectural Rules**:
  1. **Real-Time Dynamic Calculation is the Sole Operational Truth**: The system must always calculate available plan quantities dynamically in real time:
     $$\text{Remaining Before} = \text{Approved Planned Quantity} - \text{Previously Requested Quantity}$$
     $$\text{Remaining After} = \text{Remaining Before} - \text{Current Request Quantity}$$
     Operational validation, submission checks, and approval screens derive available balances exclusively from active line-item records.
  2. **Snapshots are Historical Evidentiary Records Only**: If retained, `requisition_balance_snapshots` records are **not** used to calculate operational balances. They serve purely as evidentiary proof of what the balance was calculated to be at the exact instant of a formal workflow event (`SUBMISSION` or `COMMITMENT_AUTHORIZED`).
  3. **No Alternative Source of Truth**: Snapshots must **never** become an alternative or competing source of truth for runtime quota checks without an explicit institutional policy decision.

---

### DBD-009: Structural Commitment Authorization Touchpoint
- **Context**: FR-028/029 mandates a Budget Commitment Authorization stage within the requisition workflow, but University-specific finance systems, vote codes, and general ledger structures remain unconfirmed.
- **Decision**:
  - Keep `commitment_authorizations` in Phase 1 **only as a structural finance/authorization touchpoint**.
  - Do **not** invent University-specific accounting fields, reservation codes, or ledger integration columns.
  - Model core structural columns: `requisition_id`, `finance_officer_id`, `budget_allocation_id`, `authorized_amount`, `authorization_status`, `authorized_at`.
  - Unconfirmed financial fields (`vote_code`, `commitment_reference`) are marked as `TO BE CONFIRMED` and made nullable, allowing any alphanumeric format without premature validation constraints.

---

### DBD-010: Consolidation Provenance Preservation Architecture
- **Context**: When the Procurement Directorate consolidates departmental demands into institutional tender packages, source departmental identities must not be erased.
- **Decision**: `consolidation_items` stores references to `source_entity_id`, `requisition_id`, `requisition_item_id`, and `plan_item_id`.
- **Consequences**: Total demand is aggregated for bulk purchasing power while preserving complete institutional provenance back to the originating campus, entity, and requester.

---

### DBD-011: Protected Institutional Audit Trail Architecture
- **Context**: University procurement is subject to statutory audit by internal auditors and state bodies.
- **Decision**: Implement `audit_logs` as an append-only protected table. Application database credentials do not have `DELETE` or `UPDATE` privileges on `audit_logs`. Every entry records actor ID, action, entity ID, record ID, IP address, user agent, pre-change JSON, and post-change JSON.

---

### DBD-012: Zero Hard-Coding of Institutional Master Data
- **Context**: University structures, campuses, entity names, and approval limits evolve over time.
- **Decision**: Treat the ~62 planning entities, campuses, entity types, role titles, and approval thresholds strictly as dynamic, configurable master data in normalized tables. Zero hard-coded values in schemas.

---

### DBD-013: Separation of System Seeds from University Master Data
- **Context**: Seeding invented University organizational data risks creating erroneous production assumptions.
- **Decision**: Strictly separate technical system seeds (core permissions, fundamental document types) from University master data. Any mock data used for local developer testing is isolated in a separate script explicitly labeled `DEVELOPMENT PLACEHOLDER`.

---

### DBD-014: Functional Timestamp Strategy by Table Category
- **Context**: Applying `created_at`, `created_by`, `updated_at`, and `updated_by` blindly across all tables violates domain semantics, creates schema noise, and implies that immutable event tables can be updated.
- **Decision**: Classify all 36 tables into 6 distinct functional categories with purpose-driven timestamp semantics:

| Category | Description & Representative Tables | Timestamp Semantics |
| :--- | :--- | :--- |
| **1. Master-Data Records** | Configurable institutional setup (`campuses`, `entity_types`, `planning_entities`, `item_categories`, `units_of_measure`, `standard_items`, `roles`, `workflow_definitions`, `workflow_step_rules`). | `created_at`, `created_by` (nullable if seeded), `updated_at`, `updated_by`. Tracks administrative modifications over time. |
| **2. Transactional Business Records** | Core documents progressing through lifecycle states (`procurement_plans`, `requisitions`, `requisition_items`, `budget_allocations`, `commitment_authorizations`, `consolidation_batches`). | `created_at`, `created_by`, `updated_at`, `updated_by` + stage timestamps (`submitted_at`, `authorized_at`). |
| **3. Version Baseline Records** | Plan version snapshots and version line items (`procurement_plan_versions`, `procurement_plan_items`). | `created_at`, `created_by`, `approval_date`, `approved_by_user_id`. **No generic `updated_at`/`updated_by`** on items; modifications require a new version. |
| **4. Event & Decision Log Records** | Records of human workflow actions and review certifications (`workflow_action_logs`, `plan_review_cycles`, `plan_revision_records`). | Single event timestamp: `action_timestamp` (or `event_timestamp`, `completed_at`), `actor_user_id`. **Zero `updated_at`/`updated_by`** columns. |
| **5. Audit Records** | Protected forensic institutional audit trail (`audit_logs`). | Single immutable timestamp: `event_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `actor_user_id`. **Zero `updated_at`/`updated_by`**. |
| **6. Notification Records** | User alerts and delivery states (`notifications`). | `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `read_at DATETIME NULL`. Tracks when generated and when read. No generic update columns. |

---

### DBD-015: Financial and Quantity Numeric Precision Tied to Requirements
- **Context**: Numeric precision must reflect confirmed business requirements rather than speculative future use.
- **Decision**:
  - **Financial Amounts**: Standardized on `DECIMAL(15,2)` tied directly to procurement budgeting requirements (FR-013, FR-014, FR-023). Covers values up to 999 Billion GHS with exact 2-decimal fractional currency precision. Accompanied by `CHECK (amount >= 0.00)`.
  - **Quantities**: Standardized on `INT UNSIGNED` for discrete countable units (e.g. laptops, desks, reams). Defined as `DECIMAL(12,2)` **only** on items where fractional measurement is a known physical property (e.g., liters of fuel, meters of cable), accompanied by `CHECK (quantity >= 0.00)`.

---

### DBD-016: Resolution of Active Plan Version Source of Truth & Mutual Dependency
- **Context**: Earlier drafts maintained both `procurement_plans.current_version_id` (pointing to the version row) and `procurement_plan_versions.is_current_active` (a boolean flag on version rows). These represented competing sources of truth. In MySQL InnoDB, partial/filtered unique indexes (e.g. `CREATE UNIQUE INDEX ... WHERE is_current_active = 1`) do not exist, making it impossible to enforce at the database engine level that only one version row per plan has `is_current_active = 1`. Application updates could diverge, leading to dual-active version anomalies.
- **Decision**:
  1. **Single Authoritative Source of Truth**: Designate `procurement_plans.current_version_id` as the **sole authoritative pointer** to the currently active approved version.
  2. **Elimination of `is_current_active`**: Remove column `is_current_active` entirely from `procurement_plan_versions`. This eliminates competing sources of truth and satisfies 3NF without synchronization risks.
  3. **Foreign Key Constraint**: Define `FOREIGN KEY (current_version_id) REFERENCES procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT` on `procurement_plans`.
  4. **Mutual Dependency Resolution in MySQL**:
     - *Lifecycle Sequence*: A new plan is created in `DRAFT` status with `current_version_id = NULL`. Its initial draft version (v1.0) is created in `procurement_plan_versions` with `procurement_plan_id` referencing the plan. When v1.0 is formally approved, an atomic transaction updates `procurement_plan_versions.status = 'APPROVED'`, `procurement_plans.current_version_id = procurement_plan_versions.id`, and `procurement_plans.status = 'APPROVED'`. When a later version (v2.0) is approved, `current_version_id` is updated to point to v2.0, while v1.0 remains preserved with status `'SUPERSEDED'`.
     - *DDL Script Execution*: During schema creation, mutual foreign keys between `procurement_plans` and `procurement_plan_versions` are resolved cleanly by executing DDL with `SET FOREIGN_KEY_CHECKS = 0; ... SET FOREIGN_KEY_CHECKS = 1;` (standard MySQL script practice) or by declaring `current_version_id` as nullable and adding the constraint via `ALTER TABLE` after table creation.
     - *Deletion Safety*: `ON DELETE RESTRICT` ensures an active approved version row cannot be deleted while referenced by the root plan container.

---

### DBD-017: Cross-Plan / Version Consistency Invariants for Plan Review & Revision
- **Context**: `plan_review_cycles` and `plan_revision_records` reference both a procurement plan and procurement plan versions. Without cross-plan consistency constraints, an application error or unauthorized direct SQL query could link a review cycle or revision record to a plan version belonging to an unrelated entity or different fiscal year plan.
- **Decision**:
  1. **Define Strict Consistency Invariants**:
     - *Review Cycle Invariant*: `plan_review_cycles.active_version_id` MUST be a version belonging directly to `plan_review_cycles.procurement_plan_id`.
     - *Revision Record Invariant A (Plan Ownership)*: Both `plan_revision_records.prior_version_id` and `plan_revision_records.new_version_id` MUST belong directly to `plan_revision_records.procurement_plan_id`.
     - *Revision Record Invariant B (Review Alignment)*: If `plan_revision_records.review_cycle_id` is populated, that review cycle must belong to `procurement_plan_id`, its certified `active_version_id` must match `prior_version_id`, and its outcome must be `REVISION_REQUIRED`.
     - *Revision Record Invariant C (Version Progression)*: `prior_version_id != new_version_id`, with strictly monotonic version progression (e.g., `v1.0` -> `v2.0`).
  2. **Physical Database Enforcement**:
     - Define `UNIQUE KEY uq_version_plan (id, procurement_plan_id)` on `procurement_plan_versions`.
     - In `plan_review_cycles`, enforce composite foreign key:
       `FOREIGN KEY (active_version_id, procurement_plan_id) REFERENCES procurement_plan_versions(id, procurement_plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT`.
     - In `plan_revision_records`, enforce composite foreign keys:
       `FOREIGN KEY (prior_version_id, procurement_plan_id) REFERENCES procurement_plan_versions(id, procurement_plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT`, and
       `FOREIGN KEY (new_version_id, procurement_plan_id) REFERENCES procurement_plan_versions(id, procurement_plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT`.
  3. **Application Layer Enforcement**: The Application Service Layer transactional boundary enforces state machine transitions, review outcome prerequisites, and status updates (`SUPERSEDED` for prior version, `APPROVED` for new version).

---

### DBD-018: Environment-Specific DDL Builds (MySQL 8.x vs XAMPP MariaDB 10.4)
- **Context**: The approved physical schema standard (`DBD-002`) mandates `utf8mb4_0900_ai_ci` for production MySQL 8.x. However, standard local development workstations use XAMPP, which packages MariaDB 10.4.32. MariaDB 10.4 does not support the MySQL 8.0 `_0900_` collation family, throwing `ERROR 1273 (HY000): Unknown collation: 'utf8mb4_0900_ai_ci'`. Attempting to force a single DDL file across both engines either breaks enterprise production Unicode 9.0 standards or prevents local developer execution.
- **Decision**:
  1. **Dual Authoritative Builds**: Maintain two structurally identical DDL artifacts generated from `schema-specification.md`:
     - `.database/schema.mysql8.sql`: Targets MySQL 8.0+ using `CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci`.
     - `.database/schema.mariadb10.sql`: Targets MariaDB 10.4+ / XAMPP using `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`.
  2. **Strict Structural Invariance**: Both files share 100% identical table structures (36 tables), identical foreign keys (122 constraints), identical delete/update actions (`CASCADE = 4`, `RESTRICT = 84`, `SET NULL = 34`, `ON UPDATE RESTRICT = 122`), identical primary keys, unique constraints, and check expressions.
  3. **Deployment Targeting**: Production pipelines and staging environments deploy `schema.mysql8.sql`. Local developer workstations running XAMPP deploy `schema.mariadb10.sql`.

