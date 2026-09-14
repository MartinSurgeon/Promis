---
description: Application structure, 5-tier separation of concerns, and component boundaries for PROMIS.
---

# PROMIS Architecture Workflow

## Purpose

This workflow defines the architectural rules and layer boundaries for PROMIS. PROMIS uses a clean, modular **Core PHP** architecture emphasizing separation of concerns, reusability, and simplicity without unnecessary heavyweight frameworks.

---

## 1. The 5-Layer Separation Rule

Every piece of code in PROMIS belongs to one of five distinct layers. Keep these responsibilities strictly separated:

```text
┌─────────────────────────────────────────────────────────────┐
│ 1. Presentation Layer                                       │
│    PHP views, Semantic HTML5, Tailwind CSS, UI components   │
├─────────────────────────────────────────────────────────────┤
│ 2. Business Logic Layer                                     │
│    Workflow state machines, validation rules, domain logic  │
├─────────────────────────────────────────────────────────────┤
│ 3. Data Access Layer                                        │
│    PDO prepared statements, repository functions, schemas   │
├─────────────────────────────────────────────────────────────┤
│ 4. Security Layer                                           │
│    Authentication checks, RBAC authorization, CSRF, XSS     │
├─────────────────────────────────────────────────────────────┤
│ 5. Shared Functionality Layer                               │
│    Formatting helpers, notification utilities, shared UI    │
└─────────────────────────────────────────────────────────────┘
```

### 1. Presentation Layer
- **Components**: PHP view templates, HTML templates, Tailwind styling, front-end visual states.
- **Responsibilities**: Render data passed from the backend, present status and forms clearly to users.
- **Restrictions**: Never contain database queries or complex domain rules.

### 2. Business Logic Layer
- **Components**: Service functions or service classes, domain validation rules.
- **Responsibilities**: Enforce institutional policies (e.g. quantity limits, approval state transitions, requisition thresholds).
- **Restrictions**: Must be independent of presentation rendering; can be invoked from both Web views and API controllers.

### 3. Data Access Layer
- **Components**: PDO database connection wrappers, repository functions, data-mapping helpers.
- **Responsibilities**: Execute parameterized SQL queries, handle transactions, return structured arrays/objects.
- **Restrictions**: Must never handle presentation or direct HTTP request/response payloads.

### 4. Security Layer
- **Components**: Session authentication guards, role-based authorization helpers, CSRF verification middleware/functions, input sanitizers.
- **Responsibilities**: Ensure the current session is active, verify user privileges against the requested action and target record state, validate tokens.
- **Restrictions**: Must execute server-side; never delegate security decisions to client-side logic.

### 5. Shared Functionality Layer
- **Components**: String formatters (e.g. currency, dates), UI alert banners, pagination renderers, audit loggers.
- **Responsibilities**: Provide consistent, reusable utilities across modules.

---

## 2. Architectural Anti-Patterns (Strictly Disallowed)

1. **No Business Logic in Views or JavaScript**:
   - Do not compute institutional rules, budget eligibility, or approval states in HTML files or JS scripts.
2. **No Client-Only Authorization**:
   - Never rely on hiding buttons or disabled HTML inputs to prevent unauthorized actions. Always re-verify permissions on the server.
3. **No Raw SQL in Templates**:
   - Never execute `PDO::query` or `prepare` directly inside presentation views. Retrieve data beforehand in a controller or data access function.
4. **No Code Duplication**:
   - Do not copy-paste identical SQL queries, status badge components, or calculation routines across multiple files. Extract into shared helpers.

---

## 3. Related Workflows
- Refer to [/promis-backend](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-backend.md) for Core PHP implementation details.
- Refer to [/promis-database](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-database.md) for data access layer rules.
- Refer to [/promis-security](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-security.md) for authentication and access control rules.
