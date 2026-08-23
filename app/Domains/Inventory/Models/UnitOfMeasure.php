<?php

namespace App\Domains\Inventory\Models;

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
 * @property string $name
 * @property string $symbol
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ItemMaster> $items
 * @property-read int|null $items_count
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class UnitOfMeasure extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $table = 'units_of_measure';

    protected $guarded = ['id'];

    /**
     * @return HasMany<ItemMaster, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItemMaster::class, 'base_uom_id');
    }
}
