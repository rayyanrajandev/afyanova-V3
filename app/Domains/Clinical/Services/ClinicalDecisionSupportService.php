<?php

namespace App\Domains\Clinical\Services;

use App\Domains\Clinical\Models\Allergy;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use Illuminate\Support\Carbon;

class ClinicalDecisionSupportService
{
    /**
     * Comprehensive pharmacological drug-drug interaction rule matrix
     */
    protected array $ddiRules = [
        // Anticoagulants / Antiplatelets + NSAIDs
        [
            'drug_a' => ['warfarin', 'heparin', 'enoxaparin', 'rivaroxaban', 'dabigatran', 'apixaban', 'aspirin', 'clopidogrel'],
            'drug_b' => ['ibuprofen', 'diclofenac', 'meloxicam', 'indomethacin', 'ketorolac', 'naproxen', 'piroxicam', 'celecoxib'],
            'severity' => 'CRITICAL',
            'title' => 'Major Bleeding & GI Hemorrhage Risk',
            'mechanism' => 'Concurrent NSAID use inhibits platelet COX-1 and causes gastrointestinal mucosal erosion, synergistically multiplying hemorrhage risk with anticoagulants.',
            'recommendation' => 'Avoid combination if possible. Use Paracetamol or topical analgesics. If essential, co-prescribe a Proton Pump Inhibitor (PPI) and monitor INR/hemoglobin closely.',
            'requires_override' => true,
        ],
        // ACE Inhibitors / ARBs + Potassium-Sparing Diuretics
        [
            'drug_a' => ['enalapril', 'lisinopril', 'ramipril', 'captopril', 'losartan', 'valsartan', 'candesartan', 'telmisartan'],
            'drug_b' => ['spironolactone', 'eplerenone', 'amiloride', 'triamterene', 'potassium chloride'],
            'severity' => 'CRITICAL',
            'title' => 'Severe Life-Threatening Hyperkalemia Risk',
            'mechanism' => 'Both drug classes reduce aldosterone-mediated potassium excretion in the renal distal tubules, potentially causing fatal cardiac arrhythmias.',
            'recommendation' => 'Check baseline serum potassium and creatinine before initiation. Monitor electrolytes within 1 week of co-administration.',
            'requires_override' => true,
        ],
        // Macrolides / Azoles + Statins (CYP3A4 Inhibition -> Rhabdomyolysis)
        [
            'drug_a' => ['erythromycin', 'clarithromycin', 'ketoconazole', 'itraconazole', 'fluconazole'],
            'drug_b' => ['simvastatin', 'atorvastatin', 'lovastatin'],
            'severity' => 'WARNING',
            'title' => 'Rhabdomyolysis & Statin Toxicity Risk (CYP3A4 Inhibition)',
            'mechanism' => 'Potent CYP3A4 inhibitors dramatically increase serum statin concentrations, increasing the risk of acute myopathy, muscle breakdown, and renal failure.',
            'recommendation' => 'Temporarily withhold statin therapy during antimicrobial course, or switch to Pravastatin/Rosuvastatin (non-CYP3A4 pathway).',
            'requires_override' => false,
        ],
        // PDE5 Inhibitors + Nitrates
        [
            'drug_a' => ['sildenafil', 'tadalafil', 'vardenafil'],
            'drug_b' => ['nitroglycerin', 'isosorbide dinitrate', 'isosorbide mononitrate', 'glyceryl trinitrate'],
            'severity' => 'CRITICAL',
            'title' => 'Fatal Refractory Hypotension & Cardiovascular Collapse',
            'mechanism' => 'Nitrates increase cGMP production while PDE5 inhibitors prevent cGMP degradation, causing uncontrolled systemic vasodilation and coronary hypoperfusion.',
            'recommendation' => 'Absolute contraindication. Never co-administer PDE5 inhibitors and organic nitrates.',
            'requires_override' => true,
        ],
        // Fluoroquinolones + Polyvalent Cations / Antacids
        [
            'drug_a' => ['ciprofloxacin', 'levofloxacin', 'moxifloxacin', 'norfloxacin'],
            'drug_b' => ['antacid', 'magnesium trisilicate', 'aluminium hydroxide', 'calcium carbonate', 'ferrous sulfate', 'zinc sulfate'],
            'severity' => 'WARNING',
            'title' => 'Chelation & Antimicrobial Treatment Failure',
            'mechanism' => 'Polyvalent metal cations form insoluble chelate complexes with fluoroquinolones in the GI tract, reducing antibiotic bioavailability by over 70%.',
            'recommendation' => 'Administer fluoroquinolones at least 2 hours before or 4 hours after mineral supplements and antacids.',
            'requires_override' => false,
        ],
        // SSRIs / SNRIs + Tramadol / Linezolid / MAOIs (Serotonin Syndrome)
        [
            'drug_a' => ['fluoxetine', 'sertraline', 'escitalopram', 'citalopram', 'paroxetine', 'amitriptyline'],
            'drug_b' => ['tramadol', 'linezolid', 'pethidine', 'meperidine'],
            'severity' => 'WARNING',
            'title' => 'Serotonin Syndrome Toxicity Risk',
            'mechanism' => 'Concurrent enhancement of central serotonergic neurotransmission can cause hyperthermia, autonomic instability, hyperreflexia, and tremors.',
            'recommendation' => 'Monitor for symptoms of serotonin toxicity (agitation, myoclonus, sweating). Consider alternative non-opioid analgesics.',
            'requires_override' => false,
        ],
        // Methotrexate + NSAIDs
        [
            'drug_a' => ['methotrexate'],
            'drug_b' => ['ibuprofen', 'diclofenac', 'naproxen', 'indomethacin', 'meloxicam', 'aspirin'],
            'severity' => 'CRITICAL',
            'title' => 'Severe Methotrexate Toxicity & Pancytopenia',
            'mechanism' => 'NSAIDs reduce renal blood flow and compete for renal organic anion tubular secretion, elevating methotrexate blood levels to toxic thresholds.',
            'recommendation' => 'Avoid NSAID co-administration during moderate/high-dose methotrexate regimens. Monitor complete blood count and liver/renal panels.',
            'requires_override' => true,
        ],
    ];

    /**
     * Therapeutic class mappings for duplicate therapy detection
     */
    protected array $therapeuticClasses = [
        'NSAID' => ['ibuprofen', 'diclofenac', 'meloxicam', 'indomethacin', 'ketorolac', 'naproxen', 'piroxicam', 'celecoxib', 'aspirin'],
        'Proton Pump Inhibitor (PPI)' => ['omeprazole', 'esomeprazole', 'pantoprazole', 'lansoprazole', 'rabeprazole'],
        'ACE Inhibitor' => ['enalapril', 'lisinopril', 'ramipril', 'captopril', 'perindopril'],
        'Angiotensin Receptor Blocker (ARB)' => ['losartan', 'valsartan', 'candesartan', 'telmisartan'],
        'Beta Blocker' => ['atenolol', 'bisoprolol', 'metoprolol', 'carvedilol', 'propranolol'],
        'Statin' => ['simvastatin', 'atorvastatin', 'rosuvastatin', 'pravastatin'],
        'Fluoroquinolone' => ['ciprofloxacin', 'levofloxacin', 'moxifloxacin', 'norfloxacin'],
        'Macrolide' => ['azithromycin', 'erythromycin', 'clarithromycin'],
        'Benzodiazepine' => ['diazepam', 'lorazepam', 'midazolam', 'alprazolam', 'clonazepam'],
    ];

    /**
     * Cross-allergen mapping rules
     */
    protected array $allergyCrossRules = [
        'penicillin' => [
            'exact' => ['penicillin', 'amoxicillin', 'ampicillin', 'amoxiclav', 'flucloxacillin', 'piperacillin', 'penicillin v', 'benzathine penicillin'],
            'cross_class' => [
                'targets' => ['ceftriaxone', 'cefuroxime', 'cefixime', 'cefalexin', 'cefazolin', 'cefotaxime', 'meropenem', 'imipenem'],
                'severity' => 'WARNING',
                'title' => 'Beta-Lactam / Cephalosporin Cross-Reactivity Alert',
                'message' => 'Patient has a documented Penicillin allergy. Cephalosporins and Carbapenems carry an estimated 3-10% cross-reactivity risk due to shared beta-lactam core structure.',
            ],
        ],
        'sulfa' => [
            'exact' => ['sulfamethoxazole', 'cotrimoxazole', 'septrin', 'bactrim', 'sulfadoxine', 'fansidar', 'sulfasalazine', 'dapsone'],
            'cross_class' => [
                'targets' => ['furosemide', 'hydrochlorothiazide', 'glibenclamide', 'celecoxib'],
                'severity' => 'INFO',
                'title' => 'Potential Sulfonamide Moiety Cross-Sensitivity',
                'message' => 'Patient has documented Sulfa allergy. Cross-reactivity with non-arylamine sulfonamides (e.g. thiazides, loop diuretics) is low but warrants monitoring.',
            ],
        ],
        'aspirin' => [
            'exact' => ['aspirin', 'acetylsalicylic acid'],
            'cross_class' => [
                'targets' => ['ibuprofen', 'diclofenac', 'naproxen', 'meloxicam', 'indomethacin', 'ketorolac'],
                'severity' => 'CRITICAL',
                'title' => 'NSAID Cross-Hypersensitivity & Bronchospasm Risk',
                'message' => 'Patient has documented Aspirin hypersensitivity. Non-selective NSAIDs cross-react in up to 90% of aspirin-exacerbated respiratory disease (AERD) patients.',
            ],
        ],
    ];

    /**
     * Evaluate complete prescription cart against allergies, active medications, and renal clearance
     */
    public function evaluatePrescription(string $patientId, array $items, array $existingPrescriptions = []): array
    {
        $alerts = [];
        $patient = Patient::find($patientId);
        $patientAllergies = Allergy::where('patient_id', $patientId)->where('is_deprecated', false)->get();

        // 1. Resolve medication names and generic ingredients
        $medicationDetails = [];
        foreach ($items as $item) {
            $formulary = MedicationFormulary::find($item['medication_id'] ?? null);
            if ($formulary) {
                $medicationDetails[] = [
                    'id' => $formulary->id,
                    'name' => strtolower($formulary->name),
                    'generic_name' => strtolower($formulary->generic_name ?? $formulary->name),
                    'display_name' => $formulary->name,
                    'dosage' => $item['dosage'] ?? '',
                    'frequency' => $item['frequency'] ?? '',
                ];
            }
        }

        // Add existing active prescriptions into the interaction pool
        $allActiveMeds = $medicationDetails;
        foreach ($existingPrescriptions as $ex) {
            $formulary = MedicationFormulary::find($ex['medication_id'] ?? null);
            if ($formulary) {
                $allActiveMeds[] = [
                    'id' => $formulary->id,
                    'name' => strtolower($formulary->name),
                    'generic_name' => strtolower($formulary->generic_name ?? $formulary->name),
                    'display_name' => $formulary->name,
                    'is_existing' => true,
                ];
            }
        }

        // 2. Check Allergy & Cross-Reactivity
        foreach ($medicationDetails as $med) {
            foreach ($patientAllergies as $allergy) {
                $allergen = strtolower($allergy->allergen);

                // Exact Allergen Matching
                if (str_contains($med['name'], $allergen) || str_contains($med['generic_name'], $allergen)) {
                    $alerts[] = [
                        'type' => 'DIRECT_ALLERGY',
                        'severity' => 'CRITICAL',
                        'title' => "Direct Allergen Match: {$allergy->allergen}",
                        'description' => "Patient has a documented '{$allergy->allergen}' allergy (Severity: {$allergy->severity}). Prescribing {$med['display_name']} is strongly contraindicated.",
                        'recommendation' => 'Select an alternative medication from an unrelated pharmacological class.',
                        'requires_override' => true,
                    ];
                }

                // Cross-Reactivity Rules
                foreach ($this->allergyCrossRules as $key => $rule) {
                    if (str_contains($allergen, $key)) {
                        // Check exact class
                        foreach ($rule['exact'] as $exactItem) {
                            if (str_contains($med['name'], $exactItem) || str_contains($med['generic_name'], $exactItem)) {
                                $alerts[] = [
                                    'type' => 'ALLERGY_EXACT_CLASS',
                                    'severity' => 'CRITICAL',
                                    'title' => "Allergy Class Match: {$allergy->allergen}",
                                    'description' => "Prescribed medication {$med['display_name']} belongs to the {$key} group matching patient's documented allergy.",
                                    'recommendation' => 'Cancel item and prescribe safe non-cross-reactive alternative.',
                                    'requires_override' => true,
                                ];
                            }
                        }

                        // Check cross-class
                        if (isset($rule['cross_class'])) {
                            foreach ($rule['cross_class']['targets'] as $target) {
                                if (str_contains($med['name'], $target) || str_contains($med['generic_name'], $target)) {
                                    $alerts[] = [
                                        'type' => 'ALLERGY_CROSS_REACTIVITY',
                                        'severity' => $rule['cross_class']['severity'],
                                        'title' => $rule['cross_class']['title'],
                                        'description' => "{$med['display_name']}: " . $rule['cross_class']['message'],
                                        'recommendation' => 'Ensure emergency anaphylaxis readiness or select alternative antimicrobial.',
                                        'requires_override' => $rule['cross_class']['severity'] === 'CRITICAL',
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3. Check Drug-Drug Interactions (DDI)
        $checkedPairs = [];
        for ($i = 0; $i < count($allActiveMeds); $i++) {
            for ($j = $i + 1; $j < count($allActiveMeds); $j++) {
                $medA = $allActiveMeds[$i];
                $medB = $allActiveMeds[$j];
                $pairKey = $medA['id'] . '-' . $medB['id'];

                if (in_array($pairKey, $checkedPairs)) continue;
                $checkedPairs[] = $pairKey;

                foreach ($this->ddiRules as $rule) {
                    $matchA = $this->matchesDrugList($medA, $rule['drug_a']) && $this->matchesDrugList($medB, $rule['drug_b']);
                    $matchB = $this->matchesDrugList($medB, $rule['drug_a']) && $this->matchesDrugList($medA, $rule['drug_b']);

                    if ($matchA || $matchB) {
                        $alerts[] = [
                            'type' => 'DRUG_DRUG_INTERACTION',
                            'severity' => $rule['severity'],
                            'title' => "DDI: {$medA['display_name']} + {$medB['display_name']}",
                            'description' => "{$rule['title']} — {$rule['mechanism']}",
                            'recommendation' => $rule['recommendation'],
                            'requires_override' => $rule['requires_override'] ?? false,
                        ];
                    }
                }
            }
        }

        // 4. Duplicate Therapy Detection
        $classBuckets = [];
        foreach ($allActiveMeds as $med) {
            foreach ($this->therapeuticClasses as $className => $drugList) {
                if ($this->matchesDrugList($med, $drugList)) {
                    $classBuckets[$className][] = $med['display_name'];
                }
            }
        }

        foreach ($classBuckets as $className => $medNames) {
            $uniqueNames = array_unique($medNames);
            if (count($uniqueNames) > 1) {
                $alerts[] = [
                    'type' => 'DUPLICATE_THERAPY',
                    'severity' => 'WARNING',
                    'title' => "Duplicate Therapeutic Class: {$className}",
                    'description' => "Patient has multiple active drugs in the {$className} class: " . implode(', ', $uniqueNames) . ".",
                    'recommendation' => 'Review whether concurrent multi-agent therapy in this class is clinically indicated.',
                    'requires_override' => false,
                ];
            }
        }

        // 5. Renal Dosing & eGFR Safety Check
        $egfrResult = $this->estimatePatientEgfr($patient);
        if ($egfrResult) {
            $egfr = $egfrResult['egfr'];
            foreach ($medicationDetails as $med) {
                $name = $med['generic_name'];

                // Metformin in Renal Impairment
                if (str_contains($name, 'metformin') && $egfr < 30) {
                    $alerts[] = [
                        'type' => 'RENAL_CONTRAINDICATION',
                        'severity' => 'CRITICAL',
                        'title' => "Lactic Acidosis Hazard: Metformin (eGFR: {$egfr} mL/min)",
                        'description' => "Metformin is strictly contraindicated at eGFR < 30 mL/min/1.73m² due to severe risk of fatal lactic acidosis.",
                        'recommendation' => 'Discontinue Metformin. Switch to Insulin, Linagliptin, or alternative non-renally excreted antidiabetic agent.',
                        'requires_override' => true,
                    ];
                } elseif (str_contains($name, 'metformin') && $egfr < 45) {
                    $alerts[] = [
                        'type' => 'RENAL_DOSE_ADJUSTMENT',
                        'severity' => 'WARNING',
                        'title' => "Dose Cap Required: Metformin (eGFR: {$egfr} mL/min)",
                        'description' => "At eGFR 30–44 mL/min/1.73m², maximum daily Metformin dose must not exceed 1000mg/day (500mg BD).",
                        'recommendation' => 'Verify that prescribed daily dose is ≤ 1000mg and monitor renal function Q3M.',
                        'requires_override' => false,
                    ];
                }

                // Ciprofloxacin in Renal Impairment
                if (str_contains($name, 'ciprofloxacin') && $egfr < 30) {
                    $alerts[] = [
                        'type' => 'RENAL_DOSE_ADJUSTMENT',
                        'severity' => 'WARNING',
                        'title' => "Renal Dose Reduction: Ciprofloxacin (eGFR: {$egfr} mL/min)",
                        'description' => "Ciprofloxacin clearance is substantially reduced at eGFR < 30 mL/min.",
                        'recommendation' => 'Reduce dose by 50% (e.g. 250mg–500mg Q12H to 250mg–500mg Q24H) to prevent neurotoxicity/seizures.',
                        'requires_override' => false,
                    ];
                }

                // Aminoglycosides (Gentamicin)
                if (str_contains($name, 'gentamicin') && $egfr < 50) {
                    $alerts[] = [
                        'type' => 'NEPHROTOXICITY_WARNING',
                        'severity' => 'CRITICAL',
                        'title' => "Nephrotoxic Aminoglycoside Alert: Gentamicin (eGFR: {$egfr} mL/min)",
                        'description' => "Gentamicin accumulates rapidly in renal failure, accelerating acute tubular necrosis (ATN) and ototoxicity.",
                        'recommendation' => 'Extend dosing interval (e.g. Q36H or Q48H) and order therapeutic drug monitoring (peak/trough levels).',
                        'requires_override' => true,
                    ];
                }
            }
        }

        $hasCritical = collect($alerts)->contains('severity', 'CRITICAL');
        $hasWarning = collect($alerts)->contains('severity', 'WARNING');

        return [
            'is_safe' => ! $hasCritical && ! $hasWarning,
            'summary_status' => $hasCritical ? 'CRITICAL_ALERTS' : ($hasWarning ? 'WARNINGS_DETECTED' : 'CLEAR'),
            'total_alerts' => count($alerts),
            'critical_count' => collect($alerts)->where('severity', 'CRITICAL')->count(),
            'warning_count' => collect($alerts)->where('severity', 'WARNING')->count(),
            'info_count' => collect($alerts)->where('severity', 'INFO')->count(),
            'requires_override' => collect($alerts)->contains('requires_override', true),
            'egfr_info' => $egfrResult,
            'alerts' => $alerts,
        ];
    }

    /**
     * Calculate Modified Early Warning Score (MEWS) from clinical vital signs
     */
    public function calculateMews(array $vitals, ?int $patientAge = null): array
    {
        $sbp = floatval($vitals['systolic_bp'] ?? 120);
        $hr = floatval($vitals['heart_rate'] ?? 75);
        $rr = floatval($vitals['respiratory_rate'] ?? 16);
        $temp = floatval($vitals['temperature_c'] ?? 36.8);
        $spo2 = floatval($vitals['oxygen_saturation'] ?? 98);
        $avpu = strtoupper($vitals['avpu'] ?? 'A'); // A = Alert, V = Responds to Voice, P = Responds to Pain, U = Unresponsive

        $breakdown = [];
        $totalScore = 0;

        // 1. Systolic Blood Pressure Score
        if ($sbp <= 70) {
            $sbpScore = 3;
        } elseif ($sbp <= 80) {
            $sbpScore = 2;
        } elseif ($sbp <= 100) {
            $sbpScore = 1;
        } elseif ($sbp <= 199) {
            $sbpScore = 0;
        } else {
            $sbpScore = 2;
        }
        $breakdown['systolic_bp'] = ['value' => $sbp, 'score' => $sbpScore];
        $totalScore += $sbpScore;

        // 2. Heart Rate Score
        if ($hr <= 40) {
            $hrScore = 2;
        } elseif ($hr <= 50) {
            $hrScore = 1;
        } elseif ($hr <= 100) {
            $hrScore = 0;
        } elseif ($hr <= 110) {
            $hrScore = 1;
        } elseif ($hr <= 129) {
            $hrScore = 2;
        } else {
            $hrScore = 3;
        }
        $breakdown['heart_rate'] = ['value' => $hr, 'score' => $hrScore];
        $totalScore += $hrScore;

        // 3. Respiratory Rate Score
        if ($rr < 9) {
            $rrScore = 2;
        } elseif ($rr <= 14) {
            $rrScore = 0;
        } elseif ($rr <= 20) {
            $rrScore = 1;
        } elseif ($rr <= 29) {
            $rrScore = 2;
        } else {
            $rrScore = 3;
        }
        $breakdown['respiratory_rate'] = ['value' => $rr, 'score' => $rrScore];
        $totalScore += $rrScore;

        // 4. Body Temperature Score
        if ($temp < 35.0) {
            $tempScore = 2;
        } elseif ($temp <= 38.4) {
            $tempScore = 0;
        } else {
            $tempScore = 2;
        }
        $breakdown['temperature_c'] = ['value' => $temp, 'score' => $tempScore];
        $totalScore += $tempScore;

        // 5. Oxygen Saturation (SpO2) Score
        if ($spo2 <= 91) {
            $spo2Score = 3;
        } elseif ($spo2 <= 93) {
            $spo2Score = 2;
        } elseif ($spo2 <= 95) {
            $spo2Score = 1;
        } else {
            $spo2Score = 0;
        }
        $breakdown['oxygen_saturation'] = ['value' => $spo2, 'score' => $spo2Score];
        $totalScore += $spo2Score;

        // 6. Neurological Consciousness (AVPU)
        $avpuScore = match ($avpu) {
            'A' => 0, // Alert
            'V' => 1, // Responding to Voice
            'P' => 2, // Responding to Pain
            'U' => 3, // Unresponsive
            default => 0,
        };
        $breakdown['avpu'] = ['value' => $avpu, 'score' => $avpuScore];
        $totalScore += $avpuScore;

        // Clinical Risk Tier & Nursing Escalation Protocol
        if ($totalScore >= 6 || $sbpScore === 3 || $hrScore === 3 || $rrScore === 3) {
            $riskLevel = 'CRITICAL';
            $color = '#ef4444'; // Red
            $title = 'Critical Clinical Deterioration / Code Yellow';
            $protocol = [
                'Immediately notify Ward In-Charge and Medical Officer on duty.',
                'Continuous cardiac and pulse oximetry monitoring (repeat vitals Q15min).',
                'Ensure patent airway, deliver high-flow supplemental oxygen.',
                'Secure wide-bore IV cannula access (16G/18G) and prepare emergency resuscitation trolley.',
                'Prepare for potential ICU/HDU transfer consultation.',
            ];
        } elseif ($totalScore >= 4) {
            $riskLevel = 'HIGH';
            $color = '#f97316'; // Orange / Amber
            $title = 'High Deterioration Risk';
            $protocol = [
                'Inform attending clinician within 30 minutes.',
                'Increase vital signs monitoring frequency to every 30–60 minutes.',
                'Review fluid intake/output balance and recent laboratory investigations.',
            ];
        } elseif ($totalScore >= 2) {
            $riskLevel = 'MEDIUM';
            $color = '#eab308'; // Yellow
            $title = 'Moderate Risk - Increased Surveillance';
            $protocol = [
                'Repeat vital signs observation in 2–4 hours.',
                'Verify pain control, hydration status, and baseline clinical trajectory.',
            ];
        } else {
            $riskLevel = 'LOW';
            $color = '#22c55e'; // Green
            $title = 'Physiologically Stable';
            $protocol = [
                'Continue routine 6–8 hourly ward observation schedule.',
            ];
        }

        return [
            'total_score' => $totalScore,
            'risk_level' => $riskLevel,
            'color' => $color,
            'title' => $title,
            'escalation_protocol' => $protocol,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Estimate eGFR from patient's latest Serum Creatinine lab record
     */
    protected function estimatePatientEgfr(?Patient $patient): ?array
    {
        if (! $patient) return null;

        // Look for latest completed serum creatinine investigation
        $creatItem = LabOrderItem::whereHas('labOrder', fn ($q) => $q->where('patient_id', $patient->id))
            ->whereHas('labTest', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%creatinine%'])->orWhereRaw('LOWER(test_code) LIKE ?', ['%creat%']))
            ->where('status', 'Completed')
            ->where('is_deprecated', false)
            ->latest('created_at')
            ->first();

        $scr = null;
        if ($creatItem && is_array($creatItem->results)) {
            $scr = floatval($creatItem->results['numeric_value'] ?? $creatItem->results['creatinine'] ?? $creatItem->results['value'] ?? null);
        }

        // Fallback default if not recorded in lab
        if (! $scr || $scr <= 0) {
            return null;
        }

        $age = $patient->age ?? 45;
        $isFemale = strtolower($patient->gender ?? 'male') === 'female';

        // CKD-EPI Formula
        $k = $isFemale ? 0.7 : 0.9;
        $a = $isFemale ? -0.329 : -0.411;
        $scrMgDl = $scr > 15 ? $scr / 88.4 : $scr; // Convert µmol/L to mg/dL if needed

        $minVal = min($scrMgDl / $k, 1);
        $maxVal = max($scrMgDl / $k, 1);

        $egfr = 141 * pow($minVal, $a) * pow($maxVal, -1.209) * pow(0.993, $age);
        if ($isFemale) {
            $egfr *= 1.018;
        }

        $egfrRounded = round($egfr, 1);

        return [
            'serum_creatinine' => $scr,
            'unit' => $scr > 15 ? 'µmol/L' : 'mg/dL',
            'egfr' => $egfrRounded,
            'stage' => $egfrRounded >= 90 ? 'G1 (Normal)' : ($egfrRounded >= 60 ? 'G2 (Mild CKD)' : ($egfrRounded >= 30 ? 'G3 (Moderate CKD)' : ($egfrRounded >= 15 ? 'G4 (Severe CKD)' : 'G5 (Kidney Failure)'))),
            'tested_at' => $creatItem->created_at?->toDateString(),
        ];
    }

    protected function matchesDrugList(array $med, array $targetList): bool
    {
        foreach ($targetList as $target) {
            if (str_contains($med['name'], $target) || str_contains($med['generic_name'], $target)) {
                return true;
            }
        }
        return false;
    }
}
