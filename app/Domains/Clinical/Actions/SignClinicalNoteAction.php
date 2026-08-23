<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Clinical\Exceptions\ClinicalImmutabilityException;
use App\Domains\Clinical\Models\ClinicalNote;

class SignClinicalNoteAction
{
    public function execute(ClinicalNote $note): ClinicalNote
    {
        if ($note->is_signed) {
            throw ClinicalImmutabilityException::signedNoteCannotBeEdited($note->id);
        }

        $note->update([
            'is_signed' => true,
            'signed_at' => now(),
        ]);

        return $note;
    }
}
