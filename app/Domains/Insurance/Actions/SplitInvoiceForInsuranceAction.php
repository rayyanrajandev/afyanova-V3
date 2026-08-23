<?php

namespace App\Domains\Insurance\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Insurance\Models\InsuranceClaim;
use App\Domains\Insurance\Models\PatientPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SplitInvoiceForInsuranceAction
{
    /**
     * Splits an encounter invoice between Patient Co-Pay (out-of-pocket)
     * and Insurer Accounts Receivable according to the scheme rules.
     *
     * @return array{co_pay_invoice: ?Invoice, insurance_claim: InsuranceClaim, total_billable: float, co_pay_amount: float, claimable_amount: float}
     */
    public function execute(Encounter $encounter, ?PatientPolicy $policy = null): array
    {
        $patient = $encounter->patient;
        if ($patient?->isDeceased()) {
            throw new InvalidArgumentException('Cannot split invoice for a deceased patient.');
        }
        if ($patient?->isMerged()) {
            throw new InvalidArgumentException('Cannot process insurance for a merged patient record.');
        }

        return DB::transaction(function () use ($encounter, $policy, $patient) {
            $policy = $policy ?: PatientPolicy::where('patient_id', $encounter->patient_id)
                ->where('status', 'Active')
                ->latest()
                ->first();

            if (! $policy) {
                throw new InvalidArgumentException("No active insurance policy found for patient {$patient?->first_name} {$patient?->last_name}.");
            }

            // Generate the Claim from Encounter
            $claimAction = app(GenerateClaimFromEncounterAction::class);
            $claim = $claimAction->execute($encounter, $policy);

            $totalBillable = floatval($claim->total_claimed_amount);
            $coPayAmount = floatval($claim->co_pay_amount);
            $claimableAmount = floatval($claim->approved_amount);

            $tenantId = app(TenantContext::class)->getTenantId() ?? $encounter->tenant_id;
            $facilityId = $encounter->facility_id ?? app(FacilityContext::class)->getFacilityId();

            $coPayInvoice = null;

            if ($coPayAmount > 0) {
                // Create dedicated Patient Out-of-Pocket Co-Pay invoice
                $coPayInvoiceNumber = 'INV-'.date('Y').'-'.strtoupper(Str::random(6));

                $coPayInvoice = Invoice::create([
                    'tenant_id' => $tenantId,
                    'facility_id' => $facilityId,
                    'encounter_id' => $encounter->id,
                    'patient_id' => $encounter->patient_id,
                    'invoice_number' => $coPayInvoiceNumber,
                    'total_amount' => $coPayAmount,
                    'paid_amount' => 0.00,
                    'status' => 'Pending',
                    'issued_at' => now(),
                ]);

                $coPayInvoice->items()->create([
                    'tenant_id' => $tenantId,
                    'description' => "Insurance Co-Pay ({$policy->provider?->name} - {$policy->scheme?->name})",
                    'category' => 'CoPay',
                    'quantity' => 1,
                    'unit_price' => $coPayAmount,
                    'total_price' => $coPayAmount,
                ]);
            }

            return [
                'co_pay_invoice' => $coPayInvoice,
                'insurance_claim' => $claim,
                'total_billable' => $totalBillable,
                'co_pay_amount' => $coPayAmount,
                'claimable_amount' => $claimableAmount,
            ];
        });
    }
}
