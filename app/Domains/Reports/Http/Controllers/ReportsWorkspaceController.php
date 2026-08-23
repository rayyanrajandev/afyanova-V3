<?php

namespace App\Domains\Reports\Http\Controllers;

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
    public function index(
        Request $request,
        GenerateMorbidityAnalyticsAction $morbidityAction,
        GenerateFinancialAnalyticsAction $financialAction,
        GeneratePharmacoeconomicAnalyticsAction $pharmacoAction,
        GenerateOperationalEfficiencyAction $operationalAction,
        AuthorizationService $authService
    ): Response {
        abort_unless($authService->hasPermission($request->user(), 'reports.analytics.view'), 403);

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

        $morbidity = $morbidityAction->execute(null, $startDate, $endDate);
        $financial = $financialAction->execute(null, $startDate, $endDate);
        $pharmaco = $pharmacoAction->execute(null, $startDate, $endDate);
        $operational = $operationalAction->execute(null, $startDate, $endDate);

        $topDiagnosis = count($morbidity['top_10_morbidity']) > 0
            ? $morbidity['top_10_morbidity'][0]['description']
            : 'None Recorded';

        $metrics = [
            'total_revenue_tzs' => $financial['summary']['total_collected_tzs'],
            'total_billed_tzs' => $financial['summary']['total_billed_tzs'],
            'total_diagnoses' => $morbidity['total_diagnoses'],
            'top_diagnosis' => $topDiagnosis,
            'bed_occupancy_rate' => $operational['bed_occupancy']['bor_percent'],
            'active_inpatients' => $operational['inpatient_throughput']['active_inpatients'],
            'notifiable_alerts_count' => $morbidity['notifiable_alert_count'],
            'total_stock_value_tzs' => $pharmaco['valuation']['total_cost_value_tzs'],
        ];

        return Inertia::render('Workspace/ReportsWorkspace', [
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
