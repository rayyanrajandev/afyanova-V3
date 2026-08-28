<?php

namespace App\Domains\Inpatient\Models;

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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $facility_id
 * @property string|null $encounter_id
 * @property string $patient_id
 * @property string $admitting_doctor_id
 * @property string $ward_id
 * @property string $bed_id
 * @property string $admission_number
 * @property string $admission_reason
 * @property string|null $provisional_diagnosis
 * @property Carbon $admitted_at
 * @property Carbon|null $discharged_at
 * @property string|null $discharge_disposition
 * @property string|null $discharge_summary
 * @property string|null $discharged_by
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $admittingDoctor
 * @property-read Bed|null $bed
 * @property-read User|null $dischargedBy
 * @property-read Encounter|null $encounter
 * @property-read Facility|null $facility
 * @property-read int $length_of_stay_days
 * @property-read Collection<int, MedicationAdministrationRecord> $medicationAdministrationRecords
 * @property-read int|null $medication_administration_records_count
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 * @property-read Collection<int, BedTransfer> $transfers
 * @property-read int|null $transfers_count
 * @property-read Ward|null $ward
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereAdmissionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereAdmissionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereAdmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereAdmittingDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereBedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereDischargeDisposition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereDischargeSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereDischargedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereDischargedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereProvisionalDiagnosis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission whereWardId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admission withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Admission extends Model
{
    use Auditable, BelongsToTenant, HasFacilityScope, HasUuidv7, SoftDeletes;

    const AUDIT_CATEGORY = 'INPATIENT_ADMISSION';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];

    protected $appends = ['length_of_stay_days'];

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
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * @return BelongsTo<Bed, $this>
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admittingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitting_doctor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<BedTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(BedTransfer::class, 'admission_id')->latest('transferred_at');
    }

    /**
     * @return HasMany<MedicationAdministrationRecord, $this>
     */
    public function medicationAdministrationRecords(): HasMany
    {
        return $this->hasMany(MedicationAdministrationRecord::class, 'admission_id')->latest('administered_at');
    }

    public function getLengthOfStayDaysAttribute(): int
    {
        if (! $this->admitted_at) {
            return 0;
        }
        $endTime = $this->discharged_at ?: now();

        return max(1, (int) $this->admitted_at->diffInDays($endTime));
    }
}
