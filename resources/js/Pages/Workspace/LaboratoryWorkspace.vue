<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    FlaskConical, 
    TestTube2, 
    Users, 
    Clock, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    ShieldAlert, 
    Search, 
    Plus, 
    Loader2, 
    Check, 
    X, 
    FileText, 
    Activity, 
    QrCode, 
    Microscope, 
    Stethoscope, 
    Calendar,
    ChevronRight,
    Lock,
    ShieldCheck,
    SlidersHorizontal,
    Send,
    Syringe,
    ArrowUpRight
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
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import AfyaPatientIdentity from '@/Components/Afya/AfyaPatientIdentity.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    labTests: {
        type: Array,
        default: () => [],
    },
    pendingSamples: {
        type: Array,
        default: () => [],
    },
    testingWorklist: {
        type: Array,
        default: () => [],
    },
    completedResults: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({
            total_orders: 0,
            pending_phlebotomy: 0,
            in_testing: 0,
            completed_today: 0,
            critical_alerts: 0,
        }),
    },
});

const { preferences, openContext } = useWorkspacePreferences();

const activeSection = ref('phlebotomy'); // phlebotomy, testing, completed, catalogue
const selectedDepartmentFilter = ref('all');
const searchQuery = ref('');

const selectedItem = ref(
    props.pendingSamples?.[0] || props.testingWorklist?.[0] || props.completedResults?.[0] || null
);

// Modals State
const showCollectModal = ref(false);
const collectItem = ref(null);
const isCollecting = ref(false);
const collectForm = ref({
    specimen_barcode: '',
    technician_remarks: '',
});

const showResultsModal = ref(false);
const resultItem = ref(null);
const isSavingResults = ref(false);
const resultForm = ref({
    results: {},
    technician_remarks: '',
});

const showVerifyModal = ref(false);
const verifyItem = ref(null);
const isVerifying = ref(false);
const verifyForm = ref({
    pathologist_notes: '',
});

const showAddTestModal = ref(false);
const isAddingTest = ref(false);
const addTestForm = ref({
    test_code: '',
    name: '',
    category: 'Hematology',
    specimen_type: 'Whole Blood (EDTA)',
    turnaround_time_minutes: 30,
    price: 15000,
    parameters: [
        { name: 'Result', unit: '', ref_min: null, ref_max: null, panic_low: null, panic_high: null, critical_value: 'Positive' }
    ]
});

// Category list
const departments = computed(() => {
    const deps = new Set(props.labTests.map(t => t.category).filter(Boolean));
    return ['all', ...Array.from(deps)];
});

// Selection handlers
const selectRecord = (item) => {
    selectedItem.value = item;
    openContext();
};

// Filtered lists
const filteredPendingSamples = computed(() => {
    return props.pendingSamples.filter(item => {
        if (selectedDepartmentFilter.value !== 'all' && item.lab_test?.category !== selectedDepartmentFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = item.lab_order?.patient;
            const patName = pat ? `${pat.first_name} ${pat.last_name} ${pat.primary_mrn}`.toLowerCase() : '';
            const testName = (item.lab_test?.name || '').toLowerCase();
            const orderNum = (item.lab_order?.order_number || '').toLowerCase();
            return patName.includes(q) || testName.includes(q) || orderNum.includes(q);
        }
        return true;
    });
});

const filteredTestingWorklist = computed(() => {
    return props.testingWorklist.filter(item => {
        if (selectedDepartmentFilter.value !== 'all' && item.lab_test?.category !== selectedDepartmentFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = item.lab_order?.patient;
            const patName = pat ? `${pat.first_name} ${pat.last_name} ${pat.primary_mrn}`.toLowerCase() : '';
            const testName = (item.lab_test?.name || '').toLowerCase();
            const barcode = (item.specimen_barcode || '').toLowerCase();
            return patName.includes(q) || testName.includes(q) || barcode.includes(q);
        }
        return true;
    });
});

const filteredCompletedResults = computed(() => {
    return props.completedResults.filter(item => {
        if (selectedDepartmentFilter.value !== 'all' && item.lab_test?.category !== selectedDepartmentFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = item.lab_order?.patient;
            const patName = pat ? `${pat.first_name} ${pat.last_name} ${pat.primary_mrn}`.toLowerCase() : '';
            const testName = (item.lab_test?.name || '').toLowerCase();
            const barcode = (item.specimen_barcode || '').toLowerCase();
            return patName.includes(q) || testName.includes(q) || barcode.includes(q);
        }
        return true;
    });
});

const filteredLabTests = computed(() => {
    return props.labTests.filter(test => {
        if (selectedDepartmentFilter.value !== 'all' && test.category !== selectedDepartmentFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            return test.name.toLowerCase().includes(q) || test.test_code.toLowerCase().includes(q) || test.specimen_type.toLowerCase().includes(q);
        }
        return true;
    });
});

// Modal Openers
const openCollectModal = (item) => {
    collectItem.value = item;
    collectForm.value = {
        specimen_barcode: `ACC-${new Date().getFullYear()}-${Math.floor(100000 + Math.random() * 900000)}`,
        technician_remarks: 'Specimen verified, adequate volume, unhemolyzed',
    };
    showCollectModal.value = true;
};

const openResultsModal = (item) => {
    resultItem.value = item;
    const initialResults = {};
    if (item.lab_test?.parameters && Array.isArray(item.lab_test.parameters)) {
        item.lab_test.parameters.forEach(p => {
            initialResults[p.name] = (item.results && item.results[p.name] !== undefined) 
                ? item.results[p.name] 
                : (p.default_value !== undefined ? p.default_value : (p.ref_min ? ((p.ref_min + p.ref_max) / 2).toFixed(1) : 'Negative'));
        });
    } else {
        initialResults['Result'] = item.results?.['Result'] || 'Negative';
    }

    resultForm.value = {
        results: initialResults,
        technician_remarks: item.technician_remarks || 'Analyzed on calibrated automated analyzer',
    };
    showResultsModal.value = true;
};

const openVerifyModal = (item) => {
    verifyItem.value = item;
    verifyForm.value = {
        pathologist_notes: 'Results verified against clinical history and delta checks. Clinically correlated.',
    };
    showVerifyModal.value = true;
};

const openAddTestModal = () => {
    addTestForm.value = {
        test_code: `TEST-${Math.floor(100 + Math.random() * 900)}`,
        name: '',
        category: 'Hematology',
        specimen_type: 'Whole Blood (EDTA)',
        turnaround_time_minutes: 30,
        price: 15000,
        parameters: [
            { name: 'Result', unit: '', ref_min: null, ref_max: null, panic_low: null, panic_high: null, critical_value: 'Positive' }
        ]
    };
    showAddTestModal.value = true;
};

// Actions Submissions
const submitCollectSample = () => {
    if (!collectItem.value) return;
    isCollecting.value = true;
    router.post(route('laboratory.items.collect', collectItem.value.id), collectForm.value, {
        onFinish: () => {
            isCollecting.value = false;
            showCollectModal.value = false;
            activeSection.value = 'testing';
        }
    });
};

const submitSaveResults = () => {
    if (!resultItem.value) return;
    isSavingResults.value = true;
    router.post(route('laboratory.items.results', resultItem.value.id), resultForm.value, {
        onFinish: () => {
            isSavingResults.value = false;
            showResultsModal.value = false;
        }
    });
};

const submitVerifyResults = () => {
    if (!verifyItem.value) return;
    isVerifying.value = true;
    router.post(route('laboratory.items.verify', verifyItem.value.id), verifyForm.value, {
        onFinish: () => {
            isVerifying.value = false;
            showVerifyModal.value = false;
        }
    });
};

const submitAddTest = () => {
    isAddingTest.value = true;
    router.post(route('laboratory.tests.store'), addTestForm.value, {
        onFinish: () => {
            isAddingTest.value = false;
            showAddTestModal.value = false;
        }
    });
};

// Parameter evaluations for UI
const evaluateParamStatus = (param, value) => {
    if (value === undefined || value === null || value === '') return 'normal';
    
    if (isNumeric(value)) {
        const num = parseFloat(value);
        if (param.panic_low !== null && param.panic_low !== undefined && num <= parseFloat(param.panic_low)) return 'critical-low';
        if (param.panic_high !== null && param.panic_high !== undefined && num >= parseFloat(param.panic_high)) return 'critical-high';
        if (param.ref_min !== null && param.ref_min !== undefined && num < parseFloat(param.ref_min)) return 'low';
        if (param.ref_max !== null && param.ref_max !== undefined && num > parseFloat(param.ref_max)) return 'high';
        return 'normal';
    } else {
        if (param.critical_value && String(value).toLowerCase().trim() === String(param.critical_value).toLowerCase().trim()) {
            return 'critical';
        }
        return 'normal';
    }
};

const isNumeric = (n) => !isNaN(parseFloat(n)) && isFinite(n);

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatTime = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (val) => Number(val || 0).toLocaleString('en-US');
</script>

<template>
    <Head title="Laboratory Diagnostic & Pathology Workstation — AfyaNova Workstation" />

    <AfyaShell active-module="laboratory">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Laboratory Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Diagnostics"
                    :icon="FlaskConical"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Laboratory Desk
                    </div>
                    
                    <AfyaSidebarItem
                        label="Phlebotomy Queue"
                        :icon="Syringe"
                        :badge="metrics.pending_phlebotomy"
                        :active="activeSection === 'phlebotomy'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'phlebotomy'"
                    />
                    
                    <AfyaSidebarItem
                        label="Testing Worklist"
                        :icon="Microscope"
                        :badge="metrics.in_testing"
                        :active="activeSection === 'testing'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'testing'"
                    />

                    <AfyaSidebarItem
                        label="Verified Results Registry"
                        :icon="CheckCircle2"
                        :badge="metrics.completed_today"
                        :active="activeSection === 'completed'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'completed'"
                    />

                    <AfyaSidebarItem
                        label="Test Master Catalog"
                        :icon="FileText"
                        :badge="labTests.length"
                        :active="activeSection === 'catalogue'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'catalogue'"
                    />

                    <div v-if="state !== 'collapsed'" class="pt-2 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border/40 mt-1">
                        Departments
                    </div>

                    <div v-if="state !== 'collapsed'" class="px-2 space-y-0.5">
                        <button
                            v-for="dep in departments"
                            :key="dep"
                            class="w-full text-left px-2 py-1 rounded text-[11px] flex items-center justify-between hover:bg-muted/50 transition capitalize"
                            :class="selectedDepartmentFilter === dep ? 'bg-primary/10 text-primary font-bold' : 'text-muted-foreground'"
                            @click="selectedDepartmentFilter = dep"
                        >
                            <span>{{ dep === 'all' ? 'All Departments' : dep }}</span>
                        </button>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN WORK AREA -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Laboratory', href: route('laboratory.workspace') },
                        { label: activeSection === 'phlebotomy' ? 'Phlebotomy & Sample Collection Desk' : (activeSection === 'testing' ? 'Active Analyzer Testing Worklist' : (activeSection === 'completed' ? 'Verified Diagnostic Results Archive' : 'Diagnostic Test Master Catalog')), active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span v-if="metrics.critical_alerts > 0" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800 animate-pulse">
                                <ShieldAlert class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                                <span>{{ metrics.critical_alerts }} Panic Critical Value Alerts</span>
                            </span>

                            <Button 
                                v-if="activeSection === 'catalogue'"
                                variant="default" 
                                size="sm" 
                                class="h-7.5 px-3 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="openAddTestModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Test Profile</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Top Enterprise Metric Strip (Clean Cards, No Outside Border) -->
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Total Orders</div>
                                <div class="text-base font-bold text-foreground font-mono">{{ metrics.total_orders }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Pending Phlebotomy</div>
                                <div class="text-base font-bold text-amber-600 dark:text-amber-400 font-mono">{{ metrics.pending_phlebotomy }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">In Testing Worklist</div>
                                <div class="text-base font-bold text-primary font-mono">{{ metrics.in_testing }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Completed Today</div>
                                <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ metrics.completed_today }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 col-span-2 sm:col-span-1">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Critical Alerts</div>
                                <div class="text-base font-bold text-rose-600 dark:text-rose-400 font-mono">{{ metrics.critical_alerts }}</div>
                            </div>
                        </div>

                        <!-- Department Filter & Search Strip (Seamless Container) -->
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-card p-2 rounded-lg shadow-2xs">
                            <div class="flex items-center gap-1.5 flex-1 max-w-xs">
                                <Search class="w-3.5 h-3.5 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search patient, MRN, accession barcode, or test..."
                                    class="h-7 text-xs w-full"
                                />
                            </div>
                            
                            <div class="flex items-center gap-1 overflow-x-auto">
                                <Button
                                    v-for="dep in departments"
                                    :key="dep"
                                    size="sm"
                                    variant="outline"
                                    class="h-6 px-2 text-[10.5px] capitalize"
                                    :class="{ 'bg-primary text-primary-foreground font-semibold': selectedDepartmentFilter === dep }"
                                    @click="selectedDepartmentFilter = dep"
                                >
                                    {{ dep === 'all' ? 'All Departments' : dep }}
                                </Button>
                            </div>
                        </div>

                        <!-- ================= VIEW 1: PHLEBOTOMY & SAMPLE COLLECTION ================= -->
                        <div v-if="activeSection === 'phlebotomy'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Syringe class="w-3.5 h-3.5 text-primary" />
                                        <span>Phlebotomy & Specimen Collection Queue ({{ filteredPendingSamples.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Order #</TableHead>
                                            <TableHead class="py-1 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1 px-3">Diagnostic Test</TableHead>
                                            <TableHead class="py-1 px-3">Specimen Container</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Priority</TableHead>
                                            <TableHead class="py-1 px-3">Ordering Clinician</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Phlebotomist Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="item in filteredPendingSamples"
                                            :key="item.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedItem?.id === item.id }"
                                            @click="selectRecord(item)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ item.lab_order?.order_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[140px] text-[11px]">
                                                    {{ item.lab_order?.patient?.first_name }} {{ item.lab_order?.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ item.lab_order?.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px]">{{ item.lab_test?.name }}</div>
                                                <div class="text-[9.5px] text-muted-foreground">{{ item.lab_test?.category }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-medium bg-muted text-foreground">
                                                    <TestTube2 class="w-2.5 h-2.5 text-primary" />
                                                    <span>{{ item.lab_test?.specimen_type }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="{
                                                        'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800': item.lab_order?.priority === 'STAT',
                                                        'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800': item.lab_order?.priority === 'Urgent',
                                                        'bg-muted text-foreground': item.lab_order?.priority === 'Routine' || !item.lab_order?.priority,
                                                    }"
                                                >
                                                    {{ item.lab_order?.priority || 'Routine' }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ item.lab_order?.ordering_provider ? `Dr. ${item.lab_order.ordering_provider.first_name} ${item.lab_order.ordering_provider.last_name}` : 'Clinician' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10.5px] font-semibold gap-1 shadow-2xs"
                                                    @click.stop="openCollectModal(item)"
                                                >
                                                    <Syringe class="w-3 h-3" />
                                                    <span>Collect Specimen</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredPendingSamples.length === 0">
                                            <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                No pending specimen collections in queue.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 2: ANALYZER TESTING WORKLIST ================= -->
                        <div v-else-if="activeSection === 'testing'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Microscope class="w-3.5 h-3.5 text-primary" />
                                        <span>Active Analyzer Processing Worklist ({{ filteredTestingWorklist.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-32">Accession Barcode</TableHead>
                                            <TableHead class="py-1 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1 px-3">Diagnostic Test</TableHead>
                                            <TableHead class="py-1 px-3">Department</TableHead>
                                            <TableHead class="py-1 px-3 text-center">TAT Target</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Analyzer Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="item in filteredTestingWorklist"
                                            :key="item.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedItem?.id === item.id }"
                                            @click="selectRecord(item)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-32">
                                                {{ item.specimen_barcode || 'ACC-PENDING' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[140px] text-[11px]">
                                                    {{ item.lab_order?.patient?.first_name }} {{ item.lab_order?.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ item.lab_order?.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px]">{{ item.lab_test?.name }}</div>
                                                <div class="text-[9.5px] text-muted-foreground">{{ item.lab_test?.specimen_type }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ item.lab_test?.category }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-center text-muted-foreground text-[10.5px]">
                                                {{ item.lab_test?.turnaround_time_minutes }}m
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                                    {{ item.status }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10.5px] font-semibold gap-1 shadow-2xs"
                                                    @click.stop="openResultsModal(item)"
                                                >
                                                    <Activity class="w-3 h-3" />
                                                    <span>Enter Findings</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredTestingWorklist.length === 0">
                                            <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                No active investigations currently in analyzer testing.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 3: VERIFIED RESULTS REGISTRY ================= -->
                        <div v-else-if="activeSection === 'completed'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <CheckCircle2 class="w-3.5 h-3.5 text-primary" />
                                        <span>Verified Results & Diagnostic History ({{ filteredCompletedResults.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Barcode</TableHead>
                                            <TableHead class="py-1 px-3">Patient</TableHead>
                                            <TableHead class="py-1 px-3">Investigation</TableHead>
                                            <TableHead class="py-1 px-3">Diagnostic Findings</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Panic Flag</TableHead>
                                            <TableHead class="py-1 px-3">Technician / Pathologist</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="item in filteredCompletedResults"
                                            :key="item.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedItem?.id === item.id }"
                                            @click="selectRecord(item)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-muted-foreground text-[10.5px] w-28">
                                                {{ item.specimen_barcode || 'ACC-RECORD' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[130px] text-[11px]">
                                                    {{ item.lab_order?.patient?.first_name }} {{ item.lab_order?.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ item.lab_order?.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px]">{{ item.lab_test?.name }}</div>
                                                <div class="text-[9.5px] text-muted-foreground">{{ item.lab_test?.category }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-[10.5px] truncate max-w-[200px]">
                                                <span v-if="item.results && typeof item.results === 'object'" class="font-mono">
                                                    {{ Object.entries(item.results).map(([k, v]) => `${k}: ${v}`).join(' · ') }}
                                                </span>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    v-if="item.has_critical_value"
                                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300"
                                                >
                                                    <AlertTriangle class="w-2.5 h-2.5 text-rose-600" />
                                                    <span>CRITICAL</span>
                                                </span>
                                                <span 
                                                    v-else
                                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                                >
                                                    <Check class="w-2.5 h-2.5 text-emerald-600" />
                                                    <span>NORMAL</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[10.5px]">
                                                <div>{{ item.performed_by ? `${item.performed_by.first_name} ${item.performed_by.last_name}` : 'Technologist' }}</div>
                                                <div v-if="item.verified_by" class="text-[9px] text-primary font-semibold flex items-center gap-0.5">
                                                    <ShieldCheck class="w-2.5 h-2.5" />
                                                    <span>Dr. {{ item.verified_by.first_name }} {{ item.verified_by.last_name }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    v-if="!item.verified_by_id"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10px] font-semibold gap-1 text-primary border-primary/30"
                                                    @click.stop="openVerifyModal(item)"
                                                >
                                                    <ShieldCheck class="w-3 h-3" />
                                                    <span>Sign-Off</span>
                                                </Button>
                                                <span v-else class="text-[10px] text-emerald-700 dark:text-emerald-400 font-semibold flex items-center justify-end gap-1">
                                                    <CheckCircle2 class="w-3 h-3" />
                                                    <span>Verified</span>
                                                </span>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredCompletedResults.length === 0">
                                            <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                No verified diagnostic results found.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 4: TEST MASTER CATALOGUE ================= -->
                        <div v-else-if="activeSection === 'catalogue'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <FileText class="w-3.5 h-3.5 text-primary" />
                                        <span>Diagnostic Investigation Master Catalog ({{ filteredLabTests.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Test Code</TableHead>
                                            <TableHead class="py-1 px-3">Test Name</TableHead>
                                            <TableHead class="py-1 px-3">Department</TableHead>
                                            <TableHead class="py-1 px-3">Specimen Tube</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Turnaround</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Parameters</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Standard Fee</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="test in filteredLabTests"
                                            :key="test.id"
                                            class="h-8.5 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[10.5px] w-28">
                                                {{ test.test_code }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-bold text-foreground text-[11px]">
                                                {{ test.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ test.category }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-[10.5px] text-muted-foreground">
                                                {{ test.specimen_type }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-center text-muted-foreground text-[10.5px]">
                                                {{ test.turnaround_time_minutes }}m
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-center text-[10.5px]">
                                                <span class="px-1.5 py-0.2 rounded bg-muted">
                                                    {{ Array.isArray(test.parameters) ? test.parameters.length : 1 }} params
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-right text-foreground text-[11px]">
                                                TZS {{ formatCurrency(test.price) }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: LAB 360 CONTEXT INSPECTOR -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Investigation 360 Inspector"
                    :icon="FlaskConical"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedItem" class="space-y-2.5 text-xs">
                        <AfyaPatientIdentity v-if="selectedItem.lab_order?.patient" :patient="selectedItem.lab_order.patient">
                            <span 
                                class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                :class="selectedItem.has_critical_value ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300' : 'bg-primary/10 text-primary'"
                            >
                                {{ selectedItem.status }}
                            </span>
                        </AfyaPatientIdentity>

                        <!-- Critical Panic Warning Banner -->
                        <div
                            v-if="selectedItem.has_critical_value"
                            class="p-2.5 rounded bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-950 dark:text-rose-200 text-xs space-y-1"
                        >
                            <div class="flex items-center gap-1.5 font-bold text-rose-900 dark:text-rose-300 text-[11px]">
                                <AlertTriangle class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                                <span>CRITICAL PANIC VALUE ALERT</span>
                            </div>
                            <p class="text-[10px] text-rose-800 dark:text-rose-300 leading-relaxed">
                                Diagnostic findings breach biological panic thresholds. Attending clinician has been notified for urgent therapeutic intervention.
                            </p>
                        </div>

                        <!-- Investigation Metadata -->
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Investigation Details
                            </div>
                            <div class="space-y-1 text-[10.5px]">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Test Name:</span>
                                    <span class="font-bold text-foreground">{{ selectedItem.lab_test?.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Department:</span>
                                    <span class="text-foreground">{{ selectedItem.lab_test?.category }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Accession Barcode:</span>
                                    <span class="font-mono font-bold text-primary">{{ selectedItem.specimen_barcode || 'Pending Phlebotomy' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Specimen Tube:</span>
                                    <span class="font-mono text-foreground">{{ selectedItem.lab_test?.specimen_type }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Ordering Doctor:</span>
                                    <span class="font-semibold text-foreground">
                                        {{ selectedItem.lab_order?.ordering_provider ? `Dr. ${selectedItem.lab_order.ordering_provider.first_name} ${selectedItem.lab_order.ordering_provider.last_name}` : 'Clinician' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Parameter Findings & Reference Ranges Breakdown -->
                        <div v-if="selectedItem.results && Object.keys(selectedItem.results).length > 0" class="p-2.5 bg-card rounded-lg shadow-2xs space-y-2">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                <span>Parameter Findings</span>
                                <span class="text-[9px] font-mono text-muted-foreground">Ref Ranges</span>
                            </div>

                            <div class="space-y-1.5">
                                <div 
                                    v-for="(val, paramName) in selectedItem.results" 
                                    :key="paramName"
                                    class="p-1.5 bg-muted/25 rounded border border-border/40 space-y-0.5"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-foreground text-[10.5px]">{{ paramName }}</span>
                                        <span class="font-mono font-bold text-xs" :class="selectedItem.has_critical_value ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'">
                                            {{ val }}
                                        </span>
                                    </div>
                                    <div class="text-[9px] text-muted-foreground flex justify-between">
                                        <span>Status: Normal Range</span>
                                        <span>Target: In bounds</span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="selectedItem.technician_remarks" class="pt-1 border-t border-border/40">
                                <span class="text-[9.5px] text-muted-foreground font-bold uppercase">Technician Remarks:</span>
                                <p class="text-foreground text-[10px] italic mt-0.5">"{{ selectedItem.technician_remarks }}"</p>
                            </div>
                        </div>

                        <!-- Action Triggers -->
                        <div class="space-y-1.5 pt-1">
                            <Button
                                v-if="selectedItem.status === 'Pending' || selectedItem.status === 'Ordered'"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs"
                                @click="openCollectModal(selectedItem)"
                            >
                                <Syringe class="w-3.5 h-3.5" />
                                <span>Collect Specimen</span>
                            </Button>

                            <Button
                                v-else-if="selectedItem.status === 'Sample Collected' || selectedItem.status === 'Testing'"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs"
                                @click="openResultsModal(selectedItem)"
                            >
                                <Activity class="w-3.5 h-3.5" />
                                <span>Enter Analyzer Findings</span>
                            </Button>

                            <Button
                                v-else-if="selectedItem.status === 'Completed' && !selectedItem.verified_by_id"
                                variant="outline"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 text-primary border-primary/30 shadow-2xs"
                                @click="openVerifyModal(selectedItem)"
                            >
                                <ShieldCheck class="w-3.5 h-3.5" />
                                <span>Pathologist Sign-Off</span>
                            </Button>
                        </div>
                    </div>

                    <div v-else class="text-center py-6 text-muted-foreground text-xs">
                        Select an investigation record to preview telemetry.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- ================= MODAL 1: COLLECT SPECIMEN ================= -->
        <Modal :show="showCollectModal" max-width="md" @close="showCollectModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Syringe class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Phlebotomy Sample Collection</h3>
                    </div>
                    <button @click="showCollectModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="collectItem" class="space-y-3 text-xs">
                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 space-y-1 text-[11px]">
                        <div class="font-bold text-foreground text-xs">{{ collectItem.lab_test?.name }}</div>
                        <div class="text-muted-foreground">Required Container: <strong class="text-foreground font-mono">{{ collectItem.lab_test?.specimen_type }}</strong></div>
                        <div class="text-[10px] text-muted-foreground pt-1 border-t border-border/40 flex justify-between">
                            <span>Patient: <strong class="text-foreground">{{ collectItem.lab_order?.patient?.first_name }} {{ collectItem.lab_order?.patient?.last_name }}</strong></span>
                            <span>MRN: <strong class="font-mono text-foreground">{{ collectItem.lab_order?.patient?.primary_mrn }}</strong></span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Generated Accession Barcode *</label>
                        <Input v-model="collectForm.specimen_barcode" required class="font-mono h-8.5 text-xs font-bold text-primary" />
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Phlebotomist Remarks</label>
                        <Input v-model="collectForm.technician_remarks" placeholder="e.g. Clean venipuncture, 5mL EDTA drawn" class="h-8.5 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showCollectModal = false" :disabled="isCollecting">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" @click="submitCollectSample" :disabled="isCollecting || !collectForm.specimen_barcode">
                            <Loader2 v-if="isCollecting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Confirm Specimen & Send to Analyzer</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 2: ENTER ANALYZER RESULTS ================= -->
        <Modal :show="showResultsModal" max-width="lg" @close="showResultsModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Activity class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Enter Analyzer Findings</h3>
                    </div>
                    <button @click="showResultsModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="resultItem" class="space-y-3 text-xs">
                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 flex items-center justify-between text-[11px]">
                        <div>
                            <div class="font-bold text-foreground text-xs">{{ resultItem.lab_test?.name }}</div>
                            <div class="text-muted-foreground">{{ resultItem.lab_test?.category }} · Barcode: <span class="font-mono font-bold text-primary">{{ resultItem.specimen_barcode }}</span></div>
                        </div>
                        <div class="text-right text-[10px] text-muted-foreground">
                            <div>{{ resultItem.lab_order?.patient?.first_name }} {{ resultItem.lab_order?.patient?.last_name }}</div>
                            <div class="font-mono">MRN: {{ resultItem.lab_order?.patient?.primary_mrn }}</div>
                        </div>
                    </div>

                    <!-- Parameter Fields -->
                    <div class="space-y-2 max-h-[320px] overflow-y-auto pr-1">
                        <div 
                            v-for="param in (resultItem.lab_test?.parameters || [{ name: 'Result', unit: '', ref_min: null, ref_max: null }])"
                            :key="param.name"
                            class="p-2.5 rounded-md border border-border/60 bg-card space-y-1.5"
                        >
                            <div class="flex items-center justify-between">
                                <label class="font-bold text-xs text-foreground">{{ param.name }}</label>
                                <span v-if="param.unit" class="text-[10px] font-mono text-muted-foreground">Unit: {{ param.unit }}</span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 items-center">
                                <div class="col-span-2">
                                    <Input
                                        v-model="resultForm.results[param.name]"
                                        required
                                        placeholder="Enter numeric or qualitative value"
                                        class="h-8.5 text-xs font-mono font-bold"
                                    />
                                </div>
                                <div class="text-[10px] text-muted-foreground">
                                    <div v-if="param.ref_min !== null && param.ref_max !== null">
                                        Ref: <span class="font-mono font-semibold">{{ param.ref_min }} - {{ param.ref_max }}</span>
                                    </div>
                                    <div v-if="param.panic_low || param.panic_high" class="text-rose-600 dark:text-rose-400 font-bold text-[9px]">
                                        Panic: &lt;{{ param.panic_low }} / &gt;{{ param.panic_high }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Technician Remarks</label>
                        <Input v-model="resultForm.technician_remarks" placeholder="e.g. Automated hematology analyzer Sysmex XN-550" class="h-8.5 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showResultsModal = false" :disabled="isSavingResults">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" @click="submitSaveResults" :disabled="isSavingResults">
                            <Loader2 v-if="isSavingResults" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Save & Evaluate Panic Thresholds</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 3: PATHOLOGIST SIGN-OFF ================= -->
        <Modal :show="showVerifyModal" max-width="md" @close="showVerifyModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Pathologist Electronic Sign-Off</h3>
                    </div>
                    <button @click="showVerifyModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="verifyItem" class="space-y-3 text-xs">
                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 space-y-1 text-[11px]">
                        <div class="font-bold text-foreground text-xs">{{ verifyItem.lab_test?.name }}</div>
                        <div class="font-mono text-muted-foreground">Findings: {{ JSON.stringify(verifyItem.results) }}</div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Pathologist Clinical Interpretation</label>
                        <textarea
                            v-model="verifyForm.pathologist_notes"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background text-foreground p-2.5 text-xs shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Enter pathology verification commentary..."
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showVerifyModal = false" :disabled="isVerifying">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs bg-emerald-700 hover:bg-emerald-800 text-white" @click="submitVerifyResults" :disabled="isVerifying">
                            <Loader2 v-if="isVerifying" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Sign & Lock Diagnostic Result</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 4: ADD TEST CATALOG PROFILE ================= -->
        <Modal :show="showAddTestModal" max-width="md" @close="showAddTestModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <FileText class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Add Diagnostic Test Profile</h3>
                    </div>
                    <button @click="showAddTestModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitAddTest" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Test Code *</label>
                            <Input v-model="addTestForm.test_code" required class="font-mono h-8.5 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Department *</label>
                            <select 
                                v-model="addTestForm.category" 
                                required
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                            >
                                <option value="Hematology">Hematology</option>
                                <option value="Parasitology">Parasitology</option>
                                <option value="Clinical Chemistry">Clinical Chemistry</option>
                                <option value="Microbiology">Microbiology</option>
                                <option value="Urinalysis">Urinalysis</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Test Name *</label>
                        <Input v-model="addTestForm.name" required placeholder="e.g. Serum Potassium" class="h-8.5 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Specimen Container *</label>
                            <Input v-model="addTestForm.specimen_type" required placeholder="e.g. Serum (Gold Top)" class="h-8.5 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Turnaround Time (min) *</label>
                            <Input v-model="addTestForm.turnaround_time_minutes" type="number" required class="font-mono h-8.5 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Standard Fee (TZS) *</label>
                        <Input v-model="addTestForm.price" type="number" required class="font-mono h-8.5 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showAddTestModal = false" :disabled="isAddingTest">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isAddingTest || !addTestForm.name">
                            <Loader2 v-if="isAddingTest" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Save Test Profile</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

    </AfyaShell>
</template>
