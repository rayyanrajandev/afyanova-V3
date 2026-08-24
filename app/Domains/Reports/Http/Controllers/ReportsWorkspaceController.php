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

        return Inertia::render('Workspace/ReportsWorkspace', [
            'can' => $can,
            'morbidity' => $morbidity,
            'financial' => $financial,
            'pharmaco' => $pharmaco,
            'operational' => $operational,
            'metrics' => $metrics,
            'filters' => [
                'preset' => $preset,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }
}
