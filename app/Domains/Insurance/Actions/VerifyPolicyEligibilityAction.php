<?php

namespace App\Domains\Insurance\Actions;

use App\Domains\Insurance\Models\PatientPolicy;
use Illuminate\Support\Facades\DB;

class VerifyPolicyEligibilityAction
{
    public function execute(PatientPolicy $policy, bool $biometric = true): PatientPolicy
    {
        return DB::transaction(function () use ($policy, $biometric) {
            $isExpired = $policy->policy_expiry_date && $policy->policy_expiry_date->isPast();

            $policy->update([
                'status' => $isExpired ? 'Expired' : 'Active',
                'biometric_verified' => $biometric,
                'verified_at' => now(),
            ]);

            return $policy->fresh(['provider', 'scheme', 'patient']);
        });
    }
}
