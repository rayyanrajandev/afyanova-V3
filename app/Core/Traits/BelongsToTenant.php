<?php

namespace App\Core\Traits;

use App\Core\Context\TenantContext;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            /** @var TenantContext $context */
            $context = App::make(TenantContext::class);
            if ($tenantId = $context->getTenantId()) {
                // Unqualified column name (not table-prefixed): every
                // caller applies this scope to a single model's own query,
                // and no query in this codebase currently joins another
                // tenant-scoped table into the same statement. If one ever
                // does, Postgres raises an immediate, unambiguous "column
                // reference is ambiguous" error rather than silently
                // scoping to the wrong table's tenant_id.
                $builder->where('tenant_id', $tenantId);
            }
        });

        static::creating(function (Model $model) {
            /** @var TenantContext $context */
            $context = App::make(TenantContext::class);
            if (empty($model->getAttribute('tenant_id')) && $tenantId = $context->getTenantId()) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
