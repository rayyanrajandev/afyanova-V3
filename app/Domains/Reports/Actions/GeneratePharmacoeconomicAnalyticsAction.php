<?php

namespace App\Domains\Reports\Actions;

use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Carbon;

class GeneratePharmacoeconomicAnalyticsAction
{
    public function execute(?string $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        // No Tenant::first() fallback: silently generating this report
        // against an arbitrary other tenant when neither the caller nor
        // the acting user supplies one is a landmine, not a safety net.
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        $batches = InventoryBatch::with('medication')
            ->where('tenant_id', $tenantId)
            ->get();

        $movementQuery = StockMovement::with(['medication', 'batch'])
            ->where('tenant_id', $tenantId)
            ->where('movement_type', 'Dispensed');

        if ($startDate) {
            $movementQuery->whereDate('created_at', '>=', Carbon::parse($startDate));
        }
        if ($endDate) {
            $movementQuery->whereDate('created_at', '<=', Carbon::parse($endDate));
        }

        $dispensedMovements = $movementQuery->get();

        // 1. Total Inventory Valuation
        $totalUnitsOnHand = 0;
        $totalCostValue = 0.0;
        $totalRetailValue = 0.0;
        $activeBatchCount = 0;

        // 2. Expiry Risk Exposure
        $now = Carbon::now();
        $expiredBatches = [];
        $criticalExpiryBatches = []; // < 30 days
        $warningExpiryBatches = [];  // 31 - 90 days
        $totalExpiryRiskValue = 0.0;

        foreach ($batches as $batch) {
            $qty = $batch->current_quantity ?? 0;
            $unitCost = (float) ($batch->unit_cost ?? 0);
            $unitSelling = (float) ($batch->unit_selling_price ?? 0);

            if ($qty > 0) {
                $totalUnitsOnHand += $qty;
                $totalCostValue += ($qty * $unitCost);
                $totalRetailValue += ($qty * $unitSelling);
                $activeBatchCount++;

                if ($batch->expiry_date) {
                    $daysRemaining = $now->diffInDays(Carbon::parse($batch->expiry_date), false);
                    $riskValue = $qty * $unitCost;

                    if ($daysRemaining < 0) {
                        $expiredBatches[] = [
                            'medication_name' => $batch->medication?->generic_name ?: 'Medication',
                            'batch_number' => $batch->batch_number,
                            'quantity' => $qty,
                            'loss_value_tzs' => $riskValue,
                            'expiry_date' => $batch->expiry_date->format('Y-m-d'),
                        ];
                        $totalExpiryRiskValue += $riskValue;
                    } elseif ($daysRemaining <= 30) {
                        $criticalExpiryBatches[] = [
                            'medication_name' => $batch->medication?->generic_name ?: 'Medication',
                            'batch_number' => $batch->batch_number,
                            'quantity' => $qty,
                            'days_left' => $daysRemaining,
                            'risk_value_tzs' => $riskValue,
                            'expiry_date' => $batch->expiry_date->format('Y-m-d'),
                        ];
                        $totalExpiryRiskValue += $riskValue;
                    } elseif ($daysRemaining <= 90) {
                        $warningExpiryBatches[] = [
                            'medication_name' => $batch->medication?->generic_name ?: 'Medication',
                            'batch_number' => $batch->batch_number,
                            'quantity' => $qty,
                            'days_left' => $daysRemaining,
                            'risk_value_tzs' => $riskValue,
                            'expiry_date' => $batch->expiry_date->format('Y-m-d'),
                        ];
                    }
                }
            }
        }

        $grossMargin = $totalCostValue > 0 ? round((($totalRetailValue - $totalCostValue) / $totalRetailValue) * 100, 1) : 0;

        // 3. Fast-Moving Medications by Dispensing Velocity
        $groupedMovements = $dispensedMovements->groupBy('medication_id');
        $fastMoving = [];
        $rank = 1;

        foreach ($groupedMovements as $medId => $mvGroup) {
            $firstMed = $mvGroup->first()->medication;
            $unitsDispensed = abs($mvGroup->sum('quantity_change'));
            $avgSellingPrice = (float) ($mvGroup->first()->batch?->unit_selling_price ?? 0);
            $totalDispensedVal = $unitsDispensed * $avgSellingPrice;

            $fastMoving[] = [
                'rank' => $rank++,
                'medication_id' => $medId,
                'generic_name' => $firstMed?->generic_name ?: 'Formulary Item',
                'brand_name' => $firstMed?->brand_name,
                'units_dispensed' => $unitsDispensed,
                'total_revenue_tzs' => $totalDispensedVal,
                'dispense_events_count' => $mvGroup->count(),
            ];
        }

        usort($fastMoving, fn ($a, $b) => $b['units_dispensed'] <=> $a['units_dispensed']);
        // Re-assign ranks after sorting
        foreach ($fastMoving as $i => &$item) {
            $item['rank'] = $i + 1;
        }
        $topFastMoving = array_slice($fastMoving, 0, 10);

        // 4. Stockout Risk Watch (< 10 units)
        $allFormularies = MedicationFormulary::where('tenant_id', $tenantId)->get();
        $stockoutWatch = [];

        foreach ($allFormularies as $form) {
            $totalStock = $batches->where('medication_id', $form->id)->sum('current_quantity');
            if ($totalStock < 10) {
                $stockoutWatch[] = [
                    'generic_name' => $form->generic_name,
                    'brand_name' => $form->brand_name,
                    'current_stock' => $totalStock,
                    'status' => $totalStock === 0 ? 'Out of Stock' : 'Critical Low',
                ];
            }
        }

        return [
            'valuation' => [
                'total_cost_value_tzs' => $totalCostValue,
                'total_retail_value_tzs' => $totalRetailValue,
                'gross_margin_percent' => $grossMargin,
                'total_units_on_hand' => $totalUnitsOnHand,
                'active_batches_count' => $activeBatchCount,
            ],
            'fast_moving_medications' => $topFastMoving,
            'expiry_risk' => [
                'total_at_risk_value_tzs' => $totalExpiryRiskValue,
                'expired_batches' => $expiredBatches,
                'critical_30_days' => $criticalExpiryBatches,
                'warning_90_days' => $warningExpiryBatches,
            ],
            'stockout_risks' => $stockoutWatch,
        ];
    }
}
