# PROMIS Requirements: Non-Functional Requirements (NFR)

## Overview

This document specifies the Non-Functional Requirements (NFR-001 through NFR-025) governing performance, security, reliability, usability, accessibility, and operational constraints for PROMIS.

---

## 1. Security & Compliance

### NFR-001: Comprehensive Application Security
The system shall enforce defense-in-depth security, including server-side authentication, role-based authorization, input validation, output encoding, CSRF protection, secure session management, and parameterized database access.  
*Status*: `CONFIRMED TECHNICAL`

### NFR-002: Password Security & Storage
Passwords shall never be stored in plaintext. Passwords shall be hashed using secure, industry-standard cryptographic hashing algorithms (e.g. `password_hash()` with `PASSWORD_DEFAULT`).  
*Status*: `CONFIRMED`

### NFR-003: Mandatory Server-Side Authorization
All sensitive actions, state transitions, and data retrievals shall be validated strictly on the server. Client-side hiding of buttons or form inputs shall never be relied upon for security enforcement.  
*Status*: `CONFIRMED`

### NFR-018: Secure Session Management
User sessions shall be managed using server-side session stores, with secure cookie flags (`HttpOnly`, `SameSite`, and `Secure` where HTTPS is enabled) and session ID regeneration upon authentication to prevent fixation.  
*Status*: `CONFIRMED TECHNICAL`

### NFR-022: Secure File Uploads
Where supporting documents or attachments are uploaded, the system shall validate MIME types, file sizes, and whitelisted extensions. Files shall be stored securely with script execution disabled. Renaming files to unique identifiers (such as UUIDs) is a `PROPOSED IMPLEMENTATION APPROACH` to avoid naming collisions and filesystem vulnerabilities.  
*Status*: `CONFIRMED TECHNICAL / RENAMING CONVENTION: PROPOSED IMPLEMENTATION APPROACH`

---

## 2. Data Integrity & Reliability

### NFR-004: Relational Data Integrity
The system shall maintain referential integrity across planning entities, budget allocations, procurement plans, requisitions, approvals, commitments, consolidations, and audit logs.  
*Status*: `CONFIRMED`

### NFR-005: Audit Record Protection
Important workflow and administrative actions shall be recorded in an audit trail. Audit records shall be protected from unauthorized modification or deletion.  
*Status*: `CONFIRMED`

### NFR-006: Transaction Atomicity & Reliability
Multi-table database mutations (such as requisition submission, commitment authorization, and plan revision updates) shall be executed within atomic database transactions with automatic rollback upon failure.  
*Status*: `CONFIRMED TECHNICAL`

### NFR-023: Transaction Safety
Critical financial and approval state transitions shall be guarded against partial execution or orphaned records.  
*Status*: `CONFIRMED TECHNICAL`

### NFR-025: Concurrency Control
The system shall protect records from unsafe concurrent modifications (e.g. two officers attempting to approve or revise the same record simultaneously) using appropriate database locking or state verification.  
*Status*: `CONFIRMED DESIGN REQUIREMENT`

---

## 3. Scalability & Performance

### NFR-007: Response Time Performance
Standard user interactions (page loads, form submissions, filter queries) shall respond efficiently under expected institutional load across university peak periods.  
*Status*: `CONFIRMED CONCEPT`  
*Specific latency benchmarks (e.g. < 1.5s)*: `TO BE CONFIRMED`

### NFR-008: Organizational Scalability
The system shall support approximately 62 planning entities and dynamic organizational growth without requiring source code modifications when entities are added, updated, or deactivated.  
*Status*: `CONFIRMED DESIGN PRINCIPLE`

---

## 4. Maintainability & Code Standards

### NFR-009: Modular & Maintainable Architecture
The system shall be built using clean, modular Core PHP 8.x+, avoiding unnecessary heavyweight frameworks (no Laravel, Symfony, React, Vue, Angular, Bootstrap, or jQuery) to ensure long-term university maintainability.  
*Status*: `CONFIRMED`

---

## 5. Usability, HCI & Cognitive Load

### NFR-010: Institutional Usability
The user interface shall be intuitive, understandable, and operable by non-technical university staff across administrative offices, departments, and halls.  
*Status*: `CONFIRMED`

### NFR-013: UI Consistency
The system shall maintain consistent layout structures, button hierarchies, status badge colors, font families, and feedback dialogs across all modules.  
*Status*: `CONFIRMED`

### NFR-014: Cognitive Load Minimization
The interface shall minimize cognitive load through clear visual hierarchy, progressive disclosure, Miller's Law chunking (5–7 items), generous whitespace, and exactly one dominant primary action per major section.  
*Status*: `CONFIRMED UX REQUIREMENT`

---

## 6. Mobile Responsiveness & Accessibility

### NFR-011: Mobile-First Responsiveness
The web application shall be engineered mobile-first using Tailwind CSS, ensuring smooth adaptability across smartphones, tablets, laptops, and large desktop screens.  
*Status*: `CONFIRMED`

### NFR-012: Accessibility Standards
The interface shall provide clear text labels, keyboard navigation, visible focus rings, and multi-modal status indicators (combining color with icons and text). Compliance with WCAG AA standards is treated as a `PROPOSED TECHNICAL STANDARD` unless explicitly mandated and approved by university policy.  
*Status*: `CONFIRMED PRINCIPLE / WCAG AA: PROPOSED TECHNICAL STANDARD`

---

## 7. Operations, Logging & Deployment

### NFR-015: Safe Error Handling
The system shall present user-friendly, sanitized error messages while shielding internal server paths, database errors, and stack traces from end-users.  
*Status*: `CONFIRMED`

### NFR-016: Secure Diagnostic Logging
System errors, database exceptions, and suspicious security events shall be written to secure server logs for operational investigation.  
*Status*: `CONFIRMED TECHNICAL`

### NFR-017: Backup and Disaster Recovery
The system shall support periodic database and file attachment backup routines.  
*Status*: `CONFIRMED CONCEPT`  
*Target Recovery Point Objective (RPO) and Recovery Time Objective (RTO)*: `TO BE CONFIRMED`

### NFR-019: Data Retention Policy
Procurement, plan revision, and audit records shall be retained in accordance with university statutory requirements.  
*Status*: `CONFIRMED CONCEPT`  
*Exact statutory retention period*: `TO BE CONFIRMED`

### NFR-020: Browser Compatibility
The system shall support modern evergreen browsers (Chrome, Edge, Firefox, Safari) used within the University.  
*Status*: `CONFIRMED CONCEPT`  
*Official browser matrix*: `TO BE CONFIRMED`

### NFR-021: Deployment Environment
The application shall be deployable on the University's approved server infrastructure (Core PHP 8.x, MySQL 8.x, Apache/Nginx).  
*Status*: `CONFIRMED CONCEPT`  
*Production hosting environment specifications*: `TO BE CONFIRMED`

### NFR-024: Secure Data Export
Where management data or consolidated packages are exported to spreadsheets or PDF documents, access controls and data formatting integrity shall be maintained.  
*Status*: `TO BE CONFIRMED`
