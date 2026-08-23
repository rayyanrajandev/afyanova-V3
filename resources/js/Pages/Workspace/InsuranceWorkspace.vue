<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    ShieldCheck, 
    ShieldAlert, 
    Receipt, 
    FileText, 
    Users, 
    Building2, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    Search, 
    Plus, 
    Loader2, 
    Check, 
    X, 
    Activity, 
    Send, 
    Clock, 
    DollarSign, 
    CreditCard, 
    Fingerprint, 
    FileSpreadsheet, 
    Layers, 
    ArrowUpRight,
    SlidersHorizontal,
    Percent
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';

// UI Primitives
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import AfyaPatientIdentity from '@/Components/Afya/AfyaPatientIdentity.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    claimsQueue: {
        type: Array,
        default: () => [],
    },
    submittedClaims: {
        type: Array,
        default: () => [],
    },
    preAuthorizations: {
        type: Array,
        default: () => [],
    },
    patientPolicies: {
        type: Array,
        default: () => [],
    },
    providers: {
        type: Array,
        default: () => [],
    },
    encountersForClaiming: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({
            total_claims: 0,
            pending_submission: 0,
            awaiting_remittance: 0,
            total_claimed_value: 0,
            queried_or_disputed: 0,
        }),
    },
});

const { preferences, openContext } = useWorkspacePreferences();

const activeSection = ref('claims'); // claims, remittance, preauth, policies, tariffs
const selectedProviderFilter = ref('all');
const searchQuery = ref('');
const selectedClaimIds = ref([]);

const selectedRecord = ref(
    props.claimsQueue?.[0] || props.submittedClaims?.[0] || props.patientPolicies?.[0] || null
);

// Modals State
const showGenerateClaimModal = ref(false);
const isGeneratingClaim = ref(false);
const generateClaimForm = ref({
    encounter_id: '',
    patient_policy_id: '',
});

const showAdjudicateModal = ref(false);
const adjudicateClaim = ref(null);
const isAdjudicating = ref(false);
const adjudicateForm = ref({
    status: 'Approved',
    approved_amount: 0,
    notes: '',
});

const showPreAuthModal = ref(false);
const isCreatingPreAuth = ref(false);
const preAuthForm = ref({
    patient_policy_id: '',
    encounter_id: '',
    procedure_description: '',
    requested_amount: 50000,
    approved_amount: 50000,
    auth_code: '',
    expires_at: '',
    notes: '',
});

const isSubmittingBatch = ref(false);
const isVerifyingPolicy = ref(false);

// Filtered Lists
const filteredClaimsQueue = computed(() => {
    return props.claimsQueue.filter(claim => {
        if (selectedProviderFilter.value !== 'all' && claim.policy?.provider?.code !== selectedProviderFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = claim.patient ? `${claim.patient.first_name} ${claim.patient.last_name} ${claim.patient.primary_mrn}`.toLowerCase() : '';
            const cNum = (claim.claim_number || '').toLowerCase();
            const card = (claim.policy?.card_number || '').toLowerCase();
            return pat.includes(q) || cNum.includes(q) || card.includes(q);
        }
        return true;
    });
});

const filteredSubmittedClaims = computed(() => {
    return props.submittedClaims.filter(claim => {
        if (selectedProviderFilter.value !== 'all' && claim.policy?.provider?.code !== selectedProviderFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = claim.patient ? `${claim.patient.first_name} ${claim.patient.last_name} ${claim.patient.primary_mrn}`.toLowerCase() : '';
            const cNum = (claim.claim_number || '').toLowerCase();
            const batch = (claim.batch_number || '').toLowerCase();
            return pat.includes(q) || cNum.includes(q) || batch.includes(q);
        }
        return true;
    });
});

const filteredPreAuths = computed(() => {
    return props.preAuthorizations.filter(pa => {
        if (selectedProviderFilter.value !== 'all' && pa.policy?.provider?.code !== selectedProviderFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = pa.patient ? `${pa.patient.first_name} ${pa.patient.last_name}`.toLowerCase() : '';
            const auth = (pa.auth_code || '').toLowerCase();
            return pat.includes(q) || auth.includes(q);
        }
        return true;
    });
});

const filteredPolicies = computed(() => {
    return props.patientPolicies.filter(pol => {
        if (selectedProviderFilter.value !== 'all' && pol.provider?.code !== selectedProviderFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = pol.patient ? `${pol.patient.first_name} ${pol.patient.last_name}`.toLowerCase() : '';
            const card = (pol.card_number || '').toLowerCase();
            return pat.includes(q) || card.includes(q);
        }
        return true;
    });
});

// Selection handler
const selectClaimRecord = (item) => {
    selectedRecord.value = item;
    openContext();
};

const toggleSelectAllClaims = () => {
    if (selectedClaimIds.value.length === filteredClaimsQueue.value.length) {
        selectedClaimIds.value = [];
    } else {
        selectedClaimIds.value = filteredClaimsQueue.value.map(c => c.id);
    }
};

// Modal Openers
const openGenerateClaimModal = () => {
    const firstEnc = props.encountersForClaiming?.[0];
    generateClaimForm.value = {
        encounter_id: firstEnc ? firstEnc.id : '',
        patient_policy_id: firstEnc?.patient?.policies?.[0]?.id || '',
    };
    showGenerateClaimModal.value = true;
};

const openAdjudicateModal = (claim) => {
    adjudicateClaim.value = claim;
    adjudicateForm.value = {
        status: 'Approved',
        approved_amount: claim.total_claimed_amount - claim.co_pay_amount,
        notes: 'Full remittance approved per NHIF/underwriter tariff schedule',
    };
    showAdjudicateModal.value = true;
};

const openPreAuthModal = () => {
    const firstPol = props.patientPolicies?.[0];
    preAuthForm.value = {
        patient_policy_id: firstPol ? firstPol.id : '',
        encounter_id: '',
        procedure_description: 'CT Scan Brain with Contrast / Specialist Referral',
        requested_amount: 150000,
        approved_amount: 150000,
        auth_code: `TAR-${new Date().getFullYear()}-${Math.floor(100000 + Math.random() * 900000)}`,
        expires_at: new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0],
        notes: 'Approved under primary benefit sub-limit',
    };
    showPreAuthModal.value = true;
};

// Action Submissions
const submitGenerateClaim = () => {
    if (!generateClaimForm.value.encounter_id) return;
    isGeneratingClaim.value = true;
    router.post(route('insurance.claims.generate'), generateClaimForm.value, {
        onFinish: () => {
            isGeneratingClaim.value = false;
            showGenerateClaimModal.value = false;
        }
    });
};

const submitAdjudicate = () => {
    if (!adjudicateClaim.value) return;
    isAdjudicating.value = true;
    router.post(route('insurance.claims.adjudicate', adjudicateClaim.value.id), adjudicateForm.value, {
        onFinish: () => {
            isAdjudicating.value = false;
            showAdjudicateModal.value = false;
        }
    });
};

const submitPreAuth = () => {
    isCreatingPreAuth.value = true;
    router.post(route('insurance.pre-auth.store'), preAuthForm.value, {
        onFinish: () => {
            isCreatingPreAuth.value = false;
            showPreAuthModal.value = false;
        }
    });
};

const submitBatchClaims = () => {
    if (selectedClaimIds.value.length === 0) return;
    isSubmittingBatch.value = true;
    router.post(route('insurance.claims.batch-submit'), { claim_ids: selectedClaimIds.value }, {
        onFinish: () => {
            isSubmittingBatch.value = false;
            selectedClaimIds.value = [];
            activeSection.value = 'remittance';
        }
    });
};

const verifyPolicyEligibility = (policy) => {
    isVerifyingPolicy.value = true;
    router.post(route('insurance.policies.verify', policy.id), {}, {
        onFinish: () => {
            isVerifyingPolicy.value = false;
        }
    });
};

const formatCurrency = (val) => Number(val || 0).toLocaleString('en-US');
const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Health Insurance & Claims Management Subsystem — AfyaNova Workstation" />

    <AfyaShell active-module="insurance">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Insurance Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Insurance & Claims"
                    :icon="ShieldCheck"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Claims Desk
                    </div>
                    
                    <AfyaSidebarItem
                        label="Claims Queue & Scrubber"
                        :icon="FileSpreadsheet"
                        :badge="metrics.pending_submission"
                        :active="activeSection === 'claims'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'claims'"
                    />
                    
                    <AfyaSidebarItem
                        label="Remittance & Adjudication"
                        :icon="Receipt"
                        :badge="metrics.awaiting_remittance"
                        :active="activeSection === 'remittance'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'remittance'"
                    />

                    <AfyaSidebarItem
                        label="Pre-Authorization Desk"
                        :icon="Clock"
                        :badge="preAuthorizations.length"
                        :active="activeSection === 'preauth'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'preauth'"
                    />

                    <AfyaSidebarItem
                        label="Policy Eligibility Registry"
                        :icon="Fingerprint"
                        :badge="patientPolicies.length"
                        :active="activeSection === 'policies'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'policies'"
                    />

                    <AfyaSidebarItem
                        label="Insurers & Tariffs"
                        :icon="Building2"
                        :badge="providers.length"
                        :active="activeSection === 'tariffs'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'tariffs'"
                    />

                    <div v-if="state !== 'collapsed'" class="pt-2 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border/40 mt-1">
                        Insurers & Schemes
                    </div>

                    <div v-if="state !== 'collapsed'" class="px-2 space-y-0.5">
                        <button
                            class="w-full text-left px-2 py-1 rounded text-[11px] flex items-center justify-between hover:bg-muted/50 transition"
                            :class="selectedProviderFilter === 'all' ? 'bg-primary/10 text-primary font-bold' : 'text-muted-foreground'"
                            @click="selectedProviderFilter = 'all'"
                        >
                            <span>All Providers</span>
                        </button>
                        <button
                            v-for="prov in providers"
                            :key="prov.id"
                            class="w-full text-left px-2 py-1 rounded text-[11px] flex items-center justify-between hover:bg-muted/50 transition"
                            :class="selectedProviderFilter === prov.code ? 'bg-primary/10 text-primary font-bold' : 'text-muted-foreground'"
                            @click="selectedProviderFilter = prov.code"
                        >
                            <span>{{ prov.name }}</span>
                            <span class="text-[9.5px] font-mono text-muted-foreground">{{ prov.code }}</span>
                        </button>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN WORK AREA -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Insurance', href: route('insurance.workspace') },
                        { label: activeSection === 'claims' ? 'Claims Processing Queue & Scrubber' : (activeSection === 'remittance' ? 'Submitted Claims & Remittance Adjudication' : (activeSection === 'preauth' ? 'Pre-Authorization Approval Registry' : (activeSection === 'policies' ? 'Patient Membership Policy & Biometric Registry' : 'Negotiated Tariffs & Coverage Master'))), active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button 
                                v-if="activeSection === 'claims' && selectedClaimIds.length > 0"
                                variant="default" 
                                size="sm" 
                                class="h-7.5 px-3 text-xs font-semibold gap-1.5 shadow-2xs bg-emerald-700 hover:bg-emerald-800 text-white"
                                @click="submitBatchClaims"
                                :disabled="isSubmittingBatch"
                            >
                                <Loader2 v-if="isSubmittingBatch" class="w-3.5 h-3.5 animate-spin" />
                                <Send v-else class="w-3.5 h-3.5" />
                                <span>Submit Batch ({{ selectedClaimIds.length }})</span>
                            </Button>

                            <Button 
                                v-if="activeSection === 'claims'"
                                variant="default" 
                                size="sm" 
                                class="h-7.5 px-3 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="openGenerateClaimModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Generate Claim</span>
                            </Button>

                            <Button 
                                v-if="activeSection === 'preauth'"
                                variant="default" 
                                size="sm" 
                                class="h-7.5 px-3 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="openPreAuthModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Issue Pre-Auth</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Top Enterprise Metric Strip (Clean Cards, No Outside Border) -->
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Total Claims</div>
                                <div class="text-base font-bold text-foreground font-mono">{{ metrics.total_claims }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Pending Submission</div>
                                <div class="text-base font-bold text-amber-600 dark:text-amber-400 font-mono">{{ metrics.pending_submission }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Awaiting Remittance</div>
                                <div class="text-base font-bold text-primary font-mono">{{ metrics.awaiting_remittance }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Total Claimed (TZS)</div>
                                <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ formatCurrency(metrics.total_claimed_value) }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 col-span-2 sm:col-span-1">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Disputed / Queried</div>
                                <div class="text-base font-bold text-rose-600 dark:text-rose-400 font-mono">{{ metrics.queried_or_disputed }}</div>
                            </div>
                        </div>

                        <!-- Insurer Filter & Search Strip (Seamless Container) -->
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-card p-2 rounded-lg shadow-2xs">
                            <div class="flex items-center gap-1.5 flex-1 max-w-xs">
                                <Search class="w-3.5 h-3.5 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search claim #, card #, or patient..."
                                    class="h-7 text-xs w-full"
                                />
                            </div>
                            
                            <div class="flex items-center gap-1 overflow-x-auto">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-6 px-2 text-[10.5px]"
                                    :class="{ 'bg-primary text-primary-foreground font-semibold': selectedProviderFilter === 'all' }"
                                    @click="selectedProviderFilter = 'all'"
                                >
                                    All Insurers
                                </Button>
                                <Button
                                    v-for="prov in providers"
                                    :key="prov.id"
                                    size="sm"
                                    variant="outline"
                                    class="h-6 px-2 text-[10.5px]"
                                    :class="{ 'bg-primary text-primary-foreground font-semibold': selectedProviderFilter === prov.code }"
                                    @click="selectedProviderFilter = prov.code"
                                >
                                    {{ prov.name }}
                                </Button>
                            </div>
                        </div>

                        <!-- ================= VIEW 1: CLAIMS QUEUE & SCRUBBER ================= -->
                        <div v-if="activeSection === 'claims'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <FileSpreadsheet class="w-3.5 h-3.5 text-primary" />
                                        <span>Claims Submission Queue & Audit Scrubber ({{ filteredClaimsQueue.length }})</span>
                                    </div>
                                    <span v-if="selectedClaimIds.length > 0" class="text-[10px] text-primary font-bold">
                                        {{ selectedClaimIds.length }} Claims Selected for Electronic Batching
                                    </span>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-8 text-center">
                                                <input 
                                                    type="checkbox" 
                                                    :checked="selectedClaimIds.length > 0 && selectedClaimIds.length === filteredClaimsQueue.length"
                                                    @change="toggleSelectAllClaims"
                                                    class="rounded border-input text-primary focus:ring-0" 
                                                />
                                            </TableHead>
                                            <TableHead class="py-1 px-3 w-28">Claim #</TableHead>
                                            <TableHead class="py-1 px-3">Patient & Policy</TableHead>
                                            <TableHead class="py-1 px-3">Insurer & Scheme</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Claim Amount</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Co-Pay</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Claim Scrubber</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="claim in filteredClaimsQueue"
                                            :key="claim.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedRecord?.id === claim.id }"
                                            @click="selectClaimRecord(claim)"
                                        >
                                            <TableCell class="py-1 px-3 text-center w-8" @click.stop>
                                                <input 
                                                    type="checkbox" 
                                                    v-model="selectedClaimIds" 
                                                    :value="claim.id"
                                                    class="rounded border-input text-primary focus:ring-0" 
                                                />
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ claim.claim_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[130px] text-[11px]">
                                                    {{ claim.patient?.first_name }} {{ claim.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">Card: {{ claim.policy?.card_number }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px]">{{ claim.policy?.provider?.name }}</div>
                                                <div class="text-[9.5px] text-muted-foreground">{{ claim.policy?.scheme?.name || 'Standard Tariff' }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-right text-foreground text-[11px]">
                                                TZS {{ formatCurrency(claim.total_claimed_amount) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-right text-muted-foreground text-[10.5px]">
                                                TZS {{ formatCurrency(claim.co_pay_amount) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    v-if="claim.scrubber_passed"
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                                                >
                                                    <Check class="w-2.5 h-2.5 text-emerald-600" />
                                                    <span>Clean Scrubber</span>
                                                </span>
                                                <span 
                                                    v-else
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                                                >
                                                    <AlertTriangle class="w-2.5 h-2.5 text-amber-600" />
                                                    <span>Warnings</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-primary/10 text-primary">
                                                    {{ claim.status }}
                                                </span>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredClaimsQueue.length === 0">
                                            <TableCell colspan="8" class="text-center py-8 text-muted-foreground text-xs">
                                                No draft claims awaiting submission.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 2: REMITTANCE & ADJUDICATION ================= -->
                        <div v-else-if="activeSection === 'remittance'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Receipt class="w-3.5 h-3.5 text-primary" />
                                        <span>Submitted Claims & Remittance Adjudication ({{ filteredSubmittedClaims.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Claim #</TableHead>
                                            <TableHead class="py-1 px-3">Batch Number</TableHead>
                                            <TableHead class="py-1 px-3">Patient</TableHead>
                                            <TableHead class="py-1 px-3">Insurer</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Claimed (TZS)</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Remitted (TZS)</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Adjudication Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="claim in filteredSubmittedClaims"
                                            :key="claim.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedRecord?.id === claim.id }"
                                            @click="selectClaimRecord(claim)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ claim.claim_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">
                                                {{ claim.batch_number || 'INDIVIDUAL' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[130px] text-[11px]">
                                                    {{ claim.patient?.first_name }} {{ claim.patient?.last_name }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ claim.policy?.provider?.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-right text-muted-foreground text-[11px]">
                                                {{ formatCurrency(claim.total_claimed_amount) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-right text-[11px]" :class="claim.approved_amount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">
                                                {{ formatCurrency(claim.approved_amount) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                                    :class="{
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': claim.status === 'Approved' || claim.status === 'Paid',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': claim.status === 'Submitted' || claim.status === 'Partially_Paid',
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': claim.status === 'Rejected' || claim.status === 'Queried',
                                                    }"
                                                >
                                                    {{ claim.status }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10px] font-semibold gap-1 text-primary border-primary/30"
                                                    @click.stop="openAdjudicateModal(claim)"
                                                >
                                                    <DollarSign class="w-3 h-3" />
                                                    <span>Adjudicate</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredSubmittedClaims.length === 0">
                                            <TableCell colspan="8" class="text-center py-8 text-muted-foreground text-xs">
                                                No submitted claims in remittance stream.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 3: PRE-AUTHORIZATION DESK ================= -->
                        <div v-else-if="activeSection === 'preauth'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Clock class="w-3.5 h-3.5 text-primary" />
                                        <span>Prior Authorization Approval Desk (TAR Codes) ({{ filteredPreAuths.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-32">Auth Code</TableHead>
                                            <TableHead class="py-1 px-3">Patient & Card #</TableHead>
                                            <TableHead class="py-1 px-3">Insurer</TableHead>
                                            <TableHead class="py-1 px-3">Specialized Procedure</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Approved Amount</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Expires</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="pa in filteredPreAuths"
                                            :key="pa.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-32">
                                                {{ pa.auth_code }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground text-[11px]">{{ pa.patient?.first_name }} {{ pa.patient?.last_name }}</div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">{{ pa.policy?.card_number }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ pa.policy?.provider?.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-foreground text-[11px]">
                                                {{ pa.procedure_description }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-right text-emerald-600 dark:text-emerald-400 text-[11px]">
                                                TZS {{ formatCurrency(pa.approved_amount) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono text-[10px] text-muted-foreground">
                                                {{ formatDate(pa.expires_at) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                    {{ pa.status }}
                                                </span>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredPreAuths.length === 0">
                                            <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                No pre-authorization approvals issued.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 4: POLICY ELIGIBILITY REGISTRY ================= -->
                        <div v-else-if="activeSection === 'policies'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Fingerprint class="w-3.5 h-3.5 text-primary" />
                                        <span>Patient Insurance Policies & Biometric Registry ({{ filteredPolicies.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-32">Card Number</TableHead>
                                            <TableHead class="py-1 px-3">Patient Member</TableHead>
                                            <TableHead class="py-1 px-3">Insurer Provider</TableHead>
                                            <TableHead class="py-1 px-3">Benefit Scheme</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Biometric Verification</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Verification Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="pol in filteredPolicies"
                                            :key="pol.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-32">
                                                {{ pol.card_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground text-[11px]">{{ pol.patient?.first_name }} {{ pol.patient?.last_name }}</div>
                                                <div class="text-[9.5px] text-muted-foreground">Relation: {{ pol.relationship }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-foreground font-semibold text-[11px]">
                                                {{ pol.provider?.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ pol.scheme?.name || 'Comprehensive' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    v-if="pol.biometric_verified"
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                                                >
                                                    <Fingerprint class="w-2.5 h-2.5 text-emerald-600" />
                                                    <span>Verified</span>
                                                </span>
                                                <span 
                                                    v-else
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                                                >
                                                    <span>Card Only</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                    {{ pol.status }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10px] font-semibold gap-1 text-primary border-primary/30"
                                                    @click="verifyPolicyEligibility(pol)"
                                                >
                                                    <Fingerprint class="w-3 h-3" />
                                                    <span>Re-Verify</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 5: INSURERS & TARIFFS ================= -->
                        <div v-else-if="activeSection === 'tariffs'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Building2 class="w-3.5 h-3.5 text-primary" />
                                        <span>Insurance Providers & Negotiated Schemes ({{ providers.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Provider Code</TableHead>
                                            <TableHead class="py-1 px-3">Underwriter Name</TableHead>
                                            <TableHead class="py-1 px-3">Provider Type</TableHead>
                                            <TableHead class="py-1 px-3">API Adapter</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Schemes</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="prov in providers"
                                            :key="prov.id"
                                            class="h-8.5 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ prov.code }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-bold text-foreground text-[11px]">
                                                {{ prov.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ prov.provider_type }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-muted-foreground text-[10.5px]">
                                                {{ prov.api_adapter }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono text-[10.5px]">
                                                <span class="px-1.5 py-0.2 rounded bg-muted">
                                                    {{ prov.schemes ? prov.schemes.length : 0 }} schemes
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                    Active
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: CLAIM 360 CONTEXT INSPECTOR -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Insurance 360 Inspector"
                    :icon="ShieldCheck"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedRecord" class="space-y-2.5 text-xs">
                        <AfyaPatientIdentity v-if="selectedRecord.patient" :patient="selectedRecord.patient">
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-primary/10 text-primary">
                                {{ selectedRecord.status || 'Active' }}
                            </span>
                        </AfyaPatientIdentity>

                        <!-- Scrubber Audit Breakdown -->
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                <span>Pre-Submission Scrubber</span>
                                <span 
                                    class="text-[9px] font-bold px-1.5 py-0.2 rounded"
                                    :class="selectedRecord.scrubber_passed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300'"
                                >
                                    {{ selectedRecord.scrubber_passed ? '100% Passed' : 'Audit Warnings' }}
                                </span>
                            </div>

                            <div class="space-y-1 text-[10px]">
                                <div class="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                                    <Check class="w-3 h-3 shrink-0" />
                                    <span>Primary ICD-10 diagnosis linked to encounter</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                                    <Check class="w-3 h-3 shrink-0" />
                                    <span>Attending doctor MCT registration attached</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                                    <Check class="w-3 h-3 shrink-0" />
                                    <span>Member card validity & biometrics verified</span>
                                </div>
                            </div>
                        </div>

                        <!-- Policy & Tariff Breakdown -->
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Policy & Financial Summary
                            </div>
                            <div class="space-y-1 text-[10.5px]">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Insurer:</span>
                                    <span class="font-bold text-foreground">{{ selectedRecord.policy?.provider?.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Scheme:</span>
                                    <span class="text-foreground">{{ selectedRecord.policy?.scheme?.name || 'Comprehensive' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Card Number:</span>
                                    <span class="font-mono font-bold text-primary">{{ selectedRecord.policy?.card_number }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Claim Amount:</span>
                                    <span class="font-mono font-bold text-foreground">TZS {{ formatCurrency(selectedRecord.total_claimed_amount) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Patient Co-Pay:</span>
                                    <span class="font-mono text-muted-foreground">TZS {{ formatCurrency(selectedRecord.co_pay_amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Itemized Claim Breakdown -->
                        <div v-if="selectedRecord.items && selectedRecord.items.length > 0" class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Itemized Claim Breakdown ({{ selectedRecord.items.length }})
                            </div>
                            <div class="space-y-1">
                                <div 
                                    v-for="item in selectedRecord.items" 
                                    :key="item.id"
                                    class="p-1.5 bg-muted/30 rounded border border-border/40 flex items-center justify-between text-[10.5px]"
                                >
                                    <div>
                                        <div class="font-semibold text-foreground truncate max-w-[150px]">{{ item.description }}</div>
                                        <div class="text-[9px] text-muted-foreground">{{ item.item_type }} · Qty: {{ item.quantity }}</div>
                                    </div>
                                    <div class="font-mono font-bold text-foreground">
                                        TZS {{ formatCurrency(item.claimed_amount) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Triggers -->
                        <div class="space-y-1.5 pt-1">
                            <Button
                                v-if="selectedRecord.status === 'Submitted'"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs"
                                @click="openAdjudicateModal(selectedRecord)"
                            >
                                <DollarSign class="w-3.5 h-3.5" />
                                <span>Adjudicate / Reconcile Remittance</span>
                            </Button>
                        </div>
                    </div>

                    <div v-else class="text-center py-6 text-muted-foreground text-xs">
                        Select a claim record to inspect telemetry.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- ================= MODAL 1: GENERATE CLAIM FROM ENCOUNTER ================= -->
        <Modal :show="showGenerateClaimModal" max-width="md" @close="showGenerateClaimModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <FileSpreadsheet class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Generate Itemized Insurance Claim</h3>
                    </div>
                    <button @click="showGenerateClaimModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Select Insured Encounter *</label>
                        <select 
                            v-model="generateClaimForm.encounter_id" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option v-for="enc in encountersForClaiming" :key="enc.id" :value="enc.id">
                                {{ enc.patient?.first_name }} {{ enc.patient?.last_name }} — {{ enc.encounter_type }} ({{ enc.reason_for_visit }})
                            </option>
                        </select>
                    </div>

                    <p class="text-[10.5px] text-muted-foreground leading-relaxed">
                        The claim generator will automatically pull billable items (Consultation, Lab, Bed, Pharmacy), calculate co-pay splits per insurer tariff, and run pre-submission audit rules.
                    </p>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showGenerateClaimModal = false" :disabled="isGeneratingClaim">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" @click="submitGenerateClaim" :disabled="isGeneratingClaim || !generateClaimForm.encounter_id">
                            <Loader2 v-if="isGeneratingClaim" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Generate & Run Scrubber</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 2: ADJUDICATE REMITTANCE ================= -->
        <Modal :show="showAdjudicateModal" max-width="md" @close="showAdjudicateModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <DollarSign class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Adjudicate Remittance Advice</h3>
                    </div>
                    <button @click="showAdjudicateModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="adjudicateClaim" class="space-y-3 text-xs">
                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 space-y-1 text-[11px]">
                        <div class="flex justify-between font-bold">
                            <span>Claim #{{ adjudicateClaim.claim_number }}</span>
                            <span class="text-primary font-mono">Claimed: TZS {{ formatCurrency(adjudicateClaim.total_claimed_amount) }}</span>
                        </div>
                        <div class="text-muted-foreground">Patient: {{ adjudicateClaim.patient?.first_name }} {{ adjudicateClaim.patient?.last_name }} ({{ adjudicateClaim.policy?.provider?.name }})</div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Adjudication Outcome *</label>
                        <select 
                            v-model="adjudicateForm.status" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option value="Approved">Approved (Full Remittance)</option>
                            <option value="Partially_Paid">Partially Paid / Short Payment</option>
                            <option value="Queried">Queried (Requires Clinical Justification)</option>
                            <option value="Rejected">Rejected / Disallowed</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Remitted Amount (TZS) *</label>
                        <Input v-model="adjudicateForm.approved_amount" type="number" required class="font-mono h-8.5 text-xs font-bold" />
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Adjudication Notes / Disallowance Reason</label>
                        <Input v-model="adjudicateForm.notes" placeholder="e.g. Remitted per NHIF monthly bank transfer" class="h-8.5 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showAdjudicateModal = false" :disabled="isAdjudicating">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs bg-emerald-700 hover:bg-emerald-800 text-white" @click="submitAdjudicate" :disabled="isAdjudicating">
                            <Loader2 v-if="isAdjudicating" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Confirm Remittance</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 3: ISSUE PRE-AUTHORIZATION ================= -->
        <Modal :show="showPreAuthModal" max-width="md" @close="showPreAuthModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Clock class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Issue Prior Authorization (TAR Code)</h3>
                    </div>
                    <button @click="showPreAuthModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitPreAuth" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Select Patient Policy *</label>
                        <select 
                            v-model="preAuthForm.patient_policy_id" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option v-for="pol in patientPolicies" :key="pol.id" :value="pol.id">
                                {{ pol.patient?.first_name }} {{ pol.patient?.last_name }} — {{ pol.provider?.name }} (Card: {{ pol.card_number }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">TAR / Pre-Auth Code *</label>
                        <Input v-model="preAuthForm.auth_code" required class="font-mono h-8.5 text-xs font-bold text-primary" />
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Specialized Procedure / Investigation *</label>
                        <Input v-model="preAuthForm.procedure_description" required class="h-8.5 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Approved Amount (TZS) *</label>
                            <Input v-model="preAuthForm.approved_amount" type="number" required class="font-mono h-8.5 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Validity Expiry *</label>
                            <Input v-model="preAuthForm.expires_at" type="date" required class="h-8.5 text-xs" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showPreAuthModal = false" :disabled="isCreatingPreAuth">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isCreatingPreAuth || !preAuthForm.auth_code">
                            <Loader2 v-if="isCreatingPreAuth" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Save Pre-Authorization</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

    </AfyaShell>
</template>
