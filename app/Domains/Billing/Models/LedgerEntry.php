<?php

namespace App\Domains\Billing\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $transaction_id
 * @property string $account_id
 * @property numeric $debit
 * @property numeric $credit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LedgerAccount $account
 * @property-read Tenant $tenant
 * @property-read LedgerTransaction $transaction
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LedgerEntry extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    /**
     * @return BelongsTo<LedgerAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }
}
