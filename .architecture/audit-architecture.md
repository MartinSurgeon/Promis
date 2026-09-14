# PROMIS Architecture Specification: Audit Architecture

## 1. Architectural Purpose & Mandate

Public procurement requires verifiable traceability. The PROMIS Audit Architecture ensures that every statutory, financial, and administrative transaction produces a permanent, attributable record.

### Architectural Constraint
In accordance with system requirements:
> **"Audit records shall be protected from unauthorized modification or deletion."**

---

## 2. Audit Trail Data Model & Attributes

The audit logging subsystem captures a standardized, structured event payload for every critical transaction:

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                              AUDIT TRAIL EVENT                              │
├──────────────────────┬──────────────────────────────────────────────────────┤
│ Attribute            │ Description / Architectural Role                     │
├──────────────────────┼──────────────────────────────────────────────────────┤
│ Audit ID             │ Sequential unique identifier                         │
│ User ID              │ Authenticated staff member who initiated the action  │
│ Planning Entity ID   │ Sponsoring organizational unit                       │
│ Action Code          │ Standardized machine-readable action identifier      │
│ Target Record Type   │ Entity class (e.g. REQUISITION, PLAN_VERSION)       │
│ Target Record ID     │ Primary key of the affected domain record            │
│ Previous State       │ Record status before event (e.g. PENDING_APPROVAL)   │
│ New State            │ Record status after event (e.g. APPROVED)            │
│ Variance / Details   │ Serialized delta, justification notes, or comments   │
│ IP Address & Agent   │ Client network origin for forensic tracing           │
│ Timestamp            │ Exact server date and time of execution              │
│ Outcome Status       │ SUCCESS or FAILURE (with failure reason)             │
└──────────────────────┴──────────────────────────────────────────────────────┘
```

---

## 3. Standard Action Codes

```text
USER_AUTHENTICATED          PLAN_CREATED                REQUEST_CREATED
USER_LOGIN_FAILED           PLAN_SUBMITTED              REQUEST_SUBMITTED
ENTITY_CREATED              PLAN_APPROVED               REQUEST_APPROVED
ENTITY_DEACTIVATED          PLAN_RETURNED               REQUEST_RETURNED
ROLE_ASSIGNED               PLAN_REJECTED               REQUEST_REJECTED
ALLOCATION_CAPTURED         PLAN_REVIEW_COMPLETED       COMMITMENT_AUTHORIZED
CATALOGUE_ITEM_ADDED        PLAN_REVISION_STAGED        CONSOLIDATION_RUN
CATALOGUE_ITEM_EDITED       PLAN_REVISION_APPROVED      DELIVERY_LOGGED
```

---

## 4. Protection from Unauthorized Modification or Deletion

The architecture enforces strict safeguards to protect audit records:

1. **No Application-Level Mutation Interfaces**:
   - The application codebase provides exclusively `INSERT` and `SELECT` query pathways for audit trails.
   - There are zero controllers, services, repositories, or API endpoints capable of executing `UPDATE` or `DELETE` on the audit log.
2. **Access Control on Audit Views**:
   - Only authorized compliance officers, internal auditors, and executive management possess read access to audit logs.
   - Operational requesters and department heads cannot inspect system-wide audit logs outside their own transaction histories.
3. **Transactional Logging**:
   - State transitions and corresponding audit entries are executed within the same atomic database transaction, ensuring that an operational state change can never commit without its corresponding audit entry.
