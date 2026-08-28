<?php

namespace App\Domains\Reports\Actions;

use App\Domains\Clinical\Models\Diagnosis;
use Illuminate\Support\Carbon;

class GenerateMorbidityAnalyticsAction
{
    /**
     * Immediate Notifiable Disease ICD-10 and Keyword Surveillance Dictionary
     */
    protected array $notifiableWatchlist = [
        'Cholera' => ['codes' => ['A00', 'A00.0', 'A00.1', 'A00.9'], 'keywords' => ['cholera']],
        'Measles (Surua)' => ['codes' => ['B05', 'B05.0', 'B05.1', 'B05.9'], 'keywords' => ['measles', 'surua']],
        'Typhoid Fever' => ['codes' => ['A01', 'A01.0'], 'keywords' => ['typhoid', 'salmonella']],
        'Dengue Fever' => ['codes' => ['A90', 'A91'], 'keywords' => ['dengue']],
        'Rabies (Kichaa cha Mbwa)' => ['codes' => ['A82', 'A82.0', 'A82.9'], 'keywords' => ['rabies', 'kichaa cha mbwa']],
        'Bacillary Dysentery' => ['codes' => ['A03', 'A03.0', 'A03.9'], 'keywords' => ['dysentery', 'shigellosis']],
        'Meningococcal Meningitis' => ['codes' => ['A39', 'A39.0', 'G00'], 'keywords' => ['meningitis']],
    ];

    public function execute(?string $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        // No Tenant::first() fallback: silently generating this report
        // against an arbitrary other tenant when neither the caller nor
        // the acting user supplies one is a landmine, not a safety net.
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        $query = Diagnosis::with(['patient', 'encounter'])
            ->where('tenant_id', $tenantId);

        if ($startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate));
        }

        $allDiagnoses = $query->latest()->get();
        $totalCases = $allDiagnoses->count();

        // 1. Group by ICD-10 & Diagnosis Name
        $grouped = $allDiagnoses->groupBy(function ($item) {
            $code = $item->icd_10_code ?: ($item->icd10_code ?: 'UNSPECIFIED');
            $desc = $item->description ?: ($item->diagnosis_description ?: 'General Condition');

            return $code.' - '.$desc;
        });

        $topMorbidity = [];
        $rank = 1;

        foreach ($grouped->sortByDesc->count() as $label => $group) {
            $count = $group->count();
            $first = $group->first();
            $percentage = $totalCases > 0 ? round(($count / $totalCases) * 100, 1) : 0;
            $code = $first->icd_10_code ?: ($first->icd10_code ?: 'Uncoded');
            $desc = $first->description ?: ($first->diagnosis_description ?: 'General Diagnosis');

            // Age & Gender Demographics
            $underFive = 0;
            $fiveToSeventeen = 0;
            $adults = 0;
            $geriatric = 0;
            $males = 0;
            $females = 0;

            foreach ($group as $diag) {
                $patient = $diag->patient;
                if ($patient) {
                    $age = $patient->age;
                    if ($age === null && $patient->dob) {
                        $age = Carbon::parse($patient->dob)->age;
                    }
                    $age = $age ?? 25; // default if not specified

                    if ($age < 5) {
                        $underFive++;
                    } elseif ($age <= 17) {
                        $fiveToSeventeen++;
                    } elseif ($age <= 59) {
                        $adults++;
                    } else {
                        $geriatric++;
                    }

                    if (strtolower($patient->gender ?? '') === 'male' || strtolower($patient->gender ?? '') === 'm') {
                        $males++;
                    } else {
                        $females++;
                    }
                }
            }

            $confirmedCount = $group->filter(fn ($d) => ($d->certainty === 'Confirmed' || $d->status === 'Confirmed'))->count();
            $suspectedCount = $group->filter(fn ($d) => ($d->certainty === 'Suspected' || $d->status === 'Suspected'))->count();

            $topMorbidity[] = [
                'rank' => $rank++,
                'icd10_code' => $code,
                'description' => $desc,
                'total_cases' => $count,
                'percentage' => $percentage,
                'confirmed_cases' => $confirmedCount,
                'suspected_cases' => $suspectedCount,
                'demographics' => [
                    'under_5' => $underFive,
                    'age_5_17' => $fiveToSeventeen,
                    'age_18_59' => $adults,
                    'age_60_plus' => $geriatric,
                    'male' => $males,
                    'female' => $females,
                ],
            ];

            if ($rank > 10) {
                break;
            }
        }

        // 2. Notifiable Disease Watchtower Surveillance
        $notifiableAlerts = [];
        foreach ($this->notifiableWatchlist as $diseaseName => $criteria) {
            $matchingCases = $allDiagnoses->filter(function ($item) use ($criteria) {
                $code = strtoupper($item->icd_10_code ?? ($item->icd10_code ?? ''));
                $codeMatch = in_array($code, array_map('strtoupper', $criteria['codes']));
                if ($codeMatch) {
                    return true;
                }

                $descLower = strtolower($item->description ?? ($item->diagnosis_description ?? ''));
                foreach ($criteria['keywords'] as $keyword) {
                    if (str_contains($descLower, $keyword)) {
                        return true;
                    }
                }

                return false;
            });

            if ($matchingCases->isNotEmpty()) {
                $latest = $matchingCases->sortByDesc('created_at')->first();
                $notifiableAlerts[] = [
                    'disease_name' => $diseaseName,
                    'case_count' => $matchingCases->count(),
                    'confirmed_count' => $matchingCases->filter(fn ($d) => ($d->certainty === 'Confirmed' || $d->status === 'Confirmed'))->count(),
                    'suspected_count' => $matchingCases->filter(fn ($d) => ($d->certainty === 'Suspected' || $d->status === 'Suspected'))->count(),
                    'last_detected_at' => $latest->created_at->format('Y-m-d H:i'),
                    'patient_mrn' => $latest->patient?->primary_mrn,
                    'patient_name' => ($latest->patient?->first_name ?? '').' '.($latest->patient?->last_name ?? ''),
                    'severity' => 'Critical_Immediate_Report',
                ];
            }
        }

        // 3. Demographic Summary Across All Diagnoses
        $totalUnder5 = 0;
        $totalAdults = 0;
        $totalMales = 0;
        $totalFemales = 0;

        foreach ($allDiagnoses as $diag) {
            $p = $diag->patient;
            if ($p) {
                $age = $p->age ?? (Carbon::parse($p->dob)->age ?? 25);
                if ($age < 5) {
                    $totalUnder5++;
                } else {
                    $totalAdults++;
                }
                if (strtolower($p->gender ?? '') === 'male' || strtolower($p->gender ?? '') === 'm') {
                    $totalMales++;
                } else {
                    $totalFemales++;
                }
            }
        }

        return [
            'total_diagnoses' => $totalCases,
            'top_10_morbidity' => $topMorbidity,
            'notifiable_alerts' => $notifiableAlerts,
            'notifiable_alert_count' => count($notifiableAlerts),
            'demographic_summary' => [
                'pediatric_under_5' => $totalUnder5,
                'adults_and_elderly' => $totalAdults,
                'male_total' => $totalMales,
                'female_total' => $totalFemales,
            ],
        ];
    }
}
