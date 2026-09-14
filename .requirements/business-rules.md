# PROMIS Requirements: Business Rules Catalogue

## Overview

This document defines the core institutional and procedural business rules governing PROMIS. In accordance with university procurement governance, PROMIS operates within existing statutory frameworks and does not create new procurement methods or invent approval authority.

---

## 1. Statutory & Regulatory Boundaries

### BR-001: Statutory Framework Compliance
PROMIS shall operate strictly within approved University procurement procedures, the Public Procurement Act, and applicable statutory guidelines. The system shall not bypass any mandatory legal or university requirement.

### BR-002: Preservation of Procurement Methods
PROMIS shall not create or invent new procurement methods. It serves as an electronic information and workflow management system for approved institutional procedures.

### BR-003: Non-Alteration of Approval Authority
PROMIS shall not alter or redefine institutional procurement thresholds or statutory approval authorities. All authority levels must originate from approved university statutes.

### BR-004: Strict GHANEPS Boundary
PROMIS shall not replace or replicate GHANEPS. PROMIS manages internal demand identification, planning, requisitioning, consolidation, approval, and budget commitment. Supplier-facing tendering, bidding, and contract awards remain within GHANEPS where required.

---

## 2. Planning vs. Requisition Concepts

### BR-005: Conceptual Separation of Plans and Requests
The system shall treat Procurement Plans and Item Requests (Requisitions) as fundamentally separate institutional concepts:
- **Procurement Plan**: Represents an entity's projected or intended procurement for a fiscal period.
- **Item Request (Requisition)**: Represents an entity's operational demand to draw down goods against an approved plan.  
The two concepts shall never be collapsed into a single record.

### BR-006: Planned Quantity Tracking Formula
Where plan-linked requisitioning is enforced, the system shall strictly track quantities using:
$$\text{Remaining Available Quantity} = \text{Approved Planned Quantity} - \text{Previously Requested Quantity} - \text{Current Request Quantity}$$

### BR-007: Over-Plan Request Controls
The system shall identify requests where requested quantities exceed remaining approved plan balances. Such requests must either be prevented or routed through an approved exception/variation workflow.  
*Exact over-plan policy*: `TO BE CONFIRMED`

---

## 3. Quarterly Procurement Plan Review & Revision Rules

### BR-008: Quarterly Plan Review Cycle & Outcomes
Approved procurement plans shall be subject to a periodic review process conducted on a quarterly basis in accordance with University procurement planning procedures. A quarterly review does not automatically imply a revision. A review may result in:
- **No change**: The plan is confirmed as valid and continues as the active version.
- **Revision required**: An authorized revision is initiated to update item quantities, add items, or remove items.  
*Status*: `CONFIRMED BY CLIENT / PROCEDURE TO BE CONFIRMED`

### BR-009: Plan Version & Revision History Preservation
When an approved procurement plan is revised following a quarterly review:
1. Historical approved plan versions shall be preserved and **never overwritten** once versioning is approved.
2. The system shall record a new revision with an incremented revision number and revision date.
3. The system shall preserve:
   - Original approved plan baseline
   - Revised plan version
   - Identity of the officer who prepared the revision
   - Justification / reason for the revision
   - Formal approval history of the revision
   - Itemized delta: changed items, previous quantities, revised quantities, previous amounts, revised amounts.  
*Status*: `CONFIRMED BY CLIENT / VERSIONING DETAILS TO BE CONFIRMED`

### BR-010: Plan Version Association
Each plan-linked requisition shall retain a historical reference to the approved procurement-plan version under which it was submitted, and that historical association shall not be silently changed.  
*Status*: `PROPOSED / TO BE CONFIRMED`

---

## 4. Item Catalogue & Requisition Rules

### BR-011: Standard Item Catalogue Usage
Requisitions shall use standardized item descriptions from the approved item catalogue to prevent uncontrolled text descriptions from obstructing institutional demand consolidation.

### BR-012: Segregation of Preparation and Approval
An officer who prepares an item requisition shall not be the sole approving authority for that requisition, unless specifically permitted for designated single-officer administrative units.

---

## 5. Demand Consolidation Rules

### BR-013: Dual Institutional Consolidation View
Consolidation of common items across planning entities shall produce both:
1. **The Institutional Total**: University-wide aggregated quantity for bulk sourcing.
2. **The Source Breakdown**: Granular breakdown showing each contributing planning entity, campus, requester, and individual requisition reference number.

### BR-014: Non-Erasure of Provenance
The consolidation process shall **never** erase or strip away:
- Planning entity identity
- Campus location
- Requesting officer
- Original requisition reference number
- Approval timestamps and history

---

## 6. Budget Commitment & Audit Protection

### BR-015: Mandatory Budget Commitment Check
No requisition shall be released to the Directorate of Procurement for purchasing or tendering without prior Budget Commitment Authorization from the Directorate of Finance, where required by university workflow.

### BR-016: Protection of Audit Records
All critical financial, approval, rejection, plan revision, and administrative transactions shall generate audit logs. Audit records shall be protected from unauthorized modification or deletion.

### BR-017: Configurable Master Data
Organizational structures (including the approximately 62 planning entities), campuses, and approval routes must be configurable and never hard-coded into software logic. The authoritative 62 entity records must come directly from University data.
