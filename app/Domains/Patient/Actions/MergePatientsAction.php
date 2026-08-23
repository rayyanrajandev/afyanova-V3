<?php

namespace App\Domains\Patient\Actions;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Patient\Events\PatientMergedEvent;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MergePatientsAction
{
    public function execute(Patient $winner, Patient $loser): Patient
    {
        if ($winner->id === $loser->id) {
            throw new InvalidArgumentException('Cannot merge a patient into themselves.');
        }

        if ($winner->tenant_id !== $loser->tenant_id) {
            throw new InvalidArgumentException('Cannot merge patients across different tenants.');
        }

        if ($loser->isMerged()) {
            throw new InvalidArgumentException("Patient {$loser->primary_mrn} has already been merged into {$loser->merged_into_patient_id}.");
        }

        return DB::transaction(function () use ($winner, $loser) {
            $tenantId = $winner->tenant_id;
            $userId = auth()->id();

            // 1. Mark the loser as merged
            $loser->update([
                'status' => 'Merged',
                'merged_into_patient_id' => $winner->id,
            ]);

            // 2. Re-assign unique identifiers from loser to winner
            foreach ($loser->identifiers as $identifier) {
                $exists = $winner->identifiers()
                    ->where('type', $identifier->type)
                    ->where('identifier_lookup_hash', $identifier->identifier_lookup_hash)
                    ->exists();

                if (! $exists) {
                    $identifier->update(['patient_id' => $winner->id, 'is_primary' => false]);
                }
            }

            // 3. Re-link demographic child tables
            DB::table('patient_contacts')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('emergency_contacts')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('patient_relationships')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('patient_policies')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);

            // 4. Re-link Clinical Records
            DB::table('allergies')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('encounters')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('clinical_notes')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('clinical_vitals')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('diagnoses')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('prescriptions')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('procedure_orders')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('admissions')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);

            if (DB::getSchemaBuilder()->hasTable('lab_orders')) {
                DB::table('lab_orders')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            }

            // 5. Re-link Financial & Operational Records
            DB::table('invoices')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('appointments')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);
            DB::table('insurance_claims')->where('patient_id', $loser->id)->update(['patient_id' => $winner->id]);

            // 6. Audit Trail Logging
            App::make(AuditLogger::class)->log([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'event_category' => 'PATIENT',
                'action' => 'PATIENT_MERGED',
                'entity_type' => 'Patient',
                'entity_id' => $winner->id,
                'before_state' => json_encode(['merged_from_patient_id' => $loser->id, 'loser_mrn' => $loser->primary_mrn]),
                'after_state' => json_encode(['canonical_mrn' => $winner->primary_mrn]),
                'justification_reason' => "Merged duplicate patient record {$loser->primary_mrn} into {$winner->primary_mrn}",
            ]);

            // 7. Dispatch Event
            event(new PatientMergedEvent($winner, $loser, $userId));

            return $winner->refresh();
        });
    }
}
