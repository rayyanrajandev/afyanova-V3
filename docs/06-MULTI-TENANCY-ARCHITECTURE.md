# AfyaNova V3 — Multi-Tenancy Architecture & Data Isolation

## 1. Architectural Strategy & Evaluation

AfyaNova V3 is designed from the ground up as a **Multi-Tenant SaaS Platform**. We evaluated three architectural isolation strategies:

| Strategy | Operational Complexity | Cost Efficiency | Schema Migrations | Isolation Strength | Decision |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Database-per-Tenant** | Extremely High (1000s of DB connections, high RAM) | Poor (expensive minimum resource per tenant) | Slow & Fragile (must run 1000s of individual migrations) | Total Physical Isolation | **Rejected** (Unnecessary operational overhead) |
| **Schema-per-Tenant** | Moderate (PostgreSQL search_path switching) | Moderate | Complex migration coordination across schemas | Logical Schema Boundary | **Rejected** (PostgreSQL table limit & connection pool bloat) |
| **Shared Database with PostgreSQL RLS + Application Scoping** | Low (Single connection pool, unified migrations) | **Optimal** (High density, scalable pooling) | **Fast & Instant** (Single standard migration run) | **Cryptographically & Engine-level Enforced** | **SELECTED** |

### Selected Strategy: Shared Database with RLS & Multi-Layer Scoping
AfyaNova V3 uses a **single PostgreSQL database with Row-Level Security (RLS)** reinforced by **Eloquent Global Scopes** and **Tenant Context Middleware**. This provides military-grade data isolation while keeping deployments simple, cost-effective, and easy to maintain.

---

## 2. Organizational Hierarchy

```
┌────────────────────────────────────────────────────────┐
│                        TENANT                          │
│             (e.g., Aga Khan Health Services)           │
└───────────────────────────┬────────────────────────────┘
                            │ 1:N
┌───────────────────────────▼────────────────────────────┐
│                       FACILITY                         │
│       (e.g., Dar Hospital, Arusha Clinic, Mwanza)       │
└───────────────────────────┬────────────────────────────┘
                            │ 1:N
┌───────────────────────────▼────────────────────────────┐
│                      DEPARTMENT                        │
│          (e.g., OPD, IPD, Pharmacy, Lab, Radiology)    │
└───────────────────────────┬────────────────────────────┘
                            │ 1:N
┌───────────────────────────▼────────────────────────────┐
│                    SERVICE POINT                       │
│    (e.g., Triage Desk 1, Main Cashier, Sample Room)    │
└────────────────────────────────────────────────────────┘
```

- **Tenant**: The commercial customer boundary. Billing, user pools, and enterprise configuration belong here.
- **Facility**: Physical site/branch. Encounters, beds, stock locations, and daily cash registers belong here.
- **Department**: Clinical/administrative unit within a facility.
- **Service Point**: Specific physical station within a department.

---

## 3. Tenant Resolution & Lifecycle

### Resolution Mechanisms:
1. **Subdomain Identification**: `https://{tenant-slug}.afyanova.co.tz`
2. **Custom Domain**: `https://portal.kairukihospital.co.tz` mapped via CNAME to tenant registry.
3. **API Bearer Token**: In mobile/external integration APIs, the tenant is cryptographically extracted from the Sanctum authentication token.

### Execution Lifecycle:
```
Incoming HTTP Request
       │
       ▼
[TenantResolutionMiddleware]
  1. Identifies Tenant from Host / Token
  2. Verifies Tenant status is 'Active'
  3. Binds Tenant instance to Laravel Service Container (`app(TenantContext::class)`)
  4. Sets PostgreSQL RLS session variable:
     DB::statement("SET LOCAL app.current_tenant_id = ?", [$tenant->id]);
  5. Configures Tenant-Scoped Redis Prefix & File Storage root
       │
       ▼
[FacilityScopeMiddleware]
  1. Resolves active Facility from User session or Header `X-Facility-ID`
  2. Verifies User is assigned to this Facility
  3. Binds active Facility to Service Container (`app(FacilityContext::class)`)
       │
       ▼
Application Controllers / Domain Actions Execute
       │
       ▼
Database Query Execution (RLS + Global Scope Active)
```

---

## 4. Multi-Layer Isolation Defense

### Layer 1: PostgreSQL Row-Level Security (RLS)
The database engine automatically appends tenant checks to every query:
```sql
CREATE POLICY tenant_isolation_policy ON patients
    FOR ALL
    USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID)
    WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::UUID);
```

### Layer 2: Eloquent Model Trait (`BelongsToTenant`)
Every tenant-aware Eloquent model automatically applies global query filtering and sets `tenant_id` upon creation:

```php
namespace App\Core\Traits;

use App\Core\Context\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = app(TenantContext::class)->getTenantId()) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && $tenantId = app(TenantContext::class)->getTenantId()) {
                $model->tenant_id = $tenantId;
            }
        });
    }
}
```

### Layer 3: Cache Isolation (Redis)
All Redis cache keys are prefixed with the active tenant ID:
```php
Cache::tags(["tenant:{$tenantId}"])->remember("patient:{$patientId}", 3600, fn() => ...);
```

### Layer 4: Queue Job Isolation
When a background job is dispatched, the active tenant ID is serialized onto the job payload. When the worker picks up the job, it initializes the `TenantContext` and sets PostgreSQL RLS variables before executing the job.

### Layer 5: File & Document Storage Isolation
Patient medical attachments, scans, and export PDFs are stored under isolated path prefixes:
```text
storage/app/tenants/{tenant_id}/facilities/{facility_id}/documents/{patient_id}/...
```

---

## 5. Security Invariant: Zero Client Trust
The frontend never submits `tenant_id` in request payloads. If a client attempts to supply a `tenant_id` in a form or API call, it is stripped and ignored; the server-side resolved context is the sole authoritative source.
