# PROMIS Application Architecture Specification

## 1. Architectural Style & Technology Foundation

### 1.1 Architectural Pattern: 5-Tier Layered Architecture
PROMIS is structured using a strict **5-tier layered architectural pattern** designed for Core PHP 8.x. This enforces separation of concerns, testability, high maintainability, and strict decoupling of presentation from domain logic and data storage.

```
+-------------------------------------------------------------------------+
| Tier 1: Presentation Layer (Templates / Views / Client Assets)          |
| - Semantic HTML5, Vanilla JS (ES6+), Modern CSS                         |
| - Rendered server-side views & responsive UI components                 |
+------------------------------------+------------------------------------+
                                     | HTTP Requests (GET, POST)
                                     v
+-------------------------------------------------------------------------+
| Tier 2: Controller Layer (HTTP Request Handlers & Routing)              |
| - URL Routing & Request Dispatching                                    |
| - Input extraction, HTTP parameter validation, CSRF verification        |
| - Authentication & session context checking                             |
| - Orchestration: Delegates exclusively to Service Layer                 |
+------------------------------------+------------------------------------+
                                     | DTOs / Method Invocations
                                     v
+-------------------------------------------------------------------------+
| Tier 3: Service Layer (Business Logic & Domain Rules)                   |
| - Core University business logic (Planning, Requisition, Consolidation) |
| - Requisition balance checking (conceptual balance model)               |
| - Configurable workflow routing & state transition rules                |
| - Database transaction orchestration (begin, commit, rollback)          |
| - Audit event dispatching & validation enforcement                      |
+------------------------------------+------------------------------------+
                                     | Entity Models / Parameters
                                     v
+-------------------------------------------------------------------------+
| Tier 4: Repository Layer (Data Access & Persistence Abstraction)        |
| - Encapsulates all data access and SQL execution                        |
| - Exclusively uses PDO with strict prepared statements                  |
| - Converts database result sets into typed domain objects / arrays     |
| - Zero business logic; focuses solely on querying and persistence       |
+------------------------------------+------------------------------------+
                                     | PDO Prepared Statements
                                     v
+-------------------------------------------------------------------------+
| Tier 5: Database Layer (Relational Storage)                             |
| - Relational schema, referential integrity, domain constraints          |
| - Strict relational consistency and isolation (InnoDB / MySQL 8.x)      |
+-------------------------------------------------------------------------+
```

### 1.2 Technology Foundation
- **Runtime Environment**: PHP 8.2+ (Core PHP without monolithic black-box frameworks).
- **Type Safety**: Enforced strict typing across all application layers (`declare(strict_types=1);`).
- **Data Access Engine**: Native PHP Data Objects (`PDO`) with strict parameter binding.
- **Client Architecture**: Semantic HTML5, Vanilla JavaScript (ES6+), and CSS styling tailored for desktop and mobile devices.

---

## 2. Layer Responsibilities & Strict Isolation Boundaries

### 2.1 Layer Rules (Non-Negotiable)
1. **Downwards Dependency Only**: A layer may only call the layer immediately below it. Presentation calls Controller; Controller calls Service; Service calls Repository; Repository queries Database.
2. **No Skipping Layers**:
   - Controllers **must never** call Repositories directly.
   - Controllers **must never** execute database queries or touch PDO.
   - Views/Templates **must never** invoke Services or Repositories.
3. **Transaction Ownership**:
   - The Service Layer exclusively controls database transactions (`beginTransaction`, `commit`, `rollBack`).
   - Repositories participate in existing transactions passed via connection context; they do not arbitrarily commit or roll back.
4. **Data Transfer Objects (DTOs)**:
   - Data passed between Controller and Service layers is structured using typed DTOs or validated associative data structures, preventing untyped `$_POST` or `$_GET` leakage into domain logic.

---

## 3. Directory & Module Organization Pattern

The application codebase follows a modular, feature-aligned folder organization:

```
promis/
├── config/                  # Environment, database, and application configuration
│   ├── app.php
│   ├── database.php
│   └── workflow.php
├── core/                    # Core framework and architectural foundation
│   ├── Database/            # PDO connection factory and transaction manager
│   ├── Http/                # Request, Response, and Router abstractions
│   ├── Security/            # Session, CSRF, Password, and Sanitization handlers
│   ├── Container/           # Lightweight Dependency Injection / Service Locator
│   └── Exception/           # Base application exception classes
├── src/                     # Domain modules (5-tier implementation)
│   ├── Auth/                # Authentication & User Identity
│   ├── Entity/              # Planning Entities master data management
│   ├── Planning/            # Procurement Planning & Quarterly Review Revision
│   ├── Requisition/         # Department Requisitions & Balance Tracking
│   ├── Consolidation/       # Institutional Consolidation & GHANEPS Export
│   ├── Workflow/            # Configurable Workflow & Routing Architecture
│   ├── Audit/               # Protected Audit Logging
│   └── Report/              # Reporting & Operational Dashboards
├── views/                   # Server-side presentation templates (HTML/PHP)
│   ├── layouts/             # Master layout, navigation, orientation headers
│   ├── auth/                # Login, password change views
│   ├── planning/            # Plan submission, review, quarterly revision views
│   ├── requisitions/        # Requisition forms, tracking views
│   ├── consolidation/       # Consolidation workbench, export views
│   └── reports/             # Report generation, analytics views
├── public/                  # Web server document root (Only entry point exposed)
│   ├── index.php            # Front Controller & Request Bootstrapper
│   ├── css/                 # Stylesheets & CSS bundles
│   └── js/                  # Vanilla JS ES6+ modules
└── storage/                 # Data outside web root
    ├── logs/                # Application & error log files
    └── uploads/             # Secure UUID-addressed file attachments
```

---

## 4. Architectural Subsystems & Cross-Cutting Concerns

### 4.1 Dependency Injection & Service Container
- A lightweight, explicit Dependency Injection Container is used to instantiate and wire controllers, services, and repositories.
- Promotes testability and decoupling without requiring external heavy third-party dependencies.

### 4.2 Error Handling & Domain Exception Hierarchy
The application establishes a structured hierarchy of domain-specific exceptions:
- `AppException` (Base checked exception)
  - `ValidationException` (Input validation or constraint violation; HTTP 422)
  - `AuthenticationException` (Invalid credentials, expired session; HTTP 401)
  - `AuthorizationException` (Permission denied, entity boundary violation; HTTP 403)
  - `NotFoundException` (Requested entity or record not found; HTTP 404)
  - `WorkflowException` (Invalid state transition or routing failure; HTTP 400)
  - `ConcurrencyException` (Optimistic locking conflict during plan review; HTTP 409)

A centralized Front Controller exception handler captures uncaught exceptions, logs details securely with stack traces to `storage/logs/`, and renders sanitized, human-friendly error screens (avoiding raw technical disclosure).

### 4.3 Request Lifecycle (Front Controller Flow)
1. **HTTP Request Arrives**: `public/index.php` initializes the environment, reads configuration, and boots the container.
2. **Security & Session Inspection**: Active session verified; CSRF token validated on state-modifying requests (`POST`, `PUT`, `DELETE`).
3. **Routing**: Route matched to a designated Controller and Action method.
4. **Input Extraction & Validation**: Controller extracts request payload into a validated Request DTO.
5. **Authorization Enforcement**: Controller verifies active user permissions against the required action.
6. **Service Execution**: Service method invoked within a transaction boundary.
   - Business rules verified.
   - Conceptual balances calculated and validated.
   - Repository methods executed via PDO.
   - Audit trail event generated.
   - Transaction committed.
7. **Response Generation**: Controller receives result DTO and selects appropriate View template or JSON response envelope.
8. **Render & Send**: Response emitted to client browser with appropriate HTTP status codes and security headers.

---

## 5. Security & Data Integrity Architecture within Code
1. **Zero Raw SQL in Application Layers**: All queries exist exclusively in Repositories, using parameterized PDO statements.
2. **Server-Side Authorization**: Every controller and service explicitly checks entity ownership and role authorization regardless of client-side visibility.
3. **Audit Dispatch**: Critical domain operations (Plan Approval, Plan Revision, Requisition Action, Consolidation Export) programmatically trigger audit events before transaction completion.
