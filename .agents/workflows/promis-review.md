---
description: Multi-dimensional quality gate and code review checklist for PROMIS.
---

# PROMIS Code Review & Quality Gate Workflow

## Purpose

This workflow defines the **Final Quality Gate** for PROMIS. Before declaring any coding, refactoring, or bug-fix task complete, review the code systematically across all 11 dimensions defined below.

---

## The 11-Dimension Quality Gate Checklist

### 1. Architecture
- [ ] Is the code placed in the correct architectural layer (Presentation, Business Logic, Data Access, Security, or Shared)?
- [ ] Are views free of database queries and business rule computations?
- [ ] Is the Core PHP code clean, modular, and adhering to strict typing where appropriate?

### 2. Business Logic & Institutional Process
- [ ] Does the implementation strictly adhere to the approved USTED procurement process?
- [ ] Are procurement plans and operational requisitions kept distinct?
- [ ] Are quantity constraints (planned vs. requested vs. remaining) properly tracked and enforced?

### 3. Security
- [ ] Are all state-changing endpoints protected by CSRF validation?
- [ ] Are permissions verified strictly on the server (zero trust in client-side states)?
- [ ] Is all dynamic output passed through `htmlspecialchars()` to prevent XSS?
- [ ] Are file uploads strictly validated by MIME type, size, and extension, with non-executable storage?
- [ ] Are secrets, passwords, and sensitive config kept out of version control?

### 4. Database & Queries
- [ ] Are all SQL queries parameterized using PDO prepared statements (zero string interpolation)?
- [ ] Are multi-table mutations wrapped in PDO database transactions with proper rollback handling?
- [ ] Are list queries paginated with server-side `LIMIT` and `OFFSET`?
- [ ] Have N+1 query loops and `SELECT *` patterns been eliminated?

### 5. Workflow & State Machine
- [ ] Can any approval, budget commitment, or submission state transition be bypassed?
- [ ] Are all transition rules validated against the record's current state before saving changes?
- [ ] Are actions permanently recorded in the immutable audit trail?

### 6. UI/UX
- [ ] Does the screen answer: *Where am I? What am I looking at? What should I do next?*
- [ ] Is there exactly **one dominant primary CTA** per major view?
- [ ] Are all five essential UI states handled (Loading, Empty, Success, Error, Validation)?
- [ ] Does success feedback provide reference number, current status, and next step?

### 7. Human-Computer Interaction (HCI)
- [ ] Has cognitive load been minimized using progressive disclosure and Miller's Law (chunking)?
- [ ] Are related labels, controls, and error messages grouped by Gestalt proximity?
- [ ] Are form fields logically organized, short, and free of redundant queries for known user data?

### 8. Mobile & Responsive Design
- [ ] Does the layout adapt fluidly across Mobile, Tablet, and Desktop using Tailwind responsive utilities?
- [ ] Are touch targets large enough to tap easily without accidental mis-clicks?
- [ ] Are tabular datasets handled responsively (priority columns, horizontal scroll, or stacked cards)?

### 9. Accessibility
- [ ] Is status indicated by text + iconography in addition to color?
- [ ] Does text meet WCAG AA contrast against backgrounds?
- [ ] Are interactive controls reachable and operable via keyboard with clear focus rings?

### 10. Maintainability
- [ ] Can another engineer understand the code quickly without confusion?
- [ ] Are variable and function names descriptive and consistent with surrounding code?
- [ ] Have no unauthorized frameworks or heavy libraries been introduced?

### 11. Regression Prevention
- [ ] Have all dependent endpoints and screens been checked for unintended side effects?
- [ ] Does the change represent the **smallest safe modification**?

---

## Next Step

If all checks pass, proceed to final validation under [/promis-test](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-test.md).
