<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Clinical\Models\ClinicalNote;
use Illuminate\Support\Facades\DB;

class AmendClinicalNoteAction
{
    public function execute(ClinicalNote $originalNote, array $newContent, string $reason): ClinicalNote
    {
        return DB::transaction(function () use ($originalNote, $newContent, $reason) {

            // Mark the original note as deprecated. This is the one
            // sanctioned mutation of an already-signed note, so it must go
            // through the immutability bypass rather than a plain update().
            ClinicalNote::withFinalizedMutation(function () use ($originalNote) {
                $originalNote->update(['is_deprecated' => true]);
            });

            // Create the new amended note
            $amendment = ClinicalNote::create([
                'encounter_id' => $originalNote->encounter_id,
                'patient_id' => $originalNote->patient_id,
                'author_id' => auth()->id(),
                'note_type' => $originalNote->note_type,
                'content' => $newContent,

                'is_amendment' => true,
                'amended_note_id' => $originalNote->id,
                'amendment_reason' => $reason,

                // Immediately sign the amendment if the original was signed, or let them sign later?
                // Standard practice: Addendums start unsigned so the author can draft them.
                'is_signed' => false,
            ]);

            return $amendment;
        });
    }
}
