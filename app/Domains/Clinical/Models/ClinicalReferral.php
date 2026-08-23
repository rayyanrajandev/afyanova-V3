<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $from_facility_id
 * @property string|null $to_facility_id
 * @property string|null $external_facility_name
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string $referring_doctor_id
 * @property string $referral_number
 * @property string $urgency
 * @property string $specialty_required
 * @property string $clinical_summary
 * @property string|null $investigations_performed
 * @property string|null $treatments_given
 * @property string $reason_for_referral
 * @property string|null $transport_mode
 * @property string $status
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter|null $encounter
 * @property-read Facility $fromFacility
 * @property-read Patient $patient
 * @property-read User $referringDoctor
 * @property-read Tenant $tenant
 * @property-read Facility|null $toFacility
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereClinicalSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereDispatchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereExternalFacilityName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereFromFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereInvestigationsPerformed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereReasonForReferral($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereReferralNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereReferringDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereSpecialtyRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereToFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereTransportMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereTreatmentsGiven($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalReferral whereUrgency($value)
 *
 * @mixin \Eloquent
 */
class ClinicalReferral extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'dispatched_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referringDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referring_doctor_id');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function fromFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'from_facility_id');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function toFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'to_facility_id');
    }
}
