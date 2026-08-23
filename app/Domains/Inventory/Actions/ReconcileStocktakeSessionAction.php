<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Billing\Exceptions\LedgerImbalanceException;
use App\Domains\Billing\Models\LedgerAccount;
use App\Domains\Billing\Models\LedgerTransaction;
use App\Domains\Inventory\Models\InventoryStockBalance;
use App\Domains\Inventory\Models\StocktakeItem;
use App\Domains\Inventory\Models\StocktakeSession;
use App\Domains\Pharmacy\Models\InventoryBatch;
use App\Domains\Pharmacy\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ReconcileStocktakeSessionAction
{
    /**
     * Reconciles a physical stocktaking session.
     * Posts ADJUSTMENT_POS or ADJUSTMENT_NEG movements to balance physical counts with the ledger,
     * and writes a balanced General Ledger transaction for financial shrinkage/overage.
     */
    public function execute(
        string $sessionId,
        array $counts,
        ?string $userId = null,
        ?string $notes = null
    ): StocktakeSession {
        return DB::transaction(function () use ($sessionId, $counts, $userId, $notes) {
            $session = StocktakeSession::with('location')->findOrFail($sessionId);
            $locationId = $session->location_id;
            $facilityId = $session->facility_id;
            $tenantId = $session->tenant_id;
            $userId = $userId ?? auth()->id();

            $totalNetVarianceValue = 0.0;

            foreach ($counts as $countData) {
                $medicationId = $countData['medication_id'];
                $batchId = $countData['batch_id'] ?? null;
                $physicalQty = (int) $countData['physical_counted_quantity'];
                $reason = $countData['variance_reason'] ?? 'Physical Stocktake Count';

                // Look up expected system balance
                $balance = InventoryStockBalance::where('location_id', $locationId)
                    ->where('medication_id', $medicationId)
                    ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
                    ->first();

                $expectedQty = $balance ? $balance->quantity_on_hand : 0;
                $variance = $physicalQty - $expectedQty;

                $batch = $batchId ? InventoryBatch::find($batchId) : null;
                $unitCost = $batch ? (float) $batch->unit_cost : 0.0;
                $varianceValue = $variance * $unitCost;
                $totalNetVarianceValue += $varianceValue;

                // Create Stocktake Item
                StocktakeItem::create([
                    'tenant_id' => $tenantId,
                    'stocktake_session_id' => $session->id,
                    'medication_id' => $medicationId,
                    'batch_id' => $batchId,
                    'system_expected_quantity' => $expectedQty,
                    'physical_counted_quantity' => $physicalQty,
                    'variance_quantity' => $variance,
                    'variance_value_tzs' => $varianceValue,
                    'variance_reason' => $reason,
                ]);

                if ($variance !== 0) {
                    // Update location balance
                    if (! $balance && $batchId) {
                        $balance = InventoryStockBalance::create([
                            'tenant_id' => $tenantId,
                            'facility_id' => $facilityId,
                            'location_id' => $locationId,
                            'medication_id' => $medicationId,
                            'batch_id' => $batchId,
                            'quantity_on_hand' => $physicalQty,
                        ]);
                    } elseif ($balance) {
                        $balance->update([
                            'quantity_on_hand' => $physicalQty,
                            'last_counted_at' => now(),
                        ]);
                    }

                    // Update batch current quantity if batch exists
                    if ($batch) {
                        $batch->update(['current_quantity' => max(0, $batch->current_quantity + $variance)]);
                    }

                    // Post balancing stock movement
                    StockMovement::create([
                        'tenant_id' => $tenantId,
                        'facility_id' => $facilityId,
                        'medication_id' => $medicationId,
                        'batch_id' => $batchId ?? ($batch ? $batch->id : '00000000000000000000000000'),
                        'movement_type' => $variance > 0 ? 'Adjustment_Pos' : 'Adjustment_Neg',
                        'quantity_change' => $variance,
                        'quantity_before' => $expectedQty,
                        'quantity_after' => $physicalQty,
                        'reference_type' => 'StocktakeSession',
                        'reference_id' => $session->id,
                        'performed_by' => $userId,
                        'notes' => "Stocktake Reconciliation ({$session->session_number}): {$reason}",
                    ]);
                }
            }

            // Financial Double-Entry Journal for Net Inventory Shrinkage / Overage
            if (abs($totalNetVarianceValue) > 0.001) {
                $inventoryAssetAccount = LedgerAccount::firstOrCreate(
                    ['code' => '1300', 'tenant_id' => $tenantId],
                    ['name' => 'Medical & Pharmaceutical Inventory (Asset)', 'type' => 'Asset']
                );

                $shrinkageExpenseAccount = LedgerAccount::firstOrCreate(
                    ['code' => '5200', 'tenant_id' => $tenantId],
                    ['name' => 'Inventory Shrinkage, Spoilage & Loss (Expense)', 'type' => 'Expense']
                );

                $transaction = LedgerTransaction::create([
                    'facility_id' => $facilityId,
                    'user_id' => $userId,
                    'reference_type' => 'StocktakeSession',
                    'reference_id' => $session->id,
                    'description' => "Stocktake Variance Adjustment ({$session->session_number})",
                ]);

                if ($totalNetVarianceValue < 0) {
                    // Shrinkage Loss: Debit Shrinkage Expense, Credit Inventory Asset
                    $lossAmount = abs($totalNetVarianceValue);
                    $transaction->entries()->create([
                        'account_id' => $shrinkageExpenseAccount->id,
                        'debit' => $lossAmount,
                        'credit' => 0.00,
                    ]);
                    $transaction->entries()->create([
                        'account_id' => $inventoryAssetAccount->id,
                        'debit' => 0.00,
                        'credit' => $lossAmount,
                    ]);
                } else {
                    // Overage Gain: Debit Inventory Asset, Credit Gain Revenue
                    $gainRevenueAccount = LedgerAccount::firstOrCreate(
                        ['code' => '4200', 'tenant_id' => $tenantId],
                        ['name' => 'Inventory Overage / Found Stock (Revenue)', 'type' => 'Revenue']
                    );
                    $gainAmount = $totalNetVarianceValue;
                    $transaction->entries()->create([
                        'account_id' => $inventoryAssetAccount->id,
                        'debit' => $gainAmount,
                        'credit' => 0.00,
                    ]);
                    $transaction->entries()->create([
                        'account_id' => $gainRevenueAccount->id,
                        'debit' => 0.00,
                        'credit' => $gainAmount,
                    ]);
                }

                // Invariant Check
                $totalDebits = $transaction->entries()->sum('debit');
                $totalCredits = $transaction->entries()->sum('credit');
                if (abs($totalDebits - $totalCredits) > 0.001) {
                    throw LedgerImbalanceException::unbalanced($totalDebits, $totalCredits);
                }
            }

            $session->update([
                'status' => 'Approved_Reconciled',
                'approved_by' => $userId,
                'reconciled_at' => now(),
                'notes' => $notes,
            ]);

            return $session->fresh(['facility', 'location', 'items.medication', 'items.batch']);
        });
    }
}
