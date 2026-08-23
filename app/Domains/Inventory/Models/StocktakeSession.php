<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
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
 * @property string $session_number
 * @property string $facility_id
 * @property string $location_id
 * @property string $status
 * @property string|null $initiated_by
 * @property string|null $approved_by
 * @property Carbon|null $reconciled_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $approvedBy
 * @property-read Facility $facility
 * @property-read User|null $initiatedBy
 * @property-read Collection<int, StocktakeItem> $items
 * @property-read int|null $items_count
 * @property-read InventoryLocation $location
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereInitiatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereReconciledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereSessionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StocktakeSession whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class StocktakeSession extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'reconciled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * @return HasMany<StocktakeItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StocktakeItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
