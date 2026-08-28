<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
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
 * @property string $facility_id
 * @property string $patient_id
 * @property string $administered_by
 * @property string|null $encounter_id
 * @property string $vaccine_code
 * @property string $vaccine_name
 * @property int $dose_number
 * @property string|null $batch_number
 * @property Carbon|null $expiration_date
 * @property string|null $administration_site
 * @property string $route
 * @property string|null $adverse_reaction_notes
 * @property Carbon $administered_at
 * @property Carbon|null $next_due_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $administeredBy
 * @property-read Encounter|null $encounter
 * @property-read Facility $facility
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereAdministeredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereAdministeredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereAdministrationSite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereAdverseReactionNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereBatchNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereDoseNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereExpirationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereNextDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereVaccineCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientImmunization whereVaccineName($value)
 *
 * @mixin \Eloquent
 */
class PatientImmunization extends Model
{
    use Auditable, BelongsToTenant, HasFacilityScope, HasUuidv7;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'dose_number' => 'integer',
        'expiration_date' => 'date',
        'administered_at' => 'datetime',
        'next_due_date' => 'date',
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
    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
