# PROMIS Requirements: Reporting & Management Information

## 1. Reporting Objectives

PROMIS must transform fragmented paper requisition records into real-time, actionable business intelligence for university management, planning officers, finance, and procurement.

---

## 2. Standard Management Reports

### 1. Item & Category Consumption Report
- **Purpose**: Tracks cumulative consumption across time to inform future annual procurement planning.
- **Dimensions**: Item Code, Item Name, Category, Planning Entity, Campus, Quantity Consumed, Aggregate Expenditure, Fiscal Quarter/Year.
- **Filters**: Date range, entity type, campus, item category.
- **Status**: `CONFIRMED`

### 2. Departmental Requisition Volume & Spend Report
- **Purpose**: Analyzes requisition frequency, item volumes, and expenditure per planning entity.
- **Dimensions**: Planning Entity Name, Campus, Total Requisitions Submitted, Total Approved, Total Rejected, Committed Expenditure, Uncommitted Balance.
- **Status**: `CONFIRMED`

### 3. Stage Turnaround Time & Bottleneck Analysis
- **Purpose**: Identifies delays across the administrative approval and budget authorization pipeline.
- **Metrics**: Average hours/days spent in:
  - `Submitted` → `HoD Approval`
  - `HoD Approval` → `Director Approval`
  - `Director Approval` → `Budget Commitment Authorization`
  - `Budget Commitment` → `Procurement Handover`
- **Status**: `CONFIRMED`

### 4. Institutional Demand Consolidation Report
- **Purpose**: Provides the Directorate of Procurement with aggregated procurement quantities for bulk purchasing, tendering, or framework agreements.
- **Dimensions**: Standard Item Code, Description, Unit of Measure, Total Institutional Demand, Sub-breakdown by contributing Planning Entity and Campus.
- **Status**: `CONFIRMED`

### 5. Procurement Plan Revision & Variance Report (FR-049, FR-050)
- **Purpose**: Audits changes introduced during quarterly plan reviews.
- **Dimensions**: Planning Entity, Plan Fiscal Year, Revision Number, Revision Date, Items Added/Removed, Quantity Variances (Baseline vs. Revised), Expenditure Delta, Authorizing Officer.
- **Status**: `CONFIRMED BY CLIENT`

### 6. Workflow Status & Exception Summary
- **Purpose**: Operational dashboard tracking all in-flight requisitions by current state (`Pending Approval`, `Returned`, `Pending Commitment`).
- **Status**: `CONFIRMED`

---

## 3. Search, Filtering & Data Retrieval

### REP-FLT-001: Multi-Parameter Query Engine
The system shall provide comprehensive search and filtering capabilities supporting:
- Keyword text search (Requisition Number, Item Name, Specification keywords)
- Planning Entity & Parent Directorate
- Campus location
- Lifecycle Status (Draft, Submitted, Approved, Committed, Processing, Delivered, Rejected)
- Financial Period (Fiscal Year, Quarter)
- Date range (Date submitted, Date approved, Date required)
- Submitting Officer / Approving Officer  
*Status*: `PROPOSED / TO BE CONFIRMED`

---

## 4. Data Export & Access Control

### REP-EXP-001: Structured Export Formats
The reporting module should support exporting generated tabular data into standard formats:
- **CSV / Spreadsheet (Excel)**: For administrative analysis and budgeting.
- **PDF Summary Sheets**: For formal institutional memos and committee presentations.  
*Status*: `TO BE CONFIRMED`

### REP-EXP-002: Role-Based Report Scoping
Report generation shall respect user permissions:
- Departmental users can only view report data for their assigned planning entity.
- Executive management, Finance, and Procurement Officers can view university-wide institutional reports.  
*Status*: `CONFIRMED`
