# PROMIS Requirements: Integration Architecture & System Boundaries

## 1. Context & Boundaries

PROMIS operates as the internal institutional hub for procurement management at USTED. It bridges internal academic and administrative demand with institutional finance and external national e-procurement infrastructure.

```text
┌──────────────────────────────────────────────────────────────┐
│                      USTED INTERNAL DOMAIN                   │
│                                                              │
│  [Planning Entities]  ──►  [PROMIS CORE]  ◄──  [Finance]    │
│  - Requisitions            - Plans             - Allocations │
│  - Reviews (FR-049)        - Approvals         - Commitments │
│                            - Consolidation                   │
└──────────────────────────────────┬───────────────────────────┘
                                   │ (Structured Requirement Package)
                                   ▼
┌──────────────────────────────────────────────────────────────┐
│                    EXTERNAL PUBLIC DOMAIN                    │
│                                                              │
│                     [GHANEPS PLATFORM]                       │
│                     - Public Tender Notices                  │
│                     - Supplier Bidding                       │
│                     - Bid Evaluation & Award                 │
└──────────────────────────────────────────────────────────────┘
```

---

## 2. The GHANEPS Boundary Specification

### INT-GHN-001: Non-Duplication Principle
PROMIS shall not duplicate or replace functionality mandated under the Ghana Electronic Procurement System (**GHANEPS**). 
- **Internal to PROMIS**: Departmental demand gathering, annual/quarterly planning, multi-tier approvals, budget commitment authorization, internal consolidation, and institutional audit tracking.
- **External to GHANEPS**: Public tender advertising, supplier registration, electronic bid submission, supplier clarifications, bid opening/evaluation, and official contract award notices.  
*Status*: `CONFIRMED`

### INT-GHN-002: Downstream Package Formatting
PROMIS shall provide features to format and export consolidated institutional requirements (with standardized item codes, specifications, quantities, and delivery campuses) into structured procurement packages suitable for entry into GHANEPS by Procurement Officers.  
*Status*: `CONFIRMED CONCEPT`  
*Automated API vs. Controlled Export/Import*: `TO BE CONFIRMED`

---

## 3. Financial & Budget Touchpoints

### INT-FIN-001: Budget Allocation Ingestion
The system shall support importing or capturing approved fiscal year budget allocations from the Directorate of Finance.
- **Phase 1 Approach**: Secure manual entry and spreadsheet upload by authorized finance officers.
- **Future Phase Approach**: Direct database or API integration with the University's core financial management system.  
*Status*: `CONFIRMED CONCEPT / EXACT PROCESS TO BE CONFIRMED`

### INT-FIN-002: Budget Commitment Authorization Checkpoint
The workflow engine shall route requisitions to authorized Finance Officers to verify that funds exist and reserve the budget allocation before procurement commences.  
*Status*: `CONFIRMED`

---

## 4. Future Roadmap Integration Hooks (Phase 2+)

1. **Central Stores & Inventory Management**:
   - Automated creation of goods receipt vouchers upon delivery and automatic updating of inventory stock cards.
2. **University Human Resource Information System (HRIS)**:
   - Automated synchronization of university staff, designated Heads of Department, and active email accounts.
3. **ERP / General Ledger Integration**:
   - Automated transmission of commitment authorization transactions into the university general ledger.  
*Status*: `DEFERRED TO LATER PHASES`
