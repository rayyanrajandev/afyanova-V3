<?php

namespace App\Domains\Clinical\Actions;

use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StartEncounterAction
{
    public function execute(array $data): Encounter
    {
        $patient = Patient::findOrFail((string) $data['patient_id']);

        if ($patient->isDeceased()) {
            throw new InvalidArgumentException("Cannot start encounter. Patient {$patient->first_name} {$patient->last_name} is recorded as Deceased.");
        }

        if ($patient->isMerged()) {
            throw new InvalidArgumentException("Cannot start encounter. Patient {$patient->first_name} {$patient->last_name} has been merged into {$patient->merged_into_patient_id}.");
        }

        return DB::transaction(function () use ($data) {
            $attributes = [
                'patient_id' => $data['patient_id'],
                'facility_id' => $data['facility_id'],
                'department_id' => $data['department_id'],
                'provider_id' => $data['provider_id'] ?? null,
                'encounter_type' => $data['encounter_type'],
                'reason_for_visit' => $data['reason_for_visit'] ?? null,
                'status' => $data['status'] ?? 'In Progress',
                'start_time' => now(),
            ];

            if (! empty($data['tenant_id'])) {
                $attributes['tenant_id'] = $data['tenant_id'];
            }

            return Encounter::create($attributes);
        });
    }
}
