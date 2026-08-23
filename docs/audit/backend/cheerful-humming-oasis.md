# AfyaNova V3 — Enterprise HIS Gap Closure: Implementation Plan

## Context

A four-way source audit (security/tenancy, clinical, financial/supply-chain, interoperability/ops) turned up 83 gaps between AfyaNova V3's documented architecture and its actual code, 17 of them Critical. The recurring pattern: the *documentation and domain modeling* are ahead of most projects at this stage, but the *invariants* the docs promise — tenant isolation, financial/clinical immutability, audit tamper-evidence — are held only by convention inside a single Action class each, not by anything that can't be bypassed elsewhere.

Per your direction, this plan is scoped in two layers:
- **Phase 0** (this session): the ~8 foundational blockers you selected, fully specified and executed now.
- **Phases 1–5** (future sessions): the remaining 66 findings, sequenced into a roadmap at the same granularity as the project's own `ROADMAP.md`/`NEXT_DEVELOPMENT_PHASES.md`, so each becomes its own focused session later.

**One discovery changes Phase 0's shape.** You asked to switch local dev to PostgreSQL so RLS can actually run. Investigating that surfaced a blocking prerequisite the audit only flagged as a Medium style issue: every model uses `HasUlids` (Crockford-base32 ULID strings), but 32 of 39 migrations declare `id`/`tenant_id`/FK columns as native Postgres `uuid` type (e.g. `patients.id`, `invoices.tenant_id` — see `app/Domains/Patient/Database/Migrations/2024_01_02_000010_create_patients_table.php:13-14`). SQLite's loose typing hides this today; on real Postgres, every insert on those tables would fail with `invalid input syntax for type uuid`. Fixing this is now step zero — and since the project's own `ARCHITECTURE_RULES.md` §8 / `ADR-007` already mandate UUIDv7 (not ULID) for exactly this column type, the correct fix is also the one the docs already call for: generate real UUIDv7s instead of ULIDs, not loosen the columns. This retires one more audited finding for free.

---

## Phase 0 — Foundational Blockers (this session)

### 0.0 Fix ID generation: ULID → UUIDv7 (new prerequisite, unblocks Postgres)
- Add `app/Core/Traits/HasUuidv7.php`: `use Illuminate\Database\Eloquent\Concerns\HasUuids;` as a base, override `newUniqueId()` to return `\Ramsey\Uuid\Uuid::uuid7()->toString()` (`ramsey/uuid` `^4.7` is already required and supports `uuid7()` — confirmed in `composer.lock`).
- Mechanically replace `use Illuminate\Database\Eloquent\Concerns\HasUlids;` + the `HasUlids` trait-use line with `App\Core\Traits\HasUuidv7` across every domain model (confirmed pattern in `Tenant.php`, `MedicationFormulary.php`, `RoleAssignment.php`, and ~90 others per audit). Grep for `HasUlids` after to confirm zero stragglers.
- Normalize the 6 migration files that declare `->ulid()` columns (e.g. `app/Domains/Clinical/Database/Migrations/2024_01_07_000010_create_lab_catalog_and_orders_tables.php`, which also uses `string('tenant_id')`) to `->uuid()`, matching the other 32 files, since these are pre-launch migrations that have never run against a real deployment.
- Delete `app/Providers/DomainServiceProvider.php` — confirmed dead code (never registered in `bootstrap/providers.php`); `app/Providers/AppServiceProvider.php:27-39` already loads all domain migrations via an inline equivalent loop. Keeping both invites someone to "fix" the wrong one later.

### 0.1 Local dev on PostgreSQL + reproducible deploy
- Add `Dockerfile` (multi-stage: composer install → npm build → php8.3-fpm-alpine runtime) and `docker-compose.yml` with `app`, `nginx`, `pgsql` (postgres:17-alpine, named volume, healthcheck), and `redis` (postgres:17-alpine — available for Phase 5, not wired into `.env` yet) services. This directly closes the "no reproducible way to deploy this system" Critical finding, not just the dev-DB need.
- Update `.env` and `.env.example`: `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Leave `QUEUE_CONNECTION`/`CACHE_STORE`/`SESSION_DRIVER` on `database` for now — Redis adoption is Phase 5, paired with Horizon.
- `docker compose up -d pgsql`, `php artisan migrate:fresh`, confirm the `DB::getDriverName() === 'pgsql'` branches in migrations now execute (RLS policies, e.g. `app/Domains/Clinical/Database/Migrations/2024_01_03_000013_create_diagnoses_table.php:40-46`, actually create).
- Note for the record: `phpunit.xml:26-27` pins the Pest/PHPUnit suite to SQLite `:memory:` regardless of `.env`, which is correct for speed but means the existing test suite still can't exercise RLS. Phase 0's verification step for RLS is a manual `artisan tinker`/`psql` check (below), not a new automated suite — a dedicated Postgres CI job is Phase 4 scope.

### 0.2 Facility-scoped authorization + fix the self-escalation hole
- Add `App\Core\Context\FacilityContext` (mirrors `TenantContext.php`), registered as a singleton in `AppServiceProvider::register()` next to the existing `TenantContext` binding.
- Add `app/Core/Middleware/FacilityScopeMiddleware.php`: resolves the acting facility (route param, `X-Facility-ID` header, or session) and verifies via `RoleAssignment` (`app/Domains/Identity/Models/RoleAssignment.php`) that the authenticated user has an assignment scoped to that facility or a global (`facility_id IS NULL`) assignment, before binding it into `FacilityContext`. Register in `bootstrap/app.php` right after `TenantContextMiddleware`.
- Fix `TenantContextMiddleware.php:22-43`: check `$request->user()?->tenant_id` **before** the `X-Tenant-ID` header for authenticated requests (currently the header wins), and drop the `Tenant::first()` local-env fallback in favor of failing closed with a log warning — there's no legitimate reason an authenticated session should ever need it.
- Fix `AuthorizationService.php:16` — replace the dead `$user->role === 'SuperAdmin'` check (no such column exists) with a real `isTenantAdmin(User $user): bool` helper built the same way `getUserPermissions()` already resolves role assignments, checking for a global-scope assignment to a role whose slug is `tenant-admin`.
- Fix `AccessControlWorkspaceController.php` (`assignRole`, `updatePermissions`, `assignFacility`): add explicit `AuthorizationService::hasPermission()` guards (`identity.roles.assign`, `identity.permissions.manage`) before the action executes, aborting with 403 otherwise. This is the direct fix for the self-escalation finding.
- Wire `$this->authorize()` into the highest-risk existing controllers as the reference pattern for Phase 1 to extend everywhere else: `BillingController` (pay/refund/addItem/discount → existing `InvoicePolicy.php`), `DispensingController` (verify/dispense), `InventoryWorkspaceController` (PO approval, DDA log), `ClinicalChartingController` (sign/amend note → extend `EncounterPolicy` or add `ClinicalNotePolicy`), `InsuranceWorkspaceController` (adjudicate).

### 0.3 Audit hash chaining
- Migration: add `previous_hash` (nullable string) to `audit_logs`.
- `app/Core/Traits/Auditable.php:44-45`: before computing the new hash, fetch the most recent `hash_signature` for the tenant (`orderByDesc('id')` — ULIDs/UUIDv7 are time-sortable) and fold it into the payload: `$payload = $previousHash . $tenantId . $action . ...`. Store both `previous_hash` and the resulting `hash_signature`. This makes the chain actually verifiable by replaying it from row 1, closing the "decorative hash" finding.
- `Auditable.php:50`: replace `$request->header('X-Facility-ID')` with `App::make(FacilityContext::class)->getFacilityId()` (from 0.2) so audit attribution can no longer be spoofed by an arbitrary client header.

### 0.4 Stop mutating issued invoices
- `GenerateInvoiceAction.php:21`: change the initial status from `'Issued'` to `'Open'` — an encounter's invoice legitimately accumulates line items while the visit is in progress; the bug is that it's marked `Issued` from the first line item.
- Add `IssueInvoiceAction` — the explicit "lock at checkout" transition (`Open`/`Partially Paid`-eligible → `Issued`), called from `BillingController` at the point cashier checkout actually happens.
- Add a guard on the `Invoice` model (`booted()` → `static::updating()`), throwing a new `InvoiceImmutabilityException` (mirrors `ClinicalImmutabilityException`) if `total_amount` changes while `status === 'Issued'` or later, unless the mutation originates from the new adjustment action below.
- Add `invoice_adjustment_notes` migration + `InvoiceAdjustmentNote` model (`type`: Credit/Debit, `invoice_id`, `amount`, `reason`, `created_by`, tenant/RLS columns following the `invoices` migration's exact pattern) + `IssueInvoiceAdjustmentAction`, which both records the note and posts a balanced `LedgerEntry`/`LedgerTransaction` pair, reusing the posting pattern already in `RecordPaymentAction.php`/`IssueRefundAction.php`.
- Update `GenerateInvoiceAction` to refuse adding line items once `status !== 'Open'`, directing callers to the adjustment action instead.

### 0.5 Charge master skeleton
- Migration + model: `charge_master_items` (`tenant_id`, `code`, `name`, `category`, `unit_price`, `currency` default `TZS`, `effective_from`, `effective_to` nullable, `is_active`), RLS following the standard pattern.
- `App\Domains\Billing\Services\ChargePriceResolver::priceFor(string $code, ?Carbon $at = null): float` — active, effective-dated lookup.
- Replace the hardcoded `20000.00` default in `GenerateInvoiceAction.php:11` and the `$unitPrice = 500.00` "prototype" value in `PrescribeMedicationAction.php:51` with resolver calls against seeded codes (`CONSULT-OPD`, and per-formulary drug codes — `MedicationFormulary` currently has no price column, so charge master becomes the single source of drug pricing too). Seed a handful of real entries alongside the existing `DatabaseSeeder.php` data so these resolve to something real, not zero.
- Explicitly out of scope for Phase 0: tax/VAT, multi-currency conversion, insurance-specific tariffs (Phase 3).

### 0.6 Clinical & lab-result immutability enforcement
- Add `App\Core\Traits\ImmutableWhenFinalized` — a `static::updating()` guard, parameterized by a `protected function isFinalized(): bool` method each host model implements, throwing `ClinicalImmutabilityException` unless the update is flagged as coming from that model's dedicated Amend action (a protected static bypass flag set only inside the Amend action, matching how `AmendClinicalNoteAction.php` already handles the original-note deprecation step).
- Apply it to `ClinicalNote` (closes the "any other code path can still overwrite a signed note" gap), `ClinicalVital`, and `Diagnosis` — their migrations already carry `is_amendment`/`amended_*_id`/`is_deprecated` columns (confirmed in `2024_01_03_000013_create_diagnoses_table.php:26-29`), so this is a model + Action layer, no schema change. Add `AmendVitalsAction` and `AmendDiagnosisAction`, mirroring `AmendClinicalNoteAction.php` exactly. Apply the same to `Allergy` (verify its migration has the same columns; add via a small migration if not).
- `lab_order_items` has **no** amendment columns today (confirmed absent in `2024_01_07_000010_create_lab_catalog_and_orders_tables.php:44-59`) — add a migration for `is_amendment`, `amended_result_item_id`, `amendment_reason`, plus RLS (this table currently has none — an audited gap being closed as a byproduct of already touching this file). Add `AmendLabResultAction`. Fix `RecordLabResultsAction`/`VerifyLabResultsAction` to check `verified_by_id !== null` and reject silent overwrites, directing to the amend action instead.

### 0.7 Cross-tenant isolation & authorization-boundary tests
- New `tests/Feature/TenantIsolationTest.php`, following the two-tenant/two-facility setup pattern already in `tests/Feature/Domains/RbacDomainTest.php:15-39`: two tenants, a Patient/Invoice/Encounter under each, assert a Tenant-1-authenticated user gets 403/404 against Tenant-2's records via the actual HTTP routes in `routes/web.php` (not just model-level scoping).
- Add authorization-boundary assertions exercising the policies wired in 0.2: an unprivileged role gets 403 on `billing.refund`, `access-control.roles.assign`, `pharmacy.dispense`.
- New `tests/Invariants/` directory with the first three regression tests tied directly to what Phase 0 built: ledger stays balanced after an `InvoiceAdjustmentNote`, an `Issued` invoice's `total_amount` cannot be mutated outside the adjustment action, a signed `ClinicalNote`/finalized `ClinicalVital` cannot be updated outside its Amend action. This seeds the invariant suite the audit found completely absent; Phase 4 broadens it.

### 0.8 Backup automation + CI quick win
- `composer require spatie/laravel-backup`, publish and configure `config/backup.php` (DB dump + `storage/app` to local disk now, S3-ready via env for later), schedule `backup:run` daily and `backup:clean` in `routes/console.php`.
- One-line fix in `.github/workflows/ci.yml`: remove the `|| true` after `npm run type-check`, so front-end type errors actually fail CI (currently silently swallowed).

---

## Phases 1–5 — Roadmap for follow-up sessions

*(Same granularity as the project's own `ROADMAP.md`; each becomes its own planning session when you're ready.)*

**Phase 1 — Authorization & Tenancy Depth.** Extend `$this->authorize()` wiring to the remaining ~12 controllers untouched in 0.2; finish RLS on the other ~11 tenant tables the audit found unwired (Patient PII child tables, Insurance, Inventory); AES-256-GCM field encryption for NIDA/PII; real TOTP MFA (secret storage, enroll/verify flow); security headers middleware (CSP/HSTS); session idle-timeout; break-glass emergency access; remaining audit coverage gaps (login/logout events, bulk-write auditing).

**Phase 2 — Clinical Safety & Missing Modules.** Replace the fuzzy `LIKE` allergy match with structured allergen coding + real drug-drug interaction checking; fix `MergePatientsAction` to relink encounters/diagnoses/invoices/lab orders, not just identifiers/contacts; gate procedure execution on `who_surgical_checklists.time_out_completed_at`; vitals panic thresholds + critical-value event; problem list; medication reconciliation; lab specimen/reference-range models; new Radiology/Imaging domain (DICOM/PACS); referral, consent, immunization, ANC/partograph modules; `AdmitPatientAction` row-lock fix; deceased-patient gating.

**Phase 3 — Financial & Supply-Chain Integrity.** Full charge master (tax/VAT, multi-currency, insurance tariffs); Postgres trigger/CHECK constraints on ledger tables; refund over-refund guard + correct account routing; AR aging/GL export; real NHIF/private-insurer eligibility adapters (per `ADR-008`); claim adjudication posting to the ledger; DDA register dual-signature + row locking; GRN/PO ledger posting; stock-transfer over-receipt validation; dispense-to-invoice quantity linkage.

**Phase 4 — Interoperability, Reporting & Testing.** FHIR facade layer (new `Integration` domain, `Patient`/`Observation`/`Condition` mappers); M-Pesa Daraja integration; SMS gateway; HL7/ASTM lab-analyzer ingestion; MTUHA register generation (Kitabu cha 1/2/5) + DHIS2 export; `report_snapshots` pre-aggregation; PDF/Excel export; broaden `tests/Invariants/`; dedicated Postgres CI job for RLS; mutation-testing wiring.

**Phase 5 — Observability & Scale-out.** Sentry/OpenTelemetry; Horizon + switch queue/cache/session to Redis (compose service already in place from 0.1); harden the Phase-0 `Dockerfile` into a CI/CD deploy pipeline; DR/backup-restore drills; Swahili i18n; Scout/Meilisearch search; read replicas/connection pooling; Reverb real-time broadcasting for live queue/bed views.

---

## Verification (Phase 0)

1. `docker compose up -d pgsql redis` → `php artisan migrate:fresh --seed` completes with no `invalid input syntax for type uuid` errors and no RLS-policy SQL errors.
2. Manual RLS check: via `artisan tinker`, set two different `app.current_tenant_id` session values with `DB::statement("SET LOCAL app.current_tenant_id = ?", [...])` inside two separate transactions and confirm a `SELECT` against `diagnoses`/`invoices`/`lab_order_items` from Tenant A returns zero of Tenant B's rows.
3. `vendor/bin/pest` (still SQLite in-memory per `phpunit.xml`) — full existing suite green after the `HasUuidv7` swap, confirming no regression from the ID-generation change.
4. `vendor/bin/pest tests/Feature/TenantIsolationTest.php tests/Invariants` — new tests pass.
5. Manually exercise the self-escalation fix: as a non-admin user, POST to `access-control/roles/assign` and confirm 403; as the same user, confirm the existing `billing.refund`/`pharmacy.dispense` routes now 403 without the right permission.
6. `php artisan backup:run` succeeds and produces an archive under the configured disk.
7. Push a branch with a deliberate TS type error and confirm CI now fails on it (then revert the deliberate error).
8. `vendor/bin/phpstan analyse` still passes at the configured level (guards against the trait swap or new classes introducing type errors).