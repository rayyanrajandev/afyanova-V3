<?php

namespace App\Domains\Billing\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\AuditsBulkWrites;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Billing\Exceptions\InvoiceImmutabilityException;
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
 * @property string $facility_id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string $invoice_number
 * @property numeric $total_amount
 * @property numeric $paid_amount
 * @property string $status
 * @property Carbon|null $issued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InvoiceAdjustmentNote> $adjustmentNotes
 * @property-read int|null $adjustment_notes_count
 * @property-read Encounter|null $encounter
 * @property-read Collection<int, InvoiceLineItem> $items
 * @property-read int|null $items_count
 * @property-read Collection<int, InvoiceLineItem> $lineItems
 * @property-read int|null $line_items_count
 * @property-read Patient $patient
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereEncounterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaidAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Invoice extends Model
{
    use Auditable, AuditsBulkWrites, BelongsToTenant, HasUuidv7;

    const AUDIT_CATEGORY = 'BILLING';

    /**
     * Statuses in which total_amount is locked — corrections from here
     * must go through IssueInvoiceAdjustmentAction (a CreditNote/DebitNote),
     * never a direct mutation.
     */
    protected const LOCKED_STATUSES = ['Issued', 'Partially Paid', 'Paid', 'Voided'];

    /**
     * Set only by IssueInvoiceAdjustmentAction for the duration of its own
     * write, so its balanced correction can pass through the guard below.
     */
    protected static bool $allowLockedTotalMutation = false;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice) {
            if (static::$allowLockedTotalMutation) {
                return;
            }

            if (
                $invoice->isDirty('total_amount')
                && in_array($invoice->getOriginal('status'), self::LOCKED_STATUSES, true)
            ) {
                throw InvoiceImmutabilityException::totalCannotBeMutatedOnceLocked(
                    $invoice->id,
                    $invoice->getOriginal('status')
                );
            }
        });
    }

    /**
     * Run a callback that is allowed to adjust total_amount on a locked
     * invoice. Used exclusively by IssueInvoiceAdjustmentAction, which is
     * itself the only sanctioned way to correct an issued invoice's total.
     */
    public static function withLockedTotalMutation(callable $callback): mixed
    {
        static::$allowLockedTotalMutation = true;

        try {
            return $callback();
        } finally {
            static::$allowLockedTotalMutation = false;
        }
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return HasMany<InvoiceLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * @return HasMany<InvoiceLineItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * @return HasMany<InvoiceAdjustmentNote, $this>
     */
    public function adjustmentNotes(): HasMany
    {
        return $this->hasMany(InvoiceAdjustmentNote::class);
    }
}
