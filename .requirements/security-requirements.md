# PROMIS Requirements: Security Architecture & Controls

## 1. Security Architecture Overview

PROMIS is an institutional system governing university funds, procurement approvals, and legal commitments for USTED. Security controls must be embedded throughout every layer of the architecture, adhering to the principle of defense-in-depth and zero client trust.

---

## 2. Authentication Specifications

### SEC-AUTH-001: Cryptographic Password Hashing
- User passwords shall never be stored in plaintext.
- Passwords must be hashed using robust algorithms (e.g. `password_hash()` with `PASSWORD_DEFAULT`).
- Password verification must use timing-safe comparison via `password_verify()`.

### SEC-AUTH-002: Secure Session Lifecycle
- Sessions must be stored server-side.
- Session cookies must be issued with flags: `HttpOnly` (mitigates script theft), `SameSite=Lax` or `Strict` (mitigates cross-site request forgery), and `Secure` (when HTTPS is deployed).
- Session IDs must be regenerated upon successful login (`session_regenerate_id(true)`) to mitigate session fixation attacks.
- Sessions must automatically invalidate following a defined period of inactivity.

---

## 3. Server-Side Authorization & Access Control

### SEC-AC-001: Zero Client Trust
The server shall never rely on client-side state, hidden HTML fields, disabled form inputs, or JavaScript logic to evaluate authorization. Every incoming request must be validated on the server.

### SEC-AC-002: Server-Side Authorization and Role/Permission Enforcement
Every state-changing transaction (e.g. submission, approval, revision, commitment) must satisfy server-side authorization and role/permission enforcement:
1. **Actor Permission**: Does the authenticated user hold the required institutional role and entity assignment?
2. **Record State Integrity**: Is the target record in an exact status that permits the requested transition? (e.g. An approver cannot approve a record that is in `Draft` or already `Approved`).

### SEC-AC-003: Entity Multi-Tenant Data Isolation
Users shall only access requisitions, plans, and budgets belonging to their explicitly assigned planning entities, unless granted institution-wide oversight rights (e.g. Director of Finance, Director of Procurement, Auditor).

---

## 4. Cross-Site Request Forgery (CSRF) Mitigation

### SEC-CSRF-001: Cryptographic CSRF Tokens
- All state-changing HTTP requests (`POST`, `PUT`, `DELETE`) must require a cryptographically secure, unpredictable CSRF token tied to the user's session.
- Tokens must be verified server-side using timing-safe comparison (`hash_equals()`).
- Requests lacking a valid CSRF token must be immediately rejected with HTTP 403 Forbidden.

---

## 5. Cross-Site Scripting (XSS) & Input Validation

### SEC-XSS-001: Context-Aware Output Escaping
All dynamic user data or database content rendered within HTML views must be escaped using:
```php
echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
```

### SEC-XSS-002: Strict Server-Side Input Sanitization
All incoming request parameters must be type-cast, sanitized, and validated against allowed whitelists (e.g. valid integer IDs, allowed status enums, positive numeric quantities).

---

## 6. Secure File Upload Architecture

Supporting memos, specifications, and quotations uploaded by users must be protected against malicious exploitation:

### SEC-UPL-001: Comprehensive Upload Validation
- Validate upload error flags (`UPLOAD_ERR_OK`).
- Enforce maximum upload file size limits.
- Enforce a strict file extension whitelist (`.pdf`, `.docx`, `.xlsx`, `.png`, `.jpg`).
- Verify actual file MIME types via `finfo_file()`, never relying solely on the client-supplied `Content-Type` header.

### SEC-UPL-002: Filesystem Storage Isolation
- Uploaded files must be stored in a dedicated directory outside the public web root, or within a directory where script execution (`.php`, `.phtml`, `.cgi`) is strictly prohibited.
- **Renaming Convention**: Storing uploaded files under randomized unique identifiers (such as UUIDs or random hashes) is a `PROPOSED IMPLEMENTATION APPROACH` to prevent path traversal attacks, filesystem overwrites, and filename encoding vulnerabilities.

---

## 7. Secrets Management & Diagnostic Error Masking

### SEC-OPS-001: Protection of Configuration Secrets
Database credentials, API keys, and secret tokens must reside in external environment configurations or protected config files. No secrets shall be committed to source code repositories.

### SEC-OPS-002: Sanitized User Error Responses
End-users must only see sanitized, safe error notifications (e.g. *"Unable to complete transaction. Please contact your system administrator."*). Internal SQL errors, stack traces, database schema names, and server paths must never be displayed in user interfaces.

### SEC-OPS-003: Secure Operational Logging
Detailed diagnostic traces and security exceptions must be written exclusively to secure server logs accessible only to authorized administrators.

---

## 8. Audit Record Protection

### SEC-AUD-001: Protection from Modification or Deletion
All workflow events, approval decisions, plan revisions (FR-050), and administrative changes must be recorded in an audit trail capturing User ID, Action Code, Target Record, Previous Value, New Value, IP Address, and Timestamp.  
**Audit records shall be protected from unauthorized modification or deletion.** Ordinary system users, department heads, and operational staff must possess no application interface or privilege to alter, edit, or purge audit trail records.
