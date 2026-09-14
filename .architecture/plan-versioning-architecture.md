# PROMIS Architecture Specification: Plan Versioning & Quarterly Review Architecture

## 1. Domain Model Hierarchy

The procurement planning domain maintains a strict hierarchical relationship between university budget ceilings, planning entities, plans, and discrete item lines:

```text
┌─────────────────────────┐
│    BUDGET ALLOCATION    │ (Allocated by Directorate of Finance)
└────────────┬────────────┘
             │ 1
             ▼ ∞
┌─────────────────────────┐
│     PLANNING ENTITY     │ (~62 Units across USTED campuses)
└────────────┬────────────┘
             │ 1
             ▼ ∞
┌─────────────────────────┐
│    PROCUREMENT PLAN     │ (Fiscal Year Root Container)
└────────────┬────────────┘
             │ 1
             ▼ ∞
┌─────────────────────────┐
│  PLAN VERSION (049/050) │ (Version 1.0, 2.0, Historical vs. Active)
└────────────┬────────────┘
             │ 1
             ▼ ∞
┌─────────────────────────┐
│   PROCUREMENT PLAN ITEM │ (Line Item: Item Code, Planned Qty, Cost, Quarter)
└─────────────────────────┘
```

### Fundamental Conceptual Distinction
A **Procurement Plan** represents an entity's projected or intended procurement for a fiscal year. An **Item Requisition** represents an operational drawdown of goods against that plan. The system architecture strictly isolates the two domains into distinct data structures and services.

---

## 2. Quarterly Plan Review & Revision Architecture (FR-049, FR-050)

In accordance with confirmed client requirements, approved procurement plans undergo a structured quarterly review process:

```text
               ┌─────────────────────────────────────────┐
               │     APPROVED PLAN (Version 1.0)         │
               └────────────────────┬────────────────────┘
                                    │
                                    ▼
                       [Quarterly Review Triggered]
                                    │
            ┌───────────────────────┴───────────────────────┐
            ▼                                               ▼
   [Review Outcome: NO CHANGE]              [Review Outcome: REVISION REQUIRED]
   - Certified by Planning Officer          - Draft Revision Staged
   - Audit Log Entry Created                - Line Items Added / Modified / Removed
   - Plan remains active Version 1.0        - Mandatory Revision Justification
                                            - Submitted for Formal Approval
                                                            │
                                                            ▼ (Approved)
                                             ┌─────────────────────────────────────────┐
                                             │      APPROVED PLAN (Version 2.0)        │
                                             │ - Version 2.0 becomes Current Active    │
                                             │ - Historical versions preserved         │
                                             │ - Not overwritten                       │
                                             └─────────────────────────────────────────┘
```

---

## 3. Plan Version Preservation & Delta Tracking

### Business Requirement vs. Architectural Implementation
- **Business Requirement**: Historical approved procurement-plan versions shall be preserved and shall not be overwritten.
- **Architectural Implementation**: Version history shall be preserved through a dedicated versioning structure; the exact physical database implementation will be determined during the Physical Database Design phase.

> [!NOTE]
> Do not assume database immutability until the physical design and security model explicitly establish how historical records are protected.

1. **Version History Preservation**: When a revision is approved, the system generates a new approved version record (e.g. Version 2.0). The previous version baseline is preserved.
2. **Itemized Variance Delta**: The versioning service calculates and records item-level variances:
   - Changed items: Previous Planned Quantity vs. Revised Planned Quantity.
   - Financial variance: Previous Estimated Line Total vs. Revised Estimated Line Total.
   - Newly introduced items or de-committed items.
3. **Audit Provenance**: Every version change records the preparing officer, approving officer, approval timestamp, and revision rationale.

---

## 4. Unresolved Operational Rules (Pending Institutional Confirmation)

While the versioning data architecture supports plan revisions and version preservation, the following operational rules are catalogued as **`TO BE CONFIRMED`**:

1. **Review Initiation**: What event triggers the quarterly review (calendar date, notification from Procurement, or fiscal quarter boundary)?
2. **Review Initiator**: Who initiates the review (Central Procurement, Finance, or the Planning Officer within each entity)?
3. **Revision Approver**: Does approving a quarterly revision require the exact same hierarchy as the annual baseline plan, or an expedited approval level?
4. **Impact on In-Flight Requisitions**: What occurs to requisitions submitted under Version 1.0 that are currently pending approval when Version 2.0 is approved?
5. **Impact on Approved Drawdowns**: Are approved requisitions grandfathered against Version 1.0, and how are remaining balances reconciled if revised quantities decrease?
6. **Inter-Quarter Revisions**: Can a plan be revised outside the formal quarterly review window (e.g. for emergency institutional needs)?
7. **Budget Re-validation**: Does a revision that increases planned expenditure require Finance re-authorization of the departmental budget ceiling?
