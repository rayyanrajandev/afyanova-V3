# ADR-004: Immutable Double-Entry Financial Ledger

## Status
**Accepted**

## Context
Traditional HIS systems often treat billing as mutable status flags on invoice tables (e.g. `is_paid = true`), allowing invoices to be edited or deleted in place. This creates severe accounting discrepancies, facilitates revenue leakage/theft, and fails statutory financial audits.

## Decision
AfyaNova V3 implements an **Immutable Double-Entry Financial Ledger** (`financial_ledger_entries`).
1. Every financial transaction (Invoice issuance, Payment receipt, Patient Refund, Bad-debt Write-off, Insurance Adjustment) writes balancing debit and credit entries.
2. Invoices cannot be modified or deleted once issued. Adjustments require explicit Credit Notes or Debit Notes.
3. Database triggers block `UPDATE` and `DELETE` on financial ledger tables.

## Consequences
### Positive:
- Total financial auditability and compliance with international accounting standards (IFRS / GAAP).
- Zero revenue leakage from unrecorded billing modifications.
- Seamless end-of-day cashier reconciliations and shift summaries.

### Negative:
- Correcting billing mistakes requires generating formal credit notes rather than quick inline edits.
