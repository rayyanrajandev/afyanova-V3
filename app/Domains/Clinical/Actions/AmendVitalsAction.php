<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Clinical\Models\ClinicalVital;
use Illuminate\Support\Facades\DB;

class AmendVitalsAction
{
    public function execute(ClinicalVital $originalVital, array $newValues, string $reason): ClinicalVital
    {
        return DB::transaction(function () use ($originalVital, $newValues, $reason) {
            ClinicalVital::withFinalizedMutation(function () use ($originalVital) {
                $originalVital->update(['is_deprecated' => true]);
            });

            return ClinicalVital::create(array_merge($newValues, [
                'encounter_id' => $originalVital->encounter_id,
                'patient_id' => $originalVital->patient_id,
                'recorded_by' => auth()->id(),

                'is_amendment' => true,
                'amended_vital_id' => $originalVital->id,
                'amendment_reason' => $reason,
            ]));
        });
    }
}
