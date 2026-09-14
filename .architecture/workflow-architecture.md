# PROMIS Architecture Specification: Workflow & Routing Architecture

## 1. Architectural Philosophy

In accordance with system design constraints, PROMIS avoids heavy, overly abstract workflow engines. Instead, the system implements a pragmatic, maintainable **Configurable Workflow and Routing Architecture**.

This architecture governs state transitions, validates prerequisite business conditions, and dynamically routes records to designated institutional roles based on configurable approval rules.

---

## 2. Configurable Workflow Architecture Components

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                       WORKFLOW ROUTING ARCHITECTURE                         │
│                                                                             │
│  ┌───────────────────────┐   ┌───────────────────────┐   ┌────────────────┐ │
│  │   STATE TRANSITION    │   │      TRANSITION       │   │ ROUTING POLICY │ │
│  │       REGISTRY        │   │        GUARDS         │   │    RESOLVER    │ │
│  │ (Allowed State Graph) │   │ (Business Invariants) │   │ (Next Approver)│ │
│  └───────────┬───────────┘   └───────────┬───────────┘   └────────┬───────┘ │
│              │                           │                        │         │
│              └─────────────────► ┌───────┴────────┐ ◄─────────────┘         │
│                                  │ TRANSITION SVC │                         │
│                                  └───────┬────────┘                         │
│                                          │ (Execute Mutation & Audit)       │
│                                          ▼                                  │
│                             [RECORD STATE UPDATED]                          │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1. State Transition Registry
Defines the legal state machine graph for each workflow domain (Requisitions, Procurement Plans, Plan Reviews). Disallows any illegal transition (e.g. transitioning directly from `Draft` to `Approved`, or transitioning a `Rejected` record).

### 2. Transition Guards (Business Invariants)
Reusable validation routines executed prior to state progression:
- *Completeness Guard*: Validates that all mandatory fields and line items exist.
- *Authorization Guard*: Validates that the active user possesses the required role/authority for this exact transition.
- *Budget Guard*: Validates budget allocation and commitment checks.
- *Plan Balance Guard*: Validates that drawdowns do not exceed remaining plan balances.

### 3. Routing Policy Resolver
Determines the next role or designated officer responsible for action based on:
- Requesting planning entity.
- Value threshold (if applicable upon university confirmation).
- Configured organizational route.

---

## 3. Canonical Workflow Transitions

Every workflow pipeline supports the standard institutional transition set:

```text
[Draft]
   │
   ▼ (action: SUBMIT)
[Pending Approval] ──(action: QUERY_RETURN)──► [Returned for Correction]
   │                                                      │
   │                                                      ▼ (action: RESUBMIT)
   ├──────────────────(action: REJECT)────────► [Rejected] (Terminal)
   │
   ▼ (action: APPROVE)
[Approved / Pending Next Stage]
   │
   ▼ (action: COMMIT / AUTHORIZE)
[Commitment Authorized]
   │
   ▼ (action: RELEASE_TO_PROCUREMENT)
[Processing / Complete]
```

### Standard Action Set
- **`SUBMIT`**: Transitions `Draft` or `Returned` to `Pending Approval`; locks editing.
- **`APPROVE`**: Endorses record; advances to next approval tier or budget commitment.
- **`QUERY_RETURN`**: Returns record with mandatory query comments; unlocks record for editing.
- **`RESUBMIT`**: Submits corrected record back into the approval pipeline.
- **`REJECT`**: Formally disapproves record with mandatory justification; terminates workflow.
- **`AUTHORIZE_COMMITMENT`**: Financial checkpoint authorizing budget commitment.

---

## 4. Configurable Workflow Routing Instances

To satisfy institutional diversity without hardcoding routes, approval sequences are modeled as configurable routing profiles:

### Route Instance A: Standard Departmental Requisition Route (Example)
```text
Step 1: Departmental Preparer creates and submits Draft.
Step 2: Head of Department (HoD) reviews and approves.
Step 3: Directorate Dean / Director reviews and approves.
Step 4: Directorate of Finance conducts Budget Commitment Authorization.
Step 5: Released to Directorate of Procurement for consolidation and sourcing.
```

### Route Instance B: Executive Director's Office Route (Example)
```text
Step 1: Office Administrator prepares on behalf of the Director.
Step 2: Director reviews and directly approves.
Step 3: Directorate of Finance conducts Budget Commitment Authorization.
Step 4: Released to Directorate of Procurement for consolidation and sourcing.
```

*Architectural Principle: These office-specific flows are treated as configured routing profiles. The system architecture supports defining alternative routing steps for faculties, administrative sections, or halls once University procedures are confirmed.*
