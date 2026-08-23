# AfyaNova V3 — System Architecture & Modular Monolith Design

## 1. Architectural Style: Modular Monolith

AfyaNova V3 is architected as a **Modular Monolith**. It runs as a single deployable Laravel application while enforcing strict internal domain boundaries.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          PRESENTATION LAYER                                 │
│  Vue 3 + TypeScript + Inertia.js + Tailwind CSS + shadcn-vue (SPA UX)       │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ HTTP / Inertia Protocol / JSON
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                           HTTP & ROUTING LAYER                              │
│   Middleware: TenantContext, FacilityContext, Authenticate, Authorize, Rate │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                           BOUNDED DOMAIN MODULES                            │
│  app/Domains/                                                               │
│  ├── Identity/       ├── Patient/        ├── Clinical/     ├── Lab/         │
│  ├── Pharmacy/       ├── Inventory/      ├── Inpatient/    ├── Billing/     │
│  ├── Insurance/      ├── Scheduling/     ├── Audit/        └── Integration/ │
│                                                                             │
│  Each Domain encapsulates:                                                  │
│  - Models & Scopes (Encapsulated Eloquent Entities)                        │
│  - Actions / Services (Pure Business Logic)                                │
│  - Events & Listeners (Cross-Domain Decoupling)                             │
│  - DTOs (Data Transfer Objects & Contracts)                                 │
│  - Policies & Authorizers (Fine-Grained Permissions)                        │
│  - Controllers & Form Requests                                              │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                    INFRASTRUCTURE & PERSISTENCE LAYER                       │
│  - PostgreSQL 18+ (Relational, RLS, Double-Entry Ledgers, JSONB Extensions) │
│  - Redis (Queues, Session, Fast Tenant Cache, Real-time Channels)           │
│  - Object Storage / S3 (Encrypted Document & Medical Imaging Attachments)   │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Directory Structure & Domain Organization

To ensure architectural clarity, all application logic is organized by **Domain**, rather than generic framework folders.

```text
afyanova-v3/
├── app/
│   ├── Core/                           # Shared infrastructure, base classes, utilities
│   │   ├── Contracts/                  # Global interface contracts
│   │   ├── Exceptions/                 # Domain exception handlers
│   │   ├── Middleware/                 # TenantContext, FacilityScope, AuditContext
│   │   ├── Traits/                     # HasUlids, BelongsToTenant, Auditable
│   │   └── ValueObjects/               # Money, Quantity, Address, DateRange
│   │
│   ├── Domains/                        # Bounded Context Modules
│   │   ├── Identity/
│   │   ├── Patient/
│   │   ├── Clinical/
│   │   ├── Laboratory/
│   │   ├── Pharmacy/
│   │   ├── Inventory/
│   │   ├── Billing/
│   │   ├── Insurance/
│   │   ├── Inpatient/
│   │   ├── Scheduling/
│   │   ├── Integration/
│   │   └── Audit/
│   │       ├── Actions/                # Single-responsibility business actions
│   │       ├── Contracts/              # Public interface exported to other domains
│   │       ├── DataTransferObjects/    # Type-safe DTOs for inputs/outputs
│   │       ├── Events/                 # Domain events (e.g. OrderPlaced)
│   │       ├── Exceptions/             # Domain-specific exceptions
│   │       ├── Http/
│   │       │   ├── Controllers/        # Inertia & API controllers
│   │       │   ├── Requests/           # Strict FormRequest validation
│   │       │   └── Resources/          # API resource transformers
│   │       ├── Listeners/              # Handlers reacting to internal/external events
│   │       ├── Models/                 # Eloquent entities
│   │       ├── Policies/               # Authorization policies
│   │       ├── Services/               # Complex multi-step domain services
│   │       └── Database/
│   │           ├── Migrations/         # Domain-owned database migrations
│   │           ├── Factories/          # Test factories
│   │           └── Seeders/            # Reference data seeders
│   │
├── resources/
│   ├── js/
│   │   ├── Components/                 # Shared UI components (shadcn-vue primitives)
│   │   │   ├── ui/                     # Button, Dialog, Dropdown, Table, Input
│   │   │   └── clinical/               # VitalsWidget, AllergyBadge, PatientBanner
│   │   ├── Domains/                    # Domain-specific Vue views and components
│   │   │   ├── Patient/                # Registration, Search, Profile, Demographics
│   │   │   ├── Clinical/               # Consultation, EncounterView, Orders, Vitals
│   │   │   ├── Laboratory/             # SampleCollection, Worklist, ResultEntry
│   │   │   ├── Pharmacy/               # DispenseWorklist, PrescriptionDetails
│   │   │   ├── Inventory/              # StockBalances, TransferForm, BatchList
│   │   │   ├── Billing/                # InvoiceView, CashierPOS, TariffManager
│   │   │   └── Insurance/              # ClaimBatcher, PreAuthForm, Remittance
│   │   ├── Layouts/                    # AppLayout, AuthLayout, ClinicalLayout
│   │   ├── Composables/                # useTenant, usePermissions, useCurrency
│   │   └── Types/                      # TypeScript domain definitions
│   └── css/                            # Tailwind CSS 4+
│
├── config/                             # Application configurations
├── database/                           # Central migrations runner & seeders
├── routes/                             # Route definitions partitioned by domain
│   ├── web.php                         # Core web routes
│   ├── api.php                         # Internal REST API
│   ├── fhir.php                        # Interoperability FHIR R4 endpoints
│   └── domains/                        # Domain route files
├── tests/
│   ├── Unit/                           # Fast, isolated domain unit tests
│   ├── Feature/                        # HTTP & Inertia feature tests
│   ├── Invariants/                     # Domain invariant validation tests
│   └── Integration/                    # External API & adapter tests
└── docs/                               # Complete architectural documentation
```

---

## 3. Rules of Domain Modularization

### Rule 1: No Direct Cross-Domain Model Modification
A domain must not directly mutate the Eloquent models of another domain. For example, the `Pharmacy` domain must not execute `$invoice->update(['status' => 'paid'])`. Instead, it calls `BillingService::createInvoiceForDispense(...)` or dispatches `PrescriptionDispensedEvent`.

### Rule 2: Explicit Contracts for Inter-Domain Queries
When Domain A needs data from Domain B, Domain B exposes a `Contract` interface returning structured DTOs (e.g. `PatientLookupContract::getDemographics(PatientId $id): PatientSummaryDTO`).

### Rule 3: Event-Driven Side-Effects
Non-critical cross-domain side-effects must be decoupled using asynchronous Domain Events dispatched to Redis queues (e.g., `PatientRegisteredEvent` triggers SMS notification, insurance policy initialization, and welcome packet generation).

### Rule 4: Domain-Owned Migrations
Each domain owns its database schema migrations located in `app/Domains/{Domain}/Database/Migrations/`.

---

## 4. Frontend Architecture (Vue 3 + Inertia.js)

### Why Inertia.js + Vue 3?
1. **Monolithic Simplicity with SPA Interactivity**: Inertia.js eliminates the boilerplate of managing separate client-side state stores, OAuth token refresh flows, and duplicating validation rules across frontend and backend.
2. **Server-Driven Routing with Client-Side Speed**: Laravel controllers return `Inertia::render('Clinical/Consultation', $props)`, delivering server-rendered data with Vue 3 reactive components.
3. **Type-Safe Props**: TypeScript interfaces are generated from PHP DTOs and Form Requests, ensuring full end-to-end type safety.

### UI Design System: Tailwind CSS & shadcn-vue
- Built on **shadcn-vue** (Radix Vue primitives + Tailwind CSS), providing accessible, keyboard-navigable components (dialogs, popovers, comboboxes, data tables).
- Specialized clinical UI components (Patient Banner, Vital Sign Sparks, Drug Allergy Alert Modals) adhere to healthcare usability best practices.

---

## 5. Background Jobs, Queues & Scheduling

AfyaNova V3 relies on **Redis** for performant, tenant-aware background job processing:

| Queue Name | Purpose | Concurrency / Priority |
| :--- | :--- | :--- |
| `high` | Critical alerts (Panic Lab Results, Clinical Stat Orders) | Highest priority, processed immediately |
| `default` | Standard transactions (Billing Ledger post, PDF invoice generation) | Standard priority |
| `insurance` | External insurance API calls (NHIF claim submission, Pre-auth) | Rate-limited per insurer endpoint |
| `notifications` | Outbound SMS / Email (M-Pesa payment receipts, Appointment reminders) | Retried with exponential backoff |
| `audit` | Asynchronous high-volume audit logging | Batched persistence |

### Scheduled Maintenance Tasks
- **Midnight Daily Bed Charge Posting**: Evaluates all active inpatient admissions and posts daily bed accommodation charges to encounter invoices.
- **Batch Expiry Alerts**: Scans stock batches and flags items reaching expiration within 90/60/30 days.
- **Insurance Claim Timeout Reconciler**: Polls pending external insurance claims.

---

## 6. Infrastructure & Deployment Model

```
                         Internet / Clients
                                 │
                                 ▼
                     ┌───────────────────────┐
                     │   Nginx Reverse Proxy │ (SSL Termination, Rate Limit)
                     └───────────┬───────────┘
                                 │
                                 ▼
                     ┌───────────────────────┐
                     │   Laravel PHP-FPM     │ (PHP 8.5+ with OPcache)
                     │  Modular Monolith App │
                     └───────┬───────┬───────┘
                             │       │
              ┌──────────────┘       └──────────────┐
              ▼                                     ▼
   ┌───────────────────────┐             ┌─────────────────────┐
   │    PostgreSQL 18+     │             │     Redis 7+        │
   │ - Multi-Tenant RLS    │             │ - Queues & Jobs     │
   │ - Financial Ledger    │             │ - Session Storage   │
   │ - Inventory Movements │             │ - Tenant Cache      │
   └───────────────────────┘             └─────────────────────┘
```

- **Containerized Deployment**: Defined via standard `Dockerfile` and `docker-compose.yml` for local development, CI/CD, and production Kubernetes/Docker Swarm clusters.
- **Stateless Application Tier**: Laravel application containers are completely stateless; sessions and caches reside in Redis, files in S3/MinIO object storage, and relational data in PostgreSQL.
