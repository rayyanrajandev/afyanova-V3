<?php

namespace App\Domains\Tenancy\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $code
 * @property string $facility_type
 * @property string|null $license_number
 * @property string|null $hfr_code
 * @property string|null $physical_address
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Department> $departments
 * @property-read int|null $departments_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereContactEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereFacilityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereHfrCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereLicenseNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility wherePhysicalAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Facility whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Facility extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'TENANCY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
