<?php

namespace App\Domains\Tenancy\Actions;

use App\Domains\Audit\Services\AuditLogger;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

class UpdateTenantSubscriptionAction
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function execute(Tenant $tenant, array $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data) {
            $before = $tenant->toArray();

            $tenant->update([
                'subscription_tier' => $data['subscription_tier'] ?? $tenant->subscription_tier,
                'plan' => $data['subscription_tier'] ?? $tenant->plan,
                'subscription_status' => $data['subscription_status'] ?? $tenant->subscription_status,
                'status' => in_array($data['subscription_status'] ?? '', ['suspended', 'cancelled']) ? 'suspended' : 'active',
                'max_facilities' => $data['max_facilities'] ?? $tenant->max_facilities,
                'max_users' => $data['max_users'] ?? $tenant->max_users,
                'storage_quota_mb' => $data['storage_quota_mb'] ?? $tenant->storage_quota_mb,
                'feature_flags' => $data['feature_flags'] ?? $tenant->feature_flags,
                'billing_cycle' => $data['billing_cycle'] ?? $tenant->billing_cycle,
                'billing_contact_email' => $data['billing_contact_email'] ?? $tenant->billing_contact_email,
                'billing_contact_phone' => $data['billing_contact_phone'] ?? $tenant->billing_contact_phone,
            ]);

            $this->auditLogger->log(
                category: 'PLATFORM_SUPERADMIN',
                action: 'TENANT_SUBSCRIPTION_UPDATED',
                auditableType: Tenant::class,
                auditableId: $tenant->id,
                before: $before,
                after: $tenant->fresh()->toArray(),
                facilityId: null,
                tenantId: $tenant->id,
                justification: 'Superadmin subscription tier and entitlement update.'
            );

            return $tenant->fresh();
        });
    }
}
