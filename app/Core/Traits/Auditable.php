<?php

namespace App\Core\Traits;

use App\Core\Context\FacilityContext;
use App\Core\Context\TenantContext;
use App\Domains\Audit\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::logAudit($model, 'CREATE');
        });

        static::updated(function (Model $model) {
            static::logAudit($model, 'UPDATE');
        });

        static::deleted(function (Model $model) {
            static::logAudit($model, 'DELETE');
        });
    }

    protected static function logAudit(Model $model, string $action): void
    {
        /** @var TenantContext $context */
        $context = App::make(TenantContext::class);
        $tenantId = $context->getTenantId() ?? $model->getAttribute('tenant_id');

        if (! $tenantId) {
            return;
        }

        $before = $action === 'UPDATE' || $action === 'DELETE' ? (json_encode($model->getOriginal()) ?: null) : null;
        $after = $action === 'CREATE' || $action === 'UPDATE' ? (json_encode($model->getAttributes()) ?: null) : null;

        /** @var FacilityContext $facilityContext */
        $facilityContext = App::make(FacilityContext::class);

        // $model is only known statically as the base Eloquent Model here —
        // AUDIT_CATEGORY is a per-model convention, not something every
        // Model declares, so it's read dynamically via constant() rather
        // than `$model::AUDIT_CATEGORY` (which assumes it exists).
        $modelClass = get_class($model);
        $userId = auth()->id();

        App::make(AuditLogger::class)->log([
            'tenant_id' => (string) $tenantId,
            'facility_id' => $facilityContext->getFacilityId(),
            'user_id' => $userId !== null ? (string) $userId : null,
            'event_category' => defined($modelClass.'::AUDIT_CATEGORY') ? (string) constant($modelClass.'::AUDIT_CATEGORY') : 'SYSTEM',
            'action' => $action,
            'entity_type' => class_basename($model),
            'entity_id' => (string) $model->getKey(),
            'before_state' => $before,
            'after_state' => $after,
        ]);
    }
}
