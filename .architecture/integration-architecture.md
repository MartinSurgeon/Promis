# PROMIS Integration Architecture Specification

## 1. System Integration Context & Boundary Principles
PROMIS operates as the dedicated procurement management platform for the University. While modern enterprise systems often communicate across a landscape of external applications, the approved PROMIS requirements establish a strictly disciplined integration perimeter for Phase 1 to prevent over-engineering and boundary creep.

### 1.1 Core Architectural Principles
1. **Clear System of Record**: PROMIS is the authoritative system of record for University internal procurement plans, plan revisions, quarterly review records, entity requisitions, and institutional consolidation packages.
2. **Decoupled Perimeter**: External systems (GHANEPS, Finance/ERP, HR) do not maintain direct synchronous dependencies or coupled transactions with PROMIS core processing in Phase 1.
3. **No Speculative Integration**: Where external system APIs, data schemas, or protocols are not confirmed by University authorities, PROMIS specifies controlled boundary touchpoints rather than speculative physical connectors.

---

## 2. GHANEPS (Ghana Electronic Procurement System) Boundary

### 2.1 Boundary Classification
- **Classification**: `CONFIRMED PHASE 1 BOUNDARY (Procurement Information Provision)`
- **External Counterpart**: National GHANEPS platform operated by the Public Procurement Authority (PPA).

### 2.2 Integration Model
- **Direct Synchronous API Integration**: `PROPOSED LATER PHASE / OUT OF PHASE 1 SCOPE`. No direct API connection to GHANEPS is confirmed or required for Phase 1.
- **Integration Classification**: `PROPOSED INTEGRATION APPROACH`
  - **Core Requirement**: "PROMIS shall provide the required procurement information for the applicable GHANEPS process. The technical handover/export format remains TO BE CONFIRMED."
  - The architecture establishes that the Procurement Directorate compiles the consolidated institutional procurement plan within PROMIS.
  - While structured file export packages (such as CSV, structured Excel, or JSON) for manual portal upload represent a `PROPOSED INTEGRATION APPROACH`, the system does not assume any specific file format as the approved mechanism until officially confirmed.

### 2.3 Boundary Artifacts & Touchpoints
```
+-------------------------------------------------------------------------+
|                              PROMIS Core                                |
|  [Approved Annual Plan / Revised Plan] -> [Consolidation Engine]        |
|                                                     |                   |
|                                            [Export Generator]           |
+-----------------------------------------------------+-------------------+
                                                      | Handover / Export
                                                      | Package (Format TBC)
                                                      v (Proposed Approach)
                                            +---------------------+
                                            |  Authorized Officer |
                                            |  (Manual Upload)    |
                                            +----------+----------+
                                                       | Web Portal
                                                       v Session
                                            +---------------------+
                                            |   National GHANEPS  |
                                            +---------------------+
```

- **Export Package Metadata**:
  - Export Timestamp, Exporting User ID, Plan Version ID, Hash/Checksum of exported dataset.
  - Logged in PROMIS audit trail to ensure traceability between internal approved plans and exported artifacts.

---

## 3. Financial & Budget System Touchpoints

### 3.1 Boundary Classification
- **Classification**: `BUDGET ALLOCATION AND COMMITMENT AUTHORIZATION TOUCHPOINTS`
- **Integration Mechanism Status**: `TO BE CONFIRMED`

### 3.2 Architectural Treatment
- The system must **not** treat "commitment reservation" or automated general ledger synchronization as a confirmed PROMIS function.
- In Phase 1, PROMIS models budget data through **configured master data touchpoints** and internal plan verification:
  - Each procurement plan item records an estimated budget, funding source, and budget code reference.
  - The Finance Directorate exercises budget verification during the configurable workflow routing (e.g., Finance review before Final Approval).
  - Verification is performed through human authorization checkpoints within PROMIS screens, referencing institutional accounting ledgers out-of-band or via manual entry.

### 3.3 Touchpoint Specifications
1. **Budget Allocation Entry**:
   - Authorized officers enter or update annual entity budget allocations within PROMIS master data / planning setup.
   - Mechanism for Phase 1: Administrative UI / Master Data Management.
   - Future Potential Mechanism (`PROPOSED LATER PHASE`): Automated batch import or read-only query against University Financial ERP.
2. **Commitment Authorization Touchpoint**:
   - When a requisition is processed, the workflow routes the request to designated financial authorities.
   - The financial authority reviews available funds and records an explicit financial authorization decision (`Approved`, `Rejected`, or `Returned for Adjustment`) in the PROMIS workflow history.
   - PROMIS maintains internal balances:
     - Approved Planned Quantity
     - Previously Requested Quantity
     - Current Request Quantity
     - Remaining Before Current Request = Approved Planned Quantity - Previously Requested Quantity
     - Remaining After Current Request (if approved) = Remaining Before Current Request - Current Request Quantity
   - Direct physical balance debiting on external accounting ledgers remains outside the PROMIS core boundary.

---

## 4. User Directory & Identity Management Touchpoint

### 4.1 Boundary Classification
- **Classification**: `CONFIGURABLE MASTER DATA / TO BE CONFIRMED`
- **Phase 1 Implementation**: PROMIS Native User Directory.

### 4.2 Architectural Model
- PROMIS maintains its own native user, role, and credential store.
- Passwords stored using modern cryptographically secure hashing (e.g., Argon2id or bcrypt).
- **External LDAP / Active Directory / Single Sign-On (SSO)**:
  - Classified as: `PROPOSED LATER PHASE / TO BE CONFIRMED`.
  - Architecture decoupling: Authentication logic is isolated within an `AuthenticationService` interface so an institutional SSO provider (SAML 2.0 / OAuth2 / OIDC) can be introduced in a future phase without modifying application business rules.

---

## 5. Notification & Communications Touchpoint

### 5.1 In-System Notifications
- **Classification**: `CONFIRMED PHASE 1`
- Internal notification inbox within PROMIS storing alerts for:
  - Plan submission, approval, revision requests, or rejections.
  - Requisition submission, approval routing, stage changes, or rejections.
  - Consolidation batch completions.

### 5.2 Outbound SMTP / Email Gateway
- **Classification**: `PROPOSED ARCHITECTURAL COMPONENT (TO BE CONFIRMED)`
- Notification service defines an outbound notification gateway interface.
- Outbound emails (e.g., notification alerts, password reset tokens) are routed via standard SMTP if configured in environment settings.
- Failure of external SMTP delivery must never rollback or fail internal database transactions (asynchronous dispatch or decoupled logging).

---

## 6. Document & Attachment Storage Touchpoint

### 6.1 Boundary Classification
- **Classification**: `PROPOSED ARCHITECTURAL COMPONENT`
- **Storage Strategy**: `PROPOSED IMPLEMENTATION APPROACH (UUID File Storage)`

### 6.2 Architectural Model
- Supporting documents (specifications, market research quotations, justification memos) are uploaded via HTTP multi-part upload.
- Stored on a secure local filesystem directory outside the web root (`storage/uploads/`).
- Files are renamed with unique UUID identifiers to eliminate filename collisions and path traversal exploits.
- Original filename, MIME type, upload timestamp, and user ID are retained in database metadata.
- Storage service provides an abstract file handler interface allowing potential future migration to cloud object storage (S3/compatible) if University infrastructure mandates it in later phases.

---

## 7. Integration Matrix Summary

| External Boundary / Domain | Phase 1 Status | Mechanism / Format | Direction | Fallback / Default |
| :--- | :--- | :--- | :--- | :--- |
| **GHANEPS** | `CONFIRMED PHASE 1 (Info) / TBC (Format)` | `PROPOSED INTEGRATION APPROACH`: Handover/Export Package (Format TBC) | Outbound (Data/File) | PROMIS provides required procurement information; technical format TBC |
| **Finance / Accounting** | `TO BE CONFIRMED` | Budget allocation & commitment authorization touchpoints | Internal UI / Verification Checkpoints | Manual entry of budget codes & human sign-off |
| **Identity / HR Directory**| `CONFIRMED PHASE 1 (Native)` / `LATER PHASE (SSO)` | Configurable Master Data; Native Auth Service | Internal | PROMIS local user database with strict hashing |
| **Email Gateway (SMTP)** | `PROPOSED ARCHITECTURAL COMPONENT` | Standard SMTP Gateway (Decoupled) | Outbound (Decoupled) | In-system notification inbox |
| **Document Storage** | `PROPOSED ARCHITECTURAL COMPONENT` | Secure local filesystem (`UUID storage` as proposed approach) | Local I/O | Dedicated directory outside web root |
