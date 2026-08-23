<?php

namespace App\Domains\Clinical\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Clinical\Models\PatientProblem;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManageProblemListAction
{
    /**
     * Add or update a problem on the patient's problem list.
     */
    public function record(array $data): PatientProblem
    {
        $patient = Patient::findOrFail((string) $data['patient_id']);

        if ($patient->isDeceased()) {
            throw new InvalidArgumentException('Cannot modify problem list. Patient is recorded as Deceased.');
        }

        if ($patient->isMerged()) {
            throw new InvalidArgumentException('Cannot modify problem list. Patient has been merged.');
        }

        return DB::transaction(function () use ($data, $patient) {
            $tenantId = app(TenantContext::class)->getTenantId() ?? $patient->tenant_id;

            return PatientProblem::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'patient_id' => $patient->id,
                    'icd10_code' => strtoupper(trim($data['icd10_code'])),
                ],
                [
                    'problem_name' => $data['problem_name'],
                    'recorded_by' => auth()->id() ?? $data['recorded_by'],
                    'encounter_id' => $data['encounter_id'] ?? null,
                    'status' => $data['status'] ?? 'Active',
                    'clinical_status' => $data['clinical_status'] ?? 'Confirmed',
                    'severity' => $data['severity'] ?? 'Moderate',
                    'onset_date' => $data['onset_date'] ?? now()->toDateString(),
                    'resolved_date' => ($data['status'] ?? '') === 'Resolved' ? ($data['resolved_date'] ?? now()->toDateString()) : null,
                    'notes' => $data['notes'] ?? null,
                ]
            );
        });
    }

    /**
     * Resolve a problem on the problem list.
     */
    public function resolve(PatientProblem $problem, ?string $resolvedDate = null, ?string $notes = null): PatientProblem
    {
        $problem->update([
            'status' => 'Resolved',
            'resolved_date' => $resolvedDate ?? now()->toDateString(),
            'notes' => $notes ? trim(($problem->notes ?? '')."\n".$notes) : $problem->notes,
        ]);

        return $problem->fresh();
    }
}
