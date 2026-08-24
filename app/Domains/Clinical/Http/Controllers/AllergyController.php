<?php

namespace App\Domains\Clinical\Http\Controllers;

use App\Domains\Clinical\Actions\AmendAllergyAction;
use App\Domains\Clinical\Models\Allergy;
use App\Domains\Patient\Models\Patient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AllergyController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Patient $patient)
    {
        $this->authorize('recordAllergy', $patient);

        $validated = $request->validate([
            'allergen_type' => 'required|string|in:Drug,Food,Environmental,Other',
            'allergen' => 'required|string|max:255',
            'reaction' => 'nullable|string|max:255',
            'severity' => 'required|string|in:Mild,Moderate,Severe',
        ]);

        Allergy::create([
            ...$validated,
            'patient_id' => $patient->id,
            'recorded_by' => $request->user()->id,
            'status' => 'Active',
        ]);

        return back()->with('success', 'Allergy recorded successfully.');
    }

    public function amend(Request $request, Allergy $allergy, AmendAllergyAction $action)
    {
        $this->authorize('amendAllergy', $allergy->patient);

        $validated = $request->validate([
            'allergen_type' => 'required|string|in:Drug,Food,Environmental,Other',
            'allergen' => 'required|string|max:255',
            'reaction' => 'nullable|string|max:255',
            'severity' => 'required|string|in:Mild,Moderate,Severe',
            'status' => 'required|string|in:Active,Inactive',
            'reason' => 'required|string|max:500',
        ]);

        $reason = $validated['reason'];
        unset($validated['reason']);

        $action->execute($allergy, $validated, $reason);

        return back()->with('success', 'Allergy record amended.');
    }
}
