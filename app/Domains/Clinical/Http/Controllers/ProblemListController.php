<?php

namespace App\Domains\Clinical\Http\Controllers;

use App\Domains\Clinical\Actions\ManageProblemListAction;
use App\Domains\Clinical\Models\PatientProblem;
use App\Domains\Patient\Models\Patient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class ProblemListController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Patient $patient, ManageProblemListAction $action)
    {
        $this->authorize('manageProblems', $patient);

        $validated = $request->validate([
            'icd10_code' => 'required|string|max:10',
            'problem_name' => 'required|string|max:255',
            'encounter_id' => 'nullable|uuid',
            'status' => 'nullable|string|in:Active,Resolved,Inactive',
            'clinical_status' => 'nullable|string|in:Confirmed,Provisional,Differential',
            'severity' => 'nullable|string|in:Mild,Moderate,Severe',
            'onset_date' => 'nullable|date',
            'resolved_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $validated['patient_id'] = $patient->id;

        try {
            $action->record($validated);

            return back()->with('success', 'Problem list updated successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['problem' => $e->getMessage()]);
        }
    }

    public function resolve(Request $request, PatientProblem $problem, ManageProblemListAction $action)
    {
        $this->authorize('manageProblems', $problem->patient);

        $validated = $request->validate([
            'resolved_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $action->resolve($problem, $validated['resolved_date'] ?? null, $validated['notes'] ?? null);

        return back()->with('success', 'Problem marked resolved.');
    }
}
