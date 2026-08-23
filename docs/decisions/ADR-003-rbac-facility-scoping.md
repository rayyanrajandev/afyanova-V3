# ADR-003: Multi-Facility Scoped Role-Based Access Control (RBAC)

## Status
**Accepted**

## Context
Hospital organizations frequently operate multiple physical facilities (e.g. Main Referral Hospital, Suburban Outpatient Clinic, Diagnostic Centre). Healthcare staff often have roles restricted to specific facilities or departments (e.g. a doctor practicing only at Clinic A, or a pharmacist assigned specifically to the Inpatient Pharmacy).

## Decision
We implement a **Context-Aware Scoped RBAC System** where role assignments can be:
1. **Global Tenant Scope**: (e.g., Tenant Super Admin, Group Financial Controller)
2. **Facility-Scoped**: (e.g., Medical Officer at Branch B only)
3. **Department-Scoped**: (e.g., Lab Technician in Hematology Lab only)

Authorization checks evaluate `$user->hasPermission('permission.slug', $facilityId, $departmentId)` server-side within Laravel Policies and Form Requests.

## Consequences
### Positive:
- Granular control matching real-world hospital operational hierarchies.
- Prevents unauthorized cross-facility access while enabling staff roaming where permitted.
- Clean audit trails attributing actions to explicit facility locations.

### Negative:
- Permission evaluation requires checking both global and facility-scoped role assignment joins (optimized via Redis user permission caching).
