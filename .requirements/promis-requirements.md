# PROMIS Requirements Specification: Master Overview

## 1. System Purpose & Context

**PROMIS** stands for **Procurement Management Information System**, developed for the **University of Science and Technology, Dedicated** (USTED).

PROMIS is designed to electronically capture procurement requirements at the point of origin, replacing manual, paper-based requisition processes. The system provides internal support for:
- Procurement planning and quarterly reviews
- Departmental requisitioning against approved plans
- Institutional demand consolidation
- Multi-tiered administrative approvals
- Budget commitment authorization
- Status tracking and delivery/consumption records
- Management reporting and auditability

### The GHANEPS Boundary
PROMIS operates strictly within the internal university domain. It does **not** replace or compete with the Ghana Electronic Procurement System (**GHANEPS**). GHANEPS remains the national, supplier-facing platform for public tendering, advertisement, supplier bid submission, and contract awards. PROMIS feeds well-specified and consolidated requirements downstream into procurement processing.

---

## 2. Primary Business Problems Addressed

Prior to PROMIS, procurement management across the University relied heavily on paper requisitions distributed across disparate faculties, directorates, and campuses. This fragmentation caused:
1. **Lack of Institutional Visibility**: Inability to determine university-wide aggregate demand for common items.
2. **Commitment Uncertainty**: Lack of real-time visibility into committed vs. uncommitted departmental budgets.
3. **Disjointed Consumption History**: Absence of reliable historical records to inform future planning.
4. **Opaque Request Tracking**: Inability for requesting units to track the status of their requisitions.
5. **Inefficient Sourcing**: Inability to consolidate procurement across units and satellite campuses for bulk economies of scale.
6. **Uncontrolled Item Descriptions**: Unstructured, non-standardized item descriptions impeding aggregation.

---

## 3. Core Objectives

PROMIS is engineered to achieve 14 primary institutional objectives:
1. **Replace paper requisitions** with structured electronic workflows.
2. **Enforce a standard item catalogue** to prevent uncontrolled descriptions.
3. **Consolidate requirements** across planning entities and campuses while preserving provenance.
4. **Provide real-time visibility** of approved procurement plans.
5. **Track operational requests** against approved planned quantities.
6. **Support electronic approvals** aligned with university statutory hierarchy.
7. **Record all workflow decisions** (approval, query, return, rejection) with audit timestamps.
8. **Empower requesters** with self-service status tracking.
9. **Deliver procurement views** with institutional totals and departmental breakdowns.
10. **Incorporate Budget commitment authorization** prior to procurement processing.
11. **Maintain retrievable, searchable procurement records**.
12. **Build a continuous consumption history** across entities and campuses.
13. **Generate management reports** by item, category, entity, campus, and financial period.
14. **Establish a digital foundation** for future supply chain automation.

---

## 4. Requirement Classification Taxonomy

To ensure complete transparency and prevent unapproved assumptions from becoming de facto policy, every requirement in this specification is tagged with one of four formal statuses:

- **`CONFIRMED` / `CONFIRMED BY CLIENT`**: Supported by the official PROMIS proposal or directly confirmed in writing by university stakeholders.
- **`PENDING DATA`**: A confirmed architectural requirement whose authoritative institutional master records (e.g. the exact list of 62 planning entities) have not yet been provided by the University.
- **`TO BE CONFIRMED` (TBC)**: An identified operational workflow, threshold, or decision rule where institutional policy has not yet been finalized.
- **`PROPOSED` / `PROPOSED TECHNICAL STANDARD` / `PROPOSED IMPLEMENTATION APPROACH`**: Recommended engineering or UX best practices (e.g. WCAG AA standards, UUID storage) requiring explicit university sign-off before becoming institutional rules.

---

## 5. Scope & Phasing Boundary

As established in the PROMIS project proposal, development proceeds in deliberate stages:

### Phase 1 (Current Core Scope)
- Configurable Organizational Hierarchy (~62 planning entities)
- User Authentication & Role-Based Access Control
- Standard Item Catalogue Management
- Budget Allocation capture
- Annual Procurement Planning & Approval
- **Quarterly Procurement Plan Review & Revision** (FR-049, FR-050) and **Plan Version Association** (FR-051)
- Item Requisitioning & Plan Quantity Tracking
- Multi-tier Approval Workflows (Approve, Query/Return, Reject)
- Budget Commitment Authorization
- Institutional Demand Consolidation (Totals + Breakdowns)
- Handover to Directorate of Procurement
- Status Tracking & Protected Audit Trails
- Delivery & Consumption Recording
- Core Reporting & Action-Oriented Dashboards

### Deferred Phases (Subject to Later Approval)
- Supplier portal & tendering (handled externally via GHANEPS)
- Central stores inventory and warehouse stock management
- Automated bidirectional ERP/Financial General Ledger integration
- Automated supplier contract management

---

## 6. Requirements Specification Directory

This master document is supported by 15 domain-specific requirement specifications:

| Document | Focus & Content |
| :--- | :--- |
| [actors-and-roles.md](file:///c:/xampp/htdocs/promis/.requirements/actors-and-roles.md) | Actor definitions, decoupling of entities/users/roles, permission structures |
| [organizational-model.md](file:///c:/xampp/htdocs/promis/.requirements/organizational-model.md) | Configurable entity hierarchy supporting ~62 planning entities |
| [functional-requirements.md](file:///c:/xampp/htdocs/promis/.requirements/functional-requirements.md) | Complete catalogue of FR-001 through FR-051 |
| [non-functional-requirements.md](file:///c:/xampp/htdocs/promis/.requirements/non-functional-requirements.md) | Technical qualities, NFR-001 through NFR-025 |
| [business-rules.md](file:///c:/xampp/htdocs/promis/.requirements/business-rules.md) | Core business logic, consolidation rules, versioning, quantity controls |
| [workflows.md](file:///c:/xampp/htdocs/promis/.requirements/workflows.md) | Lifecycle diagrams, quarterly review workflow, requisition state machines |
| [data-requirements.md](file:///c:/xampp/htdocs/promis/.requirements/data-requirements.md) | Conceptual domain model covering all 24+ data areas |
| [security-requirements.md](file:///c:/xampp/htdocs/promis/.requirements/security-requirements.md) | Auth, server authorization, CSRF, XSS, protected audit trails |
| [ui-ux-hci-requirements.md](file:///c:/xampp/htdocs/promis/.requirements/ui-ux-hci-requirements.md) | HCI laws, orientation triad, page patterns, forms, proposed accessibility |
| [reporting-requirements.md](file:///c:/xampp/htdocs/promis/.requirements/reporting-requirements.md) | Consumption, throughput, bottlenecks, consolidated totals, exports |
| [integration-requirements.md](file:///c:/xampp/htdocs/promis/.requirements/integration-requirements.md) | GHANEPS boundary, Finance budget touchpoints, future interfaces |
| [traceability-matrix.md](file:///c:/xampp/htdocs/promis/.requirements/traceability-matrix.md) | Full matrix mapping requirements from business need to test levels |
| [acceptance-criteria.md](file:///c:/xampp/htdocs/promis/.requirements/acceptance-criteria.md) | Testable Given-When-Then criteria across key functional areas |
| [open-questions.md](file:///c:/xampp/htdocs/promis/.requirements/open-questions.md) | Unresolved university decisions, including the 14 quarterly review questions |
| [assumptions.md](file:///c:/xampp/htdocs/promis/.requirements/assumptions.md) | Technical and operational baseline assumptions and risk analyses |
