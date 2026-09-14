# PROMIS Architecture Open Questions & Technical Dependencies

## 1. Overview & Purpose
This document catalogs the open architectural dependencies, policy confirmations, and institutional parameters required from University stakeholders. While the conceptual architecture accommodates these variations through configurable master data and modular boundaries, resolving these questions will guide the upcoming **Physical Database Design** and **Application Implementation** phases.

---

## 2. Open Questions by Architectural Domain

### 2.1 Procurement Plan Quarterly Review & Versioning (14 Specific Questions)
1. **Review Initiation Trigger**: Is the quarterly review cycle initiated automatically by the system based on academic calendar dates, or manually triggered by the Procurement Directorate?
2. **Eligibility Criteria for Revision**: What specific threshold or conditions mandate a formal plan revision versus recording "No Change"?
3. **Revision Workflow Authorities**: Does a quarterly plan revision require the exact same hierarchical approval path as the original annual plan, or an expedited / delegated path?
4. **In-Flight Requisitions Handling**: When a plan version enters "Under Review" or "Revision Required", can departments continue submitting requisitions against uncontested plan items, or is submission temporarily locked?
5. **Major vs. Minor Revision Distinction**: Does the University distinguish between minor administrative adjustments (e.g., quarter schedule shift) and major revisions (e.g., adding new line items, budget increases)?
6. **Requisition Balance Carryover Rules**: When a revised plan version (`v2.0`) is approved with altered quantities, how do existing previously requested quantities map to the revised line items?
7. **Version Numbering Convention**: What is the preferred version naming convention (e.g., `v1.0`, `v1.1`, `v2.0` vs. `2026-Original`, `2026-Q1-Revised`)?
8. **Archival & Retention Duration**: What is the mandatory archival retention duration for historical approved plan versions under University statutes?
9. **Audit Trail Retention Period**: How long must protected audit logs of quarterly reviews and workflow actions be retained online?
10. **Mid-Year Budget Reduction Handling**: If university-wide subventions or internally generated funds (IGF) are reduced mid-year, what is the formal procedure for de-allocating plan balances?
11. **Revision Publication Notification**: Which institutional stakeholders receive automated notifications when a revised plan is published?
12. **Approving Authority Ceilings for Revision**: Are revision approvals tied to financial thresholds or to the original approving officer?
13. **Consolidation Update Trigger**: When an entity's plan revision is approved, does it automatically trigger a recalculation of the institutional consolidation batch, or is it compiled on-demand?
14. **GHANEPS Re-Export Policy**: Does every approved entity revision require a revised GHANEPS export package, or are GHANEPS updates bundled quarterly?

### 2.2 Organizational & Planning Entities Master Data
15. **Official Entity Register**: What is the authoritative, verified list of ~62 planning entities, their official codes, parent-child hierarchies, and active heads from the University Registrar?
16. **Acting & Delegated Appointments**: How are temporary acting heads, sabbatical leaves, and delegated financial approving authorities formally recorded and managed in the system?
17. **Shared Planning Units**: Are there planning entities that share common cost centers or pooled procurement budgets?

### 2.3 Approval Thresholds & Financial Ceilings
18. **Statutory & Institutional Ceilings**: What are the exact monetary approval thresholds for Heads of Department, Deans, Directorate Officers, Pro-Vice Chancellors, and the Vice-Chancellor under University Financial Regulations and the Public Procurement Act (Act 663 as amended by Act 914)?
19. **Entity Tender Committee (ETC) Routing**: At what financial ceiling must an aggregated procurement package or large requisition be routed to the Entity Tender Committee (ETC)?
20. **Emergency / Off-Plan Requisitions**: What is the University's confirmed policy regarding emergency or off-plan requisitions? What extraordinary justification and executive sign-offs are required?

### 2.4 Financial & External Integrations
21. **University Accounting System**: What specific financial/ERP system is utilized by the University Finance Directorate (e.g., ITS, Sage, SAP, bespoke), and what data exchange formats are supported for future integration?
22. **Commitment Touchpoint Procedure**: What documentation or ledger reference code must Finance officers input into PROMIS when recording a commitment authorization decision?
23. **GHANEPS Portal Template Specification**: Has the Procurement Directorate confirmed the exact file format and column schema expected by their specific GHANEPS portal upload profile?

### 2.5 Security, Infrastructure & Infrastructure Policy
24. **Identity & Authentication Strategy**: Is there an immediate institutional roadmap for University-wide Single Sign-On (SSO / LDAP / Microsoft 365 Entra ID), or will native PROMIS credentials suffice through Phase 1?
25. **Attachment Size & File Type Limits**: What are the approved maximum file upload limits (e.g., 10MB per PDF/document) and permitted MIME types for supporting attachments?

---

## 3. Impact Assessment on Physical Database Design Phase

| Question Cluster | Impact on Physical Database Design | Architectural Mitigation in Place |
| :--- | :--- | :--- |
| **Quarterly Review & Versioning** | High (Requires version tables, review cycle tables, historical logical references) | Addressed conceptually in `conceptual-data-architecture.md` (FR-050, FR-051) |
| **~62 Planning Entities Register** | Low (Dynamic master data design accommodates any list of entities) | `PlanningEntity` designed as master data; zero hard-coded rows |
| **Approval Thresholds & Ceilings** | Medium (Configurable workflow step rules and condition columns) | `WorkflowStepRule` includes conditional threshold fields |
| **Finance Touchpoints** | Low (Textual budget code and reference fields in Phase 1) | Touchpoint fields isolated without external database links |
| **Attachment Size & Types** | Low (Metadata storage only; file content stored on filesystem) | UUID file storage architecture accommodates any file types |

---

## 4. Conclusion & Recommendation
None of these open operational questions block the completion of the System Architecture specification. The architecture is deliberately designed to be **configurable**, **modular**, and **policy-agnostic**, allowing University administrators to define specific rules, thresholds, and organizational hierarchies via administrative master data without altering system architecture.
