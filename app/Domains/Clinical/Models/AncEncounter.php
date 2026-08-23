<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $midwife_id
 * @property int $gravida
 * @property int $para
 * @property Carbon|null $last_menstrual_period
 * @property Carbon|null $estimated_date_of_delivery
 * @property int|null $gestational_age_weeks
 * @property float|null $fundal_height_cm
 * @property string|null $fetal_presentation
 * @property int|null $fetal_heart_rate_bpm
 * @property string|null $fetal_movement
 * @property float|null $urinary_protein
 * @property string|null $iptp_malaria_dose
 * @property bool $iron_folate_given
 * @property bool $high_risk_flag
 * @property string|null $high_risk_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter $encounter
 * @property-read Facility $facility
 * @property-read User $midwife
 * @property-read Collection<int, PartographEntry> $partographEntries
 * @property-read int|null $partograph_entries_count
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereEstimatedDateOfDelivery($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereFetalHeartRateBpm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereFetalMovement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereFetalPresentation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereFundalHeightCm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereGestationalAgeWeeks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereGravida($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereHighRiskFlag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereHighRiskReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereIptpMalariaDose($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereIronFolateGiven($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereLastMenstrualPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereMidwifeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter wherePara($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AncEncounter whereUrinaryProtein($value)
 *
 * @mixin \Eloquent
 */
class AncEncounter extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'gravida' => 'integer',
        'para' => 'integer',
        'gestational_age_weeks' => 'integer',
        'fundal_height_cm' => 'float',
        'fetal_heart_rate_bpm' => 'integer',
        'urinary_protein' => 'float',
        'iron_folate_given' => 'boolean',
        'high_risk_flag' => 'boolean',
        'last_menstrual_period' => 'date',
        'estimated_date_of_delivery' => 'date',
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
    public function midwife(): BelongsTo
    {
        return $this->belongsTo(User::class, 'midwife_id');
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<PartographEntry, $this>
     */
    public function partographEntries(): HasMany
    {
        return $this->hasMany(PartographEntry::class);
    }
}
