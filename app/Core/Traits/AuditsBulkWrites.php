<?php

namespace App\Core\Traits;

use App\Core\Context\TenantContext;
use App\Domains\Audit\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Ramsey\Uuid\Uuid;

/**
 * Provides explicit audited bulk-write methods for Eloquent models.
 *
 * Eloquent's standard model events (created/updated/deleted) fire only
 * for single-row operations. Bulk operations like:
 *
 *   Model::where(...)->update([...])
 *   Model::where(...)->delete()
 *
 * bypass model events entirely by design — they go straight to the
 * query builder and skip the PHP model lifecycle.
 *
 * WHY NOT intercept the query builder?
 * Wrapping newBaseQueryBuilder() to auto-detect bulk operations is
 * fragile — it fires on every query (including reads), requires
 * inspecting the builder state mid-flight, and is hard to test
 * reliably. The result is often false positives or missed writes.
 *
 * BETTER APPROACH — explicit audited methods:
 * Models that need audited bulk writes define WHY a bulk operation is
 * happening by calling these methods. The caller provides a mandatory
 * justification_reason (required for compliance) and the method writes
 * the audit entry before executing the query.
 *
 * Pattern:
 *   Invoice::auditedBulkUpdate(
 *     query: Invoice::where('status', 'draft'),
 *     attributes: ['status' => 'void'],
 *     reason: 'End-of-day void of all unsubmitted draft invoices',
 *     userId: auth()->id(),
 *   );
 */
trait AuditsBulkWrites
{
    /**
     * Perform an audited bulk update.
     *
     * Writes a BULK_UPDATE audit entry with the justification, then
     * executes the update. Returns the number of affected rows.
     *
     * @param  Builder<static>  $query  Pre-scoped builder (e.g. Model::where(...))
     * @param  array<string, mixed>  $attributes  Columns to update
     * @param  string  $reason  Free-text justification logged to the audit trail (min 10 chars)
     * @param  string|null  $userId  Acting user ID (defaults to auth()->id())
     * @param  string|null  $tenantId  Defaults to TenantContext::getTenantId()
     */
    public static function auditedBulkUpdate(
        Builder $query,
        array $attributes,
        string $reason,
        ?string $userId = null,
        ?string $tenantId = null,
    ): int {
        abort_if(strlen($reason) < 10, 422, 'Bulk-write justification must be at least 10 characters.');

        $tenantId ??= App::make(TenantContext::class)->getTenantId();
        $userId ??= auth()->id() !== null ? (string) auth()->id() : null;

        abort_if(! $tenantId, 500, 'Bulk update attempted without a tenant context.');

        App::make(AuditLogger::class)->log([
            'tenant_id' => (string) $tenantId,
            'user_id' => $userId,
            'event_category' => defined(static::class.'::AUDIT_CATEGORY') ? (string) constant(static::class.'::AUDIT_CATEGORY') : 'SYSTEM',
            'action' => 'BULK_UPDATE',
            'entity_type' => class_basename(static::class),
            // audit_logs.entity_id is a required, non-nullable uuid column —
            // there's no single row a bulk operation applies to, so a real
            // generated id stands in for one. The `action` field
            // (BULK_UPDATE/BULK_DELETE) is what actually signals "this
            // entry isn't a reference to an existing entity," not the id
            // itself.
            'entity_id' => Uuid::uuid7()->toString(),
            'after_state' => json_encode($attributes) ?: null,
            'justification_reason' => $reason,
        ]);

        return $query->update($attributes);
    }

    /**
     * Perform an audited bulk delete (soft or hard depending on model).
     *
     * Writes a BULK_DELETE audit entry with the justification, then
     * executes the delete. Returns the number of affected rows.
     *
     * @param  Builder<static>  $query  Pre-scoped builder
     * @param  string  $reason  Free-text justification logged to the audit trail
     */
    public static function auditedBulkDelete(
        Builder $query,
        string $reason,
        ?string $userId = null,
        ?string $tenantId = null,
    ): int {
        abort_if(strlen($reason) < 10, 422, 'Bulk-write justification must be at least 10 characters.');

        $tenantId ??= App::make(TenantContext::class)->getTenantId();
        $userId ??= auth()->id() !== null ? (string) auth()->id() : null;

        abort_if(! $tenantId, 500, 'Bulk delete attempted without a tenant context.');

        App::make(AuditLogger::class)->log([
            'tenant_id' => (string) $tenantId,
            'user_id' => $userId,
            'event_category' => defined(static::class.'::AUDIT_CATEGORY') ? (string) constant(static::class.'::AUDIT_CATEGORY') : 'SYSTEM',
            'action' => 'BULK_DELETE',
            'entity_type' => class_basename(static::class),
            // audit_logs.entity_id is a required, non-nullable uuid column —
            // there's no single row a bulk operation applies to, so a real
            // generated id stands in for one. The `action` field
            // (BULK_UPDATE/BULK_DELETE) is what actually signals "this
            // entry isn't a reference to an existing entity," not the id
            // itself.
            'entity_id' => Uuid::uuid7()->toString(),
            'justification_reason' => $reason,
        ]);

        return $query->delete();
    }
}
