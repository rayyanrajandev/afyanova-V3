<?php

namespace App\Domains\Insurance\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\Encounter;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $patient_id
 * @property string $patient_policy_id
 * @property string|null $encounter_id
 * @property string $auth_code
 * @property string $procedure_description
 * @property numeric $requested_amount
 * @property numeric $approved_amount
 * @property string $status
 * @property Carbon|null $expires_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter|null $encounter
 * @property-read Patient $patient
 * @property-read PatientPolicy $policy
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereApprovedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereAuthCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization wherePatientPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereProcedureDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereRequestedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreAuthorization whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PreAuthorization extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INSURANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'expires_at' => 'date',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<PatientPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(PatientPolicy::class, 'patient_policy_id');
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }
}
