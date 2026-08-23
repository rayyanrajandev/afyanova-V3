<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Package,
    Building2,
    ArrowRightLeft,
    ShoppingCart,
    FileCheck2,
    ClipboardCheck,
    AlertTriangle,
    CheckCircle2,
    DollarSign,
    Layers,
    Plus,
    RefreshCw,
    Search,
    ShieldAlert,
    Clock,
    X,
    Calendar,
    ArrowUpRight,
    TrendingUp,
    Send,
    Truck,
    Flame,
    FileText,
    Syringe,
    FlaskConical,
    Wind,
    Utensils,
    Shirt,
    Stethoscope,
    CheckCircle,
    XCircle,
    Camera,
    Barcode
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
import AfyaItemCombobox from '@/Components/Afya/AfyaItemCombobox.vue';
import AfyaCameraScanner from '@/Components/Afya/AfyaCameraScanner.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    selectedLocationId: { type: String, default: null },
    stockBalances: { type: Array, default: () => [] },
    itemMasters: { type: Array, default: () => [] },
    requisitions: { type: Array, default: () => [] },
    transfers: { type: Array, default: () => [] },
    purchaseOrders: { type: Array, default: () => [] },
    goodsReceiptNotes: { type: Array, default: () => [] },
    stocktakeSessions: { type: Array, default: () => [] },
    ddaLogs: { type: Array, default: () => [] },
    gasCylinders: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    medications: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    unitsOfMeasure: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    predictiveProcurement: { type: Object, default: () => ({ recommendations: [], items_needing_reorder_count: 0 }) },
    metrics: { type: Object, default: () => ({}) },
});

const { preferences, openContext } = useWorkspacePreferences();

// View State: 'stock' | 'catalog' | 'requisitions' | 'transfers' | 'procurement' | 'predictive' | 'grn' | 'dda' | 'gas' | 'stocktake'
const activeSection = ref('stock');
const selectedLocation = ref(props.selectedLocationId);
const selectedItem = ref(null);
const searchQuery = ref('');
const selectedCategory = ref('ALL');

const isGeneratingPredictive = ref(false);
const triggerPredictiveReorder = () => {
    isGeneratingPredictive.value = true;
    router.post(route('inventory.predictive-reorder'), {}, {
        onFinish: () => {
            isGeneratingPredictive.value = false;
        }
    });
};

// Modals
const showTransferModal = ref(false);
const showRequisitionModal = ref(false);
const showNewItemModal = ref(false);
const showPoModal = ref(false);
const showGrnModal = ref(false);
const showConfirmTransferModal = ref(false);
const selectedTransferToConfirm = ref(null);

// Forms
const requisitionForm = useForm({
    facility_id: props.facilities[0]?.id || '',
    department_id: props.departments[0]?.id || '',
    source_location_id: props.locations[0]?.id || '',
    destination_location_id: props.locations[1]?.id || '',
    requisition_type: 'Routine_Weekly',
    items: [{ item_id: '', quantity_requested: 10 }],
    notes: '',
});

const newItemForm = useForm({
    item_code: 'MSD-GEN-' + Math.floor(1000 + Math.random() * 9000),
    name: '',
    generic_name: '',
    category: 'Surgical_Consumable',
    base_uom_id: props.unitsOfMeasure[0]?.id || '',
    purchasing_uom_id: props.unitsOfMeasure[1]?.id || '',
    conversion_ratio: 1,
    reorder_level: 20,
    unit_cost_price: 1500,
    unit_selling_price: 2500,
    is_billable: true,
    is_cold_chain: false,
    is_dda_narcotic: false,
});

const transferForm = useForm({
    source_location_id: props.locations[0]?.id || '',
    destination_location_id: props.locations[1]?.id || '',
    items: [{ medication_id: '', batch_id: '', quantity: 1 }],
    notes: '',
});

const poForm = useForm({
    supplier_id: props.suppliers[0]?.id || '',
    facility_id: props.facilities[0]?.id || '',
    destination_location_id: props.locations[0]?.id || '',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    items: [{ medication_id: '', requested_quantity: 100, unit_cost: 2500 }],
    notes: '',
});

const grnForm = useForm({
    purchase_order_id: '',
    supplier_id: props.suppliers[0]?.id || '',
    facility_id: props.facilities[0]?.id || '',
    location_id: props.locations[0]?.id || '',
    received_date: new Date().toISOString().split('T')[0],
    supplier_invoice_number: '',
    delivery_note_number: '',
    items: [{
        medication_id: '',
        po_item_id: null,
        batch_number: 'BATCH-' + Math.floor(1000 + Math.random() * 9000),
        expiry_date: '',
        received_quantity: 100,
        rejected_quantity: 0,
        unit_purchase_cost: 2500,
        unit_selling_price: 3500,
    }],
    notes: '',
});

const confirmTransferForm = useForm({
    notes: '',
});

// Header-Level Material Scope Filters for Fast Modal Operations
const requisitionScope = ref('ALL');
const transferScope = ref('ALL');
const poScope = ref('ALL');
const grnScope = ref('ALL');

const grnSkuScanInput = ref('');
const reqSkuScanInput = ref('');

const scanGrnSku = async () => {
    if (!grnSkuScanInput.value.trim()) return;
    try {
        const res = await fetch(`/inventory/catalog/search?sku=${encodeURIComponent(grnSkuScanInput.value.trim())}`);
        const json = await res.json();
        if (json.data && json.data.length > 0) {
            const item = json.data[0];
            grnForm.items.push({
                medication_id: item.medication_id || item.id,
                po_item_id: null,
                batch_number: 'BATCH-' + Math.floor(1000 + Math.random() * 9000),
                expiry_date: new Date(Date.now() + 365*24*60*60*1000).toISOString().split('T')[0],
                received_quantity: 50,
                rejected_quantity: 0,
                unit_purchase_cost: Number(item.unit_cost_price || 0),
                unit_selling_price: Number(item.unit_selling_price || 0),
            });
            grnSkuScanInput.value = '';
        }
    } catch (e) {
        console.error('SKU Scan error:', e);
    }
};

const scanReqSku = async () => {
    if (!reqSkuScanInput.value.trim()) return;
    try {
        const res = await fetch(`/inventory/catalog/search?sku=${encodeURIComponent(reqSkuScanInput.value.trim())}`);
        const json = await res.json();
        if (json.data && json.data.length > 0) {
            const item = json.data[0];
            requisitionForm.items.push({
                item_id: item.id,
                quantity_requested: 10,
            });
            reqSkuScanInput.value = '';
        }
    } catch (e) {
        console.error('SKU Scan error:', e);
    }
};

// Mobile / Laptop Camera Barcode Scanner State
const showCameraScanner = ref(false);
const cameraScannerTarget = ref('grn'); // 'grn' or 'req'

const openCameraScanner = (target = 'grn') => {
    cameraScannerTarget.value = target;
    showCameraScanner.value = true;
};

const handleCameraScan = (code) => {
    if (cameraScannerTarget.value === 'grn') {
        grnSkuScanInput.value = code;
        scanGrnSku();
    } else if (cameraScannerTarget.value === 'req') {
        reqSkuScanInput.value = code;
        scanReqSku();
    }
};

// Filtered Item Masters
const filteredCatalog = computed(() => {
    let list = props.itemMasters;
    if (selectedCategory.value !== 'ALL') {
        list = list.filter(i => i.category === selectedCategory.value);
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(i => 
            i.name?.toLowerCase().includes(q) ||
            i.generic_name?.toLowerCase().includes(q) ||
            i.item_code?.toLowerCase().includes(q)
        );
    }
    return list;
});

// Filtered stock balances
const filterLowStockOnly = ref(false);

const filteredStockBalances = computed(() => {
    let list = props.stockBalances;
    if (selectedLocation.value) {
        list = list.filter(b => b.location_id === selectedLocation.value);
    }
    if (filterLowStockOnly.value) {
        list = list.filter(b => b.quantity_on_hand <= b.reorder_level);
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(b => 
            b.medication?.generic_name?.toLowerCase().includes(q) ||
            b.medication?.brand_name?.toLowerCase().includes(q) ||
            b.batch?.batch_number?.toLowerCase().includes(q)
        );
    }
    return list;
});

const selectLocation = (locId) => {
    selectedLocation.value = locId;
    activeSection.value = 'stock';
    router.get(route('inventory.workspace'), { location_id: locId }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const selectStockItem = (item) => {
    selectedItem.value = item;
    openContext();
};

const submitRequisition = () => {
    requisitionForm.post(route('inventory.requisitions.store'), {
        onSuccess: () => {
            showRequisitionModal.value = false;
            requisitionForm.reset();
        }
    });
};

const approveRequisition = (reqId) => {
    router.post(route('inventory.requisitions.approve', reqId));
};

const issueRequisition = (reqId) => {
    router.post(route('inventory.requisitions.issue', reqId));
};

const confirmRequisition = (reqId) => {
    router.post(route('inventory.requisitions.confirm', reqId));
};

const onRequisitionItemSelect = (itemIdx, selectedItem) => {
    requisitionForm.items[itemIdx].item_id = selectedItem.id;
};

const onTransferItemSelect = (itemIdx, selectedItem) => {
    transferForm.items[itemIdx].medication_id = selectedItem.medication_id || selectedItem.id;
};

const onPoItemSelect = (itemIdx, selectedItem) => {
    const poItem = poForm.items[itemIdx];
    poItem.medication_id = selectedItem.medication_id || selectedItem.id;
    poItem.unit_cost = Number(selectedItem.unit_cost_price || 0);
};

const onGrnItemSelect = (itemIdx, selectedItem) => {
    const grnItem = grnForm.items[itemIdx];
    grnItem.medication_id = selectedItem.medication_id || selectedItem.id;
    grnItem.unit_purchase_cost = Number(selectedItem.unit_cost_price || 0);
    grnItem.unit_selling_price = Number(selectedItem.unit_selling_price || 0);
};

const submitNewItem = () => {
    newItemForm.post(route('inventory.items.store'), {
        onSuccess: () => {
            showNewItemModal.value = false;
            newItemForm.reset();
        }
    });
};

const openConfirmTransfer = (transfer) => {
    selectedTransferToConfirm.value = transfer;
    showConfirmTransferModal.value = true;
};

const submitConfirmTransfer = () => {
    if (!selectedTransferToConfirm.value) return;
    confirmTransferForm.post(route('inventory.transfers.confirm', selectedTransferToConfirm.value.id), {
        onSuccess: () => {
            showConfirmTransferModal.value = false;
            selectedTransferToConfirm.value = null;
        }
    });
};

const submitTransfer = () => {
    transferForm.post(route('inventory.transfers.store'), {
        onSuccess: () => {
            showTransferModal.value = false;
            transferForm.reset();
        }
    });
};

const submitPo = () => {
    poForm.post(route('inventory.purchase-orders.store'), {
        onSuccess: () => {
            showPoModal.value = false;
            poForm.reset();
        }
    });
};

const submitGrn = () => {
    grnForm.post(route('inventory.goods-receipt.store'), {
        onSuccess: () => {
            showGrnModal.value = false;
            grnForm.reset();
        }
    });
};

const approvePo = (poId) => {
    router.post(route('inventory.purchase-orders.approve', poId));
};

const addRequisitionItem = () => {
    requisitionForm.items.push({ item_id: '', quantity_requested: 10 });
};

const addTransferItem = () => {
    transferForm.items.push({ medication_id: '', batch_id: '', quantity: 1 });
};

const addPoItem = () => {
    poForm.items.push({ medication_id: '', requested_quantity: 50, unit_cost: 1000 });
};

const addGrnItem = () => {
    grnForm.items.push({
        medication_id: '',
        po_item_id: null,
        batch_number: 'BATCH-' + Math.floor(1000 + Math.random() * 9000),
        expiry_date: '',
        received_quantity: 50,
        rejected_quantity: 0,
        unit_purchase_cost: 1000,
        unit_selling_price: 1500,
    });
};

const formatCurrency = (val) => {
    return 'TZS ' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const categoryTabs = [
    { key: 'ALL', label: 'All Items', icon: Package },
    { key: 'Pharmaceutical', label: 'Medicines & IVs', icon: Stethoscope },
    { key: 'Surgical_Consumable', label: 'Surgical & Consumables', icon: Syringe },
    { key: 'Lab_Reagent', label: 'Lab Reagents & Kits', icon: FlaskConical },
    { key: 'IPC_Chemical', label: 'IPC & Disinfectants', icon: Flame },
    { key: 'Stationery_MTUHA', label: 'MTUHA & Stationery', icon: FileText },
    { key: 'Medical_Gas', label: 'Medical Gases', icon: Wind },
    { key: 'Linen_Apparel', label: 'Hospital Linen', icon: Shirt },
    { key: 'Nutrition_Food', label: 'Kitchen Rations', icon: Utensils },
];

const breadcrumbLabel = computed(() => {
    switch (activeSection.value) {
        case 'stock': return 'Multi-Location Stock Balances';
        case 'catalog': return 'Universal Hospital Item Catalog';
        case 'requisitions': return 'Department Store Requisitions (Indents)';
        case 'transfers': return 'Inter-Store Transfers Handshake';
        case 'procurement': return 'Purchase Orders & Suppliers';
        case 'predictive': return 'Predictive Auto-Reorder & AI Replenishment (ADC & Lead Time)';
        case 'grn': return 'Goods Received Notes (GRN)';
        case 'dda': return 'Dangerous Drugs Register (DDA Narcotics)';
        case 'gas': return 'Medical Oxygen Cylinder Fleet';
        case 'stocktake': return 'Stocktaking Audits & Physical Counts';
        default: return 'Inventory Management';
    }
});
</script>

<template>
    <AfyaShell active-module="inventory">
        <Head title="Universal Hospital Materials & Inventory" />

        <AfyaWorkspace :show-context="true">
            <!-- 1. LEFT SIDEBAR -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Hospital Materials"
                    :icon="Package"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                        Inventory & Store Ops
                    </div>

                    <AfyaSidebarItem
                        :icon="Package"
                        label="Stock Balances"
                        :active="activeSection === 'stock'"
                        :collapsed="state === 'collapsed'"
                        :badge="stockBalances.length"
                        @click="activeSection = 'stock'"
                    />
                    <AfyaSidebarItem
                        :icon="Layers"
                        label="Universal Item Catalog"
                        :active="activeSection === 'catalog'"
                        :collapsed="state === 'collapsed'"
                        :badge="itemMasters.length"
                        @click="activeSection = 'catalog'"
                    />
                    <AfyaSidebarItem
                        :icon="FileText"
                        label="Department Indents"
                        :active="activeSection === 'requisitions'"
                        :collapsed="state === 'collapsed'"
                        :badge="metrics.pending_requisitions > 0 ? `${metrics.pending_requisitions} Pending` : null"
                        @click="activeSection = 'requisitions'"
                    />
                    <AfyaSidebarItem
                        :icon="ArrowRightLeft"
                        label="Transfers Handshake"
                        :active="activeSection === 'transfers'"
                        :collapsed="state === 'collapsed'"
                        :badge="metrics.in_transit_transfers > 0 ? `${metrics.in_transit_transfers} In-Transit` : null"
                        @click="activeSection = 'transfers'"
                    />
                    <AfyaSidebarItem
                        :icon="ShoppingCart"
                        label="Purchase Orders"
                        :active="activeSection === 'procurement'"
                        :collapsed="state === 'collapsed'"
                        :badge="purchaseOrders.length"
                        @click="activeSection = 'procurement'"
                    />
                    <AfyaSidebarItem
                        :icon="TrendingUp"
                        label="Predictive Reorders"
                        :active="activeSection === 'predictive'"
                        :collapsed="state === 'collapsed'"
                        :badge="predictiveProcurement?.items_needing_reorder_count > 0 ? `${predictiveProcurement.items_needing_reorder_count} Due` : null"
                        @click="activeSection = 'predictive'"
                    />
                    <AfyaSidebarItem
                        :icon="FileCheck2"
                        label="Goods Receiving (GRN)"
                        :active="activeSection === 'grn'"
                        :collapsed="state === 'collapsed'"
                        :badge="goodsReceiptNotes.length"
                        @click="activeSection = 'grn'"
                    />
                    <AfyaSidebarItem
                        :icon="ShieldAlert"
                        label="DDA Narcotics Register"
                        :active="activeSection === 'dda'"
                        :collapsed="state === 'collapsed'"
                        :badge="ddaLogs.length"
                        @click="activeSection = 'dda'"
                    />
                    <AfyaSidebarItem
                        :icon="Wind"
                        label="Oxygen Cylinder Fleet"
                        :active="activeSection === 'gas'"
                        :collapsed="state === 'collapsed'"
                        :badge="gasCylinders.length"
                        @click="activeSection = 'gas'"
                    />
                    <AfyaSidebarItem
                        :icon="ClipboardCheck"
                        label="Stocktaking Audits"
                        :active="activeSection === 'stocktake'"
                        :collapsed="state === 'collapsed'"
                        :badge="stocktakeSessions.length"
                        @click="activeSection = 'stocktake'"
                    />

                    <!-- Warehouses & Locations -->
                    <div v-if="state !== 'collapsed'" class="px-2 pt-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground border-t border-border/40 mt-2">
                        Physical Locations
                    </div>
                    <div v-if="state !== 'collapsed'" class="space-y-1 px-1">
                        <button
                            v-for="loc in locations"
                            :key="loc.id"
                            @click="selectLocation(loc.id)"
                            :class="selectedLocation === loc.id && activeSection === 'stock' ? 'bg-primary/10 text-primary font-bold border-l-2 border-primary' : 'text-muted-foreground hover:bg-muted'"
                            class="w-full text-left px-2 py-1.5 rounded text-xs truncate flex items-center justify-between transition-colors"
                        >
                            <span class="truncate">{{ loc.name }}</span>
                            <span class="text-[9px] font-mono text-muted-foreground">{{ loc.type }}</span>
                        </button>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER PANEL -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Materials & Supply', href: route('inventory.workspace') },
                        { label: breadcrumbLabel, active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button 
                                v-if="activeSection === 'catalog'"
                                variant="default" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 shadow-2xs"
                                @click="showNewItemModal = true"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Hospital Item</span>
                            </Button>

                            <Button 
                                v-if="activeSection === 'requisitions'"
                                variant="default" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 shadow-2xs"
                                @click="showRequisitionModal = true"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>New Department Indent</span>
                            </Button>

                            <Button 
                                v-if="activeSection === 'stock' || activeSection === 'transfers'"
                                variant="default" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 shadow-2xs"
                                @click="showTransferModal = true"
                            >
                                <ArrowRightLeft class="w-3.5 h-3.5" />
                                <span>New Stock Transfer</span>
                            </Button>

                            <Button 
                                v-if="activeSection === 'procurement'"
                                variant="default" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 shadow-2xs"
                                @click="showPoModal = true"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Create Purchase Order</span>
                            </Button>

                            <Button 
                                v-if="activeSection === 'predictive'"
                                variant="default" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1.5 bg-primary/90 hover:bg-primary shadow-2xs"
                                :disabled="isGeneratingPredictive"
                                @click="triggerPredictiveReorder"
                            >
                                <TrendingUp class="w-3.5 h-3.5" />
                                <span>{{ isGeneratingPredictive ? 'Generating Orders...' : 'Auto-Generate Replenishment POs' }}</span>
                            </Button>

                            <Button 
                                v-if="activeSection === 'grn'"
                                variant="default" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 shadow-2xs"
                                @click="showGrnModal = true"
                            >
                                <FileCheck2 class="w-3.5 h-3.5" />
                                <span>Receive Goods (GRN)</span>
                            </Button>

                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 bg-card shadow-2xs"
                                @click="router.reload()"
                            >
                                <RefreshCw class="w-3 h-3 text-primary" />
                                <span>Refresh</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        
                        <!-- TOP METRICS STRIP (Clickable Workstation Shortcuts) -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            <button
                                type="button"
                                @click="activeSection = 'stock'; filterLowStockOnly = false;"
                                class="text-left p-3 rounded-lg border transition-all cursor-pointer shadow-2xs hover:border-emerald-500/60 hover:shadow-xs group"
                                :class="activeSection === 'stock' && !filterLowStockOnly ? 'bg-emerald-500/5 border-emerald-500/80 ring-1 ring-emerald-500/20' : 'bg-card border-border/60'"
                            >
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider group-hover:text-emerald-600">
                                    <span>Stock Valuation</span>
                                    <DollarSign class="w-3.5 h-3.5 text-emerald-600 transition-transform group-hover:scale-110" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ formatCurrency(metrics.total_valuation_tzs) }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5 flex items-center justify-between">
                                    <span>Across {{ metrics.total_locations }} sites</span>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground/50 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                            </button>

                            <button
                                type="button"
                                @click="activeSection = 'requisitions'"
                                class="text-left p-3 rounded-lg border transition-all cursor-pointer shadow-2xs hover:border-indigo-500/60 hover:shadow-xs group"
                                :class="activeSection === 'requisitions' ? 'bg-indigo-500/5 border-indigo-500/80 ring-1 ring-indigo-500/20' : 'bg-card border-border/60'"
                            >
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider group-hover:text-indigo-600">
                                    <span>Pending Indents</span>
                                    <FileText class="w-3.5 h-3.5 text-indigo-600 transition-transform group-hover:scale-110" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">
                                    {{ metrics.pending_requisitions }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5 flex items-center justify-between">
                                    <span>Ward store requests</span>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground/50 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                            </button>

                            <button
                                type="button"
                                @click="activeSection = 'transfers'"
                                class="text-left p-3 rounded-lg border transition-all cursor-pointer shadow-2xs hover:border-sky-500/60 hover:shadow-xs group"
                                :class="activeSection === 'transfers' ? 'bg-sky-500/5 border-sky-500/80 ring-1 ring-sky-500/20' : 'bg-card border-border/60'"
                            >
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider group-hover:text-sky-600">
                                    <span>In-Transit Transfers</span>
                                    <Truck class="w-3.5 h-3.5 text-sky-600 transition-transform group-hover:scale-110" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ metrics.in_transit_transfers }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5 flex items-center justify-between">
                                    <span>Awaiting confirmation</span>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground/50 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                            </button>

                            <button
                                type="button"
                                @click="activeSection = 'stock'; filterLowStockOnly = true;"
                                class="text-left p-3 rounded-lg border transition-all cursor-pointer shadow-2xs hover:border-amber-500/60 hover:shadow-xs group"
                                :class="activeSection === 'stock' && filterLowStockOnly ? 'bg-amber-500/10 border-amber-500/80 ring-1 ring-amber-500/30' : 'bg-card border-border/60'"
                            >
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider group-hover:text-amber-600">
                                    <span>Reorder Safety Alarms</span>
                                    <AlertTriangle class="w-3.5 h-3.5 text-amber-600 transition-transform group-hover:scale-110" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-amber-700 dark:text-amber-400 font-mono">
                                    {{ metrics.reorder_alerts_count }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5 flex items-center justify-between">
                                    <span>Below min threshold</span>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground/50 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                            </button>

                            <button
                                type="button"
                                @click="activeSection = 'catalog'"
                                class="text-left p-3 rounded-lg border transition-all cursor-pointer shadow-2xs hover:border-primary/60 hover:shadow-xs group"
                                :class="activeSection === 'catalog' ? 'bg-primary/5 border-primary/80 ring-1 ring-primary/20' : 'bg-card border-border/60'"
                            >
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider group-hover:text-primary">
                                    <span>Hospital Item Catalog</span>
                                    <Layers class="w-3.5 h-3.5 text-primary transition-transform group-hover:scale-110" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ metrics.total_items_catalog }} items
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5 flex items-center justify-between">
                                    <span>9 standard categories</span>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground/50 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                            </button>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 1: UNIVERSAL ITEM CATALOG                              -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'catalog'" class="space-y-3">
                            <!-- Category Filter Tabs -->
                            <div class="flex items-center gap-1 overflow-x-auto pb-1 border-b border-border/40 text-xs">
                                <button
                                    v-for="cat in categoryTabs"
                                    :key="cat.key"
                                    @click="selectedCategory = cat.key"
                                    :class="selectedCategory === cat.key ? 'bg-primary text-primary-foreground font-bold shadow-2xs' : 'bg-card text-muted-foreground hover:text-foreground border border-border/60'"
                                    class="px-2.5 py-1 rounded-md text-xs whitespace-nowrap flex items-center gap-1.5 transition-all"
                                >
                                    <component :is="cat.icon" class="w-3.5 h-3.5" />
                                    <span>{{ cat.label }}</span>
                                </button>
                            </div>

                            <div class="flex items-center justify-between">
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Search catalog by name, MSD code, or category..."
                                    class="h-8 text-xs bg-card max-w-sm"
                                />
                            </div>

                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Item Code</TableHead>
                                            <TableHead class="py-2 px-3">Item Name</TableHead>
                                            <TableHead class="py-2 px-3">Category</TableHead>
                                            <TableHead class="py-2 px-3">Packaging / UOM</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Unit Cost</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Selling Price</TableHead>
                                            <TableHead class="py-2 px-3 text-center">Type</TableHead>
                                            <TableHead class="py-2 px-3">Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="item in filteredCatalog" 
                                            :key="item.id"
                                            class="hover:bg-muted/20 border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-mono font-bold text-primary">
                                                {{ item.item_code }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ item.name }}
                                                <div v-if="item.generic_name" class="text-[10px] text-muted-foreground font-normal">{{ item.generic_name }}</div>
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-muted-foreground font-semibold">
                                                {{ item.category.replace('_', ' ') }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono text-xs">
                                                <span class="text-foreground font-bold">{{ item.base_uom?.name || 'Piece' }}</span>
                                                <span v-if="item.conversion_ratio > 1" class="text-[10px] text-muted-foreground ml-1">(1 {{ item.purchasing_uom?.name }} = {{ item.conversion_ratio }})</span>
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono text-muted-foreground">
                                                {{ formatCurrency(item.unit_cost_price) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono font-bold text-foreground">
                                                {{ formatCurrency(item.unit_selling_price) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-center">
                                                <AfyaStatusBadge 
                                                    :status="item.is_billable ? 'active' : 'inactive'" 
                                                    :label="item.is_billable ? 'Billable' : 'Overhead'"
                                                />
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge status="active" label="Active" />
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 2: DEPARTMENT REQUISITIONS (STORE INDENTS)             -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'requisitions'" class="space-y-3">
                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Indent #</TableHead>
                                            <TableHead class="py-2 px-3">Department</TableHead>
                                            <TableHead class="py-2 px-3">Ward Cabinet</TableHead>
                                            <TableHead class="py-2 px-3">Items Count</TableHead>
                                            <TableHead class="py-2 px-3">Requested At</TableHead>
                                            <TableHead class="py-2 px-3">Status</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="req in requisitions" 
                                            :key="req.id"
                                            class="hover:bg-muted/20 border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-mono font-bold text-primary">
                                                {{ req.requisition_number }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ req.department?.name || 'General Ward' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-semibold text-foreground">
                                                {{ req.destination_location?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono">
                                                {{ req.items?.length }} item(s)
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-muted-foreground">
                                                {{ req.submitted_at ? String(req.submitted_at).substring(0, 16) : 'Draft' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge 
                                                    :status="req.status === 'Received_Confirmed' ? 'active' : (req.status === 'Submitted' ? 'warning' : 'pending')"
                                                    :label="req.status"
                                                />
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right space-x-1">
                                                <Button 
                                                    v-if="req.status === 'Submitted'"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 text-[10px] font-bold border-indigo-500/40 text-indigo-600 hover:bg-indigo-50"
                                                    @click="approveRequisition(req.id)"
                                                >
                                                    Approve Indent
                                                </Button>
                                                <Button 
                                                    v-if="req.status === 'Approved'"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 text-[10px] font-bold"
                                                    @click="issueRequisition(req.id)"
                                                >
                                                    Store Dispatch
                                                </Button>
                                                <Button 
                                                    v-if="req.status === 'Dispatched_In_Transit'"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 text-[10px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white"
                                                    @click="confirmRequisition(req.id)"
                                                >
                                                    Confirm Receipt
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 3: MULTI-LOCATION STOCK BALANCES                       -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'stock'" class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Search by drug name or batch..."
                                    class="h-8 text-xs bg-card max-w-sm"
                                />

                                <div v-if="filterLowStockOnly" class="flex items-center gap-2 bg-amber-500/10 border border-amber-500/30 px-2.5 py-1 rounded-md text-xs text-amber-700 dark:text-amber-300 font-bold">
                                    <AlertTriangle class="w-3.5 h-3.5" />
                                    <span>Showing Reorder Safety Alarms Only</span>
                                    <button 
                                        type="button" 
                                        @click="filterLowStockOnly = false" 
                                        class="ml-1 text-[10px] text-amber-900 dark:text-amber-100 hover:underline flex items-center gap-0.5 bg-amber-500/20 px-1.5 py-0.5 rounded"
                                    >
                                        <X class="w-3 h-3" />
                                        <span>Show All</span>
                                    </button>
                                </div>
                            </div>

                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Location</TableHead>
                                            <TableHead class="py-2 px-3">Item Name & Strength</TableHead>
                                            <TableHead class="py-2 px-3">Batch Number</TableHead>
                                            <TableHead class="py-2 px-3">Expiry Date</TableHead>
                                            <TableHead class="py-2 px-3 text-right">On Hand</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Unit Cost</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Total Value</TableHead>
                                            <TableHead class="py-2 px-3 text-center">Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="b in filteredStockBalances" 
                                            :key="b.id"
                                            @click="selectStockItem(b)"
                                            class="hover:bg-muted/20 cursor-pointer border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-semibold text-foreground">
                                                {{ b.location?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ b.medication?.generic_name }}
                                                <div class="text-[10px] text-muted-foreground font-normal">{{ b.medication?.brand_name }} · {{ b.medication?.strength }}</div>
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono text-primary font-semibold">
                                                {{ b.batch?.batch_number || 'Unbatched' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono text-xs">
                                                {{ b.batch?.expiry_date ? String(b.batch.expiry_date).substring(0, 10) : 'N/A' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono font-extrabold text-foreground">
                                                {{ b.quantity_on_hand }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono text-muted-foreground">
                                                {{ formatCurrency(b.batch?.unit_cost) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono font-bold text-foreground">
                                                {{ formatCurrency(b.quantity_on_hand * (b.batch?.unit_cost || 0)) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-center">
                                                <AfyaStatusBadge 
                                                    :status="b.quantity_on_hand <= b.reorder_level ? 'warning' : 'active'" 
                                                    :label="b.quantity_on_hand <= b.reorder_level ? 'Low Stock' : 'Optimal'"
                                                />
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 4: TRANSFERS HANDSHAKE                                 -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'transfers'" class="space-y-3">
                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Transfer #</TableHead>
                                            <TableHead class="py-2 px-3">From Location</TableHead>
                                            <TableHead class="py-2 px-3">To Location</TableHead>
                                            <TableHead class="py-2 px-3">Items Count</TableHead>
                                            <TableHead class="py-2 px-3">Dispatched At</TableHead>
                                            <TableHead class="py-2 px-3">Status</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Handshake Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="trf in transfers" 
                                            :key="trf.id"
                                            class="hover:bg-muted/20 border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-mono font-bold text-primary">
                                                {{ trf.transfer_number }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ trf.source_location?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ trf.destination_location?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono">
                                                {{ trf.items?.length }} item(s)
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-muted-foreground">
                                                {{ trf.dispatched_at ? String(trf.dispatched_at).substring(0, 16) : 'N/A' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge 
                                                    :status="trf.status === 'Received_Confirmed' ? 'active' : (trf.status === 'Dispatched_In_Transit' ? 'warning' : 'pending')"
                                                    :label="trf.status.replace(/_/g, ' ')"
                                                />
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right">
                                                <Button 
                                                    v-if="trf.status === 'Dispatched_In_Transit'"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 text-[10px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white"
                                                    @click="openConfirmTransfer(trf)"
                                                >
                                                    Confirm Intake
                                                </Button>
                                                <span v-else class="text-[10px] text-muted-foreground font-semibold flex items-center justify-end gap-1">
                                                    <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                                                    <span>Completed</span>
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 5: PURCHASE ORDERS & PROCUREMENT                       -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'procurement'" class="space-y-3">
                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">PO Number</TableHead>
                                            <TableHead class="py-2 px-3">Supplier</TableHead>
                                            <TableHead class="py-2 px-3">Destination Store</TableHead>
                                            <TableHead class="py-2 px-3">Order Date</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Total Amount</TableHead>
                                            <TableHead class="py-2 px-3">Status</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="po in purchaseOrders" 
                                            :key="po.id"
                                            class="hover:bg-muted/20 border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-mono font-bold text-primary">
                                                {{ po.po_number }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ po.supplier?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-foreground">
                                                {{ po.destination_location?.name || 'Central Warehouse' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-muted-foreground">
                                                {{ String(po.order_date).substring(0, 10) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono font-bold text-foreground">
                                                {{ formatCurrency(po.total_amount) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge 
                                                    :status="po.status === 'Approved' ? 'active' : (po.status === 'Draft' ? 'warning' : 'pending')"
                                                    :label="po.status"
                                                />
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right">
                                                <Button 
                                                    v-if="po.status === 'Draft' || po.status === 'Submitted'"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 text-[10px] font-bold border-indigo-500/40 text-indigo-600 hover:bg-indigo-50"
                                                    @click="approvePo(po.id)"
                                                >
                                                    Approve PO
                                                </Button>
                                                <span v-else class="text-[10px] text-muted-foreground font-semibold">
                                                    Authorized
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 5B: PREDICTIVE AUTO-REORDER & AI PROCUREMENT          -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'predictive'" class="space-y-4">
                            <!-- Predictive KPI Summary Strip -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-1">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Monitored Hospital SKUs</div>
                                    <div class="text-xl font-bold font-mono text-foreground">{{ predictiveProcurement?.recommendations?.length || 0 }}</div>
                                    <div class="text-[10px] text-muted-foreground">Real-time telemetry from ward & pharmacy ops</div>
                                </div>
                                <div class="p-3 bg-rose-500/5 rounded-lg border border-rose-500/30 shadow-2xs space-y-1">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">Stockout Imminent (&le; 3 Days)</div>
                                    <div class="text-xl font-bold font-mono text-rose-600 dark:text-rose-400">
                                        {{ (predictiveProcurement?.recommendations || []).filter(r => r.is_critical).length }}
                                    </div>
                                    <div class="text-[10px] text-rose-700/80 dark:text-rose-400/80">Requires urgent PO issuance</div>
                                </div>
                                <div class="p-3 bg-amber-500/5 rounded-lg border border-amber-500/30 shadow-2xs space-y-1">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Below Reorder Point (ROP)</div>
                                    <div class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400">
                                        {{ predictiveProcurement?.items_needing_reorder_count || 0 }}
                                    </div>
                                    <div class="text-[10px] text-amber-700/80 dark:text-amber-400/80">Triggering auto-replenishment</div>
                                </div>
                                <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-1">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Est. Replenishment Budget</div>
                                    <div class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                                        {{ formatCurrency((predictiveProcurement?.recommendations || []).filter(r => r.is_below_rop).reduce((acc, r) => acc + (r.suggested_quantity * r.unit_cost), 0)) }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground">For 30-day buffer restock</div>
                                </div>
                            </div>

                            <!-- AI Lead-Time & ADC Automation Banner -->
                            <div class="p-3 bg-primary/5 rounded-lg border border-primary/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-2xs">
                                <div class="flex items-start gap-2.5">
                                    <div class="p-2 bg-primary/10 rounded-md text-primary mt-0.5">
                                        <TrendingUp class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-foreground">Lead-Time Demand & Average Daily Consumption (ADC) Engine</h4>
                                        <p class="text-[11px] text-muted-foreground leading-relaxed">
                                            Calculates run-rates from Inpatient Ward e-MAR dispenses and Department Store Indents. Automatically groups low-stock items by preferred pharmaceutical distributor (Harleys, Zenufa, Shelys, Phillips).
                                        </p>
                                    </div>
                                </div>
                                <Button 
                                    variant="default" 
                                    size="sm" 
                                    class="h-8 text-xs font-semibold gap-1.5 whitespace-nowrap shadow-2xs"
                                    :disabled="isGeneratingPredictive || !predictiveProcurement?.items_needing_reorder_count"
                                    @click="triggerPredictiveReorder"
                                >
                                    <ShoppingCart class="w-3.5 h-3.5" />
                                    <span>{{ isGeneratingPredictive ? 'Drafting Orders...' : 'Generate 1-Click Supplier POs' }}</span>
                                </Button>
                            </div>

                            <!-- Recommendations Table -->
                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">SKU & Item Name</TableHead>
                                            <TableHead class="py-2 px-3">Category</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Current SOH</TableHead>
                                            <TableHead class="py-2 px-3 text-right">30d ADC</TableHead>
                                            <TableHead class="py-2 px-3 text-center">Days Remaining</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Safety / ROP</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Suggested PO Qty</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Est. Cost (TZS)</TableHead>
                                            <TableHead class="py-2 px-3 text-center">Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="rec in predictiveProcurement?.recommendations || []" 
                                            :key="rec.item_id"
                                            class="hover:bg-muted/20 border-b border-border/30"
                                            :class="rec.is_critical ? 'bg-rose-500/5' : (rec.is_below_rop ? 'bg-amber-500/5' : '')"
                                        >
                                            <TableCell class="py-2 px-3">
                                                <div class="font-bold text-foreground">{{ rec.name }}</div>
                                                <div class="font-mono text-[9.5px] text-muted-foreground">{{ rec.item_code }}</div>
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-muted-foreground">
                                                {{ rec.category?.replace('_', ' ') }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono font-bold text-foreground">
                                                {{ rec.current_stock }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono text-xs text-foreground">
                                                {{ rec.adc }}/day
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-center">
                                                <span 
                                                    class="inline-block px-2 py-0.5 rounded text-[10px] font-mono font-bold"
                                                    :class="{
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 animate-pulse': rec.days_remaining <= 3,
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': rec.days_remaining > 3 && rec.days_remaining <= 7,
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': rec.days_remaining > 7
                                                    }"
                                                >
                                                    {{ rec.days_remaining }} days
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono text-xs text-muted-foreground">
                                                {{ rec.safety_stock }} / <strong class="text-foreground">{{ rec.reorder_point }}</strong>
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono font-bold text-primary">
                                                +{{ rec.suggested_quantity }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono font-bold text-foreground">
                                                {{ formatCurrency(rec.suggested_quantity * rec.unit_cost) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-center">
                                                <span 
                                                    v-if="rec.is_critical" 
                                                    class="px-2 py-0.5 bg-rose-600 text-white rounded text-[9px] font-bold uppercase tracking-wider"
                                                >
                                                    Stockout Risk
                                                </span>
                                                <span 
                                                    v-else-if="rec.is_below_rop" 
                                                    class="px-2 py-0.5 bg-amber-500 text-white rounded text-[9px] font-bold uppercase tracking-wider"
                                                >
                                                    Reorder Due
                                                </span>
                                                <span 
                                                    v-else 
                                                    class="px-2 py-0.5 bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 rounded text-[9px] font-bold uppercase tracking-wider"
                                                >
                                                    Optimal
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 6: GOODS RECEIVING NOTES (GRN)                         -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'grn'" class="space-y-3">
                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">GRN #</TableHead>
                                            <TableHead class="py-2 px-3">Supplier</TableHead>
                                            <TableHead class="py-2 px-3">Delivery Note #</TableHead>
                                            <TableHead class="py-2 px-3">Invoice #</TableHead>
                                            <TableHead class="py-2 px-3">Warehouse</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Received Value</TableHead>
                                            <TableHead class="py-2 px-3">Received At</TableHead>
                                            <TableHead class="py-2 px-3">Received By</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="grn in goodsReceiptNotes" 
                                            :key="grn.id"
                                            class="hover:bg-muted/20 border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-mono font-bold text-primary">
                                                {{ grn.grn_number }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ grn.supplier?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono text-xs">
                                                {{ grn.delivery_note_number || 'N/A' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono text-xs">
                                                {{ grn.supplier_invoice_number || 'N/A' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-foreground">
                                                {{ grn.location?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-mono font-bold text-foreground">
                                                {{ formatCurrency(grn.total_received_value) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-muted-foreground">
                                                {{ String(grn.received_date).substring(0, 10) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-foreground font-semibold">
                                                {{ grn.received_by?.first_name }} {{ grn.received_by?.last_name }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 7: DDA NARCOTICS REGISTER                              -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'dda'" class="space-y-3">
                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Date / Time</TableHead>
                                            <TableHead class="py-2 px-3">Controlled Substance</TableHead>
                                            <TableHead class="py-2 px-3">Batch #</TableHead>
                                            <TableHead class="py-2 px-3">Patient</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Dose Given</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Dose Wasted</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Balance</TableHead>
                                            <TableHead class="py-2 px-3">Administering Nurse</TableHead>
                                            <TableHead class="py-2 px-3">Prescribing Doctor</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="log in ddaLogs" 
                                            :key="log.id"
                                            class="hover:bg-muted/20 border-b border-border/30 font-mono text-xs"
                                        >
                                            <TableCell class="py-2 px-3 text-muted-foreground">
                                                {{ String(log.created_at).substring(0, 16) }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-sans font-bold text-foreground">
                                                {{ log.item?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-primary">
                                                {{ log.batch?.batch_number }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-sans">
                                                {{ log.patient ? `${log.patient.first_name} ${log.patient.last_name}` : 'Emergency Ward Use' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-bold text-emerald-600">
                                                {{ log.dose_administered }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right text-rose-600">
                                                {{ log.dose_wasted_discarded }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right font-extrabold text-foreground">
                                                {{ log.balance_after }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-sans text-xs">
                                                {{ log.administering_nurse?.first_name }} {{ log.administering_nurse?.last_name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-sans text-xs">
                                                {{ log.prescriber?.first_name }} {{ log.prescriber?.last_name }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 8: OXYGEN CYLINDER FLEET TRACKING                      -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'gas'" class="space-y-3">
                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Cylinder Serial #</TableHead>
                                            <TableHead class="py-2 px-3">Gas Type</TableHead>
                                            <TableHead class="py-2 px-3">Size</TableHead>
                                            <TableHead class="py-2 px-3">Capacity (Liters)</TableHead>
                                            <TableHead class="py-2 px-3">Current Location</TableHead>
                                            <TableHead class="py-2 px-3">Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="cyl in gasCylinders" 
                                            :key="cyl.id"
                                            class="hover:bg-muted/20 border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-mono font-bold text-primary">
                                                {{ cyl.cylinder_serial_number }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ cyl.gas_type }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono text-xs">
                                                {{ cyl.cylinder_size }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono font-semibold">
                                                {{ cyl.volume_liters }} L
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-foreground">
                                                {{ cyl.current_location?.name || 'Central Gas Bank' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge 
                                                    :status="cyl.status === 'Full_In_Store' ? 'active' : (cyl.status === 'In_Use_Ward' ? 'warning' : 'inactive')"
                                                    :label="cyl.status.replace(/_/g, ' ')"
                                                />
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 9: STOCKTAKING AUDITS & PHYSICAL COUNTS                -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'stocktake'" class="space-y-3">
                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Session Number</TableHead>
                                            <TableHead class="py-2 px-3">Location</TableHead>
                                            <TableHead class="py-2 px-3">Status</TableHead>
                                            <TableHead class="py-2 px-3">Audited Items</TableHead>
                                            <TableHead class="py-2 px-3">Initiated By</TableHead>
                                            <TableHead class="py-2 px-3">Reconciled At</TableHead>
                                            <TableHead class="py-2 px-3">Approver</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="st in stocktakeSessions" 
                                            :key="st.id"
                                            class="hover:bg-muted/20 border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-mono font-bold text-primary">
                                                {{ st.session_number }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ st.location?.name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge 
                                                    :status="st.status === 'Approved_Reconciled' ? 'active' : 'warning'"
                                                    :label="st.status.replace(/_/g, ' ')"
                                                />
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono">
                                                {{ st.items?.length || 0 }} item(s) counted
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-foreground">
                                                {{ st.initiated_by?.first_name }} {{ st.initiated_by?.last_name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-muted-foreground">
                                                {{ st.reconciled_at ? String(st.reconciled_at).substring(0, 16) : 'Pending' }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs text-foreground font-semibold">
                                                {{ st.approved_by?.first_name }} {{ st.approved_by?.last_name }}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow v-if="stocktakeSessions.length === 0">
                                            <TableCell colspan="7" class="py-8 text-center text-muted-foreground text-xs">
                                                No active stocktaking sessions found. All store accounts currently balanced.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT PANEL -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Stock Item Telemetry"
                    :icon="Package"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedItem" class="space-y-3 text-xs">
                        <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-1.5">
                            <div class="font-bold text-foreground text-sm">{{ selectedItem.medication?.generic_name }}</div>
                            <div class="text-[11px] text-muted-foreground">{{ selectedItem.medication?.brand_name }} · {{ selectedItem.medication?.form }}</div>
                            <div class="font-mono text-primary text-[11px]">Location: {{ selectedItem.location?.name }}</div>
                        </div>

                        <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-2">
                            <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Batch Telemetry</div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between py-1 border-b border-border/30">
                                    <span class="text-muted-foreground">Batch Number</span>
                                    <span class="font-mono font-bold text-primary">{{ selectedItem.batch?.batch_number || 'N/A' }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-border/30">
                                    <span class="text-muted-foreground">Expiry Date</span>
                                    <span class="font-mono font-bold text-foreground">{{ selectedItem.batch?.expiry_date ? String(selectedItem.batch.expiry_date).substring(0, 10) : 'N/A' }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-border/30">
                                    <span class="text-muted-foreground">Unit Cost</span>
                                    <span class="font-mono font-bold text-foreground">{{ formatCurrency(selectedItem.batch?.unit_cost) }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-border/30">
                                    <span class="text-muted-foreground">Selling Price</span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(selectedItem.batch?.unit_selling_price || selectedItem.medication?.unit_price) }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <span class="text-muted-foreground">Total Batch Valuation</span>
                                    <span class="font-mono font-extrabold text-foreground">{{ formatCurrency((selectedItem.quantity_on_hand || 0) * (selectedItem.batch?.unit_cost || 0)) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-4 bg-card rounded-lg border border-border/60 shadow-2xs text-center py-12 text-muted-foreground text-xs space-y-2">
                        <Package class="w-8 h-8 text-muted-foreground/40 mx-auto" />
                        <div>Click any stock balance line item to inspect batch and valuation telemetry.</div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- MODAL: NEW STORE INDENT / REQUISITION -->
        <div v-if="showRequisitionModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-border/50 p-4 px-6 shrink-0 bg-card">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <FileText class="w-4 h-4 text-primary" />
                        <span>Create Department Store Indent (Requisition)</span>
                    </h3>
                    <button @click="showRequisitionModal = false" class="text-muted-foreground hover:text-foreground p-1 rounded hover:bg-muted/80">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
                    <div class="grid grid-cols-3 gap-2.5">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Department / Ward</label>
                            <select v-model="requisitionForm.department_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Destination Cabinet</label>
                            <select v-model="requisitionForm.destination_location_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Material Scope</label>
                            <select v-model="requisitionScope" class="w-full h-8 text-xs rounded border border-primary/50 bg-primary/5 px-2 font-bold text-foreground">
                                <option value="ALL">Mixed (All 9 Categories)</option>
                                <option value="Surgical_Consumable">Surgical Consumables Only</option>
                                <option value="Pharmaceutical">Pharmaceuticals Only</option>
                                <option value="Lab_Reagent">Lab Reagents Only</option>
                                <option value="IPC_Chemical">IPC Chemicals Only</option>
                                <option value="Stationery_Registers">Stationery & MTUHA</option>
                                <option value="Medical_Gas">Medical Gases Only</option>
                                <option value="Linen_Bedding">Linen & Apparel Only</option>
                            </select>
                        </div>
                    </div>

                    <!-- Quick SKU / Barcode Scan Row -->
                    <div class="flex items-center gap-2 p-2 bg-muted/20 border border-border/50 rounded-lg">
                        <Barcode class="w-4 h-4 text-muted-foreground shrink-0" />
                        <Input 
                            v-model="reqSkuScanInput" 
                            placeholder="Scan or type MSD Code (e.g. MSD-SURG-001) + press Enter to add..." 
                            class="h-7 text-xs font-mono bg-background flex-1" 
                            @keydown.enter.prevent="scanReqSku"
                        />
                        <Button variant="outline" size="sm" class="h-7 text-[10px] font-bold" @click="scanReqSku">Scan Add</Button>
                        <Button variant="default" size="sm" class="h-7 text-[10px] font-bold bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20" @click="openCameraScanner('req')">
                            <Camera class="w-3.5 h-3.5 mr-1" />
                            Phone Camera
                        </Button>
                    </div>

                    <div class="space-y-2 border-t border-border/40 pt-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[10px] uppercase text-muted-foreground">Requested Items</span>
                            <button @click="addRequisitionItem" class="text-[10px] font-bold text-primary hover:underline">+ Add Line</button>
                        </div>
                        <div class="space-y-2 max-h-[320px] overflow-y-auto pr-1 p-0.5">
                            <div v-for="(item, idx) in requisitionForm.items" :key="idx" class="grid grid-cols-12 gap-2 items-center p-1.5 bg-muted/10 rounded-lg border border-border/40">
                                <div class="col-span-8">
                                    <AfyaItemCombobox
                                        v-model="item.item_id"
                                        :items="itemMasters"
                                        :categoryScope="requisitionScope"
                                        placeholder="Search items by code, name, generic..."
                                        @select="(sel) => onRequisitionItemSelect(idx, sel)"
                                    />
                                </div>
                                <div class="col-span-3">
                                    <Input v-model.number="item.quantity_requested" type="number" min="1" placeholder="Quantity" class="h-8 text-xs font-mono text-right" />
                                </div>
                                <div class="col-span-1 flex justify-end">
                                    <button 
                                        v-if="requisitionForm.items.length > 1" 
                                        type="button" 
                                        @click="requisitionForm.items.splice(idx, 1)" 
                                        class="text-muted-foreground hover:text-rose-600 p-1"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Requisition Justification</label>
                        <Input v-model="requisitionForm.notes" placeholder="e.g. Weekly replenishment for Maternity Labour Ward..." class="h-8 text-xs" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-border/50 p-4 px-6 shrink-0 bg-card">
                    <Button variant="outline" size="sm" @click="showRequisitionModal = false">Cancel</Button>
                    <Button variant="default" size="sm" :disabled="requisitionForm.processing" @click="submitRequisition">
                        Submit Store Indent
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: ADD NEW ITEM TO CATALOG -->
        <div v-if="showNewItemModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-lg p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Plus class="w-4 h-4 text-primary" />
                        <span>Add Item to Universal Hospital Catalog</span>
                    </h3>
                    <button @click="showNewItemModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Item Code / MSD SKU</label>
                            <Input v-model="newItemForm.item_code" class="h-8 text-xs" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Category</label>
                            <select v-model="newItemForm.category" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option value="Surgical_Consumable">Medical & Surgical Consumables</option>
                                <option value="Pharmaceutical">Pharmaceuticals & IV Fluids</option>
                                <option value="Lab_Reagent">Lab Reagents & Diagnostics</option>
                                <option value="IPC_Chemical">Infection Control & Chemicals</option>
                                <option value="Stationery_MTUHA">Stationery & MTUHA Registers</option>
                                <option value="Medical_Gas">Medical Gases</option>
                                <option value="Linen_Apparel">Linen & Apparel</option>
                                <option value="Nutrition_Food">Kitchen Rations</option>
                                <option value="Fixed_Asset">Biomedical Fixed Asset</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Item Name</label>
                        <Input v-model="newItemForm.name" placeholder="e.g. IV Cannula G20 with Injection Port" class="h-8 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Unit Cost (TZS)</label>
                            <Input v-model.number="newItemForm.unit_cost_price" type="number" class="h-8 text-xs" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Selling Price (TZS)</label>
                            <Input v-model.number="newItemForm.unit_selling_price" type="number" class="h-8 text-xs" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-border/50 pt-3">
                    <Button variant="outline" size="sm" @click="showNewItemModal = false">Cancel</Button>
                    <Button variant="default" size="sm" :disabled="newItemForm.processing" @click="submitNewItem">
                        Save to Catalog
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: NEW INTER-STORE TRANSFER -->
        <div v-if="showTransferModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-border/50 p-4 px-6 shrink-0 bg-card">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <ArrowRightLeft class="w-4 h-4 text-primary" />
                        <span>Dispatch Inter-Store Transfer (Step 1 Handshake)</span>
                    </h3>
                    <button @click="showTransferModal = false" class="text-muted-foreground hover:text-foreground p-1 rounded hover:bg-muted/80">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
                    <div class="grid grid-cols-3 gap-2.5">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">From Source Store</label>
                            <select v-model="transferForm.source_location_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">To Destination Store</label>
                            <select v-model="transferForm.destination_location_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Material Scope</label>
                            <select v-model="transferScope" class="w-full h-8 text-xs rounded border border-primary/50 bg-primary/5 px-2 font-bold text-foreground">
                                <option value="ALL">Mixed (All 9 Categories)</option>
                                <option value="Surgical_Consumable">Surgical Consumables Only</option>
                                <option value="Pharmaceutical">Pharmaceuticals Only</option>
                                <option value="Lab_Reagent">Lab Reagents Only</option>
                                <option value="IPC_Chemical">IPC Chemicals Only</option>
                                <option value="Stationery_Registers">Stationery & MTUHA</option>
                                <option value="Medical_Gas">Medical Gases Only</option>
                                <option value="Linen_Bedding">Linen & Apparel Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-border/40 pt-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[10px] uppercase text-muted-foreground">Items to Transfer</span>
                            <button @click="addTransferItem" class="text-[10px] font-bold text-primary hover:underline">+ Add Line</button>
                        </div>
                        <div class="space-y-2 max-h-[320px] overflow-y-auto pr-1 p-0.5">
                            <div v-for="(item, idx) in transferForm.items" :key="idx" class="grid grid-cols-12 gap-2 items-center p-1.5 bg-muted/10 rounded-lg border border-border/40">
                                <div class="col-span-8">
                                    <AfyaItemCombobox
                                        v-model="item.medication_id"
                                        :items="itemMasters"
                                        :categoryScope="transferScope"
                                        placeholder="Search items to dispatch..."
                                        @select="(sel) => onTransferItemSelect(idx, sel)"
                                    />
                                </div>
                                <div class="col-span-3">
                                    <Input v-model.number="item.quantity" type="number" min="1" placeholder="Quantity" class="h-8 text-xs font-mono text-right" />
                                </div>
                                <div class="col-span-1 flex justify-end">
                                    <button 
                                        v-if="transferForm.items.length > 1" 
                                        type="button" 
                                        @click="transferForm.items.splice(idx, 1)" 
                                        class="text-muted-foreground hover:text-rose-600 p-1"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Transfer Notes</label>
                        <Input v-model="transferForm.notes" placeholder="e.g. Replenishment dispatch..." class="h-8 text-xs" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-border/50 p-4 px-6 shrink-0 bg-card">
                    <Button variant="outline" size="sm" @click="showTransferModal = false">Cancel</Button>
                    <Button variant="default" size="sm" :disabled="transferForm.processing" @click="submitTransfer">
                        Dispatch In-Transit
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: CONFIRM INTER-STORE TRANSFER HANDSHAKE -->
        <div v-if="showConfirmTransferModal && selectedTransferToConfirm" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                        <span>Confirm Transfer Intake (Handshake Step 2)</span>
                    </h3>
                    <button @click="showConfirmTransferModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-2.5 text-xs">
                    <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-1">
                        <div class="font-mono font-bold text-primary">{{ selectedTransferToConfirm.transfer_number }}</div>
                        <div class="text-foreground">From: <span class="font-bold">{{ selectedTransferToConfirm.source_location?.name }}</span></div>
                        <div class="text-foreground">To: <span class="font-bold">{{ selectedTransferToConfirm.destination_location?.name }}</span></div>
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Verification / Inspection Note</label>
                        <Input v-model="confirmTransferForm.notes" placeholder="e.g. All seal seals intact, verified count..." class="h-8 text-xs" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-border/50 pt-3">
                    <Button variant="outline" size="sm" @click="showConfirmTransferModal = false">Cancel</Button>
                    <Button variant="default" size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white" :disabled="confirmTransferForm.processing" @click="submitConfirmTransfer">
                        Confirm Physical Receipt
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: CREATE PURCHASE ORDER -->
        <div v-if="showPoModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-border/50 p-4 px-6 shrink-0 bg-card">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <ShoppingCart class="w-4 h-4 text-primary" />
                        <span>Create Supplier Purchase Order</span>
                    </h3>
                    <button @click="showPoModal = false" class="text-muted-foreground hover:text-foreground p-1 rounded hover:bg-muted/80">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
                    <div class="grid grid-cols-3 gap-2.5">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Supplier</label>
                            <select v-model="poForm.supplier_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Receiving Store</label>
                            <select v-model="poForm.destination_location_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Material Scope</label>
                            <select v-model="poScope" class="w-full h-8 text-xs rounded border border-primary/50 bg-primary/5 px-2 font-bold text-foreground">
                                <option value="ALL">Mixed (All 9 Categories)</option>
                                <option value="Surgical_Consumable">Surgical Consumables Only</option>
                                <option value="Pharmaceutical">Pharmaceuticals Only</option>
                                <option value="Lab_Reagent">Lab Reagents Only</option>
                                <option value="IPC_Chemical">IPC Chemicals Only</option>
                                <option value="Stationery_Registers">Stationery & MTUHA</option>
                                <option value="Medical_Gas">Medical Gases Only</option>
                                <option value="Linen_Bedding">Linen & Apparel Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-border/40 pt-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[10px] uppercase text-muted-foreground">Order Items</span>
                            <button @click="addPoItem" class="text-[10px] font-bold text-primary hover:underline">+ Add Line</button>
                        </div>
                        <div class="space-y-2 max-h-[320px] overflow-y-auto pr-1 p-0.5">
                            <div v-for="(item, idx) in poForm.items" :key="idx" class="grid grid-cols-12 gap-2 items-center p-1.5 bg-muted/10 rounded-lg border border-border/40">
                                <div class="col-span-6">
                                    <AfyaItemCombobox
                                        v-model="item.medication_id"
                                        :items="itemMasters"
                                        :categoryScope="poScope"
                                        placeholder="Search items to order..."
                                        @select="(sel) => onPoItemSelect(idx, sel)"
                                    />
                                </div>
                                <div class="col-span-3">
                                    <Input v-model.number="item.requested_quantity" type="number" min="1" placeholder="Quantity" class="h-8 text-xs font-mono text-right" />
                                </div>
                                <div class="col-span-2">
                                    <Input v-model.number="item.unit_cost" type="number" min="0" placeholder="Unit Cost" class="h-8 text-xs font-mono text-right" />
                                </div>
                                <div class="col-span-1 flex justify-end">
                                    <button 
                                        v-if="poForm.items.length > 1" 
                                        type="button" 
                                        @click="poForm.items.splice(idx, 1)" 
                                        class="text-muted-foreground hover:text-rose-600 p-1"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-border/50 p-4 px-6 shrink-0 bg-card">
                    <Button variant="outline" size="sm" @click="showPoModal = false">Cancel</Button>
                    <Button variant="default" size="sm" :disabled="poForm.processing" @click="submitPo">
                        Submit Purchase Order
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: GOODS RECEIVING NOTE (GRN) -->
        <div v-if="showGrnModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-2xl w-[95vw] max-w-7xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-border/50 p-4 px-6 shrink-0 bg-card">
                    <h3 class="font-bold text-foreground text-base flex items-center gap-2">
                        <FileCheck2 class="w-5 h-5 text-primary" />
                        <span>Receive Goods & Post GRN into Ledger</span>
                    </h3>
                    <button @click="showGrnModal = false" class="text-muted-foreground hover:text-foreground p-1 rounded hover:bg-muted/80">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-4 text-xs">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Supplier</label>
                            <select v-model="grnForm.supplier_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Receiving Warehouse</label>
                            <select v-model="grnForm.location_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Material Scope</label>
                            <select v-model="grnScope" class="w-full h-8 text-xs rounded border border-primary/50 bg-primary/5 px-2 font-bold text-foreground">
                                <option value="ALL">Mixed (All 9 Categories)</option>
                                <option value="Surgical_Consumable">Surgical Consumables Only</option>
                                <option value="Pharmaceutical">Pharmaceuticals Only</option>
                                <option value="Lab_Reagent">Lab Reagents Only</option>
                                <option value="IPC_Chemical">IPC Chemicals Only</option>
                                <option value="Stationery_Registers">Stationery & MTUHA</option>
                                <option value="Medical_Gas">Medical Gases Only</option>
                                <option value="Linen_Bedding">Linen & Apparel Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Delivery Note #</label>
                            <Input v-model="grnForm.delivery_note_number" placeholder="DN-2026-XXXX" class="h-8 text-xs" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Supplier Invoice #</label>
                            <Input v-model="grnForm.supplier_invoice_number" placeholder="INV-MSD-XXXX" class="h-8 text-xs" />
                        </div>
                    </div>

                    <!-- Quick SKU / Barcode Scan Row -->
                    <div class="flex items-center gap-2 p-2.5 bg-muted/20 border border-border/50 rounded-lg">
                        <Barcode class="w-4 h-4 text-muted-foreground shrink-0" />
                        <Input 
                            v-model="grnSkuScanInput" 
                            placeholder="Scan or type MSD Code (e.g. MSD-SURG-001) + press Enter to add..." 
                            class="h-7 text-xs font-mono bg-background flex-1" 
                            @keydown.enter.prevent="scanGrnSku"
                        />
                        <Button variant="outline" size="sm" class="h-7 text-[10px] font-bold" @click="scanGrnSku">Scan Add</Button>
                        <Button variant="default" size="sm" class="h-7 text-[10px] font-bold bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20" @click="openCameraScanner('grn')">
                            <Camera class="w-3.5 h-3.5 mr-1" />
                            Phone Camera
                        </Button>
                    </div>

                    <div class="space-y-2 border-t border-border/40 pt-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[10px] uppercase text-muted-foreground">Received Batches & Items (Single-Line High Density)</span>
                            <button @click="addGrnItem" class="text-[10px] font-bold text-primary hover:underline">+ Add Line</button>
                        </div>

                        <!-- Column Headers -->
                        <div class="grid grid-cols-12 gap-3 text-[10px] font-bold text-muted-foreground uppercase px-2.5">
                            <div class="col-span-5">Hospital Catalog Item (Universal Master)</div>
                            <div class="col-span-2">Batch / Lot #</div>
                            <div class="col-span-2">Expiry Date</div>
                            <div class="col-span-1 text-right">Received Qty</div>
                            <div class="col-span-2 text-right pr-6">Unit Cost (TZS)</div>
                        </div>

                        <!-- Scrollable Items Area (prevents dialog height explosion) -->
                        <div class="space-y-2 max-h-[380px] overflow-y-auto pr-1 p-0.5">
                            <div v-for="(item, idx) in grnForm.items" :key="idx" class="grid grid-cols-12 gap-3 items-center p-2 bg-card rounded-lg border border-border/60 shadow-2xs hover:border-primary/30 transition-colors">
                                <div class="col-span-5">
                                    <AfyaItemCombobox
                                        v-model="item.medication_id"
                                        :items="itemMasters"
                                        :categoryScope="grnScope"
                                        placeholder="Search 5,000+ items by code, name, generic..."
                                        @select="(sel) => onGrnItemSelect(idx, sel)"
                                    />
                                </div>
                                <div class="col-span-2">
                                    <Input v-model="item.batch_number" placeholder="Batch / Lot #" class="h-8 text-xs font-mono" />
                                </div>
                                <div class="col-span-2">
                                    <Input v-model="item.expiry_date" type="date" class="h-8 text-xs font-mono" />
                                </div>
                                <div class="col-span-1">
                                    <Input v-model.number="item.received_quantity" type="number" min="1" placeholder="Qty" class="h-8 text-xs text-right font-mono" />
                                </div>
                                <div class="col-span-2 flex items-center gap-2">
                                    <Input v-model.number="item.unit_purchase_cost" type="number" min="0" placeholder="Cost" class="h-8 text-xs text-right font-mono flex-1" />
                                    <button 
                                        v-if="grnForm.items.length > 1" 
                                        type="button" 
                                        @click="grnForm.items.splice(idx, 1)" 
                                        class="text-muted-foreground hover:text-rose-600 p-1.5 rounded hover:bg-rose-500/10 transition-colors shrink-0"
                                        title="Remove Line"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-border/50 p-4 px-6 shrink-0 bg-card">
                    <Button variant="outline" size="sm" @click="showGrnModal = false">Cancel</Button>
                    <Button variant="default" size="sm" :disabled="grnForm.processing" @click="submitGrn">
                        Post GRN to Ledger
                    </Button>
                </div>
            </div>
        </div>

        <!-- CAMERA SCANNER POPUP -->
        <AfyaCameraScanner
            :isOpen="showCameraScanner"
            :title="cameraScannerTarget === 'grn' ? 'Scan Goods Receiving Barcode' : 'Scan Store Indent Barcode'"
            @close="showCameraScanner = false"
            @scan="handleCameraScan"
        />

    </AfyaShell>
</template>
