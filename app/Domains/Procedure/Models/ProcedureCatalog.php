<?php

namespace App\Domains\Procedure\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $procedure_code
 * @property string $name
 * @property string $category
 * @property string $tier_level
 * @property int $default_duration_minutes
 * @property numeric $standard_price
 * @property bool $requires_consent
 * @property bool $requires_anesthesia
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ProcedureOrder> $orders
 * @property-read int|null $orders_count
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereDefaultDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereProcedureCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereRequiresAnesthesia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereRequiresConsent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereStandardPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereTierLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcedureCatalog whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProcedureCatalog extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PROCEDURE';

    protected $guarded = ['id'];

    protected $casts = [
        'standard_price' => 'decimal:2',
        'requires_consent' => 'boolean',
        'requires_anesthesia' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<ProcedureOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ProcedureOrder::class);
    }
}
