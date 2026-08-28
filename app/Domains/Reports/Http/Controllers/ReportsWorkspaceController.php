<?php

namespace App\Domains\Reports\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Reports\Actions\GenerateFinancialAnalyticsAction;
use App\Domains\Reports\Actions\GenerateMorbidityAnalyticsAction;
use App\Domains\Reports\Actions\GenerateOperationalEfficiencyAction;
use App\Domains\Reports\Actions\GeneratePharmacoeconomicAnalyticsAction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportsWorkspaceController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function index(
        Request $request,
        GenerateMorbidityAnalyticsAction $morbidityAction,
        GenerateFinancialAnalyticsAction $financialAction,
        GeneratePharmacoeconomicAnalyticsAction $pharmacoAction,
        GenerateOperationalEfficiencyAction $operationalAction,
        AuthorizationService $authService
    ): Response {
        $this->authorizeAnyWorkspacePermission($request->user(), $authService, [
            'reports.analytics.view', 'reports.clinical.view', 'reports.financial.view', 'reports.pharmacoeconomic.view',
        ]);

        $can = $this->buildSectionCanMap($request->user(), $authService, [
            'morbidity' => 'reports.clinical.view',
            'operational' => 'reports.clinical.view',
            'financial' => 'reports.financial.view',
            'pharmaco' => 'reports.pharmacoeconomic.view',
        ]);
        // reports.analytics.view (the historical blanket slug) still unlocks everything, for tenant-admin/auditor parity with the old behavior.
        if ($authService->hasPermission($request->user(), 'reports.analytics.view')) {
            $can = array_fill_keys(array_keys($can), true);
        }

        $preset = $request->get('preset', 'all');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (! $startDate && ! $endDate && $preset !== 'all') {
            if ($preset === 'today') {
                $startDate = Carbon::today()->toDateString();
                $endDate = Carbon::today()->toDateString();
            } elseif ($preset === 'week') {
                $startDate = Carbon::now()->startOfWeek()->toDateString();
                $endDate = Carbon::now()->endOfWeek()->toDateString();
            } elseif ($preset === 'month') {
                $startDate = Carbon::now()->startOfMonth()->toDateString();
                $endDate = Carbon::now()->endOfMonth()->toDateString();
            } elseif ($preset === 'year') {
                $startDate = Carbon::now()->startOfYear()->toDateString();
                $endDate = Carbon::now()->endOfYear()->toDateString();
            }
        }

        $morbidity = $can['morbidity'] ? $morbidityAction->execute(null, $startDate, $endDate) : [];
        $financial = $can['financial'] ? $financialAction->execute(null, $startDate, $endDate) : [];
        $pharmaco = $can['pharmaco'] ? $pharmacoAction->execute(null, $startDate, $endDate) : [];
        $operational = $can['operational'] ? $operationalAction->execute(null, $startDate, $endDate) : [];

        $topDiagnosis = ($can['morbidity'] && count($morbidity['top_10_morbidity']) > 0)
            ? $morbidity['top_10_morbidity'][0]['description']
            : 'None Recorded';

        $metrics = [
            'total_revenue_tzs' => $can['financial'] ? $financial['summary']['total_collected_tzs'] : null,
            'total_billed_tzs' => $can['financial'] ? $financial['summary']['total_billed_tzs'] : null,
            'total_diagnoses' => $can['morbidity'] ? $morbidity['total_diagnoses'] : null,
            'top_diagnosis' => $can['morbidity'] ? $topDiagnosis : null,
            'bed_occupancy_rate' => $can['operational'] ? $operational['bed_occupancy']['bor_percent'] : null,
            'active_inpatients' => $can['operational'] ? $operational['inpatient_throughput']['active_inpatients'] : null,
            'notifiable_alerts_count' => $can['morbidity'] ? $morbidity['notifiable_alert_count'] : null,
            'total_stock_value_tzs' => $can['pharmaco'] ? $pharmaco['valuation']['total_cost_value_tzs'] : null,
        ];

        $can['mtuha'] = $can['morbidity'] || $authService->hasPermission($request->user(), 'reports.clinical.view');

        $tenantId = $request->user()->tenant_id;
        $mtuhaService = app(\App\Domains\Reports\Services\MtuhaReportingService::class);
        $mtuhaBook1 = $can['mtuha'] ? $mtuhaService->generateBook1OpdReport($tenantId, $startDate, $endDate) : null;
        $mtuhaBook2 = $can['mtuha'] ? $mtuhaService->generateBook2IpdReport($tenantId, $startDate, $endDate) : null;
        $mtuhaBook5 = $can['mtuha'] ? $mtuhaService->generateBook5LabPharmacyReport($tenantId, $startDate, $endDate) : null;

        return Inertia::render('Workspace/ReportsWorkspace', [
            'can' => $can,
            'morbidity' => $morbidity,
            'financial' => $financial,
            'pharmaco' => $pharmaco,
            'operational' => $operational,
            'mtuha' => [
                'book1' => $mtuhaBook1,
                'book2' => $mtuhaBook2,
                'book5' => $mtuhaBook5,
            ],
            'metrics' => $metrics,
            'filters' => [
                'preset' => $preset,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function exportMtuha(Request $request, \App\Domains\Reports\Services\MtuhaReportingService $mtuhaService)
    {
        $tenantId = $request->user()->tenant_id;
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $format = $request->query('format', 'dhis2'); // 'dhis2', 'json', or 'csv'

        $book1 = $mtuhaService->generateBook1OpdReport($tenantId, $startDate, $endDate);
        $book2 = $mtuhaService->generateBook2IpdReport($tenantId, $startDate, $endDate);
        $book5 = $mtuhaService->generateBook5LabPharmacyReport($tenantId, $startDate, $endDate);

        $periodMonth = Carbon::parse($startDate ?: now())->format('Ym');
        $orgUnit = $request->user()->facility_id ?? 'TZ_MOH_FACILITY_001';

        if ($format === 'dhis2') {
            $dhis2Payload = $mtuhaService->formatForDhis2($book1, $book2, $book5, $orgUnit, $periodMonth);
            return response()->json($dhis2Payload)
                ->header('Content-Disposition', "attachment; filename=\"mtuha_dhis2_payload_{$periodMonth}.json\"");
        }

        if ($format === 'json') {
            return response()->json([
                'facility' => $orgUnit,
                'period' => $periodMonth,
                'book1_opd' => $book1,
                'book2_ipd' => $book2,
                'book5_lab_pharm' => $book5,
            ]);
        }

        // CSV Tabular format
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"mtuha_summary_report_{$periodMonth}.csv\"",
        ];

        $callback = function () use ($book1, $book2, $book5) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['MoH Tanzania - MTUHA National Health Statistics Summary Export']);
            fputcsv($file, ['Indicator', 'Sub-Indicator', 'Value']);
            
            // OPD
            fputcsv($file, ['Book 1 OPD', 'Total Outpatient Attendances', $book1['summary']['total_opd_attendances']]);
            fputcsv($file, ['Book 1 OPD', 'Under 5 Male', $book1['summary']['under_five_male']]);
            fputcsv($file, ['Book 1 OPD', 'Under 5 Female', $book1['summary']['under_five_female']]);
            fputcsv($file, ['Book 1 OPD', 'Over 5 Male', $book1['summary']['over_five_male']]);
            fputcsv($file, ['Book 1 OPD', 'Over 5 Female', $book1['summary']['over_five_female']]);
            
            // IPD
            fputcsv($file, ['Book 2 IPD', 'Total Inpatient Admissions', $book2['summary']['total_admissions']]);
            fputcsv($file, ['Book 2 IPD', 'Total Discharges', $book2['summary']['total_discharges']]);
            fputcsv($file, ['Book 2 IPD', 'Total Deaths', $book2['summary']['total_deaths']]);
            fputcsv($file, ['Book 2 IPD', 'Bed Occupancy Rate (%)', $book2['summary']['bed_occupancy_rate_pct']]);
            fputcsv($file, ['Book 2 IPD', 'Average Length of Stay (Days)', $book2['summary']['average_length_of_stay_days']]);

            // Lab / Pharmacy
            fputcsv($file, ['Book 5 Lab/Logistics', 'Malaria Tests Done', $book5['laboratory']['malaria_tests_performed']]);
            fputcsv($file, ['Book 5 Lab/Logistics', 'Malaria Positive Cases', $book5['laboratory']['malaria_positive_cases']]);
            fputcsv($file, ['Book 5 Lab/Logistics', 'Stockout Rate (%)', $book5['pharmacy_tracer_medicines']['stockout_rate_pct']]);
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
