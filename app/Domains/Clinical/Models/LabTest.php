<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $test_code
 * @property string $name
 * @property string $category
 * @property string $specimen_type
 * @property int $turnaround_time_minutes
 * @property numeric $price
 * @property array<array-key, mixed>|null $parameters
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LabOrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereSpecimenType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereTestCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereTurnaroundTimeMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTest whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LabTest extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'price' => 'decimal:2',
        'turnaround_time_minutes' => 'integer',
        'parameters' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<LabOrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    /**
     * @return BelongsTo<ItemMaster, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'inventory_item_id');
    }
}
