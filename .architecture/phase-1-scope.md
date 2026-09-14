# PROMIS Phase 1 Scope & Boundary Discipline Specification

## 1. Scope Governance & Architectural Discipline
To ensure successful delivery and avoid enterprise scope creep, PROMIS enforces a strict boundary between features implemented in **Phase 1** and capabilities reserved for future phases.

Every system module, feature, and architectural touchpoint is classified under one of three formal statuses:
1. `CONFIRMED PHASE 1`: Fully approved for architectural specification and Phase 1 implementation.
2. `PROPOSED LATER PHASE`: Deliberately deferred to subsequent project phases (e.g., supplier portal, inventory, live ERP sync).
3. `TO BE CONFIRMED`: Business requirement or technical mechanism under active institutional review.

---

## 2. Comprehensive Module & Feature Classification Matrix

### 2.1 Planning & Revision Module
| Capability / Feature | Scope Classification | Architectural Rationale & Boundary |
| :--- | :--- | :--- |
| **Annual Entity Plan Creation** | `CONFIRMED PHASE 1` | Core foundation: ~62 entities input itemized procurement plans. |
| **Plan Line Item Categorization** | `CONFIRMED PHASE 1` | Goods, Works, Technical Services, Consulting; quarterly schedules. |
| **Plan Review & Approval Routing**| `CONFIRMED PHASE 1` | Configurable routing from Entity Planning Officer to Final Approval. |
| **Quarterly Plan Review (FR-049)** | `CONFIRMED PHASE 1` | Periodic review support confirmed; detailed review procedure `TO BE CONFIRMED`. |
| **Plan Versioning Engine (FR-050)**| `CONFIRMED PHASE 1` | Version creation upon approved revision; versioning details `TO BE CONFIRMED`. |
| **Historical Version Preservation** | `CONFIRMED PHASE 1` | Historical approved procurement-plan versions shall be preserved and shall not be overwritten; version history preserved through dedicated versioning structure. |
| **Requisition Version Link (FR-051)**| `PROPOSED / TO BE CONFIRMED` | Requisitions retain historical reference to approved plan version under which submitted. |

### 2.2 Requisition & Balance Tracking Module
| Capability / Feature | Scope Classification | Architectural Rationale & Boundary |
| :--- | :--- | :--- |
| **Plan-Linked Requisitions** | `CONFIRMED PHASE 1` | Operational requests drawn directly against approved plan items. |
| **Requisition Balance Calculation**| `CONFIRMED PHASE 1` | Conceptual model: Approved Planned - Previously Requested = Remaining Before; Remaining Before - Current = Remaining After. |
| **Configurable Requisition Routing**| `CONFIRMED PHASE 1` | Multi-stage routing through Head of Entity, Directorate, and Finance. |
| **Supporting Document Upload** | `CONFIRMED PHASE 1` | Specifications and justification memos stored via UUID approach. |
| **Emergency / Unplanned Requisitions**| `TO BE CONFIRMED` | Exceptional workflow for non-budgeted items subject to University policy confirmation. |

### 2.3 Institutional Consolidation & GHANEPS Module
| Capability / Feature | Scope Classification | Architectural Rationale & Boundary |
| :--- | :--- | :--- |
| **Institutional Plan Consolidation**| `CONFIRMED PHASE 1` | Procurement Directorate aggregates plans across all ~62 planning entities. |
| **Dual Consolidation Views** | `CONFIRMED PHASE 1` | By-Entity perspective and By-Category perspective. |
| **GHANEPS Information Handover** | `CONFIRMED PHASE 1 (Info) / TBC (Format)` | PROMIS shall provide the required procurement information for the applicable GHANEPS process. The technical handover/export format remains TO BE CONFIRMED (PROPOSED INTEGRATION APPROACH). |
| **Direct GHANEPS Live API Sync** | `PROPOSED LATER PHASE` | Direct machine-to-machine web services deferred pending PPA API availability. |

### 2.4 Financial & Budget Integration
| Capability / Feature | Scope Classification | Architectural Rationale & Boundary |
| :--- | :--- | :--- |
| **Budget Code & Source Tagging** | `CONFIRMED PHASE 1` | Plan items tagged with funding sources and institutional budget codes. |
| **Commitment Authorization Touchpoint**| `CONFIRMED PHASE 1` | Finance Directorate human sign-off checkpoint within workflow. |
| **Automated General Ledger Sync** | `PROPOSED LATER PHASE` | Live automated balance debiting/reservation on external accounting software deferred. |
| **Financial System Integration Mechanism**| `TO BE CONFIRMED` | Exact technical integration protocol with University finance software remains TBC. |

### 2.5 User Identity & Access Management
| Capability / Feature | Scope Classification | Architectural Rationale & Boundary |
| :--- | :--- | :--- |
| **Native User & Credential Store**| `CONFIRMED PHASE 1` | Secure password hashing (Argon2id/bcrypt), user status management. |
| **Role-Based Entity Scoping** | `CONFIRMED PHASE 1` | Users assigned roles scoped strictly to their respective planning entity. |
| **Server-Side Authorization** | `CONFIRMED PHASE 1` | Server-side authorization and role/permission enforcement across all endpoints. |
| **Single Sign-On (SSO / LDAP / AD)**| `PROPOSED LATER PHASE` | Central University directory integration deferred to subsequent phase. |
| **Two-Factor Authentication (2FA)** | `PROPOSED LATER PHASE` | 2FA is not approved for Phase 1; retained as a potential future enhancement. |

### 2.6 Audit, Security & Administration
| Capability / Feature | Scope Classification | Architectural Rationale & Boundary |
| :--- | :--- | :--- |
| **Protected Institutional Audit Log**| `CONFIRMED PHASE 1` | Audit records protected from unauthorized modification or deletion. |
| **Configurable Master Data Management**| `CONFIRMED PHASE 1` | CRUD for ~62 entities, entity types, categories, and workflow steps. |
| **Document Storage Service** | `PROPOSED ARCHITECTURAL COMPONENT` | UUID file storage outside web root treated as a PROPOSED IMPLEMENTATION APPROACH. |
| **WCAG 2.1 AA Compliance** | `CONFIRMED PHASE 1 (Proposed)`| Proposed technical standard for interface accessibility. |

### 2.7 Procurement Operations (Post-Requisition)
| Capability / Feature | Scope Classification | Architectural Rationale & Boundary |
| :--- | :--- | :--- |
| **Vendor / Supplier Portal** | `PROPOSED LATER PHASE` | External vendor registration, RFQ responses, and bidding deferred. |
| **Tender Evaluation & Awarding** | `PROPOSED LATER PHASE` | Detailed committee scoring and contract award workflows deferred. |
| **Contract Lifecycle Management** | `PROPOSED LATER PHASE` | Milestone tracking, performance bonds, and contract amendments deferred. |
| **Goods Receipt & Inventory/Stores**| `PROPOSED LATER PHASE` | Physical warehouse management, GRN creation, and store issuance deferred. |

---

## 3. Scope Risk Mitigation Strategy
1. **Zero Scope Leakage in Code**: No code, database tables, or controller actions shall be implemented for `PROPOSED LATER PHASE` features during Phase 1 development.
2. **Interface Decoupling**: Where deferred features touch Phase 1 modules (e.g., Finance, SSO), clean architectural interfaces/touchpoints are established so future expansion does not break Phase 1 core logic.
3. **Formal Change Control**: Any addition to `CONFIRMED PHASE 1` requires explicit University approval and formal requirements baselining.
