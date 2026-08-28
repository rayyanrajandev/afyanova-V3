<?php

namespace App\Domains\Billing\Services;

/**
 * Single source of invoice-number formatting. Four call sites —
 * GenerateInvoiceAction, CreateLabOrderAction, CreateProcedureOrderAction,
 * AdministerMedicationAction — each independently invented their own
 * ad-hoc format when creating an invoice inline (INV-Ymd-XXXX via
 * uniqid(), INV-Ymd-NNN via a same-day row count, INV-IPD-XXXXXX), with
 * no functional difference between them — none of the formats are parsed
 * or matched on anywhere downstream (confirmed by grep across app/ and
 * resources/js/). Centralized here so a new invoice looks the same
 * regardless of which domain created it, and so the collision-resistance
 * choice only needs to be made once.
 *
 * invoice_number carries a unique DB constraint (see
 * 2024_01_06_000010_create_invoices_table.php); this doesn't retry on
 * collision, matching the risk profile every prior call site already
 * shipped with — random_bytes gives materially better entropy than the
 * uniqid()-based schemes it replaces, without adding retry-loop
 * complexity none of them had either.
 */
class InvoiceNumberGenerator
{
    public function generate(): string
    {
        return 'INV-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
    }
}
