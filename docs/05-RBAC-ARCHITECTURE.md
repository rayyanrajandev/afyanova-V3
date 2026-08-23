# AfyaNova V3 — Role-Based Access Control (RBAC) Architecture

## 1. Architectural Principles

1. **Server-Side Enforcement**: Authorization is strictly enforced on the server within Laravel Policies, Form Requests, and Domain Actions. Frontend routing, menu visibility, and disabled buttons are UX conveniences, never security boundaries.
2. **Context-Aware Scoping**: Permissions are evaluated against three dimensions:
   - **Tenant Scope**: Cross-tenant actions are forbidden.
   - **Facility Scope**: A clinician or cashier can be restricted to specific branch locations (e.g. Dar es Salaam Branch vs. Arusha Branch).
   - **Department Scope**: Staff can be restricted to specific departments (e.g. Main Pharmacy vs. Outpatient Pharmacy).
3. **No Universal Clinical Bypass**: Administrative roles (e.g. IT Super Admin) cannot bypass clinical safety rules (e.g., dispensing medication without a valid prescription or signing clinical notes without a registered medical practitioner license).
4. **Fine-Grained Permissions**: Permissions follow a standardized `domain.resource.action` naming convention (e.g. `clinical.encounter.create`, `pharmacy.dispense.authorize`, `billing.discount.approve`).

---

## 2. RBAC Data Model

```
┌──────────────┐         ┌───────────────────────┐         ┌──────────────┐
│    User      │1       *│ UserFacilityAssignment│*       1│   Facility   │
│              ├─────────┤ (facility_id, is_def) ├─────────┤              │
└──────┬───────┘         └───────────────────────┘         └──────────────┘
       │1                                                          ▲
       │*                                                          │
┌──────▼────────────────────────────────────────────────────────┐  │
│                     UserRoleAssignment                        │  │
│ - user_id                                                     │  │
│ - role_id                                                     │  │
│ - facility_id (NULL = all tenant facilities) ─────────────────┘  │
│ - department_id (NULL = all departments)                         │
└──────┬───────────────────────────────────────────────────────────┘
       │*
       │1
┌──────▼───────┐         ┌───────────────────────┐         ┌──────────────┐
│    Role      │1       *│    RolePermission     │*       1│  Permission  │
│ (tenant_id)  ├─────────┤ (role_id, perm_id)   ├─────────┤ (slug, domain│
└──────────────┘         └───────────────────────┘         └──────────────┘
```

---

## 3. Standard System Roles & Permission Matrix

AfyaNova V3 ships with battle-tested default roles pre-configured with clinical and operational best practices:

| Role Name | Domain Scope | Key Permissions & Responsibilities |
| :--- | :--- | :--- |
| **Tenant Administrator** | Tenant-wide | User provisioning, role assignment, facility setup, master charge catalog configuration, audit logs review. *(Cannot sign clinical notes or dispense drugs).* |
| **Medical Officer / Clinician** | Facility / Department | View patient history, create encounters, record clinical notes, add diagnoses, place lab/radiology orders, write prescriptions. |
| **Nurse / Triage Officer** | Facility / Department | Record triage vitals, administer scheduled medications (MAR), triage queue management, inpatient bed nursing notes. |
| **Laboratory Scientist / Tech** | Facility / Lab Dept | Access lab worklists, collect/receive specimens, input test results, review reference ranges, verify & publish lab reports. |
| **Pharmacist** | Facility / Pharmacy Dept | Review clinical prescriptions, perform drug allergy/interaction verification, dispense medications, manage pharmacy stock. |
| **Cashier / Billing Officer** | Facility / Cashier Dept | Generate patient invoices, collect payments (Cash, M-Pesa, Card), issue receipts, view daily cashier reconciliation. |
| **Billing / Insurance Manager** | Tenant-wide | Manage insurance tariffs, prepare and submit claims batches, verify pre-authorizations, reconcile remittances, approve discounts/write-offs. |
| **Inventory / Store Officer** | Facility / Store Dept | Receive purchase orders (GRN), perform stock transfers between locations, conduct stock takes, initiate stock adjustments. |
| **Receptionist / Registration Clerk**| Facility / Reception | Search and register patients, update demographic contacts, book appointments, issue queue tickets, verify insurance cards. |
| **Medical Auditor / Compliance** | Tenant-wide | Read-only access to clinical charts, billing ledgers, audit trail records, and regulatory compliance reports. |

---

## 4. Policy & Middleware Implementation Pattern

### 4.1. The Authorization Context
Every incoming HTTP request resolves the active **Authorization Context**:
- `activeUser`: The authenticated `User`
- `activeTenant`: The resolved `Tenant`
- `activeFacility`: The user's currently selected `Facility`

### 4.2. Laravel Policy Example: Clinical Encounter Authorization
```php
namespace App\Domains\Clinical\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Services\AuthorizationService;

class EncounterPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function view(User $user, Encounter $encounter): bool
    {
        return $this->auth->hasPermission($user, 'clinical.encounter.view', $encounter->facility_id);
    }

    public function update(User $user, Encounter $encounter): bool
    {
        // Must have permission in the encounter's facility
        if (!$this->auth->hasPermission($user, 'clinical.encounter.update', $encounter->facility_id)) {
            return false;
        }

        // Encounter must not be completed or closed
        if ($encounter->isClosed()) {
            return false;
        }

        // Attending clinician or authorized supervisor
        return $encounter->provider_id === $user->id 
            || $this->auth->hasPermission($user, 'clinical.encounter.override', $encounter->facility_id);
    }

    public function signNotes(User $user, Encounter $encounter): bool
    {
        // Enforce professional license requirement
        return !empty($user->professional_registration_no)
            && $this->auth->hasPermission($user, 'clinical.notes.sign', $encounter->facility_id);
    }
}
```

---

## 5. Defense-in-Depth Authorization Pipeline

```
1. Request arrives (e.g. POST /api/clinical/encounters/{id}/prescriptions)
   │
2. Authenticate Middleware (Sanctum / Session Guard)
   │
3. Tenant & Facility Context Middleware (Validates user has active assignment to facility)
   │
4. FormRequest Authorization (`authorize()` checks role permission)
   │
5. Controller calls Laravel Policy (`$this->authorize('prescribe', $encounter)`)
   │
6. Domain Action validates domain-level safety invariants (e.g. Patient not deceased)
   │
7. Database execution with Row-Level Security active
```

---

## 6. Auditability of Authorization Changes

All changes to roles, permission assignments, user facility assignments, and account privilege elevations are logged directly to the immutable `audit_logs` table with previous state and new state snapshots.
