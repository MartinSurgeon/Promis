# PROMIS Requirements: Conceptual Data Requirements

## Overview

This document defines the conceptual data model for PROMIS. It identifies the core business entities, their key attributes, relationships, and constraints. 

> [!NOTE]
> This represents a **conceptual domain model**, not the final physical MySQL database schema. The physical relational schema will be designed during implementation following confirmation of unresolved business rules.

---

## 1. Domain Entity Catalogue (24+ Core Areas)

```text
1. Users                      13. Requests / Requisitions
2. Roles                      14. Request Items
3. Permissions                15. Approvals
4. Campuses                   16. Workflow Actions
5. Planning Entities          17. Budget Commitment Authorizations
6. Entity Types               18. Demand Consolidations
7. Entity Relationships       19. Consolidated Items
8. Budget Allocations         20. Procurement Records & Orders
9. Procurement Plans          21. Delivery Records
10. Procurement Plan Versions 22. Consumption Records
11. Procurement Plan Items    23. Audit Logs
12. Standard Items & Cats     24. Attachments & Notifications
```

---

## 2. Core Entity Definitions & Attributes

### 1. Organizational & User Entities
- **Campuses**: `campus_id`, `campus_name`, `campus_code`, `location_details`, `is_active`.
- **Planning Entities**: `entity_id`, `entity_name`, `entity_code`, `campus_id`, `entity_type_id`, `parent_entity_id`, `head_user_id`, `planning_officer_id`, `is_active`, `created_at`.
- **Users**: `user_id`, `username`, `email`, `password_hash`, `full_name`, `staff_id`, `is_active`, `created_at`.
- **Roles & Permissions**: `role_id`, `role_name`, `description`; `permission_id`, `permission_key`; `user_roles` mapping; `user_entity_assignments` mapping.

### 2. Budget & Planning Entities
- **Budget Allocations**: `allocation_id`, `planning_entity_id`, `fiscal_year`, `budget_code`, `allocated_amount`, `committed_amount`, `spent_amount`, `created_at`.
- **Procurement Plans**: `plan_id`, `planning_entity_id`, `fiscal_year`, `current_version_id`, `overall_status` (Draft, Submitted, Approved, Under Review), `created_at`.
- **Procurement Plan Versions (FR-049, FR-050)**:
  - `version_id`, `plan_id`, `version_number` (e.g. 1.0, 2.0), `is_current_active`, `quarter_reviewed` (e.g. Q1, Q2, Q3), `review_outcome` (`NO_CHANGE`, `REVISION_REQUIRED`), `revision_date`, `revision_reason`, `prepared_by_user_id`, `approved_by_user_id`, `approved_at`, `created_at`.
  - *Constraint: Historical approved plan versions are preserved and never overwritten once versioning is approved.*
- **Procurement Plan Items**:
  - `plan_item_id`, `version_id`, `item_id`, `item_category_id`, `planned_quantity`, `estimated_unit_cost`, `estimated_total_cost`, `required_quarter`, `purpose`, `budget_code`, `status` (Active, Replaced, Cancelled).

### 3. Item Catalogue
- **Item Categories**: `category_id`, `category_name`, `category_code`, `parent_category_id`, `is_active`.
- **Standard Items**: `item_id`, `category_id`, `item_code`, `item_name`, `specification_template`, `unit_of_measure`, `is_active`, `created_at`.

### 4. Requisitions & Drawdowns
- **Requisitions**:
  - `requisition_id`, `requisition_number` (unique reference, e.g. `PR-2026-00125`), `planning_entity_id`, `campus_id`, `preparer_user_id`, `plan_version_id` (foreign key to approved plan version under which request was submitted, per FR-051), `required_date`, `justification`, `current_status` (Draft, Submitted, Pending Approval, Approved, Pending Commitment, Commitment Authorized, Sent to Procurement, Processing, Delivered, Rejected, Returned, Cancelled), `total_estimated_amount`, `created_at`, `updated_at`.
- **Request Items**:
  - `request_item_id`, `requisition_id`, `item_id`, `plan_item_id` (foreign key to approved plan item for plan-linked tracking), `quantity_requested`, `estimated_unit_price`, `estimated_line_total`, `specifications`.

### 5. Approvals & Budget Commitments
- **Approvals**: `approval_id`, `requisition_id` (or `plan_version_id`), `approver_user_id`, `approval_role`, `decision` (Approved, Returned, Rejected), `comments`, `decision_date`.
- **Budget Commitment Authorizations**: `commitment_id`, `requisition_id`, `finance_officer_user_id`, `budget_code`, `committed_amount`, `commitment_reference`, `decision` (Authorized, Returned, Rejected), `comments`, `committed_at`.

### 6. Consolidation & Handover
- **Demand Consolidations**: `consolidation_id`, `consolidation_reference`, `period_identifier`, `status` (Open, Closed, Handed Over), `created_by_user_id`, `created_at`.
- **Consolidated Items**: `consolidated_item_id`, `consolidation_id`, `item_id`, `total_consolidated_quantity`, `estimated_aggregate_value`.
- **Consolidated Item Requisitions** (Provenance mapping): `consolidated_item_id`, `request_item_id`, `planning_entity_id`, `campus_id`, `quantity_contributed`.

### 7. Historical Records, Consumption & Audit
- **Delivery Records**: `delivery_id`, `requisition_id`, `request_item_id`, `quantity_received`, `delivery_note_ref`, `received_by_user_id`, `received_date`.
- **Consumption Records**: `consumption_id`, `item_id`, `planning_entity_id`, `campus_id`, `quantity_consumed`, `period_year`, `period_quarter`, `source_requisition_id`.
- **Audit Logs**:
  - `audit_id`, `user_id`, `action_code` (e.g. `REQUEST_SUBMITTED`, `PLAN_REVISED`), `entity_type` (`REQUISITION`, `PLAN_VERSION`), `entity_id`, `previous_state`, `new_state`, `ip_address`, `timestamp`.
  - *Protection Constraint: Audit records shall be protected from unauthorized modification or deletion.*
- **Supporting Attachments**: `attachment_id`, `record_type`, `record_id`, `original_filename`, `stored_filename` (UUID), `file_size`, `mime_type`, `uploaded_by_user_id`, `uploaded_at`.

---

## 3. Entity Relationships & Cardinality

```text
Planning Entity 1 ─────── ∞ User Entity Assignments
Planning Entity 1 ─────── ∞ Budget Allocations
Planning Entity 1 ─────── ∞ Procurement Plans
Procurement Plan 1 ────── ∞ Procurement Plan Versions (FR-049, FR-050)
Procurement Plan Version 1 ─ ∞ Procurement Plan Items
Procurement Plan Item 1 ─── 0..∞ Request Items (Plan-Linked Tracking)
Requisition 1 ─────────── ∞ Request Items
Requisition 1 ─────────── 0..1 Procurement Plan Version (FR-051 Association)
Requisition 1 ─────────── ∞ Approvals
Requisition 1 ─────────── 0..1 Budget Commitment Authorization
Request Item 1 ────────── 0..1 Consolidated Item Requisition (Provenance)
Consolidation 1 ───────── ∞ Consolidated Items
```
