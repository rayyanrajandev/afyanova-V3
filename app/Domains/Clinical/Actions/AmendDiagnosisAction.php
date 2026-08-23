<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Clinical\Models\Diagnosis;
use Illuminate\Support\Facades\DB;

class AmendDiagnosisAction
{
    public function execute(Diagnosis $originalDiagnosis, array $newValues, string $reason): Diagnosis
    {
        return DB::transaction(function () use ($originalDiagnosis, $newValues, $reason) {
            Diagnosis::withFinalizedMutation(function () use ($originalDiagnosis) {
                $originalDiagnosis->update(['is_deprecated' => true]);
            });

            return Diagnosis::create(array_merge($newValues, [
                'encounter_id' => $originalDiagnosis->encounter_id,
                'patient_id' => $originalDiagnosis->patient_id,
                'diagnosed_by' => auth()->id(),

                'is_amendment' => true,
                'amended_diagnosis_id' => $originalDiagnosis->id,
                'amendment_reason' => $reason,
            ]));
        });
    }
}
