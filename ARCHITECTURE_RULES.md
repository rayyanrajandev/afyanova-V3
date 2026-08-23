# AfyaNova V3 — Architecture Rules & Engineering Invariants

This document outlines the strict engineering rules that govern all development on AfyaNova V3. **Violations of these rules will result in rejected pull requests.** 

## 1. Zero V2 Legacy Pollution
- **NEVER** inspect, copy, or port code, database schemas, or models from AfyaNova V2.
- **NEVER** replicate V2 technical debt or architectural compromises.
- V3 is a pure Greenfield project. It must be built exclusively according to the new V3 Domain Models and Architectural Decision Records (ADRs).

## 2. No Framework Magic Over Clarity
- Code must be understandable without relying on complex dynamic resolution or deep inheritance trees.
- Prefer explicit method calls and dependency injection over Facades and magic `__call()` methods where business logic is concerned.
- Use explicit Data Transfer Objects (DTOs) for passing complex data structures between domains, never raw associative arrays.

## 3. Strict Domain Boundaries (Modular Monolith)
- A domain (e.g., `Pharmacy`) **MUST NOT** directly execute Eloquent queries (`update()`, `delete()`, `insert()`) against models owned by another domain (e.g., `Billing\Invoice`).
- Cross-domain data mutations must occur via explicit service contracts (e.g., `BillingServiceInterface->postDispenseCharge()`) or via decoupled Domain Events.
- Avoid generic "Repositories" unless wrapping a complex data store; use isolated Action classes (e.g., `CreatePatientAction`) or Service classes for business operations.

## 4. Uncompromising Financial & Stock Ledgers
- **NEVER** build a feature that modifies an issued `Invoice`'s total, an `InvoiceLineItem`'s price, or a `StockBalance` directly in place.
- All financial corrections require a balancing entry (Credit Note, Debit Note, Refund, Write-off) written to the `financial_ledger_entries`.
- All stock corrections require a balancing entry (Adjustment) written to the `stock_movements`.

## 5. Clinical Immutability
- **NEVER** provide an "Edit" or "Delete" function for a signed Clinical Note, recorded Vital Sign, or verified Lab Result.
- All corrections must use the `is_amendment`, `amended_note_id`, and `amendment_reason` pattern to preserve the historical legal medical record.

## 6. Zero Frontend Security Trust
- The frontend (Vue 3 / Inertia) is entirely untrusted. 
- **NEVER** trust a `tenant_id`, `facility_id`, `price`, `discount_amount`, or `permission_flag` passed in a POST request.
- Financial arithmetic, tenant isolation, and authorization evaluation must execute 100% on the server.

## 7. Multi-Tenancy Defense in Depth
- Every new database migration that creates a tenant-owned table **MUST** include a `tenant_id UUID NOT NULL REFERENCES tenants(id)` column.
- Every tenant-owned Eloquent model **MUST** use the `BelongsToTenant` trait which applies the global query scope.
- Row-Level Security (RLS) policies **MUST** be applied to all tenant tables during migration.

## 8. UUIDv7 Primary Keys
- **NEVER** use `BIGINT AUTO_INCREMENT` for primary keys.
- All tables must use `UUID` columns populated with UUIDv7 (time-sorted UUIDs) to prevent ID enumeration and ensure B-Tree locality.

## 9. Definition of Done
A feature is **NOT** done until:
1. Business rules and domain invariants are verified in isolated unit tests.
2. Authorization and HTTP request logic are covered by Feature tests.
3. Multi-tenant isolation is explicitly validated.
4. Relevant Audit Logs are verified to be generating correctly.
5. Code passes PHPStan Level 8 without ignored errors.

## 10. Architecture Change Control
- You cannot silently override or ignore an architectural decision.
- If a new requirement conflicts with existing architecture, you must draft an Architectural Decision Record (ADR), propose the change, explain the risks, and secure approval before modifying code.
