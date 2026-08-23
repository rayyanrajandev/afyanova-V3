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
    Search,
    SlidersHorizontal,
    FileText,
    History,
    Activity,
    Check,
    ArrowRight,
    ArrowUpRight,
    TrendingUp,
    TrendingDown
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
import Input from '@/Components/ui/Input.vue';
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
    initialSection: {
        type: String,
        default: 'queue',
    },
});

const { preferences, openContext } = useWorkspacePreferences();

const activeSection = ref(props.initialSection || 'queue');
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
    quantity: 100,
    unit_cost: 0,
    unit_selling_price: 0,
    supplier_name: 'MSD (Medical Stores Department)',
    notes: '',
});

// Adjust Stock Modal State
const showAdjustModal = ref(false);
const adjustBatch = ref(null);
const adjustQty = ref(0);
const adjustReason = ref('');
const isAdjusting = ref(false);

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
    if (!rx || !rx.encounter || !rx.encounter.invoices || rx.encounter.invoices.length === 0) {
        return false;
    }
    const latestInvoice = rx.encounter.invoices[0];
    return latestInvoice.status === 'Paid';
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
        batch_number: `BATCH-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`,
        expiry_date: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        manufacture_date: new Date().toISOString().split('T')[0],
        quantity: 100,
        unit_cost: 500,
        unit_selling_price: 1000,
        supplier_name: 'MSD (Medical Stores Department)',
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
    adjustReason.value = 'Annual physical stock count reconciliation';
    showAdjustModal.value = true;
};

const closeAdjustModal = () => {
    showAdjustModal.value = false;
    adjustBatch.value = null;
};

const submitAdjustStock = () => {
    if (!adjustBatch.value) return;
    isAdjusting.value = true;
    router.post(route('pharmacy.batches.adjust', adjustBatch.value.id), {
        new_quantity: Number(adjustQty.value),
        reason: adjustReason.value,
    }, {
        onFinish: () => {
            isAdjusting.value = false;
            closeAdjustModal();
        }
    });
};

// Filtered Medications List
const filteredMedications = computed(() => {
    return props.medications.filter(med => {
        const matchesQuery = !searchQuery.value || 
            med.generic_name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (med.brand_name && med.brand_name.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
            (med.drug_class && med.drug_class.toLowerCase().includes(searchQuery.value.toLowerCase()));

        if (!matchesQuery) return false;

        if (stockFilter.value === 'in_stock') return med.total_stock_on_hand > 50;
        if (stockFilter.value === 'low_stock') return med.total_stock_on_hand > 0 && med.total_stock_on_hand <= 50;
        if (stockFilter.value === 'stockout') return med.total_stock_on_hand <= 0;
        if (stockFilter.value === 'expiring') {
            return med.batches && med.batches.some(b => b.days_to_expiry <= 30 && b.days_to_expiry > 0 && b.current_quantity > 0);
        }

        return true;
    });
});

// Inventory Aggregate Metrics
const inventoryMetrics = computed(() => {
    const totalMeds = props.medications.length;
    const totalUnits = props.medications.reduce((acc, m) => acc + (m.total_stock_on_hand || 0), 0);
    const stockouts = props.medications.filter(m => (m.total_stock_on_hand || 0) === 0).length;
    const lowStock = props.medications.filter(m => (m.total_stock_on_hand || 0) > 0 && (m.total_stock_on_hand || 0) <= 50).length;
    const activeBatchesCount = props.batches.filter(b => b.status === 'Active' && b.current_quantity > 0).length;
    
    return {
        totalMeds,
        totalUnits,
        stockouts,
        lowStock,
        activeBatchesCount,
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
                        label="Dispensing Queue"
                        :icon="Pill"
                        :badge="prescriptions.length"
                        :active="activeSection === 'queue'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'queue'"
                    />
                    
                    <AfyaSidebarItem
                        label="FEFO Stock & Formulary"
                        :icon="Package"
                        :badge="inventoryMetrics.totalUnits"
                        :active="activeSection === 'formulary'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'formulary'"
                    />

                    <AfyaSidebarItem
                        label="Stock Movements Ledger"
                        :icon="History"
                        :badge="recentMovements.length"
                        :active="activeSection === 'movements'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'movements'"
                    />

                    <AfyaSidebarItem
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
                                v-if="activeSection === 'formulary'"
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
                                            <TableHead class="py-1 px-3 text-right w-36">Pharmacist Action</TableHead>
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
                                                    :class="rx.medication?.total_stock_on_hand > 50 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' : (rx.medication?.total_stock_on_hand > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800' : 'bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800')"
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
                                            <TableCell class="py-1 px-3 text-right w-36">
                                                <div v-if="rx.status === 'Pending'" class="flex items-center justify-end gap-1">
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
                                                        v-if="isPaid(rx)"
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

                        <!-- ================= VIEW 2: FEFO FORMULARY & INVENTORY ================= -->
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
                                <div class="flex items-center gap-1.5 flex-1 max-w-xs">
                                    <Search class="w-3.5 h-3.5 text-muted-foreground" />
                                    <Input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Search by drug name, brand, or class..."
                                        class="h-7 text-xs w-full"
                                    />
                                </div>
                                
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
                                            <TableHead class="py-1 px-3 text-right">Actions</TableHead>
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
                                                    :class="med.total_stock_on_hand > 50 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' : (med.total_stock_on_hand > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800' : 'bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800')"
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
                                            <TableCell class="py-1 px-3 text-right">
                                                <div class="flex items-center justify-end gap-1">
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
                            <div class="w-full bg-card rounded-md overflow-hidden shadow-2xs flex flex-col border border-border/60">
                                <div class="px-3 py-1.5 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <History class="w-3.5 h-3.5 text-primary" />
                                        <span>Immutable Stock Movements Ledger ({{ recentMovements.length }})</span>
                                    </div>
                                    <div class="text-[10.5px] text-muted-foreground">
                                        <span>Click row to inspect forensic audit trail</span>
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
                                            v-for="mv in recentMovements"
                                            :key="mv.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedMovement?.id === mv.id }"
                                            @click="selectMovement(mv)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono text-[9.5px] text-muted-foreground">
                                                {{ formatDate(mv.created_at) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-semibold text-foreground text-[11px]">
                                                {{ mv.medication?.generic_name }}
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
                                        <TableRow v-if="recentMovements.length === 0">
                                            <TableCell colspan="8" class="text-center py-6 text-muted-foreground text-xs">
                                                No stock movements recorded yet.
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
                                <Lock class="w-3 h-3 text-rose-600 dark:text-rose-400 flex-shrink-0" />
                                <span>Unpaid POS Invoice</span>
                            </div>
                            <Link :href="route('billing.desk')">
                                <Button variant="outline" size="sm" class="h-5 px-1.5 text-[9.5px] bg-card text-rose-900 dark:text-rose-200 border-rose-300 dark:border-rose-800">
                                    Cashier Desk →
                                </Button>
                            </Link>
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
                                <Button variant="subtle" size="sm" class="h-6 px-2 text-[10.5px] font-semibold gap-1" @click="openReceiveModal(selectedMedication)">
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
                                        <Button variant="outline" size="sm" class="h-4.5 px-1.5 text-[8.5px]" @click="openAdjustModal(batch)">
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
                                    <span class="font-medium text-foreground">{{ selectedMovement.batch?.supplier_name || 'MSD Tanzania' }}</span>
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
                        <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 flex-shrink-0" />
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
                        <select 
                            v-model="receiveForm.medication_id" 
                            required
                            class="w-full h-8 rounded border border-input bg-background text-foreground px-2.5 py-0 text-xs shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="m in medications" :key="m.id" :value="m.id">
                                {{ m.generic_name }} ({{ m.strength }} - {{ m.form }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Batch Number *</label>
                            <Input v-model="receiveForm.batch_number" required placeholder="e.g. MSD-2026-001" class="font-mono h-8 text-xs" />
                        </div>
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Expiry Date *</label>
                            <Input v-model="receiveForm.expiry_date" type="date" required class="h-8 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Quantity Received *</label>
                            <Input v-model="receiveForm.quantity" type="number" min="1" required class="font-mono h-8 text-xs" />
                        </div>
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Supplier Name</label>
                            <Input v-model="receiveForm.supplier_name" placeholder="e.g. MSD / Zenufa" class="h-8 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Unit Cost (TZS)</label>
                            <Input v-model="receiveForm.unit_cost" type="number" min="0" class="font-mono h-8 text-xs" />
                        </div>
                        <div class="space-y-0.5">
                            <label class="block font-bold text-[11px] text-foreground">Selling Price (TZS)</label>
                            <Input v-model="receiveForm.unit_selling_price" type="number" min="0" class="font-mono h-8 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-0.5">
                        <label class="block font-bold text-[11px] text-foreground">Notes / Reference #</label>
                        <Input v-model="receiveForm.notes" placeholder="e.g. PO-2026-0811 delivery" class="h-8 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2.5 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-7 text-xs" @click="closeReceiveModal" :disabled="isReceiving">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-7 text-xs font-semibold" :disabled="isReceiving || !receiveForm.batch_number">
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
                        <label class="block font-bold text-[11px] text-foreground">Audit Reason *</label>
                        <Input v-model="adjustReason" required placeholder="e.g. Physical inventory count / damaged items" class="h-8 text-xs" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2.5 border-t border-border">
                    <Button variant="outline" size="sm" class="h-7 text-xs" @click="closeAdjustModal" :disabled="isAdjusting">Cancel</Button>
                    <Button variant="default" size="sm" class="h-7 text-xs font-semibold" @click="submitAdjustStock" :disabled="isAdjusting || !adjustReason">
                        <Loader2 v-if="isAdjusting" class="w-3 h-3 animate-spin mr-1" />
                        <span>Confirm Adjustment</span>
                    </Button>
                </div>
            </div>
        </Modal>

    </AfyaShell>
</template>
