<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Clinical\Models\Diagnosis;
use App\Domains\Clinical\Models\Encounter;
use Illuminate\Support\Facades\DB;

/**
 * Records a structured, ICD-10-coded diagnosis against an encounter.
 *
 * Before this Action existed, Diagnosis::create() was called in exactly one
 * live-app code path — nowhere. AmendDiagnosisAction (which deprecates an
 * existing diagnosis and creates its replacement) had no counterpart for
 * creating the first one, and the SOAP note's "Assessment" field only ever
 * wrote free text into ClinicalNote.content, never a Diagnosis row. Every
 * downstream consumer that reads Diagnosis — MTUHA morbidity reporting,
 * insurance claim scrubbing (GenerateClaimFromEncounterAction explicitly
 * checks for one), FHIR Condition export — was therefore working from an
 * empty table outside of the database-simulation seeder command.
 *
 * Wrapped in DB::transaction() for consistency with every other clinical
 * write in this domain (AmendDiagnosisAction, AmendClinicalNoteAction),
 * even though a single insert doesn't strictly need one today.
 */
class CreateDiagnosisAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Encounter $encounter, array $data): Diagnosis
    {
        return DB::transaction(fn () => Diagnosis::create([
            'tenant_id' => $encounter->tenant_id,
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'diagnosed_by' => auth()->id(),
            'icd_10_code' => $data['icd_10_code'] ?? null,
            'description' => $data['description'],
            'certainty' => $data['certainty'],
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
        ]));
    }
}
