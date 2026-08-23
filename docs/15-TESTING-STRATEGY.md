# AfyaNova V3 — Comprehensive Testing Strategy

## 1. Testing Philosophy & Healthcare Rigor

In healthcare and financial software, defects can lead to patient harm, regulatory penalties, or massive revenue leakage. 

**AfyaNova V3 mandates automated test coverage as a non-negotiable Definition of Done**.

A feature is not complete until:
- Domain invariants are asserted.
- Multi-tenancy isolation is proven.
- Authorization boundaries are verified.
- Happy-path and edge-case failure modes have passing automated tests.

---

## 2. The AfyaNova Testing Pyramid

```
                       ┌─────────────────────────┐
                       │   End-to-End Tests      │  (Playwright)
                       │  Critical User Flows    │  ~5% of suite
                       ├─────────────────────────┤
                       │    Integration Tests    │  (External APIs,
                       │  Adapters, Gateways     │   FHIR Mappers) ~15%
                       ├─────────────────────────┤
                       │  Feature / HTTP Tests   │  (Inertia Views,
                       │  Policies, Controllers  │   FormRequests) ~30%
                       ├─────────────────────────┤
                       │  Domain Invariant Tests │  (Ledger Balance,
                       │  Stock Math, Immutability│  Zero Neg Stock) ~25%
                       ├─────────────────────────┤
                       │    Domain Unit Tests    │  (Actions, VOs,
                       │  State Transitions      │   Algorithms) ~25%
                       └─────────────────────────┘
```

---

## 3. Test Categories & Implementation Standards

### 3.1. Domain Unit Tests (Pest PHP / PHPUnit)
- **Scope**: Pure business logic, Value Objects (`Money`, `Quantity`), clinical algorithms (BMI calculation, GFR estimation), and state machine transitions.
- **Speed**: < 1 millisecond per test, zero database interaction.

### 3.2. Domain Invariant Tests (`tests/Invariants/`)
Dedicated tests verifying non-negotiable system invariants:
- **Ledger Invariant**: Verifies every invoice generation, payment, and refund creates equal debit and credit totals.
- **Inventory Invariant**: Verifies stock movements cannot push balance below zero; verifies FEFO sorting.
- **Clinical Immutability**: Asserts that updating or deleting a signed clinical note or verified lab result throws an exception.

```php
test('financial ledger debits and credits must always balance to zero', function () {
    $invoice = createTestInvoiceWithSplitBilling(gross: 100000, insurancePortion: 80000, patientPortion: 20000);
    
    $ledgerEntries = FinancialLedgerEntry::where('entity_id', $invoice->id)->get();
    $totalDebits = $ledgerEntries->sum('debit_amount');
    $totalCredits = $ledgerEntries->sum('credit_amount');
    
    expect($totalDebits)->toEqual($totalCredits)
        ->and($totalDebits)->toEqual(100000);
});
```

### 3.3. Multi-Tenancy & Authorization Security Tests
- Tests verify that User A from Tenant 1 cannot access Patient X from Tenant 2 under any circumstances.
- Tests verify that a user lacking the specific facility permission is blocked with HTTP 403 Forbidden.

### 3.4. Integration & Adapter Tests
- Mocks external HTTP calls (M-Pesa API, NHIF portal, SMS gateways) using Laravel's `Http::fake()` and verifies request formatting, signature generation, and response parsing.
- Verifies that generated FHIR R4 JSON payloads pass strict HL7 FHIR R4 JSON schema validation.

### 3.5. Frontend Component & E2E Testing (Vitest & Playwright)
- **Vitest**: Unit tests for Vue 3 composables, allergy alert banners, and reactive form components.
- **Playwright**: End-to-end tests for the 3 core hospital journeys:
  1. *Outpatient Flow*: Registration $\to$ Triage $\to$ Consultation $\to$ Lab Order $\to$ Cashier Payment.
  2. *Pharmacy Dispense*: Prescription Review $\to$ FEFO Batch Allocation $\to$ Stock Decrement.
  3. *Inpatient Bed Lifecycle*: Admission $\to$ Ward Transfer $\to$ MAR Administer $\to$ Discharge.

---

## 4. Continuous Integration (CI) Pipeline & Quality Gates

Every pull request must pass the automated GitHub Actions pipeline:

```yaml
name: AfyaNova CI Quality Gate
jobs:
  code-quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: PHP Linting & Pint
        run: ./vendor/bin/pint --test
      - name: Static Analysis (PHPStan Level 8)
        run: ./vendor/bin/phpstan analyse --memory-limit=2G
      - name: TypeScript & Vue Type Check
        run: pnpm run type-check
      - name: Automated Test Suite (Pest PHP with Coverage)
        run: ./vendor/bin/pest --coverage --min=85
```

- **PHPStan Level 8+**: Enforces strict typing, null safety, and proper generic docblocks across all domain code.
- **Minimum 85% Code Coverage** required across all core domains (`Clinical`, `Billing`, `Inventory`, `Identity`, `Insurance`).
