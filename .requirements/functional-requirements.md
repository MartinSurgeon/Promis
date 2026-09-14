# PROMIS Requirements: Functional Requirements Catalogue

## Overview

This document provides the formal specification of all Functional Requirements (FR-001 through FR-050) for PROMIS. Every requirement is stated using precise *"The system shall..."* language and is assigned a strict status classification.

---

## 1. Authentication & Access Control

### FR-001: User Authentication
The system shall authenticate authorized users via secure credentials prior to granting access to protected system functions.  
*Status*: `CONFIRMED / TECHNICAL`

### FR-002: Role and Permission Management
The system shall control user access to menus, forms, reports, and administrative actions according to assigned roles and permissions, with strict authorization enforcement executed on the server.  
*Status*: `CONFIRMED`  
*Exact final role list*: `TO BE CONFIRMED`

---

## 2. Planning Entity & Organizational Management

### FR-003: Planning Entity Management
The system shall maintain a configurable list of authorized planning entities supporting approximately 62 university organizational units.  
*Status*: `CONFIRMED / PENDING DATA`

### FR-004: Entity Master Data Administration
The system shall enable authorized administrators to create, edit, and deactivate organizational entities, capturing Entity Name, Entity Code, Campus, Entity Type, Parent Entity, Head, Planning Officer, Approving Authority, and Active Status.  
*Status*: `PENDING DATA / TO BE CONFIRMED`

### FR-005: User-to-Entity Assignment
The system shall support associating authorized users with one or more planning entities in accordance with approved university administrative assignments.  
*Status*: `CONFIRMED / TO BE CONFIRMED`

---

## 3. Budget Allocation

### FR-006: Budget Allocation Capture
The system shall support the capture or controlled upload/import of institutional budget allocations from the Directorate of Finance, associating allocation amounts with corresponding planning entities and budget lines.  
*Status*: `CONFIRMED CONCEPT`  
*Exact Finance interface and upload format*: `TO BE CONFIRMED`

---

## 4. Procurement Planning & Approval

### FR-007: Procurement Plan Creation
The system shall enable designated planning users within an entity to create an annual procurement plan based on their budget allocation and institutional planning guidelines.  
*Status*: `CONFIRMED`

### FR-008: Procurement Plan Item Entry
The system shall capture line-item planning details, including Item, Category, Planned Quantity, Estimated Unit Cost, Estimated Total Cost, Required Period/Quarter, Purpose, and Budget Line Reference.  
*Status*: `CONFIRMED CONCEPT`  
*Mandatory field list*: `TO BE CONFIRMED`

### FR-009: Procurement Plan Validation
The system shall validate procurement plan data against mandatory fields, valid catalogue items, and configured budget thresholds prior to permitting submission.  
*Status*: `CONFIRMED`

### FR-010: Procurement Plan Submission
The system shall allow authorized planning officers to submit a completed procurement plan into the university approval workflow.  
*Status*: `CONFIRMED`

### FR-011: Procurement Plan Approval Routing
The system shall route submitted procurement plans to the designated approving authority (e.g. Head of Department, Dean, Director) in accordance with university procedures.  
*Status*: `CONFIRMED`  
*Exact approval hierarchy*: `TO BE CONFIRMED`

### FR-012: Procurement Plan Query / Return
The system shall permit authorized approvers to return a submitted procurement plan to the planning entity with mandatory queries or comments requesting correction.  
*Status*: `CONFIRMED CONCEPT`  
*Query and return rules*: `TO BE CONFIRMED`

### FR-013: Procurement Plan Rejection
The system shall enable authorized approvers to reject a procurement plan, recording the rejecting officer, rejection date, status, and mandatory justification comments.  
*Status*: `CONFIRMED CONCEPT`

### FR-014: Approved Procurement Plan Repository
The system shall maintain all approved procurement plans in an easily retrievable digital repository for reference, monitoring, and requisition validation.  
*Status*: `CONFIRMED`

---

## 5. Quarterly Procurement Plan Review & Revision

### FR-049: Quarterly Procurement Plan Review
The system shall support the periodic review of an approved procurement plan on a quarterly basis in accordance with the University's procurement planning procedure. A quarterly review does not automatically imply a revision; it may result in either:
- **No change** (the plan remains the active version without alteration), or
- **Revision required** (authorized plan revision is initiated).  
*Status*: `CONFIRMED BY CLIENT / PROCEDURE TO BE CONFIRMED`

### FR-050: Procurement Plan Revision
The system shall support authorized revision of an approved procurement plan following the quarterly review process when changes are required. The system shall preserve full plan history and version information once versioning is approved, including:
- Original approved version
- Revised version
- Revision number
- Revision date
- Planning entity
- Person who prepared the revision
- Reason for revision where required
- Approval history
- Changed items
- Previous quantities
- Revised quantities
- Previous amounts
- Revised amounts  
Historical approved plan versions shall be preserved and **not** overwritten once versioning is approved.  
*Status*: `CONFIRMED BY CLIENT / VERSIONING DETAILS TO BE CONFIRMED`

---

## 6. Standard Item Catalogue

### FR-015: Standard Item Catalogue Enforcement
The system shall provide a standardized item catalogue for requisitioning and planning, restricting uncontrolled free-text descriptions for catalogue items.  
*Status*: `CONFIRMED`

### FR-016: Item Catalogue Governance
The system shall enable designated catalogue managers to create, edit, categorize, activate, and deactivate standard items, and identify potential duplicate items.  
*Status*: `CONFIRMED CONCEPT`  
*Catalogue governance role*: `TO BE CONFIRMED`

---

## 7. Requisitions & Plan Quantity Tracking

### FR-017: Electronic Item Request Creation
The system shall enable authorized departmental users to create electronic item requests capturing requesting unit, campus, required items, requested quantities, required date, and justification.  
*Status*: `CONFIRMED`

### FR-018: Linking Requests to Approved Plans
The system shall link item requests to corresponding approved procurement plan items where required by university policy.  
*Status*: `CONFIRMED CONCEPT`  
*Mandatory linkage rule*: `TO BE CONFIRMED`

### FR-051: Plan Version Association
Each plan-linked requisition shall retain a historical reference to the approved procurement-plan version under which it was submitted, and that historical association shall not be silently changed.  
*Status*: `PROPOSED / TO BE CONFIRMED`

### FR-019: Plan Quantity Tracking
Where plan-linked requesting is active, the system shall compute and track:
- Approved planned quantity
- Previously requested quantity
- Current request quantity
- Remaining available planned quantity  
*Status*: `PROPOSED / TO BE CONFIRMED`

### FR-020: Support for Partial Requests
The system shall support requesting an approved plan item in multiple batches over time (e.g. requesting 10 units now and 10 units later from an approved plan of 20).  
*Status*: `TO BE CONFIRMED`

### FR-021: Over-Plan Request Control
The system shall detect and either block or route into an exception approval workflow any item request exceeding the remaining approved planned quantity.  
*Status*: `TO BE CONFIRMED`

### FR-022: Requisition Validation
The system shall validate all mandatory requisition fields, positive quantities, and valid budget references before allowing submission.  
*Status*: `CONFIRMED`

### FR-023: Requisition Submission
The system shall enable authorized users to submit completed requisitions into the institutional approval pipeline.  
*Status*: `CONFIRMED`

---

## 8. Requisition Approval Lifecycle

### FR-024: Requisition Approval Routing
The system shall route submitted requisitions to the designated approving officer based on the requesting entity and university approval thresholds.  
*Status*: `CONFIRMED`  
*Approval logic*: `TO BE CONFIRMED`

### FR-025: Requisition Query / Return
The system shall allow approvers to return a requisition to the preparer with queries or change requests without permanently rejecting it.  
*Status*: `CONFIRMED CONCEPT`

### FR-026: Requisition Rejection
The system shall enable authorized approvers to reject a requisition, capturing the officer's identity, timestamp, status, and mandatory rejection reason.  
*Status*: `CONFIRMED CONCEPT`

### FR-027: Office-Specific Approval Sequences
The system shall support specific departmental workflows:
- **Departmental Flow**: Head of Department → Director Approval → Budget Commitment Authorization → Directorate of Procurement.
- **Director's Office Flow**: Administrator prepares → Director approves → Budget Commitment Authorization → Directorate of Procurement.  
*Status*: `CONFIRMED FOR DESCRIBED OFFICES / GENERAL APPLICATION TO BE CONFIRMED`

---

## 9. Budget Commitment Authorization

### FR-028: Budget Commitment Authorization Stage
The system shall route approved requisitions to the Directorate of Finance for Budget Commitment Authorization prior to releasing requirements for procurement processing.  
*Status*: `CONFIRMED CONCEPT`  
*Commitment decision rules*: `TO BE CONFIRMED`

### FR-029: Commitment Authorization Decisions
The system shall record the financial officer's decision as Authorized, Returned, or Rejected, capturing allocated commitment reference codes.  
*Status*: `TO BE CONFIRMED`

---

## 10. Consolidation & Handover to Procurement

### FR-030: Institutional Demand Consolidation
The system shall aggregate identical or matching items across planning entities, campuses, and periods into consolidated procurement packages.  
*Status*: `CONFIRMED`

### FR-031: Source Breakdown Preservation
The system shall preserve the source planning entity, campus, requester, individual requisition reference, and quantities for all consolidated requirements.  
*Status*: `CONFIRMED`

### FR-032: Periodic Consolidation Windows
The system shall support consolidating requirements across defined operational planning or procurement cycles.  
*Status*: `TO BE CONFIRMED`

### FR-033: Item Matching Logic
The system shall apply standard catalogue matching rules to group identical items and flag near-matching variants for procurement review.  
*Status*: `TO BE CONFIRMED`

### FR-034: Procurement Directorate View
The system shall provide Procurement Officers with comprehensive views of institutional totals, item specifications, departmental breakdowns, commitment status, and attached justifications.  
*Status*: `CONFIRMED CONCEPT`

### FR-035: Handover to Procurement Processing
The system shall mark commitment-authorized requisitions as released and available for procurement sourcing and order processing.  
*Status*: `CONFIRMED`  
*Detailed procurement processing inside PROMIS*: `TO BE CONFIRMED`

### FR-036: GHANEPS Boundary Interfacing
The system shall support formatting and exporting internal procurement requirement packages for downstream tendering and contract award in GHANEPS without duplicating supplier-side tendering in PROMIS.  
*Status*: `CONFIRMED`

---

## 11. Tracking, Audit & Consumption Records

### FR-037: Self-Service Status Tracking
The system shall allow requesting entities to track the real-time status and stage of their submitted requisitions.  
*Status*: `CONFIRMED`

### FR-038: Workflow History Tracking
The system shall maintain an end-to-end timeline of all workflow events, including submissions, approvals, returns, rejections, commitment authorizations, revisions, and status updates.  
*Status*: `CONFIRMED`

### FR-039: Audit Trail Protection
The system shall record an audit log for all critical administrative and financial transactions. Audit records shall be protected from unauthorized modification or deletion.  
*Status*: `CONFIRMED`

### FR-040: Searchable Procurement Records
The system shall index and store procurement records, making them retrievable by item, category, planning entity, campus, and financial period.  
*Status*: `CONFIRMED`

### FR-041: Delivery Record Tracking
Where goods are received within the approved project phase, the system shall record delivery dates, received quantities, and receiving entity acknowledgments.  
*Status*: `CONFIRMED IN PROPOSAL / PHASE TO BE CONFIRMED`

### FR-042: Historical Consumption Records
The system shall maintain a continuous, searchable record of item consumption by planning entity, campus, item, and fiscal period to support future procurement planning.  
*Status*: `CONFIRMED`

---

## 12. Management Reporting & Administration

### FR-043: Standard Management Reporting
The system shall generate institutional reports on:
- Consumption by item and category
- Requisition volumes by entity and campus
- Turnaround and processing times at each workflow stage
- Institutional consolidated requirement totals
- Status summaries and exception logs  
*Status*: `CONFIRMED`  
*Additional custom reports*: `TO BE CONFIRMED`

### FR-044: Multi-Parameter Filtering and Search
The system shall provide search and filter capabilities across records by item, entity, campus, status, date range, requester, category, and approval officer.  
*Status*: `PROPOSED / TO BE CONFIRMED`

### FR-045: Workflow Notifications
The system should notify relevant users of key workflow events (submission, approval, return, rejection, commitment) via system alerts or configured communication channels.  
*Status*: `PROPOSED / TO BE CONFIRMED`

### FR-046: Supporting Document Attachments
The system shall support uploading supporting files (e.g. memos, specifications, quotations) to requisitions and procurement plans.  
*Status*: `PROPOSED / TO BE CONFIRMED`

### FR-047: Role-Relevant Dashboards
The system should provide personalized dashboards displaying pending approval queues, action items, and relevant metrics for each authenticated role.  
*Status*: `PROPOSED`

### FR-048: System Administration
The system shall provide authorized administrators with interfaces to configure entities, campuses, item catalogues, users, roles, and approval routes.  
*Status*: `CONFIRMED CONCEPT / DETAILS TO BE CONFIRMED`
