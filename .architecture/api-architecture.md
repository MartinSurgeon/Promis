# PROMIS API Architecture Specification

## 1. Architectural Status & Scope Classification

### 1.1 Architectural Status
- **RESTful Conventions**: `PROPOSED ARCHITECTURAL CONVENTION`
- **External Public API**: `PROPOSED LATER PHASE / NOT IN PHASE 1 SCOPE`
- **Internal Application Endpoints**: `CONFIRMED PHASE 1`

> [!IMPORTANT]
> RESTful API conventions are adopted as a proposed architectural convention for internal modularity and asynchronous UI interactions. They are not a confirmed business or client requirement. PROMIS Phase 1 is primarily a server-rendered web application with asynchronous AJAX/Fetch enhancements.

---

## 2. API Scope & Interaction Model

### 2.1 Internal Asynchronous Endpoints (Phase 1)
Internal API endpoints serve dynamic browser components (e.g., dynamic item lookups, requisition quantity balance recalculation, approval action modals, and dashboard updates):
- Utilizes browser `fetch()` API.
- Operates under the existing authenticated user session (Cookie-based session identifier).
- Protected by mandatory CSRF token verification.

### 2.2 External Integration APIs (Deferred)
- Direct API access for third-party systems (e.g., external ERPs, GHANEPS live web services) is deferred to future phases pending formal University IT security standards and vendor agreements.

---

## 3. Communication Standards & Payload Architecture (Proposed Convention)

### 3.1 Content Negotiation & Encoding
- **Request Format**: JSON (`Content-Type: application/json`) for complex payloads, or standard URL-encoded form data (`application/x-www-form-urlencoded`) for standard form submissions.
- **Response Format**: Exclusively JSON (`Content-Type: application/json; charset=utf-8`) for API routes.

### 3.2 Standardized JSON Envelope Format
All internal API responses adhere to a consistent response envelope:

#### Success Response Envelope
```json
{
  "status": "success",
  "data": {
    "requisition_id": "REQ-2026-0042",
    "workflow_status": "SUBMITTED",
    "remaining_before": 150,
    "current_request": 30,
    "remaining_after": 120
  },
  "message": "Requisition submitted successfully.",
  "meta": {
    "timestamp": "2026-09-11T16:45:00Z",
    "version": "1.0"
  }
}
```

#### Error Response Envelope
```json
{
  "status": "error",
  "message": "The requested procurement request exceeds approved plan allocations.",
  "errors": [
    {
      "field": "quantity",
      "code": "EXCEEDS_APPROVED_PLAN_BALANCE",
      "detail": "Approved Planned Quantity: 100, Previously Requested: 90. Maximum available: 10."
    }
  ],
  "meta": {
    "timestamp": "2026-09-11T16:45:00Z"
  }
}
```

---

## 4. HTTP Method & Status Code Conventions (Proposed)

### 4.1 Method Conventions
- `GET`: Retrieve resource or collection representation (idempotent, safe).
- `POST`: Create a new resource or execute a state-changing workflow transition.
- `PUT` / `PATCH`: Update an existing draft resource or modify attributes.
- `DELETE`: Cancel or remove an eligible draft entity (hard deletion prohibited for submitted/approved records).

### 4.2 Standard HTTP Status Codes
| HTTP Status Code | Meaning | Architectural Application |
| :--- | :--- | :--- |
| `200 OK` | Request succeeded | Successful retrieval, calculation, or status query |
| `201 Created` | Resource created | Successful creation of draft plan or requisition |
| `400 Bad Request` | Invalid format or syntax | Malformed JSON payload or missing parameters |
| `401 Unauthorized` | Unauthenticated | Missing, expired, or invalid session |
| `403 Forbidden` | Access Denied | User lacks role permission or planning entity access |
| `404 Not Found` | Resource not found | Invalid entity ID, plan ID, or requisition ID |
| `409 Conflict` | Concurrency conflict | Optimistic locking collision or plan review state lock |
| `422 Unprocessable`| Business validation error | Requisition exceeds balance, validation rule failure |
| `500 Server Error` | Unhandled system fault | Internal server exception (sanitized in production) |

---

## 5. Security & Session Handling on API Endpoints

### 5.1 Session & State Management
- API calls rely on the native PHP HTTP session cookie (`PHPSESSID`).
- Cookie flags enforced: `HttpOnly`, `Secure` (over HTTPS), `SameSite=Strict`.
- No separate token generation (JWT or API keys) is required for internal Phase 1 operations.

### 5.2 Cross-Site Request Forgery (CSRF) Prevention
- Every state-modifying API request (`POST`, `PUT`, `DELETE`) must supply a valid CSRF token.
- Sent via custom HTTP header: `X-CSRF-Token: <token_string>` or in the request body.
- Verified server-side prior to invoking the Service Layer. Requests failing CSRF verification are terminated immediately with `403 Forbidden`.

### 5.3 Server-Side Authorization Enforcement
- API controllers resolve the active user and planning entity from the secure session.
- Client-supplied IDs in URLs or bodies are strictly validated against the user's entity ownership and role authorization table.
