# PROMIS Architecture Specification: Reporting Architecture

## 1. Reporting Architecture Overview

The reporting architecture transforms operational transactions into decision-support intelligence for university executives, planning officers, finance, and procurement.

### Scope Governance
In accordance with system constraints, the architecture focuses on **confirmed reporting requirements** from the master specification. Any future analytics dimensions are strictly designated as `PROPOSED`.

---

## 2. Confirmed Management Reports Architecture

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                          REPORTING DOMAIN ARCHITECTURE                      │
│                                                                             │
│  ┌───────────────────────┐   ┌───────────────────────┐   ┌────────────────┐ │
│  │   QUERY & FILTER      │   │  DATA AGGREGATION     │   │ EXPORT FORMAT  │ │
│  │       ENGINE          │──►│       SERVICE         │──►│    SERVICE     │ │
│  │ (Entity, Date, Status)│   │ (Metrics, Bottlenecks)│   │ (CSV/Excel/PDF)│ │
│  └───────────────────────┘   └───────────────────────┘   └────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1. Consumption Report Architecture (FR-042, FR-043)
- **Purpose**: Generates longitudinal consumption history to guide annual planning.
- **Dimensions**: Standard Item Code, Description, Category, Planning Entity, Campus, Quantity Consumed, Aggregate Value, Fiscal Period.
- **Aggregation**: Groups completed deliveries and acknowledged drawdowns by fiscal quarter and year.

### 2. Requisition Volume & Spend Report (FR-043)
- **Purpose**: Tracks transactional velocity and budget utilization across planning entities.
- **Dimensions**: Planning Entity, Campus, Requisitions Submitted, Requisitions Approved, Requisitions Rejected, Committed Expenditure, Remaining Allocation.

### 3. Stage Turnaround Time & Bottleneck Analysis (FR-043)
- **Purpose**: Measures administrative efficiency across the approval and commitment pipeline.
- **Metrics**: Computes duration deltas between audit timestamps:
  - $\Delta t_1 = \text{Date(Submitted)} \rightarrow \text{Date(HoD Approved)}$
  - $\Delta t_2 = \text{Date(HoD Approved)} \rightarrow \text{Date(Director Approved)}$
  - $\Delta t_3 = \text{Date(Director Approved)} \rightarrow \text{Date(Commitment Authorized)}$
  - $\Delta t_4 = \text{Date(Commitment Authorized)} \rightarrow \text{Date(Procurement Released)}$

### 4. Institutional Demand Consolidation Report (FR-030, FR-034)
- **Purpose**: Generates university-wide procurement totals for sourcing packages.
- **Dimensions**: Item Code, Description, Unit of Measure, Total Aggregated Quantity, sub-breakdown by contributing Planning Entity and Campus.

### 5. Plan Revision & Variance Report (FR-049, FR-050)
- **Purpose**: Tracks adjustments resulting from quarterly plan reviews.
- **Dimensions**: Planning Entity, Plan Fiscal Year, Revision Number, Items Added/Removed, Quantity Deltas, Financial Variances, Authorizing Officer.

### 6. Workflow Status Summary Report (FR-037, FR-043)
- **Purpose**: Operational monitoring of all in-flight requisitions by current lifecycle status.

---

## 3. Search & Multi-Parameter Filter Engine (FR-044)

The query subsystem implements parameterized search filtering without generating dynamic SQL strings:
- Keyword text matching on Requisition Numbers, Item Codes, and Descriptions.
- Multi-select dropdown filtering by Planning Entity, Campus, and Lifecycle Status.
- Fiscal Year and Date Range boundaries.

---

## 4. Role-Scoped Data Export Architecture

- **Export Formatting**: Tabular reports can be rendered as structured CSV/Excel downloads or print-optimized PDF memos (`STATUS: TO BE CONFIRMED`).
- **Access Scoping**:
  - Departmental users are strictly restricted to data originating from their assigned planning entity.
  - Institutional executives, Finance Officers, and Procurement Officers can query university-wide data.
