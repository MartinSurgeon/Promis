---
description: Root-cause diagnosis, error reproduction, layer-specific troubleshooting, and verification for PROMIS.
---

# PROMIS Debugging & Root-Cause Analysis Workflow

## Purpose

This workflow provides a systematic approach for diagnosing, debugging, and resolving defects in PROMIS. Developers must never guess or apply superficial "band-aids" to suppress errors; fixes must address the root cause while preserving system integrity.

---

## 1. The 6-Step Debugging Cycle

When investigating any issue or error, follow this sequence:

```text
1. Reproduce       ──> Consistently trigger the issue with known inputs/roles
2. Identify Root   ──> Isolate the exact failing line, query, or logic flaw
3. Trace Impact    ──> Check callers, database relationships, and dependents
4. Fix Root Cause  ──> Implement the minimal, robust, and permanent correction
5. Validate Fix    ──> Confirm the bug no longer occurs under identical conditions
6. Check Regression──> Ensure surrounding workflows and features remain unbroken
```

---

## 2. Layer-Specific Diagnostic Techniques

### A. PHP & Server-Side Diagnosis
- **Syntax Checks**: Before running or testing changed PHP scripts, execute a syntax lint check:
  ```bash
  php -l path/to/file.php
  ```
- **Error Logs**: Inspect the PHP error log (e.g. `C:\xampp\php\logs\php_error_log` or configured log file) rather than guessing.
- **Strict Typing & Warnings**: Look for type mismatch errors, undefined array keys, or null-pointer exceptions caused by PHP 8.x+ strictness.
- **Never Suppress with `@`**: Never use error suppression operators (`@file_get_contents`, etc.). Handle errors explicitly.

### B. JavaScript & Client-Side Diagnosis
- **Browser Console**: Check for uncaught JS errors, syntax issues, or missing script imports.
- **Network Tab**: Inspect Fetch/XHR requests:
  - Verify the HTTP status code (200 vs 400, 403, 422, 500).
  - Inspect the JSON response payload sent by the server.
  - Check request headers to ensure the `X-CSRF-Token` was properly sent.
- **DOM & Event Listeners**: Verify element IDs, query selectors, and event listeners are properly attached without duplicate bindings.

### C. SQL & Database Diagnosis
- **Query Verification**: Test failing SQL queries directly in MySQL client / phpMyAdmin with mock parameters.
- **Constraint Violations**: Check if failures are caused by foreign key mismatch, missing NOT NULL fields, or duplicate unique keys.
- **Parameter Types**: Ensure PDO binding types match database column types (e.g. `PDO::PARAM_INT` for integer IDs).

---

## 3. Strict Prohibitions

- **NEVER guess at fixes**: Always identify the verifiable root cause before writing code.
- **NEVER mask errors**: Do not wrap a broken function in an empty `try/catch` block simply to silence warnings.
- **NEVER modify unrelated files**: If a bug appears in requisition approval, do not alter unrelated styling or authentication scripts to make the symptom disappear.

---

## 4. Next Step

Once a fix is implemented and validated, run the complete quality gate in [/promis-review](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-review.md).
