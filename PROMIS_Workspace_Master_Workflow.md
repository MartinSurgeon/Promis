# PROMIS Workspace AI Development Master Workflow

## Purpose

You are the senior AI coding agent for PROMIS, the Procurement Management Information System for USTED.

This file is the **single source of truth** for the PROMIS development workspace. Use it to understand the complete engineering, business-process, security, UI/UX, HCI, testing, and maintenance rules.

Your first responsibility is not to code. It is to understand the project and arrange these rules into maintainable workspace workflows.

---

# 1. FIRST ACTION: ARRANGE THIS MASTER FILE

Inspect the current workspace before changing anything.

Create or update:

```text
.agents/
└── workflows/
    ├── promis-master.md
    ├── promis-understand.md
    ├── promis-architecture.md
    ├── promis-database.md
    ├── promis-security.md
    ├── promis-backend.md
    ├── promis-frontend.md
    ├── promis-ui-ux.md
    ├── promis-workflow.md
    ├── promis-feature.md
    ├── promis-debug.md
    ├── promis-refactor.md
    ├── promis-review.md
    └── promis-test.md
```

Do not blindly duplicate this entire file into every workflow.

Extract the relevant rules into each specialized workflow while keeping `promis-master.md` as the high-level source of truth.

Preserve the meaning of the rules. Do not invent business rules.

If equivalent workflows already exist, inspect and improve them rather than creating duplicates.

---

# 2. PROMIS MASTER PRINCIPLE

Always:

**Understand → Inspect → Plan → Implement → Validate → Test → Review**

Never make a major change before understanding:

- Existing project structure
- Existing code
- Database schema
- Dependencies
- Roles and permissions
- Business workflow
- Existing UI patterns

Golden rule:

> **Understand the system before changing the system.**

---

# 3. APPROVED TECHNOLOGY STACK

Backend:
- Core PHP 8.x+
- MySQL 8.x compatible database
- PDO
- Server-side sessions where appropriate

Frontend:
- Semantic HTML5
- Tailwind CSS
- Vanilla JavaScript ES6+
- Fetch API
- Font Awesome

Architecture:
- Modular Core PHP
- Reusable components
- Separation of concerns
- Mobile-first responsive web application

Do not introduce React, Vue, Angular, Next.js, Laravel, Symfony, Bootstrap, jQuery, or unnecessary frameworks unless explicitly authorized.

Prefer simple, maintainable architecture over unnecessary complexity.

---

# 4. ARCHITECTURE RULES

Separate:

**Presentation**
→ PHP views, HTML, Tailwind

**Business Logic**
→ Services and business rules

**Data Access**
→ PDO queries/repositories

**Security**
→ Authentication, authorization, CSRF, validation

**Shared Functionality**
→ Helpers and reusable components

Do not place complex business logic inside views or JavaScript.

Do not place important authorization rules only in JavaScript.

Do not duplicate existing services, layouts, queries, or components without a valid reason.

---

# 5. PROJECT CONTEXT INSPECTION

Before modifying existing functionality, inspect:

- Directory tree
- PHP files
- Routes/endpoints
- Database schema
- JavaScript
- Tailwind/UI components
- Authentication
- Authorization
- Workflow states
- Existing naming conventions

Determine:

- What file owns this feature?
- What depends on it?
- What database tables are involved?
- Which role can perform the action?
- What state is the record currently in?
- Can an existing component be reused?

Make the smallest safe change.

Do not rewrite unrelated code.

---

# 6. PHP RULES

Use modern PHP 8.x+.

Prefer:

```php
declare(strict_types=1);
```

where compatible with the project.

Use PDO and prepared statements.

Never concatenate untrusted input into SQL.

Never use obsolete APIs such as:

```php
mysql_query()
mysql_connect()
mysql_fetch_array()
```

Use clear naming, sensible classes/functions, reasonable file sizes, and PSR-oriented coding practices where practical.

---

# 7. DATABASE RULES

Inspect the existing schema before making database changes.

Understand:

- Tables
- Primary keys
- Foreign keys
- Relationships
- Unique constraints
- Indexes
- Required fields
- Status fields
- Audit fields
- Timestamps

Avoid duplicate tables/columns.

Use transactions where multiple related operations must succeed together.

Use pagination for large datasets.

Avoid `SELECT *` where only specific fields are required.

Watch for:

- N+1 queries
- Missing indexes
- Repeated queries
- Queries inside large loops
- Unnecessary data retrieval

---

# 8. SECURITY RULES

Security is mandatory.

Authentication:
- Never store plaintext passwords.
- Use `password_hash()` and `password_verify()`.
- Protect sessions.
- Consider `session_regenerate_id(true)` after successful authentication.

Authorization:
- Enforce permissions server-side.
- Never trust hidden buttons, URLs, JavaScript, or client-provided roles/statuses.
- Validate both the user's permission and the record's current state.

CSRF:
- Protect authenticated state-changing operations.

XSS:
- Validate input.
- Escape dynamic HTML output using appropriate encoding such as `htmlspecialchars()`.

Uploads:
- Validate size, extension, MIME type, and upload errors.
- Never trust original filenames.
- Never allow uploaded files to become executable code.

Secrets:
- Never hardcode passwords, API keys, database credentials, or sensitive secrets.

Errors:
- Users receive safe, useful messages.
- Logs may contain diagnostic detail.
- Never expose credentials, SQL, stack traces, server paths, or sensitive configuration.

---

# 9. AUDITABILITY

PROMIS is an institutional system.

Important actions should be traceable.

Record appropriate:

- User
- Action
- Record
- Previous state/value
- New state/value
- Date/time
- Outcome

Examples:

```text
REQUEST_CREATED
REQUEST_SUBMITTED
REQUEST_APPROVED
REQUEST_REJECTED
REQUEST_RETURNED
COMMITMENT_AUTHORIZED
REQUEST_CANCELLED
```

Ordinary users must not be able to alter or delete important audit records.

---

# 10. PROMIS BUSINESS MODEL

Keep these concepts separate:

```text
Budget Allocation
Planning Entity
Procurement Plan
Approval
Item Request / Requisition
Consolidation
Commitment Authorization
Procurement Processing
Consumption Record
GHANEPS
```

A **Procurement Plan** represents what an entity intends to procure.

An **Item Request/Requisition** represents what the entity is actually requesting based on its approved plan.

Do not merge these concepts.

Track, where applicable:

```text
Approved Planned Quantity
Previously Requested Quantity
Current Request Quantity
Remaining Quantity
```

Do not allow uncontrolled requests above approved quantities unless an approved variation/exception workflow exists.

---

# 11. CONSOLIDATION RULES

PROMIS must provide both:

**Institutional total**

and

**Source/entity breakdown**

Example:

```text
Laptops = 85

ICT = 20
Finance = 15
Engineering = 30
Library = 20
```

Consolidation must never erase:

- Planning entity
- Requester
- Campus
- Department/unit
- Request reference
- Relevant status
- Approval history

Procurement must be able to see the institution-wide requirement and where each requirement came from.

---

# 12. BUSINESS WORKFLOW

The exact institutional approval hierarchy must come from approved University procedures.

Do not invent approval authority.

A configured workflow may look like:

```text
Draft
→ Submitted
→ Pending Approval
→ Approved
→ Pending Budget Commitment Authorization
→ Commitment Authorized
→ Sent to Procurement
→ Processing
```

Possible exception states include:

```text
Rejected
Returned
Cancelled
```

Only implement states that are supported by the actual business process.

For the specific office workflow already described:

```text
Departmental Head
→ Director Approval
→ Budget Commitment Authorization
→ Directorate of Procurement
```

For the Director's Office:

```text
Administrator prepares request
→ Director approves
→ Budget Commitment Authorization
→ Directorate of Procurement
```

Keep these responsibilities separate:

- Request preparer
- Requesting office
- Approving officer
- Budget authorization officer
- Procurement officer

Do not generalize this office-specific example to every University unit without confirmation.

---

# 13. GHANEPS BOUNDARY

PROMIS does not replace GHANEPS.

PROMIS is internal and supports planning, requisitioning, consolidation, internal approval, tracking, and institutional records.

GHANEPS remains the supplier-facing procurement platform where required for activities such as advertisement, tendering, bid submission, and related procurement stages.

PROMIS should feed better specified and quantified requirements into the appropriate downstream process.

---

# 14. FRONTEND RULES

Use:

- Semantic HTML5
- Tailwind CSS
- Vanilla JavaScript ES6+
- Fetch API
- Font Awesome

Do not use large amounts of inline CSS.

Use reusable components for repeated UI patterns.

Use semantic elements:

```html
<header>
<nav>
<main>
<section>
<form>
<table>
<footer>
```

---

# 15. MOBILE-FIRST RULE

Design for:

```text
Mobile → Tablet → Desktop → Large Desktop
```

Do not simply shrink a desktop layout.

Use Tailwind responsive utilities.

Important controls must be easy to tap.

Tables must use suitable responsive strategies such as:

- Priority columns
- Horizontal scrolling
- Responsive row/detail views
- Collapsible secondary information

Choose the method that best fits the data.

---

# 16. UI/UX AND HCI RULES

Design around the user's task, not the database.

Every major screen should make these clear:

1. Where am I?
2. What am I looking at?
3. What should I do next?

Apply:

**One primary action**
- One obvious primary CTA per major section.
- Limit competing CTAs.
- Put secondary/rare actions in appropriate menus.

**Hick's Law**
- Reduce unnecessary choices.

**Fitts's Law**
- Important controls must be large, reachable, and easy to tap.

**Jakob's Law**
- Use familiar website/application patterns for navigation, forms, cards, tables, dialogs, and feedback.

**Miller's Law**
- Break long choice lists into meaningful groups, often around 5–7 where practical.

**Gestalt Proximity**
- Keep related labels, inputs, explanations, status information, and actions together.

**Gestalt Similarity**
- Similar components must look and behave consistently.

**Serial Position Effect**
- Put important information near the beginning and useful next-step information near the end.

**Peak-End Rule**
- End important workflows with clear confirmation, reference, status, and next step. Do not turn internal PROMIS pages into marketing pages.

**Tesler's Law**
- Institutional complexity cannot always be removed. Organize it with grouping and progressive disclosure.

**Aesthetic-Usability Effect**
- Maintain polished spacing, hierarchy, consistency, and visual credibility.

---

# 17. COGNITIVE LOAD RULES

Prevent unnecessary overload.

Use:

- Clear headings
- Logical sections
- Short paragraphs
- Cards where useful
- Lists where useful
- Generous intentional whitespace
- Predictable shallow navigation
- Repeated page patterns
- Plain language

Do not give every action equal visual weight.

Do not show every available detail on the first screen.

Use progressive disclosure for complex information.

---

# 18. PAGE PATTERNS

List pages should generally follow:

```text
Page Title
→ Primary Action
→ Summary
→ Search / Filters
→ Data
→ Pagination
```

Detail pages:

```text
Breadcrumb
→ Record title/reference
→ Status
→ Key information
→ Details
→ Related information
→ Actions
→ History/Audit
```

Form pages:

```text
Page Title
→ Purpose/helper text
→ Grouped fields
→ Validation
→ Primary Action
→ Secondary Action
```

Inspect existing pages before introducing a new pattern.

---

# 19. FORMS

Make forms feel short.

Group logically:

```text
Request Information
Item Information
Supporting Information
```

Do not ask users to manually enter information the system already knows unless there is a valid reason.

Use:

- Clear labels
- Helpful input types
- Sensible defaults
- Controlled choices where required
- Search/autocomplete for large item lists
- Clear helper text
- Inline validation

---

# 20. REQUIRED UI STATES

Data-driven features must consider:

```text
Loading
Empty
Success
Error
Validation
```

Important workflow actions must explain:

- What happened
- Reference number, where applicable
- Current status
- Next expected step

Example:

```text
Request submitted successfully.

Reference: PR-2026-00125
Status: Pending Director Approval
Next Step: Director review
```

---

# 21. ACCESSIBILITY

Maintain accessible contrast.

Do not rely on color alone to communicate status.

Use text plus appropriate icons/color.

Provide:

- Labels
- Focus states
- Keyboard-accessible controls
- Meaningful button names
- ARIA labels where useful
- Accessible validation/error messages

---

# 22. DASHBOARDS

Dashboards must prioritize action over decoration.

Ask:

```text
What needs my attention?
What is pending?
What changed?
What action can I take?
```

Prefer useful metrics and workflow queues over decorative charts.

Role-focused information should be prioritized without changing server-side security.

---

# 23. API RULES

Use clear endpoint responsibilities.

Example:

```text
/api/requisitions/list.php
/api/requisitions/create.php
/api/requisitions/update.php
/api/requisitions/submit.php
/api/requisitions/approve.php
/api/requisitions/reject.php
```

Use predictable JSON responses:

```json
{
  "success": true,
  "message": "Request approved successfully.",
  "data": {}
}
```

Errors should return clear safe messages.

---

# 24. ERROR AND DEBUGGING WORKFLOW

Never guess.

Use:

```text
Reproduce
→ Identify root cause
→ Trace dependencies
→ Fix root cause
→ Validate
→ Check regression
```

PHP:
- Run syntax validation such as `php -l`.
- Inspect relevant logs.

JavaScript:
- Check browser console.
- Check network/Fetch responses.
- Check DOM selectors and events.

SQL:
- Verify tables, columns, parameters, relationships, and expected results.

Do not change unrelated files merely to make an error disappear.

---

# 25. REFACTORING

Refactor when there is a clear benefit:

- Duplicate logic
- Giant functions
- Giant files
- Unsafe legacy code
- Repeated SQL
- Poor separation of concerns

Rules:

- Preserve intended behavior.
- Make the smallest safe structural improvement.
- Inspect dependencies.
- Validate after the refactor.
- Do not refactor unrelated modules.

---

# 26. FEATURE DEVELOPMENT WORKFLOW

For a new feature:

```text
Understand requirement
→ Inspect project
→ Identify affected files
→ Inspect database
→ Identify roles/permissions
→ Identify workflow impact
→ Identify UI/UX requirements
→ Plan changes
→ Implement
→ Validate
→ Test
→ Review
```

Before implementation, produce a concise file-change plan when the change is substantial:

```text
CREATE
...

MODIFY
...

DATABASE
...

ROUTES
...

PERMISSIONS
...
```

---

# 27. CODE GENERATION RULES

Generated code must be:

- Secure
- Modular
- Readable
- Maintainable
- Reusable
- Testable
- Consistent with existing architecture

Do not create unnecessary files.

Do not duplicate components.

Do not rewrite the whole application when a focused change is sufficient.

---

# 28. EDGE CASES

Always consider:

- Empty datasets
- Invalid IDs
- Missing database records
- Duplicate submissions
- Expired sessions
- Concurrent changes
- Invalid state transitions
- Database failures
- Network/API failures
- Permission failures
- Partial transactions

Important actions should not execute twice accidentally.

Use transactions and server-side controls where appropriate.

---

# 29. DEVELOPMENT AND PRODUCTION

Development may use detailed debugging.

Production must:

- Hide internal errors
- Protect secrets
- Log securely
- Use safe error pages
- Protect sessions
- Use HTTPS
- Restrict administrative operations appropriately

---

# 30. DEFINITION OF DONE

A feature is complete only when:

```text
Business logic works
+
Database operations work
+
Security works
+
Authorization works
+
Workflow rules work
+
Auditability works where required
+
Responsive UI works
+
Mobile UI works
+
Primary action is obvious
+
Navigation is predictable
+
Forms are understandable
+
Validation works
+
Loading/success/error/empty states are handled
+
Accessibility is considered
+
Existing UI patterns are preserved
+
No obvious regression is introduced
```

---

# 31. FINAL QUALITY GATE

Before declaring any task complete, check:

Architecture:
Is the code in the correct layer?

Business:
Does it follow the actual PROMIS process?

Security:
Can an unauthorized user manipulate it?

Database:
Are queries safe, correct, and reasonably efficient?

Workflow:
Can an approval or authorization step be bypassed?

UI/UX:
Is the next correct action obvious?

HCI:
Have unnecessary decisions and cognitive load been reduced?

Responsive:
Does it work well on mobile, tablet, and desktop?

Accessibility:
Can users understand and operate it?

Maintainability:
Can another developer understand it?

Regression:
Could this change break existing functionality?

---

# 32. WORKFLOW ARRANGEMENT RULE

When splitting this master specification into the individual `.md` workflows:

`promis-master.md`
→ High-level rules, technology boundaries, golden principles, and cross-cutting rules.

`promis-understand.md`
→ Project inspection and dependency analysis.

`promis-architecture.md`
→ Application structure and separation of responsibilities.

`promis-database.md`
→ MySQL, PDO, schema, queries, integrity, indexes, transactions, optimization.

`promis-security.md`
→ Authentication, authorization, CSRF, XSS, sessions, uploads, secrets, audit security.

`promis-backend.md`
→ Core PHP, services, controllers, repositories, validation, APIs, error handling.

`promis-frontend.md`
→ HTML5, Tailwind, JavaScript, Fetch, Font Awesome, responsive implementation.

`promis-ui-ux.md`
→ UI/UX, HCI laws, cognitive load, page patterns, accessibility, responsive interaction.

`promis-workflow.md`
→ PROMIS business process, roles, approvals, requests, commitments, consolidation, GHANEPS boundary.

`promis-feature.md`
→ End-to-end feature implementation process.

`promis-debug.md`
→ Diagnosis and root-cause debugging.

`promis-refactor.md`
→ Safe architectural/code refactoring.

`promis-review.md`
→ Security, architecture, database, business, workflow, UX, accessibility, performance review.

`promis-test.md`
→ Functional, security, database, workflow, UI, responsive, regression, and acceptance validation.

Cross-reference the master rules instead of unnecessarily repeating large sections.

---

# 33. NON-NEGOTIABLE RULES

Never:

- Invent institutional approval authority.
- Bypass required workflow.
- Replace GHANEPS with PROMIS.
- Lose planning-entity/requester information during consolidation.
- Treat procurement plans and actual requests as the same concept when the process distinguishes them.
- Trust client-side authorization.
- Concatenate untrusted input into SQL.
- Store plaintext passwords.
- Expose sensitive errors or secrets.
- Use obsolete MySQL APIs.
- Introduce unauthorized frameworks.
- Overload users with unnecessary choices.
- Sacrifice security or data integrity for convenience.
- Rewrite working architecture without justification.

Always:

- Inspect before changing.
- Preserve existing business rules.
- Reuse sound architecture.
- Protect institutional data.
- Maintain auditability.
- Build mobile-first.
- Apply HCI principles.
- Validate and test changes.
- Explain significant changes clearly.

---

# FINAL PRINCIPLE

**Understand the system before changing the system.**

The AI agent is responsible for turning this single master specification into a clean, maintainable `.agents/workflows/` structure while preserving every important business, technical, security, UI/UX, and HCI requirement.
