<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Department;
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
 * @property string $facility_id
 * @property string $requisition_number
 * @property string|null $department_id
 * @property string $source_location_id
 * @property string $destination_location_id
 * @property string $requisition_type
 * @property string $status
 * @property string|null $requested_by
 * @property string|null $approved_by
 * @property string|null $dispatched_by
 * @property string|null $received_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $received_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $approvedBy
 * @property-read Department|null $department
 * @property-read InventoryLocation $destinationLocation
 * @property-read User|null $dispatchedBy
 * @property-read Facility $facility
 * @property-read Collection<int, DepartmentRequisitionItem> $items
 * @property-read int|null $items_count
 * @property-read User|null $receivedBy
 * @property-read User|null $requestedBy
 * @property-read InventoryLocation $sourceLocation
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereDestinationLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereDispatchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereDispatchedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereReceivedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereRequestedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereRequisitionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereRequisitionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereSourceLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DepartmentRequisition whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class DepartmentRequisition extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    /**
     * @return HasMany<DepartmentRequisitionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DepartmentRequisitionItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
