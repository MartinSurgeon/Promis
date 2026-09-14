# PROMIS Physical Database Design: Overview & Standards

## 1. Executive Purpose & Technical Context
This specification establishes the physical database design for the **Procurement Management Information System (PROMIS)** of the **University of Science and Technology, Dedicated (USTED)**.

The physical design translates the approved conceptual data architecture into a robust, high-performance relational database implementation optimized for **MySQL 8.x** and accessed exclusively via **Core PHP 8.x** using **PHP Data Objects (PDO)** with parameterized prepared statements.

---

## 2. Authoritative Physical Table Inventory
The physical database encompasses an authoritative total of **36 physical tables**, partitioned strictly by project phasing:
- **`CONFIRMED PHASE 1`**: 31 Tables (Core identity, organization master, catalogue, annual planning, versioning, plan-linked requisitions, workflow, consolidation, consumption, protected audit, notifications).
- **`TO BE CONFIRMED`**: 3 Tables (`requisition_balance_snapshots`, `procurement_packages`, `delivery_records`).
- **`PROPOSED LATER PHASE`**: 2 Tables (`purchase_orders`, `order_items`).

```
+────────────────────────────────────────────────────────────────────────────────────────────────────+
|                               PROMIS PHYSICAL DATABASE: 36 TABLES                                   |
+──────────────────────────────+──────────────────────────────+──────────────────────────────────────+
| 1. Identity & Access (5)     | 2. Organization Master (4)   | 3. Standard Catalogue (3)            |
| - users [P1]                 | - campuses [P1]              | - item_categories [P1]               |
| - roles [P1]                 | - entity_types [P1]          | - units_of_measure [P1]              |
| - permissions [P1]           | - planning_entities [P1]     | - standard_items [P1]                |
| - role_permissions [P1]      | - entity_hierarchies [P1]    |                                      |
| - user_entity_roles [P1]     |                              |                                      |
+──────────────────────────────+──────────────────────────────+──────────────────────────────────────+
| 4. Budget & Planning (6)     | 5. Requisitions (4)          | 6. Workflow & Routing (4)            |
| - budget_allocations [P1]    | - requisitions [P1]          | - workflow_definitions [P1]          |
| - procurement_plans [P1]     | - requisition_items [P1]     | - workflow_step_rules [P1]           |
| - procurement_plan_versions  | - requisition_balance_snaps  | - workflow_action_logs [P1]          |
|   [P1 - FR-050]              |   [TBC]                      | - commitment_authorizations          |
| - procurement_plan_items [P1]| - supporting_documents [P1]  |   [P1 - Fields TBC]                  |
| - plan_review_cycles [P1-049]|                              |                                      |
| - plan_revision_records [P1] |                              |                                      |
+──────────────────────────────+──────────────────────────────+──────────────────────────────────────+
| 7. Consolidation (3)         | 8. Procurement Records (3)   | 9. Consumption & Delivery (2)        |
| - consolidation_batches [P1] | - procurement_packages [TBC] | - delivery_records [TBC]             |
| - consolidation_items [P1]   | - purchase_orders [LATER]    | - consumption_records [P1]           |
| - ghaneps_export_packages    | - order_items [LATER]        |                                      |
|   [P1 Info / Format TBC]     |                              |                                      |
+──────────────────────────────+──────────────────────────────+──────────────────────────────────────+
| 10. Protected Audit (1)      | 11. System Notifications (1) |                                      |
| - audit_logs [P1]            | - notifications [P1]         |                                      |
+──────────────────────────────+──────────────────────────────+──────────────────────────────────────+
```

### Distinction: Phase 1 Support vs. Rule Confirmation
- **Required for Phase 1 Database Support**: Tables marked `[P1]` must be established in the physical database schema to support Phase 1 operations.
- **Rules Remaining To Be Confirmed**: Field-level details, specific financial codes, and threshold values that depend on pending University procedural decisions are marked `TBC` and designed to remain flexible.
- **Authoritative Status Matrix**: The complete 9-attribute specification matrix covering all 36 physical tables (requirements, relationships, field TBC status, versioning semantics, and implementation notes) is detailed in [database-review-report.md Section 3](file:///c:/xampp/htdocs/promis/.database/database-review-report.md#3-authoritative-36-table-physical-status-matrix).

---

## 3. Storage Engine, Character Set & Collation Standards

### 3.1 Storage Engine: InnoDB
- **Engine**: Exclusively `ENGINE=InnoDB` across all 36 tables.
- **Rationale**: Guarantees ACID transactional compliance, row-level locking, foreign key referential integrity constraints, and crash recovery. MyISAM or non-transactional engines are strictly prohibited.

### 3.2 Authoritative Character Set & Collation: `utf8mb4` & `utf8mb4_0900_ai_ci`
In accordance with Decision DBD-002:
- **Character Set**: `utf8mb4`
- **Collation**: `utf8mb4_0900_ai_ci` (Applied authoritatively and consistently across all tables, text columns, and PDO connection parameters).
- **Technical & Institutional Rationale**:
  1. **Rejection of Obsolete Collation**: Legacy `utf8mb4_unicode_ci` is based on UCA 4.0.0 and lacks modern Unicode 9.0+ weightings, incurring performance penalties in MySQL 8.x.
  2. **Ghanaian & International Orthographic Fidelity**: Fully supports 4-byte Unicode encoding for Ghanaian indigenous characters and diacritics (e.g. Akan/Twi, Ga, Ewe letters such as Ɛ/ɛ, Ɔ/ɔ, Ɖ/ɖ, Ƒ/ƒ, Ŋ/ŋ).
  3. **Accent-Insensitive (`ai`) & Case-Insensitive (`ci`) Matching**: Ensures uniform searching across institutional user names, titles, and item descriptions.
  4. **Performance**: Modern MySQL 8.0 execution engine benchmarks show substantially faster sorting and indexing with `utf8mb4_0900_ai_ci` compared to legacy collations.

---

## 4. Key, Data Type & Foreign Key Policy

### 4.1 Primary Key Strategy
- **High-Volume Transactional Tables**: `BIGINT UNSIGNED AUTO_INCREMENT` (`audit_logs`, `workflow_action_logs`, `requisitions`, `requisition_items`, `notifications`, `consolidation_items`, `consumption_records`).
- **Standard Domain & Master Tables**: `INT UNSIGNED AUTO_INCREMENT` (`planning_entities`, `campuses`, `procurement_plans`, `procurement_plan_versions`, `procurement_plan_items`, `standard_items`, `roles`, `users`).
- **Composite Primary Keys**: Used strictly for pure associative mapping/junction tables (`role_permissions` using `(role_id, permission_id)`, `entity_hierarchies` using `(ancestor_entity_id, descendant_entity_id)`).

### 4.2 Foreign Key Policy: Rejecting Blanket `ON UPDATE CASCADE` & Restricting `CASCADE`
In accordance with Decision DBD-005:
- **Foreign Key Update Rule**: Default to `ON UPDATE RESTRICT`.
  - Blanket `ON UPDATE CASCADE` is **rejected**. The database relies on stable, synthetic surrogate integer primary keys (`AUTO_INCREMENT`) which are immutable.
  - Because surrogate keys are never updated during normal business operations, cascading key updates are conceptually inappropriate and risk masking data corruption.
- **Foreign Key Delete Rule (`ON DELETE RESTRICT`)**:
  - Enforced across all statutory, financial, planning, workflow, notification, and transactional records (`procurement_plans`, `procurement_plan_versions`, `procurement_plan_items`, `requisitions`, `requisition_items`, `workflow_definitions`, `workflow_step_rules`, `budget_allocations`, `commitment_authorizations`, `planning_entities`, `users`, `notifications`, `purchase_orders`, `order_items`).
  - *Requisition & Order Items Safety*: Unrestricted `ON DELETE CASCADE` is rejected; `requisition_items.requisition_id` and `order_items.purchase_order_id` enforce `ON DELETE RESTRICT`. MySQL engine-level cascade cannot evaluate business status (`DRAFT` vs `SUBMITTED`/`APPROVED`); `RESTRICT` physically prevents accidental destruction of submitted, approved, or audited items. Draft deletion is safely managed by the application Service Layer executing explicit child item deletions inside a transaction only when parent status is `DRAFT`.
  - *Workflow & Notification Records Preservation*: `workflow_step_rules.workflow_definition_id` and `notifications.recipient_user_id` enforce `ON DELETE RESTRICT`. Routing configurations and user accounts are deactivated rather than deleted, preserving audit and communication history.
- **Permitted `ON DELETE CASCADE`**:
  - Restricted strictly and exclusively to pure associative junction mappings (`role_permissions`, `entity_hierarchies`). Zero unexplained CASCADE rules exist in the schema.

### 4.3 Financial & Quantity Precision Tied to Requirements
In accordance with Decision DBD-015:
- **Monetary Amounts**: Defined as `DECIMAL(15,2)` with `CHECK (amount >= 0.00)`. Tied directly to annual procurement plan budgeting (FR-013, FR-014) and requisition costing (FR-023). Covers institutional procurement values up to 999 Billion GHS with exact 2-decimal fractional precision.
- **Quantities**: Standardized on `INT UNSIGNED` for discrete countable units (e.g. laptops, desks, reams). Defined as `DECIMAL(12,2)` **only** on items where fractional measurement is a known physical property (e.g. liters, meters).

---

## 5. Purpose-Driven Timestamp Strategy by Table Category

In accordance with Decision DBD-014, PROMIS rejects the blind application of `created_at`/`created_by`/`updated_at`/`updated_by` across all tables. Timestamps are defined strictly by table category:

```
+─────────────────────────────────────────────────────────────────────────────+
|                       TIMESTAMP STRATEGY BY CATEGORY                        |
+───────────────────────────+─────────────────────────────────────────────────+
| Master-Data Records       | created_at, created_by, updated_at, updated_by  |
| Transactional Records     | created_at, created_by, updated_at, updated_by  |
|                           | + stage timestamps (submitted_at, authorized_at)|
| Version Baseline Records  | created_at, created_by, approval_date, approver |
|                           | (No update columns; items frozen once approved) |
| Event & Decision Logs     | action_timestamp, actor_user_id (Append-only)   |
| Protected Audit Records   | event_timestamp, actor_user_id (Append-only)    |
| Notification Records      | created_at, read_at                             |
+───────────────────────────+─────────────────────────────────────────────────+
```

1. **Master-Data Records** (`campuses`, `entity_types`, `planning_entities`, `item_categories`, `units_of_measure`, `standard_items`, `roles`, `workflow_definitions`, `workflow_step_rules`):
   - Include `created_at`, `created_by`, `updated_at`, `updated_by` to track administrative configuration changes.
2. **Transactional Business Records** (`procurement_plans`, `requisitions`, `requisition_items`, `budget_allocations`, `commitment_authorizations`, `consolidation_batches`):
   - Full lifecycle tracking: `created_at`, `created_by`, `updated_at`, `updated_by`, plus specific business milestone timestamps (`submitted_at`, `authorized_at`).
3. **Version Baseline Records** (`procurement_plan_versions`, `procurement_plan_items`):
   - Capture `created_at`, `created_by`, `approval_date`, `approved_by_user_id`.
   - **No generic `updated_at`/`updated_by`** on line items; once approved, version baselines are frozen. Any adjustments require publishing a new version!
4. **Event & Decision Log Records** (`workflow_action_logs`, `plan_review_cycles`, `plan_revision_records`):
   - Capture single immutable event timestamp (`action_timestamp` / `completed_at` / `submitted_at`) and `actor_user_id`.
   - **Zero `updated_at`/`updated_by` columns**, explicitly reflecting their append-only nature.
5. **Audit Records** (`audit_logs`):
   - Single immutable timestamp: `event_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `actor_user_id`.
   - **Zero `updated_at`/`updated_by` columns**.
6. **Notification Records** (`notifications`):
   - `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` and `read_at DATETIME NULL DEFAULT NULL`.

---

## 6. Core Governance & Traceability Implementations

### 6.1 Procurement Plan Versioning & Single Source of Truth (FR-049, FR-050)
- **Annual Procurement Plan Root** $\rightarrow$ `procurement_plans`
- **Plan Versions** $\rightarrow$ `procurement_plan_versions`
- **Active Version Pointer**: `procurement_plans.current_version_id` serves as the **Single Authoritative Source of Truth** for the active approved plan version (`FOREIGN KEY (current_version_id) REFERENCES procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`). Column `is_current_active` is explicitly eliminated to eliminate competing sources of truth.
- **Quarterly Reviews** $\rightarrow$ `plan_review_cycles` (`NO_CHANGE` vs. `REVISION_REQUIRED`). Cross-plan consistency invariant is enforced via composite foreign key `(active_version_id, procurement_plan_id) REFERENCES procurement_plan_versions(id, procurement_plan_id)`.
- **Plan Revisions** $\rightarrow$ `plan_revision_records`. Cross-plan ownership invariants enforced via composite foreign keys `(prior_version_id, procurement_plan_id)` and `(new_version_id, procurement_plan_id)`.
- **Rule**: Historical approved versions are permanently preserved and never overwritten.

### 6.2 Proposed Physical Implementation of FR-051 (Plan Version Association)
- **Business Requirement (FR-051)**: *"Each plan-linked requisition shall retain a historical reference to the approved procurement-plan version under which it was submitted, and that historical association shall not be silently changed."* (Status: PROPOSED / TO BE CONFIRMED).
- **Proposed Physical Implementation**:
  ```sql
  approved_plan_version_id INT UNSIGNED NULL
  ```
  on `requisitions`, referencing `procurement_plan_versions(id) ON DELETE RESTRICT ON UPDATE RESTRICT`.
- **Nullability Dependency (TBC / Proposed)**:
  - Column nullability is formally classified as `TO BE CONFIRMED / PROPOSED`.
  - A proposed business relationship must **not** be converted into an unconditional `NOT NULL` database constraint unless requirements explicitly confirm that 100% of requisitions created in PROMIS must be associated with an approved procurement-plan version. If off-plan/emergency requisitions or unlinked draft requisitions are permitted, it remains nullable (`NULL`).
- **Preservation Rationale**:
  - Direct foreign key reference binds the requisition to the specific approved version snapshot active at submission.
  - When subsequent quarterly reviews publish Version 2.0, existing requisitions remain permanently anchored to Version 1.0.
  - `ON DELETE RESTRICT` ensures historical version baselines cannot be deleted while associated requisitions exist.

### 6.3 Requisition Quantity Balance Model
The schema strictly models the approved balance calculation:

$$\text{Remaining Before} = \text{Approved Planned Quantity} - \text{Previously Requested Quantity}$$

$$\text{Remaining After} = \text{Remaining Before} - \text{Current Request Quantity}$$

- `Approved Planned Quantity`: Derived from `procurement_plan_items.planned_quantity`.
- `Previously Requested Quantity`: Aggregated dynamically from approved requisition items.
- Formula $\text{Planned} - \text{Requested} - \text{Current}$ is **prohibited**.

### 6.4 Balance Snapshot Strategy (Table 21: `requisition_balance_snapshots`)
- **Status**: `TO BE CONFIRMED`.
- **Operational Truth**: Real-time dynamic calculation is the sole operational source of truth for available quotas.
- **Evidentiary Role**: If retained, snapshots serve exclusively as historical legal evidence of the certified balance at a formal workflow event (`SUBMITTED` or `COMMITMENT_AUTHORIZED`).
- **Guardrail**: Snapshots must not become an alternative or competing source of truth for runtime operations without an explicit institutional policy decision.

### 6.5 Structural Commitment Authorization Touchpoint
- `commitment_authorizations` is maintained in Phase 1 **only as a structural finance touchpoint**.
- Captures `requisition_id`, `finance_officer_id`, `budget_allocation_id`, `authorized_amount`, `authorization_status`, `authorized_at`.
- Unconfirmed financial fields (`vote_code`, `commitment_reference`) are marked as `TO BE CONFIRMED` and left unconstrained.

### 6.6 Consolidation Provenance Preservation
- `consolidation_items` stores references to `source_entity_id`, `source_campus_id`, `source_requisition_id`, and `source_plan_item_id`.
- Aggregates institutional packages without erasing originating departmental provenance.

---

## 7. Institutional Master Data Strategy & Zero Hard-Coding
- The ~62 planning entities, campuses, entity types, role titles, and approval thresholds are dynamic master data. Zero hard-coded values exist in schema definitions or official seed scripts.
- Technical system permissions are maintained separately from mock organizational data.
- Any development seed mocks are segregated and explicitly labeled `DEVELOPMENT PLACEHOLDER`.

---

## 8. DDL Idempotency & Verification Policy
- In accordance with user guidance, the database design recognizes that `CREATE TABLE IF NOT EXISTS` alone does **not** constitute proof of DDL idempotency.
- Complete DDL idempotency is an implementation-phase concern that will be engineered during the DDL generation phase covering schema objects, explicit indexes, foreign key constraints, seed migrations, and rollback scripts.

---

## 9. Environment-Specific DDL Builds & Deployment Guide

### 9.1 Authoritative DDL Builds
To reconcile enterprise production standards with local development environments without modifying schema topology or business constraints, two structurally identical DDL builds are maintained:

1. **Production MySQL 8.x Build** (`.database/schema.mysql8.sql`):
   - **Target Engine**: MySQL 8.0+ (InnoDB)
   - **Collation**: `CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci`
   - **Use Case**: Production, Staging, and CI/CD environments running official MySQL 8.x server binaries.
2. **Local XAMPP / MariaDB 10.4+ Build** (`.database/schema.mariadb10.sql`):
   - **Target Engine**: MariaDB 10.4.x+ / XAMPP (InnoDB)
   - **Collation**: `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
   - **Use Case**: Local developer workstations running standard XAMPP stacks where MySQL 8.0 `_0900_` collations are unsupported by MariaDB 10.4.

Both builds contain identical tables (36), identical foreign keys (122), identical delete/update actions (`CASCADE = 4`, `RESTRICT = 84`, `SET NULL = 34`, `ON UPDATE RESTRICT = 122`), matching column definitions, primary keys, and index constraints.

### 9.2 Fresh Database Installation Procedure

> [!CAUTION]
> **Destructive Operation**: `DROP DATABASE` permanently destroys all existing tables, data, audit trails, and user accounts. It must strictly be executed only during initial provisioning or controlled developer resets.

To guarantee that no schema drift or legacy table structures persist, execute a fresh deployment:

#### For Production (MySQL 8.x):
```sql
DROP DATABASE IF EXISTS `promis`;
CREATE DATABASE `promis` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `promis`;
SOURCE c:/xampp/htdocs/promis/.database/schema.mysql8.sql;
```

#### For Local Development (XAMPP MariaDB 10.4.32):
```sql
DROP DATABASE IF EXISTS `promis`;
CREATE DATABASE `promis` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `promis`;
SOURCE c:/xampp/htdocs/promis/.database/schema.mariadb10.sql;
```

### 9.3 Critical Deployment Invariants & Warnings

1. **Schema Drift Warning (`CREATE TABLE IF NOT EXISTS`)**:
   - `CREATE TABLE IF NOT EXISTS` is non-destructive and skips table creation if a table with the same name already exists in the target database.
   - If applied to an existing database with an outdated schema, it will **not** alter existing columns, fix data types, or correct foreign key cascade rules.
   - Initial deployments and verification audits must always be executed against a freshly created database.
2. **Accurate Semantics of `SET FOREIGN_KEY_CHECKS = 0`**:
   - `SET FOREIGN_KEY_CHECKS = 0;` temporarily disables referential integrity validation during DDL script execution, allowing circular dependencies (`procurement_plans` $\leftrightarrow$ `procurement_plan_versions`, `users` $\leftrightarrow$ `planning_entities`) and self-referencing master tables to be created regardless of definition order.
   - **Not Atomic Execution**: In MySQL and MariaDB, DDL statements (`CREATE TABLE`, `ALTER TABLE`) execute with implicit transaction commits; DDL scripts are inherently non-transactional and non-atomic. `SET FOREIGN_KEY_CHECKS` does not provide transactional rollback if a syntax or storage engine error occurs mid-script.
   - Foreign key checks are re-enabled upon script completion (`SET FOREIGN_KEY_CHECKS = 1;`).
