<?php

namespace App\Domains\Tenancy\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\ImpersonationLog;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ExitImpersonationAction
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function execute(): ?User
    {
        $impersonation = session()->get('impersonation');

        if (! $impersonation || empty($impersonation['superadmin_id'])) {
            throw new InvalidArgumentException('No active superadmin impersonation session detected.');
        }

        $superadmin = User::find($impersonation['superadmin_id']);
        if (! $superadmin) {
            throw new InvalidArgumentException('Original Superadmin user record could not be resolved.');
        }

        // 1. Update ImpersonationLog with ended_at timestamp
        if (! empty($impersonation['log_id'])) {
            $log = ImpersonationLog::find($impersonation['log_id']);
            $log?->update(['ended_at' => now()]);
        }

        // 2. Audit log termination
        $this->auditLogger->log(
            category: 'PLATFORM_SUPERADMIN',
            action: 'IMPERSONATION_ENDED',
            auditableType: ImpersonationLog::class,
            auditableId: $impersonation['log_id'] ?? $superadmin->id,
            before: $impersonation,
            after: ['ended_at' => now()->toIso8601String()],
            facilityId: null,
            tenantId: $superadmin->tenant_id,
            justification: 'Superadmin concluded impersonation session and returned to platform control plane.'
        );

        // 3. Clear session tokens
        session()->forget('impersonation');

        // 4. Restore original Tenant Context and log back in
        app(TenantContext::class)->setTenantId($superadmin->tenant_id);
        Auth::login($superadmin);

        return $superadmin;
    }
}
