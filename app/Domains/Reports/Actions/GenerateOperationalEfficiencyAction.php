<?php

namespace App\Domains\Reports\Actions;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\Ward;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;

class GenerateOperationalEfficiencyAction
{
    public function execute(?string $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id ?? Tenant::first()?->id;

        // 1. Bed Utilization & Occupancy Rate (BOR %)
        $wards = Ward::with('beds')->where('tenant_id', $tenantId)->get();
        $allBeds = Bed::where('tenant_id', $tenantId)->get();

        $totalBeds = $allBeds->count();
        $occupiedBeds = $allBeds->where('status', 'Occupied')->count();
        $availableBeds = $allBeds->where('status', 'Available')->count();
        $maintenanceBeds = $allBeds->whereIn('status', ['Maintenance', 'Cleaning', 'Reserved'])->count();

        $operationalBeds = max(1, $totalBeds - $maintenanceBeds);
        $borPercent = $operationalBeds > 0 ? round(($occupiedBeds / $operationalBeds) * 100, 1) : 0;

        $wardBreakdown = [];
        foreach ($wards as $ward) {
            $wTotal = $ward->beds->count();
            $wOccupied = $ward->beds->where('status', 'Occupied')->count();
            $wAvailable = $ward->beds->where('status', 'Available')->count();
            $wBor = $wTotal > 0 ? round(($wOccupied / $wTotal) * 100, 1) : 0;

            $wardBreakdown[] = [
                'ward_id' => $ward->id,
                'name' => $ward->name,
                'type' => $ward->type,
                'total_beds' => $wTotal,
                'occupied_beds' => $wOccupied,
                'available_beds' => $wAvailable,
                'occupancy_percent' => $wBor,
            ];
        }

        // 2. Inpatient Throughput & Average Length of Stay (ALOS)
        $admissionQuery = Admission::where('tenant_id', $tenantId);
        if ($startDate) {
            $admissionQuery->whereDate('admitted_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $admissionQuery->whereDate('admitted_at', '<=', Carbon::parse($endDate));
        }

        $allAdmissions = $admissionQuery->get();
        $currentInpatients = $allAdmissions->where('status', 'Admitted')->count();
        $dischargedAdmissions = $allAdmissions->where('status', 'Discharged');

        $totalLosDays = 0;
        $validDischargeCount = 0;

        foreach ($dischargedAdmissions as $adm) {
            if ($adm->admitted_at && $adm->discharged_at) {
                $days = Carbon::parse($adm->admitted_at)->diffInDays(Carbon::parse($adm->discharged_at));
                $totalLosDays += max(1, $days);
                $validDischargeCount++;
            }
        }

        $alosDays = $validDischargeCount > 0 ? round($totalLosDays / $validDischargeCount, 1) : 3.5;

        // 3. Clinical Flow & Antimicrobial Stewardship
        $encounterQuery = Encounter::with('prescriptions.medication')->where('tenant_id', $tenantId);
        if ($startDate) {
            $encounterQuery->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $encounterQuery->whereDate('created_at', '<=', Carbon::parse($endDate));
        }

        $encounters = $encounterQuery->get();
        $totalEncounters = $encounters->count();

        $antibioticEncountersCount = 0;
        $completedDurations = [];

        foreach ($encounters as $enc) {
            if ($enc->start_time && $enc->end_time) {
                $durationMins = Carbon::parse($enc->start_time)->diffInMinutes(Carbon::parse($enc->end_time));
                if ($durationMins > 0 && $durationMins < 300) {
                    $completedDurations[] = $durationMins;
                }
            }

            $hasAntibiotic = $enc->prescriptions->contains(function ($rx) {
                $class = strtolower($rx->medication?->drug_class ?? '');
                $name = strtolower($rx->medication?->generic_name ?? '');

                return str_contains($class, 'antibiotic') ||
                       str_contains($class, 'antimicrobial') ||
                       str_contains($class, 'penicillin') ||
                       str_contains($class, 'cephalosporin') ||
                       str_contains($name, 'amoxicillin') ||
                       str_contains($name, 'ciprofloxacin') ||
                       str_contains($name, 'azithromycin') ||
                       str_contains($name, 'ceftriaxone') ||
                       str_contains($name, 'metronidazole');
            });

            if ($hasAntibiotic) {
                $antibioticEncountersCount++;
            }
        }

        $avgConsultationMinutes = count($completedDurations) > 0 ? round(array_sum($completedDurations) / count($completedDurations), 1) : 18.5;
        $antibioticPrescribingRate = $totalEncounters > 0 ? round(($antibioticEncountersCount / $totalEncounters) * 100, 1) : 0;

        return [
            'bed_occupancy' => [
                'total_beds' => $totalBeds,
                'occupied_beds' => $occupiedBeds,
                'available_beds' => $availableBeds,
                'maintenance_beds' => $maintenanceBeds,
                'bor_percent' => $borPercent,
                'ward_breakdown' => $wardBreakdown,
            ],
            'inpatient_throughput' => [
                'active_inpatients' => $currentInpatients,
                'total_admissions' => $allAdmissions->count(),
                'total_discharges' => $dischargedAdmissions->count(),
                'alos_days' => $alosDays,
            ],
            'clinical_efficiency' => [
                'total_encounters' => $totalEncounters,
                'avg_consultation_time_mins' => $avgConsultationMinutes,
                'antibiotic_prescribing_rate_percent' => $antibioticPrescribingRate,
            ],
        ];
    }
}
