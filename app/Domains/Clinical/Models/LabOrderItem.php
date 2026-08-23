<?php

namespace App\Domains\Clinical\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Core\Traits\ImmutableWhenFinalized;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $lab_order_id
 * @property string $lab_test_id
 * @property numeric $price
 * @property string $status
 * @property string|null $specimen_barcode
 * @property array<array-key, mixed>|null $results
 * @property string|null $technician_remarks
 * @property bool $has_critical_value
 * @property Carbon|null $critical_value_alerted_at
 * @property string|null $performed_by_id
 * @property string|null $verified_by_id
 * @property bool $is_amendment
 * @property string|null $amended_result_item_id
 * @property string|null $amendment_reason
 * @property bool $is_deprecated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LabOrder $labOrder
 * @property-read LabTest $labTest
 * @property-read LabOrderItem|null $originalResult
 * @property-read User|null $performedBy
 * @property-read Tenant|null $tenant
 * @property-read User|null $verifiedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereAmendedResultItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereAmendmentReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereCriticalValueAlertedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereHasCriticalValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereIsAmendment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereIsDeprecated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereLabOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereLabTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem wherePerformedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereResults($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereSpecimenBarcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereTechnicianRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabOrderItem whereVerifiedById($value)
 *
 * @mixin \Eloquent
 */
class LabOrderItem extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7, ImmutableWhenFinalized;

    const AUDIT_CATEGORY = 'CLINICAL';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'price' => 'decimal:2',
        'results' => 'array',
        'has_critical_value' => 'boolean',
        'critical_value_alerted_at' => 'datetime',
        'is_amendment' => 'boolean',
        'is_deprecated' => 'boolean',
    ];

    /**
     * @return BelongsTo<LabOrder, $this>
     */
    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    /**
     * @return BelongsTo<LabTest, $this>
     */
    public function labTest(): BelongsTo
    {
        return $this->belongsTo(LabTest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /**
     * @return BelongsTo<LabOrderItem, $this>
     */
    public function originalResult(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'amended_result_item_id');
    }

    protected function isFinalized(): bool
    {
        // The pre-update value: verification itself is the null-to-user
        // write that must be allowed through; only a result that was
        // ALREADY verified before this write is locked.
        return $this->getOriginal('verified_by_id') !== null;
    }
}
