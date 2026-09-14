# PROMIS Architecture Specification: Security Architecture

## 1. Security Architecture Overview

PROMIS handles statutory public procurement, departmental vote balances, and financial authorizations for USTED. The security architecture enforces a **Defense-in-Depth** model with zero client trust.

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                       PROMIS DEFENSE-IN-DEPTH LAYERS                        │
│                                                                             │
│  [1. TRANSPORT SECURITY]      HTTPS / TLS Encryption                        │
│  [2. SESSION SECURITY]        HttpOnly, SameSite Cookies, ID Regeneration   │
│  [3. APPLICATION SECURITY]    CSRF Token Verification on all POST/PUT/DELETE│
│  [4. ACCESS CONTROL]          Server-Side Authorization & RBAC Enforcement  │
│  [5. INPUT / OUTPUT]          Whitelisting, Sanitization, htmlspecialchars  │
│  [6. DATA ACCESS]             PDO Prepared Parameterized Statements         │
│  [7. AUDIT PROTECTION]        Protected from Unauthorized Edit or Deletion  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Authentication & Session Architecture

### Authentication Mechanism
- Passwords shall never be stored in plaintext. Passwords are encrypted using robust one-way cryptographic hashing via `password_hash()` using default secure algorithms (e.g. Bcrypt/Argon2id).
- Password validation uses timing-attack-safe comparison via `password_verify()`.

### Session Protection & Lifecycle
- Sessions are maintained server-side.
- Session cookies are issued with mandatory flags:
  - `HttpOnly`: Prevents client-side script access, neutralizing cookie theft via XSS.
  - `SameSite=Lax` (or `Strict`): Defends against cross-site request forgery.
  - `Secure`: Enforced in production under HTTPS.
- **Session Fixation Defense**: The session ID is immediately regenerated (`session_regenerate_id(true)`) upon successful authentication.
- **Session Expiration**: Automatic session timeout terminates inactive sessions after a configured idle window.

---

## 3. Server-Side Authorization & Role/Permission Enforcement

In accordance with system constraints, authorization is architected as **server-side authorization and role/permission enforcement**:

### Zero Client Trust Architecture
- The server never relies on client-provided parameters, hidden input fields, disabled buttons, or JavaScript validation to determine access.
- Every state-changing request (submission, approval, commitment, revision, export) executes a mandatory server-side authorization guard prior to invoking business logic.

### Contextual Evaluation
The authorization service verifies:
1. **Authenticated Identity**: User identity verified against active server session.
2. **Organizational Scope**: User holds an active role assignment within the target planning entity.
3. **Action Permission**: User's role contains the specific atomic permission key required.
4. **Record State Integrity**: The target record's current state permits the requested operation.

*Note: No two-factor authentication (2FA) is assumed or implied, as 2FA has not been explicitly approved.*

---

## 4. CSRF & XSS Mitigation

### CSRF Defense
- All state-changing HTTP requests (`POST`, `PUT`, `DELETE`) require a cryptographically random, per-session CSRF token.
- Tokens are verified server-side using timing-safe comparison (`hash_equals()`). Requests with invalid or missing tokens are immediately aborted with HTTP 403 Forbidden.

### XSS Mitigation
- All dynamic data rendered in HTML templates must pass through context-aware output encoding:
  ```php
  echo htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
  ```
- Strict Content Security Policy (CSP) headers are recommended to disallow unauthorized inline scripts.

---

## 5. SQL Injection Prevention & Data Access

- All database queries interacting with user or external input are executed exclusively via **PDO prepared statements** with bound parameters.
- Direct string concatenation or interpolation of variables into SQL queries is strictly prohibited across all application layers.
- Obsolete MySQL APIs (`mysql_*`) are completely banned.

---

## 6. Secure File Upload Architecture

Procurement often requires uploading memos, specifications, and supplier quotes:
- **Validation**: Strict server-side verification of upload error codes, maximum file sizes, and whitelisted file extensions (`.pdf`, `.docx`, `.xlsx`, `.png`, `.jpg`).
- **MIME Verification**: MIME types must be verified inspecting file byte signatures via `finfo_file()`, ignoring client-supplied headers.
- **Execution Prevention**: Uploads are stored in a dedicated storage folder outside the public web root, or with script execution completely disabled via server configuration.
- **Proposed Implementation Approach**: Renaming uploaded files to randomized unique identifiers (such as UUIDs) is classified as a **`PROPOSED IMPLEMENTATION APPROACH`** to prevent directory traversal and filesystem collision attacks.

---

## 7. Secrets Management & Operational Error Handling

- **Secrets Isolation**: Database credentials, encryption keys, and environment settings are stored outside version-controlled code repositories in protected environment configuration files.
- **Error Masking**: End-users receive friendly, sanitized error notifications. Detailed diagnostic stack traces, database schema names, and server paths are written exclusively to secure server logs.
- **Audit Records Protection**: Audit records shall be protected from unauthorized modification or deletion.
