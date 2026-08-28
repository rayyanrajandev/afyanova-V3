<?php

namespace App\Domains\Radiology\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $ordering_doctor_id
 * @property string $order_number
 * @property string $modality
 * @property string $procedure_name
 * @property string|null $body_site
 * @property string|null $clinical_indication
 * @property string $priority
 * @property string $status
 * @property Carbon $ordered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter $encounter
 * @property-read Facility $facility
 * @property-read RadiologyReport|null $latestReport
 * @property-read User $orderingDoctor
 * @property-read Patient $patient
 * @property-read Collection<int, RadiologyReport> $reports
 * @property-read int|null $reports_count
 * @property-read Collection<int, RadiologyStudy> $studies
 * @property-read int|null $studies_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereBodySite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereClinicalIndication($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereModality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereOrderedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereOrderingDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereProcedureName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RadiologyOrder whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class RadiologyOrder extends Model
{
    use Auditable, BelongsToTenant, HasFacilityScope, HasUuidv7;

    const AUDIT_CATEGORY = 'RADIOLOGY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'ordered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function orderingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordering_doctor_id');
    }

    /**
     * @return HasMany<RadiologyStudy, $this>
     */
    public function studies(): HasMany
    {
        return $this->hasMany(RadiologyStudy::class);
    }

    /**
     * @return HasMany<RadiologyReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(RadiologyReport::class);
    }

    /**
     * @return HasOne<RadiologyReport, $this>
     */
    public function latestReport(): HasOne
    {
        return $this->hasOne(RadiologyReport::class)->latestOfMany('created_at');
    }
}
