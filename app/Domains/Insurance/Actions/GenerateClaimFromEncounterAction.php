<?php

namespace App\Domains\Insurance\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Billing\Services\ChargePriceResolver;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\PatientPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateClaimFromEncounterAction
{
    public function execute(Encounter $encounter, ?PatientPolicy $policy = null): InsuranceClaim
    {
        return DB::transaction(function () use ($encounter, $policy) {
            $encounter->load(['patient.identifiers', 'diagnoses', 'provider', 'invoices.items', 'prescriptions.medication', 'labOrders.items.labTest']);
            $tenantId = app(TenantContext::class)->getTenantId() ?? $encounter->tenant_id;

            // Resolve Policy
            $activePolicy = $policy ?: PatientPolicy::where('patient_id', $encounter->patient_id)
                ->where('status', 'Active')
                ->latest()
                ->first();

            if (! $activePolicy) {
                throw new \InvalidArgumentException("No active insurance policy found for patient {$encounter->patient?->first_name} {$encounter->patient?->last_name}.");
            }

            // Claim Scrubber Validation Checks
            $scrubberErrors = [];

            // Rule 1: ICD-10 Diagnosis check
            if ($encounter->diagnoses->isEmpty()) {
                $scrubberErrors[] = 'Missing ICD-10 clinical diagnosis on consultation encounter.';
            }

            // Rule 2: Policy Validity check
            if ($activePolicy->policy_expiry_date && $activePolicy->policy_expiry_date->isPast()) {
                $scrubberErrors[] = "Insurance card expired on {$activePolicy->policy_expiry_date->format('Y-m-d')}.";
            }

            // Rule 3: Provider Registration check
            if (! $encounter->provider) {
                $scrubberErrors[] = 'Missing attending clinician signature/record.';
            }

            $scrubberPassed = empty($scrubberErrors);

            // Calculate Claim Items from Invoices or Encounter clinical orders
            $claimItems = [];
            $totalClaimed = 0.00;

            if ($encounter->invoices->isNotEmpty()) {
                $invoice = $encounter->invoices->first();
                foreach ($invoice->items as $invItem) {
                    $claimed = floatval($invItem->total_price);
                    $totalClaimed += $claimed;
                    $claimItems[] = [
                        'item_type' => $invItem->category ?: 'Service',
                        'item_code' => strtoupper(substr($invItem->category ?: 'SRV', 0, 3)).'-001',
                        'description' => $invItem->description,
                        'quantity' => $invItem->quantity ?: 1,
                        'unit_price' => floatval($invItem->unit_price),
                        'claimed_amount' => $claimed,
                        'approved_amount' => $claimed,
                        'status' => 'Claimed',
                    ];
                }
            } else {
                try {
                    $consultationPrice = app(ChargePriceResolver::class)->priceFor('CONSULT-OPD');
                } catch (\Exception) {
                    $consultationPrice = 20000.00;
                }

                // Fallback default consultation claim item
                $totalClaimed = $consultationPrice;
                $claimItems[] = [
                    'item_type' => 'Consultation',
                    'item_code' => 'CONSULT-OPD',
                    'description' => 'General Medical Officer Outpatient Consultation',
                    'quantity' => 1,
                    'unit_price' => $consultationPrice,
                    'claimed_amount' => $consultationPrice,
                    'approved_amount' => $consultationPrice,
                    'status' => 'Claimed',
                ];
            }

            // Calculate Scheme Co-Pay
            $coPay = 0.00;
            if ($activePolicy->scheme) {
                if ($activePolicy->scheme->co_pay_type === 'FixedAmount') {
                    $coPay = min($totalClaimed, floatval($activePolicy->scheme->co_pay_amount));
                } elseif ($activePolicy->scheme->co_pay_type === 'Percentage') {
                    $coPay = $totalClaimed * (floatval($activePolicy->scheme->co_pay_amount) / 100);
                }
            }

            $claimNumber = 'CLM-'.date('Y').'-'.strtoupper(Str::random(6));

            $claim = InsuranceClaim::create([
                'tenant_id' => $tenantId,
                'claim_number' => $claimNumber,
                'patient_id' => $encounter->patient_id,
                'patient_policy_id' => $activePolicy->id,
                'encounter_id' => $encounter->id,
                'invoice_id' => $encounter->invoices->first()?->id,
                'total_claimed_amount' => $totalClaimed,
                'co_pay_amount' => $coPay,
                'approved_amount' => $totalClaimed - $coPay,
                'status' => $scrubberPassed ? 'Vetted' : 'Draft',
                'scrubber_passed' => $scrubberPassed,
                'scrubber_errors' => $scrubberErrors,
            ]);

            foreach ($claimItems as $itemData) {
                $itemData['tenant_id'] = $tenantId;
                $claim->items()->create($itemData);
            }

            return $claim->fresh(['patient', 'policy.provider', 'policy.scheme', 'encounter', 'items']);
        });
    }
}
