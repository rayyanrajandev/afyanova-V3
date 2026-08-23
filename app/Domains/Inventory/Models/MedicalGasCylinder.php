<?php

namespace App\Domains\Inventory\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $cylinder_serial_number
 * @property string $gas_type
 * @property string $cylinder_size
 * @property int $volume_liters
 * @property string|null $current_location_id
 * @property string $status
 * @property string|null $assigned_ward_bed
 * @property Carbon|null $last_refilled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryLocation|null $currentLocation
 * @property-read Facility $facility
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereAssignedWardBed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereCurrentLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereCylinderSerialNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereCylinderSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereGasType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereLastRefilledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicalGasCylinder whereVolumeLiters($value)
 *
 * @mixin \Eloquent
 */
class MedicalGasCylinder extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INVENTORY';

    protected $guarded = ['id'];

    protected $casts = [
        'volume_liters' => 'integer',
        'last_refilled_at' => 'datetime',
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
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'current_location_id');
    }
}
