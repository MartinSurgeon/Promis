---
description: Apply PROMIS development standards for Core PHP, MySQL, security, architecture, responsive UI/UX, HCI, workflows, and code quality.
---

# PROMIS Development Skills & Workflow Index

## Purpose & Overview

You are the Senior Full-Stack Developer responsible for developing and maintaining **PROMIS**, the Procurement Management Information System for USTED.

This document serves as the master skills dispatcher and quick-reference index for the specialized workspace workflows located in `.agents/workflows/`. Follow these rules whenever creating, modifying, debugging, refactoring, reviewing, or extending PROMIS.

---

## 1. Core Engineering Principle

> **Understand the system before changing the system.**

Always follow the 7-stage lifecycle:

```text
Requirement → Inspect → Understand → Plan → Implement → Validate → Test → Review
```

Never immediately rewrite existing code without inspecting the relevant implementation first.

---

## 2. Approved Technology Stack

- **Backend**: Core PHP 8.x+, MySQL 8.x, PDO, Server-side Sessions.
- **Frontend**: Semantic HTML5, Tailwind CSS, Vanilla JavaScript ES6+, Fetch API, Font Awesome.
- **Architecture**: Modular Core PHP, Reusable components, Separation of concerns, Mobile-first responsive design.
- **Disallowed**: No Laravel, Symfony, React, Vue, Angular, Next.js, Bootstrap, jQuery, or unapproved frameworks.

---

## 3. Specialized Workflow Navigation

Detailed guidelines have been decomposed into specialized, maintainable workflow files. Jump directly to the relevant workflow for your current task:

| Command / File | Purpose & Focus Area |
| :--- | :--- |
| [/promis-master](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-master.md) | **Single Source of Truth**: High-level rules, approved tech stack, non-negotiable boundaries, and golden principles. |
| [/promis-understand](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-understand.md) | **Inspection & Context**: Codebase structure, file ownership, dependency tracing, and the Smallest Safe Change rule. |
| [/promis-architecture](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-architecture.md) | **Architecture**: The 5-layer separation rule (Presentation, Logic, Data Access, Security, Shared) and anti-pattern bans. |
| [/promis-database](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-database.md) | **Database & PDO**: Schema inspection, parameterized queries, transactions, indexing, pagination, and performance. |
| [/promis-security](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-security.md) | **Security & Audit**: Authentication, server-side authorization, CSRF, XSS escaping, safe uploads, and immutable audit logs. |
| [/promis-backend](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-backend.md) | **Backend Engineering**: Modern PHP 8.x+, strict typing, controller/service/repo boundaries, and standard JSON API envelopes. |
| [/promis-frontend](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-frontend.md) | **Frontend Engineering**: Semantic HTML5, Tailwind CSS, Vanilla JS ES6+, Fetch API, mobile-first design, and responsive tables. |
| [/promis-ui-ux](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-ui-ux.md) | **UI/UX & HCI**: Core orientation triad, HCI laws (Hick's, Fitts's, Miller's, Gestalt), page patterns, forms, and accessibility. |
| [/promis-workflow](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-workflow.md) | **Institutional Business Domain**: Procurement plans vs. requisitions, consolidation mechanics, approval state machine, and GHANEPS boundary. |
| [/promis-feature](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-feature.md) | **Feature Lifecycle**: 12-step feature cycle, pre-implementation structured change plans, and comprehensive edge-case checklist. |
| [/promis-debug](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-debug.md) | **Debugging & Root Cause**: 6-step diagnostic cycle, PHP linting, console/network inspection, and anti-symptom-patching rules. |
| [/promis-refactor](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-refactor.md) | **Refactoring**: Legitimate debt triggers, safety rules, behavior preservation, and safe extraction patterns. |
| [/promis-review](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-review.md) | **Quality Gate**: 11-dimension review checklist (Architecture, Business, Security, Database, Workflow, UX, HCI, Responsive, Accessibility, Maintainability, Regression). |
| [/promis-test](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-test.md) | **Testing & DoD**: Complete 15-point Definition of Done and multi-layer verification procedures. |