<?php

namespace App\Domains\Inventory\Http\Controllers;

use App\Domains\Inventory\Actions\RecordDdaAdministrationAction;
use App\Domains\Inventory\Models\InventoryLocation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Dangerous Drugs Act (Tanzania Pharmacy Act 2011) narcotic register entry.
 * Split out of InventoryWorkspaceController (see
 * InventoryRequisitionController's docblock for why).
 */
class InventoryDdaController extends Controller
{
    use AuthorizesRequests;

    public function storeDdaLog(Request $request, RecordDdaAdministrationAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'facility_id' => 'required|string',
            'item_id' => 'required|string',
            'batch_id' => 'required|string',
            'encounter_id' => 'nullable|string',
            'patient_id' => 'nullable|string',
            'prescriber_id' => 'nullable|string',
            'witness_user_id' => 'nullable|string',
            'dose_administered' => 'required|numeric|min:0.01',
            'dose_wasted_discarded' => 'nullable|numeric|min:0',
            'indication' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $this->authorize('recordDda', [InventoryLocation::class, $validated['facility_id']]);

        $action->execute(
            $validated['facility_id'],
            $validated['item_id'],
            $validated['batch_id'],
            (float) $validated['dose_administered'],
            (float) ($validated['dose_wasted_discarded'] ?? 0),
            $validated['encounter_id'] ?? null,
            $validated['patient_id'] ?? null,
            $validated['prescriber_id'] ?? null,
            auth()->id(),
            $validated['witness_user_id'] ?? null,
            $validated['indication'],
            $validated['notes'] ?? null
        );

        return back()->with('success', 'DDA Controlled Substance administration logged successfully.');
    }
}
