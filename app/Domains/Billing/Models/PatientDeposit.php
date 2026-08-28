<?php

namespace App\Domains\Billing\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
use App\Core\Traits\HasUuidv7;
use App\Domains\Identity\Models\User;
use App\Domains\Patient\Models\Patient;
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
 * @property string $patient_id
 * @property string $user_id
 * @property string|null $cashier_shift_id
 * @property string $deposit_number
 * @property float $amount
 * @property float $balance_remaining
 * @property string $payment_method
 * @property string|null $transaction_reference
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PatientDepositAllocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read User $cashier
 * @property-read CashierShift|null $cashierShift
 * @property-read Facility $facility
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereBalanceRemaining($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereCashierShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereDepositNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientDeposit whereUserId($value)
 *
 * @mixin \Eloquent
 */
class PatientDeposit extends Model
{
    use Auditable, BelongsToTenant, HasFacilityScope, HasUuidv7;

    const AUDIT_CATEGORY = 'BILLING';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'amount' => 'float',
        'balance_remaining' => 'float',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
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
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<CashierShift, $this>
     */
    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    /**
     * @return HasMany<PatientDepositAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PatientDepositAllocation::class, 'deposit_id');
    }
}
