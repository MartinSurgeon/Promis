---
description: Controlled, safe architectural refactoring, behavior preservation, and code quality improvement for PROMIS.
---

# PROMIS Refactoring Workflow

## Purpose

This workflow defines the standards for refactoring code in PROMIS. Refactoring must improve readability, maintainability, performance, or security without altering intended external behavior.

---

## 1. Legitimate Refactoring Triggers

Refactor code only when one or more of these clear technical debts are present:

1. **Duplicate Logic**: Identical calculation routines, authorization checks, or validation rules copied across multiple scripts.
2. **Monolithic Files**: Single PHP files or scripts exceeding reasonable bounds (e.g. 500+ lines mixing queries, HTML, and processing).
3. **Giant Functions**: Procedures attempting to perform validation, database mutation, email dispatch, and HTML rendering all in one block.
4. **Unsafe Legacy Patterns**: Code using string concatenation in queries, raw `$_POST` without validation, or missing CSRF checks.
5. **Repeated SQL Queries**: Repetitive queries that could be consolidated into a repository method or optimized with a single `JOIN`.
6. **Poor Separation of Concerns**: Presentation templates containing direct database queries or business policy logic.

---

## 2. Refactoring Safety Rules

- **Preserve Intended Behavior**: Ensure inputs and outputs remain 100% compatible. Refactoring changes *structure*, not *external behavior*.
- **Smallest Safe Improvement**: Do not attempt a multi-file rewrite all at once. Extract one helper or method at a time.
- **Dependency Inspection**: Check every caller before renaming functions, changing parameter order, or moving files.
- **Validate Immediately**: Run syntax checks and verify dependent pages after each incremental extraction.
- **No Scope Creep**: Never refactor unrelated files or modules while fixing a specific component.

---

## 3. Common Safe Refactoring Patterns in PROMIS

### Pattern A: Extracting Duplicate Queries into a Repository
*Before*: Multiple endpoints executing the exact same `SELECT ... FROM departments` query.  
*After*: Create a centralized function `getDepartments(PDO $pdo): array` in a shared repository file.

### Pattern B: Separating Business Logic from View Templates
*Before*: A `.php` view file that queries the database, calculates remaining budget, and outputs HTML.  
*After*:
1. Controller/Page script fetches data via repository.
2. Domain service computes budget balances.
3. Clean view template receives computed variables and renders semantic HTML + Tailwind.

### Pattern C: Extracting Reusable UI Components
*Before*: 30 lines of identical status badge markup copy-pasted across 5 tables.  
*After*: Create a shared helper function `renderStatusBadge(string $status): string` returning standardized Tailwind markup.

---

## 4. Next Step

After refactoring, verify that all criteria in [/promis-test](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-test.md) and [/promis-review](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-review.md) remain satisfied.
