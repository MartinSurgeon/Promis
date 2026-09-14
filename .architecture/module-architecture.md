# PROMIS Architecture Specification: Module Architecture

## 1. Modular Decomposition Overview

> [!NOTE]
> **Architectural Nature of Module Count**: The decomposition of PROMIS into 19 functional and administrative modules represents an **architectural structural design created from the requirements**, NOT a University-provided fact or mandatory final structure. These modules are not fixed as immutable application packages; if later requirements or implementation reviews warrant consolidation or refinement, the modular decomposition may adapt accordingly.
>
> **Distinction of Concepts**:
> - **CONFIRMED BUSINESS REQUIREMENT**: University procurement planning, quarterly reviews, entity requisitions, institutional consolidation, and audit protection.
> - **PROPOSED ARCHITECTURAL DESIGN**: The specific partitioning into these 19 discrete modules, service boundaries, and internal interfaces.

PROMIS is partitioned into 19 functional and administrative modules. Each module maintains high cohesion within its domain and loose coupling across module boundaries, communicating through defined business services and data access layers.

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                             PRESENTATION LAYER                              │
│              PHP Views, Semantic HTML5, Tailwind CSS, Vanilla JS            │
├─────────────────────────────────────────────────────────────────────────────┤
│                          BUSINESS SERVICE MODULES                           │
│                                                                             │
│  [Auth & Access]       [Org Management]        [Item Catalogue]             │
│  - Authentication      - ~62 Entities Master   - Standard Taxonomy          │
│  - Configurable RBAC   - Multi-Campus Models   - Unit of Measure            │
│                                                                             │
│  [Planning Domain]     [Requisition Domain]    [Workflow & Control]         │
│  - Budget Allocation   - Item Requisitions     - Approval Routing           │
│  - Annual Plans        - Plan Drawdowns (051)  - Budget Commitment          │
│  - Plan Reviews (049)  - Balance Tracking      - Consolidation Engine       │
│  - Plan Versions (050)                                                      │
│                                                                             │
│  [Procurement Handover][Tracking & Analytics]  [Governance & Ops]           │
│  - Procurement Queue   - Status Tracking       - Protected Audit Trails     │
│  - GHANEPS Packaging   - Management Reports    - Delivery / Consumption     │
│                        - Role Dashboards       - System Administration      │
├─────────────────────────────────────────────────────────────────────────────┤
│                          DATA ACCESS & REPOSITORIES                         │
│                    PDO Parameterized Statements & Schemas                   │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Module Responsibilities & Phasing Classification

To prevent scope creep, each module is categorized into:
- **`CONFIRMED PHASE 1`**: Required for the core operational rollout of PROMIS.
- **`TO BE CONFIRMED`**: Scope depends on pending institutional procedural decisions.
- **`PROPOSED LATER PHASE`**: Explicitly deferred to future project stages.

### 1. Authentication Module
- **Responsibilities**: Credential verification via cryptographic password hashing, session lifecycle management, session fixation defense, and secure session cookie issuance.
- **Phasing**: `CONFIRMED PHASE 1`

### 2. Dashboard Module
- **Responsibilities**: Role-tailored operational workspaces displaying actionable pending queues (e.g. "Requisitions Awaiting Your Approval", "Pending Commitments"), summary counts, and quick-action triggers.
- **Phasing**: `CONFIRMED PHASE 1 (CORE OPERATIONAL)` / Advanced Analytics: `PROPOSED LATER PHASE`

### 3. Organizational Management Module
- **Responsibilities**: Maintenance of the ~62 planning entities, campus directories, entity classifications (Departments, Directorates, Units, Halls), and parent-child administrative hierarchies.
- **Phasing**: `CONFIRMED PHASE 1 (CONFIGURABLE ARCHITECTURE)` / Authoritative Data: `PENDING DATA`

### 4. Budget Allocation Module
- **Responsibilities**: Captures departmental budget ceilings and vote codes from Finance to provide reference limits for procurement planning and requisition checks.
- **Phasing**: `CONFIRMED PHASE 1 (MANUAL / UPLOAD TOUCHPOINT)` / Direct Ledger API Sync: `PROPOSED LATER PHASE`

### 5. Procurement Planning Module
- **Responsibilities**: Compilation, validation, and submission of annual procurement plans with line-item estimates, planned quantities, required quarters, and justifications.
- **Phasing**: `CONFIRMED PHASE 1`

### 6. Procurement Plan Review Module (FR-049)
- **Responsibilities**: Manages the quarterly review workflow for approved plans; records review sessions; processes dual outcomes (**No change** vs. **Revision required**).
- **Phasing**: `CONFIRMED PHASE 1 / DETAILED PROCEDURE: TO BE CONFIRMED`

### 7. Procurement Plan Revision & Versioning Module (FR-050)
- **Responsibilities**: Manages authorized plan revisions; calculates itemized quantity and cost deltas; preserves version history through a dedicated versioning structure ensuring historical approved procurement-plan versions shall be preserved and shall not be overwritten.
- **Phasing**: `CONFIRMED PHASE 1 / VERSIONING DETAILS: TO BE CONFIRMED`

### 8. Standard Item Catalogue Module
- **Responsibilities**: Enforces controlled item descriptions, categorization hierarchies, units of measure, and prevents uncontrolled free-text entries from disrupting demand consolidation.
- **Phasing**: `CONFIRMED PHASE 1`

### 9. Requisition Management Module (FR-051)
- **Responsibilities**: Creation, validation, editing, and submission of operational item requests; maintains Plan Version Association (linking requisitions to the plan version under which they were submitted).
- **Phasing**: `CONFIRMED PHASE 1`

### 10. Approval Management Module
- **Responsibilities**: Executes configured approval routing; allows authorized officers to Approve, Query/Return, or Reject records; enforces server-side permission checks.
- **Phasing**: `CONFIRMED PHASE 1`

### 11. Budget Commitment Authorization Module
- **Responsibilities**: Routes approved requisitions to Finance Officers for vote balance verification and formal commitment authorization prior to procurement processing.
- **Phasing**: `CONFIRMED PHASE 1 (WORKFLOW TOUCHPOINT) / EXACT RULES: TO BE CONFIRMED`

### 12. Consolidation Module
- **Responsibilities**: Aggregates approved, committed requisitions for common items into consolidated procurement packages, presenting Institutional Totals alongside Granular Source Entity Breakdowns.
- **Phasing**: `CONFIRMED PHASE 1`

### 13. Procurement Processing Handover Module
- **Responsibilities**: Provides the Directorate of Procurement with authorized procurement packages; facilitates formatting for internal shopping or downstream GHANEPS tendering.
- **Phasing**: `CONFIRMED PHASE 1` / Full Tendering & Supplier Evaluation: `EXTERNAL (GHANEPS)`

### 14. Status Tracking Module
- **Responsibilities**: Provides requesting units with real-time, transparent visibility into the lifecycle progress of their requisitions.
- **Phasing**: `CONFIRMED PHASE 1`

### 15. Notifications Module
- **Responsibilities**: Dispatches internal in-app status badges, alerts, and optional email notifications on critical workflow events (submission, return, approval, commitment).
- **Phasing**: In-app Alerts: `CONFIRMED PHASE 1` / SMS/External Gateways: `PROPOSED LATER PHASE`

### 16. Audit Trail Module
- **Responsibilities**: Captures comprehensive, tamper-resistant operational logs (actor, action, record, previous state, new state, timestamp, IP); ensures audit records are protected from unauthorized modification or deletion.
- **Phasing**: `CONFIRMED PHASE 1`

### 17. Delivery & Consumption Records Module
- **Responsibilities**: Records delivery receipts against requisitions and maintains a historical consumption log by item, entity, campus, and period to guide future planning.
- **Phasing**: `CONFIRMED PHASE 1 (CORE RECORDING)` / Full Warehouse Stock Control: `PROPOSED LATER PHASE`

### 18. Reporting Module
- **Responsibilities**: Generates standard management reports (consumption, throughput turnaround times, consolidated demand, plan revision variance) with role-scoped access control and tabular export.
- **Phasing**: Core Management Reports: `CONFIRMED PHASE 1` / Ad-hoc BI Cube: `PROPOSED LATER PHASE`

### 19. System Administration Module
- **Responsibilities**: Administrative consoles for managing user accounts, assigning roles, updating organizational entities, configuring approval routes, and monitoring system health.
- **Phasing**: `CONFIRMED PHASE 1`
