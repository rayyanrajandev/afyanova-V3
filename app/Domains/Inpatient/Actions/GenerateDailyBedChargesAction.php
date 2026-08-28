<?php

namespace App\Domains\Inpatient\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLineItem;
use App\Domains\Inpatient\Models\Admission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateDailyBedChargesAction
{
    /**
     * Iterates all currently admitted patients and posts midnight bed & board accommodation charges.
     * Guaranteed idempotent: will not post duplicate bed charges for the same admission on the same day.
     *
     * @param  string|null  $date  YYYY-MM-DD format (defaults to today)
     * @return array{billed_count: int, total_amount: float, skipped_count: int, details: array}
     */
    public function execute(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date)->startOfDay() : Carbon::today();
        $dateFormatted = $targetDate->toDateString();

        $activeAdmissions = Admission::with(['bed.ward', 'patient', 'encounter.invoices'])
            ->where('status', 'Admitted')
            ->where('admitted_at', '<=', $targetDate->copy()->endOfDay())
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('discharged_at')
                    ->orWhere('discharged_at', '>=', $targetDate->copy()->startOfDay());
            })
            ->get();

        $billedCount = 0;
        $totalAmount = 0.0;
        $skippedCount = 0;
        $details = [];

        foreach ($activeAdmissions as $admission) {
            $billed = DB::transaction(function () use ($admission, $targetDate, $dateFormatted, &$totalAmount) {
                $rate = floatval(
                    $admission->bed?->daily_rate_amount > 0
                        ? $admission->bed->daily_rate_amount
                        : ($admission->ward?->daily_base_rate ?? 20000.00)
                );

                $wardName = $admission->ward?->name ?? 'General Ward';
                $bedNumber = $admission->bed?->bed_number ?? 'Standard Bed';
                $description = "Daily Bed & Board - {$wardName} ({$bedNumber}) [{$dateFormatted}]";

                // Idempotency check: verify this admission was not already billed for this date
                $existingCharge = InvoiceLineItem::whereHas('invoice', function ($q) use ($admission) {
                    $q->where('patient_id', $admission->patient_id)
                        ->where(function ($sq) use ($admission) {
                            $sq->where('encounter_id', $admission->encounter_id)
                                ->orWhereNull('encounter_id');
                        });
                })
                    ->where('category', 'Accommodation')
                    ->where('description', 'like', "%[{$dateFormatted}]%")
                    ->exists();

                if ($existingCharge) {
                    return false;
                }

                // Locate open Draft invoice or create new one
                $invoice = null;
                if ($admission->encounter_id) {
                    $invoice = Invoice::where('encounter_id', $admission->encounter_id)
                        ->where('status', 'Draft')
                        ->first();
                }

                if (! $invoice) {
                    $invoice = Invoice::where('patient_id', $admission->patient_id)
                        ->where('status', 'Draft')
                        ->first();
                }

                if (! $invoice) {
                    $invoiceNumber = 'INV-'.date('Y').'-'.strtoupper(Str::random(6));
                    $invoice = Invoice::create([
                        'tenant_id' => $admission->tenant_id,
                        'facility_id' => $admission->facility_id ?? $admission->bed?->facility_id,
                        'patient_id' => $admission->patient_id,
                        'encounter_id' => $admission->encounter_id,
                        'invoice_number' => $invoiceNumber,
                        'total_amount' => 0.00,
                        'paid_amount' => 0.00,
                        'status' => 'Draft',
                    ]);
                }

                InvoiceLineItem::create([
                    'tenant_id' => $admission->tenant_id,
                    'invoice_id' => $invoice->id,
                    'description' => $description,
                    'category' => 'Accommodation',
                    'quantity' => 1,
                    'unit_price' => $rate,
                    'total_price' => $rate,
                ]);

                $invoice->increment('total_amount', $rate);
                $totalAmount += $rate;

                return true;
            });

            if ($billed) {
                $billedCount++;
                $details[] = [
                    'admission_number' => $admission->admission_number,
                    'patient' => "{$admission->patient?->first_name} {$admission->patient?->last_name}",
                    'status' => 'Billed',
                    'date' => $dateFormatted,
                ];
            } else {
                $skippedCount++;
                $details[] = [
                    'admission_number' => $admission->admission_number,
                    'patient' => "{$admission->patient?->first_name} {$admission->patient?->last_name}",
                    'status' => 'Skipped (Already Billed)',
                    'date' => $dateFormatted,
                ];
            }
        }

        return [
            'billed_count' => $billedCount,
            'total_amount' => $totalAmount,
            'skipped_count' => $skippedCount,
            'details' => $details,
        ];
    }
}
