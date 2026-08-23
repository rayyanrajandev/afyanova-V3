<?php

namespace App\Domains\Clinical\Http\Controllers;

use App\Domains\Clinical\Actions\AmendClinicalNoteAction;
use App\Domains\Clinical\Actions\RecordVitalsAction;
use App\Domains\Clinical\Actions\SignClinicalNoteAction;
use App\Domains\Clinical\Exceptions\ClinicalImmutabilityException;
use App\Domains\Clinical\Models\ClinicalNote;
use App\Domains\Clinical\Models\Encounter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class ClinicalChartingController extends Controller
{
    use AuthorizesRequests;

    public function storeVitals(Request $request, Encounter $encounter, RecordVitalsAction $action)
    {
        $validated = $request->validate([
            'temperature_c' => 'nullable|numeric',
            'heart_rate' => 'nullable|integer',
            'systolic_bp' => 'nullable|integer',
            'diastolic_bp' => 'nullable|integer',
            'respiratory_rate' => 'nullable|integer',
            'oxygen_saturation' => 'nullable|numeric',
            'weight_kg' => 'nullable|numeric',
            'height_cm' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $validated['encounter_id'] = $encounter->id;
        $validated['patient_id'] = $encounter->patient_id;

        try {
            $action->execute($validated);

            return back()->with('success', 'Vitals recorded successfully.');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['vitals' => $e->getMessage()]);
        }
    }

    public function storeNote(Request $request, Encounter $encounter)
    {
        $validated = $request->validate([
            'note_type' => 'required|string',
            'content' => 'required|array',
        ]);

        ClinicalNote::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'author_id' => auth()->id(),
            'note_type' => $validated['note_type'],
            'content' => $validated['content'],
            'is_signed' => false,
        ]);

        return back()->with('success', 'Note drafted successfully.');
    }

    public function signNote(ClinicalNote $note, SignClinicalNoteAction $action)
    {
        $this->authorize('signNotes', $note->encounter);

        try {
            $action->execute($note);

            return back()->with('success', 'Note signed successfully. It is now legally locked.');
        } catch (ClinicalImmutabilityException $e) {
            return back()->withErrors(['note' => $e->getMessage()]);
        }
    }

    public function amendNote(Request $request, ClinicalNote $note, AmendClinicalNoteAction $action)
    {
        $this->authorize('signNotes', $note->encounter);

        $validated = $request->validate([
            'content' => 'required|array',
            'amendment_reason' => 'required|string',
        ]);

        $action->execute($note, $validated['content'], $validated['amendment_reason']);

        return back()->with('success', 'Addendum created successfully.');
    }
}
