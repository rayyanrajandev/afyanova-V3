<?php

namespace App\Domains\Communication\Models;

use App\Core\Traits\Auditable;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFacilityScope;
use App\Core\Traits\HasUuidv7;
use App\Domains\Patient\Models\Patient;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $facility_id
 * @property string|null $patient_id
 * @property string $recipient_phone
 * @property string|null $recipient_name
 * @property string $message_body
 * @property string $gateway_provider
 * @property string|null $message_id
 * @property string $status
 * @property float $cost_credits
 * @property Carbon|null $delivery_timestamp
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SmsNotificationLog extends Model
{
    use Auditable, BelongsToTenant, HasFacilityScope, HasUuidv7;

    const AUDIT_CATEGORY = 'COMMUNICATION';

    protected $guarded = ['id'];

    protected $casts = [
        'cost_credits' => 'decimal:2',
        'delivery_timestamp' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
