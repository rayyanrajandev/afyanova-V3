# ADR-005: Perpetual Inventory Double-Entry Movement Ledger & FEFO

## Status
**Accepted**

## Context
Hospital pharmacies and medical stores frequently suffer from unexplained stock shrinkage, expired medication dispensing, and negative stock anomalies when inventory systems allow balance overwrites without transaction auditing.

## Decision
AfyaNova V3 enforces:
1. **Double-Entry Stock Movement Ledger**: Stock balance is an aggregate state computed from immutable `stock_movements` rows. Balance rows (`stock_balances`) serve as read caches updated exclusively via movement actions.
2. **Zero Negative Stock Rule**: The inventory engine strictly prevents transactions that would reduce physical stock at any location below zero.
3. **FEFO (First-Expired, First-Out)**: Batches with the nearest expiration date are automatically prioritized for dispensing and stock transfers. Expired batches are locked from clinical selection.

## Consequences
### Positive:
- Unbroken audit trail of all pharmaceutical receipts, transfers, and patient dispenses.
- Elimination of medication waste from undetected batch expirations.
- Zero unexplained inventory discrepancies.

### Negative:
- Staff must maintain disciplined batch and goods receiving data entry when new stock arrives.
