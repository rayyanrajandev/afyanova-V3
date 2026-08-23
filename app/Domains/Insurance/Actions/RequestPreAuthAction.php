<?php

namespace App\Domains\Insurance\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Insurance\Models\PatientPolicy;
use App\Domains\Insurance\Models\PreAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestPreAuthAction
{
    public function execute(array $data): PreAuthorization
    {
        return DB::transaction(function () use ($data) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? auth()->user()?->tenant_id ?? 'default';

            $policy = PatientPolicy::findOrFail((string) $data['patient_policy_id']);
            $authCode = $data['auth_code'] ?? ('TAR-'.date('Y').'-'.strtoupper(Str::random(6)));

            return PreAuthorization::create([
                'tenant_id' => $tenantId,
                'patient_id' => $policy->patient_id,
                'patient_policy_id' => $policy->id,
                'encounter_id' => $data['encounter_id'] ?? null,
                'auth_code' => $authCode,
                'procedure_description' => $data['procedure_description'],
                'requested_amount' => floatval($data['requested_amount'] ?? 0.00),
                'approved_amount' => floatval($data['approved_amount'] ?? $data['requested_amount'] ?? 0.00),
                'status' => $data['status'] ?? 'Approved',
                'expires_at' => $data['expires_at'] ?? now()->addDays(30)->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}
