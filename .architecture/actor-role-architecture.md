# PROMIS Architecture Specification: Actor & Role Architecture

## 1. Multi-Tier Decoupling Architecture

A foundational architectural requirement in PROMIS is the strict decoupling of user identity, organizational membership, functional permissions, and workflow authority:

```text
┌────────────────┐       ┌────────────────┐       ┌────────────────────┐
│      USER      │──────►│  USER-ENTITY   │◄──────│  PLANNING ENTITY   │
│ (Identity/Auth)│       │   ASSIGNMENT   │       │ (~62 Units/Campus) │
└───────┬────────┘       └────────────────┘       └────────────────────┘
        │
        ▼
┌────────────────┐       ┌────────────────┐       ┌────────────────────┐
│      ROLE      │──────►│   PERMISSION   │       │      WORKFLOW      │
│ (Named Bundle) │       │(Granular Right)│       │   RESPONSIBILITY   │
└────────────────┘       └────────────────┘       │(Approver, Endorser)│
                                                  └────────────────────┘
```

### Decoupling Rules
1. **User Identity ≠ Role**: A user is an authenticated human operator. Roles are assigned dynamically to users and can be updated without altering user identity or transaction history.
2. **Role ≠ Planning Entity**: Roles define functional capability (e.g. `Requisition Preparer`, `Approving Officer`). Planning entities define institutional organizational scope. A user may hold a role in one department without possessing authority in another.
3. **Role ≠ Approval Authority**: Institutional approval authority flows from university statutes and administrative delegations. Workflow routing checks both the role and the designated approval mapping for the specific planning entity.
4. **No Identity Equivalence**: Never assume `62 Planning Entities = 62 Users = 62 Roles`.

---

## 2. Configurable RBAC Architecture

To prevent code refactoring whenever the University alters job titles or creates new units, PROMIS implements a **Configurable Role-Based Access Control (RBAC)** architecture:

### 1. Granular Permission Keys
Permissions represent discrete, atomic system actions:
- `requisition.create`
- `requisition.submit`
- `requisition.approve.hod`
- `requisition.approve.director`
- `requisition.commit.finance`
- `plan.create`
- `plan.review.quarterly`
- `plan.revise`
- `catalogue.manage`
- `consolidation.execute`
- `report.view.institutional`
- `audit.view`

### 2. Configurable Roles
Roles bundle granular permission keys. The system architecture defines dynamic role-permission mappings. The exact institutional roster of roles remains **`TO BE CONFIRMED`** by University management.

### 3. Dynamic User-to-Entity Mapping
Users are mapped to one or more planning entities with specific assigned roles. This enables:
- Multi-departmental staff representation (e.g. an officer servicing two administrative units).
- Executive representation (e.g. a Director overseeing multiple subordinate departments).

---

## 3. Server-Side Authorization & Enforcement Architecture

### Architectural Pattern: Contextual Authorization Guard
Authorization is enforced server-side via a reusable security service invoked before any business operation is executed. The guard evaluates three context parameters:

```text
AuthorizationGuard::authorize(
    UserContext $user,          // Authenticated identity & active roles
    string $permissionKey,      // Requested atomic permission
    ?PlanningEntity $entity,    // Target organizational scope
    ?RecordContext $record      // Current state of target record
): bool
```

### Dual-Condition Validation Rule
Every state-changing transaction requires:
1. **Actor Permission Check**: The user must possess the requisite permission within the scope of the target planning entity.
2. **Record State Integrity Check**: The target record must reside in an exact lifecycle state that permits the operation (e.g. approving requires state `Pending Approval`; revising a plan requires state `Approved` under quarterly review).

*Note: In compliance with project constraints, authorization is designated as **server-side authorization and role/permission enforcement**, avoiding any assumption of two-factor authentication.*

---

## 4. Candidate Actor Mapping (Architecture Reference)

| Candidate Actor | Architectural Role | Target Permissions Scope | Status |
| :--- | :--- | :--- | :--- |
| **Planning User** | Operational Requisitioner | `requisition.create`, `requisition.view.own` | `TO BE CONFIRMED` |
| **Planning Officer** | Plan Preparer | `plan.create`, `plan.review.quarterly`, `plan.revise` | `TO BE CONFIRMED` |
| **Departmental Head (HoD)** | Tier-1 Approver | `requisition.approve.hod`, `requisition.return`, `plan.submit` | `TO BE CONFIRMED` |
| **Director / Dean** | Tier-2 Approver / Executive | `requisition.approve.director`, `plan.approve` | `TO BE CONFIRMED` |
| **Office Administrator** | Executive Preparer | `requisition.create.proxy` (on behalf of Director) | `TO BE CONFIRMED` |
| **Budget Commitment Officer** | Financial Controller | `requisition.commit.finance`, `budget.manage` | `TO BE CONFIRMED` |
| **Procurement Officer** | Sourcing Professional | `consolidation.view`, `consolidation.execute`, `catalogue.manage` | `TO BE CONFIRMED` |
| **System Administrator** | Technical Controller | `admin.entities`, `admin.users`, `admin.roles`, `admin.routes` | `CONFIRMED` |
| **Management Reviewer** | Institutional Auditor | `report.view.all`, `audit.view` | `TO BE CONFIRMED` |
