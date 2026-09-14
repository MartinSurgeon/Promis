---
description: 12-step feature development lifecycle, structured pre-change planning, code generation standards, and edge case handling for PROMIS.
---

# PROMIS Feature Development Workflow

## Purpose

This workflow defines the standard end-to-end lifecycle for planning, implementing, and verifying features in PROMIS. It ensures all new code integrates seamlessly with existing architecture, data schemas, security policies, and UI conventions.

---

## 1. The 12-Step Feature Development Lifecycle

When tasked with introducing or expanding a feature in PROMIS, follow these 12 sequential steps:

```text
 1. Understand requirement
 2. Inspect project context
 3. Identify affected files
 4. Inspect database schema
 5. Identify roles and permissions
 6. Identify workflow & state impact
 7. Identify UI/UX and HCI requirements
 8. Plan changes (draft structured change plan)
 9. Implement smallest safe changes
10. Validate syntax, security, and edge cases
11. Test end-to-end user flows
12. Review against PROMIS quality gate
```

---

## 2. Structured Pre-Implementation Change Plan

Before writing code for any substantial change, produce a concise plan detailing every affected layer:

```text
CREATE:
- /path/to/new_file.php (Purpose)

MODIFY:
- /path/to/existing_file.php (Specific modifications)

DATABASE:
- Table: requisitions (Add column: committed_at TIMESTAMP NULL)
- Index: idx_requisition_status (status, department_id)

ROUTES / ENDPOINTS:
- POST /api/requisitions/commit.php

PERMISSIONS:
- Role required: BUDGET_COMMITMENT_OFFICER
- Allowed initial state: PENDING_COMMITMENT
```

---

## 3. Code Generation Standards

All newly generated or modified code must satisfy these criteria:

- **Modular**: Single responsibility per function/class; separate presentation, logic, and database access.
- **Readable & Maintainable**: Descriptive variable and function names, standard indentation, and clear comments on institutional business rules.
- **Consistent**: Adhere strictly to existing conventions in surrounding PROMIS files.
- **Focused**: Never rewrite an entire module or rewrite working code to add a minor feature. Make the smallest safe change.
- **Zero Framework Pollution**: Never import unauthorized external libraries or frameworks (no Laravel, React, Vue, jQuery, Bootstrap).

---

## 4. Edge Cases Checklist

Every feature must safely handle the following real-world operational conditions:

| Scenario | Defensive Strategy |
| :--- | :--- |
| **Empty Datasets** | Provide helpful empty state screens with direct call-to-action buttons. |
| **Invalid or Tampered IDs** | Validate ID format (e.g. positive integer or UUID); return HTTP 404 or 422 if record does not exist. |
| **Missing Records** | Handle `false` from PDO fetch calls gracefully without triggering PHP notices or crashes. |
| **Duplicate Submissions** | Disable submit buttons on first click; implement unique constraints and idempotent tokens. |
| **Expired Sessions** | Check session validity on API calls; return clear 401 response and redirect to login. |
| **Concurrent State Changes** | Use optimistic locking or verify record status inside database transaction before writing. |
| **Invalid State Transitions** | Validate that record's current state permits the action (e.g. cannot approve a `Draft`). |
| **Database Failures** | Wrap multi-query mutations in PDO transactions; rollback cleanly on failure. |
| **Network / API Timeouts** | Provide catch handlers in Fetch API and display retry options. |

---

## 5. Related Workflows
- Refer to [/promis-understand](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-understand.md) for initial codebase inspection.
- Refer to [/promis-review](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-review.md) for pre-merge validation.
- Refer to [/promis-test](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-test.md) for the complete Definition of Done.
