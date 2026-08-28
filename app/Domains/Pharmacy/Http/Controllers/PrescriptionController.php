<?php

namespace App\Domains\Pharmacy\Http\Controllers;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Pharmacy\Actions\PrescribeMedicationAction;
use App\Domains\Pharmacy\Exceptions\PharmacyException;
use App\Domains\Pharmacy\Models\Prescription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PrescriptionController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Encounter $encounter, PrescribeMedicationAction $action)
    {
        $this->authorize('prescribe', [Prescription::class, $encounter->facility_id]);

        $validated = $request->validate([
            'medication_id' => 'required|string',
            'dosage' => 'required|string',
            'frequency' => 'required|string',
            'duration_days' => 'required|integer|min:1',
            'route' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'instructions' => 'nullable|string',
            'allergy_override_reason' => 'nullable|string',
            'override_reason' => 'nullable|string',
        ]);

        $validated['encounter_id'] = $encounter->id;
        $validated['patient_id'] = $encounter->patient_id;

        try {
            $action->execute($validated);

            return back()->with('success', 'Prescription created successfully.');
        } catch (PharmacyException $e) {
            return back()->withErrors(['prescription' => $e->getMessage()]);
        }
    }
}
