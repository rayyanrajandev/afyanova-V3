# AfyaNova V3 — Billing & Financial Ledger Architecture

## 1. Financial Principles & Invariants

1. **Zero Frontend Financial Truth**: The frontend never calculates or dictates prices, discounts, tax, co-pays, or totals. All financial arithmetic executes server-side within ACID database transactions using arbitrary-precision math (BCMath / `numeric` types).
2. **Double-Entry General Ledger Integrity**: Every financial transaction (invoice generation, payment, refund, write-off, discount) posts balanced debits and credits to the `financial_ledger_entries` table.
3. **Immutability of Invoices**: Once an Invoice is finalized (`Issued`), it is legally and historically locked. It cannot be updated in place or deleted. Any financial adjustment must be recorded through an auditable **Credit Note**, **Debit Note**, or **Authorized Write-Off**.
4. **Strict Financial Invariant Formulas**:
   $$\text{Gross Total} - \text{Discount Total} + \text{Tax Total} = \text{Net Total}$$
   $$\text{Net Total} = \text{Amount Paid} + \text{Amount Covered by Insurance} + \text{Balance Due}$$

---

## 2. End-to-End Billing Lifecycle

```
┌────────────────────────────────────────────────────────┐
│               1. CLINICAL CHARGE CAPTURE               │
│ - Doctor Consultation Fee                              │
│ - Lab Test Orders                                      │
│ - Pharmacy Dispensed Items                             │
│ - Bed Accommodation & Nursing Charges                  │
└───────────────────────────┬────────────────────────────┘
                            │
                            ▼
┌────────────────────────────────────────────────────────┐
│               2. TARIFF & PRICING ENGINE               │
│ Evaluates Tariff (Cash / NHIF / Jubilee / Corporate)   │
│ Computes Patient Co-pay vs Insurance Claim Portion     │
└───────────────────────────┬────────────────────────────┘
                            │
                            ▼
┌────────────────────────────────────────────────────────┐
│              3. INVOICE AGGREGATE CREATION             │
│ Generates Immutable Invoice & Line Items               │
│ Posts Initial Receivable Debit to Financial Ledger     │
└───────────────────────────┬────────────────────────────┘
                            │
             ┌──────────────┴──────────────┐
             ▼                             ▼
┌─────────────────────────┐   ┌─────────────────────────┐
│ 4A. PATIENT PORTION     │   │ 4B. INSURANCE PORTION   │
│ Cashier POS Collection  │   │ Electronic Claim Batch  │
│ (Cash, M-Pesa, Card)    │   │ Submitted to Insurer    │
└────────────┬────────────┘   └────────────┬────────────┘
             │                             │
             ▼                             ▼
┌────────────────────────────────────────────────────────┐
│            5. DOUBLE-ENTRY LEDGER POSTING              │
│ - Cash Account Debited / Patient AR Credited           │
│ - Remittance Received / Insurance AR Credited          │
└────────────────────────────────────────────────────────┘
```

---

## 3. Master Charge Catalog & Dynamic Tariffs

### 3.1. Master Charge Item (`charge_master_items`)
Defines the base service or product:
- `code`: Unique alphanumeric code (e.g. `CONS-GP-01`, `LAB-FBP-01`, `RAD-XRAY-CHEST`).
- `name`: Human-readable service name.
- `base_cash_price`: Default baseline price.
- `tax_rate_percentage`: Applicable VAT (0% for medical services in Tanzania, standard for cosmetics/non-medical).

### 3.2. Tariff Sheets (`price_list_tariffs` & `tariff_item_prices`)
A facility can maintain multiple active tariffs:
- **Default Cash Tariff**: Standard private walk-in rates.
- **NHIF National Tariff**: Regulated pricing for national health insurance.
- **Private Insurer Tariffs**: Pre-negotiated fee schedules for private insurers (AAR, Jubilee, Strategis).
- **Corporate Corporate Contract Tariffs**: Discounted agreed rates for corporate employee schemes.

---

## 4. Multi-Payer Split Invoicing Mechanics

When an insured patient receives care, the invoice line item automatically calculates the split:

| Item | Unit Tariff Price | Insurer Agreed Tariff | Co-Pay Rule | Patient Owes | Insurance Owes |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Specialist Consultation | TZS 50,000 | TZS 40,000 | 10% Co-pay | TZS 4,000 | TZS 36,000 |
| Full Blood Picture (FBP) | TZS 25,000 | TZS 20,000 | Fully Covered | TZS 0 | TZS 20,000 |
| Non-Covered Medication | TZS 15,000 | Not Covered | 100% Out of Pocket | TZS 15,000 | TZS 0 |
| **Total** | **TZS 90,000** | **TZS 60,000** | — | **TZS 19,000** | **TZS 56,000** |

---

## 5. Double-Entry Accounting Ledger Structure

Every financial transaction writes balancing debit and credit entries:

### Scenario A: Invoice Issuance (TZS 75,000 Total: TZS 19,000 Patient + TZS 56,000 Insurance)
| Account Code | Account Name | Debit (TZS) | Credit (TZS) |
| :--- | :--- | :--- | :--- |
| `1100-AR-PATIENT` | Accounts Receivable - Patient | 19,000.00 | 0.00 |
| `1200-AR-INSURANCE` | Accounts Receivable - Insurance | 56,000.00 | 0.00 |
| `4010-REV-CONSULT` | Revenue - Consultation | 0.00 | 40,000.00 |
| `4020-REV-LAB` | Revenue - Laboratory | 0.00 | 20,000.00 |
| `4030-REV-PHARMACY` | Revenue - Pharmacy | 0.00 | 15,000.00 |
| **Sum** | | **75,000.00** | **75,000.00** |

### Scenario B: Patient Pays Co-Pay via M-Pesa (TZS 19,000)
| Account Code | Account Name | Debit (TZS) | Credit (TZS) |
| :--- | :--- | :--- | :--- |
| `1020-CASH-MPESA` | Mobile Money - M-Pesa Till | 19,000.00 | 0.00 |
| `1100-AR-PATIENT` | Accounts Receivable - Patient | 0.00 | 19,000.00 |
| **Sum** | | **19,000.00** | **19,000.00** |

---

## 6. Daily Cashier Drawer & Shift Reconciliation

To prevent cash leakage and simplify end-of-day reconciliation:
1. **Cashier Shift Session**: A cashier opens a shift with an initial float (opening cash balance).
2. **Real-Time Tracking**: Every payment collected is tagged with the cashier's `shift_id` and payment channel.
3. **Shift Close & Blind Count**: At the end of the shift, the cashier enters the physical counted cash without seeing the system expected total (blind count).
4. **Discrepancy Logging**: The system calculates overages/shortages, flags variances above threshold for supervisor approval, and generates the Shift Reconciliation Summary.
