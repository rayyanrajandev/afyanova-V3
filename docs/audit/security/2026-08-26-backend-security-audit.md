# AfyaNova V3 — Backend Security Audit

**Date:** 2026-08-26
**Scope:** Backend (Laravel 13) — authentication, authorization, data protection, injection surface. Frontend Vue templates were read only where needed to confirm whether a backend-exposed field is actually rendered.

## Methodology

Three parallel code-reading passes covered auth/middleware/break-glass, authorization/facility-scoping/mass-assignment, and data-protection/secrets/injection/logging. Every candidate finding below was verified by reading the actual source (not inferred), and the highest-severity ones were additionally proven live: a local Postgres-backed dev server was started, seeded accounts were logged in via cookie-jar curl sessions, and the raw Inertia JSON payload (`page.props`) was inspected directly — the same technique this repository's own prior audits used to prove their findings. Every confirmed finding was fixed in the same session (full Pest suite green, Pint clean, and re-checked live or via a new regression test after each) — the two that initially looked large enough to defer (§6, §7) turned out, on closer tracing of their actual frontend consumers, to be safely fixable without touching critical clinical/procurement workflows; the one genuinely structural item (§8) was closed with a new shared trait plus a permanent regression suite rather than a live proof, since demonstrating it needed fabricated two-facility data this session deliberately avoided writing into the shared dev database.

This is not a green-field audit. `docs/audit/` already contains a role×data-domain confidentiality matrix (`health_information_confidentiality_matrix.md`) grounded in Tanzania's PDPA 2022/MCT/Pharmacy Act/TNMC, and two prior remediation plans (`backend/`, `access-control/`) plus a frontend plan (`frontend/`). The matrix was used as the authorization oracle throughout — a finding isn't "does `authorize()` get called" but "does the actual data returned match what this specific role is legally allowed to see."

## Status of prior audit work

- **Largely implemented, ahead of what the docs describe:** TOTP MFA, break-glass emergency access (5-minute scope, fail-closed audit logging, hash-chained no-delete trail), security headers (CSP nonce, HSTS, COOP/CORP), session idle timeout, facility-scope middleware, tenant RLS, UUIDv7 migration, `spatie/laravel-backup`. The `backend/cheerful-humming-oasis.md` plan lists several of these as an unexecuted "Phase 1" roadmap; direct code reads confirm they've since shipped.
- **Page-level RBAC (`access-control/cheerful-humming-oasis.md`):** mostly implemented as designed. Two items that plan explicitly listed as deferred/out-of-scope were re-verified here (see Findings, corrected items).
- **One item that plan's own text flagged as a designed-but-incomplete special case — Dashboard's list props never got the `$can`-map treatment its metrics did — is Finding 1 below**, and has been fixed.

## Findings

Severity: **Critical** (unauthenticated/trivial access to PHI at scale) · **High** (authenticated-but-unauthorized cross-role/cross-patient PHI access, confirmed) · **Medium** (structural gap or non-PHI aggregate leak) · **Low** (best-practice deviation, limited impact) · **Info** (hygiene, no exploit path).

---

### Finding 1 — CRITICAL — FIXED — Dashboard leaked PHI to every authenticated role

**CWE-862 (Missing Authorization).** Affected data class (matrix terms): Patient Demographics, Patient Drug Allergies, Appointment Scheduling.

`app/Http/Controllers/DashboardController.php` computed a `$can` permission map but applied it only to numeric metrics — `$todayAppointments`, `$recentPatients`, `$activeQueueTickets`, and `$quickPatients` (MRN/name/gender/DOB, up to 100 rows, with allergy data eager-loaded) were fetched and returned in the Inertia props **unconditionally**, regardless of role. This directly contradicted the confidentiality matrix (Inventory Officer = "Zero Access" to Patient Demographics; Cashier = "Zero diagnostic access"; Receptionist = "Zero clinical record access") and reopened the access-control plan's own stated design intent ("`inventory-officer` will see an empty dashboard — confirmed acceptable").

**Live proof:** logged in as `inventory-officer@afyanova.local` (documented zero-patient-access role) before the fix — all four lists and their related metrics were populated. After the fix, the same login returns `todayAppointments: [], recentPatients: [], activeQueueTickets: [], quickPatients: []` and every list-derived metric is `null`, while `receptionist@afyanova.local` (holds `patient.registry.view`) still correctly receives its 6 recent patients / 6 queue tickets.

**Fix applied:** wrapped each list and its related metrics in the existing `$can[...]` gate, mirroring the pattern already used for `active_encounters`/`pending_pharmacy`/etc. Also discovered and removed dead-weight eager loads (`patient.allergies`, `latestVital`) — `resources/js/Pages/Workspace/HomeWorkspace.vue` never renders either, so they were pure over-exposure with zero UI benefit, present even for roles that legitimately hold `patients`/`appointments`/`queue` access but are separately restricted from clinical/allergy data by the matrix (e.g. Receptionist, Cashier).

---

### Finding 2 — HIGH — FIXED — Same bug pattern in `AppointmentController::index()`

**CWE-862.** Affected data class: Patient Demographics.

Found while verifying Finding 1. The route requires `scheduling.appointment.view` and computes `$can['patients']` (`patient.registry.view`), but `$patients` (100 rows: name/MRN) and `metrics.total_patients` were returned unconditionally. **Exploitability note:** in the current seed data, every role holding `scheduling.appointment.view` also holds `patient.registry.view`, so this was not live-exploitable with today's role grants — but it's a real code-level inconsistency with the app's own established gating discipline, and a future role assignment (e.g. a queue-only role) would immediately reopen it.

**Fix applied:** gated `$patients` and `metrics.total_patients` on `$can['patients']`, matching Finding 1's fix shape.

---

### Finding 3 — MEDIUM-HIGH — FIXED — `Auditable` trait duplicated full plaintext clinical content into `audit_logs`

**CWE-200 (Information Exposure) / CWE-311 (Missing Encryption).** Affected data class: Clinical SOAP Notes, ICD-10 Diagnoses, Triage Vitals, Patient Drug Allergies.

`app/Core/Traits/Auditable.php` JSON-encoded a model's *entire* attribute set into `before_state`/`after_state` on every create/update/delete, across 71 models including `ClinicalNote`, `Diagnosis`, `ClinicalVital`, `Allergy`. No controller currently reads `AuditLog::` (no live UI over-exposure today), but the confidentiality matrix promises Medical Auditor/Tenant Admin "Full (Read-Only)" access to `audit_logs`, so an audit-log viewer would inherit unredacted clinical content by default the day it ships — and `spatie/laravel-backup` (already scheduled) duplicates this to a second storage location whose archive encryption is opt-in (`BACKUP_ARCHIVE_PASSWORD`, absent from `.env.example`).

**Correction to initial scope:** `PatientIdentifier.identifier_value` was initially suspected as part of this leak (it holds a national ID) but direct testing confirmed Laravel's `encrypted` cast stores ciphertext in the model's raw `$attributes`/`getOriginal()` arrays — `Auditable` was already logging ciphertext for that field, not plaintext. It was excluded from the fix as unnecessary.

**Fix applied:** added an `AUDIT_REDACT` constant convention (sibling to the existing `AUDIT_CATEGORY` convention) to `Auditable`, and applied it to `Diagnosis` (`description`, `icd_10_code`, `notes`, `amendment_reason`), `ClinicalNote` (`content`, `amendment_reason`), `ClinicalVital` (all vital-sign fields, `notes`, `amendment_reason`), and `Allergy` (`allergen`, `reaction`, `amendment_reason`). Redacted fields are replaced with `[REDACTED]`; ids, foreign keys, status flags, and timestamps are kept so entries remain useful for traceability. **Verified live:** created a test `Diagnosis`, confirmed the resulting `audit_logs` row shows `description`/`icd_10_code` as `[REDACTED]` while `patient_id`/`encounter_id`/`diagnosed_by`/timestamps remain intact. (Incidentally, attempting to delete the test audit rows failed — confirming the documented no-delete DB rule is genuinely enforced, not just decorative.)

---

### Finding 4 — LOW — FIXED — Seeded demo accounts, no environment guard

**CWE-798-adjacent (weak/shared credential), CWE-1188 (insecure default).**

`database/seeders/DatabaseSeeder.php` creates ~11 accounts across every role with a uniform `password123`. No `db:seed` invocation exists in CI/deploy scripts, so this wasn't an automated exposure path — risk was purely operator discipline (an accidental `php artisan db:seed` against production).

**Fix applied:** added `if (app()->environment('production')) { abort(403, ...); }` at the top of `DatabaseSeeder::run()`.

---

### Finding 5 — MEDIUM — FIXED — `PatientController::index()` / `SearchPatientsAction` over-fetched clinical and financial data

**CWE-862 / CWE-200.** Affected data class: Clinical SOAP Notes, ICD-10 Diagnoses, Triage Vitals, Prescription Authoring, Invoices.

Found during the broader sweep (not in the original three-agent recon). `SearchPatientsAction` — the sole backend of the patient search page — eager-loaded each result's full clinical chart (`encounters.vitals`, `.notes`, `.diagnoses`, `.prescriptions.medication`) and financial history (`invoices.lineItems`, `encounters.invoices.lineItems`) for **every** patient returned, regardless of the requesting user's role. `resources/js/Pages/Domains/Patient/Search.vue` computes `notes`/`vitals`/`diagnoses`/`prescriptions` from this data but **never renders any of them** (dead code, same pattern as Finding 1's allergy fields) — only `invoices` is actually used, to display an unconditional "POS Balance" figure, with just the adjacent "Cashier" *link* gated on `can.billing`, not the balance itself.

**Fix applied:** dropped the unused clinical/appointment eager loads entirely (`vitals`, `notes`, `diagnoses`, `prescriptions.medication`, `appointments.provider` — none referenced by the Vue page). Added an `$includeBilling` parameter to `SearchPatientsAction::execute()`, called from the controller as `$searchAction->execute($search, 50, $can['billing'])`, so `invoices.lineItems` is only fetched (and thus only appears in the JSON payload) for users who actually hold `billing.invoice.view`.

---

### Finding 6 — MEDIUM — FIXED — `DispensingController` shipped full pharmacy stock/movement data regardless of `pharmacy.inventory.view`

**CWE-862.** Affected data class: DDA/pharmacy stock levels, cost pricing (not directly a matrix row, but the app's own permission model treats `pharmacy.inventory.view` as a distinct, narrower grant than `pharmacy.prescription.view`).

Found by a targeted follow-up sweep of every controller using the `buildSectionCanMap()` pattern. `$medications`/`$batches` (full formulary with per-batch stock, expiry, and — via `medication.stockMovements` — a 10-entry recent movement history per drug including `unit_cost`) were fetched unconditionally, despite `formulary`/`movements` being distinctly gated `$can` keys used correctly elsewhere in the same file. **Live-exploitable:** `doctor` and `auditor` seeded roles hold `pharmacy.prescription.view` without `pharmacy.inventory.view`, so both received this data on any visit to the pharmacy queue.

**Fix applied, in two passes:**

1. Removed the `medication.stockMovements` eager load outright — confirmed via grep that `PharmacyQueue.vue` never reads it (the movements *tab* uses a separately, correctly-gated `$recentMovements` prop), so this was pure dead-weight exposure.
2. Initially deferred gating `$medications`/`$batches` themselves, suspecting the dispense picker needed them directly for any `pharmacy.dispense.execute` holder. A full trace of `PharmacyQueue.vue`'s dispense flow (`suggestedBatches()`, `totalStockForDispenseMed()`, `isStockSufficient()`) showed it actually reads medication/batch data primarily from **`$prescriptions`' own eager-loaded `medication.batches` relation** — already correctly gated on `$can['queue']` — and only falls back to the top-level `$medications`/`$batches` props when present, degrading gracefully (`isStockSufficient` treats an empty fallback as permissive; the real stock check happens server-side in `DispenseMedicationAction` regardless). The top-level props are consumed *only* by the Formulary tab and the Receive/Adjust Batch modals — all three already UI-gated on `$can['formulary']`/`$can['storeBatch']`/`$can['adjustBatch']`.

Gated `$medications`/`$batches` on `$can['formulary'] || $can['storeBatch'] || $can['adjustBatch']` (confirmed via seed data that `receive`/`adjust` are never granted without `view` today, so this union is currently equivalent to `$can['formulary']` alone, kept as a union for correctness against future role grants).

**Verified live:** `doctor@afyanova.local` (`queue` only) now receives `medications: []`, `batches: []` on `/pharmacy`. `pharmacist@afyanova.local` (holds `queue`+`formulary`+`storeBatch`+`adjustBatch`) is unaffected — still receives the full 18 medications / 72 batches with stock data. Full test suite green (175/175) both before and after.

---

### Finding 7 — MEDIUM/HIGH — FIXED — `InventoryWorkspaceController`'s item catalog (with cost/selling price) was ungated, including a completely unauthenticated-by-permission JSON endpoint

**CWE-862 / CWE-306 (Missing Authentication for Critical Function, for the second half below).**

Same sweep as Finding 6. `$itemMasters` (includes `unit_cost_price`/`unit_selling_price`) was fetched unconditionally as "shared reference data," but is also the direct data source for the Universal Item Catalog *table*, UI-gated on `can.catalog` — the metrics correctly gate `total_items_catalog` on `$can['catalog']`, confirming the pricing exposure was an oversight relative to the developer's own stated intent, not a considered tradeoff.

**A second, more severe instance surfaced while tracing this:** `InventoryWorkspaceController::searchCatalog()` — a separate `GET /inventory/catalog/search` JSON endpoint that `AfyaItemCombobox.vue` calls directly for live search — had **no authorization check of any kind**, only the route-group's blanket `auth`+`verified`. Any authenticated user in the system, regardless of role — including one who can't even load `/inventory` at all — could query it directly for full item pricing.

**Live-exploitable (confirmed against seed data):** `nurse` and `auditor` both reach `/inventory` (via `stock`/`dda`-view permissions) without holding `catalog`, `requisition`, `transfer`, `po`, or `grn` — neither had any legitimate need for item-master pricing, yet both received it.

**Fix applied:** traced every one of `InventoryWorkspace.vue`'s `itemMasters` consumers in full — unlike Finding 6, this data genuinely *is* needed beyond the catalog tab: `AfyaItemCombobox` (used in the requisition/transfer/PO/GRN creation forms) displays `unit_cost_price` directly in its search results, and the DDA log form's item dropdown also reads from it. Added a single `canSeeItemCatalog()` check — the union of `catalog` view plus the five create/receive/record action permissions whose forms actually embed an item picker — and applied it to both `$itemMasters` in `index()` and as an `abort_unless` guard on `searchCatalog()`.

**Verified live:**
- `nurse@afyanova.local` (holds `inventory.dda.record`, needs the DDA item picker) — still receives the full 37-item catalog, correctly.
- `pharmacist@afyanova.local` (holds `catalog` view) — unaffected.
- `auditor@afyanova.local` (holds only `dda.view`, read-only, no picker forms reachable) — now receives `itemMasters: []` on the page, and a **403** hitting `searchCatalog` directly.

One existing test (`UniversalInventoryTest`'s catalog-search test) used a fixture user with only a legacy `role` string column and no real RBAC grant — it never actually held any permission, so it was passing by accident (nothing in the codebase honors that column for authorization anymore). Fixed by giving that fixture user a real `inventory.catalog.view` grant via the same `Role`/`Permission`/`AssignUserRoleAction` pattern used elsewhere in the test suite.

---

### Finding 8 — MEDIUM — FIXED — No model-layer facility scope on Appointment/QueueTicket/Encounter/Invoice

**CWE-284 (Improper Access Control).**

`Patient` has a facility-aware global scope (`app/Domains/Patient/Models/Patient.php:131-164`) layered on top of tenant scoping. `Appointment`, `QueueTicket`, `Encounter`, and `Invoice` only had the tenant-level scope (`BelongsToTenant` trait) — cross-facility (same-tenant) isolation for these four models depended entirely on every controller remembering to pass `facility_id` into `hasPermission()`/`authorize()`, with no model-layer backstop.

**Scope correction from the original recon:** `ProcedureCatalog` was initially grouped with these four, but has neither a `facility_id` nor a `patient_id` column — confirmed against its actual schema. It's tenant-wide reference data (procedure code, name, tier, price), structurally identical in kind to `LabTest`/`MedicationFormulary`/`ChargeMasterItem`, none of which are facility-scoped in this app by design. There's nothing to scope; it was dropped from this finding rather than forced into a fix that doesn't apply.

**Fix applied:** added `app/Core/Traits/HasFacilityScope.php` — a new trait mirroring `Patient::booted()`'s scope logic, but simpler, since these four models carry `facility_id` and `patient_id` directly (no need for `Patient`'s `registered_at_facility_id`-or-via-encounters fallback). Kept as a separate trait from Patient's inline scope rather than unifying the two, since forcing both shapes into one abstraction would touch Patient's already-correct, already-tested scope for no behavioral gain. Applied to `Appointment`, `QueueTicket`, `Encounter`, and `Invoice`. Confirmed via `grep` that no raw SQL `->join()` exists anywhere in `app/Domains` joining these tables together, so there's no risk of the "ambiguous column" issue `BelongsToTenant`'s own scope explicitly guards against for `tenant_id`.

**Verified via a new regression suite** (`tests/Feature/FacilityScopeTest.php`) rather than a live dev-database proof — proving this finding genuinely needs two facilities with facility-scoped role assignments, and the current dev database has almost none (17 of 18 role assignments are global), so fabricating that data live would have meant writing directly into a database a concurrent collaborator session was using. A Pest fixture is the more correct venue anyway: it's a permanent regression guard, not a one-off check. Four tests, all passing:
- A facility-scoped user cannot see another facility's appointments, queue tickets, encounters, or invoices.
- A user with a *global* (unscoped) role assignment still sees every facility — no regression for the common case (matches the 17-of-18 seed-data reality).
- Break-glass, activated for one patient, restores visibility of that patient's records specifically at the other facility, across all four models — proving the override generalizes correctly, not just for `Patient` itself.
- A user with no role assignment at all remains unrestricted, matching `Patient`'s own documented permissive edge case.

Full suite: 179/179 passing (175 existing + 4 new).

---

### Corrected items (prior `docs/audit/` claims re-verified, found not to hold)

- **`AccessControlWorkspaceController::testPermission()` is dead code, not a live vulnerability.** `docs/audit/access-control/cheerful-humming-oasis.md` lists it as an open info-disclosure risk (any caller can probe any user's permission for any slug). Confirmed via `routes/web.php`: only `index`, `assignRole`, `updatePermissions` are routed — `testPermission` has no route at all. No fix needed; recommend deleting the dead method as hygiene, or wiring it behind an explicit permission-gated route if the debugging capability is still wanted.
- **Role-assignment `facility_id` self-escalation is not currently exploitable.** `AccessControlWorkspaceController::assignRole()` accepts `facility_id` from the request body without checking it against the assigning user's own scope. However, `identity.roles.assign` is held exclusively by `tenant-admin` in the current seed data, and tenant-admins already have full facility reach by design — so there is no privilege escalation today. Worth a defensive fix only if this permission is ever granted to a facility-scoped role in the future.

### Checked, no finding

- **SSRF:** no outbound HTTP client code (`Http::`, Guzzle, `curl_init`) exists anywhere in `app/Domains` — no insurer/NHIF integration is implemented yet, so there's no surface.
- **XSS via `v-html`:** zero usage anywhere in `resources/js`.
- **Rate limiting beyond login:** MFA verification has a custom 5-attempts/IP/minute `RateLimiter`; password-reset routes carry `throttle:6,1`.
- **`composer audit` / `npm audit`:** no advisories, 0 vulnerabilities.
- **Raw SQL injection:** all `DB::raw/whereRaw/selectRaw/orderByRaw` usage uses static strings or parameter bindings.
- **Mass assignment:** every model uses `$guarded = ['id']` with no `$fillable` allowlist, but no `::create($request->all())`/`->fill($request->all())` call sites exist — all writes go through validated Action classes. Latent structural risk, not a live bug.

---

## Verification performed

- `vendor/bin/pest` — 179/179 passing after all fixes (two pre-existing tests updated: `tests/Feature/WorkspaceLayoutTest.php`'s fixture role needed `scheduling.appointment.view` added, since the dashboard now correctly withholds appointments from roles that don't hold it; `tests/Feature/Domains/UniversalInventoryTest.php`'s catalog-search test needed a real `inventory.catalog.view` grant, since its fixture user's legacy `role` string column was never actually honored by the authorization system and the test was passing on an unenforced endpoint; four new tests added in `tests/Feature/FacilityScopeTest.php`).
- `./vendor/bin/pint` — clean on all changed files.
- Live curl-cookie-jar sessions: `inventory-officer@afyanova.local` and `receptionist@afyanova.local` against `/dashboard`; `doctor@afyanova.local` and `pharmacist@afyanova.local` against `/pharmacy`; `nurse@afyanova.local`, `pharmacist@afyanova.local`, and `auditor@afyanova.local` against `/inventory` and `/inventory/catalog/search` — inspecting the raw Inertia `page.props` JSON (and raw HTTP status for the search endpoint) before and after each fix.
- `php artisan tinker` — created and inspected a live `Diagnosis` record's resulting `audit_logs` row to confirm redaction.
- `composer audit`, `npm audit --omit=dev`.
- `grep -rn "->join(" app/Domains` — confirmed zero raw joins across the four newly-scoped tables, ruling out the ambiguous-column risk `BelongsToTenant` explicitly guards against for `tenant_id`.

## Files changed

- `app/Http/Controllers/DashboardController.php`
- `app/Domains/Scheduling/Http/Controllers/AppointmentController.php`
- `app/Core/Traits/Auditable.php`
- `app/Domains/Clinical/Models/Diagnosis.php`, `ClinicalNote.php`, `ClinicalVital.php`, `Allergy.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Domains/Patient/Actions/SearchPatientsAction.php`
- `app/Domains/Patient/Http/Controllers/PatientController.php`
- `app/Domains/Pharmacy/Http/Controllers/DispensingController.php`
- `app/Domains/Inventory/Http/Controllers/InventoryWorkspaceController.php`
- `app/Core/Traits/HasFacilityScope.php` (new)
- `app/Core/Context/BreakGlassContext.php` (docblock only)
- `app/Domains/Scheduling/Models/Appointment.php`, `QueueTicket.php`
- `app/Domains/Clinical/Models/Encounter.php`
- `app/Domains/Billing/Models/Invoice.php`
- `tests/Feature/WorkspaceLayoutTest.php`
- `tests/Feature/Domains/UniversalInventoryTest.php`
- `tests/Feature/FacilityScopeTest.php` (new)

## Status

All eight findings closed this session — seven fixed outright, one (Finding 8) fixed via a new shared trait plus a permanent regression suite. Two prior-audit claims corrected. No items remain deferred from this pass.

## Recommended next session

`PatientController::show()`'s single unconditional `load([...])` — already tracked as a deliberately deferred "Phase 6" item in `docs/audit/access-control/cheerful-humming-oasis.md`, re-confirmed still in that state during this audit; not re-scoped here since it's already the team's own tracked backlog item, not a new finding.

---

## Addendum — infrastructure-level tenant isolation (same day, follow-up pass)

A closer look at *how* multi-tenant isolation is actually enforced at the database level — prompted by a request to push the isolation posture further, past Finding 8's application-layer fix — turned up something more significant than anything above: **row-level security had been a no-op for the whole application**, not a code defect but a database role misconfiguration. Verified live, and — with the database administrator running the one command this session's own credentials couldn't — fixed, which in turn surfaced and closed two more real problems (A14, A15) that had been invisible for exactly the same reason.

### A9 — CRITICAL (infrastructure) — FIXED — the application's own database role bypassed RLS entirely

**CWE-863 (Incorrect Authorization), infrastructure-level.**

`afyanova` — the Postgres role the application itself connects as — had `BYPASSRLS` granted. This is a role-level attribute, distinct from `SUPERUSER` and from table ownership, and it overrides *every* RLS policy on *every* table, `FORCE ROW LEVEL SECURITY` included. Proved live: with `app.current_tenant_id` explicitly set to a UUID matching nothing, `patients` — a `FORCE`-protected table with a correctly fail-closed policy — still returned every row across both real tenants.

Practically, this meant the two-layer isolation design (Postgres RLS as a database-level backstop for whenever the application-level `BelongsToTenant` Eloquent scope gets bypassed by a bug) had only ever had one layer actually active. Nothing in the repository's own provisioning (`docker-compose.yml`, migrations, seeders) granted this — it was an artifact of how this specific database was provisioned, not a code defect.

**CI had the identical blind spot.** `.github/workflows/ci.yml`'s Postgres service used `POSTGRES_USER: root`, which the official `postgres` Docker image creates as a `SUPERUSER` — also an unconditional RLS bypass. The CI job's own claim of running the full suite "against real Postgres" was true of the connection, not of RLS actually being exercised. Fixed alongside (see A13).

**Fixed by the database administrator, on this dev instance, this session:**
```sql
ALTER ROLE afyanova NOBYPASSRLS;
```
`ALTER ROLE` requires the altering role to itself already hold `CREATEROLE` with the `ADMIN` option, or be a superuser — this session's own `afyanova` credentials couldn't do it (confirmed via `permission denied to alter role`), so the fix was applied directly by the user via `sudo -u postgres psql`. Verified live immediately after: `rolbypassrls` flipped to `false`, and the same fake-tenant-ID query that previously returned every tenant's `patients` rows now returns zero.

**This surfaced two more problems the moment RLS became real, both now fixed — see A14 and A15.**

**Still open: production (or any other real deployment) has not been checked or fixed.** This dev database is the only environment this session had access to. Whoever administers staging/production needs to run the same check (`SELECT rolbypassrls FROM pg_roles WHERE rolname = current_user`, as the app's actual runtime role there) and the same fix if it comes back true.

### A10 — MEDIUM — INVESTIGATED, CORRECTED — `users` table's missing `FORCE ROW LEVEL SECURITY` is intentional, not a gap

Diffing all RLS-enabled migrations against ones that also `FORCE` it initially looked like a second finding: `users` is the one table with `ENABLE` but no `FORCE`. Reading the migration's own comment in full before acting on that (rather than mechanically "fixing" it) turned up that this is deliberate: authentication must look a user up by email *before* any tenant is known — tenant is derived from the matched user, not the reverse — so `EloquentUserProvider::retrieveByCredentials()` structurally cannot run under a tenant filter. Forcing RLS here would have broken login the moment A9 is fixed (the app's own role, owning this table, is what the login lookup depends on being exempt).

Verified the exemption is scoped as narrowly as the design intends: grepped for any `User::withoutGlobalScopes()` or raw `DB::table('users')` call elsewhere in the codebase — none exist, so every other access path to `users` still goes through `BelongsToTenant`'s Eloquent scope, exactly as the migration's comment claims. **No change made.** `tests/Feature/DatabasePostureTest.php`'s second assertion locks this down going forward: it asserts `users` is the *only* unforced tenant-scoped table, so a future migration can't quietly add a second exception.

### A11 — MEDIUM — FIXED — facility scope extended from 4 models to 18

Finding 8 (main report, above) applied `HasFacilityScope` to the four models the original recon flagged. A systematic schema sweep this pass — every table with both a `facility_id` and a `patient_id` column, not just the ones already named — turned up fourteen more with the identical shape, unaudited until now: `PatientDeposit`, `LabSpecimen`, `Admission`, `MedicationAdministrationRecord`, `PartographEntry`, `AncEncounter`, `PatientImmunization`, `ClinicalConsent`, `RadiologyReport`, `RadiologyStudy`, `RadiologyOrder`, `MedicationReconciliation`, `PatientIdentifier`, `DdaRegisterLog`. Applied the same trait to all fourteen. Full suite green throughout (no regressions from the wider surface), and the new structural test below independently confirms the sweep is now complete — not just "no test failed," but "every model with this exact shape is covered."

### A12 — LOW — FIXED — unauthenticated `X-Tenant-ID` header removed from tenant resolution

`TenantContextMiddleware` fell back to a client-supplied `X-Tenant-ID` header for unauthenticated requests, ahead of subdomain resolution. Grepped the entire codebase (backend and frontend): nothing sets or reads this header anywhere except the middleware itself. `EstablishTenantContextOnLogin.php`'s own comment confirms the login flow was deliberately built *not* to depend on it ("no X-Tenant-ID on a plain browser form post" is documented as the normal case). With no `routes/api.php` and no current token-based flow that would need it, the header had no legitimate caller and no offsetting benefit against trusting anonymous client input for a security-context variable. Removed; subdomain-based resolution (derived from the actual HTTP `Host`, not arbitrary client-controlled data) and authenticated-user resolution are unchanged.

### A13 — FIXED — permanent regression coverage for all of the above

Two new test files:
- **`tests/Feature/DatabasePostureTest.php`** — asserts the connecting role has neither `SUPERUSER` nor `BYPASSRLS` (this is A9's live-failing proof), and that `users` remains the *only* tenant-scoped table without `FORCE ROW LEVEL SECURITY` (A10, locked down). Both `pgsql`-only, skipped on SQLite.
- **`tests/Feature/TenancyScopeCoverageTest.php`** — a generic, parametrized structural check across every Eloquent model in the codebase (reflection-discovered, not a hand-maintained list): every model whose table has `tenant_id` uses `BelongsToTenant`; every model whose table has both `facility_id` and `patient_id` uses `HasFacilityScope`. This is the test that would have caught A11's 14-model gap automatically, the day each migration landed, rather than waiting for a manual sweep — and confirms, independently of that manual sweep, that the coverage is now actually complete.

**`.github/workflows/ci.yml`** updated to provision a `NOSUPERUSER NOBYPASSRLS` role (`afyanova_ci`) owning the test database, replacing the superuser `root` connection the Pest step ran as — without this, `DatabasePostureTest` would fail in CI permanently (correctly, but uselessly) even after A9 is fixed everywhere else, since CI's "real Postgres" run has never actually exercised RLS either. The exact SQL was validated for syntax (permission-denied, not syntax errors, against an available non-privileged connection) but the happy path — actually provisioning successfully — could not be rehearsed end-to-end in this session; worth watching the first CI run after this change.

### A14 — HIGH — FIXED — RLS becoming real broke the test suite's own fixtures, silently, for as long as it wasn't

11 test files (including `FacilityScopeTest.php`, added earlier this pass) build tenant fixtures by calling `TenantContext::setTenantId()` directly rather than going through a real HTTP request. `TenantContext::setTenantId()` is a pure PHP property setter — confirmed by reading it — it never touches the database. The Postgres session variable RLS policies actually check (`app.current_tenant_id`, via `set_config`) is only ever set by `TenantContextMiddleware` (a real request) or `EstablishTenantContextOnLogin` (the `Login` event). With A9 unfixed, this didn't matter — every `INSERT` succeeded regardless of RLS. The moment A9 was fixed, every one of these fixtures started failing its own setup with `new row violates row-level security policy`, because the row's `tenant_id` (correctly set at the PHP/Eloquent level) no longer matched a `WITH CHECK` clause reading a session variable that was never actually set.

This is exactly the kind of thing that's invisible until the defense it's testing against actually turns on — not a defect introduced by this session, a defect this session's own fix finally made visible.

**Fixed:** added `setTestTenantContext()` to `tests/Pest.php` (sets both the PHP context and, on `pgsql`, the Postgres session variable via the same `set_config` call the app's own middleware uses) and replaced all 12 call sites across `tests/TestCase.php` and 11 Feature test files, removing now-unused `TenantContext` imports. Verified by running the **full suite against real Postgres with `BYPASSRLS` actually revoked** — the only way to prove this class of bug is closed.

### A15 — MEDIUM — FIXED — three genuine, pre-existing SQL bugs, never caught before this session

Fixing A14 surfaced three more failures once the fixture-level noise was gone — all real, all Postgres-strict-typing-vs-SQLite-loose-typing bugs that had nothing to do with tenancy, and had apparently never been caught because this is the first time the full suite has ever run against a real, non-privileged, RLS-enforcing Postgres connection (dev was bypassing, CI was superuser). Fixed as a direct byproduct of finally being able to see them:

- **`AdministerMedicationAction`** queried `inventory_stock_balances` for a nonexistent `item_id` column, filtered on a nonexistent `batch_number` column, and read `batch_number`/`expiry_date` directly off a model that doesn't carry them (they live on the related `InventoryBatch`, reached via the `batch()` relation). SQLite tolerated all three silently. Consequence beyond the crash: every e-MAR entry and DDA narcotic register log fed by this action has been recording `null` for batch traceability despite a real batch actually being FEFO-allocated and deducted — a real clinical/DDA-compliance data-quality gap, not just a query bug. Fixed by dropping the nonexistent-column reference, fetching candidate stock balances with their `batch` relation eager-loaded and sorting in PHP for FEFO order (a real SQL `join` was tried first but collides with `BelongsToTenant`'s own unqualified `tenant_id` scope — exactly the "ambiguous column" failure mode that trait's own code comment warns about — so this avoids the join rather than touching that shared trait), and reading `batch_number`/`expiry_date` off the loaded batch.
- **`AuditsBulkWrites`** wrote the literal string `'bulk'` into `audit_logs.entity_id`, a non-nullable `uuid` column. Fixed with a real generated UUIDv7 (matching this codebase's established id convention) — the `action` field (`BULK_UPDATE`/`BULK_DELETE`) already conveys "this entry has no single referenced entity," so the id itself doesn't need to.
- **`EvaluateLabResultRangeAction`** compared `Carbon::diffInDays()`'s result (a float, sub-day precision) against `age_min_days`/`age_max_days`, both `integer` columns. Fixed with an explicit `(int)` cast.

### Addendum status

All eleven addendum findings (A9–A15, minus the intentionally-unchanged A10) now fixed and verified — the full suite passes **183/183 against real Postgres with `BYPASSRLS` genuinely revoked**, sequentially (`--parallel` hits an unrelated local `max_locks_per_transaction` resource limit on this dev instance running many concurrent full-schema `DROP TABLE CASCADE`s — a Postgres server-tuning matter, not a code issue) — and 181/183 locally against SQLite (2 correctly skipped, `pgsql`-only). This is the first point in this project's history the suite has passed against an environment that actually enforces row-level security.

**The one thing this session could not do:** check or fix any environment beyond this local dev database. Production readiness for A9 specifically depends entirely on whoever administers those environments running the same check.

### Addendum files changed
- `app/Core/Middleware/TenantContextMiddleware.php` (A12)
- `app/Core/Traits/HasFacilityScope.php`, and the 14 models listed in A11
- `.github/workflows/ci.yml` (supports A9's test actually meaning something in CI)
- `tests/Feature/DatabasePostureTest.php`, `tests/Feature/TenancyScopeCoverageTest.php` (new)
- `tests/Pest.php` (new `setTestTenantContext()` helper), `tests/TestCase.php`, and 11 Feature test files (A14)
- `app/Domains/Inpatient/Actions/AdministerMedicationAction.php`, `app/Core/Traits/AuditsBulkWrites.php`, `app/Domains/Laboratory/Actions/EvaluateLabResultRangeAction.php` (A15)
