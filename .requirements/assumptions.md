# PROMIS Requirements: Architectural & Operational Assumptions

## Overview

This document explicitly catalogues the technical, operational, and organizational assumptions underlying the PROMIS requirements baseline. Explicitly documenting assumptions prevents unvetted presuppositions from degrading system architecture.

---

## 1. Technical Stack Assumptions

### ASM-TEC-001: Approved Runtime Environment
- **Assumption**: The target production hosting environment provides:
  - PHP 8.1+ (configured with PDO, session support, and `fileinfo` extension).
  - MySQL 8.x (or compatible MariaDB) supporting transactions, foreign keys, and UTF8MB4 character sets.
  - Apache or Nginx web server with URL rewriting capability.
- **Classification**: `CONFIRMED BASELINE`
- **Risk & Mitigation**: If older PHP versions (e.g. PHP 7.x) are used, strict typing and modern error handling would break. *Mitigation: Verify server environment during deployment planning.*

### ASM-TEC-002: Zero Heavyweight External Frameworks
- **Assumption**: Development proceeds using modular Core PHP, Tailwind CSS, and Vanilla JavaScript ES6+, deliberately excluding Laravel, Symfony, React, Vue, Angular, Bootstrap, and jQuery.
- **Classification**: `CONFIRMED BASELINE`
- **Rationale**: Keeps long-term maintenance overhead minimal, eliminates version dependency vulnerabilities, and ensures maximum execution speed.

### ASM-TEC-003: WCAG AA Accessibility Classification
- **Assumption**: Compliance with full WCAG AA accessibility standards is treated as a **`PROPOSED TECHNICAL STANDARD`** rather than a confirmed statutory requirement, unless explicitly mandated by university policy.
- **Classification**: `PROPOSED TECHNICAL STANDARD`
- **Mitigation**: Core semantic HTML, keyboard focus, and multi-modal status indicators are implemented by default.

### ASM-TEC-004: File Storage Naming Convention
- **Assumption**: Renaming uploaded supporting documents to randomized unique identifiers (such as UUIDs) is treated as a **`PROPOSED IMPLEMENTATION APPROACH`** to protect filesystem integrity.
- **Classification**: `PROPOSED IMPLEMENTATION APPROACH`

---

## 2. Operational & Business Assumptions

### ASM-OPS-001: Organizational Configurability (~62 Planning Entities)
- **Assumption**: While the exact list of 62 planning entities is `PENDING DATA`, the organizational model can be fully structured as dynamic master data tables, allowing entities to be loaded via data imports without altering software architecture.
- **Classification**: `CONFIRMED ARCHITECTURAL ASSUMPTION`

### ASM-OPS-002: Independence of GHANEPS
- **Assumption**: GHANEPS provides no real-time bi-directional REST APIs for university ERP systems. PROMIS is assumed to interact with GHANEPS through structured data exports/imports managed by Procurement Officers.
- **Classification**: `WORKING ASSUMPTION`
- **Risk & Mitigation**: If GHANEPS releases public APIs in the future, PROMIS can integrate an API exporter without changing internal requisition workflows.

### ASM-OPS-003: Quarterly Plan Review Cycle (FR-049, FR-050)
- **Assumption**: Procurement plans undergo quarterly reviews and revisions as confirmed by the client, with previous versions preserved historically. The operational rules (e.g. who initiates, what happens to in-flight requests) will be confirmed by University stakeholders during iterative review.
- **Classification**: `CONFIRMED CLIENT REQUIREMENT / OPERATIONAL DETAILS: TO BE CONFIRMED`

### ASM-OPS-004: Scope of Audit Record Protection
- **Assumption**: Audit records are protected from unauthorized modification or deletion by application users and staff. Database-level physical storage will be maintained securely by system administrators according to standard institutional database retention policies.
- **Classification**: `CONFIRMED BASELINE`

---

## 3. Assumptions Tracking & Review Protocol

Whenever an institutional decision is finalized by USTED stakeholders:
1. The relevant assumption in this document will be updated to reflect the decision.
2. Cross-references in [functional-requirements.md](file:///c:/xampp/htdocs/promis/.requirements/functional-requirements.md), [business-rules.md](file:///c:/xampp/htdocs/promis/.requirements/business-rules.md), and [open-questions.md](file:///c:/xampp/htdocs/promis/.requirements/open-questions.md) will be updated synchronously.
