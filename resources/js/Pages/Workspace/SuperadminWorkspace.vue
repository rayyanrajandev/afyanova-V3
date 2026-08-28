<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Globe,
    Building2,
    Shield,
    Activity,
    CreditCard,
    Users,
    Server,
    Database,
    Plus,
    Search,
    Edit3,
    Power,
    UserCheck,
    CheckCircle2,
    AlertTriangle,
    RefreshCw,
    Layers,
    FileText,
    ExternalLink,
    Clock,
    Lock,
    KeyRound,
    Cpu,
    ArrowRight,
    Sliders,
    Sparkles,
    BookOpen
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    telemetry: { type: Object, required: true },
    tenants: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
    currentUser: { type: Object, required: true },
});

const { contextState, openContext } = useWorkspacePreferences('superadmin');

const activeTab = ref('tenants'); // telemetry, tenants, subscriptions, dictionary, impersonation_logs
const searchQuery = ref('');
const filterTier = ref('all');
const filterStatus = ref('all');

const selectedTenant = ref(props.tenants[0] || null);

const inspectTenant = (tenant) => {
    selectedTenant.value = tenant;
    openContext();
};

const filteredTenants = computed(() => {
    return props.tenants.filter(t => {
        const matchesSearch = !searchQuery.value || 
            t.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            t.slug.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (t.domain && t.domain.toLowerCase().includes(searchQuery.value.toLowerCase()));
        
        const matchesTier = filterTier.value === 'all' || t.subscription_tier === filterTier.value;
        const matchesStatus = filterStatus.value === 'all' || t.subscription_status === filterStatus.value;

        return matchesSearch && matchesTier && matchesStatus;
    });
});

// Modals State
const showProvisionModal = ref(false);
const showEditSubscriptionModal = ref(false);
const showImpersonateModal = ref(false);
const showSyncModal = ref(false);

const tenantToEdit = ref(null);
const tenantToImpersonate = ref(null);

// Forms
const provisionForm = useForm({
    name: '',
    slug: '',
    domain: '',
    subscription_tier: 'growth',
    subscription_status: 'active',
    max_facilities: 5,
    max_users: 75,
    main_facility_name: '',
    facility_type: 'Regional Referral Hospital',
    city: 'Dar es Salaam',
    region: 'Dar es Salaam',
    admin_first_name: '',
    admin_last_name: '',
    admin_email: '',
    admin_phone: '',
    admin_password: '',
});

const submitProvision = () => {
    provisionForm.post(route('superadmin.tenants.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showProvisionModal.value = false;
            provisionForm.reset();
        },
    });
};

const editSubForm = useForm({
    subscription_tier: 'growth',
    subscription_status: 'active',
    max_facilities: 5,
    max_users: 50,
    storage_quota_mb: 10240,
    billing_cycle: 'monthly',
    billing_contact_email: '',
    billing_contact_phone: '',
    feature_flags: [],
});

const openEditSubscription = (tenant) => {
    tenantToEdit.value = tenant;
    editSubForm.subscription_tier = tenant.subscription_tier || 'growth';
    editSubForm.subscription_status = tenant.subscription_status || 'active';
    editSubForm.max_facilities = tenant.max_facilities || 5;
    editSubForm.max_users = tenant.max_users || 50;
    editSubForm.storage_quota_mb = tenant.storage_quota_mb || 10240;
    editSubForm.billing_cycle = tenant.billing_cycle || 'monthly';
    editSubForm.billing_contact_email = tenant.billing_contact_email || '';
    editSubForm.billing_contact_phone = tenant.billing_contact_phone || '';
    editSubForm.feature_flags = tenant.feature_flags || ['billing', 'pharmacy', 'laboratory', 'inpatient'];
    showEditSubscriptionModal.value = true;
};

const submitUpdateSubscription = () => {
    if (!tenantToEdit.value) return;
    editSubForm.put(route('superadmin.tenants.update', tenantToEdit.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditSubscriptionModal.value = false;
            tenantToEdit.value = null;
        },
    });
};

const toggleTenantStatus = (tenant) => {
    if (confirm(`Are you sure you want to change the status of ${tenant.name}?`)) {
        router.post(route('superadmin.tenants.toggle-status', tenant.id), {}, { preserveScroll: true });
    }
};

// Impersonation Form
const impersonateForm = useForm({
    user_id: '',
    justification_reason: '',
});

const openImpersonate = (tenant) => {
    tenantToImpersonate.value = tenant;
    impersonateForm.user_id = tenant.users?.[0]?.id || '';
    impersonateForm.justification_reason = '';
    showImpersonateModal.value = true;
};

const submitImpersonate = () => {
    if (!tenantToImpersonate.value || !impersonateForm.user_id) return;
    impersonateForm.post(route('superadmin.tenants.impersonate', {
        tenant: tenantToImpersonate.value.id,
        user: impersonateForm.user_id,
    }));
};

// Master Dictionary Sync Form
const syncForm = useForm({
    dictionary_type: 'all',
    tenant_id: '',
});

const submitSyncDictionary = () => {
    syncForm.post(route('superadmin.sync-dictionary'), {
        preserveScroll: true,
        onSuccess: () => {
            showSyncModal.value = false;
        },
    });
};

const allAvailableFeatures = [
    { key: 'billing', label: 'Billing & POS Cashier' },
    { key: 'pharmacy', label: 'Pharmacy & FEFO Batching' },
    { key: 'laboratory', label: 'Laboratory Diagnostics & QC' },
    { key: 'inpatient', label: 'Inpatient Wards & Midnight Billing' },
    { key: 'theatre', label: 'Operating Theatre & PACU Aldrete' },
    { key: 'insurance', label: 'Insurance & NHIF Claim Tariffs' },
    { key: 'radiology', label: 'Radiology & Imaging Reports' },
    { key: 'dicom', label: 'DICOM PACS Medical Viewport' },
    { key: 'fhir', label: 'HL7 FHIR R4 Interoperability' },
    { key: 'mpesa', label: 'M-Pesa Daraja STK Push & Webhooks' },
    { key: 'sms', label: 'Beem / NextSMS Notification Gateway' },
    { key: 'bi_analytics', label: 'MoH MTUHA & BI Analytics' },
];

const toggleFeature = (key) => {
    const idx = editSubForm.feature_flags.indexOf(key);
    if (idx === -1) {
        editSubForm.feature_flags.push(key);
    } else {
        editSubForm.feature_flags.splice(idx, 1);
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-TZ', { style: 'currency', currency: 'TZS', maximumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Superadmin Platform Control Plane - AfyaNova SaaS" />

    <AfyaShell active-module="superadmin">
        <AfyaWorkspace workspace-id="superadmin">
            
            <!-- Left Workspace Sidebar -->
            <AfyaSidebar title="Platform Control" subtitle="Multi-Tenant SaaS">
                <div class="px-3 py-2 text-[11px] font-bold text-muted-foreground uppercase tracking-wider">
                    SaaS Management
                </div>
                <AfyaSidebarItem
                    label="Hospital Tenants"
                    :icon="Building2"
                    :badge="tenants.length.toString()"
                    :active="activeTab === 'tenants'"
                    @click="activeTab = 'tenants'"
                />
                <AfyaSidebarItem
                    label="Platform Telemetry"
                    :icon="Activity"
                    :active="activeTab === 'telemetry'"
                    @click="activeTab = 'telemetry'"
                />
                <AfyaSidebarItem
                    label="Plans & Feature Flags"
                    :icon="CreditCard"
                    :active="activeTab === 'subscriptions'"
                    @click="activeTab = 'subscriptions'"
                />
                <AfyaSidebarItem
                    label="Master Data Sync"
                    :icon="BookOpen"
                    :active="activeTab === 'dictionary'"
                    @click="activeTab = 'dictionary'"
                />
                <AfyaSidebarItem
                    label="Impersonation Audit"
                    :icon="Shield"
                    :badge="recentLogs.length.toString()"
                    :active="activeTab === 'impersonation_logs'"
                    @click="activeTab = 'impersonation_logs'"
                />

                <div class="mt-auto p-3 border-t border-border bg-card/40 space-y-2">
                    <div class="flex items-center gap-2 text-xs">
                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="font-medium text-foreground">SaaS Platform Operational</span>
                    </div>
                    <p class="text-[11px] text-muted-foreground">PostgreSQL RLS Multi-Tenant Mode</p>
                </div>
            </AfyaSidebar>

            <!-- Main Superadmin Console -->
            <AfyaWorkspaceMain>
                <div class="p-6 space-y-6 max-w-7xl mx-auto">
                    
                    <!-- Top Action Bar -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border pb-5">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/10 text-purple-600 border border-purple-500/20">
                                    SaaS Superadmin
                                </span>
                                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                                    Platform Control Center
                                </h1>
                            </div>
                            <p class="text-sm text-muted-foreground mt-1">
                                Global organization provisioning, subscription quotas, and audited multi-tenant support.
                            </p>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <Button variant="outline" size="sm" @click="showSyncModal = true" class="flex items-center gap-1.5 shadow-xs">
                                <BookOpen class="w-4 h-4 text-primary" />
                                Sync Master Dictionaries
                            </Button>
                            <Button size="sm" @click="showProvisionModal = true" class="flex items-center gap-1.5 shadow-xs bg-purple-600 hover:bg-purple-700 text-white">
                                <Plus class="w-4 h-4" />
                                Provision Organization
                            </Button>
                        </div>
                    </div>

                    <!-- TAB 1: HOSPITAL TENANTS DIRECTORY -->
                    <div v-if="activeTab === 'tenants'" class="space-y-4">
                        <!-- Filters -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-card p-3 rounded-lg border border-border">
                            <div class="relative w-full sm:w-80">
                                <Search class="w-4 h-4 absolute left-3 top-2.5 text-muted-foreground" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search hospital organization, slug, or domain..."
                                    class="w-full pl-9 pr-3 py-1.5 text-xs rounded-md border border-input bg-background focus:ring-1 focus:ring-primary focus:outline-hidden"
                                />
                            </div>
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <select v-model="filterTier" class="text-xs py-1.5 px-2.5 rounded-md border border-input bg-background focus:ring-1 focus:ring-primary">
                                    <option value="all">All Plan Tiers</option>
                                    <option value="starter">Starter Plan</option>
                                    <option value="growth">Growth Plan</option>
                                    <option value="enterprise">Enterprise Plan</option>
                                </select>
                                <select v-model="filterStatus" class="text-xs py-1.5 px-2.5 rounded-md border border-input bg-background focus:ring-1 focus:ring-primary">
                                    <option value="all">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="trial">Trial</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tenants Table -->
                        <div class="border border-border rounded-lg bg-card overflow-hidden shadow-xs">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Hospital Organization</TableHead>
                                        <TableHead>Plan Tier</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Facilities Quota</TableHead>
                                        <TableHead>Staff Users Quota</TableHead>
                                        <TableHead>Registered</TableHead>
                                        <TableHead class="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="tenant in filteredTenants"
                                        :key="tenant.id"
                                        @click="inspectTenant(tenant)"
                                        class="cursor-pointer hover:bg-muted/40 transition"
                                    >
                                        <TableCell>
                                            <div class="font-bold text-foreground">{{ tenant.name }}</div>
                                            <div class="text-[11px] text-muted-foreground font-mono">{{ tenant.slug }} • {{ tenant.domain || 'default' }}</div>
                                        </TableCell>
                                        <TableCell>
                                            <span 
                                                class="px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider"
                                                :class="{
                                                    'bg-blue-500/10 text-blue-600 border border-blue-500/20': tenant.subscription_tier === 'starter',
                                                    'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20': tenant.subscription_tier === 'growth',
                                                    'bg-purple-500/10 text-purple-600 border border-purple-500/20': tenant.subscription_tier === 'enterprise',
                                                }"
                                            >
                                                {{ tenant.subscription_tier }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <AfyaStatusBadge :status="tenant.subscription_status || tenant.status" />
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <Building2 class="w-3.5 h-3.5 text-muted-foreground" />
                                                <span class="font-semibold">{{ tenant.facilities_count }}</span>
                                                <span class="text-muted-foreground">/ {{ tenant.max_facilities }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <Users class="w-3.5 h-3.5 text-muted-foreground" />
                                                <span class="font-semibold">{{ tenant.users_count }}</span>
                                                <span class="text-muted-foreground">/ {{ tenant.max_users }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-xs text-muted-foreground">
                                            {{ formatDate(tenant.created_at) }}
                                        </TableCell>
                                        <TableCell class="text-right" @click.stop>
                                            <div class="flex items-center justify-end gap-1.5">
                                                <Button
                                                    size="xs"
                                                    variant="outline"
                                                    @click="openImpersonate(tenant)"
                                                    class="text-purple-600 border-purple-200 dark:border-purple-800 hover:bg-purple-50 dark:hover:bg-purple-950/40"
                                                    title="Audited Support Impersonation"
                                                >
                                                    <UserCheck class="w-3.5 h-3.5 mr-1" />
                                                    Login-As
                                                </Button>
                                                <Button
                                                    size="xs"
                                                    variant="outline"
                                                    @click="openEditSubscription(tenant)"
                                                    title="Edit Plan & Quotas"
                                                >
                                                    <Sliders class="w-3.5 h-3.5" />
                                                </Button>
                                                <Button
                                                    size="xs"
                                                    variant="ghost"
                                                    @click="toggleTenantStatus(tenant)"
                                                    :title="tenant.subscription_status === 'suspended' ? 'Activate Tenant' : 'Suspend Tenant'"
                                                    :class="tenant.subscription_status === 'suspended' ? 'text-emerald-600' : 'text-amber-600'"
                                                >
                                                    <Power class="w-3.5 h-3.5" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="filteredTenants.length === 0">
                                        <TableCell colspan="7" class="text-center py-8 text-muted-foreground">
                                            No hospital organizations found matching criteria.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <!-- TAB 2: PLATFORM TELEMETRY & HEALTH -->
                    <div v-else-if="activeTab === 'telemetry'" class="space-y-6">
                        <!-- KPI Metric Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="bg-card border border-border p-4 rounded-xl shadow-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground uppercase">Hospital Organizations</span>
                                    <Building2 class="w-4 h-4 text-purple-600" />
                                </div>
                                <div class="text-2xl font-bold text-foreground mt-2">{{ telemetry.tenants.total }}</div>
                                <div class="text-[11px] text-emerald-600 flex items-center gap-1 mt-1 font-medium">
                                    <span>{{ telemetry.tenants.active }} active</span>
                                    <span>•</span>
                                    <span class="text-blue-600">{{ telemetry.tenants.trial }} trial</span>
                                </div>
                            </div>

                            <div class="bg-card border border-border p-4 rounded-xl shadow-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground uppercase">Clinical Branches & Beds</span>
                                    <Layers class="w-4 h-4 text-blue-600" />
                                </div>
                                <div class="text-2xl font-bold text-foreground mt-2">{{ telemetry.infrastructure.total_facilities }} Facilities</div>
                                <div class="text-[11px] text-muted-foreground mt-1">
                                    {{ telemetry.infrastructure.total_beds }} inpatient beds configured
                                </div>
                            </div>

                            <div class="bg-card border border-border p-4 rounded-xl shadow-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground uppercase">Daily Encounters</span>
                                    <Activity class="w-4 h-4 text-emerald-600" />
                                </div>
                                <div class="text-2xl font-bold text-foreground mt-2">{{ telemetry.throughput.encounters_today }}</div>
                                <div class="text-[11px] text-muted-foreground mt-1">
                                    {{ telemetry.throughput.total_encounters }} all-time clinical visits
                                </div>
                            </div>

                            <div class="bg-card border border-border p-4 rounded-xl shadow-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-muted-foreground uppercase">Cross-Hospital GMV</span>
                                    <CreditCard class="w-4 h-4 text-amber-600" />
                                </div>
                                <div class="text-xl font-bold text-foreground mt-2 truncate">{{ formatCurrency(telemetry.throughput.total_collected_tzs) }}</div>
                                <div class="text-[11px] text-muted-foreground mt-1 truncate">
                                    Total Billed: {{ formatCurrency(telemetry.throughput.total_billed_tzs) }}
                                </div>
                            </div>
                        </div>

                        <!-- System Health Box -->
                        <div class="bg-card border border-border p-5 rounded-xl shadow-xs space-y-4">
                            <h3 class="font-bold text-sm text-foreground flex items-center gap-2">
                                <Server class="w-4 h-4 text-primary" />
                                Platform Core Infrastructure & Runtime
                            </h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                                <div class="p-3 bg-muted/30 rounded-lg border border-border">
                                    <div class="text-muted-foreground">PHP Runtime</div>
                                    <div class="font-mono font-bold text-foreground mt-1">{{ telemetry.system_health.php_version }}</div>
                                </div>
                                <div class="p-3 bg-muted/30 rounded-lg border border-border">
                                    <div class="text-muted-foreground">PostgreSQL Database Size</div>
                                    <div class="font-mono font-bold text-foreground mt-1">{{ telemetry.system_health.database_size }}</div>
                                </div>
                                <div class="p-3 bg-muted/30 rounded-lg border border-border">
                                    <div class="text-muted-foreground">Queue & Background Worker</div>
                                    <div class="font-bold text-emerald-600 mt-1 flex items-center gap-1">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                        Active ({{ telemetry.system_health.queue_backlog }} backlog)
                                    </div>
                                </div>
                                <div class="p-3 bg-muted/30 rounded-lg border border-border">
                                    <div class="text-muted-foreground">Environment</div>
                                    <div class="font-mono font-bold text-foreground mt-1 uppercase">{{ telemetry.system_health.server_environment }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: PLANS & FEATURE FLAGS MATRIX -->
                    <div v-else-if="activeTab === 'subscriptions'" class="space-y-4">
                        <div class="border border-border rounded-lg bg-card overflow-hidden shadow-xs">
                            <div class="p-4 border-b border-border bg-muted/20">
                                <h3 class="font-bold text-sm text-foreground">Multi-Tenant Modular Entitlements</h3>
                                <p class="text-xs text-muted-foreground">Feature flag status across hospital organizations</p>
                            </div>
                            <div class="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Organization</TableHead>
                                            <TableHead>Tier</TableHead>
                                            <TableHead class="text-center">Wards</TableHead>
                                            <TableHead class="text-center">Theatre</TableHead>
                                            <TableHead class="text-center">Lab & Pharmacy</TableHead>
                                            <TableHead class="text-center">Insurance</TableHead>
                                            <TableHead class="text-center">DICOM PACS</TableHead>
                                            <TableHead class="text-center">FHIR R4</TableHead>
                                            <TableHead class="text-center">M-Pesa STK</TableHead>
                                            <TableHead class="text-center">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="t in tenants" :key="t.id">
                                            <TableCell class="font-bold text-foreground text-xs">{{ t.name }}</TableCell>
                                            <TableCell>
                                                <span class="text-[11px] font-mono uppercase">{{ t.subscription_tier }}</span>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <CheckCircle2 v-if="(t.feature_flags || []).includes('inpatient')" class="w-4 h-4 text-emerald-500 mx-auto" />
                                                <span v-else class="text-muted-foreground text-xs">—</span>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <CheckCircle2 v-if="(t.feature_flags || []).includes('theatre')" class="w-4 h-4 text-emerald-500 mx-auto" />
                                                <span v-else class="text-muted-foreground text-xs">—</span>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <CheckCircle2 v-if="(t.feature_flags || []).includes('laboratory')" class="w-4 h-4 text-emerald-500 mx-auto" />
                                                <span v-else class="text-muted-foreground text-xs">—</span>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <CheckCircle2 v-if="(t.feature_flags || []).includes('insurance')" class="w-4 h-4 text-emerald-500 mx-auto" />
                                                <span v-else class="text-muted-foreground text-xs">—</span>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <CheckCircle2 v-if="(t.feature_flags || []).includes('dicom')" class="w-4 h-4 text-emerald-500 mx-auto" />
                                                <span v-else class="text-muted-foreground text-xs">—</span>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <CheckCircle2 v-if="(t.feature_flags || []).includes('fhir')" class="w-4 h-4 text-emerald-500 mx-auto" />
                                                <span v-else class="text-muted-foreground text-xs">—</span>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <CheckCircle2 v-if="(t.feature_flags || []).includes('mpesa')" class="w-4 h-4 text-emerald-500 mx-auto" />
                                                <span v-else class="text-muted-foreground text-xs">—</span>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <Button size="xs" variant="outline" @click="openEditSubscription(t)">Configure</Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: MASTER DICTIONARY SYNC -->
                    <div v-else-if="activeTab === 'dictionary'" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-card border border-border p-5 rounded-xl shadow-xs space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-600">
                                        <BookOpen class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-foreground">Tanzania MSD NEMLIT Drug Formulary</h4>
                                        <p class="text-xs text-muted-foreground">National Essential Medicines List standard items & strengths</p>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Broadcasts standard generic and brand essential medicines (Amoxicillin, ALu, Paracetamol, Metformin, ORS) across all hospital tenant pharmacy formulary catalogs.
                                </p>
                                <Button size="sm" @click="submitSyncDictionary" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white">
                                    Broadcast NEMLIT Formulary
                                </Button>
                            </div>

                            <div class="bg-card border border-border p-5 rounded-xl shadow-xs space-y-3">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-blue-500/10 text-blue-600">
                                        <Activity class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-foreground">Standard Clinical Laboratory LOINC Tests</h4>
                                        <p class="text-xs text-muted-foreground">Standardized diagnostic test battery and turnaround benchmarks</p>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Synchronizes standard laboratory tests (mRDT, FBP, Urinalysis, ABO blood grouping, RBS, Serum Creatinine) with statutory turnaround times.
                                </p>
                                <Button size="sm" variant="outline" @click="submitSyncDictionary" class="w-full">
                                    Broadcast LOINC Lab Catalog
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: IMPERSONATION AUDIT LOGS -->
                    <div v-else-if="activeTab === 'impersonation_logs'" class="space-y-4">
                        <div class="border border-border rounded-lg bg-card overflow-hidden shadow-xs">
                            <div class="p-4 border-b border-border bg-muted/20 flex items-center justify-between">
                                <div>
                                    <h3 class="font-bold text-sm text-foreground flex items-center gap-2">
                                        <Shield class="w-4 h-4 text-purple-600" />
                                        Cryptographic Impersonation & Support Audit Trail
                                    </h3>
                                    <p class="text-xs text-muted-foreground">Every superadmin support login-as event is permanently recorded for regulatory compliance.</p>
                                </div>
                            </div>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Superadmin</TableHead>
                                        <TableHead>Masqueraded As</TableHead>
                                        <TableHead>Target Hospital</TableHead>
                                        <TableHead>Legal / Technical Justification</TableHead>
                                        <TableHead>Started At</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="log in recentLogs" :key="log.id">
                                        <TableCell class="font-medium text-xs text-foreground">
                                            {{ log.superadmin_name }}
                                            <div class="text-[10px] text-muted-foreground">{{ log.ip_address }}</div>
                                        </TableCell>
                                        <TableCell class="font-semibold text-xs text-purple-600">
                                            {{ log.target_user_name }}
                                            <div class="text-[10px] text-muted-foreground">{{ log.target_user_email }}</div>
                                        </TableCell>
                                        <TableCell class="text-xs text-foreground font-medium">{{ log.target_tenant_name }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground max-w-xs truncate" :title="log.justification_reason">
                                            {{ log.justification_reason }}
                                        </TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ formatDate(log.started_at) }}</TableCell>
                                        <TableCell>
                                            <span 
                                                class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                :class="log.is_active ? 'bg-purple-500/10 text-purple-600 border border-purple-500/20' : 'bg-muted text-muted-foreground'"
                                            >
                                                {{ log.is_active ? 'ACTIVE SESSION' : 'CONCLUDED' }}
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="recentLogs.length === 0">
                                        <TableCell colspan="6" class="text-center py-8 text-muted-foreground text-xs">
                                            No superadmin impersonation sessions recorded.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                </div>
            </AfyaWorkspaceMain>

            <!-- Context Inspector Panel -->
            <AfyaContextPanel
                v-if="contextState.isOpen && selectedTenant"
                title="Organization Profile"
                :subtitle="selectedTenant.name"
            >
                <div class="p-4 space-y-5 text-xs">
                    <!-- Quick Info -->
                    <div class="space-y-2 bg-muted/30 p-3 rounded-lg border border-border">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Slug:</span>
                            <span class="font-mono font-bold">{{ selectedTenant.slug }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Domain:</span>
                            <span class="font-mono">{{ selectedTenant.domain || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Plan Tier:</span>
                            <span class="font-bold uppercase text-purple-600">{{ selectedTenant.subscription_tier }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Status:</span>
                            <AfyaStatusBadge :status="selectedTenant.subscription_status || selectedTenant.status" />
                        </div>
                    </div>

                    <!-- Quota Usage -->
                    <div class="space-y-3">
                        <h4 class="font-bold text-foreground uppercase tracking-wider text-[11px]">Resource Quotas</h4>
                        <div class="space-y-1">
                            <div class="flex justify-between text-[11px]">
                                <span class="text-muted-foreground">Facilities</span>
                                <span class="font-bold">{{ selectedTenant.facilities_count }} / {{ selectedTenant.max_facilities }}</span>
                            </div>
                            <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden">
                                <div class="bg-primary h-1.5 rounded-full" :style="{ width: `${Math.min(100, (selectedTenant.facilities_count / selectedTenant.max_facilities) * 100)}%` }"></div>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <div class="flex justify-between text-[11px]">
                                <span class="text-muted-foreground">Staff User Accounts</span>
                                <span class="font-bold">{{ selectedTenant.users_count }} / {{ selectedTenant.max_users }}</span>
                            </div>
                            <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden">
                                <div class="bg-purple-600 h-1.5 rounded-full" :style="{ width: `${Math.min(100, (selectedTenant.users_count / selectedTenant.max_users) * 100)}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Enabled Modules -->
                    <div class="space-y-2">
                        <h4 class="font-bold text-foreground uppercase tracking-wider text-[11px]">Active Modules</h4>
                        <div class="flex flex-wrap gap-1">
                            <span 
                                v-for="flag in (selectedTenant.feature_flags || [])" 
                                :key="flag"
                                class="px-2 py-0.5 bg-muted rounded text-[10px] font-mono font-medium text-foreground"
                            >
                                {{ flag }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2 pt-2">
                        <Button 
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white flex items-center justify-center gap-1.5"
                            size="sm"
                            @click="openImpersonate(selectedTenant)"
                        >
                            <UserCheck class="w-4 h-4" />
                            Audited Login-As Support
                        </Button>
                        <Button 
                            variant="outline"
                            class="w-full"
                            size="sm"
                            @click="openEditSubscription(selectedTenant)"
                        >
                            <Sliders class="w-4 h-4 mr-1.5" />
                            Manage Subscription & Quotas
                        </Button>
                    </div>
                </div>
            </AfyaContextPanel>

        </AfyaWorkspace>
    </AfyaShell>

    <!-- MODAL 1: PROVISION NEW HOSPITAL ORGANIZATION -->
    <Modal :show="showProvisionModal" @close="showProvisionModal = false" max-width="2xl">
        <form @submit.prevent="submitProvision" class="p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div class="flex items-center gap-2">
                    <div class="p-2 bg-purple-500/10 text-purple-600 rounded-lg">
                        <Building2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-foreground">Provision New Hospital Organization</h3>
                        <p class="text-xs text-muted-foreground">Automated multi-tenant setup, primary branch & administrator credentials.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="sm:col-span-2">
                    <label class="font-bold block mb-1">Organization / Hospital Name *</label>
                    <Input v-model="provisionForm.name" placeholder="e.g. Arusha Regional Referral Hospital" required />
                    <InputError :message="provisionForm.errors.name" />
                </div>

                <div>
                    <label class="font-bold block mb-1">Subdomain Slug *</label>
                    <Input v-model="provisionForm.slug" placeholder="e.g. arusha-referral" />
                    <InputError :message="provisionForm.errors.slug" />
                </div>

                <div>
                    <label class="font-bold block mb-1">Subscription Plan Tier *</label>
                    <select v-model="provisionForm.subscription_tier" class="w-full text-xs py-2 px-3 rounded-md border border-input bg-background focus:ring-1 focus:ring-primary">
                        <option value="starter">Starter Plan (1 Facility, 15 Users)</option>
                        <option value="growth">Growth Plan (5 Facilities, 75 Users)</option>
                        <option value="enterprise">Enterprise Plan (50 Facilities, 500 Users)</option>
                    </select>
                </div>

                <div class="sm:col-span-2 border-t border-border pt-3">
                    <h4 class="font-bold text-xs uppercase text-muted-foreground mb-2">Initial Administrator Credentials</h4>
                </div>

                <div>
                    <label class="font-bold block mb-1">Admin First Name *</label>
                    <Input v-model="provisionForm.admin_first_name" placeholder="e.g. Dkt. Joseph" required />
                </div>

                <div>
                    <label class="font-bold block mb-1">Admin Last Name *</label>
                    <Input v-model="provisionForm.admin_last_name" placeholder="e.g. Mrosso" required />
                </div>

                <div>
                    <label class="font-bold block mb-1">Admin Email Address *</label>
                    <Input v-model="provisionForm.admin_email" type="email" placeholder="admin@arushahospital.go.tz" required />
                    <InputError :message="provisionForm.errors.admin_email" />
                </div>

                <div>
                    <label class="font-bold block mb-1">Temporary Password *</label>
                    <Input v-model="provisionForm.admin_password" type="password" placeholder="Password123!" required />
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" @click="showProvisionModal = false">Cancel</Button>
                <Button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white" :disabled="provisionForm.processing">
                    Provision Organization Now
                </Button>
            </div>
        </form>
    </Modal>

    <!-- MODAL 2: EDIT SUBSCRIPTION & QUOTAS -->
    <Modal :show="showEditSubscriptionModal" @close="showEditSubscriptionModal = false" max-width="xl">
        <form @submit.prevent="submitUpdateSubscription" class="p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <h3 class="font-bold text-base text-foreground">Edit Subscription & Quotas</h3>
                    <p class="text-xs text-muted-foreground">{{ tenantToEdit?.name }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="font-bold block mb-1">Plan Tier</label>
                    <select v-model="editSubForm.subscription_tier" class="w-full text-xs py-2 px-3 rounded-md border border-input bg-background focus:ring-1 focus:ring-primary">
                        <option value="starter">Starter</option>
                        <option value="growth">Growth</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>

                <div>
                    <label class="font-bold block mb-1">Status</label>
                    <select v-model="editSubForm.subscription_status" class="w-full text-xs py-2 px-3 rounded-md border border-input bg-background focus:ring-1 focus:ring-primary">
                        <option value="active">Active</option>
                        <option value="trial">Trial</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <div>
                    <label class="font-bold block mb-1">Max Facilities</label>
                    <Input v-model.number="editSubForm.max_facilities" type="number" min="1" max="200" required />
                </div>

                <div>
                    <label class="font-bold block mb-1">Max Staff Users</label>
                    <Input v-model.number="editSubForm.max_users" type="number" min="1" max="2000" required />
                </div>

                <div class="sm:col-span-2">
                    <label class="font-bold block mb-2">Modular Feature Flags</label>
                    <div class="grid grid-cols-2 gap-2 bg-muted/20 p-3 rounded-lg border border-border">
                        <label 
                            v-for="feat in allAvailableFeatures" 
                            :key="feat.key"
                            class="flex items-center gap-2 cursor-pointer text-xs"
                        >
                            <input 
                                type="checkbox" 
                                :checked="editSubForm.feature_flags.includes(feat.key)"
                                @change="toggleFeature(feat.key)"
                                class="rounded border-input text-primary focus:ring-primary"
                            />
                            <span>{{ feat.label }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" @click="showEditSubscriptionModal = false">Cancel</Button>
                <Button type="submit" class="bg-primary text-primary-foreground" :disabled="editSubForm.processing">
                    Save Subscription
                </Button>
            </div>
        </form>
    </Modal>

    <!-- MODAL 3: AUDITED SUPPORT IMPERSONATION ("LOGIN-AS") -->
    <Modal :show="showImpersonateModal" @close="showImpersonateModal = false" max-width="lg">
        <form @submit.prevent="submitImpersonate" class="p-6 space-y-4">
            <div class="flex items-center gap-3 border-b border-border pb-3">
                <div class="p-2 bg-purple-500/10 text-purple-600 rounded-lg">
                    <UserCheck class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="font-bold text-base text-foreground">Initiate Audited Support Impersonation</h3>
                    <p class="text-xs text-muted-foreground">{{ tenantToImpersonate?.name }}</p>
                </div>
            </div>

            <div class="p-3 bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 rounded-lg text-xs leading-relaxed">
                <div class="font-bold mb-1 flex items-center gap-1.5">
                    <AlertTriangle class="w-4 h-4" />
                    Regulatory Compliance & Security Notice
                </div>
                Support sessions are cryptographically logged with your Superadmin ID, IP address, and stated justification. Clinical safety rules prevent non-clinician users from signing medical records.
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="font-bold block mb-1">Select User to Masquerade As *</label>
                    <select v-model="impersonateForm.user_id" class="w-full text-xs py-2 px-3 rounded-md border border-input bg-background focus:ring-1 focus:ring-primary" required>
                        <option v-for="u in tenantToImpersonate?.users || []" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.email }}) — {{ u.role }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="font-bold block mb-1">Mandatory Technical/Legal Justification Reason *</label>
                    <textarea 
                        v-model="impersonateForm.justification_reason" 
                        rows="3" 
                        placeholder="e.g. Diagnosing cashier split payment reconciliation error reported in Support Ticket #AFYA-492..."
                        class="w-full text-xs p-3 rounded-md border border-input bg-background focus:ring-1 focus:ring-primary focus:outline-hidden"
                        required
                    ></textarea>
                    <InputError :message="impersonateForm.errors.justification_reason" />
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" @click="showImpersonateModal = false">Cancel</Button>
                <Button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white flex items-center gap-1.5" :disabled="impersonateForm.processing">
                    <UserCheck class="w-4 h-4" />
                    Enter Support Session
                </Button>
            </div>
        </form>
    </Modal>

    <!-- MODAL 4: MASTER DICTIONARY BROADCAST -->
    <Modal :show="showSyncModal" @close="showSyncModal = false" max-width="md">
        <form @submit.prevent="submitSyncDictionary" class="p-6 space-y-4">
            <div class="flex items-center gap-3 border-b border-border pb-3">
                <div class="p-2 bg-emerald-500/10 text-emerald-600 rounded-lg">
                    <BookOpen class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="font-bold text-base text-foreground">Sync Master Clinical Dictionaries</h3>
                    <p class="text-xs text-muted-foreground">Broadcast standard medical data to hospital databases.</p>
                </div>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="font-bold block mb-1">Dictionary Target</label>
                    <select v-model="syncForm.dictionary_type" class="w-full text-xs py-2 px-3 rounded-md border border-input bg-background">
                        <option value="all">All Master Catalogs (NEMLIT Drugs + LOINC Lab)</option>
                        <option value="nemlit">Tanzania MSD NEMLIT Drug Formulary Only</option>
                        <option value="loinc">Clinical Laboratory LOINC Catalog Only</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" @click="showSyncModal = false">Cancel</Button>
                <Button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white" :disabled="syncForm.processing">
                    Execute Master Broadcast
                </Button>
            </div>
        </form>
    </Modal>
</template>
