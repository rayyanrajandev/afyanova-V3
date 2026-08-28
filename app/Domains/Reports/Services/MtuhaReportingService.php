<?php

namespace App\Domains\Reports\Services;

use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Clinical\Models\LabOrderItem;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MtuhaReportingService
{

    /**
     * Generate MTUHA Book 1 (Outpatient / OPD Morbidity & Attendance Register)
     */
    public function generateBook1OpdReport(string $tenantId, ?string $startDate = null, ?string $endDate = null, ?string $facilityId = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $encountersQuery = Encounter::with(['patient', 'diagnoses'])
            ->where('tenant_id', $tenantId)
            ->where('encounter_type', '!=', 'Inpatient')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($facilityId) {
            $encountersQuery->where('facility_id', $facilityId);
        }

        $encounters = $encountersQuery->get();

        $totalOpdAttendances = $encounters->count();
        $underFiveMale = 0;
        $underFiveFemale = 0;
        $overFiveMale = 0;
        $overFiveFemale = 0;

        foreach ($encounters as $enc) {
            $patient = $enc->patient;
            if (! $patient) continue;

            $dob = $patient->date_of_birth ? Carbon::parse($patient->date_of_birth) : null;
            $age = $dob ? $dob->age : ($patient->age_years ?? 25);
            $gender = strtolower($patient->gender ?? 'male');

            if ($age < 5) {
                if ($gender === 'female' || $gender === 'f') {
                    $underFiveFemale++;
                } else {
                    $underFiveMale++;
                }
            } else {
                if ($gender === 'female' || $gender === 'f') {
                    $overFiveFemale++;
                } else {
                    $overFiveMale++;
                }
            }
        }

        // Aggregate Morbidity by ICD-10
        $diagnosesQuery = Diagnosis::with(['patient'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $diagnoses = $diagnosesQuery->get();

        $morbidityTally = [];
        foreach ($diagnoses as $diag) {
            $code = $diag->icd_10_code ?: ($diag->icd10_code ?: 'R69');
            $desc = $diag->description ?: 'Illness, unspecified';
            $key = $code . ' - ' . $desc;

            if (! isset($morbidityTally[$key])) {
                $morbidityTally[$key] = [
                    'icd10_code' => $code,
                    'description' => $desc,
                    'under_5_male' => 0,
                    'under_5_female' => 0,
                    'over_5_male' => 0,
                    'over_5_female' => 0,
                    'total_cases' => 0,
                ];
            }

            $dob = $diag->patient?->date_of_birth ? Carbon::parse($diag->patient->date_of_birth) : null;
            $age = $dob ? $dob->age : ($diag->patient?->age_years ?? 25);
            $gender = strtolower($diag->patient?->gender ?? 'male');

            if ($age < 5) {
                if ($gender === 'female' || $gender === 'f') {
                    $morbidityTally[$key]['under_5_female']++;
                } else {
                    $morbidityTally[$key]['under_5_male']++;
                }
            } else {
                if ($gender === 'female' || $gender === 'f') {
                    $morbidityTally[$key]['over_5_female']++;
                } else {
                    $morbidityTally[$key]['over_5_male']++;
                }
            }
            $morbidityTally[$key]['total_cases']++;
        }

        uasort($morbidityTally, fn ($a, $b) => $b['total_cases'] <=> $a['total_cases']);

        return [
            'book' => 'MTUHA Book 1 - OPD Registry',
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_opd_attendances' => $totalOpdAttendances,
                'under_five_male' => $underFiveMale,
                'under_five_female' => $underFiveFemale,
                'over_five_male' => $overFiveMale,
                'over_five_female' => $overFiveFemale,
                'total_under_five' => $underFiveMale + $underFiveFemale,
                'total_over_five' => $overFiveMale + $overFiveFemale,
            ],
            'top_morbidity_tallies' => array_values(array_slice($morbidityTally, 0, 30)),
        ];
    }

    /**
     * Generate MTUHA Book 2 (Inpatient / IPD Admissions, Discharges & Mortality Register)
     */
    public function generateBook2IpdReport(string $tenantId, ?string $startDate = null, ?string $endDate = null, ?string $facilityId = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $admissions = Admission::with(['patient', 'ward', 'bed'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('admitted_at', [$startDate, $endDate])
            ->get();

        $discharges = Admission::with(['patient', 'ward', 'bed'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'Discharged')
            ->whereBetween('discharged_at', [$startDate, $endDate])
            ->get();

        $totalAdmissions = $admissions->count();
        $totalDischarges = $discharges->count();
        
        $totalDaysStay = 0;
        $deathsUnder5 = 0;
        $deathsGeneral = 0;
        $dischargedAlive = 0;
        $absconded = 0;
        $transferredOut = 0;

        foreach ($discharges as $d) {
            $los = $d->length_of_stay_days ?? 1;
            $totalDaysStay += $los;

            $disposition = strtolower($d->discharge_disposition ?? 'recovered');
            $patient = $d->patient;
            $dob = $patient?->date_of_birth ? Carbon::parse($patient->date_of_birth) : null;
            $age = $dob ? $dob->age : ($patient?->age_years ?? 25);

            if (str_contains($disposition, 'death') || str_contains($disposition, 'deceased') || str_contains($disposition, 'died')) {
                if ($age < 5) {
                    $deathsUnder5++;
                } else {
                    $deathsGeneral++;
                }
            } elseif (str_contains($disposition, 'abscond')) {
                $absconded++;
            } elseif (str_contains($disposition, 'transfer')) {
                $transferredOut++;
            } else {
                $dischargedAlive++;
            }
        }

        $totalBeds = Bed::where('tenant_id', $tenantId)->count() ?: 1;
        $periodDays = max(1, $startDate->diffInDays($endDate));
        $availableBedDays = $totalBeds * $periodDays;
        
        $occupancyRate = round(($totalDaysStay / max(1, $availableBedDays)) * 100, 1);
        $averageLengthOfStay = $totalDischarges > 0 ? round($totalDaysStay / $totalDischarges, 1) : 0;

        return [
            'book' => 'MTUHA Book 2 - Inpatient & Mortality Registry',
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_admissions' => $totalAdmissions,
                'total_discharges' => $totalDischarges,
                'discharged_alive' => $dischargedAlive,
                'transferred_out' => $transferredOut,
                'absconded' => $absconded,
                'total_deaths' => $deathsUnder5 + $deathsGeneral,
                'deaths_under_five' => $deathsUnder5,
                'deaths_five_and_above' => $deathsGeneral,
                'total_bed_days' => $totalDaysStay,
                'available_bed_days' => $availableBedDays,
                'bed_occupancy_rate_pct' => min(100, $occupancyRate),
                'average_length_of_stay_days' => $averageLengthOfStay,
            ],
        ];
    }

    /**
     * Generate MTUHA Book 5 (Diagnostic & Logistics / Lab & Pharmacy Monthly Surveillance)
     */
    public function generateBook5LabPharmacyReport(string $tenantId, ?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $labItems = LabOrderItem::with(['labOrder.patient', 'labTest'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalLabTests = $labItems->count();
        $malariaMrdtDone = 0;
        $malariaMrdtPositive = 0;
        $bloodGroupingDone = 0;
        $urinalysisDone = 0;
        $fbpDone = 0;

        foreach ($labItems as $item) {
            $name = strtolower($item->labTest?->name ?? '');
            $res = strtolower(is_array($item->results) ? json_encode($item->results) : ($item->results ?? ''));

            if (str_contains($name, 'malaria') || str_contains($name, 'mrdt') || str_contains($name, 'bs')) {
                $malariaMrdtDone++;
                if (str_contains($res, 'pos') || str_contains($res, '+') || str_contains($res, 'reactive')) {
                    $malariaMrdtPositive++;
                }
            } elseif (str_contains($name, 'blood group') || str_contains($name, 'abo')) {
                $bloodGroupingDone++;
            } elseif (str_contains($name, 'urine') || str_contains($name, 'urinalysis')) {
                $urinalysisDone++;
            } elseif (str_contains($name, 'fbp') || str_contains($name, 'full blood') || str_contains($name, 'cbc')) {
                $fbpDone++;
            }
        }

        $stockBatches = InventoryBatch::with('medication')
            ->where('tenant_id', $tenantId)
            ->get();

        $totalStockItems = $stockBatches->count();
        $stockOutItems = $stockBatches->filter(fn ($s) => ($s->current_quantity ?? 0) <= 0)->count();
        $stockOutRate = $totalStockItems > 0 ? round(($stockOutItems / $totalStockItems) * 100, 1) : 0;

        return [
            'book' => 'MTUHA Book 5 - Laboratory & Essential Medicines Logistics',
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'laboratory' => [
                'total_investigations_performed' => $totalLabTests,
                'malaria_tests_performed' => $malariaMrdtDone,
                'malaria_positive_cases' => $malariaMrdtPositive,
                'malaria_positivity_rate_pct' => $malariaMrdtDone > 0 ? round(($malariaMrdtPositive / $malariaMrdtDone) * 100, 1) : 0,
                'full_blood_pictures_done' => $fbpDone,
                'urinalysis_tests_done' => $urinalysisDone,
                'blood_grouping_done' => $bloodGroupingDone,
            ],
            'pharmacy_tracer_medicines' => [
                'total_managed_items' => $totalStockItems,
                'stockout_items_count' => $stockOutItems,
                'stockout_rate_pct' => $stockOutRate,
                'availability_rate_pct' => round(100 - $stockOutRate, 1),
            ],
        ];
    }

    /**
     * Transform data to standard DHIS2 DataValueSet payload for automated MoH electronic sync
     */
    public function formatForDhis2(array $book1, array $book2, array $book5, string $orgUnitCode, string $periodMonth): array
    {
        // Period format: YYYYMM (e.g. 202608)
        $dataValues = [
            // OPD Elements
            ['dataElement' => 'MoH_OPD_TOTAL', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book1['summary']['total_opd_attendances']],
            ['dataElement' => 'MoH_OPD_UNDER5', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book1['summary']['total_under_five']],
            ['dataElement' => 'MoH_OPD_OVER5', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book1['summary']['total_over_five']],
            // IPD Elements
            ['dataElement' => 'MoH_IPD_ADMISSIONS', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book2['summary']['total_admissions']],
            ['dataElement' => 'MoH_IPD_DISCHARGES', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book2['summary']['total_discharges']],
            ['dataElement' => 'MoH_IPD_DEATHS_U5', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book2['summary']['deaths_under_five']],
            ['dataElement' => 'MoH_IPD_DEATHS_TOTAL', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book2['summary']['total_deaths']],
            ['dataElement' => 'MoH_IPD_BOR', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book2['summary']['bed_occupancy_rate_pct']],
            ['dataElement' => 'MoH_IPD_ALOS', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book2['summary']['average_length_of_stay_days']],
            // Lab & Malaria
            ['dataElement' => 'MoH_LAB_MALARIA_TESTED', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book5['laboratory']['malaria_tests_performed']],
            ['dataElement' => 'MoH_LAB_MALARIA_POSITIVE', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book5['laboratory']['malaria_positive_cases']],
            // Pharmacy
            ['dataElement' => 'MoH_PHARM_STOCKOUT_RATE', 'period' => $periodMonth, 'orgUnit' => $orgUnitCode, 'value' => $book5['pharmacy_tracer_medicines']['stockout_rate_pct']],
        ];

        return [
            'dataSet' => 'MoH_TZ_MONTHLY_SUMMARY_V3',
            'completeDate' => Carbon::now()->toDateString(),
            'period' => $periodMonth,
            'orgUnit' => $orgUnitCode,
            'dataValues' => $dataValues,
        ];
    }
}
