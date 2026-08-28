<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\AuditsBulkWrites;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Core\Traits\ImmutableWhenFinalized;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $recorded_by
 * @property float|null $temperature_c
 * @property int|null $heart_rate
 * @property int|null $systolic_bp
 * @property int|null $diastolic_bp
 * @property int|null $respiratory_rate
 * @property float|null $oxygen_saturation
 * @property float|null $weight_kg
 * @property float|null $height_cm
 * @property float|null $bmi
 * @property string|null $notes
 * @property bool $is_amendment
 * @property string|null $amended_vital_id
 * @property string|null $amendment_reason
 * @property bool $is_deprecated
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter $encounter
 * @property-read ClinicalVital|null $originalVital
 * @property-read Patient $patient
 * @property-read User $recorder
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereAmendedVitalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereAmendmentReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereBmi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereDiastolicBp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereHeartRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereHeightCm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereIsAmendment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereIsDeprecated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereOxygenSaturation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereRecordedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereRecordedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereRespiratoryRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereSystolicBp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereTemperatureC($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicalVital whereWeightKg($value)
 *
 * @mixin \Eloquent
 */
class ClinicalVital extends Model
{
    use Auditable, AuditsBulkWrites, BelongsToTenant, HasUuidv7, ImmutableWhenFinalized;

    const AUDIT_CATEGORY = 'CLINICAL';

    const AUDIT_REDACT = [
        'temperature_c', 'heart_rate', 'systolic_bp', 'diastolic_bp',
        'respiratory_rate', 'oxygen_saturation', 'weight_kg', 'height_cm',
        'bmi', 'notes', 'amendment_reason',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_amendment' => 'boolean',
        'is_deprecated' => 'boolean',
        'temperature_c' => 'float',
        'heart_rate' => 'integer',
        'systolic_bp' => 'integer',
        'diastolic_bp' => 'integer',
        'respiratory_rate' => 'integer',
        'oxygen_saturation' => 'float',
        'weight_kg' => 'float',
        'height_cm' => 'float',
        'bmi' => 'float',
        'recorded_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<ClinicalVital, $this>
     */
    public function originalVital(): BelongsTo
    {
        return $this->belongsTo(ClinicalVital::class, 'amended_vital_id');
    }

    protected function isFinalized(): bool
    {
        // Vitals have no draft state — a recorded reading is final the
        // moment it's created; corrections are always amendments.
        return true;
    }
}
