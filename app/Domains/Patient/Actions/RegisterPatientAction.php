<?php

namespace App\Domains\Patient\Actions;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterPatientAction
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected FacilityContext $facilityContext,
    ) {}

    public function execute(array $data): Patient
    {
        return DB::transaction(function () use ($data) {
            $mrn = $this->generateMrn();

            $patient = Patient::create([
                'primary_mrn' => $mrn,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'dob' => $data['dob'] ?? null,
                'gender' => $data['gender'],
                'blood_group' => $data['blood_group'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'status' => 'Active',
                'registered_at_facility_id' => $this->resolveRegisteringFacility($data),
            ]);

            if (! empty($data['phone'])) {
                $patient->contacts()->create([
                    'contact_type' => 'Primary Mobile',
                    'value' => $data['phone'],
                ]);
            }

            if (! empty($data['email'])) {
                $patient->contacts()->create([
                    'contact_type' => 'Email',
                    'value' => $data['email'],
                ]);
            }

            if (! empty($data['street_address']) || ! empty($data['ward']) || ! empty($data['district']) || ! empty($data['region'])) {
                $addressParts = array_filter([
                    $data['street_address'] ?? null,
                    $data['ward'] ? 'Kata ya '.$data['ward'] : null,
                    $data['district'] ? 'Wilaya ya '.$data['district'] : null,
                    $data['region'] ? 'Mkoa wa '.$data['region'] : null,
                ]);

                $patient->contacts()->create([
                    'contact_type' => 'Physical Address',
                    'value' => implode(', ', $addressParts),
                ]);
            }

            if (! empty($data['nida'])) {
                $patient->identifiers()->create([
                    'type' => 'NIDA',
                    'identifier_value' => $data['nida'],
                    'is_primary' => false,
                ]);
            }

            return $patient;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveRegisteringFacility(array $data): ?string
    {
        if (! empty($data['facility_id'])) {
            return $data['facility_id'];
        }

        if ($this->facilityContext->hasFacility()) {
            return $this->facilityContext->getFacilityId();
        }

        $user = Auth::user();
        if ($user instanceof User) {
            $assignment = $user->roleAssignments()->whereNotNull('facility_id')->first();
            if ($assignment) {
                return $assignment->facility_id;
            }
        }

        return null;
    }

    protected function generateMrn(): string
    {
        $prefix = 'MRN-'.date('Ym').'-';
        $random = strtoupper(substr(uniqid(), -5));

        return $prefix.$random;
    }
}
