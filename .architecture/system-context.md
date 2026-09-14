# PROMIS Architecture Specification: System Context & Boundaries

## 1. Context Overview

PROMIS serves as the internal institutional backbone for procurement planning, demand management, and administrative approvals at USTED. It operates entirely within the University's administrative boundaries and interfaces cleanly with external statutory platforms.

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                           USTED INTERNAL BOUNDARY                           │
│                                                                             │
│   [Planning Entities (~62)]                                                 │
│   Academic Depts, Directorates, Units, Halls                                │
│   - Annual Plans & Quarterly Reviews (FR-049, FR-050)                       │
│   - Item Requisitions linked to Plans (FR-051)                              │
│         │                                                                   │
│         ▼                                                                   │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │                            PROMIS CORE                              │   │
│   │  - Configurable Workflow/Routing (Approvals & Returns)              │   │
│   │  - Standard Item Catalogue Management                               │   │
│   │  - Demand Consolidation Engine (Institutional Totals + Breakdowns)  │   │
│   │  - Protected Audit Trails & History                                 │   │
│   │  - Consumption & Delivery Tracking                                  │   │
│   └──────────────────────┬───────────────────────▲──────────────────────┘   │
│                          │                       │                          │
│                          ▼                       │ (Allocation Data)        │
│   [Directorate of Procurement]         [Directorate of Finance]             │
│   - Receives Consolidated Demand       - Budget allocation capture          │
│   - Sourcing Strategy Formulation      - Commitment authorization           │
│   - Prepares GHANEPS Tender Packages     checkpoint (Touchpoint)            │
└──────────────────────────┬──────────────────────────────────────────────────┘
                           │
                           │ (Structured Requirement Package Export)
                           ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         EXTERNAL PUBLIC BOUNDARY                            │
│                                                                             │
│                   GHANEPS (Public Procurement Platform)                     │
│                   - Public Tender Notices & Advertising                     │
│                   - Supplier Registration & Bid Submission                  │
│                   - Electronic Bid Opening, Evaluation & Contract Award     │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. The Strict GHANEPS Architectural Boundary

A foundational architectural rule is that **PROMIS does not replace or compete with GHANEPS**:

### What Belongs in PROMIS (Internal Domain)
- Annual procurement planning by planning entities.
- Quarterly procurement plan reviews and authorized revisions.
- Operational requisitioning against approved planned quantities.
- Multi-tier institutional approval workflows (HoD, Dean, Director).
- Budget allocation and commitment authorization touchpoints with Finance.
- Demand consolidation across planning entities and satellite campuses.
- Delivery receipt logging and historical consumption records.
- Internal procurement tracking and management reporting.

### What Belongs in GHANEPS (External Statutory Domain)
- Publication of public procurement tenders and expressions of interest.
- Supplier registration, portal access, and tender document downloads.
- Electronic bid submission by commercial contractors and vendors.
- Statutory bid opening, evaluation committee scoring, and official award notices.
- Contractual supplier-facing communication mandated under public procurement law.

### The Interfacing Bridge
PROMIS prepares, consolidates, and packages internal university requirements into well-specified, standardized procurement packages. These packages are made available to the Directorate of Procurement to initiate external tendering in GHANEPS where statutory thresholds require it.

---

## 3. Financial Integration Boundary

In accordance with user directives, the financial architecture recognizes:
- **Budget Allocation & Commitment Authorization Touchpoints**:
  - PROMIS captures budget allocation figures assigned to planning entities.
  - The workflow routes approved requisitions through a mandatory Budget Commitment Authorization checkpoint in the Directorate of Finance before releasing requirements to Procurement.
- **Status of Automated Sync**:
  - The exact financial integration mechanism (e.g. manual entry/upload vs. direct API integration with the University General Ledger) is **`TO BE CONFIRMED`**.
  - PROMIS does not construct a complex financial-system integration architecture beyond this confirmed boundary in Phase 1.
