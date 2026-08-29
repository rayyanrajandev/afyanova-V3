<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Pill, 
    CheckCircle2, 
    XCircle, 
    Send, 
    ShieldCheck, 
    AlertTriangle, 
    Users, 
    Clock, 
    Building2,
    DollarSign,
    Package,
    Loader2,
    X,
    Lock,
    Plus,
    Calendar,
    Layers,
    ArrowDownUp,
    RefreshCw,
    SlidersHorizontal,
    FileText,
    History,
    Activity,
    Check,
    TrendingUp,
    TrendingDown,
    RotateCcw,
    ShoppingBag,
    ShoppingCart,
    Trash2
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';

// UI Primitives & Design Foundation
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import AfyaDatePicker from '@/Components/Afya/AfyaDatePicker.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
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
    prescriptions: {
        type: Array,
        default: () => [],
    },
    medications: {
        type: Array,
        default: () => [],
    },
    batches: {
        type: Array,
        default: () => [],
    },
    recentMovements: {
        type: Array,
        default: () => [],
    },
    waitingTickets: {
        type: Array,
        default: () => [],
    },
    initialSection: {
        type: String,
        default: 'queue',
    },
});

const { preferences, openContext } = useWorkspacePreferences();

const activeSection = ref(
    props.can[props.initialSection] ? props.initialSection : (Object.keys(props.can).find(k => props.can[k]) ?? null)
);
const selectedPrescription = ref(props.prescriptions?.[0] || null);
const selectedMedication = ref(props.medications?.[0] || null);
const selectedMovement = ref(props.recentMovements?.[0] || null);

// Formulary Search & Filter
const searchQuery = ref('');
const stockFilter = ref('all'); // all, in_stock, low_stock, stockout, expiring

// Dispensing Modal State
const showDispenseModal = ref(false);
const dispenseRx = ref(null);
const dispenseQty = ref(1);
const isDispensing = ref(false);

// Receive Batch Modal State
const showReceiveModal = ref(false);
const isReceiving = ref(false);
const receiveForm = ref({
    medication_id: '',
    batch_number: '',
    expiry_date: '',
    manufacture_date: '',
    quantity: null,
    unit_cost: null,
    unit_selling_price: null,
    supplier_name: '',
    notes: '',
});

// Adjust Stock Modal State
const showAdjustModal = ref(false);
const adjustBatch = ref(null);
const adjustQty = ref(0);
const adjustReasonPreset = ref('Physical Stocktake Count Reconciliation');
const adjustCustomNotes = ref('');
const isAdjusting = ref(false);

// Direct OTC POS State
const otcPatient = ref(null);
const otcTicket = ref(null);
const otcCart = ref([]);
const otcSearchQuery = ref('');
const otcNotes = ref('');
const isSubmittingOtc = ref(false);
const otcStockWarning = ref(null);

const startOtcForTicket = (ticket) => {
    otcTicket.value = ticket;
    otcPatient.value = ticket.patient || null;
    activeSection.value = 'otc';
};

const addMedicationToOtcCart = (med) => {
    otcStockWarning.value = null;
    const existing = otcCart.value.find(i => i.medication_id === med.id);
    const availableStock = med.batches?.filter(b => b.status === 'Active' && Number(b.current_quantity) > 0)
        .reduce((sum, b) => sum + Number(b.current_quantity), 0) || 0;

    if (availableStock <= 0) {
        otcStockWarning.value = `${med.generic_name} is currently out of stock.`;
        return;
    }

    if (existing) {
        if (existing.quantity < availableStock) {
            existing.quantity += 1;
        }
    } else {
        const unitPrice = parseFloat(med.charge_code ? (med.item_master?.standard_price || 1000) : 1000);
        otcCart.value.push({
            medication_id: med.id,
            name: med.generic_name,
            brand_name: med.brand_name,
            strength: med.strength,
            form: med.form,
            quantity: 1,
            available_stock: availableStock,
            unit_price: unitPrice,
            instructions: '',
        });
    }
};

const removeOtcItem = (idx) => {
    otcCart.value.splice(idx, 1);
};

const decreaseOtcQty = (item) => {
    if (item.quantity > 1) {
        item.quantity--;
    }
};

const increaseOtcQty = (item) => {
    if (item.quantity < item.available_stock) {
        item.quantity++;
    }
};

const otcCartTotal = computed(() => {
    return otcCart.value.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
});

const submitDirectOtcSale = () => {
    if (otcCart.value.length === 0) return;

    isSubmittingOtc.value = true;
    router.post(route('pharmacy.dispense-direct-otc'), {
        patient_id: otcPatient.value?.id || null,
        ticket_id: otcTicket.value?.id || null,
        notes: otcNotes.value,
        items: otcCart.value.map(i => ({
            medication_id: i.medication_id,
            quantity: i.quantity,
            unit_price: i.unit_price,
            instructions: i.instructions,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            otcCart.value = [];
            otcNotes.value = '';
            otcTicket.value = null;
            otcPatient.value = null;
            isSubmittingOtc.value = false;
        },
        onError: () => {
            isSubmittingOtc.value = false;
        }
    });
};

const filteredOtcMedications = computed(() => {
    if (!otcSearchQuery.value) return props.medications.slice(0, 35);
    const q = otcSearchQuery.value.toLowerCase();
    return props.medications.filter(m => 
        (m.generic_name && m.generic_name.toLowerCase().includes(q)) ||
        (m.brand_name && m.brand_name.toLowerCase().includes(q))
    ).slice(0, 35);
});

const getMedicationTotalStock = (med) => {
    if (!med.batches || med.batches.length === 0) return 0;
    return med.batches
        .filter(b => b.status === 'Active' && Number(b.current_quantity) > 0)
        .reduce((sum, b) => sum + Number(b.current_quantity), 0);
};

const adjustmentReasons = [
    'Physical Stocktake Count Reconciliation',
    'Damaged / Broken Ampoules or Bottles',
    'Expired Medication Write-Off',
    'Supplier Return / Product Recall',
    'Spillage / Wastage in Dispensary',
    'Other / Custom Reason',
];

const selectRx = (rx) => {
    selectedPrescription.value = rx;
    openContext();
};

const selectMed = (med) => {
    selectedMedication.value = med;
    openContext();
};

const selectMovement = (mv) => {
    selectedMovement.value = mv;
    openContext();
};

const contextTitle = computed(() => {
    if (activeSection.value === 'queue') return 'Rx Order Inspector';
    if (activeSection.value === 'formulary') return 'Medication & FEFO Batches';
    return 'Stock Movement Inspector';
});

const isPaid = (rx) => {
    if (!rx) return false;
    // 1. Check if patient has active insurance policy (NHIF / Private)
    const policies = rx.patient?.policies || [];
    const activePolicy = policies.find(p => p.status === 'Active' || p.status === 'Verified');
    if (activePolicy) return true;

    // 2. Check encounter invoices for cash settlement
    const invoices = rx.encounter?.invoices || [];
    if (invoices.length === 0) {
        return false;
    }

    // Look for pharmacy-specific invoices or general open invoices
    const unpaidInvoices = invoices.filter(inv => inv.status !== 'Paid');
    return unpaidInvoices.length === 0;
};

const verify = (prescriptionId, approve) => {
    router.post(route('pharmacy.verify', prescriptionId), { approve });
};

// FEFO Calculation for Dispensing Modal
const suggestedBatches = computed(() => {
    if (!dispenseRx.value) return [];
    
    const medId = dispenseRx.value.medication_id;
    const med = props.medications.find(m => m.id === medId);
    const medBatches = med?.batches || dispenseRx.value.medication?.batches || [];

    const active = medBatches.filter(b => {
        const isExp = b.expiry_date ? new Date(b.expiry_date) <= new Date() : false;
        return b.status === 'Active' && b.current_quantity > 0 && !isExp;
    }).sort((a, b) => new Date(a.expiry_date) - new Date(b.expiry_date));

    let remainingNeeded = Number(dispenseQty.value || 0);
    const allocated = [];

    for (const b of active) {
        if (remainingNeeded <= 0) break;
        const take = Math.min(remainingNeeded, b.current_quantity);
        allocated.push({
            batch_number: b.batch_number,
            expiry_date: b.expiry_date,
            available: b.current_quantity,
            deduct: take,
            supplier_name: b.supplier_name,
        });
        remainingNeeded -= take;
    }

    return allocated;
});

const totalStockForDispenseMed = computed(() => {
    if (!dispenseRx.value) return 0;
    const medId = dispenseRx.value.medication_id;
    const med = props.medications.find(m => m.id === medId);
    if (med) return med.total_stock_on_hand;
    return dispenseRx.value.medication?.total_stock_on_hand || 0;
});

const isStockSufficient = computed(() => {
    if (!dispenseRx.value) return true;
    if (props.batches.length === 0 && (!props.medications || props.medications.every(m => !m.batches || m.batches.length === 0))) {
        return true;
    }
    return totalStockForDispenseMed.value >= Number(dispenseQty.value || 0);
});

const openDispenseModal = (rx) => {
    dispenseRx.value = rx;
    dispenseQty.value = rx.quantity || 1;
    showDispenseModal.value = true;
};

const closeDispenseModal = () => {
    showDispenseModal.value = false;
    dispenseRx.value = null;
};

const confirmDispense = () => {
    if (!dispenseRx.value || !dispenseQty.value) return;
    isDispensing.value = true;
    router.post(route('pharmacy.dispense', dispenseRx.value.id), {
        quantity_dispensed: Number(dispenseQty.value),
    }, {
        onFinish: () => {
            isDispensing.value = false;
            closeDispenseModal();
        }
    });
};

// Receive Batch Form Handlers
const openReceiveModal = (med = null) => {
    receiveForm.value = {
        medication_id: med ? med.id : (props.medications[0]?.id || ''),
        batch_number: '',
        expiry_date: '',
        manufacture_date: '',
        quantity: null,
        unit_cost: null,
        unit_selling_price: null,
        supplier_name: '',
        notes: '',
    };
    showReceiveModal.value = true;
};

const closeReceiveModal = () => {
    showReceiveModal.value = false;
};

const submitReceiveBatch = () => {
    isReceiving.value = true;
    router.post(route('pharmacy.batches.store'), receiveForm.value, {
        onFinish: () => {
            isReceiving.value = false;
            closeReceiveModal();
        }
    });
};

// Stock Adjustment Handlers
const openAdjustModal = (batch) => {
    adjustBatch.value = batch;
    adjustQty.value = batch.current_quantity;
    adjustReasonPreset.value = 'Physical Stocktake Count Reconciliation';
    adjustCustomNotes.value = '';
    showAdjustModal.value = true;
};

const closeAdjustModal = () => {
    showAdjustModal.value = false;
    adjustBatch.value = null;
};

const submitAdjustStock = () => {
    if (!adjustBatch.value) return;
    const finalReason = adjustReasonPreset.value === 'Other / Custom Reason'
        ? (adjustCustomNotes.value || 'Manual stock adjustment')
        : (adjustCustomNotes.value ? `${adjustReasonPreset.value}: ${adjustCustomNotes.value}` : adjustReasonPreset.value);

    isAdjusting.value = true;
    router.post(route('pharmacy.batches.adjust', adjustBatch.value.id), {
        new_quantity: Number(adjustQty.value),
        reason: finalReason,
    }, {
        onFinish: () => {
            isAdjusting.value = false;
            closeAdjustModal();
        }
    });
};

// Filtered Medications List (Dynamic Reorder Level Thresholds)
const filteredMedications = computed(() => {
    return props.medications.filter(med => {
        const matchesQuery = !searchQuery.value || 
            med.generic_name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (med.brand_name && med.brand_name.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
            (med.drug_class && med.drug_class.toLowerCase().includes(searchQuery.value.toLowerCase()));

        if (!matchesQuery) return false;

        const reorderThreshold = med.reorder_level || 50;

        if (stockFilter.value === 'in_stock') return med.total_stock_on_hand > reorderThreshold;
        if (stockFilter.value === 'low_stock') return med.total_stock_on_hand > 0 && med.total_stock_on_hand <= reorderThreshold;
        if (stockFilter.value === 'stockout') return med.total_stock_on_hand <= 0;
        if (stockFilter.value === 'expiring') {
            return med.batches && med.batches.some(b => b.days_to_expiry <= 30 && b.days_to_expiry > 0 && b.current_quantity > 0);
        }

        return true;
    });
});

// Inventory Aggregate Metrics (Dynamic Reorder Level Thresholds)
const inventoryMetrics = computed(() => {
    const totalMeds = props.medications.length;
    const totalUnits = props.medications.reduce((acc, m) => acc + (m.total_stock_on_hand || 0), 0);
    const stockouts = props.medications.filter(m => (m.total_stock_on_hand || 0) === 0).length;
    const lowStock = props.medications.filter(m => {
        const reorderThreshold = m.reorder_level || 50;
        const stock = m.total_stock_on_hand || 0;
        return stock > 0 && stock <= reorderThreshold;
    }).length;
    const activeBatchesCount = props.batches.filter(b => b.status === 'Active' && b.current_quantity > 0).length;
    
    return {
        totalMeds,
        totalUnits,
        stockouts,
        lowStock,
        activeBatchesCount,
    };
});

// Stock Movements Ledger State & Filters (Digital Bin Card / Kadi ya Akiba)
const movementSearchQuery = ref('');
const movementTypeFilter = ref('all'); // all, Received, Dispensed, Adjustment, Transfer
const movementDateFilter = ref('all'); // all, today, week, month
const activeBinCardMedication = ref(null); // When filtering for a specific drug's bin card

const viewMedicationBinCard = (med) => {
    activeBinCardMedication.value = med;
    movementSearchQuery.value = '';
    movementTypeFilter.value = 'all';
    movementDateFilter.value = 'all';
    activeSection.value = 'movements';
};

const clearBinCardFilter = () => {
    activeBinCardMedication.value = null;
    movementSearchQuery.value = '';
};

const clearMovementFilters = () => {
    movementSearchQuery.value = '';
    movementTypeFilter.value = 'all';
    movementDateFilter.value = 'all';
    activeBinCardMedication.value = null;
};

const filteredMovements = computed(() => {
    let list = props.recentMovements || [];

    // Filter by specific medication bin card
    if (activeBinCardMedication.value) {
        list = list.filter(m => m.medication_id === activeBinCardMedication.value.id || m.medication?.id === activeBinCardMedication.value.id);
    }

    // Filter by search query (Drug name, batch number, performer name, notes, reference)
    if (movementSearchQuery.value.trim()) {
        const q = movementSearchQuery.value.toLowerCase().trim();
        list = list.filter(m => {
            const medName = `${m.medication?.generic_name || ''} ${m.medication?.brand_name || ''}`.toLowerCase();
            const batchNo = (m.batch?.batch_number || '').toLowerCase();
            const performer = `${m.performer?.first_name || ''} ${m.performer?.last_name || ''}`.toLowerCase();
            const notes = (m.notes || '').toLowerCase();
            const refId = (m.reference_id || m.reference_type || '').toLowerCase();
            return medName.includes(q) || batchNo.includes(q) || performer.includes(q) || notes.includes(q) || refId.includes(q);
        });
    }

    // Filter by movement type
    if (movementTypeFilter.value !== 'all') {
        list = list.filter(m => {
            if (movementTypeFilter.value === 'Received') return m.movement_type === 'Received';
            if (movementTypeFilter.value === 'Dispensed') return m.movement_type === 'Dispensed';
            if (movementTypeFilter.value === 'Adjustment') return m.movement_type === 'Adjusted' || m.movement_type === 'Adjustment' || m.movement_type === 'Expired' || m.movement_type === 'Damaged';
            if (movementTypeFilter.value === 'Transfer') return m.movement_type === 'Transferred' || m.movement_type === 'Transfer';
            return true;
        });
    }

    // Filter by date
    if (movementDateFilter.value !== 'all') {
        const now = new Date();
        list = list.filter(m => {
            const mDate = new Date(m.created_at);
            if (movementDateFilter.value === 'today') {
                return mDate.toDateString() === now.toDateString();
            }
            if (movementDateFilter.value === 'week') {
                const weekAgo = new Date();
                weekAgo.setDate(now.getDate() - 7);
                return mDate >= weekAgo;
            }
            if (movementDateFilter.value === 'month') {
                const monthAgo = new Date();
                monthAgo.setDate(now.getDate() - 30);
                return mDate >= monthAgo;
            }
            return true;
        });
    }

    return list;
});

const movementCounts = computed(() => {
    const base = activeBinCardMedication.value
        ? (props.recentMovements || []).filter(m => m.medication_id === activeBinCardMedication.value.id || m.medication?.id === activeBinCardMedication.value.id)
        : (props.recentMovements || []);

    return {
        all: base.length,
        received: base.filter(m => m.movement_type === 'Received').length,
        dispensed: base.filter(m => m.movement_type === 'Dispensed').length,
        adjustments: base.filter(m => m.movement_type === 'Adjusted' || m.movement_type === 'Adjustment' || m.movement_type === 'Expired' || m.movement_type === 'Damaged').length,
        transfers: base.filter(m => m.movement_type === 'Transferred' || m.movement_type === 'Transfer').length,
    };
});

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
};

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('en-US');
};

// Keyboard Traversal
const handleTableKeydown = (e) => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || showDispenseModal.value || showReceiveModal.value) {
        return;
    }

    if (activeSection.value === 'queue') {
        if (!props.prescriptions || props.prescriptions.length === 0) return;
        const currentIndex = props.prescriptions.findIndex(rx => rx.id === selectedPrescription.value?.id);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = Math.min(currentIndex + 1, props.prescriptions.length - 1);
            selectedPrescription.value = props.prescriptions[nextIndex];
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = Math.max(currentIndex - 1, 0);
            selectedPrescription.value = props.prescriptions[prevIndex];
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedPrescription.value && (selectedPrescription.value.status === 'Verified' || selectedPrescription.value.status === 'Partially Dispensed')) {
                if (isPaid(selectedPrescription.value)) {
                    openDispenseModal(selectedPrescription.value);
                }
            }
        }
    }
};

onMounted(() => window.addEventListener('keydown', handleTableKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleTableKeydown));
</script>

<template>
    <Head title="Pharmacy & Inventory FEFO Workstation — AfyaNova Workstation" />

    <AfyaShell active-module="pharmacy">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Pharmacy Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Dispensary"
                    :icon="Pill"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Pharmacist Desk
                    </div>
                    
                    <AfyaSidebarItem
                        v-if="can.queue"
                        label="Dispensing Queue"
                        :icon="Pill"
                        :badge="prescriptions.length"
                        :active="activeSection === 'queue'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'queue'"
                    />

                    <AfyaSidebarItem
                        v-if="can.otc"
                        label="Direct OTC & Walk-in POS"
                        :icon="ShoppingBag"
                        :badge="waitingTickets.length"
                        :active="activeSection === 'otc'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'otc'"
                    />

                    <AfyaSidebarItem
                        v-if="can.formulary"
                        label="FEFO Stock & Formulary"
                        :icon="Package"
                        :badge="inventoryMetrics.totalUnits"
                        :active="activeSection === 'formulary'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'formulary'"
                    />

                    <AfyaSidebarItem
                        v-if="can.movements"
                        label="Stock Movements Ledger"
                        :icon="History"
                        :badge="recentMovements.length"
                        :active="activeSection === 'movements'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'movements'"
                    />

                    <AfyaSidebarItem
                        v-if="can.billing"
                        label="Cashier Collections"
                        :icon="DollarSign"
                        :collapsed="state === 'collapsed'"
                        :href="route('billing.desk')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN WORK AREA -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Pharmacy', href: route('pharmacy.queue') },
                        { label: activeSection === 'queue' ? 'Dispensing Queue' : (activeSection === 'formulary' ? 'FEFO Inventory & Batches' : 'Stock Movements Ledger'), active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <ShieldCheck class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                                <span>FEFO Batch Tracking</span>
                            </span>
                            <Button
                                v-if="activeSection === 'formulary' && can.storeBatch"
                                variant="default"
                                size="sm"
                                class="h-7.5 px-3 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="openReceiveModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Receive Stock Batch</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Waiting Walk-in Direct Pharmacy Patients Banner -->
                        <div v-if="waitingTickets.length > 0" class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-between shadow-2xs">
                            <div class="flex items-center gap-2.5">
                                <Users class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                                <div>
                                    <div class="text-xs font-bold text-amber-950 dark:text-amber-200">
                                        {{ waitingTickets.length }} Fast-Track Walk-in Patient(s) Waiting at Pharmacy Window
                                    </div>
                                    <div class="text-[10.5px] text-muted-foreground flex items-center gap-2 mt-0.5 flex-wrap">
                                        <span v-for="t in waitingTickets" :key="t.id" class="px-1.5 py-0.2 rounded bg-amber-200/70 dark:bg-amber-900/50 font-mono font-bold text-amber-900 dark:text-amber-200">
                                            {{ t.ticket_number }}: {{ t.patient ? `${t.patient.first_name} ${t.patient.last_name}` : 'Walk-in' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <Button size="sm" variant="outline" class="h-7 text-xs font-bold gap-1 border-amber-500/50 hover:bg-amber-500/10" @click="startOtcForTicket(waitingTickets[0])">
                                <span>Serve Ticket ({{ waitingTickets[0].ticket_number }})</span>
                                <ShoppingBag class="w-3.5 h-3.5" />
                            </Button>
                        </div>

                        <!-- ================= VIEW 1: DISPENSING QUEUE ================= -->
                        <div v-if="activeSection === 'queue'" class="w-full space-y-3">
                            <div class="w-full bg-card rounded-md overflow-hidden shadow-2xs flex flex-col border border-border/60">
                                <div class="px-3 py-1.5 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Pill class="w-3.5 h-3.5 text-primary" />
                                        <span>Active Clinical Prescriptions ({{ prescriptions.length }})</span>
                                    </div>
                                    <div class="text-[10.5px] text-muted-foreground flex items-center gap-1.5">
                                        <span>Use <kbd class="px-1 py-0.2 text-[8.5px] bg-muted border border-border rounded">↑</kbd> <kbd class="px-1 py-0.2 text-[8.5px] bg-muted border border-border rounded">↓</kbd> to navigate, <kbd class="px-1 py-0.2 text-[8.5px] bg-muted border border-border rounded">Enter</kbd> to dispense</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-40">Patient Details</TableHead>
                                            <TableHead class="py-1 px-3">Prescription Order</TableHead>
                                            <TableHead class="py-1 px-3 w-32">Dosage & Route</TableHead>
                                            <TableHead class="py-1 px-3 text-center w-24">Stock</TableHead>
                                            <TableHead class="py-1 px-3 text-center w-14">Qty</TableHead>
                                            <TableHead class="py-1 px-3 text-center w-32">POS Payment</TableHead>
                                            <TableHead class="py-1 px-3 text-center w-24">Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[150px]">Pharmacist Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="rx in prescriptions"
                                            :key="rx.id"
                                            :selected="selectedPrescription?.id === rx.id"
                                            class="h-9 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                            :class="{ 'bg-primary/5': selectedPrescription?.id === rx.id }"
                                            @click="selectRx(rx)"
                                        >
                                            <TableCell class="py-1 px-3 w-40">
                                                <div class="font-bold text-foreground truncate max-w-[140px] text-[11px]">{{ rx.patient?.first_name }} {{ rx.patient?.last_name }}</div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ rx.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px]">
                                                    {{ rx.medication?.generic_name }}
                                                    <span v-if="rx.medication?.brand_name" class="text-muted-foreground font-normal">({{ rx.medication.brand_name }})</span>
                                                </div>
                                                <div class="text-[9.5px] text-muted-foreground font-mono">{{ rx.medication?.strength }} · {{ rx.medication?.form }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-[10.5px] w-32">
                                                <div class="font-medium text-foreground">{{ rx.dosage }} · {{ rx.frequency }}</div>
                                                <div class="text-[9.5px] text-muted-foreground">{{ rx.duration_days }}d ({{ rx.route }})</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center w-24">
                                                <span 
                                                    class="inline-flex items-center gap-1 font-mono font-bold text-[10.5px] px-1.5 py-0.2 rounded"
                                                    :class="rx.medication?.total_stock_on_hand > (rx.medication?.reorder_level || 50) ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' : (rx.medication?.total_stock_on_hand > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800' : 'bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800')"
                                                >
                                                    <Package class="w-2.5 h-2.5 opacity-70" />
                                                    <span>{{ rx.medication?.total_stock_on_hand ?? 0 }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-center w-14 text-[11px]">{{ rx.quantity }}</TableCell>
                                            <TableCell class="py-1 px-3 text-center w-32">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold inline-flex items-center gap-1"
                                                    :class="isPaid(rx) ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800'"
                                                >
                                                    <Lock v-if="!isPaid(rx)" class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400" />
                                                    <span>{{ isPaid(rx) ? 'Prepaid (Paid)' : 'Unpaid at POS' }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center w-24">
                                                <AfyaStatusBadge :status="rx.status" dot />
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[150px]">
                                                <div v-if="rx.status === 'Pending' && can.verify" class="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="default"
                                                        size="sm"
                                                        class="h-5 px-2 text-[9.5px] font-semibold"
                                                        @click.stop="verify(rx.id, true)"
                                                    >
                                                        Verify
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        class="h-5 px-2 text-[9.5px] font-semibold"
                                                        @click.stop="verify(rx.id, false)"
                                                    >
                                                        Reject
                                                    </Button>
                                                </div>
                                                <div v-else-if="rx.status === 'Verified' || rx.status === 'Partially Dispensed'" class="flex justify-end">
                                                    <Button
                                                        v-if="isPaid(rx) && can.dispense"
                                                        variant="default"
                                                        size="sm"
                                                        class="h-5 px-2 text-[9.5px] font-semibold"
                                                        @click.stop="openDispenseModal(rx)"
                                                    >
                                                        Dispense (FEFO)
                                                    </Button>
                                                    <Button
                                                        v-else
                                                        variant="outline"
                                                        size="sm"
                                                        disabled
                                                        class="opacity-60 cursor-not-allowed gap-1 text-[9.5px] h-5 px-1.5 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 bg-rose-50/50 dark:bg-rose-950/20"
                                                        title="Prepaid lock: Patient must clear invoice at cashier desk before medication can be dispensed."
                                                    >
                                                        <Lock class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400" />
                                                        <span>POS Locked</span>
                                                    </Button>
                                                </div>
                                                <div v-else-if="rx.status === 'Dispensed'" class="text-[10.5px] text-emerald-700 dark:text-emerald-400 font-semibold flex items-center justify-end gap-1">
                                                    <CheckCircle2 class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                                                    <span>Completed</span>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="prescriptions.length === 0">
                                            <TableCell colspan="8" class="text-center py-8 text-muted-foreground">
                                                <Pill class="w-6 h-6 mx-auto mb-1 text-muted-foreground opacity-50" />
                                                <p class="font-semibold text-foreground text-xs">No pending prescriptions in queue</p>
                                                <p class="text-[10px]">Orders issued by clinicians in consultation workstations will appear here.</p>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 2: DIRECT OTC & WALK-IN POS ================= -->
                        <div v-else-if="activeSection === 'otc'" class="w-full space-y-3">
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                                
                                <!-- LEFT: Formulary Drug Picker (7 cols) -->
                                <div class="lg:col-span-7 space-y-2.5">
                                    <div class="bg-card rounded-md border border-border/60 p-3 shadow-2xs space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                                                <Pill class="w-3.5 h-3.5 text-primary" />
                                                <span>Hospital Medication Formulary ({{ medications.length }} Drugs)</span>
                                            </h3>
                                            <span class="text-[10px] font-mono text-muted-foreground">FEFO Batch Deductions</span>
                                        </div>

                                        <SearchInput
                                            v-model="otcSearchQuery"
                                            size="default"
                                            placeholder="Search drug by brand or generic name (e.g. Paracetamol, Amox, Cetirizine)..."
                                        />

                                        <div class="divide-y divide-border/40 max-h-[480px] overflow-y-auto rounded border border-border/40">
                                            <div 
                                                v-for="med in filteredOtcMedications" 
                                                :key="med.id" 
                                                class="p-2 flex items-center justify-between hover:bg-muted/30 transition text-xs"
                                            >
                                                <div class="space-y-0.5 max-w-[65%]">
                                                    <div class="font-bold text-foreground">{{ med.generic_name }} <span v-if="med.brand_name" class="text-muted-foreground font-normal">({{ med.brand_name }})</span></div>
                                                    <div class="text-[10px] text-muted-foreground font-mono flex items-center gap-1.5">
                                                        <span>{{ med.strength }} · {{ med.form }}</span>
                                                        <span class="px-1.5 py-0.2 rounded bg-black/5 dark:bg-white/5 font-semibold">
                                                            Stock: {{ getMedicationTotalStock(med) }} units
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <div class="text-right">
                                                        <div class="font-bold text-primary text-xs">
                                                            TZS {{ Number(med.charge_code ? (med.item_master?.standard_price || 1000) : 1000).toLocaleString() }}
                                                        </div>
                                                        <div class="text-[9px] text-muted-foreground">per {{ med.form }}</div>
                                                    </div>
                                                    <Button 
                                                        size="sm" 
                                                        variant="outline" 
                                                        class="h-7 text-[11px] font-semibold gap-1"
                                                        :disabled="getMedicationTotalStock(med) <= 0"
                                                        @click="addMedicationToOtcCart(med)"
                                                    >
                                                        <Plus class="w-3 h-3" />
                                                        <span>Add</span>
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- RIGHT: Direct Sale POS Cart & Checkout (5 cols) -->
                                <div class="lg:col-span-5 space-y-2.5">
                                    <div class="bg-card rounded-md border border-border/60 p-3 shadow-2xs space-y-3">
                                        <div class="flex items-center justify-between border-b border-border/60 pb-2">
                                            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                                                <ShoppingCart class="w-3.5 h-3.5 text-primary" />
                                                <span>Walk-in Direct OTC Bill ({{ otcCart.length }} Items)</span>
                                            </h3>
                                            <span v-if="otcTicket" class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                                Ticket: {{ otcTicket.ticket_number }}
                                            </span>
                                        </div>

                                        <!-- Customer Identity Tag -->
                                        <div class="p-2 rounded bg-muted/30 border border-border/40 text-xs flex items-center justify-between">
                                            <div>
                                                <span class="text-[10px] text-muted-foreground uppercase font-bold block">Customer / Patient:</span>
                                                <span class="font-semibold text-foreground">
                                                    {{ otcPatient ? `${otcPatient.first_name} ${otcPatient.last_name} (${otcPatient.primary_mrn})` : 'Anonymous Walk-in Counter Customer' }}
                                                </span>
                                            </div>
                                            <button v-if="otcPatient" @click="otcPatient = null; otcTicket = null" class="text-[10px] text-rose-600 font-semibold hover:underline">
                                                Clear
                                            </button>
                                        </div>

                                        <!-- Out-of-Stock Warning Alert -->
                                        <div v-if="otcStockWarning" class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 font-medium min-w-0">
                                                <AlertTriangle class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                                <span class="truncate">{{ otcStockWarning }}</span>
                                            </div>
                                            <button @click="otcStockWarning = null" class="text-xs text-muted-foreground hover:text-foreground shrink-0 font-bold px-1">✕</button>
                                        </div>

                                        <!-- Cart Items List -->
                                        <div v-if="otcCart.length === 0" class="py-8 text-center text-muted-foreground text-xs space-y-1">
                                            <ShoppingBag class="w-8 h-8 mx-auto opacity-30" />
                                            <p class="font-semibold">Counter Cart is Empty</p>
                                            <p class="text-[10px]">Select formulary medications on the left to begin direct dispensing.</p>
                                        </div>

                                        <div v-else class="space-y-2 divide-y divide-border/40 max-h-[300px] overflow-y-auto">
                                            <div v-for="(item, idx) in otcCart" :key="idx" class="pt-2 flex items-center justify-between text-xs">
                                                <div class="space-y-0.5 max-w-[55%]">
                                                    <div class="font-bold text-foreground truncate">{{ item.name }}</div>
                                                    <div class="text-[10px] text-muted-foreground">{{ item.strength }} · TZS {{ item.unit_price.toLocaleString() }}</div>
                                                </div>

                                                <div class="flex items-center gap-1.5">
                                                    <div class="flex items-center border border-border rounded overflow-hidden">
                                                        <button 
                                                            type="button" 
                                                            class="px-1.5 py-0.5 bg-muted hover:bg-muted/80 text-xs font-bold"
                                                            @click="decreaseOtcQty(item)"
                                                        >-</button>
                                                        <input 
                                                            v-model.number="item.quantity" 
                                                            type="number" 
                                                            min="1" 
                                                            :max="item.available_stock" 
                                                            class="w-10 h-6 text-center text-xs border-x border-border bg-background" 
                                                        />
                                                        <button 
                                                            type="button" 
                                                            class="px-1.5 py-0.5 bg-muted hover:bg-muted/80 text-xs font-bold"
                                                            @click="increaseOtcQty(item)"
                                                        >+</button>
                                                    </div>

                                                    <div class="font-bold font-mono text-foreground text-right w-16">
                                                        TZS {{ (item.quantity * item.unit_price).toLocaleString() }}
                                                    </div>

                                                    <button @click="removeOtcItem(idx)" class="text-rose-600 hover:text-rose-800 p-1">
                                                        <Trash2 class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Notes / Instructions -->
                                        <div v-if="otcCart.length > 0">
                                            <label class="block text-[10px] font-bold uppercase text-muted-foreground mb-1">Pharmacist Counseling Notes / Posology</label>
                                            <Input v-model="otcNotes" placeholder="e.g. Take 2 tablets TID after meals for 3 days..." class="h-7 text-xs" />
                                        </div>

                                        <!-- Summary & Action -->
                                        <div v-if="otcCart.length > 0" class="pt-2 border-t border-border/60 space-y-2">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-muted-foreground font-semibold">Total Amount Due:</span>
                                                <span class="text-sm font-extrabold text-foreground font-mono">TZS {{ otcCartTotal.toLocaleString() }}</span>
                                            </div>

                                            <Button 
                                                class="w-full h-8 justify-center gap-1.5 font-bold shadow-2xs" 
                                                :disabled="isSubmittingOtc"
                                                @click="submitDirectOtcSale"
                                            >
                                                <Loader2 v-if="isSubmittingOtc" class="w-3.5 h-3.5 animate-spin" />
                                                <CheckCircle2 v-else class="w-3.5 h-3.5" />
                                                <span>{{ isSubmittingOtc ? 'Processing Sale...' : `Complete OTC Sale (TZS ${otcCartTotal.toLocaleString()})` }}</span>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= VIEW 3: FEFO FORMULARY & INVENTORY ================= -->
                        <div v-else-if="activeSection === 'formulary'" class="w-full space-y-3">
                            
                            <!-- Aggregate Metrics Banner (Clean Cards, No Outside Border) -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                    <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Formulary Drugs</div>
                                    <div class="text-base font-bold text-foreground font-mono">{{ inventoryMetrics.totalMeds }}</div>
                                </div>
                                <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                    <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Total Units on Hand</div>
                                    <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ formatCurrency(inventoryMetrics.totalUnits) }}</div>
                                </div>
                                <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                    <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Active FEFO Batches</div>
                                    <div class="text-base font-bold text-primary font-mono">{{ inventoryMetrics.activeBatchesCount }}</div>
                                </div>
                                <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                    <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Low Stock / Stockout</div>
                                    <div class="text-base font-bold text-rose-600 dark:text-rose-400 font-mono">{{ inventoryMetrics.stockouts + inventoryMetrics.lowStock }}</div>
                                </div>
                            </div>

                            <!-- Filter & Search Strip (Seamless Container) -->
                            <div class="flex flex-wrap items-center justify-between gap-2 bg-card p-2 rounded-lg shadow-2xs">
                                <SearchInput
                                    v-model="searchQuery"
                                    placeholder="Search by drug name, brand, or class..."
                                    class="flex-1 max-w-xs"
                                />
                                
                                <div class="flex items-center gap-1">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-6 px-2 text-[10.5px]"
                                        :class="{ 'bg-primary text-primary-foreground font-semibold': stockFilter === 'all' }"
                                        @click="stockFilter = 'all'"
                                    >
                                        All
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-6 px-2 text-[10.5px]"
                                        :class="{ 'bg-emerald-600 text-white font-semibold dark:bg-emerald-700': stockFilter === 'in_stock' }"
                                        @click="stockFilter = 'in_stock'"
                                    >
                                        In Stock
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-6 px-2 text-[10.5px]"
                                        :class="{ 'bg-amber-600 text-white font-semibold dark:bg-amber-700': stockFilter === 'low_stock' }"
                                        @click="stockFilter = 'low_stock'"
                                    >
                                        Low Stock
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-6 px-2 text-[10.5px]"
                                        :class="{ 'bg-rose-600 text-white font-semibold dark:bg-rose-700': stockFilter === 'stockout' }"
                                        @click="stockFilter = 'stockout'"
                                    >
                                        Stockouts
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-6 px-2 text-[10.5px]"
                                        :class="{ 'bg-purple-600 text-white font-semibold dark:bg-purple-700': stockFilter === 'expiring' }"
                                        @click="stockFilter = 'expiring'"
                                    >
                                        Expiring (&lt;30d)
                                    </Button>
                                </div>
                            </div>

                            <!-- Inventory Formulary Data Table -->
                            <div class="w-full bg-card rounded-md overflow-hidden shadow-2xs flex flex-col border border-border/60">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3">Medication & Formulary</TableHead>
                                            <TableHead class="py-1 px-3">Form & Strength</TableHead>
                                            <TableHead class="py-1 px-3">Drug Class</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Active Batches</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Earliest Expiry (FEFO)</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Stock on Hand</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[130px]">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="med in filteredMedications"
                                            :key="med.id"
                                            class="h-9 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                            :class="{ 'bg-primary/5': selectedMedication?.id === med.id }"
                                            @click="selectMed(med)"
                                        >
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground text-[11px]">{{ med.generic_name }}</div>
                                                <div v-if="med.brand_name" class="text-[9.5px] text-muted-foreground">{{ med.brand_name }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-[10.5px]">
                                                {{ med.strength }} · {{ med.form }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[10.5px]">
                                                {{ med.drug_class || 'General' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-center text-[11px]">
                                                {{ med.active_batches_count }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span v-if="med.earliest_expiry_date" class="font-mono text-[10.5px] text-foreground font-semibold">
                                                    {{ formatDate(med.earliest_expiry_date) }}
                                                </span>
                                                <span v-else class="text-muted-foreground text-[10.5px]">—</span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-center">
                                                <span 
                                                    class="inline-block px-1.5 py-0.2 rounded text-[10.5px]"
                                                    :class="med.total_stock_on_hand > (med.reorder_level || 50) ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' : (med.total_stock_on_hand > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800' : 'bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800')"
                                                >
                                                    {{ formatCurrency(med.total_stock_on_hand) }} units
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="med.stock_status === 'In Stock' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : (med.stock_status === 'Low Stock' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300')"
                                                >
                                                    {{ med.stock_status }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[130px]">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        class="h-6 px-2 text-[10px] font-semibold rounded border border-border/70 hover:bg-muted text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1 cursor-pointer"
                                                        @click.stop="viewMedicationBinCard(med)"
                                                        title="View Electronic Bin Card (Stock Movements Ledger for this medication)"
                                                    >
                                                        <History class="w-3 h-3 text-primary" />
                                                        <span>Bin Card</span>
                                                    </button>
                                                    <Button 
                                                        variant="subtle" 
                                                        size="sm" 
                                                        class="h-6 px-2 text-[10.5px] font-semibold"
                                                        @click.stop="openReceiveModal(med)"
                                                    >
                                                        Receive
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 3: STOCK MOVEMENTS LEDGER ================= -->
                        <div v-else-if="activeSection === 'movements'" class="w-full space-y-3">
                            <!-- Active Bin Card Filter Banner -->
                            <div v-if="activeBinCardMedication" class="p-3 bg-primary/10 rounded-lg border border-primary/30 flex flex-wrap items-center justify-between gap-2 shadow-2xs">
                                <div class="flex items-center gap-2.5">
                                    <div class="p-2 bg-primary text-primary-foreground rounded-md shadow-2xs">
                                        <History class="w-4.5 h-4.5" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-foreground uppercase tracking-wide">Electronic Bin Card (Kadi ya Akiba)</span>
                                            <span class="px-1.5 py-0.2 bg-primary/20 text-primary rounded text-[10px] font-mono font-bold">{{ activeBinCardMedication.brand_name || activeBinCardMedication.generic_name }}</span>
                                        </div>
                                        <div class="text-[11px] text-muted-foreground flex items-center gap-3 mt-0.5">
                                            <span>Generic: <strong class="text-foreground">{{ activeBinCardMedication.generic_name }}</strong></span>
                                            <span>Strength: <strong class="text-foreground">{{ activeBinCardMedication.strength || 'Standard' }}</strong></span>
                                            <span>Current Stock: <strong class="text-emerald-600 dark:text-emerald-400 font-mono">{{ formatCurrency(activeBinCardMedication.total_stock_on_hand || 0) }} units</strong></span>
                                        </div>
                                    </div>
                                </div>
                                <Button variant="outline" size="sm" class="h-7 text-xs font-semibold gap-1 bg-background" @click="clearBinCardFilter">
                                    <X class="w-3.5 h-3.5" />
                                    <span>Show All Medications</span>
                                </Button>
                            </div>

                            <div class="w-full bg-card rounded-md overflow-hidden shadow-2xs flex flex-col border border-border/60">
                                <!-- Movements Toolbar Header -->
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                            <History class="w-3.5 h-3.5 text-primary" />
                                            <span>Stock Ledger Movements ({{ filteredMovements.length }})</span>
                                        </div>

                                        <!-- Live Search Box -->
                                        <SearchInput
                                            v-model="movementSearchQuery"
                                            placeholder="Search drug, batch #, dispenser, Rx #..."
                                            class="w-60 md:w-72"
                                        />
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <!-- Movement Type Filter Pills -->
                                        <div class="flex items-center gap-1 bg-muted/60 p-0.5 rounded-md text-[10px]">
                                            <button
                                                type="button"
                                                @click="movementTypeFilter = 'all'"
                                                class="px-2 py-0.5 rounded font-semibold transition-colors cursor-pointer"
                                                :class="movementTypeFilter === 'all' ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                            >
                                                All ({{ movementCounts.all }})
                                            </button>
                                            <button
                                                type="button"
                                                @click="movementTypeFilter = 'Received'"
                                                class="px-2 py-0.5 rounded font-semibold transition-colors cursor-pointer"
                                                :class="movementTypeFilter === 'Received' ? 'bg-card text-emerald-700 dark:text-emerald-400 shadow-2xs font-bold' : 'text-muted-foreground hover:text-foreground'"
                                            >
                                                Received ({{ movementCounts.received }})
                                            </button>
                                            <button
                                                type="button"
                                                @click="movementTypeFilter = 'Dispensed'"
                                                class="px-2 py-0.5 rounded font-semibold transition-colors cursor-pointer"
                                                :class="movementTypeFilter === 'Dispensed' ? 'bg-card text-blue-700 dark:text-blue-400 shadow-2xs font-bold' : 'text-muted-foreground hover:text-foreground'"
                                            >
                                                Dispensed ({{ movementCounts.dispensed }})
                                            </button>
                                            <button
                                                type="button"
                                                @click="movementTypeFilter = 'Adjustment'"
                                                class="px-2 py-0.5 rounded font-semibold transition-colors cursor-pointer"
                                                :class="movementTypeFilter === 'Adjustment' ? 'bg-card text-amber-700 dark:text-amber-400 shadow-2xs font-bold' : 'text-muted-foreground hover:text-foreground'"
                                            >
                                                Adjusted ({{ movementCounts.adjustments }})
                                            </button>
                                        </div>

                                        <!-- Date Range Filter -->
                                        <Select
                                            v-model="movementDateFilter"
                                            class="h-7 rounded-md border border-input bg-background text-foreground px-2 py-0 text-[10.5px] shadow-2xs"
                                        >
                                            <option value="all">All Dates</option>
                                            <option value="today">Today</option>
                                            <option value="week">Past 7 Days</option>
                                            <option value="month">Past 30 Days</option>
                                        </Select>

                                        <!-- Clear Filters -->
                                        <button
                                            v-if="movementSearchQuery || movementTypeFilter !== 'all' || movementDateFilter !== 'all'"
                                            @click="clearMovementFilters"
                                            class="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                            title="Reset all filters"
                                        >
                                            <RotateCcw class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3">Date & Time</TableHead>
                                            <TableHead class="py-1 px-3">Medication</TableHead>
                                            <TableHead class="py-1 px-3">Batch Number</TableHead>
                                            <TableHead class="py-1 px-3">Movement Type</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Change</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Stock Level</TableHead>
                                            <TableHead class="py-1 px-3">Performed By</TableHead>
                                            <TableHead class="py-1 px-3">Notes / Reference</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="mv in filteredMovements"
                                            :key="mv.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedMovement?.id === mv.id }"
                                            @click="selectMovement(mv)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono text-[9.5px] text-muted-foreground whitespace-nowrap">
                                                {{ formatDateTime(mv.created_at) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-semibold text-foreground text-[11px]">
                                                <div>{{ mv.medication?.generic_name }}</div>
                                                <div v-if="mv.medication?.brand_name && mv.medication.brand_name !== mv.medication.generic_name" class="text-[9.5px] text-muted-foreground font-normal">
                                                    {{ mv.medication.brand_name }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[10.5px]">
                                                {{ mv.batch?.batch_number || '—' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="mv.movement_type === 'Received' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : (mv.movement_type === 'Dispensed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300')"
                                                >
                                                    {{ mv.movement_type }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-center text-[10.5px]">
                                                <span :class="mv.quantity_change > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                                    {{ mv.quantity_change > 0 ? `+${mv.quantity_change}` : mv.quantity_change }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-center text-muted-foreground text-[10.5px]">
                                                {{ mv.quantity_before }} → <span class="font-bold text-foreground">{{ mv.quantity_after }}</span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[10.5px]">
                                                {{ mv.performer?.first_name }} {{ mv.performer?.last_name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[9.5px] truncate max-w-[180px]">
                                                {{ mv.notes || mv.reference_type }}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow v-if="filteredMovements.length === 0">
                                            <TableCell colspan="8" class="text-center py-8 text-muted-foreground text-xs space-y-1">
                                                <div>No stock movements match your search or filter criteria.</div>
                                                <button
                                                    v-if="movementSearchQuery || movementTypeFilter !== 'all' || movementDateFilter !== 'all' || activeBinCardMedication"
                                                    @click="clearMovementFilters"
                                                    class="text-xs text-primary font-semibold hover:underline cursor-pointer"
                                                >
                                                    Clear all filters
                                                </button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: Context Inspector & Forensic Audit Drawer (Compact) -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    :title="contextTitle"
                    :icon="activeSection === 'movements' ? History : Pill"
                    :width="width"
                    @close="close"
                >
                    <!-- View 1: Queue Prescription Context -->
                    <div v-if="activeSection === 'queue' && selectedPrescription" class="space-y-2.5 text-xs">
                        <AfyaPatientIdentity v-if="selectedPrescription.patient" :patient="selectedPrescription.patient">
                            <AfyaStatusBadge :status="selectedPrescription.status" dot />
                        </AfyaPatientIdentity>

                        <!-- POS Payment Status Alert Warning -->
                        <div
                            v-if="!isPaid(selectedPrescription)"
                            class="p-2 rounded bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-950 dark:text-rose-200 text-xs flex items-center justify-between"
                        >
                            <div class="flex items-center gap-1.5 font-bold text-rose-900 dark:text-rose-300 text-[10.5px]">
                                <Lock class="w-3 h-3 text-rose-600 dark:text-rose-400 shrink-0" />
                                <span>Unpaid POS Invoice</span>
                            </div>
                            <Link v-if="can.billing" :href="route('billing.desk')">
                                <Button variant="outline" size="sm" class="h-5 px-1.5 text-[9.5px] bg-card text-rose-900 dark:text-rose-200 border-rose-300 dark:border-rose-800">
                                    Cashier Desk →
                                </Button>
                            </Link>
                            <span v-else class="text-[9.5px] font-semibold text-rose-800 dark:text-rose-300">Requires POS Settlement</span>
                        </div>

                        <!-- Medication Order Details -->
                        <div class="p-2.5 rounded bg-card border border-border/70 space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Prescribed Medication
                            </div>
                            <div class="space-y-1 text-[10.5px]">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Drug Name:</span>
                                    <span class="font-bold text-foreground truncate max-w-[140px]">{{ selectedPrescription.medication?.generic_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Form & Strength:</span>
                                    <span class="font-mono text-[9.5px]">{{ selectedPrescription.medication?.strength }} ({{ selectedPrescription.medication?.form }})</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Dosage & Frequency:</span>
                                    <span class="font-semibold text-foreground text-[9.5px]">{{ selectedPrescription.dosage }} · {{ selectedPrescription.frequency }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Duration & Route:</span>
                                    <span class="font-mono">{{ selectedPrescription.duration_days }}d ({{ selectedPrescription.route }})</span>
                                </div>
                                <div class="flex justify-between items-center pt-1 border-t border-border/40">
                                    <span class="text-muted-foreground">Prescribed Quantity:</span>
                                    <span class="font-mono font-bold text-xs text-foreground">{{ selectedPrescription.quantity }} units</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-muted-foreground">Stock on Hand:</span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ selectedPrescription.medication?.total_stock_on_hand ?? 0 }} units</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View 2: Formulary Medication Batches Context -->
                    <div v-else-if="activeSection === 'formulary' && selectedMedication" class="space-y-2.5 text-xs">
                        <div class="p-2.5 bg-card border border-border/70 rounded-md space-y-1">
                            <div class="font-bold text-foreground text-xs">{{ selectedMedication.generic_name }}</div>
                            <div v-if="selectedMedication.brand_name" class="text-[10.5px] text-muted-foreground">{{ selectedMedication.brand_name }}</div>
                            <div class="text-[10px] font-mono text-muted-foreground pt-0.5">
                                {{ selectedMedication.strength }} · {{ selectedMedication.form }} ({{ selectedMedication.route }})
                            </div>
                        </div>

                        <!-- Active Batches Breakdown (FEFO Order) -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                <span>Tracked Batches (FEFO)</span>
                                <Button v-if="can.storeBatch" variant="subtle" size="sm" class="h-6 px-2 text-[10.5px] font-semibold gap-1" @click="openReceiveModal(selectedMedication)">
                                    <Plus class="w-3 h-3" />
                                    <span>Add Batch</span>
                                </Button>
                            </div>

                            <div v-if="selectedMedication.batches && selectedMedication.batches.length > 0" class="space-y-1.5">
                                <div
                                    v-for="batch in selectedMedication.batches"
                                    :key="batch.id"
                                    class="p-2 rounded border border-border/60 bg-card space-y-1"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-mono font-bold text-primary text-[10.5px]">{{ batch.batch_number }}</span>
                                        <span 
                                            class="px-1.5 py-0.2 rounded text-[8.5px] font-bold"
                                            :class="batch.stock_status === 'Available' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : (batch.stock_status === 'Expiring Soon' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300')"
                                        >
                                            {{ batch.stock_status }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-0.5 text-[9.5px] text-muted-foreground">
                                        <div>Expiry: <span class="font-mono font-bold text-foreground">{{ formatDate(batch.expiry_date) }}</span></div>
                                        <div>Available: <span class="font-mono font-bold text-foreground">{{ batch.current_quantity }} / {{ batch.initial_quantity }}</span></div>
                                        <div class="col-span-2">Supplier: <span class="font-medium text-foreground">{{ batch.supplier_name || 'MSD' }}</span></div>
                                    </div>
                                    <div class="flex justify-end pt-1 border-t border-border/40">
                                        <Button v-if="can.adjustBatch" variant="outline" size="sm" class="h-4.5 px-1.5 text-[8.5px]" @click="openAdjustModal(batch)">
                                            Adjust Stock
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-4 text-muted-foreground text-[10.5px] border border-dashed border-border rounded">
                                No active batches.
                            </div>
                        </div>
                    </div>

                    <!-- View 3: Forensic Stock Movement Inspector -->
                    <div v-else-if="activeSection === 'movements' && selectedMovement" class="space-y-2.5 text-xs">
                        
                        <!-- Transaction Overview Card -->
                        <div class="p-2.5 bg-card border border-border/70 rounded-md space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span 
                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                    :class="selectedMovement.movement_type === 'Received' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : (selectedMovement.movement_type === 'Dispensed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300')"
                                >
                                    {{ selectedMovement.movement_type }}
                                </span>
                                <span class="text-[9.5px] font-mono text-muted-foreground">
                                    {{ formatDateTime(selectedMovement.created_at) }}
                                </span>
                            </div>
                            <div class="font-bold text-foreground text-xs">
                                {{ selectedMovement.medication?.generic_name }}
                                <span v-if="selectedMovement.medication?.brand_name" class="font-normal text-muted-foreground">({{ selectedMovement.medication?.brand_name }})</span>
                            </div>
                            <div class="text-[10px] font-mono text-muted-foreground">
                                {{ selectedMovement.medication?.strength }} · {{ selectedMovement.medication?.form }} ({{ selectedMovement.medication?.route }})
                            </div>
                        </div>

                        <!-- Quantity Telemetry & Balance Card -->
                        <div class="p-2.5 bg-muted/25 border border-border/70 rounded-md space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Inventory Balance Transition
                            </div>
                            <div class="grid grid-cols-3 gap-1.5 text-center pt-0.5">
                                <div class="p-1.5 bg-card rounded border border-border/50">
                                    <span class="block text-[8.5px] text-muted-foreground uppercase">Before</span>
                                    <strong class="font-mono text-[11px] text-foreground">{{ selectedMovement.quantity_before }}</strong>
                                </div>
                                <div class="p-1.5 bg-card rounded border border-border/50">
                                    <span class="block text-[8.5px] text-muted-foreground uppercase">Change</span>
                                    <strong 
                                        class="font-mono text-[11px]"
                                        :class="selectedMovement.quantity_change > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                    >
                                        {{ selectedMovement.quantity_change > 0 ? `+${selectedMovement.quantity_change}` : selectedMovement.quantity_change }}
                                    </strong>
                                </div>
                                <div class="p-1.5 bg-card rounded border border-border/50">
                                    <span class="block text-[8.5px] text-muted-foreground uppercase">After</span>
                                    <strong class="font-mono text-[11px] text-foreground">{{ selectedMovement.quantity_after }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Batch & Supplier Context Card -->
                        <div class="p-2.5 bg-card border border-border/70 rounded-md space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Batch Context
                            </div>
                            <div class="space-y-0.5 text-[10.5px]">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Batch Number:</span>
                                    <span class="font-mono font-bold text-primary">{{ selectedMovement.batch?.batch_number || '—' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Expiry Date:</span>
                                    <span class="font-mono font-semibold text-foreground">{{ formatDate(selectedMovement.batch?.expiry_date) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Supplier:</span>
                                    <span class="font-medium text-foreground">{{ selectedMovement.batch?.supplier_name || 'Direct / Unspecified' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Unit Cost:</span>
                                    <span class="font-mono">TZS {{ formatCurrency(selectedMovement.batch?.unit_cost) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Audit Ledger & Actor Identity -->
                        <div class="p-2.5 bg-card border border-border/70 rounded-md space-y-1">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Traceability
                            </div>
                            <div class="space-y-0.5 text-[10.5px]">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Officer:</span>
                                    <span class="font-bold text-foreground">{{ selectedMovement.performer?.first_name }} {{ selectedMovement.performer?.last_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Ref Type:</span>
                                    <span class="font-mono text-[9.5px]">{{ selectedMovement.reference_type || 'Internal' }}</span>
                                </div>
                                <div v-if="selectedMovement.notes" class="pt-1 mt-1 border-t border-border/40">
                                    <p class="text-foreground text-[9.5px] italic bg-muted/20 p-1 rounded">
                                        "{{ selectedMovement.notes }}"
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div v-else class="text-center py-6 text-muted-foreground text-xs">
                        Select a record to preview details.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- ================= MODAL 1: FEFO DISPENSE CONFIRMATION ================= -->
        <Modal :show="showDispenseModal" max-width="md" @close="closeDispenseModal">
            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-border pb-2.5">
                    <div class="flex items-center gap-1.5">
                        <Pill class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-xs text-foreground">Dispense Medication — FEFO Allocation</h3>
                    </div>
                    <button @click="closeDispenseModal" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="dispenseRx" class="space-y-2.5 text-xs">
                    <div class="p-2 bg-muted/30 rounded border border-border/60 space-y-0.5 text-[11px]">
                        <div class="font-bold text-foreground text-xs">{{ dispenseRx.medication?.generic_name }}</div>
                        <div class="text-muted-foreground">{{ dispenseRx.dosage }} · {{ dispenseRx.frequency }} ({{ dispenseRx.duration_days }}d)</div>
                        <div class="text-[10px] text-muted-foreground pt-1 border-t border-border/40 flex justify-between">
                            <span>Patient: <strong class="text-foreground">{{ dispenseRx.patient?.first_name }} {{ dispenseRx.patient?.last_name }}</strong></span>
                            <span>Stock: <strong class="text-emerald-600 dark:text-emerald-400 font-mono">{{ totalStockForDispenseMed }} units</strong></span>
                        </div>
                    </div>

                    <div class="space-y-0.5">
                        <label class="block font-bold text-[11px] text-foreground">Quantity to Dispense</label>
                        <Input
                            v-model="dispenseQty"
                            type="number"
                            min="1"
                            :max="dispenseRx.quantity"
                            class="w-full font-mono h-8 text-xs"
                            autofocus
                        />
                        <p class="text-[9.5px] text-muted-foreground">Prescribed Total Quantity: {{ dispenseRx.quantity }} units</p>
                    </div>

                    <div v-if="!isStockSufficient" class="p-2 rounded bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-300 text-xs flex items-center gap-1.5">
                        <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 shrink-0" />
                        <div>
                            <strong>Stockout Warning:</strong>
                            <p class="text-[10px]">Requested {{ dispenseQty }} units, but only {{ totalStockForDispenseMed }} units available.</p>
                        </div>
                    </div>

                    <!-- FEFO Batch Allocation Preview -->
                    <div v-if="suggestedBatches.length > 0" class="space-y-1 pt-0.5">
                        <label class="block font-bold text-[9.5px] text-muted-foreground uppercase tracking-wider">
                            FEFO Batch Allocation Schedule
                        </label>
                        <div class="border border-border/60 rounded overflow-hidden">
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="h-6 text-[8.5px] bg-muted/40 uppercase font-bold text-muted-foreground">
                                        <TableHead class="py-0.5 px-2">Batch #</TableHead>
                                        <TableHead class="py-0.5 px-2">Expiry Date</TableHead>
                                        <TableHead class="py-0.5 px-2 text-right">Deducted Qty</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="b in suggestedBatches" :key="b.batch_number" class="h-7 border-b border-border/30 text-[10.5px]">
                                        <TableCell class="py-0.5 px-2 font-mono font-bold text-primary">{{ b.batch_number }}</TableCell>
                                        <TableCell class="py-0.5 px-2 font-mono">{{ formatDate(b.expiry_date) }}</TableCell>
                                        <TableCell class="py-0.5 px-2 font-mono font-bold text-right text-emerald-600 dark:text-emerald-400">{{ b.deduct }} units</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <Button variant="outline" size="sm" class="h-7 text-xs" @click="closeDispenseModal" :disabled="isDispensing">Cancel</Button>
                    <Button 
                        variant="default" 
                        size="sm" 
                        class="h-7 text-xs font-semibold"
                        @click="confirmDispense" 
                        :disabled="isDispensing || !dispenseQty || !isStockSufficient"
                    >
                        <Loader2 v-if="isDispensing" class="w-3 h-3 animate-spin mr-1" />
                        <span>Confirm FEFO Dispense</span>
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 2: RECEIVE STOCK BATCH ================= -->
        <Modal :show="showReceiveModal" max-width="md" @close="closeReceiveModal">
            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-border pb-2.5">
                    <div class="flex items-center gap-1.5">
                        <Package class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-xs text-foreground">Receive Stock Batch (Goods Receipt)</h3>
                    </div>
                    <button @click="closeReceiveModal" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitReceiveBatch" class="space-y-2.5 text-xs">
                    <div class="space-y-0.5">
                        <label class="block font-bold text-[11px] text-foreground">Medication Formulary *</label>
                        <Select 
                            v-model="receiveForm.medication_id" 
                            required
                            class="w-full h-8 rounded border border-input bg-background text-foreground px-2.5 py-0 text-xs shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="m in medications" :key="m.id" :value="m.id">
                                {{ m.generic_name }} ({{ m.strength }} - {{ m.form }})
                            </option>
                        </Select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Batch Number *</label>
                            <Input v-model="receiveForm.batch_number" required placeholder="e.g. MSD-2026-001 or ZN-5421" class="font-mono h-8 text-xs" />
                        </div>
                        <div>
                            <AfyaDatePicker
                                v-model="receiveForm.expiry_date"
                                label="Expiry Date"
                                required
                                size="sm"
                                :min="new Date().toISOString().split('T')[0]"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Quantity Received *</label>
                            <Input v-model="receiveForm.quantity" type="number" min="1" required placeholder="e.g. 100" class="font-mono h-8 text-xs" />
                        </div>
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Supplier Name</label>
                            <Input v-model="receiveForm.supplier_name" placeholder="e.g. MSD / Zenufa / Crown" class="h-8 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Unit Cost (TZS)</label>
                            <Input v-model="receiveForm.unit_cost" type="number" min="0" placeholder="e.g. 500" class="font-mono h-8 text-xs" />
                        </div>
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Selling Price (TZS)</label>
                            <Input v-model="receiveForm.unit_selling_price" type="number" min="0" placeholder="e.g. 1000" class="font-mono h-8 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-0.5">
                        <label class="block font-bold text-[11px] text-foreground">Notes / Reference #</label>
                        <Input v-model="receiveForm.notes" placeholder="e.g. PO-2026-0811 delivery" class="h-8 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2.5 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-7 text-xs" @click="closeReceiveModal" :disabled="isReceiving">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-7 text-xs font-semibold" :disabled="isReceiving || !receiveForm.batch_number || !receiveForm.quantity || !receiveForm.expiry_date">
                            <Loader2 v-if="isReceiving" class="w-3 h-3 animate-spin mr-1" />
                            <span>Save Batch & Update Ledger</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ================= MODAL 3: STOCK ADJUSTMENT ================= -->
        <Modal :show="showAdjustModal" max-width="sm" @close="closeAdjustModal">
            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-border pb-2.5">
                    <div class="flex items-center gap-1.5">
                        <SlidersHorizontal class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-xs text-foreground">Stock Count Adjustment</h3>
                    </div>
                    <button @click="closeAdjustModal" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="adjustBatch" class="space-y-2 text-xs">
                    <div class="p-2 bg-muted/40 rounded border border-border/60 space-y-0.5 text-[10.5px]">
                        <div>Batch: <strong class="font-mono text-primary">{{ adjustBatch.batch_number }}</strong></div>
                        <div>Current System Stock: <strong class="font-mono">{{ adjustBatch.current_quantity }} units</strong></div>
                    </div>

                    <div class="space-y-0.5">
                        <label class="block font-bold text-[11px] text-foreground">Actual Counted Quantity *</label>
                        <Input v-model="adjustQty" type="number" min="0" class="font-mono h-8 text-xs" />
                    </div>

                    <div class="space-y-0.5">
                        <label class="block font-bold text-[11px] text-foreground">Standard Audit Reason *</label>
                        <Select
                            v-model="adjustReasonPreset"
                            class="w-full h-8 rounded border border-input bg-background text-foreground px-2.5 py-0 text-xs shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="r in adjustmentReasons" :key="r" :value="r">
                                {{ r }}
                            </option>
                        </Select>
                    </div>

                    <div class="space-y-0.5">
                        <label class="block font-bold text-[11px] text-foreground">
                            {{ adjustReasonPreset === 'Other / Custom Reason' ? 'Specific Reason / Audit Detail *' : 'Optional Notes / Audit Detail' }}
                        </label>
                        <Input
                            v-model="adjustCustomNotes"
                            :required="adjustReasonPreset === 'Other / Custom Reason'"
                            placeholder="e.g. Shelf recount after physical verification"
                            class="h-8 text-xs"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2.5 border-t border-border">
                    <Button variant="outline" size="sm" class="h-7 text-xs" @click="closeAdjustModal" :disabled="isAdjusting">Cancel</Button>
                    <Button variant="default" size="sm" class="h-7 text-xs font-semibold" @click="submitAdjustStock" :disabled="isAdjusting || (adjustReasonPreset === 'Other / Custom Reason' && !adjustCustomNotes)">
                        <Loader2 v-if="isAdjusting" class="w-3 h-3 animate-spin mr-1" />
                        <span>Confirm Adjustment</span>
                    </Button>
                </div>
            </div>
        </Modal>

    </AfyaShell>
</template>
