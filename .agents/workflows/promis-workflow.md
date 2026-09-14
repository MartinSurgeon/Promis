---
description: Business processes, approval hierarchies, budget commitments, requisition consolidation, and GHANEPS boundaries for PROMIS.
---

# PROMIS Business Domain & Workflow Engine

## Purpose

This workflow defines the institutional business domain, approval states, requisition workflows, consolidation mechanics, and external boundaries for PROMIS at USTED.

---

## 1. Separation of Core Business Concepts

In PROMIS, the following business concepts represent distinct institutional entities and must never be merged:

```text
┌───────────────────────────────────────────────────────────────┐
│ 1. Budget Allocation        Institutional financial envelope  │
│ 2. Planning Entity         Entity/Cost-center with budget     │
│ 3. Procurement Plan        Intended procurement for the year  │
│ 4. Approval                Administrative managerial consent  │
│ 5. Item Request / Req.     Actual operational requisition     │
│ 6. Consolidation           Institutional aggregation of items │
│ 7. Commitment Auth.        Finance budget fund reservation    │
│ 8. Procurement Processing  Directorate of Procurement action  │
│ 9. Consumption Record      Received and utilized goods/assets │
│ 10. GHANEPS                External national e-procurement    │
└───────────────────────────────────────────────────────────────┘
```

### Procurement Plan vs. Item Request (Requisition)
- A **Procurement Plan** represents what a department/entity *intends* to procure over a financial year.
- An **Item Request (Requisition)** represents what the department is *actively requesting* against its approved plan.
- **Quantity Tracking Rule**: The system must track:
  ```text
  Approved Planned Quantity
  - Previously Requested Quantity
  - Current Request Quantity
  = Remaining Available Quantity
  ```
- **Over-Request Control**: Requisitions exceeding remaining approved quantities must be blocked, unless routed through an explicitly authorized variation or supplementary budget workflow.

---

## 2. Requisition Consolidation Rules

When multiple departments submit requests for common items (e.g. laptops, stationary, office furniture), PROMIS consolidates requirements for bulk procurement.

### Institutional Total vs. Source Breakdown
Consolidation must provide **both**:
1. **Institutional Aggregated Total** (for bulk purchasing/tendering):
   ```text
   Total Institutional Laptops = 85
   ```
2. **Entity-Specific Granular Breakdown**:
   ```text
   - ICT Directorate: 20
   - Directorate of Finance: 15
   - School of Engineering: 30
   - University Library: 20
   ```

### Non-Erasure Rule
Consolidation must **never** strip away provenance metadata:
- Planning entity / Cost Center
- Requester identity and campus
- Department / Unit
- Original requisition reference number
- Item specifications and audit trail

---

## 3. Institutional Approval Lifecycle

Approval authority flows strictly from approved University statutes and financial regulations. **Never invent approval authority.**

### Canonical Workflow State Machine
```text
[Draft]
  │
  ▼
[Submitted]
  │
  ▼
[Pending Approval]  ──(Rejected / Returned)──► [Rejected] / [Returned]
  │
  ▼
[Approved]
  │
  ▼
[Pending Budget Commitment Authorization]
  │
  ▼
[Commitment Authorized]
  │
  ▼
[Sent to Procurement]
  │
  ▼
[Processing]
```

### Key Workflow Actors & Roles
Keep these five institutional roles distinct:
1. **Request Preparer**: Compiles requisition line items and justifications.
2. **Requesting Office / Head**: Submits and endorses the departmental need.
3. **Approving Officer (e.g. Director / Dean)**: Authorizes departmental execution.
4. **Budget Commitment Officer (Finance)**: Verifies budget availability and commits institutional funds.
5. **Procurement Officer**: Manages consolidation, sourcing, and contract processing.

### Unit Workflows Example
- **Departmental Flow**:  
  Department Head → Director Approval → Budget Commitment Authorization → Directorate of Procurement.
- **Director's Office Flow**:  
  Administrator prepares → Director approves → Budget Commitment Authorization → Directorate of Procurement.
- *Do not assume all university units follow the identical sub-stages without checking specific institutional rules.*

---

## 4. The GHANEPS Boundary

- **PROMIS is Internal**: Handles internal planning, requisitions, multi-tier approvals, budget commitment reservations, departmental tracking, and institutional records.
- **GHANEPS is External**: The Ghana Electronic Procurement System is the public, supplier-facing platform mandated for public tender notices, supplier bids, tender evaluations, and contract awards.
- **PROMIS Boundary Rule**: PROMIS does **not** replace GHANEPS. Instead, PROMIS consolidates and packages verified institutional requirements to feed cleanly into downstream GHANEPS activities.
