<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
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
 * @property string $order_number
 * @property string $encounter_id
 * @property string $patient_id
 * @property string|null $ordering_provider_id
 * @property string $priority
 * @property string|null $clinical_notes
 * @property string $status
 * @property Carbon $ordered_at
 * @property Carbon|null $collected_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter $encounter
 * @property-read Collection<int, LabOrderItem> $items
 * @property-read int|null $items_count
 * @property-read User|null $orderingProvider
 * @property-read Patient $patient
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereClinicalNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereCollectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereOrderedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereOrderingProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrder whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LabOrder extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'ordered_at' => 'datetime',
        'collected_at' => 'datetime',
        'completed_at' => 'datetime',
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
    public function orderingProvider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordering_provider_id');
    }

    /**
     * @return HasMany<LabOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }
}
