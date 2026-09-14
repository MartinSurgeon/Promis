# PROMIS Architecture Specification: Requisition Architecture

## 1. Requisition Domain Structure

The operational requisition architecture governs the submission, validation, approval, commitment, and fulfillment tracking of institutional goods requests:

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                                 REQUISITION                                 │
│  - Requisition Number (e.g. PR-2026-00125)                                 │
│  - Requesting Entity & Campus                                               │
│  - Requester Identity (Preparer)                                            │
│  - Procurement Plan Reference                                               │
│  - Plan Version Reference (FR-051 Historical Link)                          │
│  - Date Required & Departmental Justification                               │
│  - Current Lifecycle Status                                                 │
│  - Total Estimated Amount                                                   │
│  - Consolidation Status (Unconsolidated, Consolidated)                      │
└──────────────────────┬───────────────────────┬──────────────────────────────┘
                       │ 1                     │ 1
                       ▼ ∞                     ▼ 0..∞
              [REQUEST ITEMS]             [SUPPORTING DOCUMENTS]
              - Standard Item Code        - Specifications, Memos, Quotes
              - Requested Quantity        - Secure File Uploads
              - Unit of Measure
              - Estimated Unit Price
              - Line Total
```

---

## 2. Requisition Quantity Balance Architecture

In accordance with architectural corrections, plan balance calculations adhere strictly to the following **Conceptual Calculation Model**:

```text
Approved Planned Quantity
Previously Requested Quantity
Current Request Quantity
```

### Calculation Stages
1. **Remaining Balance Before Current Request**:
   $$\text{Remaining Before Current Request} = \text{Approved Planned Quantity} - \text{Previously Requested Quantity}$$
2. **Post-Approval Balance**:
   If the current request is approved by the designated institutional authority:
   $$\text{Remaining After Current Request} = \text{Remaining Before Current Request} - \text{Current Request Quantity}$$

### Architectural Guardrails
- **Pre-Submission Check**: When a user enters a requisition quantity, the system computes $\text{Remaining Before Current Request}$ to inform the user of available capacity.
- **Over-Plan Handling**: If $\text{Current Request Quantity} > \text{Remaining Before Current Request}$, the system flags an over-plan condition. Whether the request is blocked or routed through an approved supplementary exception workflow is catalogued as `TO BE CONFIRMED`.
- *Constraint: This calculation is maintained as a configurable service model, avoiding hardcoding beyond approved requirements.*

---

## 3. Plan Version Association Architecture (FR-051)

### Specification
In accordance with **FR-051** (`STATUS: PROPOSED / TO BE CONFIRMED`):

> **"Each plan-linked requisition shall retain a historical reference to the approved procurement-plan version under which it was submitted, and that historical association shall not be silently changed."**

### Architectural Implementation Pattern
- When a plan-linked requisition is created, the system establishes a historical logical reference between the requisition and the approved procurement-plan version.
- If the planning entity subsequently revises its procurement plan (e.g. transitioning from Version 1.0 to Version 2.0 following a quarterly review), previously submitted requisitions **retain their historical reference to Version 1.0**.
- The historical association is protected against automatic or silent mutation, guaranteeing exact auditing and traceability for financial reviews; the exact physical database implementation will be determined during the Physical Database Design phase.

---

## 4. Requisition Lifecycle Progression

The operational flow of a requisition through the system architecture proceeds as follows:

```text
[1. Preparation]
    ├── User selects items from Standard Item Catalogue
    ├── System displays Remaining Balance Before Current Request
    └── Supporting memos/specifications attached
          │
          ▼
[2. Validation & Submission]
    ├── Form validation (mandatory fields, positive quantities)
    ├── Plan Version Association established (FR-051)
    └── State transitions from Draft to Submitted / Pending Approval
          │
          ▼
[3. Multi-Tier Administrative Approval]
    ├── Configurable routing directs to HoD, Dean, Director
    ├── Approver actions: Approve, Query/Return, or Reject
    └── Approval metadata (officer, timestamp, remarks) recorded
          │
          ▼
[4. Budget Commitment Authorization Touchpoint]
    ├── Directorate of Finance validates vote balance
    ├── Commitment authorized and reference recorded
    └── State transitions to Commitment Authorized
          │
          ▼
[5. Consolidation & Handover]
    ├── Requirement marked available for procurement
    └── Grouped into institutional consolidation packages
          │
          ▼
[6. Fulfillment & Delivery Tracking]
    └── Goods receipt logged; consumption record generated
```
