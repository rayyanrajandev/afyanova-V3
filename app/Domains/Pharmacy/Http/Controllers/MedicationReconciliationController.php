<?php

namespace App\Domains\Pharmacy\Http\Controllers;

use App\Domains\Patient\Models\Patient;
use App\Domains\Pharmacy\Actions\ReconcileMedicationsAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class MedicationReconciliationController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Patient $patient, ReconcileMedicationsAction $action)
    {
        $this->authorize('reconcileMedications', $patient);

        $validated = $request->validate([
            'stage' => 'required|string|in:Admission,Transfer,Discharge',
            'facility_id' => 'nullable|uuid',
            'encounter_id' => 'nullable|uuid',
            'admission_id' => 'nullable|uuid',
            'medications' => 'required|array|min:1',
            'medications.*.medication_name' => 'required|string|max:255',
            'medications.*.dosage' => 'nullable|string|max:100',
            'medications.*.frequency' => 'nullable|string|max:100',
            'medications.*.route' => 'nullable|string|max:50',
            'medications.*.action_taken' => 'required|string|in:Continue,Discontinue,Substitute,ModifyDose,Hold',
            'medications.*.clinical_rationale' => 'nullable|string',
            'medications.*.substitute_medication_name' => 'nullable|string|max:255',
            'medications.*.new_dosage_instructions' => 'nullable|string|max:255',
        ]);

        try {
            $action->execute(
                $patient,
                $validated['stage'],
                $validated['medications'],
                $validated['facility_id'] ?? null,
                $validated['encounter_id'] ?? null,
                $validated['admission_id'] ?? null,
            );

            return back()->with('success', 'Medication reconciliation recorded.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reconciliation' => $e->getMessage()]);
        }
    }
}
