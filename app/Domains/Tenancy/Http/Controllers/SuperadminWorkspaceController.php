<?php

namespace App\Domains\Tenancy\Http\Controllers;

use App\Domains\Clinical\Models\LabTest;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Pharmacy\Models\MedicationFormulary;
use App\Domains\Tenancy\Actions\ExitImpersonationAction;
use App\Domains\Tenancy\Actions\ImpersonateTenantUserAction;
use App\Domains\Tenancy\Actions\ProvisionTenantAction;
use App\Domains\Tenancy\Actions\SyncMasterDictionaryAction;
use App\Domains\Tenancy\Actions\UpdateTenantSubscriptionAction;
use App\Domains\Tenancy\Models\Department;
use App\Domains\Tenancy\Models\Facility;
use App\Domains\Tenancy\Models\ImpersonationLog;
use App\Domains\Tenancy\Models\SubscriptionPlan;
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

        $tenants = Tenant::with([
            'facilities' => fn ($q) => $q->withoutGlobalScopes(),
            'users' => fn ($q) => $q->withoutGlobalScopes()->with(['roleAssignments' => fn ($ra) => $ra->withoutGlobalScopes()->with('role')]),
        ])
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
                    'facilities' => $t->facilities->map(fn ($f) => [
                        'id' => $f->id,
                        'name' => $f->name,
                        'code' => $f->code,
                        'type' => $f->type ?? 'Facility Branch',
                        'city' => $f->city ?? 'N/A',
                        'region' => $f->region ?? 'N/A',
                        'is_active' => (bool) $f->is_active,
                    ]),
                    'users' => $t->users->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => "{$u->first_name} {$u->last_name}",
                        'email' => $u->email,
                        'status' => $u->status,
                        'role' => $u->roleAssignments->first()?->role?->name ?? 'Staff User',
                    ]),
                ];
            });

        $recentLogs = ImpersonationLog::with([
            'superadmin' => fn ($q) => $q->withoutGlobalScopes(),
            'impersonatedUser' => fn ($q) => $q->withoutGlobalScopes(),
            'tenant',
        ])
            ->latest('started_at')
            ->take(50)
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

        $subscriptionPlans = SubscriptionPlan::orderBy('sort_order')->get();

        // Master Clinical Catalog Items for Live Explorer
        $masterMedicines = [
            ['name' => 'Amoxicillin 500mg Caps', 'generic_name' => 'Amoxicillin', 'category' => 'Antibacterial', 'form' => 'Capsule', 'strength' => '500mg', 'source' => 'WHO / MSD NEMLIT'],
            ['name' => 'Paracetamol 500mg Tabs', 'generic_name' => 'Paracetamol', 'category' => 'Analgesics / Antipyretics', 'form' => 'Tablet', 'strength' => '500mg', 'source' => 'WHO / MSD NEMLIT'],
            ['name' => 'Artemether / Lumefantrine (ALu) 20/120mg', 'generic_name' => 'Artemether + Lumefantrine', 'category' => 'Antimalarials', 'form' => 'Tablet', 'strength' => '20/120mg', 'source' => 'MoH National Guideline'],
            ['name' => 'Metformin 500mg Tabs', 'generic_name' => 'Metformin HCl', 'category' => 'Oral Hypoglycaemic', 'form' => 'Tablet', 'strength' => '500mg', 'source' => 'WHO / MSD NEMLIT'],
            ['name' => 'Amlodipine 5mg Tabs', 'generic_name' => 'Amlodipine Besylate', 'category' => 'Antihypertensives', 'form' => 'Tablet', 'strength' => '5mg', 'source' => 'WHO / MSD NEMLIT'],
            ['name' => 'Ceftriaxone 1g Inj', 'generic_name' => 'Ceftriaxone Sodium', 'category' => 'Antibacterial', 'form' => 'Injection', 'strength' => '1g', 'source' => 'WHO / MSD NEMLIT'],
            ['name' => 'Oral Rehydration Salts (ORS) Sachet', 'generic_name' => 'ORS Formula', 'category' => 'Electrolyte Solution', 'form' => 'Powder', 'strength' => 'Standard', 'source' => 'WHO Essential'],
            ['name' => 'Zinc Sulfate 20mg Dispersible', 'generic_name' => 'Zinc Sulfate', 'category' => 'Mineral Supplements', 'form' => 'Dispersible Tablet', 'strength' => '20mg', 'source' => 'WHO Essential'],
            ['name' => 'Omeprazole 20mg Caps', 'generic_name' => 'Omeprazole', 'category' => 'Antiulcer / PPI', 'form' => 'Capsule', 'strength' => '20mg', 'source' => 'WHO / MSD NEMLIT'],
            ['name' => 'Azithromycin 500mg Tabs', 'generic_name' => 'Azithromycin', 'category' => 'Macrolide Antibacterial', 'form' => 'Tablet', 'strength' => '500mg', 'source' => 'WHO / MSD NEMLIT'],
            ['name' => 'Salbutamol Inhaler 100mcg', 'generic_name' => 'Salbutamol Sulfate', 'category' => 'Antiasthmatic', 'form' => 'Inhaler', 'strength' => '100mcg/dose', 'source' => 'WHO / MSD NEMLIT'],
            ['name' => 'Insulin Soluble (Human Regular) 100IU/ml', 'generic_name' => 'Insulin Regular', 'category' => 'Insulin & Antidiabetic', 'form' => 'Vial Inj', 'strength' => '100IU/ml', 'source' => 'WHO Essential'],
        ];

        $masterLabTests = [
            ['code' => 'LAB-MRDT', 'name' => 'Malaria Rapid Diagnostic Test (mRDT)', 'category' => 'Parasitology', 'turnaround' => 15, 'loinc' => '51567-6'],
            ['code' => 'LAB-FBP', 'name' => 'Full Blood Picture (FBP / CBC with Diff)', 'category' => 'Hematology', 'turnaround' => 30, 'loinc' => '58410-2'],
            ['code' => 'LAB-URI', 'name' => 'Urinalysis Multi-Stix & Microscopy', 'category' => 'Biochemistry', 'turnaround' => 20, 'loinc' => '24356-8'],
            ['code' => 'LAB-ABO', 'name' => 'Blood Grouping & Crossmatch (ABO / Rhesus)', 'category' => 'Immunology', 'turnaround' => 25, 'loinc' => '883-9'],
            ['code' => 'LAB-RBS', 'name' => 'Random Blood Glucose (RBS)', 'category' => 'Biochemistry', 'turnaround' => 10, 'loinc' => '2345-7'],
            ['code' => 'LAB-CREAT', 'name' => 'Serum Creatinine & eGFR', 'category' => 'Renal Profile', 'turnaround' => 45, 'loinc' => '2160-0'],
            ['code' => 'LAB-LFT', 'name' => 'Liver Function Tests (ALT, AST, ALP, Bilirubin)', 'category' => 'Hepatic Profile', 'turnaround' => 60, 'loinc' => '24325-3'],
            ['code' => 'LAB-LIPID', 'name' => 'Lipid Profile (Cholesterol, HDL, LDL, Triglycerides)', 'category' => 'Cardiovascular', 'turnaround' => 60, 'loinc' => '24331-1'],
            ['code' => 'LAB-HBA1C', 'name' => 'Glycated Hemoglobin (HbA1c)', 'category' => 'Endocrinology', 'turnaround' => 40, 'loinc' => '4548-4'],
            ['code' => 'LAB-TYPHOID', 'name' => 'Widal / Typhoid Antibody Agglutination', 'category' => 'Serology', 'turnaround' => 30, 'loinc' => '22587-0'],
        ];

        $masterDiagnoses = [
            ['code' => 'B54', 'name' => 'Unspecified malaria', 'chapter' => 'Infectious and parasitic diseases'],
            ['code' => 'I10', 'name' => 'Essential (primary) hypertension', 'chapter' => 'Diseases of the circulatory system'],
            ['code' => 'E11', 'name' => 'Type 2 diabetes mellitus', 'chapter' => 'Endocrine, nutritional and metabolic diseases'],
            ['code' => 'J06.9', 'name' => 'Acute upper respiratory infection, unspecified', 'chapter' => 'Diseases of the respiratory system'],
            ['code' => 'K29.7', 'name' => 'Gastritis, unspecified / Peptic Ulcer', 'chapter' => 'Diseases of the digestive system'],
            ['code' => 'N39.0', 'name' => 'Urinary tract infection, site not specified', 'chapter' => 'Diseases of the genitourinary system'],
            ['code' => 'A09', 'name' => 'Infectious gastroenteritis and colitis, unspecified', 'chapter' => 'Infectious and parasitic diseases'],
            ['code' => 'J18.9', 'name' => 'Pneumonia, unspecified organism', 'chapter' => 'Diseases of the respiratory system'],
        ];

        $masterCatalogs = [
            'medicines' => $masterMedicines,
            'lab_tests' => $masterLabTests,
            'diagnoses' => $masterDiagnoses,
            'total_medicines' => count($masterMedicines),
            'total_lab_tests' => count($masterLabTests),
            'total_diagnoses' => count($masterDiagnoses),
        ];

        return Inertia::render('Workspace/SuperadminWorkspace', [
            'telemetry' => $telemetry,
            'tenants' => $tenants,
            'subscriptionPlans' => $subscriptionPlans,
            'masterCatalogs' => $masterCatalogs,
            'recentLogs' => $recentLogs,
            'currentUser' => [
                'id' => $user->id,
                'name' => "{$user->first_name} {$user->last_name}",
                'email' => $user->email,
                'is_superadmin' => true,
            ],
        ]);
    }

    public function storePlan(Request $request)
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:subscription_plans,code',
            'description' => 'nullable|string',
            'price_monthly_tzs' => 'required|integer|min:0',
            'price_annual_tzs' => 'required|integer|min:0',
            'max_facilities' => 'required|integer|min:1|max:500',
            'max_users' => 'required|integer|min:1|max:5000',
            'storage_quota_mb' => 'required|integer|min:1024',
            'feature_flags' => 'required|array',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
        ]);

        $plan = SubscriptionPlan::create($validated);

        return back()->with('success', "New Plan Tier '{$plan->name}' created successfully.");
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price_monthly_tzs' => 'required|integer|min:0',
            'price_annual_tzs' => 'required|integer|min:0',
            'max_facilities' => 'required|integer|min:1|max:500',
            'max_users' => 'required|integer|min:1|max:5000',
            'storage_quota_mb' => 'required|integer|min:1024',
            'feature_flags' => 'required|array',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
        ]);

        $plan->update($validated);

        if ($request->boolean('propagate_to_tenants')) {
            $count = Tenant::where('subscription_tier', $plan->code)->update([
                'feature_flags' => $plan->feature_flags,
                'max_facilities' => $plan->max_facilities,
                'max_users' => $plan->max_users,
                'storage_quota_mb' => $plan->storage_quota_mb,
            ]);
            return back()->with('success', "Plan '{$plan->name}' blueprint updated and synced to {$count} active hospital tenants.");
        }

        return back()->with('success', "Plan '{$plan->name}' blueprint updated successfully.");
    }

    public function propagatePlanToTenants(Request $request, SubscriptionPlan $plan)
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403);

        $updatedCount = Tenant::where('subscription_tier', $plan->code)->update([
            'feature_flags' => $plan->feature_flags,
            'max_facilities' => $plan->max_facilities,
            'max_users' => $plan->max_users,
            'storage_quota_mb' => $plan->storage_quota_mb,
        ]);

        return back()->with('success', "Propagated plan features & quotas across {$updatedCount} hospital tenant organizations.");
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

    public function storeFacility(Request $request, Tenant $tenant)
    {
        $user = $request->user();
        abort_unless($this->authService->isSuperAdmin($user) || $this->authService->hasPermission($user, 'platform.superadmin.access'), 403);

        $currentCount = Facility::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        if ($currentCount >= $tenant->max_facilities) {
            return back()->withErrors([
                'name' => "Quota exceeded: '{$tenant->name}' has already reached its maximum branch quota ({$currentCount}/{$tenant->max_facilities}). Upgrade plan tier or adjust quota first.",
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'facility_type' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'physical_address' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ]);

        $code = $validated['code'] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $validated['name']), 0, 4)) . '-' . ($currentCount + 1);

        $previousTenantId = null;
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            $previousTenantId = \Illuminate\Support\Facades\DB::scalar("SELECT current_setting('app.current_tenant_id', true)");
            \Illuminate\Support\Facades\DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $tenant->id]);
        }

        try {
            $facility = Facility::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'code' => $code,
                'facility_type' => $validated['facility_type'],
                'city' => $validated['city'] ?? 'Dar es Salaam',
                'region' => $validated['region'] ?? 'Dar es Salaam',
                'physical_address' => $validated['physical_address'] ?? 'Hospital Street',
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'is_active' => true,
            ]);

            // Seed default clinical departments for this branch
            $standardDepts = [
                ['name' => 'Outpatient Department (OPD)', 'code' => 'OPD'],
                ['name' => 'Main Pharmacy', 'code' => 'PHARM'],
                ['name' => 'Clinical Pathology Laboratory', 'code' => 'LAB'],
                ['name' => 'Billing & Cashier Accounts', 'code' => 'BILL'],
            ];

            foreach ($standardDepts as $dept) {
                Department::create([
                    'tenant_id' => $tenant->id,
                    'facility_id' => $facility->id,
                    'name' => $dept['name'],
                    'code' => $dept['code'],
                    'is_clinical' => $dept['code'] !== 'BILL',
                    'is_active' => true,
                ]);
            }
        } finally {
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' && $previousTenantId !== null) {
                \Illuminate\Support\Facades\DB::statement('SELECT set_config(?, ?, false)', ['app.current_tenant_id', $previousTenantId]);
            }
        }

        return back()->with('success', "New facility branch '{$facility->name}' added to '{$tenant->name}' successfully.");
    }
}
