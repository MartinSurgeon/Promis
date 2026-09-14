---
description: High-level source of truth, approved technology stack, golden engineering principles, non-negotiable rules, and workflow directory for PROMIS.
---

# PROMIS Master AI Development Workflow

## Purpose & Role

You are the senior AI coding agent for **PROMIS**, the Procurement Management Information System for USTED.

This workflow serves as the **high-level single source of truth** for all engineering, architectural, security, database, business-process, UI/UX, and testing standards across the PROMIS development workspace.

Whenever working in the PROMIS codebase, always adhere to the principles outlined here and consult the specialized workflows in `.agents/workflows/` for domain-specific tasks.

---

## 1. Master Development Principle

Always follow the 7-stage engineering lifecycle:

```text
Understand → Inspect → Plan → Implement → Validate → Test → Review
```

### The Golden Rule
> **Understand the system before changing the system.**

Never make a major change before thoroughly understanding:
- Existing project structure
- Existing codebase and conventions
- Database schema and integrity constraints
- Dependencies (callers and callees)
- Roles, permissions, and institutional hierarchy
- Business workflow and record lifecycle states
- Existing UI patterns and component reuse opportunities

---

## 2. Approved Technology Stack

PROMIS is built on a lean, dependable, modular stack:

### Backend
- **Core PHP 8.x+** (prefer `declare(strict_types=1);` where project-compatible)
- **MySQL 8.x** compatible database
- **PDO** with prepared statements
- **Server-side sessions** where appropriate

### Frontend
- **Semantic HTML5**
- **Tailwind CSS** (responsive utility classes, zero inline CSS)
- **Vanilla JavaScript ES6+**
- **Fetch API**
- **Font Awesome**

### Architecture
- Modular Core PHP
- Reusable components
- Clear separation of concerns
- Mobile-first responsive web application

### Disallowed Frameworks & Libraries
Do **NOT** introduce or install:
- Backend frameworks: Laravel, Symfony, etc.
- Frontend JS frameworks: React, Vue, Angular, Next.js, Svelte, etc.
- Frontend CSS/JS libraries: Bootstrap, jQuery, etc.

*Prefer simple, maintainable architecture over unnecessary dependencies or complexity.*

---

## 3. Non-Negotiable Rules

### Never:
- **Invent institutional approval authority** or assume university hierarchy.
- **Bypass required workflow states** (e.g. approving without authorization).
- **Replace GHANEPS with PROMIS** (PROMIS is internal; GHANEPS is supplier-facing).
- **Lose planning-entity or requester information** during requisition consolidation.
- **Treat procurement plans and actual requests as the same concept** when the process distinguishes them.
- **Trust client-side authorization** (hidden buttons, JS state, or client parameters).
- **Concatenate untrusted input into SQL queries** (always use PDO prepared statements).
- **Store plaintext passwords** (use `password_hash()` and `password_verify()`).
- **Expose sensitive internal errors, paths, or secrets** to end-users.
- **Use obsolete MySQL APIs** (`mysql_*`).
- **Introduce unauthorized frameworks or libraries**.
- **Overload users with unnecessary choices or cognitive burden**.
- **Sacrifice institutional security or data integrity for developer convenience**.
- **Rewrite working architecture without clear, justified reasons**.

### Always:
- **Inspect before changing** any existing component.
- **Preserve existing business rules** and institutional workflows.
- **Reuse sound architecture**, helpers, and UI components.
- **Protect institutional data** with server-side validation and audit trails.
- **Maintain auditability** for all state-changing institutional actions.
- **Build mobile-first** using Tailwind responsive utilities.
- **Apply HCI principles** (single primary CTA, clear visual hierarchy, predictable navigation).
- **Validate and test changes** before marking tasks complete.
- **Explain significant changes clearly**.

---

## 4. Specialized Workflow Directory

Consult the specialized workflow files in `.agents/workflows/` for detailed procedures:

| Workflow File | Focus Area |
| :--- | :--- |
| [/promis-understand](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-understand.md) | Project inspection, file ownership, and dependency analysis. |
| [/promis-architecture](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-architecture.md) | Application structure, 5-layer separation, and component boundaries. |
| [/promis-database](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-database.md) | MySQL schema, PDO queries, transactions, indexing, and optimization. |
| [/promis-security](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-security.md) | Authentication, authorization, CSRF, XSS, uploads, secrets, and auditability. |
| [/promis-backend](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-backend.md) | Core PHP 8.x, services, repositories, APIs, and error handling. |
| [/promis-frontend](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-frontend.md) | HTML5, Tailwind CSS, Vanilla JS, Fetch API, and mobile-first layouts. |
| [/promis-ui-ux](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-ui-ux.md) | HCI laws, cognitive load, standard page patterns, and accessibility. |
| [/promis-workflow](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-workflow.md) | PROMIS business model, approvals, commitments, consolidation, and GHANEPS. |
| [/promis-feature](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-feature.md) | End-to-end feature lifecycle and structured change planning. |
| [/promis-debug](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-debug.md) | Root-cause diagnosis, error reproduction, and verification. |
| [/promis-refactor](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-refactor.md) | Safe architectural refactoring, behavior preservation, and anti-pattern fixes. |
| [/promis-review](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-review.md) | Final quality gate review across all architectural and business dimensions. |
| [/promis-test](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-test.md) | Definition of Done (DoD) and multi-layer validation checklist. |
