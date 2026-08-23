<?php

namespace App\Domains\Pharmacy\Actions;

use App\Domains\Pharmacy\Models\Prescription;

class VerifyPrescriptionAction
{
    public function execute(Prescription $prescription, bool $approve = true, ?string $reason = null): Prescription
    {
        $status = $approve ? 'Verified' : 'Rejected';

        $prescription->update([
            'status' => $status,
            // In a real app we'd log the reason and verifier_id
        ]);

        return $prescription;
    }
}
