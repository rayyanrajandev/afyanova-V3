<?php

namespace App\Domains\Pharmacy\Services;

use App\Domains\Clinical\Models\Allergy;
use App\Domains\Pharmacy\Models\MedicationFormulary;

/**
 * AllergyInteractionChecker
 *
 * Provides codified allergen class evaluation and cross-sensitivity
 * mapping instead of fragile SQL LIKE wildcard matches.
 */
class AllergyInteractionChecker
{
    /**
     * Cross-reactivity mapping between drug classes and specific allergens.
     * Maps allergen codes/classes to contraindicated formulary classes & generic names.
     */
    protected array $classCrossReactivityMap = [
        'penicillin' => [
            'penicillin', 'amoxicillin', 'ampicillin', 'cloxacillin', 'piperacillin',
            'amoxicillin/clavulanic acid', 'ampicillin/cloxacillin', 'benzylpenicillin',
            'phenoxymethylpenicillin', 'flucloxacillin', 'beta-lactam', 'cephalosporin',
            'cefazolin', 'ceftriaxone', 'cefotaxime', 'cefixime', 'cefuroxime',
            'cephalexin', 'meropenem', 'imipenem',
        ],
        'beta-lactam' => [
            'penicillin', 'amoxicillin', 'ampicillin', 'cloxacillin', 'piperacillin',
            'cephalosporin', 'ceftriaxone', 'cefuroxime', 'cefazolin', 'meropenem',
        ],
        'cephalosporin' => [
            'cephalosporin', 'cefazolin', 'ceftriaxone', 'cefotaxime', 'cefixime',
            'cefuroxime', 'cephalexin', 'penicillin', 'amoxicillin',
        ],
        'sulfonamide' => [
            'sulfonamide', 'sulfa', 'co-trimoxazole', 'sulfamethoxazole', 'trimethoprim/sulfamethoxazole',
            'sulfadoxine/pyrimethamine', 'furosemide', 'sulfasalazine', 'dapsone',
        ],
        'nsaid' => [
            'nsaid', 'aspirin', 'acetylsalicylic acid', 'ibuprofen', 'diclofenac',
            'ketorolac', 'indomethacin', 'naproxen', 'meloxicam', 'piroxicam', 'celecoxib',
        ],
        'opioid' => [
            'opioid', 'morphine', 'codeine', 'tramadol', 'fentanyl', 'pethidine', 'meperidine', 'oxycodone',
        ],
        'macrolide' => [
            'macrolide', 'erythromycin', 'azithromycin', 'clarithromycin',
        ],
        'fluoroquinolone' => [
            'fluoroquinolone', 'ciprofloxacin', 'levofloxacin', 'moxifloxacin', 'norfloxacin',
        ],
        'aminoglycoside' => [
            'aminoglycoside', 'gentamicin', 'amikacin', 'tobramycin', 'streptomycin',
        ],
    ];

    /**
     * Check if the given medication has an active allergy contraindication for the patient.
     *
     * @return array{has_contraindication: bool, allergen: ?string, severity: ?string, reason: ?string}
     */
    public function check(string $patientId, MedicationFormulary $medication): array
    {
        $activeAllergies = Allergy::where('patient_id', $patientId)
            ->where('status', 'Active')
            ->where('is_deprecated', false)
            ->get();

        if ($activeAllergies->isEmpty()) {
            return ['has_contraindication' => false, 'allergen' => null, 'severity' => null, 'reason' => null];
        }

        $medGeneric = strtolower(trim($medication->generic_name ?? ''));
        $medClass = strtolower(trim($medication->drug_class ?? ''));

        foreach ($activeAllergies as $allergy) {
            $allergen = strtolower(trim($allergy->allergen ?? ''));

            // 1. Direct Name Match
            if ($allergen === $medGeneric || $allergen === $medClass) {
                return [
                    'has_contraindication' => true,
                    'allergen' => $allergy->allergen,
                    'severity' => $allergy->severity ?? 'Severe',
                    'reason' => "Direct allergy match against recorded patient allergy '{$allergy->allergen}'.",
                ];
            }

            // 2. Class Hierarchy & Cross-Reactivity Match
            foreach ($this->classCrossReactivityMap as $classKey => $reactives) {
                $allergenMatchesClass = str_contains($allergen, $classKey) || in_array($allergen, $reactives, true);
                $medicationMatchesClass = str_contains($medGeneric, $classKey)
                    || str_contains($medClass, $classKey)
                    || in_array($medGeneric, $reactives, true)
                    || in_array($medClass, $reactives, true);

                if ($allergenMatchesClass && $medicationMatchesClass) {
                    return [
                        'has_contraindication' => true,
                        'allergen' => $allergy->allergen,
                        'severity' => $allergy->severity ?? 'Severe',
                        'reason' => "Cross-reactivity contraindication: Patient allergic to '{$allergy->allergen}' ({$classKey} class), which cross-reacts with prescribed '{$medication->generic_name}'.",
                    ];
                }
            }
        }

        return ['has_contraindication' => false, 'allergen' => null, 'severity' => null, 'reason' => null];
    }
}
