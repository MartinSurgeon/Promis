# PROMIS Architecture Specification: Organizational Architecture

## 1. Architectural Model & Scale

PROMIS is architected to dynamically support the University's organizational structure, comprising approximately **62 planning entities** across multiple campuses and directorates.

Because the authoritative roster of entities, entity codes, and reporting hierarchies has not yet been delivered by the University (`STATUS: PENDING DATA`), the organizational architecture is designed as a **dynamic, data-driven model**. 

### Architectural Rule
**No planning entity names, codes, campuses, heads, or organizational hierarchies shall be hard-coded into application logic, configuration files, or database seeds.** All organizational structures are treated as runtime-configurable master data.

---

## 2. Planning Entity Domain Model

The conceptual organizational model captures the hierarchical, spatial, and administrative relationships required for procurement planning, approvals, and consolidation:

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                                   CAMPUS                                    │
│                     (Main Campus, City Campus, etc.)                        │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ 1
                                       ▼ ∞
┌─────────────────────────────────────────────────────────────────────────────┐
│                               PLANNING ENTITY                               │
│  - Entity Name (e.g. Department of Computer Science)                       │
│  - Entity Code (e.g. CS-001)                                                │
│  - Campus Reference (Logical Association)                                   │
│  - Entity Type (Department, Directorate, School, Unit, Hall)                │
│  - Parent Entity Reference (Self-referencing logical hierarchy)              │
│  - Head User Reference (Designated HoD, Dean, or Director)                  │
│  - Planning Officer User Reference (Designated Plan Preparer)               │
│  - Approving Authority Reference (Designated Approval Role / Office)        │
│  - Active Status (Active / Soft-Deactivated)                                │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ 1
                         ┌─────────────┴─────────────┐
                         ▼ ∞                         ▼ ∞
                [PROCUREMENT PLANS]            [REQUISITIONS]
```

### Key Architectural Attributes
1. **Hierarchical Self-Reference (`parent_entity_id`)**: Allows modeling multi-level organizational trees (e.g. Department → Faculty/School → Directorate) to support rollup reporting and multi-tier approval escalations.
2. **Entity Classification (`entity_type`)**: Distinguishes between academic departments, administrative directorates, service units, and residential halls, enabling type-specific workflow rules where required.
3. **Campus Association (`campus_id`)**: Preserves physical location metadata necessary for logistics, consolidated packaging, delivery receipts, and consumption analytics.
4. **Designated Officers (`head_user_id`, `planning_officer_id`)**: Identifies primary organizational actors without tightly coupling authority to personal user identities.

---

## 3. Organizational Governance & Lifecycle

### Dynamic Entity Management
Authorized administrators can add, update, or reorganize planning entities through administrative consoles. The system adapts automatically to institutional reorganizations, faculty mergers, or new departmental creations without requiring application code changes (satisfying **NFR-008**).

### Soft Deactivation & Historical Integrity
When an organizational unit is dissolved, renamed, or restructured:
- The entity record is marked as `is_active = FALSE`.
- Inactive entities are immediately excluded from new procurement planning and requisition drop-downs.
- **Historical Integrity**: All historical procurement plans, plan revisions, requisitions, consolidation records, and audit logs associated with the entity remain completely intact, accessible, and queryable.
