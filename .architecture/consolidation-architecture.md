# PROMIS Architecture Specification: Demand Consolidation Architecture

## 1. Architectural Purpose & Problem

Historically, fragmented paper requisitions obscured aggregate institutional demand. Different departments and campuses purchased identical items in small, disaggregated lots at higher retail prices.

The **PROMIS Consolidation Architecture** aggregates operational requisitions across the University into bulk procurement packages, while strictly preserving granular organizational provenance.

---

## 2. Dual Aggregation Architecture

The consolidation engine is architected around a mandatory **Dual Aggregation Principle**:

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                       CONSOLIDATED PROCUREMENT PACKAGE                      │
│                                                                             │
│  [VIEW 1: INSTITUTIONAL AGGREGATED TOTAL]                                   │
│  - Item: Standard Laptops (Core i7, 16GB RAM)                               │
│  - Standard Item Catalogue Code: IT-LAP-001                                 │
│  - Total University Demand: 85 Units                                        │
│  - Aggregate Estimated Budget: GHS 850,000                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│  [VIEW 2: GRANULAR PROVENANCE & SOURCE BREAKDOWN]                           │
│                                                                             │
│  ├── ICT Directorate (Main Campus): 20 Units                                │
│  │   └── Requisition Ref: PR-2026-00102 (Requester: J. Mensah, Committed)  │
│  │                                                                          │
│  ├── Directorate of Finance (City Campus): 15 Units                         │
│  │   └── Requisition Ref: PR-2026-00115 (Requester: A. Osei, Committed)    │
│  │                                                                          │
│  ├── School of Engineering (Main Campus): 30 Units                          │
│  │   └── Requisition Ref: PR-2026-00128 (Requester: K. Boateng, Committed) │
│  │                                                                          │
│  └── University Library (Main Campus): 20 Units                             │
│      └── Requisition Ref: PR-2026-00142 (Requester: E. Appiah, Committed)  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Provenance Preservation Architecture

### Architectural Rule: Non-Destructive Aggregation
Consolidation must **never destroy or flatten source information**. The consolidation architecture implements a relational mapping table between consolidated item packages and individual requisition items:

```text
Consolidated Package (1) ─── (∞) Consolidated Package Item (1) ─── (∞) Source Requisition Item
```

### Preserved Provenance Attributes
Every consolidated item permanently retains:
1. **Source Planning Entity**: Sponsoring department, unit, or directorate.
2. **Campus Location**: Delivery destination campus.
3. **Requester Identity**: Staff member who prepared the demand.
4. **Requisition Reference Code**: Original unique requisition number (e.g. `PR-2026-00125`).
5. **Approval & Commitment Timestamps**: Verifiable audit history proving statutory authorization.
6. **Delivery Allocations**: Downstream goods receipt vouchers map directly back to individual requisitions to fulfill original departmental orders.

---

## 4. Consolidation Execution Flow

```text
[1. Requirement Harvesting]
    └── Query engine filters requisitions: Status = "Commitment Authorized"
          │
          ▼
[2. Catalogue Taxonomy Matching]
    └── Items grouped by Standard Catalogue Code (BR-010)
          │
          ▼
[3. Package Assembly & Verification]
    ├── Sourcing Officer reviews grouped quantities and campus breakdown
    └── System generates Consolidated Package ID
          │
          ▼
[4. Sourcing Strategy Determination]
    ├── Low Value: Internal National Shopping / Request for Quotation
    └── High Value: Packaged for external tendering via GHANEPS
```
