# PROMIS UI/UX & Human-Computer Interaction (HCI) Architecture Specification

## 1. UI/UX Principles & Accessibility Standards

### 1.1 Core Design Philosophy
PROMIS is an enterprise public procurement system serving a diverse user base across the University—ranging from departmental planning officers preparing line-item budgets to high-level executives (Vice-Chancellor, Deans, Finance Director) reviewing and approving requisitions on mobile devices.

### 1.2 Accessibility Standard
- **Status**: `PROPOSED TECHNICAL STANDARD`
- **Standard**: **WCAG 2.1 Level AA Compliance** (Color contrast ratio >= 4.5:1 for normal text, full keyboard navigability, clear ARIA landmarks, screen-reader accessible forms, responsive zooming up to 200% without loss of content).

---

## 2. The Universal Orientation Triad
To prevent cognitive disorientation across ~62 planning entities and complex multi-stage approvals, **every single screen in PROMIS must continuously satisfy the Orientation Triad**:

```
+------------------------------------------------------------------------------------------------------+
|  PROMIS | [Active Entity: School of Engineering]   [User: Prof. J. Mensah | Role: Dean / Approver]   |
+------------------------------------------------------------------------------------------------------+
|  Dashboard > Requisitions > REQ-2026-0042                                                            |
|  STATUS: PENDING DEAN ENDORSEMENT                                        [ ACTION REQUIRED BY YOU ]  |
+------------------------------------------------------------------------------------------------------+
```

1. **Where Am I? (Entity & Location Context)**:
   - Displays current breadcrumb hierarchy and active Planning Entity (e.g., *Faculty of Science > Department of Physics*).
   - If an officer oversees multiple units, the active entity switcher is explicitly visible with the active context highlighted.
2. **Who Am I? (Identity & Role Context)**:
   - Displays logged-in user name, active institutional role, and permission scope in the persistent top utility bar.
3. **What Is The State? (Lifecycle Status & Next Action)**:
   - Every document header (Plan, Requisition, Consolidation Batch) features a high-visibility semantic status badge (`Draft`, `Submitted`, `Approved`, `Revision Required`).
   - Clear banner indicating whether immediate action is required by the current user.

---

## 3. Human-Computer Interaction (HCI) Cognitive Laws

### 3.1 Hick's Law (Reducing Decision Time)
- Complex procurement planning forms with dozens of fields are broken down into logical step-by-step chunks (e.g., *1. Entity & Category Setup* -> *2. Item Line Entry* -> *3. Quarterly Schedule* -> *4. Review & Submission*).
- Approval screens present primary action buttons prominently (`Approve`, `Return for Correction`, `Reject`) with secondary options tucked into structured menus.

### 3.2 Fitts's Law (Touch & Click Target Optimization)
- Primary call-to-action buttons (e.g., `Submit Requisition`, `Approve Plan`) have a minimum touch target size of 44x44 pixels and are positioned in predictable, accessible screen regions.
- Dangerous or irreversible actions (e.g., `Reject Requisition`, `Delete Draft Item`) are visually differentiated with destructive styling and require explicit confirmation modals with clear rationale inputs.

### 3.3 Miller's Law (Working Memory Chunking)
- Requisition tables and plan listings chunk complex data into readable groups (Summary metrics cards at top, tabbed groupings by Procurement Category: *Goods*, *Works*, *Services*, *Consulting*).
- Long lists of items default to 15–25 items per page with clear pagination, sorting, and inline search.

### 3.4 Jakob's Law (Familiar Enterprise Mental Models)
- Standardized, familiar web interaction patterns: recognizable search filters, tabular data presentation, breadcrumb navigation, and modal dialogs.
- Avoids unconventional UI gimmicks or hidden navigation bars.

---

## 4. Mandatory 5 UI States for Dynamic Screens
Every view, data table, and asynchronous form interaction in PROMIS must explicitly architect and handle five distinct UI states:

```
[1. Empty State]        -> No items found / First-time user onboarding
[2. Loading State]      -> Skeleton screens / Non-blocking spinners during Fetch
[3. Success State]      -> Inline toast notification / Form confirmation banner
[4. Validation Warning] -> Field-level error messages / Pre-submission warnings
[5. Error State]        -> Graceful recovery UI / Clear institutional contact info
```

1. **Empty State**:
   - Clean, friendly visual illustration or icon explaining that no records exist yet.
   - Includes a clear call-to-action (e.g., *"No procurement plan created for FY 2026. Click 'Create Draft Plan' to begin."*).
2. **Loading State**:
   - Subtle, non-blocking skeleton loaders matching table or card layouts.
   - Action buttons show disabled state with spinner during form submission to prevent duplicate clicks.
3. **Success State**:
   - Clear confirmation banner or toast message confirming state transition (e.g., *"Requisition REQ-2026-0042 submitted for Head of Department approval."*).
4. **Validation / Warning State**:
   - Immediate inline field validation before submission.
   - Prominent balance warning if a requested quantity approaches or exceeds available plan allocations.
5. **Error / System Failure State**:
   - Graceful, secure error presentation.
   - Explains what happened without exposing raw database errors or stack traces, providing clear guidance on next steps.

---

## 5. Requisition Balance Visualization Pattern

To ensure complete transparency and prevent over-requisitioning, every requisition item form and approval review screen displays the **Requisition Quantity Calculation Model**:

```
+------------------------------------------------------------------------------------------------------+
| ITEM: Dell Latitude Laptops (Core i7, 16GB RAM)                                                     |
| Linked Plan Item: FY 2026 Annual Plan (v1.0) - Line #14                                             |
+------------------------------------------------------------------------------------------------------+
| [Approved Planned Qty]   [Previously Requested Qty]   [Remaining Before]   [Current Request Qty]     |
|         50 units                  20 units                  30 units              10 units           |
+------------------------------------------------------------------------------------------------------+
|  --> Remaining After Current Request (if approved): 20 units                                         |
|  [ Status: WITHIN APPROVED ALLOCATION ]                                                              |
+------------------------------------------------------------------------------------------------------+
```

- **Visual Indicators**:
  - Green indicator when `Current Request Quantity <= Remaining Before Current Request`.
  - Amber warning when request consumes the entire remaining balance (`Remaining After = 0`).
  - Red blocked indicator if `Current Request Quantity > Remaining Before Current Request`, with descriptive prompt for the user.

---

## 6. Responsive Layout & Mobile-First Strategy

### 6.1 Device Archetypes
- **Desktop (>= 1024px)**:
  - Optimized for data-dense tasks: Departmental Planning Officers creating detailed multi-line annual plans, Procurement Directorate officers managing institutional consolidation batches and GHANEPS exports.
  - Multi-column layouts, sticky filter sidebars, comprehensive data tables.
- **Tablet & Mobile (< 1024px, down to 360px)**:
  - Specifically optimized for **Executive Approvers** (Vice-Chancellor, Pro-Vice Chancellors, Deans, Directors, Heads of Department).
  - Streamlined approval cards: Document overview, summary total, plan item balance check, attached justification memos, and prominent single-tap `Approve`, `Return`, or `Reject` actions.
  - Responsive tables automatically collapse into expandable item summary cards on narrow screens.

---

## 7. Design Tokens & Styling Foundation
- **Color Hierarchy**:
  - Primary / Brand: Deep Academic Navy (`#1E3A8A`) conveying institutional authority and stability.
  - Success: Forest Emerald (`#059669`) for approved and active states.
  - Warning: Warm Amber (`#D97706`) for pending reviews and balance alerts.
  - Danger / Error: Crimson Red (`#DC2626`) for rejections and balance overruns.
  - Neutral / Background: Clean Slate & Light Cool Gray (`#F8FAFC`, `#E2E8F0`) with crisp typography.
- **Typography**: Clean, accessible sans-serif system font stack (`Inter`, system-ui, -apple-system, sans-serif) for legibility at small sizes.
