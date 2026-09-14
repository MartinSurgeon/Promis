---
description: MySQL schema rules, PDO best practices, query optimization, transactions, and data integrity for PROMIS.
---

# PROMIS Database Workflow

## Purpose

This workflow sets the rules for interacting with the MySQL 8.x-compatible database in PROMIS using PDO. It ensures maximum data integrity, institutional auditability, security, and high runtime performance.

---

## 1. Pre-Change Schema Inspection

Before executing migrations, modifying tables, or introducing new queries, inspect the active schema:

1. **Table Definitions & Relationships**:
   - Check primary keys, foreign key constraints (`ON DELETE RESTRICT/CASCADE`), and cascading behaviors.
2. **Data Integrity & Constraints**:
   - Verify `UNIQUE` indexes (e.g. unique requisition reference numbers, budget codes).
   - Check `NOT NULL` constraints and default values.
3. **Status & Audit Columns**:
   - Ensure tables contain appropriate status enums/varchars and audit tracking columns (`created_by`, `created_at`, `updated_by`, `updated_at`).
4. **Duplicate Prevention**:
   - Do not create redundant tables or duplicate columns when existing normalized structures already store the data.

---

## 2. Safe Query Construction with PDO

PROMIS strictly enforces PDO with prepared statements:

```php
// Correct: Parameterized PDO prepared statement
$stmt = $pdo->prepare('
    SELECT id, requisition_number, status, total_amount 
    FROM requisitions 
    WHERE department_id = :department_id AND status = :status
    ORDER BY created_at DESC 
    LIMIT :limit OFFSET :offset
');
$stmt->bindValue(':department_id', $departmentId, PDO::PARAM_INT);
$stmt->bindValue(':status', $status, PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Prohibited Query Anti-Patterns:
- **NEVER concatenate variables into SQL strings**:  
  `"SELECT * FROM users WHERE id = " . $id` is strictly forbidden.
- **NEVER use obsolete APIs**:  
  `mysql_query()`, `mysql_connect()`, and `mysql_*` functions must never be used.

---

## 3. Database Transactions

Whenever an institutional action modifies more than one table, wrap the operations in a strict database transaction:

```php
try {
    $pdo->beginTransaction();

    // 1. Update requisition status
    $stmt1 = $pdo->prepare('UPDATE requisitions SET status = :status WHERE id = :id');
    $stmt1->execute([':status' => 'COMMITMENT_AUTHORIZED', ':id' => $requisitionId]);

    // 2. Insert into audit trail
    $stmt2 = $pdo->prepare('
        INSERT INTO audit_logs (record_type, record_id, action, previous_state, new_state, user_id, created_at)
        VALUES (:type, :id, :action, :prev, :new, :user_id, NOW())
    ');
    $stmt2->execute([
        ':type' => 'REQUISITION',
        ':id' => $requisitionId,
        ':action' => 'COMMITMENT_AUTHORIZED',
        ':prev' => 'PENDING_COMMITMENT',
        ':new' => 'COMMITMENT_AUTHORIZED',
        ':user_id' => $currentUserId
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log error securely and return safe user feedback
    error_log('Transaction failed: ' . $e->getMessage());
    throw new RuntimeException('Failed to authorize commitment.');
}
```

---

## 4. Query Performance & Optimization

- **Avoid `SELECT *`**: Explicitly specify columns needed by the view or consumer to reduce memory usage and network transfer.
- **Eliminate N+1 Queries**: Never run queries inside loops to fetch related items or requester details. Use `JOIN`s or batch `WHERE id IN (...)` queries.
- **Enforce Pagination**: Any list view or API endpoint returning institutional records must implement server-side pagination with `LIMIT` and `OFFSET`.
- **Index Optimization**: Ensure foreign keys, lookup status fields, and columns frequently used in `WHERE`, `ORDER BY`, or `JOIN` clauses have appropriate indexes.

---

## 5. Related Workflows
- Refer to [/promis-security](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-security.md) for SQL injection prevention and audit logging.
- Refer to [/promis-backend](file:///c:/xampp/htdocs/promis/.agents/workflows/promis-backend.md) for integrating PDO with service layers.
