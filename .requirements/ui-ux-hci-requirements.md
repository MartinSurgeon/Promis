# PROMIS Requirements: UI/UX & Human-Computer Interaction (HCI)

## 1. UX Philosophy & Core Orientation Triad

PROMIS is designed around the operational tasks of university personnel, not raw database tables. Every primary screen must answer three fundamental questions within three seconds:

```text
1. Where am I?        (Clear breadcrumb trail, page heading, active nav highlight)
2. What am I looking at? (Record reference, current status badge, key metadata summary)
3. What should I do next? (Exactly ONE visually dominant primary action button)
```

---

## 2. Formal HCI Laws Applied to PROMIS

### 1. One Primary Action Rule (Dominant CTA)
- Every major screen or card shall feature exactly **one dominant call-to-action** styled with primary visual weight (e.g. *"Submit Requisition"*, *"Authorize Commitment"*).
- Secondary actions (e.g. *"Save Draft"*, *"Cancel"*) must use muted styles (ghost or outline buttons).
- Destructive or rare actions (*"Reject"*, *"Delete Draft"*) must be separated or styled with warning tones.

### 2. Hick's Law (Decision Latency)
- Minimize concurrent choices presented to users.
- Use pre-filtered selections and progressive disclosure to prevent overwhelming users with massive lists.

### 3. Fitts's Law (Target Ergonomics)
- Critical interactive targets (buttons, pagination numbers, checkboxes) must have comfortable click and tap bounding boxes (minimum 44×44px touch area on mobile).

### 4. Jakob's Law (Familiarity of Interface)
- Utilize familiar enterprise web patterns: top/sidebar navigation, tabular data with sortable headers, filter bars, standard modal dialogs, and slide-over preview sheets.

### 5. Miller's Law (Chunking Complexity)
- Organize extensive forms and record displays into logical clusters of 5 to 7 items (e.g. Entity Info → Line Items → Supporting Justification).

### 6. Gestalt Laws of Proximity & Similarity
- **Proximity**: Keep form field labels directly above input fields; place validation errors adjacent to the invalid input; cluster action buttons together.
- **Similarity**: Ensure all primary actions share identical styling, all status badges share identical visual logic, and identical icons convey identical meanings across all screens.

### 7. Serial Position Effect
- Position critical summary indicators (Total Amount, Current Status, Reference Code) at the top of detail pages; position final submission and next-step actions at the bottom.

### 8. Peak-End Rule
- Conclude workflows with an informative confirmation state that communicates:
  1. What happened (*"Requisition submitted successfully."*)
  2. Reference number (*"PR-2026-00125"*)
  3. Current status (*"Pending Director Approval"*)
  4. Next expected step (*"Next: In queue for Director review"*)

### 9. Tesler's Law (Conservation of Complexity)
- Public procurement possesses irreducible statutory complexity. Manage this complexity through logical step-by-step structuring and progressive disclosure, rather than cutting out mandatory controls.

### 10. Aesthetic-Usability Effect
- Maintain crisp typography, harmonious Tailwind spacing (`p-6`, `gap-6`), neat card borders, and clear contrast. Clean aesthetics inspire trust and lower perceived task difficulty.

---

## 3. Cognitive Load Management

- **Progressive Disclosure**: Show high-level summaries by default; provide expandable accordions or modal drawers for deep technical specifications.
- **Visual Hierarchy**: Establish unmistakable typographic scale using Tailwind weights and sizes (`text-2xl font-bold`, `text-lg font-semibold`, `text-sm text-slate-500`).
- **Whitespace**: Provide generous margins and paddings to prevent screens from appearing visually crowded.

---

## 4. Canonical Page Patterns

### Pattern A: List / Directory Page
```text
Header: Page Title + Context Description + Primary Action Button (Top Right)
├── Summary Metric Cards (Total Records, Pending Action, Approved, Completed)
├── Filter & Search Bar (Keyword, Planning Entity, Campus, Status, Date Range)
├── Data Table (or Stacked Cards on Mobile)
└── Server-Side Pagination Bar
```

### Pattern B: Detail / Review Page
```text
Breadcrumb (Requisitions > PR-2026-00125)
├── Header: Title, Reference Code, Prominent Multi-Modal Status Badge
├── Metric Grid (Requester, Entity, Campus, Fiscal Period, Estimated Total)
├── Tabbed / Sectioned Line Items & Specifications
├── Supporting Memos & Attachments Panel
├── Approval Action Bar (Approve, Query/Return, Reject, Authorize)
└── Timeline: Protected Audit Trail
```

### Pattern C: Form / Creation Page
```text
Header: Form Purpose & Concise Helper Text
├── Section 1: Entity & Request Metadata (Auto-populated where known)
├── Section 2: Standard Item Selection & Quantity Entry
├── Section 3: Justification & File Uploads
├── Inline Validation Message Areas
└── Bottom Bar: Primary CTA + Secondary Action ("Save Draft" / "Cancel")
```

---

## 5. Form Design Rules

- **Short Form Ergonomics**: Chunk long requisition forms into digestible sections or accordions.
- **Zero Redundant Data Entry**: Never prompt the user to manually enter data the system already knows (e.g. user's own name, staff ID, assigned department, today's date).
- **Inline Validation**: Trigger immediate, clear error feedback underneath invalid inputs upon blur or submit attempt.

---

## 6. The 5 Mandatory UI States

Every data-driven interface must explicitly handle and display five states:
1. **Loading**: Spinners or skeleton placeholders preventing double-submission.
2. **Empty**: Helpful illustration/icon, clear text (*"No requisitions found"*), and an immediate creation CTA button.
3. **Success**: Unambiguous confirmation displaying transaction reference, new status, and next step.
4. **Error**: Sanitized, clear banner explaining the issue with retry options.
5. **Validation**: Distinct red borders and explanatory text directly under affected inputs.

---

## 7. Accessibility & Mobile Responsiveness

- **Mobile-First Responsiveness**: All views must scale cleanly from mobile screens (375px) through tablets (768px) to desktop viewports (1280px+).
- **Responsive Tables**: Wide tabular data must utilize priority column visibility, horizontal scrolling wrappers, or card transformations on small screens.
- **Multi-Modal Status Indicators**: Status must never rely on color alone. Status badges must combine background tint, clear text, and Font Awesome iconography:
  - *Approved*: Green tint + Text "Approved" + `<i class="fa-solid fa-circle-check"></i>`
  - *Pending*: Amber tint + Text "Pending" + `<i class="fa-solid fa-clock"></i>`
  - *Rejected*: Red tint + Text "Rejected" + `<i class="fa-solid fa-circle-xmark"></i>`
- **Proposed Technical Standard**:
  > [!NOTE]
  > Full compliance with **WCAG AA** accessibility guidelines (contrast ratios, complete keyboard focus traps, ARIA markup) is treated as a **`PROPOSED TECHNICAL STANDARD`** pending explicit university institutional confirmation.
