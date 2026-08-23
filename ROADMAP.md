# AfyaNova V3 — Implementation Roadmap

This roadmap defines the sequential execution phases for the greenfield AfyaNova V3 build. Development follows a **Vertical-Slice** methodology where applicable, ensuring functional end-to-end capabilities rather than isolated backend logic.

---

## Phase 0 — Research and Architecture [CURRENT]
- [x] Analyze open-source reference systems (Bahmni, OpenMRS, OpenEMR, GNU Health).
- [x] Document Domain-Driven Design (DDD) model & bounded contexts.
- [x] Define System, Database, and Multi-Tenancy Architecture.
- [x] Define RBAC, Security, and Audit Architecture.
- [x] Define Clinical, Billing, Inventory, and Insurance Architecture.
- [x] Establish Architecture Decision Records (ADRs).
- [ ] Receive Master Architecture Approval.

---

## Phase 1 — Foundation (Identity, Tenancy & Security)
1. **Core Setup**: Laravel 12+, Vue 3 / Inertia setup, PostgreSQL 18+ DB initialization.
2. **Tenancy**: `Tenant`, `Facility`, and `Department` models with PostgreSQL RLS and Eloquent Global Scopes.
3. **Identity & Auth**: `User`, `Role`, `Permission`, Sanctum Auth, Argon2id, TOTP MFA.
4. **Audit Foundation**: Immutable `audit_logs` table and Trait interceptors.
5. **Testing & CI**: PHPStan, Pest configuration, GitHub Actions workflow.

---

## Phase 2 — Patient Identity & Demographics
1. **Patient Registry**: Core demographics, multiple identifier generation (MRN, NIDA).
2. **Patient Relationships & Contacts**: Next of kin, emergency contacts, guarantors.
3. **Patient De-duplication**: Patient search, fuzzy matching, and merge functionality.

---

## Phase 3 — Core Clinical (Outpatient Journey)
1. **Encounter Management**: Check-in, OPD encounter lifecycle states.
2. **Clinical Charting (SOAP)**: Vitals collection, Clinical Notes (Subjective, Objective, Assessment, Plan) with Immutability/Amendment logic.
3. **Diagnosis & Allergies**: ICD-10 Diagnosis coding and active allergy registry.
4. **Clinical Orders Engine**: Base `ClinicalOrder` for downstream domains.

---

## Phase 4 — Scheduling and Patient Flow
1. **Appointments**: Provider schedules, slot booking.
2. **Queue Management**: Triage ticketing, waitlist prioritization, service point transfers.

---

## Phase 5 — Laboratory & Diagnostics
1. **Lab Catalog**: Tests, Panels, Reference Ranges, Parameters.
2. **Lab Workflow**: Order ingestion, specimen collection, barcoding.
3. **Result Entry**: Tech input, Panic/Critical value alerts, verification sign-off.

---

## Phase 6 — Billing & Financial Ledger
1. **Charge Master**: Base service catalog, pricing configurations.
2. **Financial Ledger**: Double-entry ledger core (`financial_ledger_entries`), strict debit/credit enforcement.
3. **Invoicing**: Automatic generation from clinical orders, patient co-pay calculations.
4. **Cashier POS**: Payment receipts (Cash, M-Pesa), refunds, daily shift reconciliation.

---

## Phase 7 — Inventory & Procurement
1. **Product Catalog**: Medical and non-medical stock items.
2. **Stock Management**: Locations, Batches, Expiry tracking.
3. **Movements & Ledger**: Goods receipts, transfers, adjustments via double-entry stock movements.
4. **Procurement**: Purchase Orders and Supplier management.

---

## Phase 8 — Pharmacy & Dispensing
1. **Prescription Authoring**: Electronic prescribing with dosage/duration rules and allergy interaction checks.
2. **Dispensing Workflow**: FEFO batch allocation, label printing.
3. **Stock Decrement Integration**: Triggering physical stock movements upon clinical dispense.

---

## Phase 9 — Inpatient & Bed Management
1. **Ward/Bed Topology**: Ward definitions, bed availability mapping.
2. **Admission Lifecycle**: Admission, ward transfers, and discharge disposition.
3. **Inpatient Nursing**: Vitals flowsheets, Medication Administration Records (MAR).
4. **Bed Billing**: Automated daily midnight room/board charge generation.

---

## Phase 10 — Insurance & Claims Management
1. **Insurance Registry**: Providers, Schemes, Corporate Contracts.
2. **Eligibility & Policy**: Patient policy cards and benefit limits.
3. **Pre-Authorization**: E-Auth workflow mapping.
4. **Claims Batching**: Claim generation scrubber, e-claim batch exports/API integrations.
5. **Remittance**: Claim adjudication response and accounts receivable reconciliation.

---

## Phase 11 — Integrations & Interoperability
1. **Mobile Money**: M-Pesa STK Push and callback endpoints.
2. **FHIR R4**: FHIR Facade Mappers for `Patient`, `Observation`, `Condition`.
3. **Lab Analyzers**: ASTM/HL7 Edge Agent hooks.
4. **Notifications**: SMS API dispatching via Redis queues.

---

## Phase 12 — Enterprise Hardening & Observability
1. **Performance Tuning**: Index optimization, query profiling, caching strategies.
2. **Observability**: OpenTelemetry, Prometheus metrics, Log aggregation.
3. **Scaling**: Load balancer configuration, read replicas (if required).
4. **Disaster Recovery**: Automated snapshot testing, point-in-time recovery validation.
