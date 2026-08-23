<?php

namespace App\Domains\Billing\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $facility_id
 * @property string $user_id
 * @property string $shift_number
 * @property string $status
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property numeric $opening_float
 * @property numeric|null $closing_cash_counted
 * @property numeric $expected_cash_total
 * @property numeric $discrepancy
 * @property string $variance_status
 * @property string|null $notes
 * @property string|null $reconciled_by
 * @property Carbon|null $reconciled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read User|null $reconciledBy
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereClosedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereClosingCashCounted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereDiscrepancy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereExpectedCashTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereOpenedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereOpeningFloat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereReconciledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereReconciledBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereShiftNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashierShift whereVarianceStatus($value)
 *
 * @mixin \Eloquent
 */
class CashierShift extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'BILLING';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'opening_float' => 'decimal:2',
        'closing_cash_counted' => 'decimal:2',
        'expected_cash_total' => 'decimal:2',
        'discrepancy' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
