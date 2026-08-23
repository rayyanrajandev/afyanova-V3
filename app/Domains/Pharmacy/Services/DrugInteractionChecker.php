<?php

namespace App\Domains\Pharmacy\Services;

use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\Prescription;

/**
 * DrugInteractionChecker
 *
 * Evaluates candidate prescriptions against the patient's existing active
 * medications to prevent major/severe Drug-Drug Interactions (DDIs).
 */
class DrugInteractionChecker
{
    /**
     * Known severe clinical drug interaction pairs with mechanisms and risk levels.
     */
    protected array $severeInteractionRules = [
        // ACE Inhibitor + ARB (Dual RAS blockade -> Renal failure / severe hyperkalemia)
        [
            'drug_a' => ['enalapril', 'lisinopril', 'captopril', 'ramipril', 'perindopril', 'ace inhibitor'],
            'drug_b' => ['losartan', 'valsartan', 'candesartan', 'telmisartan', 'olmesartan', 'arb'],
            'severity' => 'Severe',
            'effect' => 'Dual Renin-Angiotensin System blockade significantly increases risk of acute renal failure, hypotension, and fatal hyperkalemia.',
        ],
        // Anticoagulant + NSAID (Major GI Hemorrhage)
        [
            'drug_a' => ['warfarin', 'heparin', 'enoxaparin', 'rivaroxaban', 'apixaban', 'dabigatran', 'anticoagulant'],
            'drug_b' => ['diclofenac', 'ibuprofen', 'ketorolac', 'naproxen', 'indomethacin', 'aspirin', 'nsaid'],
            'severity' => 'Major',
            'effect' => 'Synergistic bleeding risk and severe gastrointestinal ulceration/hemorrhage.',
        ],
        // Nitrates + PDE5 Inhibitors (Fatal Refractory Hypotension)
        [
            'drug_a' => ['nitroglycerin', 'isosorbide dinitrate', 'isosorbide mononitrate', 'nitrate'],
            'drug_b' => ['sildenafil', 'tadalafil', 'vardenafil', 'pde5 inhibitor'],
            'severity' => 'Contraindicated',
            'effect' => 'Severe, life-threatening systemic vasodilation and refractory fatal hypotension.',
        ],
        // Potassium-Sparing Diuretics + Potassium Supplements / ACEI (Fatal Arrhythmias)
        [
            'drug_a' => ['spironolactone', 'eplerenone', 'amiloride', 'triamterene'],
            'drug_b' => ['potassium chloride', 'kcl', 'enalapril', 'lisinopril', 'losartan'],
            'severity' => 'Major',
            'effect' => 'Severe hyperkalemic cardiac arrest.',
        ],
        // Methotrexate + NSAIDs (Bone marrow suppression / Methotrexate toxicity)
        [
            'drug_a' => ['methotrexate'],
            'drug_b' => ['diclofenac', 'ibuprofen', 'naproxen', 'aspirin', 'nsaid'],
            'severity' => 'Severe',
            'effect' => 'NSAIDs reduce renal clearance of Methotrexate, causing fatal bone marrow suppression and pancytopenia.',
        ],
        // Macrolide / Fluoroquinolone + QT-Prolonging Antipsychotics (Torsades de Pointes)
        [
            'drug_a' => ['azithromycin', 'clarithromycin', 'erythromycin', 'ciprofloxacin', 'levofloxacin', 'moxifloxacin'],
            'drug_b' => ['haloperidol', 'amiodarone', 'chlorpromazine', 'ondansetron', 'erythromycin'],
            'severity' => 'Major',
            'effect' => 'Additive QT prolongation leading to fatal ventricular arrhythmias (Torsades de Pointes).',
        ],
    ];

    /**
     * Check if a candidate medication interacts severely with existing active prescriptions for the patient.
     *
     * @return array{has_interaction: bool, severity: ?string, conflicting_drug: ?string, effect: ?string}
     */
    public function check(string $patientId, MedicationFormulary $candidateMed, ?string $excludePrescriptionId = null): array
    {
        $activePrescriptions = Prescription::with('medication')
            ->where('patient_id', $patientId)
            ->whereIn('status', ['Pending', 'Prescribed', 'Verified', 'Dispensed'])
            ->when($excludePrescriptionId, fn ($q) => $q->where('id', '!=', $excludePrescriptionId))
            ->get();

        if ($activePrescriptions->isEmpty()) {
            return ['has_interaction' => false, 'severity' => null, 'conflicting_drug' => null, 'effect' => null];
        }

        $candidateName = strtolower(trim($candidateMed->generic_name ?? ''));
        $candidateClass = strtolower(trim($candidateMed->drug_class ?? ''));

        foreach ($activePrescriptions as $prescription) {
            $existingMed = $prescription->medication;
            if (! $existingMed) {
                continue;
            }

            $existingName = strtolower(trim($existingMed->generic_name ?? ''));
            $existingClass = strtolower(trim($existingMed->drug_class ?? ''));

            foreach ($this->severeInteractionRules as $rule) {
                $matchesA = in_array($candidateName, $rule['drug_a'], true)
                    || in_array($candidateClass, $rule['drug_a'], true);

                $matchesB = in_array($existingName, $rule['drug_b'], true)
                    || in_array($existingClass, $rule['drug_b'], true);

                $reverseMatchesA = in_array($candidateName, $rule['drug_b'], true)
                    || in_array($candidateClass, $rule['drug_b'], true);

                $reverseMatchesB = in_array($existingName, $rule['drug_a'], true)
                    || in_array($existingClass, $rule['drug_a'], true);

                if (($matchesA && $matchesB) || ($reverseMatchesA && $reverseMatchesB)) {
                    return [
                        'has_interaction' => true,
                        'severity' => $rule['severity'],
                        'conflicting_drug' => $existingMed->generic_name,
                        'effect' => $rule['effect'],
                    ];
                }
            }
        }

        return ['has_interaction' => false, 'severity' => null, 'conflicting_drug' => null, 'effect' => null];
    }
}
