# PROMIS Requirements: Actors, Roles & Permissions

## 1. The Core Conceptual Separation

To prevent architectural rigidity and ensure security compliance, PROMIS strictly decouples organizational units, human operators, and functional access rights into five distinct concepts:

```text
┌──────────────────────────────────────────────────────────────┐
│ 1. Planning Entity    Organizational unit owning budget/need │
│ 2. User               Human operator authenticated to login  │
│ 3. Role               Named collection of system privileges  │
│ 4. Permission         Granular technical action permitted    │
│ 5. Approval Authority Statutory office authorized to approve │
└──────────────────────────────────────────────────────────────┘
```

### Non-Equivalence Axiom
The system shall **never** assume:
- `62 Planning Entities = 62 Users`
- `62 Planning Entities = 62 Roles`
- `1 User = 1 Role`
- `1 User = 1 Planning Entity`

A single planning entity may contain multiple users (e.g. an Administrator preparing requests and a Head approving them). A single user may hold multiple roles across different units if sanctioned by institutional policy.

---

## 2. Candidate Actors & Responsibilities

The official, binding roster of University user accounts and institutional roles has not yet been finalized (`STATUS: PENDING DATA / TO BE CONFIRMED`). Based on the project proposal and stakeholder business discussions, the candidate actors include:

### 1. Planning / Requesting User
- **Description**: Operational staff member within a department, unit, or hall who identifies and enters material needs.
- **Responsibilities**: Creates draft requisitions, selects standardized catalogue items, specifies quantities and justifications, views status tracking.

### 2. Planning Officer
- **Description**: Staff member designated within a planning entity to prepare the annual and quarterly procurement plan.
- **Responsibilities**: Compiles annual procurement plan line items, adjusts quarterly revisions (FR-049, FR-050), submits plans for departmental sign-off.

### 3. Departmental Head (HoD / Head of Section)
- **Description**: Executive head of an academic department, administrative section, or hall.
- **Responsibilities**: Endorses and approves annual/quarterly procurement plans, reviews and approves departmental item requisitions, returns requests with queries, or rejects non-compliant requests.

### 4. Director / Dean
- **Description**: Head of a Directorate, Faculty, or School exercising oversight over subordinate departments.
- **Responsibilities**: Conducts secondary institutional review and approval of requisitions; reviews quarterly procurement plan revisions for the directorate.

### 5. Office Administrator
- **Description**: Administrative assistant acting on behalf of an executive office (e.g. Director's Office).
- **Responsibilities**: Prepares requisitions on behalf of the Directorate executive for subsequent direct approval by the Director.

### 6. Budget Commitment Officer (Directorate of Finance)
- **Description**: Financial officer responsible for university-wide vote commitment and budget control.
- **Responsibilities**: Verifies approved requisitions against allocated budget lines, checks committed vs. uncommitted funds, authorizes budget commitment or returns/rejects requisitions for lack of funds.

### 7. Procurement Officer (Directorate of Procurement)
- **Description**: Specialized procurement professional within the central Directorate of Procurement.
- **Responsibilities**: Accesses consolidated institutional requirements, reviews source-entity breakdowns, manages item catalogue taxonomy, prepares packages for internal processing or external GHANEPS tendering.

### 8. System Administrator
- **Description**: Technical IT administrator maintaining system availability and security configuration.
- **Responsibilities**: Configures planning entities, manages user accounts, assigns roles, configures approval workflow routes, monitors audit logs and system backups.

### 9. Management / Authorized Reviewer
- **Description**: Senior university management (e.g. Vice-Chancellor, Pro-VC, Registrar, Director of Finance, Internal Audit).
- **Responsibilities**: Views institutional dashboards, monitors throughput times and bottlenecks, accesses audit trails and aggregate consumption reports.

---

## 3. Role & Permission Requirements

### PR-ROL-001: Configurable Role-Based Access Control (RBAC)
The system shall provide a configurable RBAC model where permissions are grouped into roles, and roles are mapped to users, without requiring source code modifications to alter role definitions.  
*Status*: `CONFIRMED`

### PR-ROL-002: Server-Side Authorization Enforcement
The system shall enforce all permissions strictly on the server. Client-side state, hidden HTML elements, or manipulated POST payloads shall never be relied upon for access control.  
*Status*: `CONFIRMED`

### PR-ROL-003: User-to-Entity Multi-Tenancy Assignment
The system shall support associating authenticated users with one or more authorized planning entities, restricting record creation and viewing according to assigned entities.  
*Status*: `CONFIRMED`

### PR-ROL-004: Segregation of Duties
The system shall enforce segregation of duties in accordance with university financial regulations:
- A user who prepares a requisition shall not have the sole authority to approve that requisition, unless explicitly permitted for specific executive roles.
- Budget commitment authorization must be restricted exclusively to authorized finance officers.  
*Status*: `CONFIRMED CONCEPT / SPECIFIC RULES TO BE CONFIRMED`

---

## 4. Open Questions & Items Requiring Confirmation

1. What is the authoritative roster of PROMIS roles approved by University management? (`TO BE CONFIRMED`)
2. Can a user hold conflicting roles simultaneously (e.g. Requester in Department A and Approver in Department B)? (`TO BE CONFIRMED`)
3. Who has authority to delegate approval rights when an Approving Officer is on official leave? (`TO BE CONFIRMED`)
