# Procurement Management Information System (PROMIS)
**University of Science and Technology, Dedicated (USTED)**

An institutional, enterprise-grade Procurement Management Information System built on a zero-framework, 5-tier Core PHP 8.x architecture.

---

## 1. Authoritative References & Architectural Baseline

This repository is governed by the authoritative engineering specifications located in the project workspace:
- **Requirements Foundation:**
  - [`.requirements/promis-requirements.md`](.requirements/promis-requirements.md)
  - [`.requirements/functional-requirements.md`](.requirements/functional-requirements.md)
  - [`.requirements/security-requirements.md`](.requirements/security-requirements.md)
  - [`.requirements/acceptance-criteria.md`](.requirements/acceptance-criteria.md)
- **System Architecture:**
  - [`.architecture/application-architecture.md`](.architecture/application-architecture.md)
  - [`.architecture/module-architecture.md`](.architecture/module-architecture.md)
  - [`.architecture/security-architecture.md`](.architecture/security-architecture.md)
  - [`.architecture/audit-architecture.md`](.architecture/audit-architecture.md)
  - [`.architecture/phase-1-scope.md`](.architecture/phase-1-scope.md)
- **Physical Database Design:**
  - [`.database/schema-specification.md`](.database/schema-specification.md)
  - [`.database/schema.mysql8.sql`](.database/schema.mysql8.sql)
  - [`.database/schema.mariadb10.sql`](.database/schema.mariadb10.sql)

---

## 2. Technology Stack & Prerequisites

- **Core Runtime:** PHP 8.2+ with strict typing (`declare(strict_types=1);` in all application files).
- **Architecture Pattern:** 5-Tier Layered Architecture (`Presentation → Controller → Service → Repository → Database`).
- **Database Access:** Native PHP Data Objects (`PDO`) with strict native prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`).
- **Relational Databases Supported:**
  - MySQL 8.x (`utf8mb4_0900_ai_ci` collation, InnoDB engine)
  - MariaDB 10.4+ (`utf8mb4_unicode_ci` collation, InnoDB engine)
- **Front-End Foundation:** Semantic HTML5, Vanilla CSS design system tokens, Vanilla JavaScript (zero node/npm runtime dependency).
- **Framework & Dependencies:** Zero framework, zero third-party Composer runtime dependencies.

### Required PHP Extensions
Ensure the following standard PHP extensions are enabled in `php.ini`:
- `pdo`
- `pdo_mysql`
- `session`
- `json`
- `openssl`
- `ctype`
- `filter`
- `mbstring`
- `sodium` (or `hash` for cryptographic randomness and Argon2id password hashing)

---

## 3. Environment & Configuration

1. Copy `.env.example` to create `.env`:
   ```bash
   cp .env.example .env
   ```
2. Configure your environment parameters in `.env`:
   ```ini
   APP_NAME="PROMIS - USTED"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost/promis/public
   APP_TIMEZONE=Africa/Accra

   # Database Selection: 'mariadb' or 'mysql'
   DB_ENGINE=mariadb
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=promis
   DB_USERNAME=root
   DB_PASSWORD=
   DB_CHARSET=utf8mb4
   DB_COLLATION=utf8mb4_unicode_ci

   # Session Security
   SESSION_COOKIE_NAME=promis_session
   SESSION_LIFETIME=7200
   SESSION_SECURE=false
   SESSION_HTTPONLY=true
   SESSION_SAMESITE=Lax
   ```

> **Security Notice:** The `.env` file is excluded from version control. Never commit production database credentials, secrets, or API keys into git.

---

## 4. Database Provisioning

The database must be provisioned using one of the two verified, environment-specific DDL builds.

### A. For MariaDB 10.4 / XAMPP
```bash
# Using MariaDB CLI:
c:\xampp\mysql\bin\mysql.exe -u root -p < .database/schema.mariadb10.sql
```

### B. For MySQL 8.x
```bash
# Using MySQL CLI:
mysql -u root -p < .database/schema.mysql8.sql
```

### Database Provisioning Guarantees:
- **36 Relational Tables** created in dependency-safe order.
- **122 Physical Foreign Keys** with verified referential integrity actions:
  - `CASCADE = 4` (pure associative junctions only)
  - `RESTRICT = 84` (audit logs, workflows, master entities, line items)
  - `SET NULL = 34` (optional actor relationships)
  - `ON UPDATE RESTRICT = 122` across all foreign keys.
- **Drift Warning:** DDL statements use `CREATE TABLE IF NOT EXISTS`, which will not automatically alter or repair existing drifted columns. For a clean deployment, drop and re-provision in controlled environments only.

---

## 5. Running the Application

### Running with Apache (XAMPP)
1. Place the repository in `c:/xampp/htdocs/promis`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open your browser and navigate to:
   - Foundation Dashboard: `http://localhost/promis/public/`
   - Health Check Endpoint: `http://localhost/promis/public/health`

### Running with PHP Built-in Server (Development)
```bash
c:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```
Then visit `http://127.0.0.1:8000/`.

---

## 6. Running the Foundation Test Suite

The test suite runs via the CLI test runner without external testing frameworks:

```bash
c:\xampp\php\php.exe tests/FoundationTest.php
```

### Verified Test Categories (42 Assertions):
1. **Environment Loading:** `.env` parsing, type casting, and defaults.
2. **Config System:** `config/app.php`, `config/database.php`, `config/session.php`.
3. **Application Bootstrap:** Router, Logger, and autoloader initialization.
4. **Database Connection:** PDO connection verification, strict error modes (`ERRMODE_EXCEPTION`), associative fetch default, and native prepared statements (`ATTR_EMULATE_PREPARES = false`).
5. **HTTP Router:** GET, POST, parameterized routes (`{id}`, `{slug}`), middleware pipelines, 404 Not Found, and 405 Method Not Allowed with `Allow` header.
6. **Security & CSRF:** Cryptographic token generation (`random_bytes`), timing-safe verification (`hash_equals`), and forged/missing token rejection.
7. **Password Hashing:** Argon2id / secure algorithm verification and rehash detection.
8. **Authentication & Authorization:** Deny-by-default access guard, entity-scoped RBAC authorization (`allows()`, `authorize()`).
9. **Sanitizer & Escaping:** HTML UTF-8 encoding defense against XSS.
10. **Session State:** Session key storage, flash messaging single-read destruction.
11. **Health Endpoint:** Sanitized JSON output (`/health`) returning HTTP 200 or HTTP 503 without credential disclosure.

---

## 7. Architectural Layering & Boundaries

```
Presentation Layer (Views, Layouts, CSS/JS)
         ↓
Controller Layer (BaseController, Request/Response, Csrf Validation)
         ↓
Service Layer (BaseService, Transactions, Business Rules)
         ↓
Repository Layer (BaseRepository, Strict Prepared Statements)
         ↓
Database Layer (InnoDB Engine, 122 Foreign Keys)
```

### Non-Negotiable Rules:
- **Controllers** must not contain SQL statements.
- **Services** must not render or return HTML.
- **Repositories** must not contain presentation or session logic.
- **Views** must not contain business logic or database queries.
- **Authorization** decisions are strictly enforced server-side.

---

## 8. Implemented Foundation Components

- `public/index.php` — Single public entry point and front controller.
- `public/.htaccess` — Apache rewrite rules for clean URLs and security headers.
- `core/autoload.php` — Zero-dependency PSR-4 autoloader (`Promis\Core\` & `Promis\Src\`).
- `core/App.php` — Central bootstrap, route registrar, and error handler.
- `core/Support/Env.php` — Environment variable loader.
- `core/Support/Logger.php` — Diagnostic logger with sensitive data masking.
- `core/Support/View.php` — Safe view renderer with layout support and path traversal defense.
- `core/Database/Connection.php` — Singleton PDO connection factory.
- `core/Http/Request.php` — HTTP request abstraction.
- `core/Http/Response.php` — HTTP response abstraction with security headers.
- `core/Http/Router.php` — Parameterized HTTP router with REST verb matching and middleware.
- `core/Security/Session.php` — Secure session manager (HttpOnly, SameSite=Lax, fixation defense).
- `core/Security/Csrf.php` — Cryptographic CSRF token generator and timing-safe validator.
- `core/Security/Password.php` — Argon2id / bcrypt password hasher and validator.
- `core/Security/Sanitizer.php` — UTF-8 HTML escaping and input sanitization.
- `core/Auth/AuthManager.php` — Authentication lifecycle state manager.
- `core/Auth/Authorization.php` — Deny-by-default server-side authorization guard.
- `core/Controller/BaseController.php` — Abstract base controller.
- `core/Service/BaseService.php` — Abstract base service with transaction helpers.
- `core/Repository/BaseRepository.php` — Abstract base repository with prepared statements.
- `core/Exception/*` — Layer-specific custom exception hierarchy.
- `views/layouts/main.php` & `views/layouts/guest.php` — Accessible HTML5 layouts.
- `views/errors/401.php`, `403.php`, `404.php`, `500.php` — Sanitized error views.
- `views/home/index.php` — Neutral foundation status overview.
- `public/css/app.css` & `public/js/app.js` — Mobile-first foundation styles and CSRF-aware scripts.

---

## 9. Intentionally Deferred Components (Phase 2+)

The following procurement business modules are strictly deferred to subsequent implementation phases and have not been stubbed or seeded with mock university data:
- Annual Procurement Planning (`procurement_plans`, `procurement_plan_lines`)
- Departmental Requisition Submission, Validation & Multi-Tier Approval Workflows
- Requisition Consolidation & Sourcing Package Assembly
- Procurement Method Threshold Rules & Sourcing Management
- Contract Administration, Purchase Orders & Inspection/Receiving Workflows
- Finance GL Budgetary Reservation & Commitment Integrations
- External GHANEPS Compliance & Audit Trail Export Integrations
- Official University Master Data (campuses, faculties, departments, user accounts, role definitions)

---

## 10. Security Considerations

- **Deny by Default:** Unauthenticated visitors or authenticated users without explicit permission grants are rejected by default.
- **Session Fixation Defense:** Session IDs are regenerated upon login.
- **Zero Information Leakage:** Database passwords, connection parameters, stack traces, and internal file paths are masked from user-facing views and health endpoints.
- **Native Prepared Statements:** Repositories enforce native prepared queries, eliminating SQL injection vulnerabilities.
- **Timing Attack Defense:** All CSRF token checks utilize `hash_equals()`.
