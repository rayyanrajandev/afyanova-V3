<?php

namespace App\Domains\Laboratory\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasUuidv7;
use App\Domains\Clinical\Models\LabTest;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $lab_test_id
 * @property string|null $parameter_name
 * @property string $gender
 * @property int $age_min_days
 * @property int $age_max_days
 * @property float|null $normal_min
 * @property float|null $normal_max
 * @property float|null $critical_low
 * @property float|null $critical_high
 * @property string|null $unit
 * @property string|null $textual_normal_range
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read LabTest $test
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereAgeMaxDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereAgeMinDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereCriticalHigh($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereCriticalLow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereLabTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereNormalMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereNormalMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereParameterName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereTextualNormalRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LabTestRange whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LabTestRange extends Model
{
    use BelongsToTenant, HasUuidv7;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'age_min_days' => 'integer',
        'age_max_days' => 'integer',
        'normal_min' => 'float',
        'normal_max' => 'float',
        'critical_low' => 'float',
        'critical_high' => 'float',
    ];

    /**
     * @return BelongsTo<LabTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }
}
