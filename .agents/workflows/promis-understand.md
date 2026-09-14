---
description: Project context inspection, file ownership discovery, and dependency analysis for PROMIS.
---

# PROMIS Project Understanding & Context Inspection Workflow

## Purpose

This workflow guides the inspection of the PROMIS codebase before modifying or adding any functionality. In accordance with [/promis-master](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-master.md):

> **Understand the system before changing the system.**

---

## 1. Context Inspection Checklist

Before modifying existing functionality or introducing new components, systematically inspect:

1. **Directory Tree & Structure**:
   - Locate where related features, services, templates, and assets currently live.
   - Respect established folder hierarchies (e.g. `api/`, `views/`, `services/`, `components/`).

2. **PHP Files & Execution Flow**:
   - Identify which entry point script handles the request.
   - Trace included or required dependencies, autoloaders, and session configurations.

3. **Routes and Endpoints**:
   - Map URL patterns and request methods (GET, POST).
   - Check if an API endpoint or a page view controller handles the transaction.

4. **Database Schema & Tables**:
   - Inspect table definitions, primary keys, foreign keys, and indexes.
   - Note required vs. optional fields, status enums, and audit columns (`created_at`, `updated_at`, `user_id`).

5. **JavaScript Modules & Events**:
   - Trace client-side event listeners, fetch calls, and DOM manipulation scripts.
   - Ensure you do not break existing JS bindings or CSRF header passing.

6. **Tailwind & UI Components**:
   - Review existing component markup to match visual styling, color palette, card patterns, and typography.
   - Avoid creating custom CSS or deviating from existing design tokens.

7. **Authentication & Authorization**:
   - Identify session checks, role definitions, and access gates guarding the feature.

8. **Workflow & Lifecycle States**:
   - Check what record state is required for this action (e.g. `Draft`, `Submitted`, `Approved`).
   - Identify what state the record will transition into upon completion.

9. **Existing Naming Conventions**:
   - Match function names, variable naming (camelCase vs snake_case), database columns, and file naming in the surrounding module.

---

## 2. Core Diagnostic Questions

Always answer these six questions before drafting code:

1. **What file owns this feature?**  
   Locate the specific controller, view, or service responsible.
2. **What depends on it?**  
   Identify all scripts, APIs, or UI elements that consume this file or its outputs.
3. **What database tables are involved?**  
   Verify table schema, constraints, and relationships directly.
4. **Which institutional role can perform the action?**  
   Verify whether the action requires Departmental Head, Director, Budget Officer, or Procurement Officer rights.
5. **What state is the record currently in?**  
   Ensure the current lifecycle status permits the requested action.
6. **Can an existing component or query be reused?**  
   Check for existing helpers, UI cards, modals, or repository methods.

---

## 3. The Smallest Safe Change Rule

- **Make the smallest safe change** that completely and robustly achieves the objective.
- **Never rewrite unrelated code** or restructure working files without explicit necessity.
- If an existing function works as intended, build alongside or reuse it rather than refactoring without cause.

---

## 4. Next Step

After completing context inspection, proceed to:
- [/promis-architecture](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-architecture.md) to ensure proper layer placement, or
- [/promis-feature](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-feature.md) to draft the structured change plan.
