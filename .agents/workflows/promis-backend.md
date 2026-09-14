---
description: Core PHP 8.x standards, service and repository patterns, API endpoint design, and error handling for PROMIS.
---

# PROMIS Backend Engineering Workflow

## Purpose

This workflow sets the engineering standards for the PROMIS backend. PROMIS uses modern **Core PHP 8.x+** with PDO, emphasizing robust separation of concerns, clear API endpoint design, and predictable error handling without heavy framework overhead.

---

## 1. Modern PHP Standards

- **PHP Version**: Target PHP 8.x+.
- **Strict Typing**: Prefer declaring strict types at the head of backend PHP files:
  ```php
  <?php
  declare(strict_types=1);
  ```
- **Type Declarations**: Use explicit parameter types, return types, and nullable types (`?string`, `int`, `array`).
- **Coding Conventions**: Follow PSR-12 coding styles where practical (consistent indentation, clear class/function naming, readable method signatures).
- **File Sizing**: Keep files focused. If a single file exceeds 400–500 lines, consider breaking it down into focused services or repositories.

---

## 2. API Endpoint Design & Responsibilities

API endpoints in PROMIS must follow clear, action-oriented responsibilities.

### Endpoint Structure Example
```text
/api/requisitions/list.php      -> Retrieve paginated requisition records
/api/requisitions/create.php    -> Create a new draft requisition
/api/requisitions/update.php    -> Update an existing draft requisition
/api/requisitions/submit.php    -> Transition draft to submitted
/api/requisitions/approve.php   -> Authorize or approve a requisition
/api/requisitions/reject.php    -> Reject a requisition with remarks
```

### Predictable JSON Response Envelope
All API endpoints must return a standardized JSON structure with proper HTTP response codes:

```json
{
  "success": true,
  "message": "Request approved successfully.",
  "data": {
    "requisition_id": 1042,
    "status": "APPROVED",
    "next_step": "Pending Budget Commitment Authorization"
  }
}
```

### Standard Header & Error Format
```php
header('Content-Type: application/json; charset=UTF-8');

try {
    // 1. Session & CSRF verification
    // 2. Validate input parameters
    // 3. Invoke business service
    // 4. Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Operation completed successfully.',
        'data' => $result
    ]);
} catch (ValidationException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'errors' => $e->getErrors()
    ]);
} catch (UnauthorizedException $e) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to perform this action.'
    ]);
} catch (Throwable $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred. Please try again later.'
    ]);
}
exit;
```

---

## 3. Separation of Service and Repository

Maintain clean abstraction between HTTP handling, business policies, and database queries:

1. **Controller / Endpoint**:
   - Parses incoming request (GET/POST/JSON).
   - Validates CSRF token and authentication session.
   - Calls the domain Service.
   - Renders JSON or PHP view.
2. **Service Layer**:
   - Enforces institutional rules (e.g. "Does the requested quantity exceed the remaining approved plan?").
   - Coordinates multi-step operations.
   - Dispatches audit logs.
3. **Repository Layer**:
   - Encapsulates PDO prepared statements.
   - Executes transactions.
   - Maps raw query results to typed structures.

---

## 4. Related Workflows
- Refer to [/promis-database](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-database.md) for PDO query patterns and transactions.
- Refer to [/promis-security](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-security.md) for session, role, and CSRF enforcement.
- Refer to [/promis-frontend](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-frontend.md) for consuming backend APIs with Fetch.
