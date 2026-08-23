<?php

namespace App\Domains\Pharmacy\Actions;

use App\Domains\Billing\Actions\GenerateInvoiceAction;
use App\Domains\Billing\Services\ChargePriceResolver;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\Prescription;
use App\Domains\Pharmacy\Services\AllergyInteractionChecker;
use App\Domains\Pharmacy\Services\DrugInteractionChecker;
use InvalidArgumentException;

class PrescribeMedicationAction
{
    public function __construct(
        protected GenerateInvoiceAction $generateInvoiceAction,
        protected ChargePriceResolver $prices,
        protected AllergyInteractionChecker $allergyChecker,
        protected DrugInteractionChecker $ddiChecker
    ) {}

    public function execute(array $data): Prescription
    {
        $patient = Patient::findOrFail((string) $data['patient_id']);

        if ($patient->isDeceased()) {
            throw PharmacyException::deceasedPatient("{$patient->first_name} {$patient->last_name}");
        }

        if ($patient->isMerged()) {
            throw new InvalidArgumentException("Cannot prescribe medication. Patient {$patient->first_name} {$patient->last_name} has been merged into {$patient->merged_into_patient_id}.");
        }

        $medication = MedicationFormulary::findOrFail((string) $data['medication_id']);

        // 1. Structured Codified Allergy & Cross-Reactivity Check
        $allergyResult = $this->allergyChecker->check($data['patient_id'], $medication);
        if ($allergyResult['has_contraindication']) {
            throw PharmacyException::allergyContraindication($allergyResult['allergen'].' ('.$allergyResult['reason'].')');
        }

        // 2. Severe Drug-Drug Interaction (DDI) Check against active prescriptions
        $ddiResult = $this->ddiChecker->check($data['patient_id'], $medication);
        if ($ddiResult['has_interaction']) {
            throw PharmacyException::drugInteractionContraindication(
                $medication->generic_name,
                $ddiResult['conflicting_drug'],
                $ddiResult['severity'],
                $ddiResult['effect']
            );
        }

        // 3. Prevent duplicate active prescriptions in the same consultation
        $existingActiveRx = Prescription::where('encounter_id', $data['encounter_id'])
            ->where('medication_id', $medication->id)
            ->whereIn('status', ['Pending', 'Prescribed', 'Verified'])
            ->exists();

        if ($existingActiveRx) {
            throw new PharmacyException("Medication '{$medication->generic_name}' is already actively prescribed in this consultation. Duplicate prescription blocked.");
        }

        $prescription = Prescription::create([
            'encounter_id' => $data['encounter_id'],
            'patient_id' => $data['patient_id'],
            'prescriber_id' => auth()->id(),
            'medication_id' => $medication->id,
            'dosage' => $data['dosage'],
            'frequency' => $data['frequency'],
            'duration_days' => $data['duration_days'],
            'route' => $data['route'],
            'quantity' => $data['quantity'],
            'instructions' => $data['instructions'] ?? null,
            'status' => 'Pending',
        ]);

        // Automatically append medication charge to encounter's invoice
        $encounter = Encounter::find((string) $data['encounter_id']);
        if ($encounter) {
            if (! $medication->charge_code) {
                throw PharmacyException::missingChargeCode($medication->generic_name);
            }
            $unitPrice = $this->prices->priceFor($medication->charge_code);
            $this->generateInvoiceAction->execute(
                $encounter,
                "Pharmacy: {$medication->generic_name} {$medication->strength} ({$data['quantity']} {$medication->form}s)",
                $unitPrice,
                'Pharmacy',
                (int) $data['quantity']
            );
        }

        return $prescription;
    }
}
