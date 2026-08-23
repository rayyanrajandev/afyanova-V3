<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Clinical\Models\Allergy;
use Illuminate\Support\Facades\DB;

class AmendAllergyAction
{
    public function execute(Allergy $originalAllergy, array $newValues, string $reason): Allergy
    {
        return DB::transaction(function () use ($originalAllergy, $newValues, $reason) {
            Allergy::withFinalizedMutation(function () use ($originalAllergy) {
                $originalAllergy->update(['is_deprecated' => true]);
            });

            return Allergy::create(array_merge($newValues, [
                'patient_id' => $originalAllergy->patient_id,
                'recorded_by' => auth()->id(),

                'is_amendment' => true,
                'amended_allergy_id' => $originalAllergy->id,
                'amendment_reason' => $reason,
            ]));
        });
    }
}
