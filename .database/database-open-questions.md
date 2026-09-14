# PROMIS Physical Database Open Questions & Dependencies

## 1. Purpose
This document catalogs all open operational dependencies, institutional decisions, and unconfirmed technical parameters interfacing with the physical database design of the **Procurement Management Information System (PROMIS)**.

The schema has been specifically designed to accommodate these dependencies using configurable master tables, flexible nullable columns, and decoupled structural touchpoints without requiring schema breaking changes when decisions are finalized.

---

## 2. Catalog of Open Questions & Institutional Decisions

### 2.1 Financial & Budget Integration Fields (`commitment_authorizations`)
1. **Commitment Authorization Vote Code Structure**:
   - *Question*: Does the University Finance Directorate require a specific format, regex validation, or structural coding convention for `vote_code` and `commitment_reference` on `commitment_authorizations`?
   - *Design Treatment*: Maintained in Phase 1 strictly as a **structural finance touchpoint**. PROMIS does not invent University-specific financial accounting fields, vote numbers, or general ledger structures. Columns are modeled as nullable `VARCHAR(50)` and `VARCHAR(100)` respectively, marked as `TO BE CONFIRMED (TBC)`, allowing any alpha-numeric entry.
2. **Multi-Source Funding Allocation Mechanics**:
   - *Question*: Can a single procurement plan line item or requisition line draw simultaneously across multiple funding sources (e.g. 50% IGF, 50% GoG)?
   - *Design Treatment*: Phase 1 schema models a primary `funding_source` string per item. If split-funding allocation is required, an associative multi-source funding table can be introduced without altering core entity tables.

### 2.2 Balance Snapshot Strategy (`requisition_balance_snapshots`)
3. **Operational Dynamic Calculation vs. Historical Balance Snapshots**:
   - *Question*: Is `requisition_balance_snapshots` formally required as a separate permanent physical table, or is dynamic balance computation coupled with pre/post state logging in `audit_logs` sufficient?
   - *Design Treatment*: Retained with status `TO BE CONFIRMED (TBC)`.
   - *Architectural Rules*:
     - Real-time balance calculation remains the operational source of truth:
       $$\text{Remaining Before} = \text{Approved Planned Quantity} - \text{Previously Requested Quantity}$$
       $$\text{Remaining After} = \text{Remaining Before} - \text{Current Request Quantity}$$
     - Snapshots, if eventually approved, serve purely as historical evidence of balance at a defined workflow event (`SUBMISSION` or `COMMITMENT_AUTHORIZED`).
     - Snapshots must never become an alternative or competing source of truth for runtime operations without an explicit business decision.

### 2.3 Organizational Master Data Register
4. **Official ~62 Planning Entities & Campus Register**:
   - *Question*: When will the authoritative list of ~62 planning entities, entity codes, parent-child reporting hierarchies, and campus assignments be provided by the University Registrar?
   - *Design Treatment*: Zero entities, campuses, entity types, or users are hard-coded in the database schema or official seed scripts. All organizational data is fully configurable via `planning_entities`, `campuses`, `entity_types`, and `entity_hierarchies`. Any development mocks used for testing are isolated and explicitly labeled `DEVELOPMENT PLACEHOLDER`.

### 2.4 Workflow Approval Thresholds & Financial Ceilings
5. **Statutory Monetary Ceilings for Approval Step Rules**:
   - *Question*: What are the exact monetary threshold limits governing Head of Entity, Dean, Director of Procurement, Pro-Vice-Chancellor, and Vice-Chancellor approval steps under University Financial Regulations and the Public Procurement Act?
   - *Design Treatment*: `workflow_step_rules` provides nullable `threshold_min_amount` and `threshold_max_amount` columns. Zero statutory financial thresholds are hard-coded into the schema or application logic.

### 2.5 Technical Handover Format for GHANEPS
6. **Consolidated Plan Handover Format**:
   - *Question*: Has the Procurement Directorate confirmed the exact file format (CSV, Excel, JSON, XML) or API specification required for handing over consolidated procurement plans to GHANEPS?
   - *Design Treatment*: PROMIS provides the required procurement information for the applicable GHANEPS process. `ghaneps_export_packages.export_format` stores the format as `VARCHAR(20)` with status marked `TO BE CONFIRMED (TBC)`.

### 2.6 Downstream Procurement Operations Phasing
7. **Procurement Packages, Purchase Orders & Delivery Verification**:
   - *Question*: At what stage will post-requisition procurement operations (tendering packages, supplier purchase orders, and goods delivery inspection) transition from external/manual processes to fully digitized PROMIS modules?
   - *Design Treatment*:
     - `procurement_packages` is marked `TO BE CONFIRMED (TBC)`.
     - `delivery_records` is marked `TO BE CONFIRMED (TBC)`.
     - `purchase_orders` and `order_items` are classified as `PROPOSED LATER PHASE`.
     - These tables are excluded from Phase 1 procurement-operation requirements while maintaining clean structural foreign key anchors.

### 2.7 FR-051 Plan Version Association Nullability & Plan-Linkage Exclusivity
8. **Requisition Plan-Linkage Exclusivity & Column Nullability**:
   - *Question*: Will 100% of all requisitions created in PROMIS be strictly drawn against an approved procurement-plan version, or will the University permit emergency, contingency, or off-plan requisitions? Furthermore, during initial draft authoring prior to submission, can a requisition exist before a specific plan version is linked?
   - *Design Treatment*: Column `requisitions.approved_plan_version_id` is modeled as `INT UNSIGNED NULL` with nullability formally designated as `TO BE CONFIRMED / PROPOSED`. In accordance with design review directives, a proposed business relationship must **not** be converted into an unconditional `NOT NULL` database constraint without explicit requirement confirmation. If strict plan-linkage is confirmed, the constraint will be enforced as `NOT NULL` in the final DDL or validated at the application service layer upon submission.

### 2.8 Draft Requisition Deletion Governance & Forensic Retention
9. **Draft Requisition Purge vs. Soft-Discard**:
   - *Question*: Does University procurement policy permit physical purge of unsubmitted draft requisitions, or should discarded drafts transition to a `DISCARDED` status for complete administrative traceability?
   - *Design Treatment*: The database enforces `ON DELETE RESTRICT` on `requisition_items.requisition_id`, physically preventing engine-level cascading deletes. If physical draft purge is approved, the application Service Layer verifies `status = 'DRAFT'` and explicitly deletes draft child items inside a managed transaction before deleting the requisition header. If status-based soft discarding is preferred, no child records are ever deleted.

---

## 3. Impact Assessment on DDL Generation
None of these open questions impede the generation of production-ready MySQL 8.x DDL. The schema is specifically engineered with flexible, nullable fields, configurable master tables, and strict database-level constraints that seamlessly accommodate pending institutional decisions without requiring structural schema revisions or breaking migrations.
