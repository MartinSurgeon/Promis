---
description: Human-Computer Interaction (HCI) laws, cognitive load optimization, page patterns, form design, accessibility, and dashboards for PROMIS.
---

# PROMIS UI/UX & HCI Standards Workflow

## Purpose

This workflow defines the Human-Computer Interaction (HCI), user experience, and visual design standards for PROMIS. Institutional enterprise tools must not feel clunky or overwhelming. Design must focus on the user's workflow, minimizing cognitive burden while maintaining speed, precision, and clarity.

---

## 1. The Core Orientation Triad

Every primary screen in PROMIS must immediately answer three fundamental questions for the user:

1. **Where am I?** (Clear page title, active navigation state, breadcrumbs).
2. **What am I looking at?** (Key record identifier, status badge, summary metrics).
3. **What should I do next?** (One obvious, unambiguous primary action).

---

## 2. HCI Laws & Principles in PROMIS

### One Primary Action (Dominant CTA)
- Each major view or section must feature **one clear primary action** (e.g. *"Submit Requisition"*, *"Authorize Commitment"*).
- De-emphasize secondary actions (e.g. outline or ghost buttons for *"Save Draft"*, *"Cancel"*).
- Place rare or destructive actions (*"Delete Draft"*, *"Reject"*) in separate menus or styled with distinct warning accents.

### Hick's Law (Decision Time)
- Minimize the number of concurrent choices presented to the user.
- Pre-filter options where possible and use progressive disclosure to avoid overwhelming users with massive drop-down lists.

### Fitts's Law (Target Reachability)
- Critical interactive elements must have generous clickable/tappable boundaries.
- On mobile devices, ensure thumb-friendly positioning for submit and approval actions.

### Jakob's Law (Familiar Conventions)
- Leverage familiar enterprise web UI patterns: top navigation, breadcrumb trails, data tables, modal dialogs, and slide-over panels.
- Do not reinvent standard UI interactions.

### Miller's Law (Information Chunking)
- Group complex information and form inputs into digestible clusters of 5–7 related items.

### Gestalt Laws of Proximity & Similarity
- **Proximity**: Keep input labels directly above their respective fields; keep helper text and validation errors adjacent to the control; cluster action buttons together.
- **Similarity**: Ensure all primary buttons share identical styling, all status badges adhere to the same color logic, and identical iconography represents identical concepts across the application.

### Serial Position Effect
- Place high-priority summary information at the top of detail screens; place confirmation and next-step actions at the bottom.

### Peak-End Rule
- Conclude workflows with an unambiguous resolution screen showing reference codes, timestamps, and next steps. PROMIS is an institutional system, not an e-commerce marketing site.

### Tesler's Law (Conservation of Complexity)
- Public procurement inherently possesses institutional complexity. Rather than eliminating necessary institutional controls, manage complexity through clear categorization, progressive disclosure, and logical workflow steps.

### Aesthetic-Usability Effect
- Maintain crisp typography, balanced spacing, cohesive Tailwind palettes, and neat borders. Clean aesthetics foster user confidence and reduce perceived difficulty.

---

## 3. Cognitive Load Management

- **Progressive Disclosure**: Show only what the user needs to complete the immediate step. Provide expandable accordions or modal drawers for deep technical specs.
- **Visual Hierarchy**: Use Tailwind font sizing (`text-2xl`, `text-lg`, `text-sm`) and weights (`font-semibold`, `font-medium`) to establish obvious content relationships.
- **Whitespace**: Utilize generous spacing (`p-6`, `gap-6`, `my-4`) to prevent screens from appearing cluttered.

---

## 4. Canonical Page Patterns

Inspect existing screens and follow these standardized layouts:

### A. List Page Pattern
```text
Page Title & Context
└── Primary Action Button (Top Right)
└── Summary Metric Cards (Total, Pending, Approved)
└── Search & Filter Bar (Status, Date Range, Department)
└── Data Table / Responsive Card List
└── Server-side Pagination Controls
```

### B. Detail Page Pattern
```text
Breadcrumb Navigation (Requisitions > PR-2026-00125)
└── Header: Record Title, Code & Prominent Status Badge
└── Key Highlights Grid (Requester, Entity, Budget Code, Total Amount)
└── Tabbed or Sectioned Line Items / Bill of Quantities
└── Attached Supporting Documents
└── Workflow Action Panel (Approve, Reject, Return, Commit)
└── Immutable Audit History Timeline
```

### C. Form Page Pattern
```text
Page Title & Brief Purpose Description
└── Step / Section 1: General Request Metadata (Auto-populated where known)
└── Step / Section 2: Requested Items & Quantities
└── Step / Section 3: Justification & Supporting Files
└── Validation Error Summary (if errors exist)
└── Bottom Action Bar: Primary Action + Secondary "Save Draft" / "Cancel"
```

---

## 5. Form Design Rules

- **Make Forms Feel Short**: Partition lengthy requisitions into logical cards or multi-step accordions.
- **Zero Redundant Inputs**: Never ask users to type information the system already knows (e.g. current user's name, email, department, or date).
- **Inline Validation**: Provide immediate visual feedback with clear error messaging adjacent to the affected input.

---

## 6. Accessibility & Contrast

- **Color Independence**: Never rely on color alone to convey status. Always combine colors with text and Font Awesome icons:
  - *Approved*: Green badge + text "Approved" + `<i class="fa-solid fa-check-circle"></i>`
  - *Pending*: Amber badge + text "Pending" + `<i class="fa-solid fa-clock"></i>`
  - *Rejected*: Red badge + text "Rejected" + `<i class="fa-solid fa-circle-xmark"></i>`
- **Contrast**: Ensure text complies with WCAG AA contrast standards against backgrounds.
- **Focus Rings**: Never remove `focus:outline-none` without providing Tailwind `focus:ring-2 focus:ring-blue-500` replacements for keyboard accessibility.

---

## 7. Action-Oriented Dashboards

PROMIS dashboards must prioritize urgent tasks over decorative charts:

```text
Priority Questions:
1. What needs my immediate review or action? (Pending Approvals Queue)
2. What is awaiting external authorization? (Pending Commitment)
3. What was recently updated or returned? (Recent Activity)
4. What primary action can I trigger right now? ("New Requisition")
```

Reserve charts for high-level management overviews; operational dashboards should focus on actionable work queues.
