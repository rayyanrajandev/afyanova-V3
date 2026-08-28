<?php

namespace App\Domains\Tenancy\Models;

use App\Core\Traits\HasUuidv7;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasUuidv7;

    protected $table = 'subscription_plans';

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'string',
        'price_monthly_tzs' => 'integer',
        'price_annual_tzs' => 'integer',
        'max_facilities' => 'integer',
        'max_users' => 'integer',
        'storage_quota_mb' => 'integer',
        'feature_flags' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
    ];
}
