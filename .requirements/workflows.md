# PROMIS Requirements: Workflow & State Machine Specifications

## 1. End-to-End Institutional Procurement Lifecycle

The operational lifecycle of procurement in PROMIS spans ten macro stages:

```text
[1. Budget Allocation]
         │ (Allocated by Directorate of Finance)
         ▼
[2. Planning Entity Setup]
         │ (Configured with Planning Officers and Approvers)
         ▼
[3. Annual Procurement Plan Creation]
         │ (Items, estimated costs, required quarters)
         ▼
[4. Plan Approval & Baselining]
         │ (Approved by Head/Dean/Director)
         ▼
[5. Quarterly Plan Review & Revision] ◄───(Periodic cycle: Q1, Q2, Q3, Q4)
         │ (Revision recorded, version preserved, re-approved)
         ▼
[6. Operational Item Requisitions]
         │ (Drawn against approved planned quantities)
         ▼
[7. Multi-Tier Administrative Approval]
         │ (HoD → Dean/Director; Return with queries or Reject)
         ▼
[8. Budget Commitment Authorization]
         │ (Finance verifies vote & reserves institutional funds)
         ▼
[9. Institutional Consolidation & Procurement Handover]
         │ (Total aggregated demand + entity breakdown; GHANEPS packaging)
         ▼
[10. Delivery, Consumption & Historical Records]
           (Goods receipt, inventory record, consumption history)
```

---

## 2. Procurement Plan Lifecycles

### A. Annual Procurement Plan Approval State Machine
```text
[Plan Draft] 
      │
      ▼ (Submit)
[Pending Plan Approval] ──(Query / Return)──► [Plan Returned for Correction]
      │                                                │ (Resubmit)
      │                                                ▼
      ├───────────────────(Reject)──────────► [Plan Rejected]
      │
      ▼ (Approve)
[Plan Approved (Baseline Version 1.0)]
```

### B. Quarterly Procurement Plan Review & Revision Workflow
In accordance with **FR-049** and **FR-050**, approved procurement plans undergo a periodic quarterly review process.

**Crucial Workflow Principles**:
1. **A quarterly review does not automatically imply a revision**. A quarterly review may result in:
   - **No change**: The plan is verified and certified as remaining valid; it remains the current active version without alteration.
   - **Revision required**: An authorized plan revision is initiated to modify, add, or remove planned items.
2. **Historical Version Preservation**: Historical approved plan versions are permanently preserved and **not** overwritten once versioning is approved.
3. **Plan Version Association (FR-051)**: Each plan-linked requisition shall retain a historical reference to the approved procurement-plan version under which it was submitted, and that historical association shall not be silently changed.

```text
[Approved Plan (Version N.0)]
            │
            ▼
   [Quarterly Review Triggered] (End of Q1, Q2, Q3, Q4)
            │
   ┌────────┴──────────────────────────┐
   ▼                                   ▼
[No Revision Required]      [Plan Revision Initiated]
(Plan remains Version N.0)             │
                                       ▼
                            [Draft Plan Revision]
                            - Add / Modify / Remove items
                            - Record Revision Justification
                            - Preserve Version N.0 as Historical
                                       │
                                       ▼ (Submit Revision)
                            [Pending Revision Approval]
                                       │
                                       ▼ (Approve)
                            [Plan Approved (Version N+1.0)]
```

*Note: Unresolved governance questions surrounding quarterly review triggers, approval authorities, and impacts on in-flight requisitions are detailed in [open-questions.md](file:///c:/xampp/htdocs/promis/.requirements/open-questions.md).*

---

## 3. Operational Requisition Lifecycle State Machine

The core operational requisition lifecycle proceeds through strict status checkpoints (with each plan-linked requisition referencing its source plan version per FR-051):

```text
[Draft Requisition]
        │
        ▼ (Submit by Preparer)
[Submitted / Pending Approval] ──(Query / Return)──► [Returned for Query]
        │                                                     │
        │                                                     ▼ (Resubmit)
        ├──────────────────────(Reject)─────────────► [Rejected] (Closed)
        │
        ▼ (Approve by Authorized Officer)
[Approved / Pending Budget Commitment]
        │
        ▼ (Finance Review)
        ├──────────────────────(Return / Reject)───► [Commitment Rejected / Returned]
        │
        ▼ (Commitment Authorized)
[Commitment Authorized]
        │
        ▼ (Release to Procurement)
[Sent to Procurement]
        │
        ▼ (Procurement Processing / Tendering)
[Processing]
        │
        ▼ (Order Placed & Goods Delivered)
[Delivered / Fulfilled]
```

### Allowable Requisition Lifecycle States
- **`Draft`**: Requisition is being prepared; line items can be added, updated, or removed.
- **`Submitted`**: Requisition submitted for administrative review; editing locked.
- **`Pending Approval`**: In the approval queue of the designated Head or Director.
- **`Returned`**: Returned by approver with comments/queries; unlocked for preparer correction.
- **`Rejected`**: Disapproved with recorded reasons; terminal state.
- **`Approved`**: Endorsed by administrative authority; queued for Finance.
- **`Pending Budget Commitment`**: Awaiting fund reservation in the Directorate of Finance.
- **`Commitment Authorized`**: Institutional funds committed; authorized for procurement release.
- **`Sent to Procurement`**: Available for consolidation and purchasing.
- **`Processing`**: Active in procurement sourcing, RFQ, or GHANEPS tendering.
- **`Delivered / Completed`**: Goods received and acknowledged by the requesting entity.
- **`Cancelled`**: Withdrawn by authorized user prior to processing.

---

## 4. Office-Specific Approval Sequences

### Flow A: Standard Academic / Administrative Department
```text
Preparer (Departmental Staff)
     ↓
Head of Department (Endorses / Approves)
     ↓
Director / Dean (Executive Review & Approval)
     ↓
Directorate of Finance (Budget Commitment Authorization)
     ↓
Directorate of Procurement (Consolidation & Processing)
```

### Flow B: Executive Director's Office
```text
Office Administrator (Prepares Request on behalf of Director)
     ↓
Director (Direct Executive Approval)
     ↓
Directorate of Finance (Budget Commitment Authorization)
     ↓
Directorate of Procurement (Consolidation & Processing)
```

*Rule: Do not assume all 62 planning entities follow identical sub-stages without checking university procedures.*

---

## 5. Demand Consolidation Flow

```text
Approved & Commitment-Authorized Requisitions (from across 62 Entities & Campuses)
                         │
                         ▼
             [Item Taxonomy Filter]
     (Group by Standard Item Catalogue Codes)
                         │
                         ▼
        [Consolidated Procurement Package]
      ┌──────────────────┴──────────────────┐
      ▼                                     ▼
[Institutional Summary]             [Granular Provenance]
- Item Code & Description           - Entity 1: Qty A (Campus X)
- Total Consolidated Quantity       - Entity 2: Qty B (Campus Y)
- Estimated Aggregate Value         - Entity 3: Qty C (Campus X)
      │
      ▼
[Procurement Strategy Decision]
- Internal Procurement (Quotation / National Shopping)
- External Tendering via GHANEPS
```
