<script setup>
import { ref, computed, watch, onMounted } from 'vue';
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
    BookOpen,
    HardDrive,
    Network,
    TrendingUp,
    ShieldCheck,
    Check,
    X,
    Filter,
    HelpCircle,
    LayoutDashboard,
    Zap,
    Radio,
    FlaskConical,
    Pill,
    Stethoscope,
    Share2,
    Save,
    RotateCcw,
    Trash2,
    FileEdit,
    FolderPlus,
    Loader2
} from 'lucide-vue-next';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import AfyaTablePagination from '@/Components/Afya/AfyaTablePagination.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    telemetry: { type: Object, required: true },
    tenants: { type: Array, default: () => [] },
    subscriptionPlans: { type: Array, default: () => [] },
    masterCatalogs: { type: Object, default: () => ({ medicines: [], lab_tests: [], diagnoses: [] }) },
    recentLogs: { type: Array, default: () => [] },
    currentUser: { type: Object, required: true },
});

const { preferences, openContext } = useWorkspacePreferences();

const activeTab = ref('tenants'); // tenants, telemetry, subscriptions, dictionary, impersonation_logs
const searchQuery = ref('');
const filterTier = ref('all');
const filterStatus = ref('all');

const selectedTenant = ref(props.tenants[0] || null);
const isReloading = ref(false);
const isPropagatingId = ref(null);

const reloadWorkspace = () => {
    isReloading.value = true;
    router.reload({
        onFinish: () => {
            isReloading.value = false;
        },
    });
};

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

const tenantsCurrentPage = ref(1);
const tenantsPerPage = ref(25);
const tenantsPerPageOptions = [10, 25, 50, 100];

watch([searchQuery, filterTier, filterStatus], () => {
    tenantsCurrentPage.value = 1;
});

const paginatedTenants = computed(() => {
    const start = (tenantsCurrentPage.value - 1) * tenantsPerPage.value;
    return filteredTenants.value.slice(start, start + tenantsPerPage.value);
});

// Modals State
const showProvisionModal = ref(false);
const showEditSubscriptionModal = ref(false);
const showImpersonateModal = ref(false);
const showSyncModal = ref(false);
const showPlanModal = ref(false);
const showAddFacilityModal = ref(false);
const isEditingPlan = ref(false);

const tenantToEdit = ref(null);
const tenantToImpersonate = ref(null);

// Master Catalogs Tab State
const catalogTab = ref('medicines'); // medicines, lab_tests, diagnoses
const catalogSearch = ref('');

const filteredMasterMedicines = computed(() => {
    const meds = props.masterCatalogs?.medicines || [];
    if (!catalogSearch.value) return meds;
    const q = catalogSearch.value.toLowerCase();
    return meds.filter(m => 
        m.name.toLowerCase().includes(q) || 
        m.generic_name.toLowerCase().includes(q) || 
        m.category.toLowerCase().includes(q)
    );
});

const filteredMasterLabTests = computed(() => {
    const tests = props.masterCatalogs?.lab_tests || [];
    if (!catalogSearch.value) return tests;
    const q = catalogSearch.value.toLowerCase();
    return tests.filter(t => 
        t.name.toLowerCase().includes(q) || 
        t.code.toLowerCase().includes(q) || 
        t.category.toLowerCase().includes(q)
    );
});

const filteredMasterDiagnoses = computed(() => {
    const diags = props.masterCatalogs?.diagnoses || [];
    if (!catalogSearch.value) return diags;
    const q = catalogSearch.value.toLowerCase();
    return diags.filter(d => 
        d.name.toLowerCase().includes(q) || 
        d.code.toLowerCase().includes(q) || 
        d.chapter.toLowerCase().includes(q)
    );
});

// ==========================================
// 📝 DRAFT MECHANISM: HOSPITAL PROVISIONING
// ==========================================
const PROVISION_DRAFT_KEY = 'afyanova_provision_hospital_draft';
const savedProvisionDraft = ref(null);
const draftLastSavedAt = ref(null);

const defaultProvisionData = {
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
};

const provisionForm = useForm({ ...defaultProvisionData });

const loadSavedProvisionDraft = () => {
    try {
        const raw = localStorage.getItem(PROVISION_DRAFT_KEY);
        if (raw) {
            savedProvisionDraft.value = JSON.parse(raw);
        } else {
            savedProvisionDraft.value = null;
        }
    } catch (e) {
        savedProvisionDraft.value = null;
    }
};

const saveProvisionDraft = () => {
    const draftPayload = {
        data: provisionForm.data(),
        updated_at: new Date().toISOString(),
    };
    localStorage.setItem(PROVISION_DRAFT_KEY, JSON.stringify(draftPayload));
    savedProvisionDraft.value = draftPayload;
    draftLastSavedAt.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const restoreProvisionDraft = () => {
    if (savedProvisionDraft.value?.data) {
        Object.assign(provisionForm, savedProvisionDraft.value.data);
    }
};

const discardProvisionDraft = () => {
    localStorage.removeItem(PROVISION_DRAFT_KEY);
    savedProvisionDraft.value = null;
    draftLastSavedAt.value = null;
    provisionForm.reset();
};

const openProvisionWizard = () => {
    loadSavedProvisionDraft();
    showProvisionModal.value = true;
};

// Auto-save draft when form changes (with debounce/watch)
watch(
    () => ({ ...provisionForm.data() }),
    (newVal) => {
        if (!showProvisionModal.value) return;
        // Only auto-save if at least name or email is partially typed
        if (newVal.name || newVal.admin_email || newVal.main_facility_name) {
            saveProvisionDraft();
        }
    },
    { deep: true }
);

const submitProvision = () => {
    provisionForm.post(route('superadmin.tenants.store'), {
        preserveScroll: true,
        onSuccess: () => {
            discardProvisionDraft();
            showProvisionModal.value = false;
        },
    });
};

// ==========================================
// 🏢 DRAFT MECHANISM: ADD FACILITY BRANCH
// ==========================================
const FACILITY_DRAFT_PREFIX = 'afyanova_facility_draft_';
const savedFacilityDraft = ref(null);
const facilityDraftSavedAt = ref(null);

const defaultFacilityData = {
    name: '',
    code: '',
    facility_type: 'Polyclinic',
    city: 'Dar es Salaam',
    region: 'Dar es Salaam',
    physical_address: '',
    contact_email: '',
    contact_phone: '',
};

const facilityForm = useForm({ ...defaultFacilityData });

const getFacilityDraftKey = () => {
    return selectedTenant.value ? `${FACILITY_DRAFT_PREFIX}${selectedTenant.value.id}` : null;
};

const loadSavedFacilityDraft = () => {
    const key = getFacilityDraftKey();
    if (!key) return;
    try {
        const raw = localStorage.getItem(key);
        if (raw) {
            savedFacilityDraft.value = JSON.parse(raw);
        } else {
            savedFacilityDraft.value = null;
        }
    } catch (e) {
        savedFacilityDraft.value = null;
    }
};

const saveFacilityDraft = () => {
    const key = getFacilityDraftKey();
    if (!key) return;
    const draftPayload = {
        data: facilityForm.data(),
        tenant_name: selectedTenant.value?.name,
        updated_at: new Date().toISOString(),
    };
    localStorage.setItem(key, JSON.stringify(draftPayload));
    savedFacilityDraft.value = draftPayload;
    facilityDraftSavedAt.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const restoreFacilityDraft = () => {
    if (savedFacilityDraft.value?.data) {
        Object.assign(facilityForm, savedFacilityDraft.value.data);
    }
};

const discardFacilityDraft = () => {
    const key = getFacilityDraftKey();
    if (key) localStorage.removeItem(key);
    savedFacilityDraft.value = null;
    facilityDraftSavedAt.value = null;
    facilityForm.reset();
};

const openAddFacilityModal = () => {
    if (!selectedTenant.value) return;
    loadSavedFacilityDraft();
    showAddFacilityModal.value = true;
};

watch(
    () => ({ ...facilityForm.data() }),
    (newVal) => {
        if (!showAddFacilityModal.value) return;
        if (newVal.name || newVal.physical_address || newVal.code) {
            saveFacilityDraft();
        }
    },
    { deep: true }
);

const submitAddFacility = () => {
    if (!selectedTenant.value) return;
    facilityForm.post(route('superadmin.tenants.facilities.store', selectedTenant.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            discardFacilityDraft();
            showAddFacilityModal.value = false;
        },
    });
};

onMounted(() => {
    loadSavedProvisionDraft();
});

// Edit Tenant Subscription Form
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
    editSubForm.feature_flags = [...(tenant.feature_flags || ['billing', 'pharmacy', 'laboratory', 'inpatient'])];
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

// Tenant Status Modal State
const showTenantStatusModal = ref(false);
const targetTenantForStatus = ref(null);
const isTogglingTenantStatus = ref(false);

const openTenantStatusModal = (tenant) => {
    targetTenantForStatus.value = tenant;
    showTenantStatusModal.value = true;
};

const confirmTenantStatusToggle = () => {
    if (!targetTenantForStatus.value) return;
    isTogglingTenantStatus.value = true;
    router.post(route('superadmin.tenants.toggle-status', targetTenantForStatus.value.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isTogglingTenantStatus.value = false;
            showTenantStatusModal.value = false;
            targetTenantForStatus.value = null;
        }
    });
};

const toggleTenantStatus = (tenant) => {
    openTenantStatusModal(tenant);
};

// Plan Blueprint Management Form
const planForm = useForm({
    id: '',
    name: '',
    code: '',
    description: '',
    price_monthly_tzs: 0,
    price_annual_tzs: 0,
    max_facilities: 1,
    max_users: 15,
    storage_quota_mb: 5120,
    feature_flags: [],
    is_active: true,
    is_popular: false,
    propagate_to_tenants: false,
});

const openEditPlan = (plan) => {
    isEditingPlan.value = true;
    planForm.id = plan.id;
    planForm.name = plan.name;
    planForm.code = plan.code;
    planForm.description = plan.description || '';
    planForm.price_monthly_tzs = plan.price_monthly_tzs;
    planForm.price_annual_tzs = plan.price_annual_tzs;
    planForm.max_facilities = plan.max_facilities;
    planForm.max_users = plan.max_users;
    planForm.storage_quota_mb = plan.storage_quota_mb;
    planForm.feature_flags = [...(plan.feature_flags || [])];
    planForm.is_active = plan.is_active;
    planForm.is_popular = plan.is_popular;
    planForm.propagate_to_tenants = false;
    showPlanModal.value = true;
};

const openCreatePlan = () => {
    isEditingPlan.value = false;
    planForm.id = '';
    planForm.name = '';
    planForm.code = '';
    planForm.description = '';
    planForm.price_monthly_tzs = 1500000;
    planForm.price_annual_tzs = 15000000;
    planForm.max_facilities = 2;
    planForm.max_users = 25;
    planForm.storage_quota_mb = 10240;
    planForm.feature_flags = ['billing', 'pharmacy', 'laboratory'];
    planForm.is_active = true;
    planForm.is_popular = false;
    planForm.propagate_to_tenants = false;
    showPlanModal.value = true;
};

const submitPlanForm = () => {
    if (isEditingPlan.value) {
        planForm.put(route('superadmin.plans.update', planForm.id), {
            preserveScroll: true,
            onSuccess: () => {
                showPlanModal.value = false;
            },
        });
    } else {
        planForm.post(route('superadmin.plans.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showPlanModal.value = false;
            },
        });
    }
};

const togglePlanFeature = (key) => {
    const idx = planForm.feature_flags.indexOf(key);
    if (idx === -1) {
        planForm.feature_flags.push(key);
    } else {
        planForm.feature_flags.splice(idx, 1);
    }
};

// Plan Propagation Modal State
const showPropagatePlanModal = ref(false);
const targetPlanForPropagate = ref(null);

const openPropagatePlanModal = (plan) => {
    targetPlanForPropagate.value = plan;
    showPropagatePlanModal.value = true;
};

const confirmPropagatePlan = () => {
    if (!targetPlanForPropagate.value) return;
    isPropagatingId.value = targetPlanForPropagate.value.id;
    router.post(route('superadmin.plans.propagate', targetPlanForPropagate.value.id), {}, { 
        preserveScroll: true,
        onFinish: () => {
            isPropagatingId.value = null;
            showPropagatePlanModal.value = false;
            targetPlanForPropagate.value = null;
        }
    });
};

const propagatePlan = (plan) => {
    openPropagatePlanModal(plan);
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
    { key: 'billing', label: 'Billing & POS Cashier Desk', category: 'Commercial' },
    { key: 'pharmacy', label: 'Pharmacy & FEFO Batch Dispensing', category: 'Clinical' },
    { key: 'laboratory', label: 'Laboratory Diagnostics & Specimen Tracking', category: 'Diagnostics' },
    { key: 'inpatient', label: 'Inpatient Wards & Midnight Census', category: 'Clinical' },
    { key: 'theatre', label: 'Operating Theatre & PACU Aldrete Scoring', category: 'Surgical' },
    { key: 'insurance', label: 'NHIF & Private Insurance Claim Tariffs', category: 'Commercial' },
    { key: 'radiology', label: 'Radiology & Imaging Worklists', category: 'Diagnostics' },
    { key: 'dicom', label: 'DICOM PACS Native Medical Viewport', category: 'Imaging' },
    { key: 'fhir', label: 'HL7 FHIR R4 Standardized Interoperability', category: 'Platform' },
    { key: 'mpesa', label: 'M-Pesa Daraja STK Push & Webhook Processing', category: 'Payments' },
    { key: 'sms', label: 'Beem / NextSMS Notification Gateway', category: 'Integrations' },
    { key: 'bi_analytics', label: 'MoH MTUHA & BI Analytics Suite', category: 'Analytics' },
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
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Platform Control -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Platform Control"
                    subtitle="Multi-Tenant SaaS"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-3 py-1.5 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        SaaS Operations
                    </div>
                    
                    <AfyaSidebarItem
                        label="Hospital Tenants"
                        :icon="Building2"
                        :badge="tenants.length.toString()"
                        :active="activeTab === 'tenants'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'tenants'"
                    />
                    <AfyaSidebarItem
                        label="Platform Telemetry"
                        :icon="Activity"
                        :active="activeTab === 'telemetry'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'telemetry'"
                    />
                    <AfyaSidebarItem
                        label="Plans & Feature Matrix"
                        :icon="CreditCard"
                        :badge="subscriptionPlans.length.toString()"
                        :active="activeTab === 'subscriptions'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'subscriptions'"
                    />
                    <AfyaSidebarItem
                        label="Master Clinical Data"
                        :icon="BookOpen"
                        :active="activeTab === 'dictionary'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'dictionary'"
                    />
                    <AfyaSidebarItem
                        label="Impersonation Audit"
                        :icon="Shield"
                        :badge="recentLogs.length.toString()"
                        :active="activeTab === 'impersonation_logs'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'impersonation_logs'"
                    />

                    <!-- Engine Status Card (Compact) -->
                    <div v-if="state !== 'collapsed'" class="mt-auto p-2.5 m-2 rounded-lg border border-border/80 bg-card/60 space-y-1 backdrop-blur-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-foreground">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                Platform Online
                            </div>
                            <span class="text-[9.5px] font-mono text-purple-600 font-bold bg-purple-500/10 px-1.5 py-0.2 rounded">v3.2</span>
                        </div>
                        <p class="text-[9.5px] text-muted-foreground leading-tight">PostgreSQL 16 RLS Multi-Tenant</p>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER PANEL: MAIN COMPACT WORKSPACE -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'SaaS Platform Control', href: route('superadmin.workspace') },
                        { label: activeTab === 'tenants' ? 'Hospital Fleet Directory' : (activeTab === 'telemetry' ? 'Platform Telemetry' : (activeTab === 'subscriptions' ? 'Plan Matrix & Feature Flags' : (activeTab === 'dictionary' ? 'Master Clinical Catalogs' : 'Impersonation Audit'))), active: true }
                    ]"
                >
                    <!-- TOP ACTIONS BAR -->
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <!-- Draft indicator chip if saved draft exists -->
                            <button
                                v-if="savedProvisionDraft"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-700 hover:bg-amber-100 transition-colors"
                                title="You have an unsaved hospital provisioning draft"
                                @click="openProvisionWizard"
                            >
                                <FileEdit class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                <span>Resume Draft ({{ savedProvisionDraft.data?.name || 'Untitled' }})</span>
                            </button>

                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-purple-50 text-purple-800 border border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800">
                                <ShieldCheck class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                                <span>RLS Strict Fleet Isolation</span>
                            </span>

                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 px-2.5 text-xs font-semibold gap-1 shadow-2xs text-purple-700 dark:text-purple-400 border-purple-300 dark:border-purple-700 hover:bg-purple-50 dark:hover:bg-purple-950/40"
                                @click="showSyncModal = true"
                            >
                                <BookOpen class="w-3 h-3" />
                                <span>Broadcast Dictionaries</span>
                            </Button>

                            <Button
                                size="sm"
                                class="h-7 px-2.5 text-xs font-bold gap-1 shadow-2xs bg-purple-600 hover:bg-purple-700 text-white"
                                @click="openProvisionWizard"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Provision Hospital Group</span>
                            </Button>

                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="h-7 px-2 text-xs font-semibold gap-1 bg-card shadow-2xs"
                                :disabled="isReloading"
                                @click="reloadWorkspace"
                            >
                                <RefreshCw class="w-3 h-3 text-muted-foreground" :class="{ 'animate-spin': isReloading }" />
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- HIGH-DENSITY 4-COLUMN PLATFORM KPI METRICS STRIP -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5">
                            <!-- Metric 1: Tenants -->
                            <div class="bg-card p-2.5 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Hospital Tenants</span>
                                    <Building2 class="w-3.5 h-3.5 text-purple-600" />
                                </div>
                                <div class="mt-1 text-lg font-extrabold font-mono text-foreground">
                                    {{ telemetry.tenants?.total || tenants.length }}
                                </div>
                                <div class="text-[9.5px] text-emerald-600 font-semibold mt-0.5">
                                    {{ telemetry.tenants?.active || 0 }} Active · {{ telemetry.tenants?.trial || 0 }} Trial
                                </div>
                            </div>

                            <!-- Metric 2: Facilities & Users -->
                            <div class="bg-card p-2.5 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Facilities & Users</span>
                                    <Layers class="w-3.5 h-3.5 text-blue-600" />
                                </div>
                                <div class="mt-1 text-lg font-extrabold font-mono text-foreground">
                                    {{ telemetry.infrastructure?.total_facilities || 0 }} <span class="text-xs font-normal text-muted-foreground">Branches</span>
                                </div>
                                <div class="text-[9.5px] text-muted-foreground mt-0.5">
                                    {{ telemetry.infrastructure?.total_users || 0 }} Provisioned Seats
                                </div>
                            </div>

                            <!-- Metric 3: Patient Encounters -->
                            <div class="bg-card p-2.5 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Clinical Encounters</span>
                                    <Activity class="w-3.5 h-3.5 text-emerald-600" />
                                </div>
                                <div class="mt-1 text-lg font-extrabold font-mono text-foreground">
                                    {{ telemetry.throughput?.encounters_today || 0 }} <span class="text-xs font-normal text-muted-foreground">Today</span>
                                </div>
                                <div class="text-[9.5px] text-muted-foreground mt-0.5">
                                    {{ (telemetry.infrastructure?.total_patients || 0).toLocaleString() }} Registered Patients
                                </div>
                            </div>

                            <!-- Metric 4: Revenue Gross (TZS) -->
                            <div class="bg-card p-2.5 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Gross Invoiced</span>
                                    <TrendingUp class="w-3.5 h-3.5 text-amber-600" />
                                </div>
                                <div class="mt-1 text-base font-extrabold font-mono text-foreground truncate">
                                    {{ formatCurrency(telemetry.throughput?.total_billed_tzs) }}
                                </div>
                                <div class="text-[9.5px] text-emerald-600 font-semibold mt-0.5 truncate">
                                    Paid: {{ formatCurrency(telemetry.throughput?.total_collected_tzs) }}
                                </div>
                            </div>
                        </div>

                        <!-- TAB 1: HOSPITAL TENANTS DIRECTORY -->
                        <div v-if="activeTab === 'tenants'" class="space-y-2.5">
                            <!-- Compact Search & Filters Toolbar -->
                            <div class="p-2 bg-card rounded-lg border border-border/60 shadow-2xs flex flex-wrap items-center justify-between gap-2">
                                <SearchInput
                                    v-model="searchQuery"
                                    placeholder="Search hospital by name, slug, or domain..."
                                    class="flex-1 min-w-[220px]"
                                    input-class="font-medium"
                                />

                                <div class="flex items-center gap-2">
                                    <Select 
                                        v-model="filterTier"
                                        class="h-8 min-w-[125px] pl-2.5 pr-7 rounded-md border border-border bg-background text-xs font-medium focus:ring-1 focus:ring-purple-500 cursor-pointer shadow-2xs"
                                    >
                                        <option value="all">All Tiers</option>
                                        <option value="starter">Starter</option>
                                        <option value="growth">Growth</option>
                                        <option value="enterprise">Enterprise</option>
                                    </Select>

                                    <Select 
                                        v-model="filterStatus"
                                        class="h-8 min-w-[130px] pl-2.5 pr-7 rounded-md border border-border bg-background text-xs font-medium focus:ring-1 focus:ring-purple-500 cursor-pointer shadow-2xs"
                                    >
                                        <option value="all">All Statuses</option>
                                        <option value="active">Active</option>
                                        <option value="trial">Trial</option>
                                        <option value="suspended">Suspended</option>
                                    </Select>
                                </div>
                            </div>

                            <!-- Master High-Density Tenants Table -->
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs border border-border/60 flex flex-col">
                                <div class="max-h-[580px] overflow-y-auto">
                                    <Table class="w-full text-xs">
                                        <TableHeader class="sticky top-0 bg-muted/95 backdrop-blur-xs z-10">
                                            <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground border-b border-border/40">
                                                <TableHead class="py-1 px-3">Hospital Organization</TableHead>
                                                <TableHead class="py-1 px-2.5">Plan Tier</TableHead>
                                                <TableHead class="py-1 px-2.5">Branches Quota</TableHead>
                                                <TableHead class="py-1 px-2.5">Staff Seats</TableHead>
                                                <TableHead class="py-1 px-2.5">Status</TableHead>
                                                <TableHead class="py-1 px-2.5">Created</TableHead>
                                                <TableHead class="py-1 px-3 text-right">Actions</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow
                                                v-for="tenant in paginatedTenants"
                                                :key="tenant.id"
                                                class="h-8.5 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                                :class="selectedTenant?.id === tenant.id ? 'bg-purple-500/5 dark:bg-purple-500/10 border-l-2 border-l-purple-600' : ''"
                                                @click="inspectTenant(tenant)"
                                            >
                                                <!-- Organization & Domain -->
                                                <TableCell class="py-1 px-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded bg-purple-500/10 border border-purple-500/20 text-purple-600 font-bold flex items-center justify-center text-[10px]">
                                                            {{ tenant.name.substring(0, 2).toUpperCase() }}
                                                        </div>
                                                        <div>
                                                            <div class="font-bold text-foreground text-xs hover:text-purple-600 transition-colors">
                                                                {{ tenant.name }}
                                                            </div>
                                                            <div class="font-mono text-[9.5px] text-muted-foreground flex items-center gap-1">
                                                                <span>{{ tenant.slug }}</span>
                                                                <span v-if="tenant.domain" class="text-purple-600">· {{ tenant.domain }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>

                                                <!-- Subscription Tier -->
                                                <TableCell class="py-1 px-2.5">
                                                    <span 
                                                        class="px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase tracking-wider"
                                                        :class="{
                                                            'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20': tenant.subscription_tier === 'starter',
                                                            'bg-blue-500/10 text-blue-600 border border-blue-500/20': tenant.subscription_tier === 'growth',
                                                            'bg-purple-500/10 text-purple-600 border border-purple-500/20': tenant.subscription_tier === 'enterprise',
                                                        }"
                                                    >
                                                        {{ tenant.subscription_tier }}
                                                    </span>
                                                </TableCell>

                                                <!-- Facilities Quota -->
                                                <TableCell class="py-1 px-2.5">
                                                    <div class="space-y-0.5">
                                                        <div class="text-[10px] font-mono font-bold text-foreground">
                                                            {{ tenant.facilities_count }} / {{ tenant.max_facilities }}
                                                        </div>
                                                        <div class="w-14 bg-muted rounded-full h-1 overflow-hidden">
                                                            <div class="bg-primary h-1 rounded-full" :style="{ width: `${Math.min(100, (tenant.facilities_count / tenant.max_facilities) * 100)}%` }"></div>
                                                        </div>
                                                    </div>
                                                </TableCell>

                                                <!-- Users Quota -->
                                                <TableCell class="py-1 px-2.5">
                                                    <div class="space-y-0.5">
                                                        <div class="text-[10px] font-mono font-bold text-foreground">
                                                            {{ tenant.users_count }} / {{ tenant.max_users }}
                                                        </div>
                                                        <div class="w-14 bg-muted rounded-full h-1 overflow-hidden">
                                                            <div class="bg-purple-600 h-1 rounded-full" :style="{ width: `${Math.min(100, (tenant.users_count / tenant.max_users) * 100)}%` }"></div>
                                                        </div>
                                                    </div>
                                                </TableCell>

                                                <!-- Status -->
                                                <TableCell class="py-1 px-2.5">
                                                    <AfyaStatusBadge :status="tenant.subscription_status || tenant.status" />
                                                </TableCell>

                                                <!-- Created At -->
                                                <TableCell class="py-1 px-2.5 text-[10px] text-muted-foreground font-mono whitespace-nowrap">
                                                    {{ formatDate(tenant.created_at) }}
                                                </TableCell>

                                                <!-- Actions -->
                                                <TableCell class="py-1 px-3 text-right">
                                                    <div class="flex items-center justify-end gap-1" @click.stop>
                                                        <!-- Impersonate Support Button -->
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            class="h-6 px-2 text-[10.5px] font-semibold text-purple-600 border-purple-500/30 hover:bg-purple-500/10 gap-1"
                                                            title="Audited Support Impersonation"
                                                            @click="openImpersonate(tenant)"
                                                        >
                                                            <UserCheck class="w-3 h-3" />
                                                            <span>Support</span>
                                                        </Button>

                                                        <!-- Edit Subscription Button -->
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            class="h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                                                            title="Edit Subscription & Quotas"
                                                            @click="openEditSubscription(tenant)"
                                                        >
                                                            <Sliders class="w-3 h-3" />
                                                        </Button>

                                                        <!-- Suspend / Activate Toggle -->
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            class="h-6 w-6 p-0"
                                                            :class="tenant.status === 'suspended' ? 'text-emerald-600 hover:text-emerald-700' : 'text-rose-600 hover:text-rose-700'"
                                                            :title="tenant.status === 'suspended' ? 'Reactivate Tenant' : 'Suspend Tenant'"
                                                            @click="toggleTenantStatus(tenant)"
                                                        >
                                                            <Power class="w-3 h-3" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>

                                            <TableRow v-if="filteredTenants.length === 0">
                                                <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                    <Building2 class="w-6 h-6 mx-auto text-muted-foreground/40 mb-1" />
                                                    No hospital organizations match search criteria.
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>

                                <!-- Pagination Footer -->
                                <AfyaTablePagination
                                    v-model:currentPage="tenantsCurrentPage"
                                    v-model:perPage="tenantsPerPage"
                                    :total-items="filteredTenants.length"
                                    :per-page-options="tenantsPerPageOptions"
                                    item-label="hospitals"
                                />
                            </div>
                        </div>

                        <!-- TAB 2: PLATFORM TELEMETRY & INFRASTRUCTURE -->
                        <div v-if="activeTab === 'telemetry'" class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <!-- Card: Infrastructure Capacity -->
                                <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <div class="p-1 rounded bg-blue-500/10 text-blue-600">
                                            <Server class="w-3.5 h-3.5" />
                                        </div>
                                        <h3 class="font-bold text-xs text-foreground">Infrastructure Scale</h3>
                                    </div>
                                    <div class="space-y-2 text-xs">
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">Total Hospital Facilities</span>
                                            <span class="font-bold font-mono">{{ telemetry.infrastructure?.total_facilities || 0 }} Branches</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">Total Inpatient Beds</span>
                                            <span class="font-bold font-mono">{{ telemetry.infrastructure?.total_beds || 0 }} Beds</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">Staff User Accounts</span>
                                            <span class="font-bold font-mono">{{ telemetry.infrastructure?.total_users || 0 }} Accounts</span>
                                        </div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-muted-foreground">Registered Patients</span>
                                            <span class="font-bold font-mono">{{ (telemetry.infrastructure?.total_patients || 0).toLocaleString() }} Records</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card: Operational Throughput -->
                                <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <div class="p-1 rounded bg-emerald-500/10 text-emerald-600">
                                            <Activity class="w-3.5 h-3.5" />
                                        </div>
                                        <h3 class="font-bold text-xs text-foreground">Clinical & Billing Volume</h3>
                                    </div>
                                    <div class="space-y-2 text-xs">
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">Encounters Processed Today</span>
                                            <span class="font-bold font-mono text-emerald-600">{{ telemetry.throughput?.encounters_today || 0 }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">Lifetime Encounters</span>
                                            <span class="font-bold font-mono">{{ (telemetry.throughput?.total_encounters || 0).toLocaleString() }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">Total Billed (TZS)</span>
                                            <span class="font-bold font-mono">{{ formatCurrency(telemetry.throughput?.total_billed_tzs) }}</span>
                                        </div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-muted-foreground">Total Collected (TZS)</span>
                                            <span class="font-bold font-mono text-purple-600">{{ formatCurrency(telemetry.throughput?.total_collected_tzs) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card: System Engine Health -->
                                <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <div class="p-1 rounded bg-purple-500/10 text-purple-600">
                                            <Cpu class="w-3.5 h-3.5" />
                                        </div>
                                        <h3 class="font-bold text-xs text-foreground">Platform Engine Runtime</h3>
                                    </div>
                                    <div class="space-y-2 text-xs">
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">PHP Runtime</span>
                                            <span class="font-mono font-bold">{{ telemetry.system_health?.php_version }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">PostgreSQL Footprint</span>
                                            <span class="font-mono font-bold">{{ telemetry.system_health?.database_size }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-border/40">
                                            <span class="text-muted-foreground">Redis Cache State</span>
                                            <span class="font-bold text-emerald-600">{{ telemetry.system_health?.redis_status || 'Connected' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-muted-foreground">Server Environment</span>
                                            <span class="font-mono uppercase font-bold text-purple-600">{{ telemetry.system_health?.server_environment || 'production' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: INTERACTIVE SUBSCRIPTION PLAN MATRIX BUILDER -->
                        <div v-if="activeTab === 'subscriptions'" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-bold text-xs text-foreground">Hospital SaaS Subscription Plans & Feature Matrix</h3>
                                    <p class="text-[10.5px] text-muted-foreground">Configure plan blueprints, adjust modular feature flags, and propagate updates across active hospital tenants.</p>
                                </div>
                                <Button
                                    size="sm"
                                    class="h-7 text-xs font-bold gap-1 bg-purple-600 hover:bg-purple-700 text-white shadow-2xs"
                                    @click="openCreatePlan"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Create New Tier</span>
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div 
                                    v-for="plan in subscriptionPlans" 
                                    :key="plan.id"
                                    class="p-3.5 bg-card rounded-lg border shadow-2xs space-y-3 relative flex flex-col justify-between"
                                    :class="plan.code === 'growth' ? 'border-2 border-purple-500/50' : 'border-border/60'"
                                >
                                    <div class="space-y-2.5">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <h4 class="font-bold text-xs text-foreground">{{ plan.name }}</h4>
                                                    <span v-if="plan.is_popular" class="text-[8.5px] font-bold px-1.5 py-0.2 rounded bg-purple-500/10 text-purple-600 uppercase border border-purple-500/20">Popular</span>
                                                </div>
                                                <p class="text-[10.5px] text-muted-foreground line-clamp-1 mt-0.5">{{ plan.description }}</p>
                                            </div>
                                            <span class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded bg-muted text-muted-foreground uppercase">code: {{ plan.code }}</span>
                                        </div>

                                        <div class="p-2 rounded-md bg-muted/30 border border-border/40 space-y-1 text-xs">
                                            <div class="flex justify-between items-baseline">
                                                <span class="text-[10.5px] text-muted-foreground">Monthly License:</span>
                                                <span class="font-bold font-mono text-foreground">{{ formatCurrency(plan.price_monthly_tzs) }}</span>
                                            </div>
                                            <div class="flex justify-between items-baseline text-[10.5px]">
                                                <span class="text-muted-foreground">Quotas:</span>
                                                <span class="font-bold font-mono">{{ plan.max_facilities }} Facilities · {{ plan.max_users }} Seats</span>
                                            </div>
                                        </div>

                                        <!-- Feature Flags Chips -->
                                        <div class="space-y-1">
                                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Enabled Modules ({{ (plan.feature_flags || []).length }})</div>
                                            <div class="flex flex-wrap gap-1">
                                                <span 
                                                    v-for="flag in (plan.feature_flags || [])" 
                                                    :key="flag"
                                                    class="px-1.5 py-0.2 bg-purple-500/10 text-purple-700 dark:text-purple-300 rounded text-[9.5px] font-mono font-medium border border-purple-500/20"
                                                >
                                                    {{ flag }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Plan Action Controls -->
                                    <div class="pt-2 border-t border-border/40 flex items-center justify-between gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-6.5 text-[10.5px] font-semibold text-purple-600 border-purple-500/30 hover:bg-purple-500/10 gap-1 flex-1"
                                            @click="openEditPlan(plan)"
                                        >
                                            <Sliders class="w-3 h-3" />
                                            <span>Edit Blueprint</span>
                                        </Button>

                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-6.5 text-[10.5px] text-muted-foreground hover:text-foreground gap-1"
                                            :loading="isPropagatingId === plan.id"
                                            :disabled="isPropagatingId === plan.id"
                                            title="Sync this plan's feature flags & quotas to all hospitals on this tier"
                                            @click="propagatePlan(plan)"
                                        >
                                            <Share2 v-if="isPropagatingId !== plan.id" class="w-3 h-3" />
                                            <span>{{ isPropagatingId === plan.id ? 'Syncing...' : 'Sync Fleet' }}</span>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: MASTER CLINICAL DATA EXPLORER & BROADCAST -->
                        <div v-if="activeTab === 'dictionary'" class="space-y-3">
                            <!-- Catalog Toolbar -->
                            <div class="p-2.5 bg-card rounded-lg border border-border/60 shadow-2xs flex flex-wrap items-center justify-between gap-2.5">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="h-7 px-2.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5"
                                        :class="catalogTab === 'medicines' ? 'bg-purple-600 text-white shadow-2xs font-bold' : 'bg-muted/40 hover:bg-muted text-muted-foreground'"
                                        @click="catalogTab = 'medicines'"
                                    >
                                        <Pill class="w-3.5 h-3.5" />
                                        <span>NEMLIT Formulary ({{ masterCatalogs?.total_medicines || 12 }})</span>
                                    </button>

                                    <button
                                        type="button"
                                        class="h-7 px-2.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5"
                                        :class="catalogTab === 'lab_tests' ? 'bg-purple-600 text-white shadow-2xs font-bold' : 'bg-muted/40 hover:bg-muted text-muted-foreground'"
                                        @click="catalogTab = 'lab_tests'"
                                    >
                                        <FlaskConical class="w-3.5 h-3.5" />
                                        <span>LOINC Lab Tests ({{ masterCatalogs?.total_lab_tests || 10 }})</span>
                                    </button>

                                    <button
                                        type="button"
                                        class="h-7 px-2.5 text-xs font-semibold rounded-md transition-colors flex items-center gap-1.5"
                                        :class="catalogTab === 'diagnoses' ? 'bg-purple-600 text-white shadow-2xs font-bold' : 'bg-muted/40 hover:bg-muted text-muted-foreground'"
                                        @click="catalogTab = 'diagnoses'"
                                    >
                                        <Stethoscope class="w-3.5 h-3.5" />
                                        <span>ICD-10 Diagnoses ({{ masterCatalogs?.total_diagnoses || 8 }})</span>
                                    </button>
                                </div>

                                <div class="flex items-center gap-2">
                                    <SearchInput
                                        v-model="catalogSearch"
                                        placeholder="Search catalog items..."
                                        class="w-48 sm:w-64"
                                    />
                                    <Button
                                        size="sm"
                                        class="h-7 text-xs bg-purple-600 hover:bg-purple-700 text-white gap-1.5 font-bold shadow-2xs"
                                        @click="showSyncModal = true"
                                    >
                                        <RefreshCw class="w-3.5 h-3.5" />
                                        <span>Sync Catalogs</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Sub-View 1: NEMLIT MEDICINES TABLE -->
                            <div v-if="catalogTab === 'medicines'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs border border-border/60">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/20 border-b border-border/40">
                                            <TableHead class="py-1 px-3">Formulation Name</TableHead>
                                            <TableHead class="py-1 px-2.5">Generic Molecule</TableHead>
                                            <TableHead class="py-1 px-2.5">Therapeutic Class</TableHead>
                                            <TableHead class="py-1 px-2.5">Dosage Form</TableHead>
                                            <TableHead class="py-1 px-2.5">Strength</TableHead>
                                            <TableHead class="py-1 px-3">Standard Reference</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(med, idx) in filteredMasterMedicines" :key="idx" class="h-8 border-b border-border/30 hover:bg-muted/20">
                                            <TableCell class="py-1 px-3 font-bold text-foreground">{{ med.name }}</TableCell>
                                            <TableCell class="py-1 px-2.5 font-medium text-muted-foreground">{{ med.generic_name }}</TableCell>
                                            <TableCell class="py-1 px-2.5">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-blue-500/10 text-blue-600">{{ med.category }}</span>
                                            </TableCell>
                                            <TableCell class="py-1 px-2.5 text-muted-foreground">{{ med.form }}</TableCell>
                                            <TableCell class="py-1 px-2.5 font-mono text-[10.5px]">{{ med.strength }}</TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-[10px] text-purple-600 font-semibold">{{ med.source }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <!-- Sub-View 2: LOINC LAB TESTS TABLE -->
                            <div v-if="catalogTab === 'lab_tests'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs border border-border/60">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/20 border-b border-border/40">
                                            <TableHead class="py-1 px-3">Diagnostic Test Name</TableHead>
                                            <TableHead class="py-1 px-2.5">Test Code</TableHead>
                                            <TableHead class="py-1 px-2.5">Discipline / Category</TableHead>
                                            <TableHead class="py-1 px-2.5">LOINC Code</TableHead>
                                            <TableHead class="py-1 px-3">Standard Turnaround</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(test, idx) in filteredMasterLabTests" :key="idx" class="h-8 border-b border-border/30 hover:bg-muted/20">
                                            <TableCell class="py-1 px-3 font-bold text-foreground">{{ test.name }}</TableCell>
                                            <TableCell class="py-1 px-2.5 font-mono text-[10.5px] font-bold text-purple-600">{{ test.code }}</TableCell>
                                            <TableCell class="py-1 px-2.5">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-500/10 text-emerald-600">{{ test.category }}</span>
                                            </TableCell>
                                            <TableCell class="py-1 px-2.5 font-mono text-[10.5px] text-muted-foreground">{{ test.loinc }}</TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-[10.5px]">{{ test.turnaround }} mins</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <!-- Sub-View 3: ICD-10 DIAGNOSES TABLE -->
                            <div v-if="catalogTab === 'diagnoses'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs border border-border/60">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/20 border-b border-border/40">
                                            <TableHead class="py-1 px-3">ICD-10 Code</TableHead>
                                            <TableHead class="py-1 px-3">Diagnosis Description</TableHead>
                                            <TableHead class="py-1 px-3">WHO Classification Chapter</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(diag, idx) in filteredMasterDiagnoses" :key="idx" class="h-8 border-b border-border/30 hover:bg-muted/20">
                                            <TableCell class="py-1 px-3 font-mono font-bold text-purple-600">{{ diag.code }}</TableCell>
                                            <TableCell class="py-1 px-3 font-bold text-foreground">{{ diag.name }}</TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground">{{ diag.chapter }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- TAB 5: FORENSIC IMPERSONATION AUDIT TRAIL -->
                        <div v-if="activeTab === 'impersonation_logs'" class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-bold text-foreground">
                                    Forensic Support Session Audit Trail
                                </div>
                                <span class="text-[11px] font-mono text-muted-foreground font-semibold">{{ recentLogs.length }} Logged Records</span>
                            </div>

                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs border border-border/60">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/20 border-b border-border/40">
                                            <TableHead class="py-1 px-3">Operator</TableHead>
                                            <TableHead class="py-1 px-2.5">Target Hospital User</TableHead>
                                            <TableHead class="py-1 px-2.5">Audit Justification</TableHead>
                                            <TableHead class="py-1 px-2.5">Operator IP</TableHead>
                                            <TableHead class="py-1 px-2.5">Started At</TableHead>
                                            <TableHead class="py-1 px-3">Session Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="log in recentLogs" :key="log.id" class="h-8.5 border-b border-border/30 hover:bg-muted/20">
                                            <TableCell class="py-1 px-3 font-medium text-xs text-foreground">
                                                {{ log.superadmin_name }}
                                                <div class="text-[9.5px] text-muted-foreground font-mono">{{ log.superadmin_email }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-2.5 text-xs">
                                                <div class="font-bold text-foreground">{{ log.target_user_name }}</div>
                                                <div class="text-[10px] text-purple-600 font-medium">{{ log.target_tenant_name }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-2.5 text-xs text-muted-foreground max-w-xs truncate" :title="log.justification_reason">
                                                {{ log.justification_reason }}
                                            </TableCell>
                                            <TableCell class="py-1 px-2.5 font-mono text-[10px] text-muted-foreground">
                                                {{ log.ip_address }}
                                            </TableCell>
                                            <TableCell class="py-1 px-2.5 font-mono text-[10px] text-muted-foreground whitespace-nowrap">
                                                {{ formatDate(log.started_at) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="log.is_active ? 'bg-purple-500/10 text-purple-600 border border-purple-500/20' : 'bg-muted text-muted-foreground'"
                                                >
                                                    {{ log.is_active ? 'ACTIVE' : 'CONCLUDED' }}
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                        <TableRow v-if="recentLogs.length === 0">
                                            <TableCell colspan="6" class="text-center py-6 text-muted-foreground text-xs">
                                                No superadmin impersonation sessions recorded.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: COMPACT FORENSIC CONTEXT INSPECTOR -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    v-if="selectedTenant"
                    title="Organization Profile"
                    :subtitle="selectedTenant.name"
                    :width="width"
                    @close="close"
                >
                    <div class="p-3 space-y-3 text-xs">
                        <!-- Quick Info -->
                        <div class="space-y-1 bg-muted/30 p-2 rounded-lg border border-border/60 text-[11px]">
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
                            <div class="flex justify-between items-center">
                                <span class="text-muted-foreground">Status:</span>
                                <AfyaStatusBadge :status="selectedTenant.subscription_status || selectedTenant.status" />
                            </div>
                        </div>

                        <!-- Resource Quotas -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-foreground uppercase tracking-wider text-[10px]">Resource Quotas</h4>
                                <span class="text-[9.5px] font-mono text-purple-600 font-bold">
                                    {{ selectedTenant.facilities_count }}/{{ selectedTenant.max_facilities }} Branches
                                </span>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-[10.5px]">
                                    <span class="text-muted-foreground">Facilities</span>
                                    <span class="font-bold font-mono">{{ selectedTenant.facilities_count }} / {{ selectedTenant.max_facilities }}</span>
                                </div>
                                <div class="w-full bg-muted rounded-full h-1 overflow-hidden">
                                    <div class="bg-primary h-1 rounded-full" :style="{ width: `${Math.min(100, (selectedTenant.facilities_count / selectedTenant.max_facilities) * 100)}%` }"></div>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <div class="flex justify-between text-[10.5px]">
                                    <span class="text-muted-foreground">Staff User Accounts</span>
                                    <span class="font-bold font-mono">{{ selectedTenant.users_count }} / {{ selectedTenant.max_users }}</span>
                                </div>
                                <div class="w-full bg-muted rounded-full h-1 overflow-hidden">
                                    <div class="bg-purple-600 h-1 rounded-full" :style="{ width: `${Math.min(100, (selectedTenant.users_count / selectedTenant.max_users) * 100)}%` }"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Facility Branches List & Add Button -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-foreground uppercase tracking-wider text-[10px]">Facility Branches</h4>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-5 px-1.5 text-[9.5px] font-bold text-purple-600 border-purple-500/30 hover:bg-purple-500/10 gap-0.5"
                                    :disabled="selectedTenant.facilities_count >= selectedTenant.max_facilities"
                                    :title="selectedTenant.facilities_count >= selectedTenant.max_facilities ? 'Branch quota limit reached' : 'Add new physical branch to this hospital organization'"
                                    @click="openAddFacilityModal"
                                >
                                    <Plus class="w-2.5 h-2.5" />
                                    <span>Add Branch</span>
                                </Button>
                            </div>

                            <!-- Facility Branch List Scroll Area -->
                            <div class="space-y-1 max-h-32 overflow-y-auto pr-0.5">
                                <div 
                                    v-for="fac in (selectedTenant.facilities || [])" 
                                    :key="fac.id"
                                    class="p-1.5 px-2 rounded-md bg-muted/40 border border-border/50 flex items-center justify-between text-[11px]"
                                >
                                    <div>
                                        <div class="font-semibold text-foreground text-[11px]">{{ fac.name }}</div>
                                        <div class="text-[9.5px] text-muted-foreground font-mono">{{ fac.code }} · {{ fac.type }}</div>
                                    </div>
                                    <span 
                                        class="px-1 py-0.2 rounded text-[8.5px] font-bold"
                                        :class="fac.is_active ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600'"
                                    >
                                        {{ fac.is_active ? 'ACTIVE' : 'INACTIVE' }}
                                    </span>
                                </div>
                                <div v-if="!(selectedTenant.facilities || []).length" class="text-muted-foreground text-[10.5px] italic">
                                    No facility branches provisioned.
                                </div>
                            </div>
                        </div>

                        <!-- Staff Accounts Registry -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-foreground uppercase tracking-wider text-[10px]">Staff Accounts</h4>
                                <span class="text-[9.5px] font-mono text-muted-foreground">{{ (selectedTenant.users || []).length }} users</span>
                            </div>
                            <div class="space-y-1 max-h-32 overflow-y-auto pr-0.5">
                                <div 
                                    v-for="usr in (selectedTenant.users || [])" 
                                    :key="usr.id"
                                    class="p-1.5 px-2 rounded-md bg-muted/40 border border-border/50 flex items-center justify-between text-[11px]"
                                >
                                    <div>
                                        <div class="font-semibold text-foreground text-[11px]">{{ usr.name }}</div>
                                        <div class="text-[9.5px] text-muted-foreground font-mono">{{ usr.email }}</div>
                                    </div>
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-medium bg-purple-500/10 text-purple-600">
                                        {{ usr.role }}
                                    </span>
                                </div>
                                <div v-if="!(selectedTenant.users || []).length" class="text-muted-foreground text-[10.5px] italic">
                                    No staff accounts provisioned.
                                </div>
                            </div>
                        </div>

                        <!-- Enabled Modules -->
                        <div class="space-y-1.5">
                            <h4 class="font-bold text-foreground uppercase tracking-wider text-[10px]">Active Modules</h4>
                            <div class="flex flex-wrap gap-1">
                                <span 
                                    v-for="flag in (selectedTenant.feature_flags || [])" 
                                    :key="flag"
                                    class="px-1.5 py-0.2 bg-muted rounded text-[9.5px] font-mono font-medium text-foreground"
                                >
                                    {{ flag }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="space-y-1.5 pt-1">
                            <Button 
                                class="w-full h-7 text-xs bg-purple-600 hover:bg-purple-700 text-white flex items-center justify-center gap-1.5 font-bold shadow-2xs"
                                size="sm"
                                @click="openImpersonate(selectedTenant)"
                            >
                                <UserCheck class="w-3.5 h-3.5" />
                                Audited Login-As Support
                            </Button>
                            <Button 
                                variant="outline"
                                class="w-full h-7 text-xs"
                                size="sm"
                                @click="openEditSubscription(selectedTenant)"
                            >
                                <Sliders class="w-3.5 h-3.5 mr-1" />
                                Manage Subscription & Quotas
                            </Button>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>

        </AfyaWorkspace>
    </AfyaShell>

    <!-- 1. MODAL: EDIT PLAN BLUEPRINT -->
    <Modal :show="showPlanModal" @close="showPlanModal = false" max-width="xl">
        <form @submit.prevent="submitPlanForm" class="p-5 space-y-3.5">
            <div class="flex items-center justify-between border-b border-border pb-2.5">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-600">
                        <CreditCard class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-foreground">
                            {{ isEditingPlan ? `Edit ${planForm.name} Blueprint` : 'Create New Subscription Plan Tier' }}
                        </h3>
                        <p class="text-[11px] text-muted-foreground">Configure default quotas, pricing, and enabled modular features.</p>
                    </div>
                </div>
                <button type="button" @click="showPlanModal = false" class="text-muted-foreground hover:text-foreground">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <!-- Name -->
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Plan Display Name *</label>
                    <Input v-model="planForm.name" placeholder="e.g. Regional Referral Tier" class="h-8" required />
                </div>

                <!-- Code -->
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Plan Code Identifier *</label>
                    <Input v-model="planForm.code" :disabled="isEditingPlan" placeholder="e.g. regional" class="h-8 font-mono" required />
                </div>

                <!-- Description -->
                <div class="space-y-1 sm:col-span-2">
                    <label class="font-bold text-foreground text-[11px]">Plan Description</label>
                    <Input v-model="planForm.description" placeholder="Brief target description for this tier..." class="h-8" />
                </div>

                <!-- Monthly Price -->
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Monthly License Fee (TZS)</label>
                    <Input v-model.number="planForm.price_monthly_tzs" type="number" min="0" step="50000" class="h-8 font-mono" required />
                </div>

                <!-- Annual Price -->
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Annual License Fee (TZS)</label>
                    <Input v-model.number="planForm.price_annual_tzs" type="number" min="0" step="500000" class="h-8 font-mono" required />
                </div>

                <!-- Max Facilities -->
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Max Facilities Quota</label>
                    <Input v-model.number="planForm.max_facilities" type="number" min="1" max="500" class="h-8" required />
                </div>

                <!-- Max Users -->
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Max Staff Seats Quota</label>
                    <Input v-model.number="planForm.max_users" type="number" min="1" max="5000" class="h-8" required />
                </div>

                <!-- Storage Quota -->
                <div class="space-y-1 sm:col-span-2">
                    <label class="font-bold text-foreground text-[11px]">Storage Quota (MB)</label>
                    <Input v-model.number="planForm.storage_quota_mb" type="number" min="1024" step="1024" class="h-8" required />
                </div>

                <!-- Feature Flags Checklist -->
                <div class="space-y-1.5 sm:col-span-2 pt-1 border-t border-border">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-foreground text-[11px]">Default Enabled Modular Features</label>
                        <span class="text-[10px] font-mono text-purple-600 font-bold">{{ planForm.feature_flags.length }} Selected</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-48 overflow-y-auto p-1">
                        <label 
                            v-for="feat in allAvailableFeatures" 
                            :key="feat.key"
                            class="flex items-center gap-2 p-1.5 px-2 rounded-lg border border-border hover:bg-muted/40 cursor-pointer text-xs transition-colors"
                            :class="planForm.feature_flags.includes(feat.key) ? 'bg-purple-500/5 border-purple-500/30' : ''"
                        >
                            <input 
                                type="checkbox" 
                                :checked="planForm.feature_flags.includes(feat.key)"
                                @change="togglePlanFeature(feat.key)"
                                class="rounded border-border text-purple-600 focus:ring-purple-500"
                            />
                            <div>
                                <div class="font-medium text-foreground text-[11px]">{{ feat.label }}</div>
                                <div class="text-[9.5px] text-muted-foreground">{{ feat.category }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Propagate to active tenants checkbox -->
                <div v-if="isEditingPlan" class="sm:col-span-2 p-2 rounded-lg bg-purple-500/10 border border-purple-500/20">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-purple-800 dark:text-purple-300 font-medium">
                        <input 
                            type="checkbox" 
                            v-model="planForm.propagate_to_tenants"
                            class="rounded border-purple-500 text-purple-600 focus:ring-purple-500"
                        />
                        <span>Propagate updated feature flags & quotas to all active hospitals on this tier immediately</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <Button type="button" variant="outline" size="sm" class="h-7.5 text-xs" @click="showPlanModal = false">
                    Cancel
                </Button>
                <Button 
                    type="submit" 
                    size="sm" 
                    class="h-7.5 text-xs bg-purple-600 hover:bg-purple-700 text-white font-bold" 
                    :loading="planForm.processing"
                    :disabled="planForm.processing"
                >
                    {{ planForm.processing ? 'Saving Blueprint...' : (isEditingPlan ? 'Save Plan Blueprint' : 'Create Plan Tier') }}
                </Button>
            </div>
        </form>
    </Modal>

    <!-- 2. MODAL: PROVISION HOSPITAL GROUP WITH DRAFT SUPPORT -->
    <Modal :show="showProvisionModal" @close="showProvisionModal = false" max-width="2xl">
        <form @submit.prevent="submitProvision" class="p-5 space-y-3.5">
            <!-- Header with Draft Status -->
            <div class="flex items-center justify-between border-b border-border pb-2.5">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-600">
                        <Building2 class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-foreground">Provision New Hospital Group</h3>
                            <span v-if="draftLastSavedAt" class="text-[9.5px] font-mono px-1.5 py-0.2 rounded bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 flex items-center gap-1">
                                <Save class="w-2.5 h-2.5" />
                                <span>Draft auto-saved {{ draftLastSavedAt }}</span>
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground">Setup tenant organization, primary facility branch, and root admin credentials.</p>
                    </div>
                </div>
                <button type="button" @click="showProvisionModal = false" class="text-muted-foreground hover:text-foreground">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Draft Notice & Quick Restore / Clear Banner -->
            <div v-if="savedProvisionDraft && !provisionForm.name" class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-between text-xs">
                <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300">
                    <FileEdit class="w-4 h-4 text-amber-600 shrink-0" />
                    <div>
                        <span class="font-bold">Unfinished Provisioning Draft Detected</span>
                        <div class="text-[10.5px] text-muted-foreground">
                            {{ savedProvisionDraft.data?.name || 'Untitled Organization' }} (Saved {{ formatDate(savedProvisionDraft.updated_at) }})
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <Button 
                        type="button" 
                        size="sm" 
                        class="h-6 px-2 text-[10.5px] font-bold bg-amber-600 hover:bg-amber-700 text-white gap-1"
                        @click="restoreProvisionDraft"
                    >
                        <RotateCcw class="w-3 h-3" />
                        <span>Restore Draft</span>
                    </Button>
                    <Button 
                        type="button" 
                        variant="ghost" 
                        size="sm" 
                        class="h-6 px-1.5 text-[10.5px] text-rose-600 hover:text-rose-700 gap-1"
                        @click="discardProvisionDraft"
                    >
                        <Trash2 class="w-3 h-3" />
                        <span>Discard</span>
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="space-y-1 sm:col-span-2">
                    <label class="font-bold text-foreground text-[11px]">
                        Hospital Organization Name * <span class="text-muted-foreground font-normal">(Parent Tenant Company)</span>
                    </label>
                    <Input v-model="provisionForm.name" placeholder="e.g. Aga Khan Health Services Tanzania" class="h-8" required />
                    <InputError :message="provisionForm.errors.name" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Tenant Slug</label>
                    <Input v-model="provisionForm.slug" placeholder="e.g. aga-khan-tz" class="h-8 font-mono" />
                    <InputError :message="provisionForm.errors.slug" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Custom FQDN Domain</label>
                    <Input v-model="provisionForm.domain" placeholder="e.g. emr.agakhan.co.tz" class="h-8 font-mono" />
                    <InputError :message="provisionForm.errors.domain" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Subscription Tier *</label>
                    <Select v-model="provisionForm.subscription_tier" class="w-full">
                        <option v-for="plan in subscriptionPlans" :key="plan.id" :value="plan.code">
                            {{ plan.name }} ({{ plan.max_facilities }} Br / {{ plan.max_users }} Seats)
                        </option>
                    </Select>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Initial Status *</label>
                    <Select v-model="provisionForm.subscription_status" class="w-full">
                        <option value="active">Active (Production)</option>
                        <option value="trial">Trial (14 Days)</option>
                    </Select>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">
                        Primary Facility Branch Name * <span class="text-muted-foreground font-normal">(1st Physical Building)</span>
                    </label>
                    <Input v-model="provisionForm.main_facility_name" placeholder="e.g. Ocean Road Main Hospital" class="h-8" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Facility Classification</label>
                    <Select v-model="provisionForm.facility_type" class="w-full">
                        <option value="National Referral Hospital">National Referral Hospital</option>
                        <option value="Regional Referral Hospital">Regional Referral Hospital</option>
                        <option value="District Hospital">District Hospital</option>
                        <option value="Polyclinic">Polyclinic</option>
                        <option value="Health Center">Health Center</option>
                        <option value="Dispensary">Dispensary</option>
                    </Select>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Admin First Name *</label>
                    <Input v-model="provisionForm.admin_first_name" placeholder="First Name" class="h-8" required />
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Admin Last Name *</label>
                    <Input v-model="provisionForm.admin_last_name" placeholder="Last Name" class="h-8" required />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Admin Login Email *</label>
                    <Input v-model="provisionForm.admin_email" type="email" placeholder="admin@hospital.co.tz" class="h-8" required />
                    <InputError :message="provisionForm.errors.admin_email" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Initial Admin Password *</label>
                    <Input v-model="provisionForm.admin_password" type="password" placeholder="••••••••" class="h-8" required />
                </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-border">
                <div class="flex items-center gap-1.5">
                    <Button 
                        type="button" 
                        variant="outline" 
                        size="sm" 
                        class="h-7.5 text-xs gap-1"
                        @click="saveProvisionDraft"
                    >
                        <Save class="w-3.5 h-3.5 text-muted-foreground" />
                        <span>Save as Draft</span>
                    </Button>
                    <Button 
                        v-if="savedProvisionDraft"
                        type="button" 
                        variant="ghost" 
                        size="sm" 
                        class="h-7.5 text-xs text-rose-600 hover:text-rose-700 gap-1"
                        @click="discardProvisionDraft"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>Discard Draft</span>
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-7.5 text-xs" @click="showProvisionModal = false">
                        Cancel
                    </Button>
                    <Button 
                        type="submit" 
                        size="sm" 
                        class="h-7.5 text-xs bg-purple-600 hover:bg-purple-700 text-white font-bold" 
                        :loading="provisionForm.processing"
                        :disabled="provisionForm.processing"
                    >
                        {{ provisionForm.processing ? 'Provisioning Hospital...' : 'Provision Organization' }}
                    </Button>
                </div>
            </div>
        </form>
    </Modal>

    <!-- 3. MODAL: ADD FACILITY BRANCH TO EXISTING TENANT WITH DRAFT SUPPORT -->
    <Modal :show="showAddFacilityModal" @close="showAddFacilityModal = false" max-width="lg">
        <form @submit.prevent="submitAddFacility" class="p-5 space-y-3.5">
            <div class="flex items-center justify-between border-b border-border pb-2.5">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-blue-500/10 text-blue-600">
                        <FolderPlus class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-foreground">Add Physical Facility Branch</h3>
                            <span v-if="facilityDraftSavedAt" class="text-[9.5px] font-mono px-1.5 py-0.2 rounded bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 flex items-center gap-1">
                                <Save class="w-2.5 h-2.5" />
                                <span>Draft {{ facilityDraftSavedAt }}</span>
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground">Under {{ selectedTenant?.name }} (Quota: {{ selectedTenant?.facilities_count }}/{{ selectedTenant?.max_facilities }})</p>
                    </div>
                </div>
                <button type="button" @click="showAddFacilityModal = false" class="text-muted-foreground hover:text-foreground">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Draft Restorer if exists -->
            <div v-if="savedFacilityDraft && !facilityForm.name" class="p-2 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5 text-amber-800 dark:text-amber-300 text-[11px]">
                    <FileEdit class="w-3.5 h-3.5 text-amber-600" />
                    <span>Unsaved branch draft: <strong>{{ savedFacilityDraft.data?.name || 'Untitled' }}</strong></span>
                </div>
                <div class="flex items-center gap-1">
                    <Button type="button" size="sm" class="h-5.5 px-2 text-[10px] bg-amber-600 text-white font-bold" @click="restoreFacilityDraft">
                        Restore
                    </Button>
                    <Button type="button" variant="ghost" size="sm" class="h-5.5 px-1.5 text-[10px] text-rose-600" @click="discardFacilityDraft">
                        Discard
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="space-y-1 sm:col-span-2">
                    <label class="font-bold text-foreground text-[11px]">Branch / Campus Facility Name *</label>
                    <Input v-model="facilityForm.name" placeholder="e.g. Regency Clinic Mikocheni" class="h-8" required />
                    <InputError :message="facilityForm.errors.name" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Branch Code</label>
                    <Input v-model="facilityForm.code" placeholder="e.g. RMC-MIK" class="h-8 font-mono" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Facility Classification *</label>
                    <Select v-model="facilityForm.facility_type" class="w-full">
                        <option value="Polyclinic">Polyclinic / Outpatient Center</option>
                        <option value="District Hospital">District Hospital Wing</option>
                        <option value="Specialized Center">Specialized Dialysis / Diagnostics</option>
                        <option value="Health Center">Health Center</option>
                        <option value="Dispensary">Dispensary</option>
                    </Select>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">City / District</label>
                    <Input v-model="facilityForm.city" placeholder="e.g. Dar es Salaam" class="h-8" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Region</label>
                    <Input v-model="facilityForm.region" placeholder="e.g. Dar es Salaam" class="h-8" />
                </div>

                <div class="space-y-1 sm:col-span-2">
                    <label class="font-bold text-foreground text-[11px]">Physical Street Address</label>
                    <Input v-model="facilityForm.physical_address" placeholder="e.g. Mwai Kibaki Road, Mikocheni B" class="h-8" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Branch Contact Email</label>
                    <Input v-model="facilityForm.contact_email" type="email" placeholder="mikocheni@hospital.co.tz" class="h-8" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Branch Phone Number</label>
                    <Input v-model="facilityForm.contact_phone" placeholder="+255 7XX XXX XXX" class="h-8" />
                </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-border">
                <div class="flex items-center gap-1.5">
                    <Button type="button" variant="outline" size="sm" class="h-7 text-xs gap-1" @click="saveFacilityDraft">
                        <Save class="w-3 h-3 text-muted-foreground" />
                        <span>Save Draft</span>
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="h-7 text-xs" @click="showAddFacilityModal = false">
                        Cancel
                    </Button>
                    <Button 
                        type="submit" 
                        size="sm" 
                        class="h-7 text-xs bg-blue-600 hover:bg-blue-700 text-white font-bold" 
                        :loading="facilityForm.processing"
                        :disabled="facilityForm.processing"
                    >
                        {{ facilityForm.processing ? 'Creating Branch...' : 'Create Facility Branch' }}
                    </Button>
                </div>
            </div>
        </form>
    </Modal>

    <!-- 4. MODAL: EDIT TENANT SUBSCRIPTION & QUOTAS -->
    <Modal :show="showEditSubscriptionModal" @close="showEditSubscriptionModal = false" max-width="xl">
        <form @submit.prevent="submitUpdateSubscription" class="p-5 space-y-3.5">
            <div class="flex items-center justify-between border-b border-border pb-2.5">
                <div>
                    <h3 class="font-bold text-sm text-foreground">Manage Individual Hospital Subscription</h3>
                    <p class="text-[11px] text-muted-foreground">{{ tenantToEdit?.name }}</p>
                </div>
                <button type="button" @click="showEditSubscriptionModal = false" class="text-muted-foreground hover:text-foreground">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Subscription Tier</label>
                    <Select v-model="editSubForm.subscription_tier" class="w-full">
                        <option v-for="plan in subscriptionPlans" :key="plan.id" :value="plan.code">
                            {{ plan.name }}
                        </option>
                    </Select>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Status</label>
                    <Select v-model="editSubForm.subscription_status" class="w-full">
                        <option value="active">Active</option>
                        <option value="trial">Trial</option>
                        <option value="past_due">Past Due</option>
                        <option value="suspended">Suspended</option>
                    </Select>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Max Facilities Allowed</label>
                    <Input v-model.number="editSubForm.max_facilities" type="number" min="1" max="500" class="h-8" />
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Max Staff Seats</label>
                    <Input v-model.number="editSubForm.max_users" type="number" min="1" max="5000" class="h-8" />
                </div>

                <div class="space-y-1 sm:col-span-2">
                    <label class="font-bold text-foreground text-[11px]">Storage Quota (MB)</label>
                    <Input v-model.number="editSubForm.storage_quota_mb" type="number" min="1024" class="h-8" />
                </div>

                <!-- Feature Flags Checklist -->
                <div class="space-y-1.5 sm:col-span-2 pt-1 border-t border-border">
                    <label class="font-bold text-foreground text-[11px]">Hospital Active Modular Features</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                        <label 
                            v-for="feat in allAvailableFeatures" 
                            :key="feat.key"
                            class="flex items-center gap-2 p-1.5 px-2 rounded-lg border border-border hover:bg-muted/40 cursor-pointer text-xs"
                        >
                            <input 
                                type="checkbox" 
                                :checked="editSubForm.feature_flags.includes(feat.key)"
                                @change="toggleFeature(feat.key)"
                                class="rounded border-border text-purple-600 focus:ring-purple-500"
                            />
                            <div>
                                <div class="font-medium text-foreground text-[11px]">{{ feat.label }}</div>
                                <div class="text-[9.5px] text-muted-foreground">{{ feat.category }}</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <Button type="button" variant="outline" size="sm" class="h-7.5 text-xs" @click="showEditSubscriptionModal = false">
                    Cancel
                </Button>
                <Button 
                    type="submit" 
                    size="sm" 
                    class="h-7.5 text-xs bg-purple-600 hover:bg-purple-700 text-white font-bold" 
                    :loading="editSubForm.processing"
                    :disabled="editSubForm.processing"
                >
                    {{ editSubForm.processing ? 'Saving Changes...' : 'Save Changes' }}
                </Button>
            </div>
        </form>
    </Modal>

    <!-- 5. MODAL: AUDITED SUPPORT IMPERSONATION -->
    <Modal :show="showImpersonateModal" @close="showImpersonateModal = false" max-width="md">
        <form @submit.prevent="submitImpersonate" class="p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-border pb-2.5">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-600">
                        <UserCheck class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-foreground">Support Impersonation</h3>
                        <p class="text-[11px] text-muted-foreground">{{ tenantToImpersonate?.name }}</p>
                    </div>
                </div>
                <button type="button" @click="showImpersonateModal = false" class="text-muted-foreground hover:text-foreground">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="p-2.5 bg-purple-500/10 border border-purple-500/20 rounded-lg text-xs text-purple-700 dark:text-purple-300 space-y-1">
                <div class="font-bold flex items-center gap-1 text-[11px]">
                    <Shield class="w-3.5 h-3.5" />
                    Audited Healthcare Security Boundary
                </div>
                <p class="text-[10.5px] leading-relaxed">
                    This support session is recorded in the platform audit ledger. You will navigate AfyaNova with this user's facility credentials.
                </p>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Select Target Staff Account *</label>
                    <Select v-model="impersonateForm.user_id" class="w-full" required>
                        <option value="" disabled>Select User</option>
                        <option v-for="u in (tenantToImpersonate?.users || [])" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.email }}) — {{ u.role }}
                        </option>
                    </Select>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Justification Reason * (Min 10 chars)</label>
                    <textarea 
                        v-model="impersonateForm.justification_reason" 
                        rows="2.5" 
                        placeholder="e.g. Investigating billing reconciliation issue reported in ticket #1042"
                        class="w-full p-2 rounded-md border border-border bg-background text-xs"
                        required
                    ></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <Button type="button" variant="outline" size="sm" class="h-7.5 text-xs" @click="showImpersonateModal = false">
                    Cancel
                </Button>
                <Button 
                    type="submit" 
                    size="sm" 
                    class="h-7.5 text-xs bg-purple-600 hover:bg-purple-700 text-white font-bold" 
                    :loading="impersonateForm.processing"
                    :disabled="impersonateForm.processing || (impersonateForm.justification_reason || '').length < 10"
                >
                    {{ impersonateForm.processing ? 'Connecting Session...' : 'Start Support Session' }}
                </Button>
            </div>
        </form>
    </Modal>

    <!-- 6. MODAL: MASTER CLINICAL DATA BROADCAST -->
    <Modal :show="showSyncModal" @close="showSyncModal = false" max-width="md">
        <form @submit.prevent="submitSyncDictionary" class="p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-border pb-2.5">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-purple-500/10 text-purple-600">
                        <RefreshCw class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-foreground">Broadcast Master Dictionaries</h3>
                        <p class="text-[11px] text-muted-foreground">Push standard catalogs across hospital tenant databases.</p>
                    </div>
                </div>
                <button type="button" @click="showSyncModal = false" class="text-muted-foreground hover:text-foreground">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Dictionary Type</label>
                    <Select v-model="syncForm.dictionary_type" class="w-full">
                        <option value="all">All Catalogs (NEMLIT Drugs, LOINC Lab, ICD-10 Diagnoses)</option>
                        <option value="nemlit">Tanzania MSD / WHO Essential Drugs Formulary</option>
                        <option value="loinc">Clinical Laboratory Diagnostic Tests Catalog</option>
                    </Select>
                </div>

                <div class="space-y-1">
                    <label class="font-bold text-foreground text-[11px]">Target Scope</label>
                    <Select v-model="syncForm.tenant_id" class="w-full">
                        <option value="">Broadcast to ALL Active Hospital Tenants</option>
                        <option v-for="t in tenants" :key="t.id" :value="t.id">
                            Only {{ t.name }} ({{ t.slug }})
                        </option>
                    </Select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <Button type="button" variant="outline" size="sm" class="h-7.5 text-xs" @click="showSyncModal = false">
                    Cancel
                </Button>
                <Button 
                    type="submit" 
                    size="sm" 
                    class="h-7.5 text-xs bg-purple-600 hover:bg-purple-700 text-white font-bold" 
                    :loading="syncForm.processing"
                    :disabled="syncForm.processing"
                >
                    {{ syncForm.processing ? 'Broadcasting Dictionaries...' : 'Execute Broadcast' }}
                </Button>
            </div>
        </form>
    </Modal>

    <!-- 7. MODAL: CONFIRM TENANT STATUS TOGGLE -->
    <Modal :show="showTenantStatusModal && !!targetTenantForStatus" @close="showTenantStatusModal = false" max-width="md">
        <div class="p-5 space-y-4">
            <div class="flex items-center gap-3 border-b border-border pb-3">
                <div 
                    class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                    :class="targetTenantForStatus?.status === 'suspended' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600'"
                >
                    <Power class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="font-bold text-sm text-foreground">
                        {{ targetTenantForStatus?.status === 'suspended' ? 'Reactivate Hospital Tenant' : 'Suspend Hospital Tenant' }}
                    </h3>
                    <p class="text-[11px] text-muted-foreground">{{ targetTenantForStatus?.name }}</p>
                </div>
            </div>

            <div class="space-y-2 text-xs">
                <p class="text-foreground leading-relaxed">
                    Are you sure you want to {{ targetTenantForStatus?.status === 'suspended' ? 'reactivate' : 'suspend' }}
                    <strong class="font-bold text-foreground">{{ targetTenantForStatus?.name }}</strong> 
                    <span class="font-mono text-muted-foreground">({{ targetTenantForStatus?.slug }})</span>?
                </p>
                <div 
                    class="p-2.5 rounded-lg border text-[11px] leading-relaxed"
                    :class="targetTenantForStatus?.status === 'suspended' ? 'bg-emerald-500/5 border-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/5 border-rose-500/20 text-rose-700 dark:text-rose-300'"
                >
                    <span v-if="targetTenantForStatus?.status === 'suspended'">
                        Reactivating will restore operational status, staff logins, and clinical encounter capabilities immediately.
                    </span>
                    <span v-else>
                        Suspending will immediately block all staff logins, new patient check-ins, and API requests across all branch facilities under this tenant.
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <Button type="button" variant="outline" size="sm" :disabled="isTogglingTenantStatus" @click="showTenantStatusModal = false">
                    Cancel
                </Button>
                <Button 
                    type="button" 
                    size="sm" 
                    :variant="targetTenantForStatus?.status === 'suspended' ? 'default' : 'destructive'"
                    :disabled="isTogglingTenantStatus"
                    @click="confirmTenantStatusToggle"
                    class="font-bold"
                >
                    <Loader2 v-if="isTogglingTenantStatus" class="w-3.5 h-3.5 animate-spin mr-1" />
                    <span>{{ targetTenantForStatus?.status === 'suspended' ? 'Reactivate Hospital' : 'Suspend Hospital' }}</span>
                </Button>
            </div>
        </div>
    </Modal>

    <!-- 8. MODAL: CONFIRM PLAN PROPAGATION -->
    <Modal :show="showPropagatePlanModal && !!targetPlanForPropagate" @close="showPropagatePlanModal = false" max-width="md">
        <div class="p-5 space-y-4">
            <div class="flex items-center gap-3 border-b border-border pb-3">
                <div class="w-9 h-9 rounded-full bg-purple-500/10 text-purple-600 flex items-center justify-center shrink-0">
                    <Sparkles class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="font-bold text-sm text-foreground">Propagate Plan Blueprint</h3>
                    <p class="text-[11px] text-muted-foreground">{{ targetPlanForPropagate?.name }} (Tier {{ targetPlanForPropagate?.code }})</p>
                </div>
            </div>

            <div class="space-y-2 text-xs">
                <p class="text-foreground leading-relaxed">
                    This will synchronize and propagate all quotas and feature flags from 
                    <strong class="font-bold text-purple-600">{{ targetPlanForPropagate?.name }}</strong> 
                    to all active hospital tenants subscribed to this tier.
                </p>
                <div class="p-2.5 rounded-lg border bg-purple-500/5 border-purple-500/20 text-[11px] text-purple-700 dark:text-purple-300 space-y-1">
                    <div class="font-bold">Plan Quotas to Apply:</div>
                    <ul class="list-disc list-inside space-y-0.5 font-mono text-[10px]">
                        <li>Max Branch Facilities: {{ targetPlanForPropagate?.max_facilities }}</li>
                        <li>Max Staff Seats: {{ targetPlanForPropagate?.max_users }}</li>
                        <li>Storage Quota: {{ targetPlanForPropagate?.storage_quota_mb }} MB</li>
                        <li>Feature Flags: {{ (targetPlanForPropagate?.feature_flags || []).join(', ') || 'None' }}</li>
                    </ul>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <Button type="button" variant="outline" size="sm" :disabled="!!isPropagatingId" @click="showPropagatePlanModal = false">
                    Cancel
                </Button>
                <Button 
                    type="button" 
                    size="sm" 
                    class="bg-purple-600 hover:bg-purple-700 text-white font-bold" 
                    :disabled="!!isPropagatingId"
                    @click="confirmPropagatePlan"
                >
                    <Loader2 v-if="isPropagatingId" class="w-3.5 h-3.5 animate-spin mr-1" />
                    <span>Propagate to All Hospitals</span>
                </Button>
            </div>
        </div>
    </Modal>
</template>
