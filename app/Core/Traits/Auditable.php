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

        // $model is only known statically as the base Eloquent Model here —
        // AUDIT_CATEGORY/AUDIT_REDACT are per-model conventions, not
        // something every Model declares, so they're read dynamically via
        // constant() rather than `$model::AUDIT_CATEGORY` (which assumes it
        // exists).
        $modelClass = get_class($model);
        $redact = defined($modelClass.'::AUDIT_REDACT') ? constant($modelClass.'::AUDIT_REDACT') : [];

        $before = $action === 'UPDATE' || $action === 'DELETE' ? (json_encode(static::redactAuditAttributes($model->getOriginal(), $redact)) ?: null) : null;
        $after = $action === 'CREATE' || $action === 'UPDATE' ? (json_encode(static::redactAuditAttributes($model->getAttributes(), $redact)) ?: null) : null;

        /** @var FacilityContext $facilityContext */
        $facilityContext = App::make(FacilityContext::class);

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

    /**
     * Strip a model's AUDIT_REDACT-listed attribute names from an
     * attribute snapshot before it's persisted into audit_logs — clinical
     * content (diagnosis text, note bodies, vitals, allergen/reaction
     * narrative) is otherwise duplicated verbatim into a table with no
     * encryption-at-rest and a no-delete retention rule. Non-redacted
     * fields (ids, status flags, timestamps) are kept so the entry still
     * shows what changed.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $redact
     * @return array<string, mixed>
     */
    protected static function redactAuditAttributes(array $attributes, array $redact): array
    {
        foreach ($redact as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = '[REDACTED]';
            }
        }

        return $attributes;
    }
}
