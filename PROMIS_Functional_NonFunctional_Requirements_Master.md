# PROMIS Functional & Non-Functional Requirements Master Workspace Specification

## AI Agent Instruction

You are the requirements architect and senior business/system analyst for PROMIS, the Procurement Management Information System for USTED.

This document is the single working source of truth for transforming the known PROMIS business information into a structured, implementation-ready requirements specification.

Your task is NOT to start coding the application immediately.

Your first responsibility is to:

1. Read and understand this entire document.
2. Separate confirmed requirements from assumptions.
3. Organize the requirements into a clean workspace structure.
4. Preserve unresolved items as `PENDING DATA` or `TO BE CONFIRMED`.
5. Never invent University business rules, approval authorities, organizational structures, or statutory procedures.
6. Produce clear Functional Requirements (FR), Non-Functional Requirements (NFR), Business Rules, Data Requirements, Workflow Requirements, Role/Permission Requirements, and Acceptance Criteria.
7. Keep the final requirements suitable for both human stakeholders and AI coding agents.
8. Use traceable requirement IDs and never silently change the business meaning of a requirement.

---

# 1. SOURCE OF TRUTH AND REQUIREMENT STATUS

Every requirement must be classified using one of these statuses.

## CONFIRMED

Use when the requirement is supported by the PROMIS proposal or explicitly confirmed during business-process discussions.

## PENDING DATA

Use when the requirement is known, but authoritative University master data has not yet been provided.

Example:

> PROMIS shall support approximately 62 planning entities.

The existence of the 62 entities is known, but the authoritative list is not yet available.

## TO BE CONFIRMED

Use when the system clearly needs a business rule, but the exact institutional rule has not yet been provided.

Example:

> Can one approved procurement-plan quantity be requested in several batches?

## PROPOSED

Use for a sensible technical or UX recommendation that has not yet been approved as an institutional rule.

Never silently promote `PROPOSED` or `TO BE CONFIRMED` items into `CONFIRMED`.

---

# 2. PROMIS SYSTEM CONTEXT

PROMIS means **Procurement Management Information System**.

The system is intended to electronically capture procurement requirements and support internal planning, requisitioning, consolidation, approval, status tracking, records, and consumption information.

PROMIS operates inside the University.

GHANEPS remains the supplier-facing procurement platform where required.

PROMIS must not replace GHANEPS or create a competing procurement process.

The PROMIS proposal describes the first phase as deliberately limited, with the requirement to be settled in detail before development or acquisition.

---

# 3. PRIMARY BUSINESS PROBLEM

The current process relies heavily on paper requisitions and information distributed across offices and campuses.

This makes it difficult for the University to:

- See total institutional requirements for an item.
- See current committed purchases.
- Maintain usable consumption history.
- Track request progress.
- Consolidate demand across units and campuses.
- Support procurement planning from reliable historical information.

PROMIS is intended to capture information at the point where the requirement arises and make it useful for planning, consolidation, management reporting, and procurement processing.

---

# 4. PRIMARY OBJECTIVES

PROMIS should enable the University to:

1. Replace paper requisitions with electronic requisitions.
2. Use a controlled standard item list rather than uncontrolled descriptions.
3. Consolidate identical or similar requirements across planning entities and campuses.
4. Provide visibility of approved procurement plans.
5. Track requests against approved plans where applicable.
6. Support electronic approvals.
7. Record approval, query, rejection, and related workflow actions.
8. Allow requesters to track status.
9. Provide Procurement with institutional totals and source-entity breakdowns.
10. Support Budget commitment authorization as defined by the institutional workflow.
11. Maintain retrievable procurement records.
12. Build a continuous consumption history.
13. Support reporting by item, category, entity, campus, and period.
14. Provide a foundation for later procurement and supply-chain digitalization.

---

# 5. ORGANIZATIONAL MODEL

The University has stated that PROMIS must support approximately **62 planning entities**.

The entity types may include:

- Departments
- Directorates
- Units
- Sections
- Halls
- Other administrative entities

The exact official list has not yet been provided.

PROMIS must therefore use a configurable organizational structure rather than hard-code the 62 entities.

## Planning Entity Data Model

The system should be capable of storing, where applicable:

- Entity Name
- Entity Code
- Campus
- Entity Type
- Parent Entity / Directorate
- Head
- Planning Officer
- Approving Authority
- Active/Inactive Status

Status: `PENDING DATA` for the actual 62 records.

Do not invent the actual entity names or codes.

---

# 6. ENTITY, USER, ROLE, AND PERMISSION DISTINCTION

Keep these concepts separate.

## Planning Entity

An organizational body that owns or submits procurement requirements.

## User

A person who logs into PROMIS.

## Role

A defined responsibility assigned to a user.

## Permission

A specific action the user is allowed to perform.

## Approval Authority

The person or authority designated by University procedure to approve a particular transaction.

Do not assume:
`62 entities = 62 users`
or:
`62 entities = 62 roles`.

One entity may have multiple users, and a user may potentially have multiple responsibilities subject to University rules.

---

# 7. ACTOR MODEL

The exact official user list has not yet been provided.

Potential actors identified from the business discussions and proposal include:

- Planning/Requesting User
- Planning Officer
- Departmental Head
- Director
- Administrator acting on behalf of an office
- Budget Officer
- Procurement Officer
- System Administrator
- Management/Authorized Reviewer

These are not all final roles.

Status: `TO BE CONFIRMED`

The system must support configurable role assignment rather than hard-coded job titles wherever practical.

---

# 8. CORE BUSINESS LIFECYCLE

The broad PROMIS lifecycle is:

```text
Budget Allocation
        ↓
Planning Entity
        ↓
Procurement Plan
        ↓
Procurement Plan Approval
        ↓
Approved Procurement Plan
        ↓
Item Request / Requisition
        ↓
Approval
        ↓
Budget Commitment Authorization
        ↓
Institutional Consolidation
        ↓
Procurement Processing
        ↓
GHANEPS where required
        ↓
Procurement Records / Delivery / Consumption
```

Not every downstream procurement activity must necessarily be implemented in the first phase.

The actual system boundary must be confirmed against the approved implementation phase.

---

# 9. IMPORTANT BUSINESS DISTINCTION

## Procurement Plan

Represents what an entity intends or plans to procure.

## Item Request / Requisition

Represents what the entity actually requests based on its procurement plan.

The system must be able to relate requests back to the relevant approved procurement-plan item where the approved business rules require this.

Do not collapse both concepts into one database record simply because they contain similar item information.

---

# 10. FUNCTIONAL REQUIREMENTS

## FR-001: User Authentication

PROMIS shall authenticate authorized users before allowing access to protected functions.
Status: `CONFIRMED / TECHNICAL`

## FR-002: Role and Permission Management

PROMIS shall control access to functions according to assigned roles and permissions. Permissions shall be enforced server-side.
Status: `CONFIRMED`
Exact final role list: `TO BE CONFIRMED`

## FR-003: Planning Entity Management

PROMIS shall maintain a configurable list of authorized planning entities. The system shall support approximately 62 entities.
Status: `CONFIRMED / PENDING DATA`

## FR-004: Entity Master Data

Authorized administrators shall be able to maintain organizational master data, subject to institutional rules.

Potential fields:

- Entity Name
- Entity Code
- Campus
- Entity Type
- Parent Entity
- Head
- Planning Officer
- Approving Authority
- Active/Inactive

Status: `PENDING DATA / TO BE CONFIRMED`

## FR-005: User-to-Entity Assignment

PROMIS shall support assigning authorized users to planning entities according to University rules.
Status: `CONFIRMED / TO BE CONFIRMED`

## FR-006: Budget Allocation Management

PROMIS shall support capture or controlled import/upload of budget allocations from the Finance/Budget function, where approved. Budget allocations may be associated with relevant planning entities.
Status: `CONFIRMED CONCEPT`
Exact Finance process: `TO BE CONFIRMED`

## FR-007: Procurement Plan Creation

Authorized planning users shall be able to create procurement plans based on their approved allocation and applicable planning rules.
Status: `CONFIRMED`

## FR-008: Procurement Plan Item Entry

A procurement plan shall support item-level information sufficient for planning and later tracking.

Potential information:

- Item
- Category
- Planned Quantity
- Estimated Unit Cost
- Estimated Total Cost
- Required Period
- Purpose
- Budget Reference

Exact mandatory fields: `TO BE CONFIRMED`

## FR-009: Procurement Plan Validation

PROMIS shall validate procurement plans against required fields and configured business rules before submission.
Status: `CONFIRMED`

## FR-010: Procurement Plan Submission

Authorized planning users shall be able to submit completed procurement plans into the defined approval workflow.
Status: `CONFIRMED`

## FR-011: Procurement Plan Approval

PROMIS shall route submitted procurement plans to the appropriate approving authority according to configured University procedures.
Status: `CONFIRMED`
Exact approval hierarchy: `TO BE CONFIRMED`

## FR-012: Procurement Plan Return / Query

Authorized approvers shall be able to return or query plans requiring correction where supported by University procedure.
Status: `CONFIRMED CONCEPT`
Exact return/query rules: `TO BE CONFIRMED`

## FR-013: Procurement Plan Rejection

Authorized approvers shall be able to reject plans where permitted. The rejection must be recorded with relevant date, officer, reason, and status.
Status: `CONFIRMED CONCEPT`

## FR-014: Approved Procurement Plan Repository

PROMIS shall maintain approved procurement plans for retrieval and reference.
Status: `CONFIRMED`

## FR-015: Standard Item Catalogue

PROMIS shall provide a standard item list for requisitioning instead of relying on uncontrolled free-text descriptions.
Status: `CONFIRMED`

## FR-016: Item Catalogue Management

Authorized users shall be able to manage standard items subject to an approved governance process.
Potential functions:

- Create item
- Edit item
- Categorize item
- Activate item
- Deactivate item
- Identify similar/duplicate items

Who manages the catalogue: `TO BE CONFIRMED`

## FR-017: Item Request / Requisition Creation

Authorized planning/requesting users shall be able to create an electronic item request.

At minimum, the proposal identifies:

- Requesting unit/entity
- Campus
- Quantity
- Date required
- Purpose

Status: `CONFIRMED`

## FR-018: Request Against Approved Plan

Where required by the business rules, PROMIS shall relate an item request to the relevant approved procurement-plan item.
Status: `CONFIRMED CONCEPT`
Whether every request must be within approved planned quantities: `TO BE CONFIRMED`

## FR-019: Planned Quantity Tracking

Where plan-linked requesting is enforced, PROMIS should track:

- Approved planned quantity
- Previously requested quantity
- Current request quantity
- Remaining quantity
  Status: `PROPOSED / TO BE CONFIRMED`

## FR-020: Partial Requests

PROMIS should support requesting an approved quantity in portions if this is permitted by the institutional process.
Example: Approved plan = 20 laptops; requests may potentially be 10 now, 5 later, 5 later.
Status: `TO BE CONFIRMED`

## FR-021: Over-Plan Requests

PROMIS shall identify or prevent requests that exceed approved quantities if the business rule requires such control.
Status: `TO BE CONFIRMED`

## FR-022: Request Validation

PROMIS shall validate mandatory request fields before submission.
Status: `CONFIRMED`

## FR-023: Request Submission

Authorized users shall be able to submit a completed request into the configured approval workflow.
Status: `CONFIRMED`

## FR-024: Request Approval

PROMIS shall route the request to the appropriate approving authority.
Status: `CONFIRMED`
Exact approval logic: `TO BE CONFIRMED`

## FR-025: Request Query / Return

Authorized approvers shall be able to query or return requests where permitted.
Status: `CONFIRMED CONCEPT`

## FR-026: Request Rejection

Authorized approvers shall be able to reject requests where permitted. The rejection should include responsible officer, date, status, and reason where required.
Status: `CONFIRMED CONCEPT`

## FR-027: Office-Specific Approval Workflow

For the specifically described office:

```text
Departmental Head
→ Director Approval
→ Budget Commitment Authorization
→ Directorate of Procurement
```

For the Director's Office:

```text
Administrator prepares request
→ Director approves
→ Budget Commitment Authorization
→ Directorate of Procurement
```

Status: `CONFIRMED FOR THE DESCRIBED OFFICE`

Do not automatically apply this sequence to all 62 entities.

## FR-028: Budget Commitment Authorization

PROMIS shall support the Budget commitment authorization stage before the request returns to Procurement for processing, where this is part of the configured institutional workflow.
Status: `CONFIRMED CONCEPT`
Budget checks and decision rules: `TO BE CONFIRMED`

## FR-029: Commitment Authorization Decision

The system should support approved outcomes such as:

- Authorized
- Returned
- Rejected
  Status: `TO BE CONFIRMED`

## FR-030: Consolidated Institutional Requirement

PROMIS shall consolidate requirements for identical or sufficiently matching items according to approved consolidation rules.
Status: `CONFIRMED`

## FR-031: Source Breakdown Preservation

Consolidation shall preserve source entity, campus, requester, quantity, request reference, and relevant workflow information.
Status: `CONFIRMED`

## FR-032: Consolidation by Period

PROMIS shall support consolidation within a defined period.
Exact consolidation period: `TO BE CONFIRMED`

## FR-033: Consolidation Matching Rules

PROMIS shall apply controlled item matching based on the standard item catalogue and approved rules.
Definition of identical versus similar items: `TO BE CONFIRMED`

## FR-034: Procurement View

Procurement users shall be able to see:

- Institutional total requirement
- Item
- Planning entity
- Campus
- Requested quantities
- Status
- Relevant approvals
- Commitment status
- Supporting records where applicable
  Status: `CONFIRMED CONCEPT`

## FR-035: Procurement Processing Handover

PROMIS shall make authorized requirements available to the Directorate of Procurement for processing.
Status: `CONFIRMED`
Exact procurement-stage functionality inside PROMIS: `TO BE CONFIRMED`

## FR-036: GHANEPS Boundary

PROMIS shall not replace GHANEPS. Where supplier-facing procurement activity is required through GHANEPS, PROMIS shall support the necessary internal information flow without unnecessarily duplicating GHANEPS functionality.
Status: `CONFIRMED`

## FR-037: Status Tracking

Users shall be able to view the current status of their requests.
Status: `CONFIRMED`

## FR-038: Workflow History

PROMIS shall retain a history of important workflow actions, including applicable submission, approval, query, return, rejection, commitment authorization, and procurement status updates.
Status: `CONFIRMED`

## FR-039: Audit Trail

PROMIS shall maintain auditable records for important actions.
Status: `CONFIRMED`

## FR-040: Procurement Records

PROMIS shall retain relevant procurement records in a retrievable manner by item, unit/entity, campus, and period.
Status: `CONFIRMED`

## FR-041: Delivery Records

Where delivery records are within the approved phase, PROMIS shall retain relevant delivery information.
Status: `CONFIRMED IN PROPOSAL / PHASE TO BE CONFIRMED`

## FR-042: Consumption Records

PROMIS shall support a continuous retrievable record of consumption by relevant item, entity, campus, and period.
Status: `CONFIRMED`

## FR-043: Reporting

PROMIS shall provide reports such as:

- Consumption by item/category
- Requisition volumes by entity
- Processing time at each stage
- Institutional requirement totals
- Status summaries
  Status: `CONFIRMED`
  Additional reports: `TO BE CONFIRMED`

## FR-044: Search and Filtering

The system shall support retrieval and filtering by relevant fields such as item, entity, campus, status, date range, requester, category, approval state, and procurement status.
Status: `PROPOSED / TO BE CONFIRMED`

## FR-045: Notifications

PROMIS should notify users of important workflow events where required.
Potential events:

- Submission
- Approval
- Return/query
- Rejection
- Commitment authorization
- Status change
  Status: `PROPOSED / TO BE CONFIRMED`
  Notification channels: `TO BE CONFIRMED`

## FR-046: Supporting Documents

PROMIS should support appropriate attachments where required for procurement requests, approvals, or supporting records.
Status: `PROPOSED / TO BE CONFIRMED`

## FR-047: Dashboard

PROMIS should provide role-relevant dashboards showing actionable information.
Status: `PROPOSED`

## FR-048: Administration

Authorized administrators shall be able to manage configured system master data and security settings appropriate to their authority.
Potential areas:

- Users
- Roles
- Permissions
- Planning entities
- Campuses
- Entity types
- Item catalogue
- Workflow configuration
  Status: `CONFIRMED CONCEPT / DETAILS TO BE CONFIRMED`


**FR-049: Quarterly Procurement Plan Review**

> PROMIS shall support the periodic review of an approved procurement plan on a quarterly basis, in accordance with the University's procurement planning procedure.

**Status:** `CONFIRMED BY CLIENT / UNIVERSITY PROCEDURE TO BE DOCUMENTED`

Then:

**FR-050: Procurement Plan Revision**

> PROMIS shall allow an authorized planning entity to revise its procurement plan following the approved quarterly review process.

The system should preserve:

* Original approved plan
* Revised plan
* Revision date
* Revision number/version
* Entity
* User who prepared the revision
* Reason for revision, where required
* Approval history
* Changes made to quantities/amounts/items
* Effective version of the plan

**Status:** `CONFIRMED BY CLIENT / DETAILED RULES TO BE CONFIRMED`

---

# 11. NON-FUNCTIONAL REQUIREMENTS

## NFR-001: Security

PROMIS shall use secure authentication, authorization, input validation, output encoding, CSRF protection, session protection, and parameterized database access.
Status: `CONFIRMED TECHNICAL`

## NFR-002: Password Security

Passwords shall not be stored in plaintext.
Status: `CONFIRMED`

## NFR-003: Authorization Enforcement

All sensitive actions shall be authorized on the server.
Status: `CONFIRMED`

## NFR-004: Data Integrity

The system shall preserve the integrity and relationship of budget allocations, procurement plans, requests, approvals, commitments, consolidations, and records.
Status: `CONFIRMED`

## NFR-005: Auditability

Important workflow and administrative actions shall be traceable.
Status: `CONFIRMED`

## NFR-006: Reliability

The system should minimize transaction failures and use database transactions where multiple changes must succeed together.
Status: `CONFIRMED TECHNICAL`

## NFR-007: Performance

Normal operations should be designed to respond efficiently under expected institutional load.
Specific response-time targets: `TO BE CONFIRMED`

## NFR-008: Scalability

The system shall support approximately 62 planning entities without requiring source-code changes whenever organizational records are added, modified, or deactivated.
Status: `CONFIRMED DESIGN PRINCIPLE`

## NFR-009: Maintainability

The system shall use modular, documented, understandable code and reusable components.
Status: `CONFIRMED`

## NFR-010: Usability

The system shall be understandable and usable by non-technical institutional users.
Status: `CONFIRMED`

## NFR-011: Mobile Responsiveness

The web application shall use a mobile-first responsive design and remain usable across phones, tablets, laptops, and desktops.
Status: `CONFIRMED`

## NFR-012: Accessibility

The system shall provide appropriate labels, focus states, keyboard accessibility, readable contrast, meaningful status communication, and accessible form/error feedback.
Status: `CONFIRMED TECHNICAL`

## NFR-013: UI Consistency

Repeated functions shall use consistent components, labels, buttons, status indicators, spacing, and interaction patterns.
Status: `CONFIRMED`

## NFR-014: Cognitive Load

The interface shall reduce unnecessary decision-making and cognitive overload through clear hierarchy, grouped content, progressive disclosure, predictable navigation, and limited competing CTAs.
Status: `CONFIRMED UX REQUIREMENT`

## NFR-015: Error Handling

The system shall provide safe and understandable errors while protecting internal technical details.
Status: `CONFIRMED`

## NFR-016: Logging

System and security events requiring operational investigation shall be logged securely.
Status: `CONFIRMED TECHNICAL`

## NFR-017: Backup and Recovery

The system shall support appropriate backup and recovery procedures.
Specific backup frequency, retention, RPO, and RTO: `TO BE CONFIRMED`

## NFR-018: Session Security

Sessions shall be managed using appropriate secure configuration and lifecycle controls.
Status: `CONFIRMED TECHNICAL`

## NFR-019: Data Retention

Records shall be retained according to applicable University and statutory requirements.
Exact retention periods: `TO BE CONFIRMED`

## NFR-020: Compatibility

The system shall support modern browsers used by the University.
Exact browser/version matrix: `TO BE CONFIRMED`

## NFR-021: Deployment

The application shall be deployable within the University's approved hosting/infrastructure environment.
Exact production environment: `TO BE CONFIRMED`

## NFR-022: File Upload Security

Where uploads are implemented, files shall be validated and stored securely.
Status: `CONFIRMED TECHNICAL`

## NFR-023: Transaction Safety

Critical multi-step database operations shall maintain atomicity where appropriate.
Status: `CONFIRMED TECHNICAL`

## NFR-024: Data Export

Where reporting/export is required, generated outputs shall preserve data accuracy and appropriate access control.
Status: `TO BE CONFIRMED`

## NFR-025: Concurrency

The system shall protect important records from unsafe concurrent changes where required.
Status: `CONFIRMED DESIGN REQUIREMENT`

---

# 12. UI/UX AND HCI REQUIREMENTS

PROMIS must make the user's next correct action obvious.

Apply:

- One primary action per major section.
- Hick's Law: reduce competing choices and unnecessary CTAs.
- Fitts's Law: important controls must be large, reachable, and easy to tap.
- Jakob's Law: use familiar patterns for navigation, forms, cards, tables, modals, search, filters, and confirmation dialogs.
- Miller's Law: break long choice lists into meaningful groups, often around 5–7 items where practical.
- Gestalt proximity: keep related elements together.
- Gestalt similarity: similar components must look and behave consistently.
- Serial position effect: put important information near the beginning and useful next-step information near the end.
- Peak-end rule: end important workflow actions with clear confirmation, reference, current status, and next step.
- Tesler's Law: organize unavoidable institutional complexity with grouping and progressive disclosure.
- Aesthetic-usability effect: maintain polished hierarchy, spacing, typography, consistency, and visual credibility.

Additional UX rules:

- Predictable, shallow navigation.
- Repeated page patterns.
- Scannable cards/lists/short paragraphs.
- Generous intentional whitespace.
- Plain language, while preserving precise procurement terms.
- Forms grouped logically and kept as short as possible.
- Clear loading, empty, success, error, and validation states.
- Accessible contrast and non-color-only status communication.
- Mobile-first interaction.

---

# 13. UI STATE REQUIREMENTS

Every important data-driven interface should consider:

```text
Loading
Empty
Success
Error
Validation
```

Important workflow actions should explain:

- What happened.
- Reference number where applicable.
- Current status.
- Next expected step.

Example:

```text
Request submitted successfully.

Reference: PR-2026-00125
Status: Pending Director Approval
Next Step: Director review
```

---

# 14. BUSINESS RULES

BR-001: PROMIS shall operate within approved University procurement procedures and applicable statutory requirements.

BR-002: PROMIS shall not create a new procurement method.

BR-003: PROMIS shall not change procurement thresholds or approval authority.

BR-004: PROMIS shall not replace GHANEPS.

BR-005: Standardized item descriptions should be used to support consolidation.

BR-006: Planning-entity information shall remain traceable after consolidation.

BR-007: Approval, query, rejection, and other important workflow actions shall be attributable to the responsible officer.

BR-008: Requesters should be able to see the current status of their requests.

BR-009: Procurement should be able to see institutional totals and source-entity breakdowns.

BR-010: Organizational structures must be configurable and not hard-coded.

BR-011: The actual 62 entity records must come from authoritative University data.

BR-012: The specific approval hierarchy must come from approved institutional procedures.

---

# 15. DATA MODEL AREAS TO BE EXPECTED

This is not yet the final database schema.

The requirements imply data areas such as:

```text
Users
Roles
Permissions
Campuses
Planning Entities
Entity Types
Entity Relationships
Budget Allocations
Procurement Plans
Procurement Plan Items
Standard Items
Item Categories
Requests/Requisitions
Request Items
Approvals
Workflow Actions
Commitment Authorizations
Consolidations
Procurement Records
Orders
Deliveries
Consumption Records
Audit Logs
Notifications
Attachments
```

Do not create the final database schema until business rules and relationships have been reviewed.

---

# 16. REQUIREMENTS TRACEABILITY MATRIX

Maintain traceability in this form:

| ID     | Requirement                | Actor          | Data               | Workflow Stage | Status       | Acceptance Criteria                               |
| ------ | -------------------------- | -------------- | ------------------ | -------------- | ------------ | ------------------------------------------------- |
| FR-003 | Planning Entity Management | Administrator  | Entity master data | Setup          | Pending Data | Authorized admin can manage configurable entities |
| FR-007 | Procurement Plan Creation  | Planning User  | Plan + items       | Planning       | Confirmed    | User can create valid plan                        |
| FR-011 | Plan Approval              | Approver       | Plan               | Approval       | TBC          | Correct authority receives submission             |
| FR-017 | Item Request               | Requester      | Request + items    | Requisition    | Confirmed    | Valid request can be submitted                    |
| FR-028 | Commitment Authorization   | Budget Officer | Request + budget   | Budget         | TBC          | Authorized officer can decide                     |
| FR-030 | Consolidation              | Procurement    | Requests           | Consolidation  | Confirmed    | Matching requirements are consolidated            |
| FR-037 | Status Tracking            | Requester      | Workflow history   | Tracking       | Confirmed    | Requester sees current status                     |

Expand this matrix for the full requirements set.

---

# 17. ACCEPTANCE CRITERIA STYLE

Each functional requirement should eventually have testable acceptance criteria.

Example:

## FR-017 Item Request Creation

Given:

- The user is authenticated.
- The user belongs to an authorized planning entity.
- Required request information is available.

When:

- The user creates a request.

Then:

- PROMIS validates the request.
- Required fields must be completed.
- The request is associated with the correct planning entity.
- The request receives a unique reference.
- The request remains auditable.

---

# 18. OPEN INFORMATION REQUIRED FROM THE UNIVERSITY

Do not block all requirements work while waiting for these items. Track them as open inputs.

## Organizational Master Data

Required when available:

```text
Entity Name
Entity Code
Campus
Entity Type
Parent/Directorate
Head
Planning Officer
Approving Authority
```

Known quantity:
`Approximately 62 planning entities`

Status:
`PENDING DATA`

## User List

Need:

- Users
- Job responsibilities
- Entity assignments
- Approval responsibilities

Status: `PENDING DATA`

## Role List

Need confirmation of exact roles and permissions.
Status: `TO BE CONFIRMED`

## Approval Matrix

Need:

- Approval levels
- Thresholds
- Categories
- Approving authority
- Query rights
- Rejection rights
- Return rights

Status: `TO BE CONFIRMED`

## Budget Rules

Need confirmation of what Budget checks before commitment authorization.

Potential checks:

- Available balance
- Approved plan
- Previous commitments
- Budget line
- Amount
- Quantity

Status: `TO BE CONFIRMED`

## Item Catalogue

Need:

- Standard item list
- Categories
- Codes
- Units of measure
- Catalogue ownership

Status: `PENDING DATA`

## Consolidation Rules

Need:

- Matching logic
- Similar-item logic
- Consolidation period
- Campus treatment
- Category treatment

Status: `TO BE CONFIRMED`

## Request Exceptions

Need:

- Over-plan requests
- Partial requests
- Variations
- Emergency requests
- Returned requests
- Resubmission

Status: `TO BE CONFIRMED`

## Procurement Scope

Need confirmation of exactly how far PROMIS goes after Procurement receives the requirement.
Status: `TO BE CONFIRMED`

---

# 19. PHASING GUIDANCE

The original proposal takes a staged approach.

Initial implementation should focus on approved first-phase functions.

Potential later phases identified include:

- Contract and supplier records
- Stores and inventory
- Management reporting expansion
- Financial-system integration

Do not automatically implement later phases in the first release without explicit approval.

---

# 20. AI REQUIREMENTS ANALYSIS WORKFLOW

When asked to turn this document into implementation requirements:

1. Read the entire document.
2. Identify all `CONFIRMED` items.
3. Separate `PENDING DATA`, `TO BE CONFIRMED`, and `PROPOSED`.
4. Build a clean requirements catalogue.
5. Normalize requirement wording using `The system shall...`.
6. Assign stable IDs and never reuse an ID for another meaning.
7. Trace Business Need → Requirement → Actor → Workflow → Data → Acceptance Criterion → Test.
8. Identify contradictions or ambiguities without silently resolving them.
9. Create an open-questions register.
10. Produce an implementation-ready requirements document.

---

# 21. AI CODING AGENT HANDOFF RULES

The coding agent must:

1. Treat confirmed requirements as implementation constraints.
2. Treat `PENDING DATA` as configurable input.
3. Treat `TO BE CONFIRMED` as unresolved business decisions.
4. Never invent missing institutional rules.
5. Build configuration structures where master data is not yet available.
6. Never hard-code the 62 planning entities.
7. Never hard-code organizational hierarchy that has not been supplied.
8. Never hard-code approval authorities that have not been approved.
9. Never merge separate business concepts simply because they appear similar.
10. Preserve traceability from implementation back to requirement IDs.

---

# 22. AI WORKSPACE SKILL / WORKFLOW PROMPT

Use the following as the workspace workflow instruction:

```text
You are the PROMIS Requirements Engineering Agent.

Read and understand the PROMIS Functional & Non-Functional Requirements Master Workspace Specification.

Your job is to transform the specification into a structured, traceable, implementation-ready requirements system.

Before making recommendations or generating implementation plans:

1. Inspect the workspace.
2. Locate all existing PROMIS documentation.
3. Identify existing requirement files and avoid duplicating them.
4. Preserve confirmed requirements.
5. Separate pending organizational data from unresolved business rules.
6. Never invent University procedures.
7. Never invent users, roles, entities, approval authorities, thresholds, or consolidation rules.
8. Use stable requirement IDs.
9. Write formal requirements using clear "The system shall..." language.
10. Maintain a requirements traceability matrix.
11. Convert important requirements into acceptance criteria.
12. Maintain an open-questions register.
13. Identify contradictions and ambiguities explicitly.
14. Keep business requirements separate from technical implementation decisions.
15. Keep PROMIS and GHANEPS boundaries clear.
16. Treat the approximately 62 planning entities as configurable master data.
17. Do not wait for the 62 entity list to design the organizational data model, but mark the actual records as PENDING DATA.
18. Do not start full application development until the requirements baseline is sufficiently reviewed.

Use this logical structure:

Requirements
→ Actors/Roles
→ Organizational Model
→ Business Rules
→ Functional Requirements
→ Non-Functional Requirements
→ Workflows
→ Data Requirements
→ Security
→ UI/UX/HCI
→ Integrations
→ Reporting
→ Acceptance Criteria
→ Traceability
→ Open Questions
→ Assumptions
→ Release/Phase Scope

Always distinguish:

CONFIRMED
PENDING DATA
TO BE CONFIRMED
PROPOSED

Do not silently convert one status into another.

Final principle:

Understand the business process before specifying the software.
```

---

# 23. AI WORKSPACE FILE ARRANGEMENT

Once the requirements baseline is approved, the AI agent may organize it into:

```text
.requirements/
├── promis-requirements.md
├── actors-and-roles.md
├── organizational-model.md
├── functional-requirements.md
├── non-functional-requirements.md
├── business-rules.md
├── workflows.md
├── data-requirements.md
├── security-requirements.md
├── ui-ux-hci-requirements.md
├── reporting-requirements.md
├── integration-requirements.md
├── traceability-matrix.md
├── acceptance-criteria.md
└── open-questions.md
```

It may also create specialized `.agents/workflows/` files based on the approved requirements.

Do not create these files manually until the master requirements specification has been analyzed.

---

# 24. FINAL REQUIREMENTS PRINCIPLE

The purpose of this specification is not to make assumptions appear official.

The purpose is to create a reliable bridge between:

```text
University Business Process
        ↓
Approved Requirements
        ↓
System Design
        ↓
Database
        ↓
Application
        ↓
Testing
        ↓
Operational PROMIS
```

Where the University has not yet decided something, keep it visible as unresolved.

Where the University has provided a rule, preserve it accurately.

Where technical guidance is proposed, label it as technical guidance rather than institutional policy.

FINAL RULE:

**Do not code what has not been understood, and do not assume what the University has not approved.**
