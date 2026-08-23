<?php

namespace App\Domains\Inpatient\Actions;

use App\Domains\Inpatient\Exceptions\InpatientException;
use App\Domains\Inpatient\Models\Admission;
use Illuminate\Support\Facades\DB;

class DischargePatientAction
{
    public function execute(Admission $admission, array $data): Admission
    {
        if ($admission->status !== 'Admitted') {
            throw InpatientException::admissionAlreadyDischarged();
        }

        $disposition = $data['discharge_disposition'] ?? 'Home';
        $summary = $data['discharge_summary'] ?? 'Patient improved and discharged home with follow-up instructions.';
        $dischargedBy = $data['discharged_by'] ?? auth()->id() ?? $admission->admitting_doctor_id;

        return DB::transaction(function () use ($admission, $disposition, $summary, $dischargedBy) {
            // 1. Release the Bed back to Cleaning / Available
            if ($admission->bed) {
                $admission->bed->update(['status' => 'Cleaning']);
            }

            // 2. Update Admission Record
            $admission->update([
                'status' => 'Discharged',
                'discharged_at' => now(),
                'discharge_disposition' => $disposition,
                'discharge_summary' => $summary,
                'discharged_by' => $dischargedBy,
            ]);

            // 3. Mark Encounter as Closed if associated
            if ($admission->encounter) {
                $admission->encounter->update([
                    'status' => 'Closed',
                    'end_time' => now(),
                ]);
            }

            return $admission;
        });
    }
}
