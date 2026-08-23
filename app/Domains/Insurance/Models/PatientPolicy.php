<?php

namespace App\Domains\Insurance\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
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
 * @property string $patient_id
 * @property string $insurance_provider_id
 * @property string|null $insurance_scheme_id
 * @property string $card_number
 * @property string|null $principal_member_name
 * @property string|null $principal_member_number
 * @property string $relationship
 * @property Carbon|null $policy_start_date
 * @property Carbon|null $policy_expiry_date
 * @property string $status
 * @property bool $biometric_verified
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InsuranceClaim> $claims
 * @property-read int|null $claims_count
 * @property-read Patient $patient
 * @property-read Collection<int, PreAuthorization> $preAuthorizations
 * @property-read int|null $pre_authorizations_count
 * @property-read InsuranceProvider $provider
 * @property-read InsuranceScheme|null $scheme
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereBiometricVerified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereCardNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereInsuranceProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereInsuranceSchemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy wherePolicyExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy wherePolicyStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy wherePrincipalMemberName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy wherePrincipalMemberNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereRelationship($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientPolicy whereVerifiedAt($value)
 *
 * @mixin \Eloquent
 */
class PatientPolicy extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INSURANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'policy_start_date' => 'date',
        'policy_expiry_date' => 'date',
        'biometric_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<InsuranceProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id');
    }

    /**
     * @return BelongsTo<InsuranceScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(InsuranceScheme::class, 'insurance_scheme_id');
    }

    /**
     * @return HasMany<PreAuthorization, $this>
     */
    public function preAuthorizations(): HasMany
    {
        return $this->hasMany(PreAuthorization::class);
    }

    /**
     * @return HasMany<InsuranceClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status !== 'Active') {
            return false;
        }

        if ($this->policy_expiry_date && $this->policy_expiry_date->isPast()) {
            return false;
        }

        return true;
    }
}
