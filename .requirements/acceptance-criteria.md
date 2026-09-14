# PROMIS Requirements: Acceptance Criteria Catalogue

## Overview

This document specifies testable, verification-ready **Given-When-Then** acceptance criteria for the core functional requirements in PROMIS.

---

## 1. Authentication & Security

### AC-AUTH-01: Valid & Invalid User Authentication (FR-001)
- **Scenario A: Successful Login**
  - **Given**: A registered, active user with valid credentials.
  - **When**: The user submits valid username/email and password.
  - **Then**: PROMIS authenticates the user, regenerates the session ID, sets secure cookie flags, and redirects to the appropriate role dashboard.
- **Scenario B: Failed Login**
  - **Given**: An unknown username or incorrect password.
  - **When**: The user attempts to authenticate.
  - **Then**: PROMIS rejects the login with a sanitized message (*"Invalid credentials"*), logs the failure, and preserves no session state.

### AC-AUTH-02: Server-Side Permission Enforcement (FR-002)
- **Given**: A user logged in with standard `Requester` permissions.
- **When**: The user attempts to execute a `POST` to an approval or budget authorization endpoint.
- **Then**: The server rejects the request with HTTP 403 Forbidden, records an audit event, and modifies no database state.

---

## 2. Procurement Planning & Approval

### AC-PLN-01: Procurement Plan Creation & Submission (FR-007, FR-010)
- **Given**: An authorized Planning Officer associated with an active planning entity.
- **When**: The officer creates a plan, enters valid items from the standard catalogue, specifies quantities and estimated costs, and clicks "Submit Plan".
- **Then**: PROMIS validates that required fields are non-empty, sets status to `Pending Approval`, assigns a unique plan reference, and sends the plan to the designated Approver's queue.

### AC-PLN-05: Procurement Plan Approval (FR-011)
- **Given**: A plan in `Pending Approval` status and an authenticated Approving Officer (e.g. Dean/Director).
- **When**: The Approving Officer reviews the plan items and clicks "Approve".
- **Then**: PROMIS updates status to `Approved`, records the approving officer ID and timestamp, locks the plan items, and creates Version 1.0 in the plan repository.

---

## 3. Quarterly Plan Review & Revision (FR-049, FR-050)

### AC-REV-01: Quarterly Procurement Plan Review Outcomes (FR-049)
- **Scenario A: Review Concludes with No Change**
  - **Given**: An approved procurement plan (Version 1.0) and an active quarterly review cycle.
  - **When**: The authorized officer reviews current drawdowns, determines no adjustments are needed, and certifies the review.
  - **Then**: PROMIS logs the review completion in the audit trail, records the outcome as "No change", and maintains Version 1.0 as the active baseline without creating a new version.
- **Scenario B: Review Concludes with Revision Required**
  - **Given**: An approved procurement plan (Version 1.0) and an active quarterly review cycle.
  - **When**: The authorized officer identifies required adjustments to quantities, items, or budget allocations and triggers a revision.
  - **Then**: PROMIS initializes a draft revision, creates a staging environment for changes, and leaves Version 1.0 active for existing operations until the revision is formally approved.

### AC-REV-02: Preserving Plan Version History on Revision (FR-050)
- **Given**: An approved procurement plan (Version 1.0) undergoing quarterly review where revision is required.
- **When**: The Planning Officer updates item quantities, adds a newly required item, provides a mandatory revision justification, and the revision is formally approved.
- **Then**:
  1. PROMIS increments the active plan version to Version 2.0.
  2. The previous Version 1.0 remains intact, fully retrievable, and marked as historical baseline. Historical approved plan versions are **not** overwritten once versioning is approved.
  3. The system records an itemized variance delta (previous quantity vs. new quantity; previous estimated amount vs. new estimated amount).
  4. The revision author, approval history, revision reason, and timestamp are permanently recorded in the audit trail.

---

## 4. Requisitions & Plan Quantity Tracking

### AC-REQ-01: Electronic Requisition Creation (FR-017)
- **Given**: An authenticated user assigned to an active planning entity.
- **When**: The user creates a new requisition, selects items from the standard catalogue, enters quantities and justifications, and clicks "Submit".
- **Then**: PROMIS validates all inputs, generates a unique requisition number (e.g. `PR-2026-00125`), sets status to `Submitted`, and routes the requisition to the Head of Department.

### AC-REQ-03: Tracking Remaining Plan Quantities (FR-018, FR-019)
- **Given**: An approved plan item with an approved planned quantity of 50 units, and previous approved requisitions totaling 30 units.
- **When**: A user creates a new requisition for 15 units of this item.
- **Then**: PROMIS calculates:
  $$\text{Approved: } 50 \quad|\quad \text{Previously Requested: } 30 \quad|\quad \text{Current: } 15 \quad|\quad \text{Remaining Balance: } 5$$
  and displays this calculation clearly to the user.

### AC-REQ-08: Plan Version Association on Requisition (FR-051)
- **Given**: An active approved procurement plan at Version 1.0.
- **When**: A user prepares and submits a plan-linked requisition.
- **Then**:
  1. The requisition captures and retains a historical reference to the approved procurement-plan version (Version 1.0) under which it was submitted.
  2. When the procurement plan subsequently undergoes quarterly review and is revised to Version 2.0, that historical association shall not be silently changed.

### AC-REQ-05: Over-Plan Request Detection (FR-021)
- **Given**: An approved plan item with 5 units of remaining balance.
- **When**: A user attempts to submit a requisition for 10 units.
- **Then**: PROMIS detects the 5-unit excess, flags the request as exceeding the approved plan, and blocks submission or prompts for an approved supplementary variation.

---

## 5. Requisition Approval & Budget Commitment

### AC-APP-01: Requisition Approval (FR-024)
- **Given**: A submitted requisition in `Pending Approval` state and an authenticated Approving Officer.
- **When**: The officer reviews the requisition and clicks "Approve".
- **Then**: PROMIS transitions status to `Pending Budget Commitment Authorization`, records the approval timestamp and officer ID, and adds the requisition to Finance's commitment queue.

### AC-APP-02: Requisition Return with Query (FR-025)
- **Given**: A submitted requisition in `Pending Approval` state.
- **When**: The approver clicks "Return for Correction" and enters mandatory query remarks.
- **Then**: PROMIS sets status to `Returned`, unlocks the record for the preparer, and logs the query remarks in the workflow history.

### AC-BDG-02: Budget Commitment Authorization (FR-028, FR-029)
- **Given**: An approved requisition in `Pending Budget Commitment Authorization` and an authenticated Finance Officer.
- **When**: The Finance Officer validates budget availability, enters a vote reference code, and clicks "Authorize Commitment".
- **Then**: PROMIS reserves the fund amount, sets status to `Commitment Authorized`, assigns a unique commitment reference, and makes the requisition available to the Directorate of Procurement.

---

## 6. Demand Consolidation & Handover

### AC-CNS-01: Demand Consolidation with Full Provenance (FR-030, FR-031)
- **Given**: Multiple commitment-authorized requisitions for identical catalogue items (e.g. Laptops) from ICT (20 units, Main Campus) and Finance (15 units, City Campus).
- **When**: A Procurement Officer runs the consolidation process for the period.
- **Then**: PROMIS generates a consolidated package showing:
  - **Institutional Total**: 35 Laptops
  - **Source Breakdown**: ICT Directorate = 20 units (Main Campus); Directorate of Finance = 15 units (City Campus)
  - All original requisition references, requester identities, and approval histories remain linked and retrievable.

---

## 7. Audit Trail Protection (FR-039)

### AC-AUD-01: Audit Record Protection & Integrity
- **Given**: A completed administrative or financial transaction (e.g. requisition approval or plan revision).
- **When**: Any user (including administrators or departmental heads) interacts with the system.
- **Then**:
  1. The audit record is permanently written capturing User ID, Action, Record Type, ID, Previous State, New State, and Timestamp.
  2. No user interface or standard application route exists to edit, modify, or delete the audit record.
  3. Attempting direct modification via unauthorized API calls is rejected.
