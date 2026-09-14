---
description: Comprehensive security standards, authentication, authorization, CSRF, XSS, file uploads, secrets, and auditability for PROMIS.
---

# PROMIS Security & Auditability Workflow

## Purpose

This workflow defines mandatory security controls and audit logging requirements for PROMIS. Because PROMIS handles institutional public procurement, budgets, approvals, and commitments for USTED, security and audit integrity are non-negotiable.

---

## 1. Authentication Standards

- **Password Storage**: Never store plaintext passwords. Always use `password_hash($password, PASSWORD_DEFAULT)` and verify with `password_verify($password, $hash)`.
- **Session Security**:
  - Store sessions server-side.
  - Configure session cookie flags: `HttpOnly`, `SameSite=Lax` (or `Strict`), and `Secure` (in HTTPS environments).
  - Regenerate session IDs on privilege changes or login using `session_regenerate_id(true)` to prevent session fixation attacks.
  - Invalidate and destroy sessions upon logout or timeout.

---

## 2. Server-Side Authorization

- **Zero Client Trust**: Never trust client-provided roles, hidden form fields, disabled buttons, or JavaScript checks.
- **Server-Side Authorization and Role/Permission Enforcement**:
  Every state-changing operation must validate:
  1. **Actor Permission**: Does the logged-in user possess the specific role or permission required?
  2. **Record State Integrity**: Is the target record in a status that legally permits this action?
- **Office Separation**: Ensure users can only access records belonging to their assigned department or planning entity, unless they hold institution-wide authority (e.g. Director, Budget Authorization, Procurement Officer).

---

## 3. CSRF Protection

- All state-changing HTTP requests (POST, PUT, DELETE) must require a cryptographically secure CSRF token:
  ```php
  // Generating token
  if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  
  // Validating token
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
      http_response_code(403);
      exit(json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']));
  }
  ```
- Embed CSRF tokens in forms via hidden inputs and provide them in headers (`X-CSRF-Token`) for Fetch API calls.

---

## 4. XSS Mitigation & Input Validation

- **Output Escaping**: Escape all dynamic user or database output rendered in HTML templates using:
  ```php
  echo htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
  ```
- **Input Validation**: Validate all incoming parameters (types, length, numeric ranges, allowed enum values) on the server before processing.

---

## 5. Secure File Upload Handling

Procurement often involves supporting specifications, memos, or quotations:
- **Validate Everything**: Inspect file size (`$_FILES['file']['size']`), error codes (`UPLOAD_ERR_OK`), file extension whitelist (`.pdf`, `.docx`, `.xlsx`, `.png`, `.jpg`), and verified MIME type via `finfo_file()`.
- **Never Trust Original Names**: Rename uploaded files to random strings (e.g. UUIDs or hash + timestamp).
- **Prevent Execution**: Store uploaded files in a dedicated storage directory with script execution disabled (`.htaccess` or outside the public web root).

---

## 6. Secrets & Error Handling

- **No Hardcoded Secrets**: Store database credentials and encryption keys in external configuration files or environment variables; never commit credentials into version control.
- **Safe User Messaging**: End-users must only see friendly, sanitized error messages (e.g., *"Unable to process request. Please contact the administrator."*).
- **Diagnostic Logging**: Write stack traces, SQL errors, and server paths to secure PHP system logs. Never echo them into the browser.

---

## 7. Institutional Auditability

Every important workflow action must be permanently recorded in an immutable audit log:

### Required Audit Fields
- **User ID**: Identifier of the user taking the action.
- **Action**: Standardized action code.
- **Record**: Record type and target record ID (e.g. `REQUISITION`, `1042`).
- **Previous State / Value**: State prior to the event (e.g. `PENDING_DIRECTOR_APPROVAL`).
- **New State / Value**: State following the event (e.g. `APPROVED`).
- **Date & Time**: Exact timestamp (`NOW()`).
- **Outcome**: `SUCCESS` or `FAILURE` with reason.

### Standard Action Codes
```text
REQUEST_CREATED
REQUEST_SUBMITTED
REQUEST_APPROVED
REQUEST_REJECTED
REQUEST_RETURNED
COMMITMENT_AUTHORIZED
REQUEST_CANCELLED
```

### Audit Immutability Rule
Ordinary users and departmental staff must **never** possess database permissions or application routes to alter, edit, or delete audit trail records.
