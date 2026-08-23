<?php

namespace App\Domains\Inpatient\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Inpatient\Models\Admission;

class AdmissionPolicy
{
    public function __construct(
        protected AuthorizationService $auth
    ) {}

    public function admit(User $user, ?string $facilityId = null): bool
    {
        return $this->auth->hasPermission($user, 'inpatient.admission.create', $facilityId);
    }

    public function transfer(User $user, Admission $admission): bool
    {
        return $this->auth->hasPermission($user, 'inpatient.admission.transfer', $admission->facility_id);
    }

    public function discharge(User $user, Admission $admission): bool
    {
        return $this->auth->hasPermission($user, 'inpatient.admission.discharge', $admission->facility_id);
    }

    public function administerMar(User $user, Admission $admission): bool
    {
        return $this->auth->hasPermission($user, 'inpatient.mar.administer', $admission->facility_id);
    }
}
