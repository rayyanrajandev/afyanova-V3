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
    CreditCard,
    ArrowUpRight,
    Pill,
    Receipt,
    Scissors,
    Scan,
    LayoutDashboard,
    Package,
    Sparkles,
    Zap,
    RotateCcw,
    AlertCircle,
    CheckCircle,
    Info,
    Edit3,
    Trash2,
    Power,
    PowerOff,
    Printer
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';
import SpecimenLabelPrint from '@/Components/Print/SpecimenLabelPrint.vue';
import { useHospitalAudio } from '@/Composables/useHospitalAudio';

// UI Primitives
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
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
    can: {
        type: Object,
        default: () => ({}),
    },
    labTests: {
        type: Array,
        default: () => [],
    },
    labConsumables: {
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

const printingSample = ref(null);
const { playPhlebotomyChime, playCriticalAlarm } = useHospitalAudio();

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

// Payment & Billing Clearance Check for Laboratory Orders
const getLabPaymentStatus = (item) => {
    if (!item) return { isPaid: true, label: 'Unbilled', type: 'unbilled' };

    // 1. Check if patient has active insurance policy (NHIF / Private)
    const policies = item.lab_order?.patient?.policies || [];
    const activePolicy = policies.find(p => p.status === 'Active' || p.status === 'Verified');
    if (activePolicy) {
        return {
            isPaid: true,
            label: `${activePolicy.insurance_company?.name || 'NHIF'} Covered`,
            type: 'insured',
        };
    }

    // 2. If STAT / Emergency priority, allow immediate clinical testing
    if (item.lab_order?.priority === 'STAT') {
        return {
            isPaid: true,
            label: 'Emergency STAT Bypass',
            type: 'emergency',
        };
    }

    // 3. Check encounter invoices for cash settlement
    const invoices = item.lab_order?.encounter?.invoices || [];
    if (invoices.length === 0) {
        return {
            isPaid: true,
            label: 'No Fee Billed',
            type: 'unbilled',
        };
    }

    const unpaidInvoices = invoices.filter(inv => inv.status !== 'Paid');
    if (unpaidInvoices.length === 0) {
        return {
            isPaid: true,
            label: 'Paid (Cashier Cleared)',
            type: 'paid',
        };
    }

    const unpaidTotal = unpaidInvoices.reduce((acc, inv) => acc + (Number(inv.total_amount) - Number(inv.paid_amount || 0)), 0);
    return {
        isPaid: false,
        label: `Unpaid TZS ${unpaidTotal.toLocaleString()}`,
        type: 'unpaid',
    };
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

const catalogStatusFilter = ref('all');

const filteredLabTests = computed(() => {
    return props.labTests.filter(test => {
        if (catalogStatusFilter.value === 'active' && !test.is_active) {
            return false;
        }
        if (catalogStatusFilter.value === 'draft' && test.is_active) {
            return false;
        }
        if (selectedDepartmentFilter.value !== 'all' && test.category !== selectedDepartmentFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            return test.name.toLowerCase().includes(q) || test.test_code.toLowerCase().includes(q) || (test.specimen_type || '').toLowerCase().includes(q);
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

// Parameter Live Clinical Status Evaluator
const getParameterLiveStatus = (param, val) => {
    if (val === '' || val === undefined || val === null) {
        return { status: 'empty', label: 'Pending', class: 'bg-muted text-muted-foreground', isPanic: false };
    }

    const strVal = String(val).trim();

    // 1. Numeric Analysis
    if (!isNaN(Number(strVal)) && strVal !== '') {
        const num = parseFloat(strVal);
        // Check Panic Low
        if (param.panic_low !== null && param.panic_low !== undefined && num <= parseFloat(param.panic_low)) {
            return { status: 'panic_low', label: `PANIC LOW (< ${param.panic_low})`, class: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 font-bold border border-rose-300 animate-pulse', isPanic: true };
        }
        // Check Panic High
        if (param.panic_high !== null && param.panic_high !== undefined && num >= parseFloat(param.panic_high)) {
            return { status: 'panic_high', label: `PANIC HIGH (> ${param.panic_high})`, class: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 font-bold border border-rose-300 animate-pulse', isPanic: true };
        }
        // Check Reference Min
        if (param.min !== null && param.min !== undefined && num < parseFloat(param.min)) {
            return { status: 'low', label: 'LOW', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-semibold border border-amber-300', isPanic: false };
        }
        if (param.ref_min !== null && param.ref_min !== undefined && num < parseFloat(param.ref_min)) {
            return { status: 'low', label: 'LOW', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-semibold border border-amber-300', isPanic: false };
        }
        // Check Reference Max
        if (param.max !== null && param.max !== undefined && num > parseFloat(param.max)) {
            return { status: 'high', label: 'HIGH', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-semibold border border-amber-300', isPanic: false };
        }
        if (param.ref_max !== null && param.ref_max !== undefined && num > parseFloat(param.ref_max)) {
            return { status: 'high', label: 'HIGH', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-semibold border border-amber-300', isPanic: false };
        }
        // Within Normal Range
        if ((param.ref_min !== null && param.ref_max !== null) || (param.min !== null && param.max !== null)) {
            return { status: 'normal', label: 'NORMAL', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-semibold border border-emerald-300', isPanic: false };
        }
    }

    // 2. Qualitative & Textual Findings
    const lowStr = strVal.toLowerCase();
    
    // Check critical string trigger
    if (param.critical_value && (strVal.toLowerCase() === String(param.critical_value).toLowerCase() || lowStr.includes(String(param.critical_value).toLowerCase()))) {
        return { status: 'critical', label: 'CRITICAL POSITIVE', class: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 font-bold border border-rose-300 animate-pulse', isPanic: true };
    }

    // Known positive/reactive keywords
    if (
        (lowStr.includes('reactive') && !lowStr.includes('non')) ||
        lowStr.includes('positive') ||
        lowStr.includes('+++') ||
        lowStr.includes('++++') ||
        lowStr.includes('heavy') ||
        (lowStr.includes('seen') && !lowStr.includes('no') && !lowStr.includes('not'))
    ) {
        return { status: 'abnormal', label: 'POSITIVE / REACTIVE', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-semibold border border-amber-300', isPanic: false };
    }

    // Known normal/negative keywords
    if (
        lowStr.includes('negative') ||
        lowStr.includes('non-reactive') ||
        lowStr.includes('nps') ||
        lowStr.includes('no ') ||
        lowStr.includes('not seen') ||
        lowStr.includes('normal') ||
        lowStr.includes('nil') ||
        lowStr.includes('absent')
    ) {
        return { status: 'normal', label: 'NEGATIVE / NORMAL', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-semibold border border-emerald-300', isPanic: false };
    }

    return { status: 'documented', label: 'DOCUMENTED', class: 'bg-muted text-foreground font-mono text-[10px]', isPanic: false };
};

// Quick-Preset Choices per analyte for Tanzania Standard Tests
const getQuickPresetsForParameter = (param, testName = '') => {
    const pName = (param.name || '').toLowerCase();
    const tName = (testName || '').toLowerCase();

    if (pName.includes('malaria') || tName.includes('malaria') || tName.includes('mrdt')) {
        if (pName.includes('count') || (pName.includes('parasite') && !pName.includes('antigen'))) {
            return ['0', '500', '2500', '15000', '50000'];
        }
        return ['Negative', 'Positive (P. falciparum)', 'Positive (Pan)', 'Invalid'];
    }

    if (pName.includes('mps') || pName.includes('blood smear') || pName.includes('blood film')) {
        return ['No Parasites Seen (NPS)', '1+ (1-10/100F)', '2+ (11-100/100F)', '3+ (1-10/F)', '4+ (>10/F)'];
    }

    if (pName.includes('hiv') || tName.includes('hiv')) {
        return ['Non-Reactive', 'Reactive (Requires Confirmation)', 'Indeterminate'];
    }

    if (pName.includes('blood group') || pName.includes('abo')) {
        return ['O Positive (O+)', 'A Positive (A+)', 'B Positive (B+)', 'AB Positive (AB+)', 'O Negative (O-)', 'A Negative (A-)', 'B Negative (B-)', 'AB Negative (AB-)'];
    }

    if (pName.includes('rhesus') || pName.includes('rh')) {
        return ['Positive (Rh+)', 'Negative (Rh-)'];
    }

    if (pName.includes('syphilis') || pName.includes('vdrl') || pName.includes('rpr') || pName.includes('treponema')) {
        return ['Non-Reactive', 'Reactive (1:8)', 'Reactive (1:16)', 'Reactive (1:32)'];
    }

    if (pName.includes('hbsag') || pName.includes('hcv') || pName.includes('hepatitis') || pName.includes('upt') || pName.includes('pregnancy') || pName.includes('hcg')) {
        return ['Negative', 'Positive'];
    }

    if (pName.includes('typhoid') || pName.includes('widal')) {
        return ['Negative', 'Positive', '< 1:80', '1:160 (Active)', '1:320 (High Titre)'];
    }

    if (pName.includes('afb') || pName.includes('tb') || pName.includes('sputum')) {
        return ['No Acid Fast Bacilli Seen', '1+ (10-99/100F)', '2+ (1-10/F)', '3+ (>10/F)'];
    }

    if (pName.includes('protein') || pName.includes('glucose') || pName.includes('ketone') || pName.includes('leukocyte') || pName.includes('nitrite') || pName.includes('blood') || pName.includes('bilirubin') || pName.includes('urobilinogen')) {
        return ['Negative', 'Trace', '1+', '2+', '3+', '4+'];
    }

    if (pName.includes('pus') || pName.includes('rbc') || pName.includes('epithelial')) {
        return ['0 - 2 /HPF', '2 - 5 /HPF', '5 - 10 /HPF', '10 - 20 /HPF', 'Overcrowded /HPF'];
    }

    if (pName.includes('ova') || pName.includes('parasite') || pName.includes('cyst') || pName.includes('stool')) {
        return ['No Ova or Cysts Seen', 'E. histolytica Cysts', 'Giardia lamblia', 'Hookworm Ova', 'Ascaris lumbricoides'];
    }

    if (param.type === 'qualitative' || (!param.unit && !param.min && !param.ref_min)) {
        return ['Negative', 'Positive'];
    }

    return [];
};

// Auto-Fill All Normal Values in 1-Click
const applyAllNormalFindings = () => {
    if (!resultItem.value || !resultItem.value.lab_test) return;
    const params = resultItem.value.lab_test.parameters || [{ name: 'Result' }];
    params.forEach(p => {
        const pName = (p.name || '').toLowerCase();
        if (p.normal) {
            resultForm.value.results[p.name] = p.normal;
        } else if (pName.includes('blood group') || pName.includes('abo')) {
            resultForm.value.results[p.name] = resultForm.value.results[p.name] || 'O Positive (O+)';
        } else if (pName.includes('rhesus') || pName.includes('rh')) {
            resultForm.value.results[p.name] = 'Positive (Rh+)';
        } else if (p.ref_min !== null && p.ref_min !== undefined && p.ref_max !== null && p.ref_max !== undefined) {
            resultForm.value.results[p.name] = String(((parseFloat(p.ref_min) + parseFloat(p.ref_max)) / 2).toFixed(1));
        } else if (p.min !== null && p.min !== undefined && p.max !== null && p.max !== undefined) {
            resultForm.value.results[p.name] = String(((parseFloat(p.min) + parseFloat(p.max)) / 2).toFixed(1));
        } else if (p.type === 'qualitative' || (!p.unit && !p.min && !p.ref_min)) {
            resultForm.value.results[p.name] = pName.includes('hiv') || pName.includes('syphilis') ? 'Non-Reactive' : 'Negative';
        } else {
            resultForm.value.results[p.name] = 'Normal';
        }
    });
};

// Clear All Findings
const clearAllFindings = () => {
    if (!resultItem.value || !resultItem.value.lab_test) return;
    const params = resultItem.value.lab_test.parameters || [{ name: 'Result' }];
    params.forEach(p => {
        resultForm.value.results[p.name] = '';
    });
};

// Set preset technician remarks
const setPresetRemarks = (text) => {
    resultForm.value.technician_remarks = text;
};

// Count of Active Live Panic Values
const livePanicCount = computed(() => {
    if (!resultItem.value || !resultItem.value.lab_test?.parameters) return 0;
    let count = 0;
    const params = resultItem.value.lab_test.parameters;
    params.forEach(p => {
        const val = resultForm.value.results?.[p.name];
        const status = getParameterLiveStatus(p, val);
        if (status.isPanic) count++;
    });
    return count;
});

const openVerifyModal = (item) => {
    verifyItem.value = item;
    verifyForm.value = {
        pathologist_notes: 'Results verified against clinical history and delta checks. Clinically correlated.',
    };
    showVerifyModal.value = true;
};

const editingTest = ref(null);
const isTogglingStatus = ref(null);

const standardContainers = [
    { label: '🟣 Whole Blood (EDTA Purple Top)', value: 'Whole Blood (EDTA Purple Top)', code: 'MSD-LAB-EDTA-01' },
    { label: '🟡 Serum Separator Tube - SST (Gold Top)', value: 'Serum Separator Tube - SST (Gold Top)', code: 'MSD-LAB-SST-01' },
    { label: '🔴 Plain Clot Activator (Red Top)', value: 'Plain Clot Activator (Red Top)', code: 'MSD-LAB-RED-01' },
    { label: '🔵 Sodium Citrate 3.2% (Light Blue Top)', value: 'Sodium Citrate (Light Blue Top)', code: 'MSD-LAB-CIT-01' },
    { label: '⚪ Fluoride Oxalate (Grey Top)', value: 'Fluoride Oxalate (Grey Top)', code: 'MSD-LAB-GLU-01' },
    { label: '🧫 Sterile Urine Container (Yellow Top)', value: 'Sterile Urine Container (Yellow Top)', code: 'MSD-LAB-URI-01' },
    { label: '📦 Stool Container with Spoon (Brown Top)', value: 'Stool Container with Spoon (Brown Top)', code: 'MSD-LAB-STL-01' },
    { label: '🥛 Sputum Container (Screw Cap)', value: 'Sputum Container (Screw Cap)', code: 'MSD-LAB-SPT-01' },
    { label: '🧪 Sterile Swab / Transport Medium', value: 'Sterile Swab / Transport Medium', code: 'MSD-LAB-SWB-01' },
];

const onContainerSelected = (val) => {
    addTestForm.value.specimen_type = val;
    const match = standardContainers.find(c => c.value === val);
    if (match && props.labConsumables) {
        const item = props.labConsumables.find(i => i.item_code === match.code);
        if (item) {
            addTestForm.value.inventory_item_id = item.id;
        }
    }
};

const openAddTestModal = () => {
    editingTest.value = null;
    const defaultEdta = props.labConsumables.find(i => i.item_code === 'MSD-LAB-EDTA-01');
    addTestForm.value = {
        test_code: `TEST-${Math.floor(100 + Math.random() * 900)}`,
        name: '',
        category: 'Hematology',
        specimen_type: 'Whole Blood (EDTA Purple Top)',
        inventory_item_id: defaultEdta ? defaultEdta.id : null,
        turnaround_time_minutes: 30,
        price: 15000,
        is_active: true,
        parameters: [
            { name: 'Result', unit: '', ref_min: null, ref_max: null, panic_low: null, panic_high: null, critical_value: 'Positive' }
        ]
    };
    showAddTestModal.value = true;
};

const openEditTestModal = (test) => {
    editingTest.value = test;
    const copiedParams = Array.isArray(test.parameters) && test.parameters.length > 0
        ? JSON.parse(JSON.stringify(test.parameters))
        : [{ name: 'Result', unit: '', ref_min: null, ref_max: null, panic_low: null, panic_high: null, critical_value: 'Positive' }];

    addTestForm.value = {
        test_code: test.test_code,
        name: test.name,
        category: test.category,
        specimen_type: test.specimen_type,
        inventory_item_id: test.inventory_item_id || null,
        turnaround_time_minutes: test.turnaround_time_minutes,
        price: test.price,
        is_active: Boolean(test.is_active),
        parameters: copiedParams,
    };
    showAddTestModal.value = true;
};

const addParameterRow = () => {
    if (!Array.isArray(addTestForm.value.parameters)) {
        addTestForm.value.parameters = [];
    }
    addTestForm.value.parameters.push({
        name: '',
        unit: '',
        ref_min: null,
        ref_max: null,
        panic_low: null,
        panic_high: null,
        critical_value: ''
    });
};

const removeParameterRow = (idx) => {
    if (addTestForm.value.parameters.length > 1) {
        addTestForm.value.parameters.splice(idx, 1);
    }
};

const submitAddOrUpdateTest = () => {
    isAddingTest.value = true;
    if (editingTest.value) {
        router.put(route('laboratory.tests.update', editingTest.value.id), addTestForm.value, {
            onFinish: () => {
                isAddingTest.value = false;
                showAddTestModal.value = false;
                editingTest.value = null;
            }
        });
    } else {
        router.post(route('laboratory.tests.store'), addTestForm.value, {
            onFinish: () => {
                isAddingTest.value = false;
                showAddTestModal.value = false;
            }
        });
    }
};

const toggleTestStatus = (test) => {
    isTogglingStatus.value = test.id;
    router.patch(route('laboratory.tests.toggle-status', test.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isTogglingStatus.value = null;
        }
    });
};

// Actions Submissions
const submitCollectSample = () => {
    if (!collectItem.value) return;
    isCollecting.value = true;
    router.post(route('laboratory.items.collect', collectItem.value.id), collectForm.value, {
        onSuccess: () => {
            playPhlebotomyChime();
            if (collectItem.value) {
                printingSample.value = {
                    ...collectItem.value,
                    specimen_barcode: collectForm.value.specimen_barcode || collectItem.value.specimen_barcode,
                    collected_at: new Date().toISOString()
                };
            }
        },
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
        onSuccess: () => {
            // Check if any entered parameter is panic/critical
            const hasCritical = (resultForm.value.results || []).some(r => {
                const param = (resultItem.value.test?.parameters || []).find(p => p.name === r.name);
                if (!param) return false;
                const status = evaluateParamStatus(param, r.value);
                return status === 'critical' || status === 'critical-high' || status === 'critical-low';
            });
            if (hasCritical) {
                playCriticalAlarm();
            }
        },
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

// Forensic Result Amendment Logic
const showAmendModal = ref(false);
const amendItem = ref(null);
const isAmending = ref(false);
const amendForm = ref({
    results: {},
    amendment_reason: '',
});

const openAmendModal = (item) => {
    amendItem.value = item;
    const initialResults = {};
    if (item.lab_test?.parameters && Array.isArray(item.lab_test.parameters)) {
        item.lab_test.parameters.forEach(p => {
            initialResults[p.name] = (item.results && item.results[p.name] !== undefined)
                ? item.results[p.name]
                : (p.default_value !== undefined ? p.default_value : 'Negative');
        });
    } else if (item.results && typeof item.results === 'object') {
        Object.assign(initialResults, item.results);
    } else {
        initialResults['Result'] = 'Negative';
    }

    amendForm.value = {
        results: initialResults,
        amendment_reason: '',
    };
    showAmendModal.value = true;
};

const submitAmendResults = () => {
    if (!amendItem.value || !amendForm.value.amendment_reason) return;
    isAmending.value = true;
    router.post(route('laboratory.items.amend', amendItem.value.id), amendForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showAmendModal.value = false;
            amendItem.value = null;
        },
        onFinish: () => {
            isAmending.value = false;
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
                            <span 
                                v-if="metrics.critical_alerts > 0" 
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800 animate-pulse cursor-pointer hover:opacity-90 transition select-none"
                                @click="activeSection = 'completed'"
                                title="Click to view critical alerts in verified results"
                            >
                                <ShieldAlert class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                                <span>{{ metrics.critical_alerts }} Panic Critical Value Alerts</span>
                            </span>

                            <Button
                                v-if="activeSection === 'catalogue' && can.storeTest"
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
                        
                        <!-- Top Enterprise Metric Strip (Clean Clickable Cards) -->
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                            
                            <!-- 1. Total Orders -->
                            <div 
                                @click="activeSection = 'phlebotomy'; selectedDepartmentFilter = 'all'; searchQuery = ''"
                                class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 cursor-pointer hover:bg-muted/50 transition-all select-none group"
                                :class="activeSection === 'phlebotomy' && selectedDepartmentFilter === 'all' && !searchQuery ? 'ring-2 ring-primary/60 bg-primary/5' : ''"
                                title="View all lab orders"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[9.5px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Total Orders</span>
                                    <FlaskConical class="w-3 h-3 text-muted-foreground flex-shrink-0" />
                                </div>
                                <div class="text-base font-bold text-foreground font-mono">{{ metrics.total_orders }}</div>
                            </div>

                            <!-- 2. Pending Phlebotomy -->
                            <div 
                                @click="activeSection = 'phlebotomy'"
                                class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 cursor-pointer hover:bg-muted/50 transition-all select-none group"
                                :class="activeSection === 'phlebotomy' ? 'ring-2 ring-amber-500/80 bg-amber-50/20 dark:bg-amber-950/20' : ''"
                                title="Switch to Phlebotomy Queue"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[9.5px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Pending Phlebotomy</span>
                                    <Syringe class="w-3 h-3 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                                </div>
                                <div class="text-base font-bold text-amber-600 dark:text-amber-400 font-mono">{{ metrics.pending_phlebotomy }}</div>
                            </div>

                            <!-- 3. In Testing Worklist -->
                            <div 
                                @click="activeSection = 'testing'"
                                class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 cursor-pointer hover:bg-muted/50 transition-all select-none group"
                                :class="activeSection === 'testing' ? 'ring-2 ring-primary bg-primary/10' : ''"
                                title="Switch to Analyzer Testing Worklist"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[9.5px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">In Testing Worklist</span>
                                    <Microscope class="w-3 h-3 text-primary flex-shrink-0" />
                                </div>
                                <div class="text-base font-bold text-primary font-mono">{{ metrics.in_testing }}</div>
                            </div>

                            <!-- 4. Completed Today -->
                            <div 
                                @click="activeSection = 'completed'"
                                class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 cursor-pointer hover:bg-muted/50 transition-all select-none group"
                                :class="activeSection === 'completed' ? 'ring-2 ring-emerald-500/80 bg-emerald-50/20 dark:bg-emerald-950/20' : ''"
                                title="Switch to Verified Results Registry"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[9.5px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Completed Today</span>
                                    <CheckCircle2 class="w-3 h-3 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                                </div>
                                <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ metrics.completed_today }}</div>
                            </div>

                            <!-- 5. Critical Alerts -->
                            <div 
                                @click="activeSection = 'completed'"
                                class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 col-span-2 sm:col-span-1 cursor-pointer hover:bg-muted/50 transition-all select-none group"
                                :class="metrics.critical_alerts > 0 ? 'ring-2 ring-rose-500/80 bg-rose-50/20 dark:bg-rose-950/20' : ''"
                                title="View Critical Panic Alerts"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[9.5px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Critical Alerts</span>
                                    <AlertTriangle class="w-3 h-3 text-rose-600 dark:text-rose-400 flex-shrink-0" :class="metrics.critical_alerts > 0 ? 'animate-pulse' : ''" />
                                </div>
                                <div class="text-base font-bold text-rose-600 dark:text-rose-400 font-mono">{{ metrics.critical_alerts }}</div>
                            </div>
                        </div>

                        <!-- Department Filter & Search Strip (Seamless Container) -->
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-card p-2 rounded-lg shadow-2xs">
                            <div class="flex items-center gap-1.5 flex-1 max-w-xs">
                                <SearchInput
                                    v-model="searchQuery"
                                    placeholder="Search patient, MRN, accession barcode, or test..."
                                    size="sm"
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
                                            <TableHead class="py-1 px-3">Billing Clearance</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Priority</TableHead>
                                            <TableHead class="py-1 px-3">Ordering Clinician</TableHead>
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[150px]">Phlebotomist Action</TableHead>
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
                                                <div class="text-[9.5px] text-muted-foreground">{{ item.lab_test?.specimen_type }} ({{ item.lab_test?.category }})</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <span 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="getLabPaymentStatus(item).classes"
                                                >
                                                    <Lock v-if="!getLabPaymentStatus(item).isPaid" class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400" />
                                                    <span>{{ getLabPaymentStatus(item).label }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="{
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300': item.lab_order?.priority === 'Emergency',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': item.lab_order?.priority === 'Urgent',
                                                        'bg-muted text-foreground': item.lab_order?.priority === 'Routine' || !item.lab_order?.priority,
                                                    }"
                                                >
                                                    {{ item.lab_order?.priority || 'Routine' }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ item.lab_order?.ordering_provider ? `Dr. ${item.lab_order.ordering_provider.first_name} ${item.lab_order.ordering_provider.last_name}` : 'Clinician' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[150px]">
                                                <Button
                                                    v-if="can.collectSample"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10.5px] font-semibold gap-1 shadow-2xs"
                                                    :disabled="!getLabPaymentStatus(item).isPaid"
                                                    :title="!getLabPaymentStatus(item).isPaid ? 'Payment required at Cashier Desk before collecting specimen' : 'Collect Specimen'"
                                                    @click.stop="openCollectModal(item)"
                                                >
                                                    <Syringe class="w-3 h-3" />
                                                    <span>{{ getLabPaymentStatus(item).isPaid ? 'Collect Specimen' : 'Pending Payment' }}</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredPendingSamples.length === 0">
                                            <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                No pending specimen collection orders.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 2: TESTING IN PROGRESS WORKLIST ================= -->
                        <div v-else-if="activeSection === 'testing'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Activity class="w-3.5 h-3.5 text-primary" />
                                        <span>Active Analyzer Testing Worklist ({{ filteredTestingWorklist.length }})</span>
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
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[140px]">Analyzer Action</TableHead>
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
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[140px]">
                                                <Button
                                                    v-if="can.saveResults"
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
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[130px]">Actions</TableHead>
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
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[150px]">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        v-if="!item.verified_by_id && can.verifyResults"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10px] font-semibold gap-1 text-primary border-primary/30"
                                                        @click.stop="openVerifyModal(item)"
                                                    >
                                                        <ShieldCheck class="w-3 h-3" />
                                                        <span>Sign-Off</span>
                                                    </Button>
                                                    <span v-else class="text-[10px] text-emerald-700 dark:text-emerald-400 font-semibold flex items-center gap-0.5">
                                                        <CheckCircle2 class="w-3 h-3" />
                                                        <span>Verified</span>
                                                    </span>

                                                    <Button
                                                        v-if="can.amendResults"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10px] font-semibold gap-1 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/40"
                                                        @click.stop="openAmendModal(item)"
                                                        title="Amend verified diagnostic findings with audit trail"
                                                    >
                                                        <Edit3 class="w-3 h-3" />
                                                        <span>Amend</span>
                                                    </Button>
                                                </div>
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
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <FileText class="w-3.5 h-3.5 text-primary" />
                                        <span>Diagnostic Investigation Master Catalog ({{ filteredLabTests.length }})</span>
                                    </div>

                                    <!-- Status Filter Tabs -->
                                    <div class="flex items-center gap-1 bg-muted/60 p-0.5 rounded-md text-[10px]">
                                        <button
                                            type="button"
                                            @click="catalogStatusFilter = 'all'"
                                            class="px-2 py-0.5 rounded font-semibold transition-colors cursor-pointer"
                                            :class="catalogStatusFilter === 'all' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                        >
                                            All ({{ props.labTests.length }})
                                        </button>
                                        <button
                                            type="button"
                                            @click="catalogStatusFilter = 'active'"
                                            class="px-2 py-0.5 rounded font-semibold transition-colors cursor-pointer"
                                            :class="catalogStatusFilter === 'active' ? 'bg-card text-emerald-700 dark:text-emerald-400 shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                        >
                                            Active / Live ({{ props.labTests.filter(t => t.is_active).length }})
                                        </button>
                                        <button
                                            type="button"
                                            @click="catalogStatusFilter = 'draft'"
                                            class="px-2 py-0.5 rounded font-semibold transition-colors cursor-pointer"
                                            :class="catalogStatusFilter === 'draft' ? 'bg-card text-amber-700 dark:text-amber-400 shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                        >
                                            Draft / Inactive ({{ props.labTests.filter(t => !t.is_active).length }})
                                        </button>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Test Code</TableHead>
                                            <TableHead class="py-1 px-3">Test Name</TableHead>
                                            <TableHead class="py-1 px-3">Department</TableHead>
                                            <TableHead class="py-1 px-3">Specimen Container</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Turnaround</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Parameters</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Standard Fee</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Clinical Status</TableHead>
                                            <TableHead v-if="can.storeTest" class="py-1 px-3 text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="test in filteredLabTests"
                                            :key="test.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 transition-colors"
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
                                            <TableCell class="py-1 px-3 text-[10.5px]">
                                                <div class="font-medium text-foreground">{{ test.specimen_type }}</div>
                                                <div v-if="test.inventory_item" class="text-[9.5px] font-mono text-muted-foreground flex items-center gap-1 mt-0.5">
                                                    <Package class="w-2.5 h-2.5 text-primary" />
                                                    <span>{{ test.inventory_item.item_code }} (Auto-Deduct)</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-center text-muted-foreground text-[10.5px]">
                                                {{ test.turnaround_time_minutes }}m
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-center text-[10.5px]">
                                                <span class="px-1.5 py-0.2 rounded bg-muted font-semibold">
                                                    {{ Array.isArray(test.parameters) ? test.parameters.length : 1 }} analyte(s)
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-right text-foreground text-[11px]">
                                                TZS {{ formatCurrency(test.price) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9.5px] font-bold"
                                                    :class="test.is_active 
                                                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-300' 
                                                        : 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-300'"
                                                >
                                                    <span class="w-1.5 h-1.5 rounded-full" :class="test.is_active ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                                    <span>{{ test.is_active ? 'Active / Live' : 'Draft / Pending' }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell v-if="can.storeTest" class="py-1 px-3 text-right">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        @click="openEditTestModal(test)"
                                                        class="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                                        title="Edit test profile, parameters, and pricing"
                                                    >
                                                        <Edit3 class="w-3.5 h-3.5" />
                                                    </button>

                                                    <button
                                                        type="button"
                                                        @click="toggleTestStatus(test)"
                                                        :disabled="isTogglingStatus === test.id"
                                                        class="px-1.5 py-0.5 rounded text-[10px] font-semibold border transition-colors cursor-pointer flex items-center gap-1"
                                                        :class="test.is_active 
                                                            ? 'hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40 border-border text-muted-foreground' 
                                                            : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-300 font-bold'"
                                                        :title="test.is_active ? 'Deactivate test profile' : 'Approve & Activate for doctor ordering'"
                                                    >
                                                        <Loader2 v-if="isTogglingStatus === test.id" class="w-3 h-3 animate-spin" />
                                                        <template v-else>
                                                            <Check v-if="!test.is_active" class="w-3 h-3 text-emerald-600" />
                                                            <span>{{ test.is_active ? 'Deactivate' : 'Approve' }}</span>
                                                        </template>
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredLabTests.length === 0">
                                            <TableCell colspan="9" class="text-center py-8 text-muted-foreground text-xs">
                                                No diagnostic test profiles match your filter.
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

                        <!-- Billing & Financial Clearance Card -->
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5 border border-border/60">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                <span>Billing Clearance</span>
                                <span 
                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9px] font-bold"
                                    :class="{
                                        'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800': getLabPaymentStatus(selectedItem).isPaid,
                                        'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800 animate-pulse': !getLabPaymentStatus(selectedItem).isPaid,
                                    }"
                                >
                                    <CheckCircle2 v-if="getLabPaymentStatus(selectedItem).isPaid" class="w-2.5 h-2.5 text-emerald-600 dark:text-emerald-400" />
                                    <CreditCard v-else class="w-2.5 h-2.5 text-amber-600 dark:text-amber-400" />
                                    <span>{{ getLabPaymentStatus(selectedItem).label }}</span>
                                </span>
                            </div>
                            <p v-if="!getLabPaymentStatus(selectedItem).isPaid" class="text-[10px] text-amber-700 dark:text-amber-300 font-medium leading-tight">
                                ⚠️ Payment has not been cleared at the Cashier Desk for this encounter. Specimen processing is locked.
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
                                v-if="(selectedItem.status === 'Pending' || selectedItem.status === 'Ordered') && can.collectSample"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs"
                                :disabled="!getLabPaymentStatus(selectedItem).isPaid"
                                :title="!getLabPaymentStatus(selectedItem).isPaid ? 'Payment required at Cashier Desk before collecting specimen' : 'Collect Specimen'"
                                @click="openCollectModal(selectedItem)"
                            >
                                <Syringe class="w-3.5 h-3.5" />
                                <span>{{ getLabPaymentStatus(selectedItem).isPaid ? 'Collect Specimen' : 'Pending Cashier Payment' }}</span>
                            </Button>

                            <Button
                                v-else-if="(selectedItem.status === 'Sample Collected' || selectedItem.status === 'Testing') && can.saveResults"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs"
                                @click="openResultsModal(selectedItem)"
                            >
                                <Activity class="w-3.5 h-3.5" />
                                <span>Enter Analyzer Findings</span>
                            </Button>

                            <Button
                                v-else-if="selectedItem.status === 'Completed' && !selectedItem.verified_by_id && can.verifyResults"
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
        <Modal :show="showResultsModal" max-width="3xl" @close="showResultsModal = false">
            <div class="p-5 space-y-4">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Activity class="w-5 h-5 text-primary" />
                        <div>
                            <h3 class="font-bold text-sm text-foreground">Enter Analyzer Findings & Diagnostic Results</h3>
                            <p class="text-[11px] text-muted-foreground">Tanzania Standard Diagnostic Reporting & Automated Critical Panic Threshold Engine</p>
                        </div>
                    </div>
                    <button @click="showResultsModal = false" class="text-muted-foreground hover:text-foreground p-1 rounded-md hover:bg-muted transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="resultItem" class="space-y-3.5 text-xs">
                    <!-- Patient & Specimen Context Banner -->
                    <div class="p-3 bg-muted/40 rounded-lg border border-border/80 flex flex-wrap items-center justify-between gap-3 text-[11px]">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-foreground text-sm">{{ resultItem.lab_test?.name }}</span>
                                <span class="px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded bg-primary/10 text-primary border border-primary/20">
                                    {{ resultItem.lab_test?.category }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 text-muted-foreground text-[10.5px]">
                                <span>Specimen: <strong class="text-foreground">{{ resultItem.lab_test?.specimen_type || 'Specimen Verified' }}</strong></span>
                                <span>•</span>
                                <span>Barcode: <strong class="font-mono text-primary">{{ resultItem.specimen_barcode || 'N/A' }}</strong></span>
                            </div>
                        </div>

                        <div class="text-right space-y-0.5">
                            <div class="font-bold text-foreground">
                                {{ resultItem.lab_order?.patient?.first_name }} {{ resultItem.lab_order?.patient?.last_name }}
                                <span v-if="resultItem.lab_order?.patient?.gender" class="text-muted-foreground text-[10px] font-normal">({{ resultItem.lab_order?.patient?.gender }})</span>
                            </div>
                            <div class="text-[10px] font-mono text-muted-foreground">
                                MRN: <span class="font-semibold text-foreground">{{ resultItem.lab_order?.patient?.primary_mrn }}</span>
                            </div>
                            <div class="text-[10px] text-muted-foreground">
                                Ordered by: <span class="italic">{{ resultItem.lab_order?.ordering_provider?.name || 'Attending Physician' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Toolbar -->
                    <div class="flex items-center justify-between gap-2 p-2 bg-card rounded-md border border-border/70 text-xs flex-wrap">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="applyAllNormalFindings"
                                class="px-2.5 py-1 text-[11px] font-semibold bg-emerald-50 hover:bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:hover:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 rounded transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                title="Set all qualitative & numeric parameters to standard normal baseline"
                            >
                                <Sparkles class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span>Auto-Fill All Normal / Negative</span>
                            </button>

                            <button
                                type="button"
                                @click="clearAllFindings"
                                class="px-2 py-1 text-[11px] font-medium hover:bg-muted text-muted-foreground hover:text-foreground rounded transition-colors flex items-center gap-1 cursor-pointer"
                            >
                                <RotateCcw class="w-3 h-3" />
                                <span>Clear Values</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                            <span>Live Evaluation:</span>
                            <span v-if="livePanicCount > 0" class="px-1.5 py-0.2 rounded font-bold uppercase tracking-wider bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300 animate-pulse flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3 text-rose-600" />
                                {{ livePanicCount }} Critical Panic Alert(s)
                            </span>
                            <span v-else class="px-1.5 py-0.2 rounded font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200">
                                Parameters Stable
                            </span>
                        </div>
                    </div>

                    <!-- Parameter Findings Worksheet Table -->
                    <div class="space-y-2.5 max-h-[360px] overflow-y-auto pr-1 divide-y divide-border/40">
                        <div 
                            v-for="param in (resultItem.lab_test?.parameters || [{ name: 'Result', unit: '', ref_min: null, ref_max: null }])"
                            :key="param.name"
                            class="pt-2.5 first:pt-0 space-y-2"
                        >
                            <!-- Parameter Header Row -->
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-foreground">{{ param.name }}</span>
                                    <span v-if="param.unit" class="text-[10px] font-mono px-1.5 py-0.2 bg-muted rounded text-foreground font-semibold">
                                        {{ param.unit }}
                                    </span>
                                </div>

                                <!-- Live Keystroke Status Badge -->
                                <div class="flex items-center gap-2">
                                    <div class="text-[10px] text-muted-foreground">
                                        <span v-if="param.ref_min !== null && param.ref_max !== null">
                                            Normal: <strong class="font-mono text-foreground">{{ param.ref_min }} – {{ param.ref_max }}</strong>
                                        </span>
                                        <span v-else-if="param.min !== null && param.max !== null">
                                            Normal: <strong class="font-mono text-foreground">{{ param.min }} – {{ param.max }}</strong>
                                        </span>
                                        <span v-else-if="param.normal">
                                            Normal: <strong class="text-foreground">{{ param.normal }}</strong>
                                        </span>
                                    </div>

                                    <span 
                                        :class="getParameterLiveStatus(param, resultForm.results[param.name]).class"
                                        class="px-2 py-0.5 rounded text-[9px] uppercase tracking-wider font-bold shrink-0 flex items-center gap-1"
                                    >
                                        <AlertTriangle v-if="getParameterLiveStatus(param, resultForm.results[param.name]).isPanic" class="w-3 h-3" />
                                        <span>{{ getParameterLiveStatus(param, resultForm.results[param.name]).label }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Input & Quick Pick Row -->
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
                                <div class="md:col-span-6">
                                    <Input
                                        v-model="resultForm.results[param.name]"
                                        required
                                        placeholder="Enter result value..."
                                        class="h-8.5 text-xs font-mono font-bold"
                                        :class="getParameterLiveStatus(param, resultForm.results[param.name]).isPanic ? 'border-destructive ring-1 ring-destructive/30 bg-destructive/5' : ''"
                                    />
                                </div>

                                <!-- Quick Presets Button Chips -->
                                <div class="md:col-span-6 flex items-center gap-1 flex-wrap">
                                    <template v-for="opt in getQuickPresetsForParameter(param, resultItem.lab_test?.name)" :key="opt">
                                        <button
                                            type="button"
                                            @click="resultForm.results[param.name] = opt"
                                            class="px-2 py-0.5 text-[10px] rounded border transition-colors cursor-pointer"
                                            :class="resultForm.results[param.name] === opt 
                                                ? 'bg-primary text-primary-foreground border-primary font-bold shadow-2xs' 
                                                : 'bg-card hover:bg-muted text-muted-foreground border-border'"
                                        >
                                            {{ opt }}
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Critical Panic Alert Notice Banner -->
                    <div v-if="livePanicCount > 0" class="p-3 bg-rose-50 dark:bg-rose-950/60 rounded-lg border border-rose-300 dark:border-rose-800 text-rose-950 dark:text-rose-200 flex items-start gap-2.5 animate-pulse">
                        <AlertTriangle class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                        <div class="text-[11px] leading-tight space-y-0.5">
                            <div class="font-bold text-rose-900 dark:text-rose-300">
                                🚨 CRITICAL PANIC ALERT: {{ livePanicCount }} Parameter(s) Breach Life-Safety Limits
                            </div>
                            <p class="text-rose-800 dark:text-rose-300 text-[10.5px]">
                                Saving these findings will immediately flag the attending physician and consultation desk with a panic alert banner for urgent clinical intervention.
                            </p>
                        </div>
                    </div>

                    <!-- Technician Remarks & Analyzer Equipment Chips -->
                    <div class="space-y-1.5 pt-1">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-xs text-foreground">Technician Remarks / Analyzer Instrument</label>
                            <div class="flex items-center gap-1 overflow-x-auto text-[9.5px]">
                                <button
                                    type="button"
                                    @click="setPresetRemarks('Sysmex XN-550 Automated 5-Part Hematology Analyzer')"
                                    class="text-primary hover:underline"
                                >
                                    + Sysmex XN-550
                                </button>
                                <span>·</span>
                                <button
                                    type="button"
                                    @click="setPresetRemarks('Mindray BS-240 Fully Automated Clinical Chemistry Analyzer')"
                                    class="text-primary hover:underline"
                                >
                                    + Mindray BS-240
                                </button>
                                <span>·</span>
                                <button
                                    type="button"
                                    @click="setPresetRemarks('Standard Microscopic Examination (100x Oil Immersion)')"
                                    class="text-primary hover:underline"
                                >
                                    + Microscopy
                                </button>
                                <span>·</span>
                                <button
                                    type="button"
                                    @click="setPresetRemarks('Point-of-Care Rapid Immunochromatographic Assay Verified')"
                                    class="text-primary hover:underline"
                                >
                                    + Rapid Assay
                                </button>
                            </div>
                        </div>
                        <Input v-model="resultForm.technician_remarks" placeholder="e.g. Automated hematology analyzer Sysmex XN-550 (Calibrated & QC verified)" class="h-8.5 text-xs" />
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-between pt-3 border-t border-border gap-2">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showResultsModal = false" :disabled="isSavingResults">
                            Cancel
                        </Button>

                        <Button 
                            :variant="livePanicCount > 0 ? 'destructive' : 'default'" 
                            size="sm" 
                            class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" 
                            @click="submitSaveResults" 
                            :disabled="isSavingResults"
                        >
                            <Loader2 v-if="isSavingResults" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <AlertTriangle v-else-if="livePanicCount > 0" class="w-3.5 h-3.5" />
                            <Check v-else class="w-3.5 h-3.5" />
                            <span>{{ livePanicCount > 0 ? `Save & Dispatch Panic Alert (${livePanicCount}) 🚨` : 'Save Diagnostic Test Findings' }}</span>
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

        <!-- ================= MODAL 4: ADD / EDIT TEST CATALOG PROFILE ================= -->
        <Modal :show="showAddTestModal" max-width="4xl" @close="showAddTestModal = false">
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <FileText class="w-5 h-5 text-primary" />
                        <div>
                            <h3 class="font-bold text-sm text-foreground">
                                {{ editingTest ? `Edit Test Profile [${editingTest.test_code}]` : 'Add New Diagnostic Test Profile' }}
                            </h3>
                            <p class="text-[11px] text-muted-foreground">Configure laboratory test parameters, clinical reference intervals, and billing tariff.</p>
                        </div>
                    </div>
                    <button @click="showAddTestModal = false" class="text-muted-foreground hover:text-foreground p-1 rounded hover:bg-muted transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitAddOrUpdateTest" class="space-y-3.5 text-xs">
                    <!-- General Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Test Code *</label>
                            <Input v-model="addTestForm.test_code" required placeholder="e.g. HEM-012" class="font-mono h-8.5 text-xs" />
                        </div>

                        <div class="md:col-span-2 space-y-1">
                            <label class="block font-bold text-xs text-foreground">Test Name *</label>
                            <Input v-model="addTestForm.name" required placeholder="e.g. Full Blood Picture (FBP / CBC)" class="h-8.5 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
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
                                <option value="Serology & Immunology">Serology & Immunology</option>
                                <option value="Blood Transfusion">Blood Transfusion</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Specimen Container *</label>
                            <select
                                :value="addTestForm.specimen_type"
                                @change="onContainerSelected($event.target.value)"
                                required
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-2.5 py-1 text-xs shadow-xs"
                            >
                                <option v-for="c in standardContainers" :key="c.value" :value="c.value">{{ c.label }}</option>
                                <option value="Other / Custom Container">➕ Other / Custom Container</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Turnaround Time (min) *</label>
                            <Input v-model="addTestForm.turnaround_time_minutes" type="number" required min="5" max="1440" class="font-mono h-8.5 text-xs" />
                        </div>
                    </div>

                    <!-- Linked Physical Consumable SKU (Auto-Inventory Depletion) -->
                    <div class="p-2.5 bg-muted/40 rounded-md border border-border/70 grid grid-cols-1 md:grid-cols-2 gap-3 items-center">
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground flex items-center gap-1.5">
                                <Package class="w-3.5 h-3.5 text-primary" />
                                <span>Track Physical Inventory Stock (Consumable SKU)</span>
                            </label>
                            <p class="text-[10px] text-muted-foreground">Auto-deducts 1 unit from Lab Phlebotomy Bench store upon sample collection.</p>
                        </div>
                        <div>
                            <select
                                v-model="addTestForm.inventory_item_id"
                                class="w-full h-8 rounded-md border border-input bg-background text-foreground px-2.5 py-1 text-xs shadow-xs font-mono"
                            >
                                <option :value="null">-- No Inventory Link (Do Not Auto-Deduct) --</option>
                                <option v-for="item in props.labConsumables" :key="item.id" :value="item.id">
                                    [{{ item.item_code }}] {{ item.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-center">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Standard Fee (TZS) *</label>
                            <Input v-model="addTestForm.price" type="number" required min="0" step="100" class="font-mono h-8.5 text-xs" />
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Clinical Governance Status</label>
                            <label class="flex items-center gap-2 p-2 bg-muted/40 rounded border border-border/70 cursor-pointer select-none">
                                <input 
                                    type="checkbox" 
                                    v-model="addTestForm.is_active" 
                                    class="w-4 h-4 rounded text-primary border-border focus:ring-primary" 
                                />
                                <div class="text-[11px] leading-tight">
                                    <span class="font-bold text-foreground">Approve & Activate for Ordering</span>
                                    <p class="text-[10px] text-muted-foreground">When active, doctors can immediately order this test in consultation.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Parameters & Reference Intervals Builder -->
                    <div class="space-y-2 pt-2 border-t border-border/60">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block font-bold text-xs text-foreground">Analyte Parameters & Reference Intervals</label>
                                <p class="text-[10px] text-muted-foreground">Define individual test components, normal ranges, and panic triggers.</p>
                            </div>
                            <button
                                type="button"
                                @click="addParameterRow"
                                class="px-2 py-1 text-[10.5px] font-semibold bg-primary/10 hover:bg-primary/20 text-primary rounded border border-primary/30 transition-colors flex items-center gap-1 cursor-pointer"
                            >
                                <Plus class="w-3 h-3" />
                                <span>Add Parameter</span>
                            </button>
                        </div>

                        <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1">
                            <div 
                                v-for="(param, pIdx) in addTestForm.parameters" 
                                :key="pIdx"
                                class="p-2.5 bg-card rounded-md border border-border/70 space-y-2 text-[11px]"
                            >
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
                                    <div class="md:col-span-4 space-y-0.5">
                                        <span class="text-[10px] font-bold text-muted-foreground">Parameter / Analyte Name *</span>
                                        <Input v-model="param.name" required placeholder="e.g. Hemoglobin" class="h-7.5 text-xs" />
                                    </div>
                                    <div class="md:col-span-2 space-y-0.5">
                                        <span class="text-[10px] font-bold text-muted-foreground">Unit</span>
                                        <Input v-model="param.unit" placeholder="e.g. g/dL" class="h-7.5 font-mono text-xs" />
                                    </div>
                                    <div class="md:col-span-2 space-y-0.5">
                                        <span class="text-[10px] font-bold text-muted-foreground">Ref Min</span>
                                        <Input v-model="param.ref_min" type="number" step="any" placeholder="12.0" class="h-7.5 font-mono text-xs" />
                                    </div>
                                    <div class="md:col-span-2 space-y-0.5">
                                        <span class="text-[10px] font-bold text-muted-foreground">Ref Max</span>
                                        <Input v-model="param.ref_max" type="number" step="any" placeholder="17.5" class="h-7.5 font-mono text-xs" />
                                    </div>
                                    <div class="md:col-span-2 text-right pt-3">
                                        <button
                                            type="button"
                                            @click="removeParameterRow(pIdx)"
                                            :disabled="addTestForm.parameters.length <= 1"
                                            class="p-1 rounded text-muted-foreground hover:text-rose-600 transition-colors disabled:opacity-30 cursor-pointer"
                                            title="Remove parameter"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Panic / Critical Triggers -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 pt-1 border-t border-border/40 text-[10px]">
                                    <div>
                                        <span class="text-rose-600 font-semibold">Panic Low Alert (&lt;)</span>
                                        <Input v-model="param.panic_low" type="number" step="any" placeholder="e.g. 7.0" class="h-7 font-mono text-[10px]" />
                                    </div>
                                    <div>
                                        <span class="text-rose-600 font-semibold">Panic High Alert (&gt;)</span>
                                        <Input v-model="param.panic_high" type="number" step="any" placeholder="e.g. 20.0" class="h-7 font-mono text-[10px]" />
                                    </div>
                                    <div>
                                        <span class="text-rose-600 font-semibold">Critical Qualitative Trigger</span>
                                        <Input v-model="param.critical_value" placeholder="e.g. Positive / Reactive" class="h-7 text-[10px]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showAddTestModal = false" :disabled="isAddingTest">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isAddingTest || !addTestForm.name">
                            <Loader2 v-if="isAddingTest" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <Check v-else class="w-3.5 h-3.5" />
                            <span>{{ editingTest ? 'Update Test Profile' : (addTestForm.is_active ? 'Save & Activate Test Profile' : 'Save as Draft Profile') }}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Specimen Tube Barcode Label Print Modal -->
        <SpecimenLabelPrint
            v-if="printingSample"
            :sample="printingSample"
            @close="printingSample = null"
        />

        <!-- ================= MODAL 5: FORENSIC RESULT AMENDMENT ================= -->
        <Modal :show="showAmendModal" max-width="lg" @close="showAmendModal = false">
            <div class="p-6 space-y-4 text-xs">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <Edit3 class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-foreground">Amend Diagnostic Test Finding</h3>
                            <p class="text-[11px] text-muted-foreground">Immutable forensic correction with full clinical provenance tracking</p>
                        </div>
                    </div>
                    <button @click="showAmendModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="amendItem" class="space-y-4">
                    <!-- Forensic Audit Notice -->
                    <div class="p-3 bg-amber-50/80 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/60 rounded-lg flex items-start gap-2 text-[11px] text-amber-900 dark:text-amber-300">
                        <AlertTriangle class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Forensic Protocol Invariant:</span>
                            The verified original result will be marked <span class="font-mono font-bold">deprecated</span>. A new immutable amendment record will be generated linked directly to this specimen barcode.
                        </div>
                    </div>

                    <!-- Specimen & Patient Header -->
                    <div class="p-2.5 bg-muted/40 rounded-lg border border-border/70 grid grid-cols-2 gap-2 text-[11px]">
                        <div>
                            <span class="text-muted-foreground">Patient:</span>
                            <span class="font-bold ml-1">{{ amendItem.lab_order?.patient?.first_name }} {{ amendItem.lab_order?.patient?.last_name }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">MRN:</span>
                            <span class="font-mono font-bold ml-1">{{ amendItem.lab_order?.patient?.primary_mrn }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Test:</span>
                            <span class="font-bold ml-1">{{ amendItem.lab_test?.name }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Barcode:</span>
                            <span class="font-mono font-bold ml-1">{{ amendItem.specimen_barcode }}</span>
                        </div>
                    </div>

                    <form @submit.prevent="submitAmendResults" class="space-y-3.5">
                        <!-- Amended Values Inputs -->
                        <div class="space-y-2">
                            <label class="block font-bold text-[10px] uppercase text-muted-foreground">Amended Analytical Findings *</label>
                            
                            <div v-if="amendItem.lab_test?.parameters && Array.isArray(amendItem.lab_test.parameters)" class="space-y-2">
                                <div v-for="param in amendItem.lab_test.parameters" :key="param.name" class="grid grid-cols-3 items-center gap-2 p-2 bg-card rounded border border-border/60">
                                    <div class="font-medium text-foreground text-xs truncate">
                                        {{ param.name }}
                                        <span v-if="param.unit" class="text-[10px] text-muted-foreground font-mono">({{ param.unit }})</span>
                                    </div>
                                    <div class="col-span-2">
                                        <Input
                                            v-model="amendForm.results[param.name]"
                                            required
                                            class="h-7 text-xs font-mono font-bold"
                                        />
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="space-y-1">
                                <Input
                                    v-model="amendForm.results['Result']"
                                    required
                                    placeholder="Enter corrected qualitative / quantitative finding"
                                    class="h-8 text-xs font-mono font-bold"
                                />
                            </div>
                        </div>

                        <!-- Amendment Reason -->
                        <div class="space-y-1">
                            <label class="block font-bold text-[10px] uppercase text-muted-foreground">Pathology Justification / Correction Reason *</label>
                            <textarea
                                v-model="amendForm.amendment_reason"
                                required
                                minlength="10"
                                rows="3"
                                class="w-full rounded-md border border-input bg-background text-foreground p-2.5 text-xs shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="Explain why the previously signed result is being amended (e.g. Analyzer drift re-run, dilution recheck, specimen re-evaluation)..."
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                            <Button variant="outline" size="sm" type="button" @click="showAmendModal = false" :disabled="isAmending">Cancel</Button>
                            <Button
                                variant="default"
                                size="sm"
                                type="submit"
                                class="bg-amber-600 hover:bg-amber-700 text-white font-bold"
                                :disabled="isAmending || !amendForm.amendment_reason || amendForm.amendment_reason.length < 10"
                            >
                                <Loader2 v-if="isAmending" class="w-3.5 h-3.5 animate-spin mr-1.5" />
                                <span>Commit Forensic Result Amendment</span>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>
    </AfyaShell>
</template>
