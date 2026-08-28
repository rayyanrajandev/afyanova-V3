<?php

namespace App\Domains\Tenancy\Actions;

use App\Core\Context\TenantContext;
use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Tenancy\Models\ImpersonationLog;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use InvalidArgumentException;

class ImpersonateTenantUserAction
{
    public function __construct(
        protected AuthorizationService $authService,
        protected AuditLogger $auditLogger
    ) {}

    public function execute(User $superadmin, Tenant $tenant, User $targetUser, string $justificationReason): ImpersonationLog
    {
        if (! $this->authService->isSuperAdmin($superadmin) && ! $this->authService->hasPermission($superadmin, 'platform.superadmin.access')) {
            throw new InvalidArgumentException('Only authorized Platform Superadmins can initiate support impersonation sessions.');
        }

        if ($targetUser->tenant_id !== $tenant->id) {
            throw new InvalidArgumentException("User {$targetUser->email} does not belong to organization {$tenant->name}.");
        }

        if (empty(trim($justificationReason))) {
            throw new InvalidArgumentException('A detailed legal/technical justification reason is strictly required for support impersonation.');
        }

        // 1. Create immutable audit log entry
        $log = ImpersonationLog::create([
            'superadmin_user_id' => $superadmin->id,
            'impersonated_user_id' => $targetUser->id,
            'impersonated_tenant_id' => $tenant->id,
            'justification_reason' => trim($justificationReason),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'started_at' => now(),
        ]);

        // 2. Log in cryptographic forensic audit log
        $this->auditLogger->log(
            category: 'PLATFORM_SUPERADMIN',
            action: 'IMPERSONATION_STARTED',
            auditableType: ImpersonationLog::class,
            auditableId: $log->id,
            before: null,
            after: [
                'superadmin_id' => $superadmin->id,
                'superadmin_email' => $superadmin->email,
                'target_user_id' => $targetUser->id,
                'target_user_email' => $targetUser->email,
                'target_tenant_id' => $tenant->id,
                'target_tenant_name' => $tenant->name,
                'justification' => $justificationReason,
            ],
            facilityId: $targetUser->facility_id,
            tenantId: $tenant->id,
            justification: "Support Impersonation: {$justificationReason}"
        );

        // 3. Save original superadmin tokens in session
        session()->put('impersonation', [
            'is_active' => true,
            'log_id' => $log->id,
            'superadmin_id' => $superadmin->id,
            'superadmin_name' => $superadmin->first_name . ' ' . $superadmin->last_name,
            'superadmin_email' => $superadmin->email,
            'target_user_id' => $targetUser->id,
            'target_user_name' => $targetUser->first_name . ' ' . $targetUser->last_name,
            'target_tenant_id' => $tenant->id,
            'target_tenant_name' => $tenant->name,
            'started_at' => now()->toIso8601String(),
        ]);

        // 4. Set TenantContext and switch Auth session
        app(TenantContext::class)->setTenantId($tenant->id);
        Auth::login($targetUser);

        return $log;
    }
}
