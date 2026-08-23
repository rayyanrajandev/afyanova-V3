<?php

namespace App\Domains\Audit\Services;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Single write path into audit_logs, shared by the Auditable model trait
 * and anything that isn't a model mutation (login/logout events, chart
 * views) but still needs to land on the same hash-chained trail.
 */
class AuditLogger
{
    /**
     * @param  array{
     *     tenant_id: string,
     *     entity_type: string,
     *     entity_id: string,
     *     event_category: string,
     *     action: string,
     *     facility_id?: ?string,
     *     user_id?: ?string,
     *     before_state?: ?string,
     *     after_state?: ?string,
     *     justification_reason?: ?string,
     * }  $data
     */
    public function log(array $data): void
    {
        $request = request();

        // audit_logs is FORCE RLS'd, and this method is the one write path
        // called from places with no authenticated session yet (login
        // events, the pre-login MFA challenge) — nothing upstream is
        // guaranteed to have set the Postgres session's tenant already.
        // Since tenant_id always arrives explicitly here, set it directly
        // rather than depending on caller ordering.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $data['tenant_id']]);
        }

        $previousHash = DB::table('audit_logs')
            ->where('tenant_id', $data['tenant_id'])
            ->orderByDesc('id')
            ->value('hash_signature');

        $before = $data['before_state'] ?? null;
        $after = $data['after_state'] ?? null;
        $justification = $data['justification_reason'] ?? null;

        $payload = $previousHash.$data['tenant_id'].$data['action'].$data['entity_type'].$data['entity_id'].$before.$after.$justification;
        $hash = hash('sha256', $payload.time());

        DB::table('audit_logs')->insert([
            'id' => Uuid::uuid7()->toString(),
            'tenant_id' => $data['tenant_id'],
            'facility_id' => $data['facility_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'event_category' => $data['event_category'],
            'action' => $data['action'],
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
            'route_name' => $request->route()?->getName(),
            'before_state' => $before,
            'after_state' => $after,
            'justification_reason' => $justification,
            'hash_signature' => $hash,
            'previous_hash' => $previousHash,
            'created_at' => now(),
        ]);
    }
}
