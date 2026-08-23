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
 * @property string $facility_id
 * @property string|null $anc_encounter_id
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $recorded_by
 * @property float $cervical_dilation_cm
 * @property int $fetal_heart_rate_bpm
 * @property string $liquor_status
 * @property string|null $fetal_head_descent
 * @property int $uterine_contractions_per_10min
 * @property int $contraction_duration_seconds
 * @property float|null $maternal_systolic_bp
 * @property float|null $maternal_diastolic_bp
 * @property int|null $maternal_pulse_bpm
 * @property bool $alert_line_crossed
 * @property bool $action_line_crossed
 * @property string|null $midwife_remarks
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AncEncounter|null $ancEncounter
 * @property-read Encounter $encounter
 * @property-read Facility $facility
 * @property-read Patient $patient
 * @property-read User $recorder
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereActionLineCrossed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereAlertLineCrossed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereAncEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereCervicalDilationCm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereContractionDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereFetalHeadDescent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereFetalHeartRateBpm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereLiquorStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereMaternalDiastolicBp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereMaternalPulseBpm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereMaternalSystolicBp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereMidwifeRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereRecordedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereRecordedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartographEntry whereUterineContractionsPer10min($value)
 *
 * @mixin \Eloquent
 */
class PartographEntry extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'cervical_dilation_cm' => 'float',
        'fetal_heart_rate_bpm' => 'integer',
        'uterine_contractions_per_10min' => 'integer',
        'contraction_duration_seconds' => 'integer',
        'maternal_systolic_bp' => 'float',
        'maternal_diastolic_bp' => 'float',
        'maternal_pulse_bpm' => 'integer',
        'alert_line_crossed' => 'boolean',
        'action_line_crossed' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AncEncounter, $this>
     */
    public function ancEncounter(): BelongsTo
    {
        return $this->belongsTo(AncEncounter::class);
    }

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
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
