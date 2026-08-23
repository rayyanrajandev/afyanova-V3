<?php

namespace App\Domains\Inpatient\Actions;

use App\Domains\Inpatient\Exceptions\InpatientException;
use App\Domains\Inpatient\Models\Admission;
use App\Domains\Inpatient\Models\Bed;
use App\Domains\Inpatient\Models\BedTransfer;
use App\Domains\Inpatient\Models\Ward;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferBedAction
{
    public function execute(Admission $admission, array $data): BedTransfer
    {
        if ($admission->status !== 'Admitted') {
            throw InpatientException::admissionAlreadyDischarged();
        }

        $toBedId = $data['to_bed_id'] ?? null;
        $reason = $data['reason'] ?? 'Clinical condition requirement / step-down';
        $userId = $data['transferred_by'] ?? auth()->id() ?? $admission->admitting_doctor_id;

        if (! $toBedId) {
            throw new InvalidArgumentException('Destination bed ID is required for transfer.');
        }

        $toBed = Bed::findOrFail($toBedId);
        $fromBed = $admission->bed;
        $fromWard = $admission->ward;
        $toWard = $toBed->ward;

        if ($toBed->id === $fromBed->id) {
            throw new InvalidArgumentException('Destination bed cannot be the same as current bed.');
        }

        if ($toBed->status !== 'Available') {
            throw InpatientException::destinationBedOccupied($toBed->bed_number);
        }

        return DB::transaction(function () use ($admission, $fromWard, $fromBed, $toWard, $toBed, $reason, $userId) {
            // 1. Release source bed (mark as Cleaning or Available)
            $fromBed->update(['status' => 'Cleaning']);

            // 2. Claim destination bed
            $toBed->update(['status' => 'Occupied']);

            // 3. Create BedTransfer log
            $transfer = BedTransfer::create([
                'tenant_id' => $admission->tenant_id,
                'facility_id' => $admission->facility_id,
                'admission_id' => $admission->id,
                'from_ward_id' => $fromWard->id,
                'from_bed_id' => $fromBed->id,
                'to_ward_id' => $toWard->id,
                'to_bed_id' => $toBed->id,
                'transferred_at' => now(),
                'transferred_by' => $userId,
                'reason' => $reason,
            ]);

            // 4. Update Admission record with new ward and bed
            $admission->update([
                'ward_id' => $toWard->id,
                'bed_id' => $toBed->id,
            ]);

            return $transfer;
        });
    }
}
