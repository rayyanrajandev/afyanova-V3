# AfyaNova V3 — Workspace Access Control (View-Level RBAC) Remediation Plan

## Context

Every one of the app's 20 authenticated workspace/page-render routes carries only `auth`+`verified` middleware — no role or permission check on the page-view itself. Only *mutating* actions (store/dispense/sign/etc.) check permissions via `AuthorizationService::hasPermission()`. This was proven live: logging in as `receptionist@afyanova.local` (a front-desk role) and loading `/access-control` — the RBAC admin panel — returned HTTP 200 with all 11 users' names/emails, all roles, and every permission definition. `php artisan route:list --json` confirms all 20 routes share the identical bare `auth`+`verified` stack.

Several workspaces additionally render many internal tabs in one page-load with no way to show some and hide others — Inventory has 10 (`stock`, `catalog`, `requisitions`, `transfers`, `procurement`, `predictive`, `grn`, `dda` [controlled-substances register], `gas`, `stocktake`), all loaded and shipped in the JSON payload regardless of which tab is initially active.

While researching this, three **pre-existing, already-broken permission references** surfaced — the same bug class found earlier in this engagement (a checked slug that was never actually seeded, so the check permanently fails for every non-tenant-admin role): `ReportsWorkspaceController` already tries to gate its `index()` with `reports.analytics.view`, but that slug doesn't exist in the catalog — meaning **every role including `auditor` is currently 403'd out of `/reports` entirely**. `AccessControlWorkspaceController`'s `assignRole()`/`updatePermissions()` check `identity.roles.assign`/`identity.permissions.manage`, neither seeded either. The `nurse` role's seeded perms array references `inpatient.ward.view`, also never seeded (silently dropped by `sync()`). These are quick, high-confidence fixes and are sequenced first.

A second load-bearing discovery: `tests/TestCase.php::setupTenantEnvironment()` builds its own **fabricated** permission/role fixture from scratch rather than using the real `DatabaseSeeder` catalog, and its baseline list already contains `reports.analytics.view` as a real-looking permission — which is exactly why the broken slug was never caught by the test suite. Every phase below that adds a page-level `.view` gate must add the matching slug to that fixture, or dependent tests will false-fail.

**Recommended architecture:** a small shared trait (`AuthorizesWorkspaceAccess`) providing (a) a page-level `abort_unless` gate and (b) a per-section `can` map built via individual `hasPermission()` calls (never `getUserPermissions()`, which doesn't special-case the tenant-admin bypass the way `hasPermission()` does). Controllers only query/include a section's data when its `can` flag is true — this both hides the UI tab and stops shipping the underlying data in the JSON payload. New permission slugs follow the catalog's existing `{domain}.{noun}.view` convention, reusing an existing slug wherever a genuine one-to-one match already exists (e.g. `patient.registry.view` for the whole Patient Profile page) and minting a new one only for genuine gaps (Inventory has essentially none today).

Two product/compliance decisions were confirmed with you before finalizing this plan: **`inventory-officer` gets `inventory.dda.view`** (physically reconciles the narcotics safe during stocktakes), `auditor` does **not** (kept to pharmacist/nurse/inventory-officer only). **`inventory-officer` seeing an empty Dashboard is acceptable** (their real workspace is `/inventory`) — no 8th dashboard tile added.

---

## Phase 0 — Foundation (zero behavior change)

- Add `app/Core/Traits/AuthorizesWorkspaceAccess.php` (alongside the existing `Auditable.php`/`BelongsToTenant.php`/`HasUuidv7.php` traits in that directory):
  ```php
  trait AuthorizesWorkspaceAccess
  {
      protected function authorizeAnyWorkspacePermission(User $user, AuthorizationService $authService, array $slugs, ?string $facilityId = null): void
      {
          abort_unless(
              collect($slugs)->contains(fn (string $slug) => $authService->hasPermission($user, $slug, $facilityId)),
              403
          );
      }

      protected function buildSectionCanMap(User $user, AuthorizationService $authService, array $sectionSlugs, ?string $facilityId = null): array
      {
          return collect($sectionSlugs)
              ->map(fn (string $slug) => $authService->hasPermission($user, $slug, $facilityId))
              ->all();
      }
  }
  ```
- Add a global `auth.permissions` share to `app/Http/Middleware/HandleInertiaRequests.php::share()` (additive convenience for nav chrome, NOT the authoritative gate — that's always the server-side check per controller):
  ```php
  'auth' => [
      'user' => $request->user(),
      'permissions' => fn () => $request->user()
          ? app(AuthorizationService::class)->getUserPermissions($request->user())
          : [],
  ],
  ```
- Verify: full `php artisan test` stays 100% green (nothing calls the new trait yet). `./vendor/bin/pint`.

---

## Phase 1 — Quick wins: fix the 3 already-broken permission references

Pure `database/seeders/DatabaseSeeder.php` catalog + role-grant edits, no controller changes — the existing (currently-broken) `abort_unless()` calls become correct simply because the slugs they check now exist.

1. Add `reports.analytics.view` (domain `Reports`) to `$permissionsList`. Grant to **`auditor`** only.
2. Add `identity.roles.assign` and `identity.permissions.manage` (domain `Identity`) to `$permissionsList`. Grant to **no named role** — RBAC administration stays tenant-admin-exclusive (matches today: no role currently holds any `identity.*` permission).
3. Add `inpatient.ward.view` (domain `Inpatient`) to `$permissionsList`. Grant to **`nurse`** (fixes the dangling reference it already had), **`doctor`**, **`auditor`**.

**Verify:** live-login `auditor@afyanova.local` / `password123` → `GET /reports` now 200 (was 403). `receptionist@afyanova.local` → `POST /access-control/roles/assign` still 403 (no regression — receptionist still lacks the slug). `php artisan test` — expect zero new failures. `./vendor/bin/pint`.

---

## Phase 2 — Group A: page-level-only gates (single-collection pages, proves the pattern)

No section split needed — verified each of these pages' Vue tabs are pure client-side filters over one collection already returned in full by the controller.

| Route | File | Gate |
|---|---|---|
| `billing.desk` | `app/Domains/Billing/Http/Controllers/BillingController.php` | `abort_unless(hasPermission($user, 'billing.invoice.view'))` — confirmed `index()` returns exactly `invoices`/`activeShift`/`recentShifts`/`tillTelemetry`; all 6 Vue tabs (`invoices`/`pos`/`mobile_money`/`claims`/`refunds`/`ledger`) read from these same 4 props |
| `patients.index` | `app/Domains/Patient/Http/Controllers/PatientController.php` | `abort_unless(hasPermission($user, 'patient.registry.view'))` |
| `patients.show` | same file | `$this->authorize('view', $patient)` — reuses existing `PatientPolicy` |
| `patients.create` | same file | `$this->authorize('create', Patient::class)` — reuses existing `PatientPolicy` |
| `workspace.clinical` | `app/Domains/Clinical/Http/Controllers/EncounterController.php` | `abort_unless(hasPermission($user, 'clinical.encounter.view'))` |
| `encounters.workspace` | same file | `$this->authorize('view', $encounter)` — reuses existing `EncounterPolicy` |

**Required in this same phase — `tests/TestCase.php` `$baselinePermissions` array (~line 55):** add `patient.registry.view`, `billing.invoice.view`, `clinical.encounter.view` (confirmed: none of the three exist there today). Additionally, `tests/Feature/WorkspaceLayoutTest.php` builds its own roleless `User::create()` fixture (doesn't use `setupTenantEnvironment()`) and asserts 200 on `/billing/desk` and `/encounters/{id}/workspace` — give that test's user a role holding the new slugs, or it breaks.

**Verify:** `receptionist@afyanova.local` (holds `patient.registry.view`, `clinical.encounter.view`, not `billing.invoice.view`) → patients pages 200, `workspace.clinical` 200, `billing.desk` 403. `cashier@afyanova.local` (holds `billing.invoice.view`, `patient.registry.view`, no clinical) → `billing.desk` 200, `workspace.clinical` 403. Full Pest suite + Pint.

---

## Phase 3 — Group B: moderate section splits (reuses Phase 1's newly-fixed slugs)

- **`inpatient.workspace`** (`InpatientWorkspaceController.php` / `InpatientWorkspace.vue`): page gate `inpatient.ward.view`; 3-section `can` map (`bed_map`/`census`/`discharges`) all reusing the same slug — one cohesive read-domain, no finer existing distinction.
- **`access-control.workspace`** (`AccessControlWorkspaceController.php` / `AccessControlWorkspace.vue`): page gate = `identity.user.manage` OR `identity.role.manage`; section map: `users`→`identity.user.manage`, `roles`+`permissions`→`identity.role.manage`.
  - **Fix required:** `tests/Feature/TenantIsolationTest.php`'s `"a user cannot list another tenant's facilities via the access-control workspace"` test (~line 96) uses a roleless actor and asserts `assertOk()` — will break. Assign that actor a role with `identity.user.manage`, mirroring the pattern already used two tests below it.
- **`reports.workspace`** section refinement (`ReportsWorkspaceController.php` / `ReportsWorkspace.vue`): 6-tab (`morbidity`/`financial`/`payermix`/`operations`/`pharmaco`/`notifiable`) `can` map reusing `reports.clinical.view` (morbidity, operations, notifiable), `reports.financial.view` (financial, payermix), `reports.pharmacoeconomic.view` (pharmaco) — no new slugs. Page-level bar widens to "ANY of these 3 OR `reports.analytics.view`".
  - Add one new regression test using the **real seeded** `auditor` role (not a fabricated one) hitting `/reports` — `ReportsDomainTest.php`'s existing test at ~line 307 fabricates its own role/permission and is exactly why the original break went undetected.

**Verify:** role matrix per route (nurse/doctor/auditor for inpatient; tenant-admin only for access-control initially since no other role holds `identity.*`; auditor/doctor/insurance-manager/inventory-officer for reports sections). Full Pest suite + Pint.

---

## Phase 4 — Group C: new slug per page, incremental rollout

Order: `queue.index` → `appointments.index` → `dashboard` → `pharmacy.queue`+`pharmacy.inventory` (must move together, see below) → `radiology.workspace` (reuse-only) → `laboratory.workspace` → `procedures.workspace` → `insurance.workspace`.

New slugs (domain, exact convention match): `scheduling.queue.view`, `scheduling.appointment.view`, `pharmacy.inventory.view`, `lab.order.view`, `procedure.order.view`, `insurance.claim.view`. `radiology.workspace` reuses the existing `radiology.order.view` for the page and all 3 tabs (no finer distinction exists elsewhere in the app). `laboratory.workspace`/`procedures.workspace`'s `catalogue` tabs stay ungated (reference data, not patient data).

- **Dashboard** (`DashboardController.php` / `HomeWorkspace.vue`): **no hard page-level 403** — every one of the 10 seeded roles holds `patient.registry.view`, so a page-level bar would be dead code. 7-section `can` map only (reusing `clinical.encounter.view`/`scheduling.queue.view`/`scheduling.appointment.view`/`pharmacy.prescription.view`/`billing.invoice.view`/`patient.registry.view`). `inventory-officer` will see an empty dashboard — confirmed acceptable.
- **Pharmacy** (`DispensingController.php` route `pharmacy.queue`, `InventoryBatchController.php` route `pharmacy.inventory` — both render the same `Domains/Pharmacy/PharmacyQueue.vue`): both controllers must call `buildSectionCanMap()` with the identical map (`queue`→`pharmacy.prescription.view`, `formulary`+`movements`→`pharmacy.inventory.view`) via the shared trait so they can't drift, since a user could otherwise enter through whichever route has the looser page-level bar and switch tabs client-side into data the other route would have blocked. Each route's own page-level bar matches its `initialSection`.

**Test-fixture:** add each new slug to `tests/TestCase.php`'s baseline as its phase lands, only if a `setupTenantEnvironment()`-based test GETs that route. Confirmed hits needing it: `tests/Feature/Domains/LaboratoryDomainTest.php` (~line 150), `InsuranceDomainTest.php` (~line 186), `ProcedureDomainTest.php` (~lines 260, 301).

**Verify per sub-phase:** role matrix + full Pest + Pint, same pattern as prior phases.

---

## Phase 5 — Group D: Inventory (10 new slugs, most sensitive)

Add to catalog: `inventory.stock.view`, `inventory.catalog.view`, `inventory.requisition.view`, `inventory.transfer.view`, `inventory.po.view`, `inventory.predictive.view`, `inventory.grn.view`, `inventory.dda.view`, `inventory.gas.view`, `inventory.stocktake.view`.

Grants:
- **`inventory-officer`**: all 10, including `inventory.dda.view` (confirmed).
- **`pharmacist`**: `stock`, `catalog`, `dda` (already holds `inventory.dda.record`/`pharmacy.inventory.adjust`/`.receive`/`inventory.location.view` — dispensing safety + narcotics accountability).
- **`nurse`**: `dda` (already holds `inventory.dda.record`), `stock` (ward-cabinet awareness, adjacent to `inpatient.mar.administer`).
- Everyone else (doctor, cashier, insurance-manager, receptionist, lab-technologist, radiologist, **auditor**): none of the 10 (confirmed — auditor excluded from DDA per your decision).

Page-level bar: "ANY of the 10" (same pattern as Dashboard — Inventory legitimately serves multiple non-overlapping roles). File: `InventoryWorkspaceController.php` / `InventoryWorkspace.vue`.

**Metrics must be split, not just sections** — `InventoryWorkspaceController` currently computes one flat `metrics` array from the *full* ungated collections before the props array is built (e.g. `total_valuation_tzs` from `$stockBalances`). Once a denied section's collection is conditionally empty, its derived metric(s) must also only be computed inside that section's `if ($can['x'])` block, or the aggregate leaks a real number computed from rows the user can't see individually.

**Vue-side (applies to Inventory + every other multi-section file from Phases 3–5):**
1. Add `can: { type: Object, default: () => ({}) }` to `defineProps`.
2. Wrap each section's nav button in `v-if="can.sectionName"`.
3. Change the hardcoded initial `activeSection = ref('stock')` to pick the first section the user actually has: `ref(props.can.stock ? 'stock' : Object.keys(props.can).find(k => props.can[k]) ?? null)` — handle the all-false case (e.g. a role with zero Inventory grants) without crashing.

**Test-fixture:** audit `InventoryDomainTest.php`, `UniversalInventoryTest.php`, `PredictiveProcurementTest.php`, `PharmacyInventoryTest.php` for GETs against `/inventory` at implementation time; add whichever new slugs they need to `TestCase.php`'s baseline.

---

## Phase 6 — Patient Profile section-level refinement (deferred)

`PatientController::show()` eager-loads everything onto one `$patient` model tree via a single `load([...])` call — no independent top-level props to conditionally omit the way Inventory has. Phase 2 already closes the primary hole (page-level `patient.registry.view`, so a receptionist no longer gets the *entire* chart for free). This phase restructures the `load([...])` array to be conditional on a `can` map (only eager-load `radiologyOrders.reports` if `radiology.order.view`, `invoices`/`encounters.invoices` if `billing.invoice.view`, `encounters.labOrders` if `lab.order.view`, etc.) — a genuine query refactor, sequenced last since it doesn't just mechanically apply the pattern already proven in Phases 2–5.

---

## Explicitly out of scope (separate engagement — do not fold in)

- Ungated **mutating** actions beyond page-views: `BillingController@openShift`/`@closeShift`; most of `InsuranceWorkspaceController` (`generateClaim`/`verifyPolicy`/`storePreAuth`/`batchSubmit`); most of `InventoryWorkspaceController`'s write endpoints (`storeItem`, `storeRequisition`, `approveRequisition`, `issueRequisition`, `confirmRequisition`, `storeTransfer`, `confirmTransfer`, `storePurchaseOrder`, `storeGoodsReceipt`, `storeStocktake`, `searchCatalog`, `generatePredictiveReorder`); `AccessControlWorkspaceController@testPermission` (JSON endpoint — lets any authenticated user probe any other user's permission for any slug, an info-disclosure risk).
- `EncounterController::workspace()`'s GET-triggers-Triage→InProgress side effect (correctness bug, not access control).
- `AppointmentController::index()` returning every staff member's name+email as `providers` regardless of role (data-minimization issue inside an already-to-be-gated route, not a gap in the gate itself).

---

## Verification (every phase)

1. **Live role-matrix check** via curl+cookie-jar (same recipe already used to prove the original `/access-control` exploit): for each touched route, log in as every seeded user whose grant differs (`{role-slug}@afyanova.local` / `password123`, all 11 roles), confirm 200 for roles with the grant and 403 without, and confirm the JSON payload (`page.props`) actually omits denied sections' data keys, not just that the page loads.
2. **`php artisan test`** (full suite) after every phase — apply the named test-fixture fixes from that phase before expecting green; anything red beyond those named tests is a real regression.
3. **`./vendor/bin/pint`** after every phase.
4. **New regression tests per newly-gated route**: (a) role without grant → 403, (b) role with grant → 200 with expected section keys present, (c) for multi-section pages, a role with only some section grants sees exactly those keys. At least one test per route should use the real seeded `DatabaseSeeder` roles, not just `setupTenantEnvironment()`'s fabricated fixture — this is exactly the coverage gap that let `reports.analytics.view` ship broken undetected.
5. **Final pass**: full suite + Pint one more time, plus a repeat of the original live exploit check — `receptionist@afyanova.local` → `GET /access-control` must now be 403, not the full 11-user/all-roles/all-permissions payload originally proven live.

### Critical files
- `routes/web.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Domains/Identity/Services/AuthorizationService.php`
- `app/Core/Traits/AuthorizesWorkspaceAccess.php` (new)
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Domains/Inventory/Http/Controllers/InventoryWorkspaceController.php` + `resources/js/Pages/Workspace/InventoryWorkspace.vue`
- `tests/TestCase.php`
