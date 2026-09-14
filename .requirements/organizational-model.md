# PROMIS Requirements: Organizational Model

## 1. Overview & Scale

The University has officially indicated that PROMIS must support approximately **62 planning entities** distributed across its campuses and administrative directorates.

The exact, authoritative list of the 62 planning entities, their official codes, and reporting structures has not yet been delivered by the University (`STATUS: PENDING DATA`).

### Core Architectural Principle
Because the official roster of entities is pending, **PROMIS shall never hard-code planning entities, campuses, or organizational hierarchies in application source code**. All organizational structures must be modeled as dynamic, configurable master data managed via administrative interfaces or controlled database configuration.

---

## 2. Planning Entity Types

In the USTED organizational hierarchy, planning entities span multiple administrative classifications:
1. **Academic Departments** (e.g. Department of Computer Science, Department of Electrical Engineering)
2. **Directorates** (e.g. Directorate of Procurement, Directorate of Finance, Directorate of ICT)
3. **Faculties / Schools** (e.g. School of Engineering, Faculty of Applied Sciences)
4. **Administrative Units / Sections** (e.g. Transport Unit, Security Section, Estate Organization)
5. **Halls of Residence** (e.g. Student halls and residential colleges)
6. **Executive Offices** (e.g. Vice-Chancellor's Office, Registrar's Office)

---

## 3. Planning Entity Data Structure

To accommodate the full operational needs of procurement planning, requisitioning, and approval routing, the Planning Entity structure must capture:

| Attribute | Description | Data Constraint | Status |
| :--- | :--- | :--- | :--- |
| **Entity ID** | Unique system identifier | Auto-increment / Primary Key | `CONFIRMED` |
| **Entity Name** | Full official title of the entity | String, Unique, Mandatory | `CONFIRMED` |
| **Entity Code** | Official University organizational/cost-center code | Alphanumeric, Unique, Mandatory | `PENDING DATA` |
| **Campus** | Campus location where entity operates | Foreign Key / Lookup, Mandatory | `CONFIRMED` |
| **Entity Type** | Classification (Department, Directorate, Unit, Hall) | Enum / Lookup, Mandatory | `CONFIRMED` |
| **Parent Entity ID** | Reporting directorate or faculty (hierarchical parent) | Foreign Key, Nullable | `CONFIRMED` |
| **Head of Entity** | Staff member designated as official Head (HoD/Dean/Director) | Foreign Key to Users, Nullable | `CONFIRMED` |
| **Planning Officer** | Staff member designated to prepare procurement plans | Foreign Key to Users, Nullable | `CONFIRMED` |
| **Approving Authority** | Designated approval office/role for the entity | Foreign Key to Roles / Approvers | `TO BE CONFIRMED` |
| **Is Active** | Operational status flag for soft deactivation | Boolean (Default: Active) | `CONFIRMED` |

---

## 4. Multi-Campus Representation

USTED operates across multiple campuses and geographic locations. The organizational model must capture campus affiliations:
- Each planning entity must be mapped to its primary campus.
- Requisitions and consolidated procurement packages must retain campus identifiers to support logistics, delivery, and consumption tracking.
- Status of authoritative campus list: `PENDING DATA`.

---

## 5. Organizational Governance & Business Rules

### BR-ORG-001: Configurable Maintenance
System administrators shall be able to create, update, and soft-deactivate planning entities without system downtime or code deployment.  
*Status*: `CONFIRMED`

### BR-ORG-002: Immutability of Historical Transactions
Deactivating a planning entity shall prevent new requisitions or procurement plans from being submitted under that entity, but shall **never** modify or delete existing historical records, approved plans, or audit logs.  
*Status*: `CONFIRMED`

### BR-ORG-003: Hierarchical Aggregation
The system shall support parent-child organizational relationships (e.g. Department → School → Directorate) to facilitate hierarchical review of procurement plans and departmental demand consolidation.  
*Status*: `CONFIRMED DESIGN PRINCIPLE`

---

## 6. Open Information Required from the University

1. The authoritative master spreadsheet or database export containing the official 62 planning entities with their codes and parent directorates. (`PENDING DATA`)
2. The official list of university campuses and satellite centers. (`PENDING DATA`)
3. The official mapping of designated Heads and Planning Officers for each of the 62 entities. (`PENDING DATA`)
