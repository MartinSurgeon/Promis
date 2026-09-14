# PROMIS Architecture Decision Records (ADRs)

## 1. Overview of Architecture Decisions
This document records the foundational Architecture Decision Records (ADRs) governing the technical structure, design patterns, and operational boundaries of PROMIS.

---

## 2. Decision Log Index

| ADR ID | Decision Title | Status | Primary Rationale |
| :--- | :--- | :--- | :--- |
| **ADR-001** | 5-Tier Layered Architecture in Core PHP 8.x | Accepted | Clear separation of concerns, testability, high maintainability |
| **ADR-002** | Server-Side Authorization & Entity-Scoped RBAC | Accepted | Enforces data confidentiality across ~62 entities without relying on client UI |
| **ADR-003** | Configurable Master Data for ~62 Planning Entities | Accepted | Eliminates hard-coded organizational structures, accommodating University changes |
| **ADR-004** | Conceptual Requisition Quantity Balance Model | Accepted | Transparent, predictable balance calculation preventing over-requisitioning |
| **ADR-005** | Configurable Workflow & Routing Architecture | Accepted | Fits University approval hierarchy without over-engineered workflow engines |
| **ADR-006** | Plan Version Preservation via Quarterly Review Cycle | Accepted | Preserves historical auditability; prevents silent overwriting of approved plans |
| **ADR-007** | Plan Version Association on Requisitions (FR-051) | Accepted (Proposed) | Maintains historical linkage to the approved plan version under which submitted |
| **ADR-008** | Dual Consolidation Architecture (Entity & Category) | Accepted | Satisfies institutional management analysis and GHANEPS lot packaging needs |
| **ADR-009** | GHANEPS Information Handover (Proposed Approach) | Accepted (Proposed) | PROMIS provides procurement information; technical export format TBC |
| **ADR-010** | Finance Boundary via Budget & Commitment Touchpoints | Accepted | Models financial controls without premature two-way general ledger coupling |
| **ADR-011** | Protected Audit Logging Architecture | Accepted | Protects critical financial and procedural records from unauthorized modification/deletion |
| **ADR-012** | Document Storage & UUID Storage Approach | Accepted (Proposed) | Proposed component and approach for secure file handling outside web root |
| **ADR-013** | RESTful Conventions as Proposed Convention | Accepted (Proposed) | Provides consistent JSON envelope structure for internal dynamic UI interactions |
| **ADR-014** | WCAG 2.1 AA Accessibility Standard | Accepted (Proposed) | Ensures inclusive usability across academic and administrative staff |
| **ADR-015** | Strict Separation of Architecture from Physical Design | Accepted | Preserves conceptual clarity before committing to physical SQL schemas or application code |

---

## 3. Detailed Architecture Decision Records

### ADR-001: 5-Tier Layered Architecture in Core PHP 8.x
- **Context**: PROMIS requires a robust, long-term maintainable architecture that does not suffer from framework bloat, abrupt breaking framework upgrades, or vendor lock-in.
- **Decision**: Adopt a strict 5-tier architecture: Presentation -> Controller -> Service -> Repository -> Database. Enforce strict typing (`declare(strict_types=1);`), native PDO with prepared statements, and transaction boundaries exclusively in the Service Layer.
- **Consequences**: Clean separation of concerns; Repositories isolate SQL; Controllers remain thin; Services encapsulate all business logic; high unit-testability.

### ADR-002: Server-Side Authorization & Entity-Scoped RBAC
- **Context**: PROMIS handles sensitive university procurement and budget allocations across ~62 autonomous planning entities.
- **Decision**: Enforce server-side authorization and role/permission enforcement on every request. Scoping is evaluated against the authenticated user's assigned entity. UI button hiding is treated strictly as an aesthetic convenience, never a security control.
- **Consequences**: Completely mitigates IDOR (Insecure Direct Object Reference) vulnerabilities and cross-entity data leakage.

### ADR-003: Configurable Master Data for Planning Entities
- **Context**: The University comprises approximately 62 planning entities whose names, hierarchies, deans, heads, and approving authorities may evolve over time.
- **Decision**: Treat all planning entities, types, hierarchies, and officer assignments as dynamic, configurable master data. Zero hard-coding of entity names or structures in application code.
- **Consequences**: University administrative reorganizations can be accommodated via configuration without requiring code deployments.

### ADR-004: Conceptual Requisition Quantity Balance Model
- **Context**: Requisitions must be checked against approved annual procurement plans without over-constraining the business logic beyond confirmed rules.
- **Decision**: Adopt the conceptual balance tracking model:
  - `Approved Planned Quantity`
  - `Previously Requested Quantity`
  - `Current Request Quantity`
  - `Remaining Before Current Request` = `Approved Planned Quantity` - `Previously Requested Quantity`
  - If approved: `Remaining After Current Request` = `Remaining Before Current Request` - `Current Request Quantity`
- **Consequences**: Transparent, auditable allocation consumption displayed clearly in UI and verified in the Service Layer without hard-coding rigid unapproved formulas.

### ADR-005: Configurable Workflow & Routing Architecture
- **Context**: Institutional documents require approval routing through departmental, directorate, and executive authorities.
- **Decision**: Implement a "configurable workflow/routing architecture" rather than importing or building a complex, generic BPMN/workflow engine. Use explicit state transitions and configurable routing steps.
- **Consequences**: Lightweight, transparent, highly performant code tailored exactly to University procurement governance.

### ADR-006: Plan Version Preservation via Quarterly Review Cycle
- **Context**: Procurement plans undergo periodic quarterly reviews (FR-049, FR-050) which may result in either `No Change` or `Revision Required`.
- **Decision**: 
  - **Business Requirement**: Historical approved procurement-plan versions shall be preserved and shall not be overwritten.
  - **Architectural Implementation**: Version history shall be preserved through a dedicated versioning structure; the exact physical database implementation will be determined during the Physical Database Design phase. Do not assume database immutability until the physical design and security model explicitly establish how historical records are protected.
- **Consequences**: Preserves full institutional auditability and historical accountability for state auditors without premature physical immutability assumptions.

### ADR-007: Plan Version Association on Requisitions (FR-051)
- **Context**: When a plan is revised, past requisitions submitted against earlier plan versions must maintain their original context.
- **Decision**: Adopt FR-051 (Status: `PROPOSED / TO BE CONFIRMED`): Each plan-linked requisition shall retain a historical reference to the approved procurement-plan version under which it was submitted, and that historical association shall not be silently changed.
- **Consequences**: Eliminates retroactive distortion of requisition history when subsequent plan versions are published.

### ADR-008: Dual Consolidation Architecture (Entity & Category)
- **Context**: The Procurement Directorate requires institutional visibility across both planning entities and procurement categories.
- **Decision**: Architect the consolidation engine with dual perspectives: (1) By-Entity consolidation for budget monitoring, and (2) By-Category consolidation for bulk packaging and tender scheduling.
- **Consequences**: Enables bulk purchasing power, optimal tender packaging, and comprehensive executive reporting.

### ADR-009: GHANEPS Boundary via Information Handover (Proposed Integration Approach)
- **Context**: National law requires submission of procurement plans to the Public Procurement Authority via GHANEPS.
- **Decision**: PROMIS shall provide the required procurement information for the applicable GHANEPS process. The technical handover/export format remains `TO BE CONFIRMED`. Structured file export packages (such as CSV, Excel, or JSON for manual upload by the Procurement Directorate) are classified strictly as a `PROPOSED INTEGRATION APPROACH`. Defer direct machine-to-machine API sync to later phases.
- **Consequences**: Satisfies statutory information handover needs without making premature technical assumptions about file formats or unconfirmed PPA APIs.

### ADR-010: Finance Boundary via Budget & Commitment Touchpoints
- **Context**: Procurement activities must align with available university funds.
- **Decision**: Classify financial integration as "Budget allocation and commitment authorization touchpoints" with the exact technical mechanism marked as `TO BE CONFIRMED`. Model human authorization checkpoints within PROMIS rather than building speculative general ledger connectors.
- **Consequences**: Prevents premature architectural commitments while guaranteeing proper financial governance checkpoints.

### ADR-011: Protected Audit Logging Architecture
- **Context**: Public procurement is subject to rigorous statutory audit and potential legal scrutiny.
- **Decision**: Architect an audit subsystem where audit records are protected from unauthorized modification or deletion. Log all security, state transition, and balance calculation events with timestamps, user IDs, and pre/post snapshots.
- **Consequences**: Non-repudiation and complete forensic auditability for University Internal Audit and external auditors.

### ADR-012: Document Storage & UUID Storage Approach (Proposed Component & Approach)
- **Context**: Requisitions and plans require supporting document attachments.
- **Decision**: Classify Document Storage as a `PROPOSED ARCHITECTURAL COMPONENT`. Storage of uploaded files on the filesystem outside the public web root using unique UUID identifiers is classified as a `PROPOSED IMPLEMENTATION APPROACH`. Original filenames and MIME types are stored in database metadata.
- **Consequences**: Complete protection against path traversal, filename collisions, and malicious script execution without assuming dedicated storage services as confirmed business mandates.

### ADR-013: RESTful Conventions as Proposed Architectural Convention
- **Context**: Dynamic browser components require clean, predictable communication with server endpoints.
- **Decision**: Classify RESTful API conventions as a `PROPOSED ARCHITECTURAL CONVENTION` for internal AJAX/Fetch interactions, utilizing a standardized JSON response envelope.
- **Consequences**: Clean front-end integration without misrepresenting REST as a client-mandated business requirement.

### ADR-014: WCAG 2.1 AA Accessibility Standard
- **Context**: University systems must be usable by all faculty and administrative personnel.
- **Decision**: Adopt WCAG 2.1 Level AA as a `PROPOSED TECHNICAL STANDARD` guiding contrast, keyboard navigation, and responsive scaling.
- **Consequences**: High usability, low cognitive load, and inclusive access across desktop and mobile devices.

### ADR-015: Strict Separation of Architecture from Physical Design
- **Context**: Moving prematurely to SQL or code before architecture approval introduces architectural debt and rework.
- **Decision**: Keep the architecture phase strictly focused on conceptual domain models, system boundaries, and structural patterns. Zero SQL, DDL, or application code generated in this phase.
- **Consequences**: Ensures thorough stakeholder validation of system structure before physical schema and code implementation.
