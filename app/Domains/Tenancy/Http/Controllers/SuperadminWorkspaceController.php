<?php

namespace App\Domains\Tenancy\Http\Controllers;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Tenancy\Actions\ExitImpersonationAction;
use App\Domains\Tenancy\Actions\ImpersonateTenantUserAction;
use App\Domains\Tenancy\Actions\ProvisionTenantAction;
use App\Domains\Tenancy\Actions\SyncMasterDictionaryAction;
use App\Domains\Tenancy\Actions\UpdateTenantSubscriptionAction;
use App\Domains\Tenancy\Models\ImpersonationLog;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\PlatformTelemetryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminWorkspaceController extends Controller
{
    public function __construct(
        protected AuthorizationService $authService
    ) {}

    public function index(Request $request, PlatformTelemetryService $telemetryService): Response
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403, 'Unauthorized. Platform Superadmin credentials required.');

        $telemetry = $telemetryService->getGlobalMetrics();

        $tenants = Tenant::with(['facilities', 'users'])
            ->latest('created_at')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'domain' => $t->domain,
                    'status' => $t->status,
                    'subscription_tier' => $t->subscription_tier ?? $t->plan,
                    'subscription_status' => $t->subscription_status ?? $t->status,
                    'max_facilities' => $t->max_facilities ?? 5,
                    'max_users' => $t->max_users ?? 50,
                    'storage_quota_mb' => $t->storage_quota_mb ?? 10240,
                    'feature_flags' => $t->feature_flags ?? ['inpatient', 'pharmacy', 'laboratory', 'billing'],
                    'billing_cycle' => $t->billing_cycle ?? 'monthly',
                    'billing_contact_email' => $t->billing_contact_email,
                    'billing_contact_phone' => $t->billing_contact_phone,
                    'trial_ends_at' => $t->trial_ends_at?->toDateString(),
                    'created_at' => $t->created_at?->toIso8601String(),
                    'facilities_count' => $t->facilities->count(),
                    'users_count' => $t->users->count(),
                    'users' => $t->users->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => "{$u->first_name} {$u->last_name}",
                        'email' => $u->email,
                        'role' => $u->roleAssignments->first()?->role?->name ?? 'Staff User',
                    ]),
                ];
            });

        $recentLogs = ImpersonationLog::with(['superadmin', 'impersonatedUser', 'tenant'])
            ->latest('started_at')
            ->take(30)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'superadmin_name' => $log->superadmin ? "{$log->superadmin->first_name} {$log->superadmin->last_name}" : 'Superadmin',
                'superadmin_email' => $log->superadmin?->email,
                'target_user_name' => $log->impersonatedUser ? "{$log->impersonatedUser->first_name} {$log->impersonatedUser->last_name}" : 'User',
                'target_user_email' => $log->impersonatedUser?->email,
                'target_tenant_name' => $log->tenant?->name ?? 'Unknown Organization',
                'justification_reason' => $log->justification_reason,
                'ip_address' => $log->ip_address,
                'started_at' => $log->started_at?->toIso8601String(),
                'ended_at' => $log->ended_at?->toIso8601String(),
                'is_active' => is_null($log->ended_at),
            ]);

        return Inertia::render('Workspace/SuperadminWorkspace', [
            'telemetry' => $telemetry,
            'tenants' => $tenants,
            'recentLogs' => $recentLogs,
            'currentUser' => [
                'id' => $user->id,
                'name' => "{$user->first_name} {$user->last_name}",
                'email' => $user->email,
                'is_superadmin' => true,
            ],
        ]);
    }

    public function storeTenant(Request $request, ProvisionTenantAction $action)
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100',
            'domain' => 'nullable|string|max:255',
            'subscription_tier' => 'required|string|in:starter,growth,enterprise',
            'subscription_status' => 'required|string|in:active,trial,past_due,suspended',
            'max_facilities' => 'nullable|integer|min:1|max:200',
            'max_users' => 'nullable|integer|min:1|max:5000',
            'admin_first_name' => 'required|string|max:100',
            'admin_last_name' => 'required|string|max:100',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_phone' => 'nullable|string|max:50',
            'admin_password' => 'required|string|min:8',
            'main_facility_name' => 'nullable|string|max:255',
            'facility_type' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
        ]);

        $tenant = $action->execute($validated);

        return back()->with('success', "Hospital Organization '{$tenant->name}' provisioned successfully with Main Branch & Administrator credentials.");
    }

    public function updateTenant(Request $request, Tenant $tenant, UpdateTenantSubscriptionAction $action)
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403);

        $validated = $request->validate([
            'subscription_tier' => 'required|string|in:starter,growth,enterprise',
            'subscription_status' => 'required|string|in:active,trial,past_due,suspended,cancelled',
            'max_facilities' => 'required|integer|min:1|max:500',
            'max_users' => 'required|integer|min:1|max:5000',
            'storage_quota_mb' => 'required|integer|min:1024',
            'feature_flags' => 'nullable|array',
            'billing_cycle' => 'nullable|string|in:monthly,annually',
            'billing_contact_email' => 'nullable|email',
            'billing_contact_phone' => 'nullable|string',
        ]);

        $action->execute($tenant, $validated);

        return back()->with('success', "Subscription & quotas for '{$tenant->name}' updated successfully.");
    }

    public function toggleStatus(Request $request, Tenant $tenant, UpdateTenantSubscriptionAction $action)
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403);

        $newStatus = $tenant->isSuspended() ? 'active' : 'suspended';
        $action->execute($tenant, [
            'subscription_status' => $newStatus,
            'status' => $newStatus,
        ]);

        return back()->with('success', "Hospital Organization '{$tenant->name}' status changed to " . ucfirst($newStatus) . ".");
    }

    public function impersonate(Request $request, Tenant $tenant, User $user, ImpersonateTenantUserAction $action)
    {
        $superadmin = $request->user();
        $validated = $request->validate([
            'justification_reason' => 'required|string|min:10|max:1000',
        ]);

        $action->execute($superadmin, $tenant, $user, $validated['justification_reason']);

        return redirect()->route('dashboard')->with('success', "Active Support Impersonation: You are now viewing AfyaNova as {$user->first_name} {$user->last_name} ({$tenant->name}).");
    }

    public function exitImpersonation(ExitImpersonationAction $action)
    {
        $action->execute();

        return redirect()->route('superadmin.workspace')->with('success', 'Support session ended. Returned safely to Superadmin Platform Control Plane.');
    }

    public function syncDictionary(Request $request, SyncMasterDictionaryAction $action)
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403);

        $type = $request->input('dictionary_type', 'all');
        $tenantId = $request->input('tenant_id');

        $result = $action->execute($type, $tenantId);

        return back()->with('success', "Master Clinical Dictionary Synced: {$result['records_synced']} records broadcast across {$result['tenants_synced']} tenant databases.");
    }
}
