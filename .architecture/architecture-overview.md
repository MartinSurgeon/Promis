# PROMIS Architecture Specification: Overview & Principles

## 1. Executive Summary

The **Procurement Management Information System (PROMIS)** for the **University of Science and Technology, Dedicated** (USTED) is engineered to replace fragmented, paper-based requisitioning with a structured, transparent, and auditable digital system.

This Architecture Specification defines **HOW** the system is structured to fulfill the requirements defined in the PROMIS Requirements Foundation ([.requirements/](file:///c:/xampp/htdocs/promis/.requirements/)).

### Core Engineering Axiom
> **Requirements define WHAT PROMIS must do.**  
> **Architecture defines HOW the system should be structured to support those requirements.**

> [!NOTE]
> **Foundational Distinctions**:
> 1. **CONFIRMED BUSINESS REQUIREMENT vs. PROPOSED ARCHITECTURAL DESIGN**: Requirements define confirmed institutional needs (such as annual procurement planning, quarterly reviews, requisition quantity tracking, and audit log protection). Architecture defines the proposed structural patterns (such as 5-tier layered design, modular boundaries, and conceptual data relationships) engineered to support those requirements.
> 2. **Architectural Decompositions vs. University Facts**: Any structural counts—such as 19 architectural modules, 24 conceptual domain entities, or 15 Architecture Decision Records (ADRs)—are **technical decompositions devised by the architecture team to structure the solution**. They are **not** University-provided facts or immutable mandates. If future requirements reviews or design refinements warrant adjustment, these architectural groupings may be adapted accordingly.
> 3. **Conceptual Data Entities**: Conceptual entities defined herein represent logical business concepts, **not final database tables**. Physical schema design, indexing, and normalization are reserved for the subsequent Physical Database Design phase.

---

## 2. Guiding Architectural Principles

1. **Understand Before Changing**: All architecture decisions are grounded directly in the confirmed requirements, statutory frameworks, and operational context of USTED.
2. **Pragmatic Simplicity & Zero Framework Bloat**: Rather than introducing heavy, complex third-party frameworks (e.g. Laravel, Symfony, React, Angular, Vue), PROMIS relies on clean, modular **Core PHP 8.x+**, **MySQL 8.x**, **PDO**, **Tailwind CSS**, and **Vanilla JavaScript ES6+**.
3. **Strict Separation of Concerns**: Clean boundaries between Presentation, Controllers, Business Services, Repositories/Data Access, and Shared Helpers.
4. **Configurability Over Hardcoding**: Missing institutional rules (e.g. the roster of ~62 planning entities, approval hierarchies, and budget decision logic) are designed as configurable structures rather than hardcoded assumptions.
5. **Defense-in-Depth Security**: Strict server-side authorization and role/permission enforcement on every state-changing action, zero client trust, robust CSRF mitigation, and protected audit logs.
6. **Preservation of Institutional Provenance**: Aggregation and demand consolidation must never destroy source entity, campus, requester, or requisition reference data.
7. **Strict Phase 1 Discipline**: Architecture strictly isolates confirmed Phase 1 core functionality from deferred possibilities (supplier bidding portals, warehouse inventory, ERP ledger sync).

---

## 3. Technology Stack Architecture

| Layer | Approved Technology | Architectural Role | Status |
| :--- | :--- | :--- | :--- |
| **Backend Runtime** | PHP 8.1+ (Core PHP) | Request handling, business domain services, session security | `CONFIRMED` |
| **Data Access** | PDO (PHP Data Objects) | Parameterized prepared statements, transaction boundaries | `CONFIRMED` |
| **Database** | MySQL 8.x / MariaDB | Relational storage, referential integrity (Conceptual Model only) | `CONFIRMED` |
| **Presentation / Markup** | Semantic HTML5 | Structured, accessible, semantic document layouts | `CONFIRMED` |
| **Styling** | Tailwind CSS | Mobile-first utility design system, zero inline CSS | `CONFIRMED` |
| **Client Scripting** | Vanilla JavaScript ES6+ | Lightweight DOM events, Fetch API interactions, no frameworks | `CONFIRMED` |
| **Icons & Typography** | Font Awesome & Inter | Multi-modal status communication, clear typographic hierarchy | `CONFIRMED` |

---

## 4. Architecture Specification Directory

The complete architecture specification is organized into 19 companion documents:

| Specification Document | Architectural Scope |
| :--- | :--- |
| [system-context.md](file:///c:/xampp/htdocs/promis/.architecture/system-context.md) | System boundaries, internal university domain, strict GHANEPS boundary |
| [module-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/module-architecture.md) | 19 functional and administrative modules, layer mapping, Phase 1 mapping |
| [actor-role-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/actor-role-architecture.md) | Configurable RBAC, decoupling of users, roles, entities, and authorities |
| [organizational-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/organizational-architecture.md) | Configurable ~62 planning entities, multi-campus topology, hierarchies |
| [workflow-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/workflow-architecture.md) | Configurable workflow/routing architecture, state machine transitions |
| [plan-versioning-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/plan-versioning-architecture.md) | Annual plans, quarterly reviews (No change vs. Revision), version preservation |
| [requisition-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/requisition-architecture.md) | Requisitions, balance calculation model, FR-051 plan version association |
| [consolidation-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/consolidation-architecture.md) | Dual consolidation views (totals + breakdowns), non-erasure of provenance |
| [security-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/security-architecture.md) | Server-side authorization and role/permission enforcement, CSRF, uploads |
| [audit-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/audit-architecture.md) | Audit trail recording, protection from unauthorized modification or deletion |
| [reporting-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/reporting-architecture.md) | Confirmed reporting dimensions, search engine, role-scoped data exports |
| [integration-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/integration-architecture.md) | GHANEPS handover packages, budget allocation & commitment touchpoints |
| [application-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/application-architecture.md) | 5-tier modular Core PHP structure, repository pattern, transaction safety |
| [api-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/api-architecture.md) | Endpoint responsibilities, JSON envelopes, proposed RESTful conventions |
| [conceptual-data-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/conceptual-data-architecture.md) | Conceptual domain model (24+ entities), relationships, no physical DDL |
| [ui-ux-architecture.md](file:///c:/xampp/htdocs/promis/.architecture/ui-ux-architecture.md) | Orientation triad, applied HCI laws, page patterns, 5 mandatory UI states |
| [phase-1-scope.md](file:///c:/xampp/htdocs/promis/.architecture/phase-1-scope.md) | Strict boundary classification: Confirmed Phase 1, Later Phases, TBC |
| [architecture-decisions.md](file:///c:/xampp/htdocs/promis/.architecture/architecture-decisions.md) | Architecture Decision Register (ADR) detailing key architectural trade-offs |
| [architecture-open-questions.md](file:///c:/xampp/htdocs/promis/.architecture/architecture-open-questions.md) | Open architectural questions, dependencies on pending University data |
