<?php

namespace App\Domains\Patient\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $patient_id
 * @property string|null $facility_id
 * @property string $type
 * @property string $identifier_value
 * @property string|null $identifier_lookup_hash
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereIdentifierLookupHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereIdentifierValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientIdentifier whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PatientIdentifier extends Model
{
    use Auditable, BelongsToTenant, HasFacilityScope, HasUuidv7;

    const AUDIT_CATEGORY = 'PATIENT';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_primary' => 'boolean',
        // A national ID, NHIF card number, passport, etc. — encrypted at
        // rest (AES-256-GCM, see config/app.php). Laravel's encrypted cast
        // uses a random IV per save, so the ciphertext is never the same
        // value twice and can't be searched or uniquely constrained
        // directly — identifier_lookup_hash exists for exactly that.
        'identifier_value' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::saving(function (PatientIdentifier $identifier) {
            if ($identifier->isDirty('identifier_value')) {
                $identifier->identifier_lookup_hash = self::lookupHash($identifier->identifier_value);
            }
        });
    }

    /**
     * Deterministic HMAC of an identifier value, for equality lookups and
     * the tenant+type+hash unique constraint — never used to decrypt or
     * recover the original value, only to compare two values for equality
     * without storing either of them in the clear.
     */
    public static function lookupHash(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
