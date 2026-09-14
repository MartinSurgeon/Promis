# PROMIS Requirements: Requirements Traceability Matrix (RTM)

## Overview

This Requirements Traceability Matrix (RTM) links business needs, functional requirements (FR-001 to FR-050), non-functional requirements (NFR-001 to NFR-025), primary actors, impacted data entities, workflow stages, authoritative status, and acceptance test references.

---

## 1. Functional Requirements Traceability Matrix

| Req ID | Requirement Description | Primary Actor | Data Entities | Workflow Stage | Status | Acceptance Criteria Ref | Test Level |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **FR-001** | User Authentication | All Users | Users, Sessions | Access | `CONFIRMED` | AC-AUTH-01 | Security / Integration |
| **FR-002** | Role & Permission Management | Admin | Roles, Permissions | Access | `CONFIRMED` | AC-AUTH-02 | Security / Unit |
| **FR-003** | Planning Entity Management | Admin | Planning Entities | Setup | `CONFIRMED / PENDING DATA` | AC-ORG-01 | System / Functional |
| **FR-004** | Entity Master Data Administration | Admin | Entities, Campuses | Setup | `PENDING DATA / TBC` | AC-ORG-02 | Functional |
| **FR-005** | User-to-Entity Assignment | Admin | User Entity Map | Setup | `CONFIRMED / TBC` | AC-ORG-03 | Functional |
| **FR-006** | Budget Allocation Capture | Finance Officer | Budget Allocations | Budgeting | `CONFIRMED CONCEPT` | AC-BDG-01 | Functional |
| **FR-007** | Procurement Plan Creation | Planning Officer | Procurement Plans | Planning | `CONFIRMED` | AC-PLN-01 | Functional |
| **FR-008** | Plan Item Entry | Planning Officer | Plan Items | Planning | `CONFIRMED CONCEPT` | AC-PLN-02 | Functional |
| **FR-009** | Plan Validation | System | Plan Items, Budget | Planning | `CONFIRMED` | AC-PLN-03 | Unit / Functional |
| **FR-010** | Plan Submission | Planning Officer | Procurement Plans | Planning | `CONFIRMED` | AC-PLN-04 | Functional |
| **FR-011** | Plan Approval Routing | Approver (HoD/Dean) | Plans, Approvals | Approval | `CONFIRMED` | AC-PLN-05 | Integration |
| **FR-012** | Plan Query / Return | Approver | Plans, Approvals | Approval | `CONFIRMED CONCEPT` | AC-PLN-06 | Functional |
| **FR-013** | Plan Rejection | Approver | Plans, Approvals | Approval | `CONFIRMED CONCEPT` | AC-PLN-07 | Functional |
| **FR-014** | Approved Plan Repository | Planning / Procurement | Plans, Plan Items | Repository | `CONFIRMED` | AC-PLN-08 | Functional |
| **FR-015** | Standard Item Catalogue | Requester / Planning | Standard Items | Cataloguing | `CONFIRMED` | AC-CAT-01 | Functional |
| **FR-016** | Catalogue Governance | Catalogue Manager | Items, Categories | Setup | `CONFIRMED CONCEPT` | AC-CAT-02 | Functional |
| **FR-017** | Item Request Creation | Requester | Requisitions, Items | Requisition | `CONFIRMED` | AC-REQ-01 | End-to-End |
| **FR-018** | Plan-Linked Requesting | Requester | Requisitions, Plans | Requisition | `CONFIRMED CONCEPT` | AC-REQ-02 | Integration |
| **FR-019** | Plan Quantity Tracking | System | Plan Items, Requests | Requisition | `PROPOSED / TBC` | AC-REQ-03 | Unit / Functional |
| **FR-020** | Partial Drawdown Requests | Requester | Plan Items, Requests | Requisition | `TO BE CONFIRMED` | AC-REQ-04 | Functional |
| **FR-021** | Over-Plan Request Control | System | Plan Items, Requests | Requisition | `TO BE CONFIRMED` | AC-REQ-05 | Functional / Rule |
| **FR-022** | Requisition Validation | System | Requisitions | Requisition | `CONFIRMED` | AC-REQ-06 | Unit |
| **FR-023** | Requisition Submission | Requester | Requisitions | Requisition | `CONFIRMED` | AC-REQ-07 | Functional |
| **FR-024** | Requisition Approval Routing | Approver | Requisitions, Approvals | Approval | `CONFIRMED` | AC-APP-01 | Integration |
| **FR-025** | Requisition Query / Return | Approver | Requisitions, Approvals | Approval | `CONFIRMED CONCEPT` | AC-APP-02 | Functional |
| **FR-026** | Requisition Rejection | Approver | Requisitions, Approvals | Approval | `CONFIRMED CONCEPT` | AC-APP-03 | Functional |
| **FR-027** | Office Approval Sequences | Approver / Director | Requisitions | Approval | `CONFIRMED (OFFICE)` | AC-APP-04 | End-to-End |
| **FR-028** | Budget Commitment Check | Finance Officer | Requisitions, Budget | Commitment | `CONFIRMED CONCEPT` | AC-BDG-02 | Functional |
| **FR-029** | Commitment Decision | Finance Officer | Commitments | Commitment | `TO BE CONFIRMED` | AC-BDG-03 | Functional |
| **FR-030** | Demand Consolidation | Procurement Officer | Consolidations | Consolidation | `CONFIRMED` | AC-CNS-01 | Functional |
| **FR-031** | Provenance Preservation | System | Consolidated Items | Consolidation | `CONFIRMED` | AC-CNS-02 | Unit / Functional |
| **FR-032** | Periodic Consolidation | Procurement Officer | Consolidations | Consolidation | `TO BE CONFIRMED` | AC-CNS-03 | Functional |
| **FR-033** | Item Matching Logic | System | Items, Categories | Consolidation | `TO BE CONFIRMED` | AC-CNS-04 | Unit |
| **FR-034** | Procurement Directorate View | Procurement Officer | Consolidated Views | Processing | `CONFIRMED CONCEPT` | AC-PRC-01 | Functional |
| **FR-035** | Handover to Processing | Procurement Officer | Requisitions | Processing | `CONFIRMED` | AC-PRC-02 | Functional |
| **FR-036** | GHANEPS Boundary | Procurement Officer | Packages | Interface | `CONFIRMED` | AC-INT-01 | Integration |
| **FR-037** | Status Tracking | Requester | Requisitions | Tracking | `CONFIRMED` | AC-TRK-01 | Functional |
| **FR-038** | Workflow History | All Stakeholders | Workflow History | Tracking | `CONFIRMED` | AC-TRK-02 | Functional |
| **FR-039** | Audit Record Protection | System / Auditor | Audit Logs | Audit | `CONFIRMED` | AC-AUD-01 | Security / DB |
| **FR-040** | Searchable Records | All Authorized | Requisitions, Items | Archive | `CONFIRMED` | AC-REP-01 | Functional |
| **FR-041** | Delivery Recording | Receiving Officer | Delivery Records | Delivery | `CONFIRMED (PROPOSAL)`| AC-DLV-01 | Functional |
| **FR-042** | Consumption Records | System / Planning | Consumption Records | Consumption | `CONFIRMED` | AC-CSM-01 | Functional |
| **FR-043** | Management Reporting | Management / Finance | Reports | Reporting | `CONFIRMED` | AC-REP-02 | Functional |
| **FR-044** | Multi-Parameter Search | All Users | Database Indices | Search | `PROPOSED / TBC` | AC-SRH-01 | Functional |
| **FR-045** | Workflow Notifications | All Stakeholders | Notifications | Alerting | `PROPOSED / TBC` | AC-NTF-01 | Functional |
| **FR-046** | Supporting Attachments | Requester / Approver | Attachments | Attachments | `PROPOSED / TBC` | AC-ATT-01 | Functional |
| **FR-047** | Role Dashboards | Authenticated Users | Dashboards | UI | `PROPOSED` | AC-DSH-01 | UI / UX |
| **FR-048** | System Administration | Administrator | System Master Data | Admin | `CONFIRMED CONCEPT` | AC-ADM-01 | Functional |
| **FR-049** | Quarterly Plan Review | Planning / Approver | Procurement Plans | Review | `CONFIRMED BY CLIENT / PROCEDURE TO BE CONFIRMED` | AC-REV-01 | Functional |
| **FR-050** | Plan Revision & Versioning | Planning / Approver | Plan Versions | Review | `CONFIRMED BY CLIENT / VERSIONING DETAILS TO BE CONFIRMED` | AC-REV-02 | Functional / DB |
| **FR-051** | Plan Version Association | Requester / System | Requisitions, Plan Versions | Requisition | `PROPOSED / TO BE CONFIRMED` | AC-REQ-08 | Functional / DB |

---

## 2. Non-Functional Requirements Traceability Matrix

| Req ID | Category | Summary Focus | Status | Verification Method |
| :--- | :--- | :--- | :--- | :--- |
| **NFR-001** | Security | Defense-in-depth architecture | `CONFIRMED TECHNICAL` | Security Review / Penetration Test |
| **NFR-002** | Security | Cryptographic password hashing | `CONFIRMED` | Code Inspection / Unit Test |
| **NFR-003** | Security | Mandatory server-side authorization | `CONFIRMED` | Automated Access Control Tests |
| **NFR-004** | Integrity | Relational integrity & foreign keys | `CONFIRMED` | Database Schema Constraint Tests |
| **NFR-005** | Audit | Protection of audit records | `CONFIRMED` | Database Permission & Security Tests |
| **NFR-006** | Reliability | Multi-table transaction atomicity | `CONFIRMED TECHNICAL` | Stress / Rollback Failure Tests |
| **NFR-007** | Performance | Sub-second response benchmarks | `TBC BENCHMARKS` | Load Testing / Benchmark Scripts |
| **NFR-008** | Scalability | Dynamic support for ~62 entities | `CONFIRMED DESIGN` | Architectural Review |
| **NFR-009** | Maintainability | Modular Core PHP 8.x, no heavy FW | `CONFIRMED` | Code Quality Gate / Linting |
| **NFR-010** | Usability | Non-technical staff intuitive use | `CONFIRMED` | User Acceptance Testing (UAT) |
| **NFR-011** | Mobile | Mobile-first responsive layouts | `CONFIRMED` | Viewport Testing (375px to 1920px) |
| **NFR-012** | Accessibility | Keyboard nav, multi-modal status | `PROPOSED STANDARD`| Accessibility Audit |
| **NFR-013** | UI | Cross-module visual consistency | `CONFIRMED` | UI Design System Inspection |
| **NFR-014** | Cognitive | Progressive disclosure, Miller's law | `CONFIRMED UX` | Expert Cognitive Walkthrough |
| **NFR-015** | Errors | Safe user error messages | `CONFIRMED` | Error Injection Testing |
| **NFR-016** | Logging | Secure diagnostic error logs | `CONFIRMED TECHNICAL` | Log Inspection |
| **NFR-017** | Backup | Database backup & disaster recovery | `CONFIRMED CONCEPT` | Backup / Restore Simulation |
| **NFR-018** | Security | Session cookies & fixation defense | `CONFIRMED TECHNICAL` | Session Security Audit |
| **NFR-019** | Compliance | Statutory data retention | `TBC RETENTION` | Policy Alignment Review |
| **NFR-020** | Compatibility | Modern browser compatibility | `CONFIRMED CONCEPT` | Cross-Browser Testing |
| **NFR-021** | Deployment | XAMPP / Apache / PHP 8.x hosting | `CONFIRMED CONCEPT` | Deployment Smoke Test |
| **NFR-022** | Security | Secure file upload validation & UUID | `CONFIRMED / PROPOSED`| Upload Attack Vector Testing |
| **NFR-023** | Reliability | Critical state transition safety | `CONFIRMED TECHNICAL` | State Machine Boundary Tests |
| **NFR-024** | Export | Secure data export to Excel / PDF | `TO BE CONFIRMED` | Export Content Integrity Check |
| **NFR-025** | Concurrency | Concurrency locking & collision guards | `CONFIRMED DESIGN` | Parallel Submission Stress Test |
