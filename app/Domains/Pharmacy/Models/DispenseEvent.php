<?php

namespace App\Domains\Pharmacy\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $prescription_id
 * @property string $dispensed_by
 * @property int $quantity_dispensed
 * @property string|null $pharmacist_notes
 * @property Carbon $dispensed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryBatch> $batches
 * @property-read int|null $batches_count
 * @property-read Collection<int, DispenseEventBatch> $dispenseEventBatches
 * @property-read int|null $dispense_event_batches_count
 * @property-read User $pharmacist
 * @property-read Prescription $prescription
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent whereDispensedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent whereDispensedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent wherePharmacistNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent wherePrescriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent whereQuantityDispensed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispenseEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class DispenseEvent extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'PHARMACY';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'quantity_dispensed' => 'integer',
        'dispensed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pharmacist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    /**
     * @return HasMany<DispenseEventBatch, $this>
     */
    public function dispenseEventBatches(): HasMany
    {
        return $this->hasMany(DispenseEventBatch::class, 'dispense_event_id');
    }

    /**
     * @return BelongsToMany<InventoryBatch, $this>
     */
    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(InventoryBatch::class, 'dispense_event_batches', 'dispense_event_id', 'batch_id')
            ->withPivot('quantity_dispensed', 'unit_price_at_dispense')
            ->withTimestamps();
    }
}
