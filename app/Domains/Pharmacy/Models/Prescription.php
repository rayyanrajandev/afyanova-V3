<?php

namespace App\Domains\Pharmacy\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $prescriber_id
 * @property string $medication_id
 * @property string $dosage
 * @property string $frequency
 * @property int $duration_days
 * @property string $route
 * @property int $quantity
 * @property string|null $instructions
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, DispenseEvent> $dispenseEvents
 * @property-read int|null $dispense_events_count
 * @property-read Encounter $encounter
 * @property-read MedicationFormulary $medication
 * @property-read Patient $patient
 * @property-read User $prescriber
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereDosage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereDurationDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereInstructions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereMedicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription wherePrescriberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Prescription extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PHARMACY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'duration_days' => 'integer',
        'quantity' => 'integer',
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
    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescriber_id');
    }

    /**
     * @return BelongsTo<MedicationFormulary, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationFormulary::class, 'medication_id');
    }

    /**
     * @return HasMany<DispenseEvent, $this>
     */
    public function dispenseEvents(): HasMany
    {
        return $this->hasMany(DispenseEvent::class);
    }
}
