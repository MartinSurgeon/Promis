---
description: Definition of Done (DoD), verification methodology, and multi-layer test checklists for PROMIS.
---

# PROMIS Testing & Verification Workflow

## Purpose

This workflow defines the **Definition of Done (DoD)** and the testing methodology for PROMIS. No task or feature is marked complete until all verification gates in this workflow have been satisfied.

---

## 1. The PROMIS Definition of Done (DoD)

A feature or modification is complete **only when all 15 conditions are fulfilled**:

```text
 1. Business logic works completely and accurately
 2. Database operations work (prepared statements, transactions, indexes)
 3. Security controls work (CSRF, XSS escaping, safe file handling)
 4. Server-side authorization works (strict role and state validation)
 5. Workflow state machine rules work (proper transitions, no bypassing)
 6. Auditability works where required (immutable audit log entry created)
 7. Responsive UI works across all standard screen sizes
 8. Mobile UI works with comfortable touch targets
 9. Primary action is visually obvious and dominant
10. Navigation is predictable and shallow
11. Forms are understandable, chunked, and short
12. Validation works (both server-side and client-side inline feedback)
13. All 5 essential UI states are handled (Loading, Empty, Success, Error, Validation)
14. Accessibility standards are considered (contrast, keyboard focus, icon+text)
15. No regression is introduced to existing functionality
```

---

## 2. Multi-Layer Testing Checklist

Before delivering any work, execute these checks:

### A. Syntax & Static Linting
- Run PHP syntax checks on every modified PHP file:
  ```bash
  php -l path/to/changed_file.php
  ```
- Ensure no PHP notices, warnings, or deprecation messages appear in logs.

### B. Functional & Workflow Verification
- **Draft Creation**: Can the preparer create and save a draft requisition?
- **Submission**: Does submitting correctly advance status to `Submitted` / `Pending Approval`?
- **Approvals**: Does the Approving Officer's action advance the record to `Approved` or reject/return it with mandatory comments?
- **Budget Commitment**: Does the Finance Officer's authorization allocate budget and transition the record to `Commitment Authorized`?
- **Quantity Limits**: Attempting to requisition more than the remaining planned quantity is blocked.

### C. Security & Permission Tests
- **Role Isolation**: Log in as an unauthorized role (e.g. general staff) and attempt to access an approval endpoint; verify HTTP 403 Forbidden is returned.
- **CSRF Defense**: Send a POST request with an invalid or omitted CSRF token; verify the request is rejected immediately.
- **XSS Escaping**: Submit test data containing `<script>alert(1)</script>` or `"><img src=x onerror=alert(1)>`; verify all view outputs render literal text via `htmlspecialchars()`.

### D. Responsive & Viewport Testing
- **Mobile Viewport (375px–640px)**:
  - Verify layout collapses into a single column.
  - Verify tables scroll horizontally or display stacked card summaries.
  - Verify touch buttons are easily tappable.
- **Tablet Viewport (768px–1024px)**:
  - Verify multi-column grid layouts align cleanly.
- **Desktop Viewport (1280px+)**:
  - Verify generous whitespace, clear typography, and complete navigation panels.

### E. UI State Simulation
- **Empty State**: Verify appropriate empty graphics and "Create" CTAs appear when test tables have zero rows.
- **Loading State**: Check that buttons disable and display loading spinners during network requests.
- **Error Feedback**: Simulate network or validation failure; verify friendly error banners appear without exposing internal system paths or stack traces.

---

## 3. Completion Sign-Off

Once all items in the Definition of Done pass, document the verification results and summarize changes for the user.
