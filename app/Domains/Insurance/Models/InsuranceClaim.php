<?php

namespace App\Domains\Insurance\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Clinical\Models\Encounter;
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
 * @property string $claim_number
 * @property string $patient_id
 * @property string $patient_policy_id
 * @property string $encounter_id
 * @property string|null $invoice_id
 * @property numeric $total_claimed_amount
 * @property numeric $co_pay_amount
 * @property numeric $approved_amount
 * @property string $status
 * @property bool $scrubber_passed
 * @property array<array-key, mixed>|null $scrubber_errors
 * @property string|null $batch_number
 * @property string|null $rejection_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $adjudicated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Encounter $encounter
 * @property-read Invoice|null $invoice
 * @property-read Collection<int, InsuranceClaimItem> $items
 * @property-read int|null $items_count
 * @property-read Patient $patient
 * @property-read PatientPolicy $policy
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereAdjudicatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereApprovedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereBatchNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereClaimNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereCoPayAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim wherePatientPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereScrubberErrors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereScrubberPassed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereTotalClaimedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InsuranceClaim whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InsuranceClaim extends Model
{
    use Auditable, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'INSURANCE';

    protected $guarded = ['id'];

    protected $casts = [
        'total_claimed_amount' => 'decimal:2',
        'co_pay_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'scrubber_passed' => 'boolean',
        'scrubber_errors' => 'array',
        'submitted_at' => 'datetime',
        'adjudicated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<PatientPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(PatientPolicy::class, 'patient_policy_id');
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<InsuranceClaimItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InsuranceClaimItem::class);
    }
}
