<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    Receipt, 
    DollarSign, 
    Smartphone, 
    Building2, 
    Undo2, 
    Scale, 
    CreditCard, 
    Wallet,
    CheckCircle2,
    ShieldCheck,
    Loader2,
    X,
    AlertCircle
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

// UI Primitives & Design Foundation
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
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
import InvoiceChargesDrawer from '@/Components/Billing/InvoiceChargesDrawer.vue';
import CashierShiftModal from '@/Components/Billing/CashierShiftModal.vue';

const props = defineProps({
    invoices: {
        type: Array,
        default: () => [],
    },
    activeShift: {
        type: Object,
        default: null,
    },
    recentShifts: {
        type: Array,
        default: () => [],
    },
    tillTelemetry: {
        type: Object,
        default: () => ({
            cash_in_drawer: 0,
            opening_float: 0,
            lipa_namba_total: 0,
            card_pos_total: 0,
            nhif_total: 0,
            cash_collected: 0,
            total_inflow: 0,
            invoices_settled: 0,
        }),
    },
});

const activeSection = ref('invoices');
const selectedInvoice = ref(props.invoices?.[0] || null);
const showChargesDrawer = ref(false);

// Cashier Shift State
const showShiftModal = ref(false);
const shiftModalMode = ref('open');

const openShiftDialog = () => {
    shiftModalMode.value = 'open';
    showShiftModal.value = true;
};

const closeShiftDialog = () => {
    shiftModalMode.value = 'close';
    showShiftModal.value = true;
};

const openChargesDrawer = (inv) => {
    selectedInvoice.value = inv;
    showChargesDrawer.value = true;
};

// Payment Modal State
const showPaymentModal = ref(false);
const paymentAmount = ref('');
const paymentMethod = ref('Lipa Namba');
const paymentReferenceNumber = ref('');
const isSplitPayment = ref(false);
const splitMethods = ref([
    { method: 'Cash', amount: '', reference_number: '' },
    { method: 'Lipa Namba', amount: '', reference_number: '' },
]);
const isSubmittingPayment = ref(false);

// Refund Modal State
const showRefundModal = ref(false);
const refundInvoiceData = ref(null);
const refundAmount = ref('');
const refundReason = ref('Patient requested refund');
const isSubmittingRefund = ref(false);

const paymentMethods = [
    { 
        id: 'Lipa Namba', 
        name: 'Lipa Namba', 
        subtitle: 'M-Pesa / Tigo / Airtel',
        icon: Smartphone,
        color: 'text-emerald-600 dark:text-emerald-400',
        bg: 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200/70 dark:border-emerald-800/70',
    },
    { 
        id: 'Cash', 
        name: 'Cash (Taslimu)', 
        subtitle: 'Physical Cash Tender',
        icon: Wallet,
        color: 'text-amber-600 dark:text-amber-400',
        bg: 'bg-amber-50 dark:bg-amber-950/50 border-amber-200/70 dark:border-amber-800/70',
    },
    { 
        id: 'Card', 
        name: 'Bank POS Card', 
        subtitle: 'Visa / Mastercard / CRDB',
        icon: CreditCard,
        color: 'text-blue-600 dark:text-blue-400',
        bg: 'bg-blue-50 dark:bg-blue-950/50 border-blue-200/70 dark:border-blue-800/70',
    },
    { 
        id: 'NHIF', 
        name: 'Insurance / NHIF', 
        subtitle: 'Co-Pay / Policy Claim',
        icon: Building2,
        color: 'text-purple-600 dark:text-purple-400',
        bg: 'bg-purple-50 dark:bg-purple-950/50 border-purple-200/70 dark:border-purple-800/70',
    },
];

const { preferences, toggleContext, openContext } = useWorkspacePreferences();

const selectInvoice = (inv) => {
    selectedInvoice.value = inv;
    openContext();
};

const openPaymentModal = (inv) => {
    selectedInvoice.value = inv;
    const balance = Number(inv.total_amount) - Number(inv.paid_amount);
    paymentAmount.value = balance > 0 ? balance : 0;
    paymentReferenceNumber.value = '';
    isSplitPayment.value = false;
    splitMethods.value = [
        { method: 'Cash', amount: '', reference_number: '' },
        { method: 'Lipa Namba', amount: '', reference_number: '' },
    ];
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
};

const addSplitRow = () => {
    splitMethods.value.push({ method: 'Lipa Namba', amount: '', reference_number: '' });
};

const removeSplitRow = (index) => {
    if (splitMethods.value.length > 1) {
        splitMethods.value.splice(index, 1);
    }
};

const submitPayment = () => {
    if (!selectedInvoice.value) return;

    isSubmittingPayment.value = true;

    const payload = isSplitPayment.value
        ? {
            splits: splitMethods.value
                .filter(s => Number(s.amount) > 0)
                .map(s => ({
                    amount: Number(s.amount),
                    payment_method: s.method,
                    reference_number: s.reference_number || null,
                }))
        }
        : {
            amount: Number(paymentAmount.value),
            payment_method: paymentMethod.value,
            reference_number: paymentReferenceNumber.value || null,
        };

    router.post(route('billing.pay', selectedInvoice.value.id), payload, {
        onFinish: () => {
            isSubmittingPayment.value = false;
            closePaymentModal();
        }
    });
};

const openRefundModal = (inv) => {
    refundInvoiceData.value = inv;
    refundAmount.value = inv.paid_amount;
    refundReason.value = 'Marekebisho ya bili';
    showRefundModal.value = true;
};

const closeRefundModal = () => {
    showRefundModal.value = false;
    refundInvoiceData.value = null;
};

const submitRefund = () => {
    if (!refundInvoiceData.value || !refundAmount.value) return;

    isSubmittingRefund.value = true;
    router.post(route('billing.refund', refundInvoiceData.value.id), {
        amount: Number(refundAmount.value),
        reason: refundReason.value,
    }, {
        onFinish: () => {
            isSubmittingRefund.value = false;
            closeRefundModal();
        }
    });
};

// Keyboard Traversal (Gap 2.3)
const handleTableKeydown = (e) => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) || showPaymentModal.value || showRefundModal.value) return;
    if (!props.invoices || props.invoices.length === 0) return;

    const currentIndex = props.invoices.findIndex(inv => inv.id === selectedInvoice.value?.id);

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        const nextIndex = Math.min(currentIndex + 1, props.invoices.length - 1);
        selectInvoice(props.invoices[nextIndex]);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prevIndex = Math.max(currentIndex - 1, 0);
        selectInvoice(props.invoices[prevIndex]);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedInvoice.value && selectedInvoice.value.status !== 'Paid') {
            openPaymentModal(selectedInvoice.value);
        }
    }
};

onMounted(() => window.addEventListener('keydown', handleTableKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleTableKeydown));
</script>

<template>
    <Head title="Billing & POS Desk" />

    <AfyaShell active-module="billing">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            <!-- 1. LEFT SIDEBAR: Billing & POS Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Billing & POS"
                    :icon="Receipt"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Cashier Operations
                    </div>
                    <AfyaSidebarItem
                        label="Patient Invoices"
                        :icon="Receipt"
                        :badge="invoices.length"
                        :active="activeSection === 'invoices'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'invoices'"
                    />
                    <AfyaSidebarItem
                        label="Prepaid Point of Service"
                        :icon="DollarSign"
                        :active="activeSection === 'pos'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'pos'"
                    />
                    <AfyaSidebarItem
                        label="Mobile Money Reconcile"
                        :icon="Smartphone"
                        badge="M-Pesa"
                        :active="activeSection === 'mobile_money'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'mobile_money'"
                    />

                    <div v-if="state !== 'collapsed'" class="pt-3 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border mt-2">
                        Financial Management
                    </div>
                    <AfyaSidebarItem
                        label="NHIF & Claims Desk"
                        :icon="Building2"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'claims'"
                    />
                    <AfyaSidebarItem
                        label="Refunds & Adjustments"
                        :icon="Undo2"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'refunds'"
                    />
                    <AfyaSidebarItem
                        label="General Ledger Preview"
                        :icon="Scale"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'ledger'"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Cashier POS Primary Work Area (Full Width) -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Billing', href: route('billing.desk') },
                        { label: 'Cashier POS Desk', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <!-- Active Shift Indicator & Action -->
                            <div v-if="activeShift" class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Shift: <strong>{{ activeShift.shift_number }}</strong> (Float: TZS {{ Number(activeShift.opening_float).toLocaleString() }})</span>
                                </span>
                                <Button variant="outline" size="sm" class="h-7 px-2.5 text-[11px] gap-1 shadow-2xs" @click="closeShiftDialog">
                                    <Clock class="w-3.5 h-3.5 text-amber-600" />
                                    <span>End Shift & Handover</span>
                                </Button>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    <AlertTriangle class="w-3.5 h-3.5 text-amber-600" />
                                    <span>Till Offline (No Active Shift)</span>
                                </span>
                                <Button variant="default" size="sm" class="h-7 px-2.5 text-[11px] gap-1 shadow-2xs font-semibold" @click="openShiftDialog">
                                    <Wallet class="w-3.5 h-3.5" />
                                    <span>Open Cashier Shift</span>
                                </Button>
                            </div>

                            <!-- Context Inspector Toggle Button (Ctrl+I) -->
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 px-2.5 text-[11px] gap-1 shadow-2xs transition-all"
                                :class="preferences.contextOpen ? 'bg-primary/10 text-primary border-primary/40 font-semibold' : 'text-muted-foreground hover:text-foreground'"
                                @click="toggleContext"
                                title="Toggle Invoice Inspector Panel (Ctrl+I)"
                            >
                                <Receipt class="w-3.5 h-3.5" />
                                <span>{{ preferences.contextOpen ? 'Inspector Active' : 'Show Inspector' }}</span>
                            </Button>

                            <!-- Balanced Ledger Badge -->
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-muted text-muted-foreground border border-border/70">
                                <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
                                <span>Ledger: Balanced</span>
                            </span>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        <!-- 4 Live Till Telemetry Counter Cards (Borderless Surface Elevation) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 w-full">
                            <!-- Card 1: Physical Cash in Drawer -->
                            <div class="p-3 rounded-lg bg-card hover:bg-muted/30 transition-all space-y-1 select-none shadow-2xs group">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Physical Cash in Till</span>
                                    <Wallet class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-foreground">
                                    TZS {{ Number(tillTelemetry.cash_in_drawer || 0).toLocaleString() }}
                                </div>
                                <div class="text-[10px] text-muted-foreground font-mono truncate">
                                    Opening Float: TZS {{ Number(tillTelemetry.opening_float || 0).toLocaleString() }}
                                </div>
                            </div>

                            <!-- Card 2: Lipa Namba Till Total -->
                            <div class="p-3 rounded-lg bg-card hover:bg-muted/30 transition-all space-y-1 select-none shadow-2xs group">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Lipa Namba Till</span>
                                    <Smartphone class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-emerald-700 dark:text-emerald-400">
                                    TZS {{ Number(tillTelemetry.lipa_namba_total || 0).toLocaleString() }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">
                                    M-Pesa / Tigo / Airtel STK
                                </div>
                            </div>

                            <!-- Card 3: Bank POS Card Total -->
                            <div class="p-3 rounded-lg bg-card hover:bg-muted/30 transition-all space-y-1 select-none shadow-2xs group">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Bank POS Card</span>
                                    <CreditCard class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-blue-700 dark:text-blue-400">
                                    TZS {{ Number(tillTelemetry.card_pos_total || 0).toLocaleString() }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">
                                    Visa / Mastercard / CRDB
                                </div>
                            </div>

                            <!-- Card 4: Total Shift Inflow -->
                            <div class="p-3 rounded-lg bg-card hover:bg-muted/30 transition-all space-y-1 select-none shadow-2xs group">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Shift Collections</span>
                                    <Scale class="w-3.5 h-3.5 text-primary flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-foreground">
                                    TZS {{ Number(tillTelemetry.total_inflow || 0).toLocaleString() }}
                                </div>
                                <div class="text-[10px] text-muted-foreground font-mono truncate">
                                    {{ tillTelemetry.invoices_settled || 0 }} Invoices Settled
                                </div>
                            </div>
                        </div>

                        <!-- Invoice List Table (Modern Surface Elevation & Balanced Columns) -->
                        <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="h-8 text-[10px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                        <TableHead class="py-1.5 px-4 w-36">Invoice #</TableHead>
                                        <TableHead class="py-1.5 px-3">Patient Details</TableHead>
                                        <TableHead class="py-1.5 px-3 text-right w-32">Total (TZS)</TableHead>
                                        <TableHead class="py-1.5 px-3 text-right w-28">Paid (TZS)</TableHead>
                                        <TableHead class="py-1.5 px-3 text-right w-32">Balance Due</TableHead>
                                        <TableHead class="py-1.5 px-3 text-center w-28">Status</TableHead>
                                        <TableHead class="py-1.5 px-4 text-right w-48">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="inv in invoices"
                                        :key="inv.id"
                                        :selected="selectedInvoice?.id === inv.id"
                                        class="h-10 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                        @click="selectInvoice(inv)"
                                        @dblclick="openChargesDrawer(inv)"
                                    >
                                        <TableCell class="py-1.5 px-4 font-mono font-bold text-foreground w-36">
                                            <button 
                                                class="hover:underline text-primary text-left"
                                                @click.stop="openChargesDrawer(inv)"
                                                title="Open Itemized Charges Drawer"
                                            >
                                                {{ inv.invoice_number }}
                                            </button>
                                        </TableCell>
                                        <TableCell class="py-1.5 px-3">
                                            <div class="font-bold text-foreground">{{ inv.patient?.first_name }} {{ inv.patient?.last_name }}</div>
                                            <div class="text-[10px] font-mono text-muted-foreground">MRN: {{ inv.patient?.primary_mrn }}</div>
                                        </TableCell>
                                        <TableCell class="py-1.5 px-3 font-mono font-semibold text-right w-32">{{ Number(inv.total_amount).toLocaleString() }}</TableCell>
                                        <TableCell class="py-1.5 px-3 font-mono text-emerald-700 dark:text-emerald-400 font-medium text-right w-28">{{ Number(inv.paid_amount).toLocaleString() }}</TableCell>
                                        <TableCell class="py-1.5 px-3 font-mono font-bold text-right w-32" :class="Number(inv.total_amount) - Number(inv.paid_amount) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'">
                                            {{ (Number(inv.total_amount) - Number(inv.paid_amount)).toLocaleString() }}
                                        </TableCell>
                                        <TableCell class="py-1.5 px-3 text-center w-28">
                                            <AfyaStatusBadge :status="inv.status" dot />
                                        </TableCell>
                                        <TableCell class="py-1.5 px-4 text-right w-48">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10px] gap-1 shadow-2xs"
                                                    @click.stop="openChargesDrawer(inv)"
                                                    title="View Itemized Charges Drawer"
                                                >
                                                    <Receipt class="w-3 h-3 text-primary" />
                                                    <span>Charges ({{ (inv.line_items || inv.lineItems || []).length }})</span>
                                                </Button>
                                                <Button
                                                    v-if="inv.status !== 'Paid'"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2.5 text-[10px] font-semibold shadow-2xs"
                                                    @click.stop="openPaymentModal(inv)"
                                                >
                                                    Pay
                                                </Button>
                                                <Button
                                                    v-else
                                                    variant="subtle"
                                                    size="sm"
                                                    class="h-6 px-2.5 text-[10px] font-semibold"
                                                    @click.stop="openRefundModal(inv)"
                                                >
                                                    Refund
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="invoices.length === 0">
                                        <TableCell colspan="7" class="text-center py-10 text-muted-foreground text-xs">
                                            No active invoices found.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: Invoice & Ledger Context Inspector -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Invoice Ledger"
                    :icon="Receipt"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedInvoice" class="space-y-3 text-xs">
                        <!-- Invoice Header Details & Drawer Trigger -->
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                <span>Invoice Breakdown</span>
                                <span class="font-mono text-foreground">{{ selectedInvoice.invoice_number }}</span>
                            </div>
                            <div class="space-y-1 text-[11px]">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Patient:</span>
                                    <span class="font-bold text-foreground truncate max-w-[140px]">{{ selectedInvoice.patient?.first_name }} {{ selectedInvoice.patient?.last_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total Invoiced:</span>
                                    <span class="font-mono font-bold text-foreground">TZS {{ Number(selectedInvoice.total_amount).toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Balance Due:</span>
                                    <span class="font-mono font-bold text-rose-600 dark:text-rose-400">TZS {{ (Number(selectedInvoice.total_amount) - Number(selectedInvoice.paid_amount)).toLocaleString() }}</span>
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full h-7 text-[11px] font-semibold gap-1.5 shadow-2xs mt-1"
                                @click="openChargesDrawer(selectedInvoice)"
                            >
                                <Receipt class="w-3.5 h-3.5 text-primary" />
                                <span>Open Full Charges Drawer</span>
                            </Button>
                        </div>

                        <!-- Itemized Charges Preview Card -->
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                <span>Charge Items ({{ (selectedInvoice.line_items || selectedInvoice.lineItems || []).length }})</span>
                                <span class="text-primary font-semibold">Itemized</span>
                            </div>
                            <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                <div
                                    v-for="item in (selectedInvoice.line_items || selectedInvoice.lineItems || [])"
                                    :key="item.id"
                                    class="flex items-center justify-between text-[11px] p-1.5 rounded bg-muted/40"
                                >
                                    <div class="min-w-0 pr-2">
                                        <div class="font-medium text-foreground truncate">{{ item.description }}</div>
                                        <div class="text-[9px] text-muted-foreground">{{ item.category }} · Qty {{ item.quantity }}</div>
                                    </div>
                                    <div class="font-mono font-bold text-foreground flex-shrink-0">
                                        {{ Number(item.total_price).toLocaleString() }}
                                    </div>
                                </div>
                                <div v-if="!(selectedInvoice.line_items || selectedInvoice.lineItems || []).length" class="text-center py-2 text-[10px] text-muted-foreground">
                                    No itemized charges attached.
                                </div>
                            </div>
                        </div>

                        <!-- Double Entry Ledger Card -->
                        <div class="p-3 rounded-lg bg-card space-y-1.5 shadow-2xs">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground">
                                <Scale class="w-3.5 h-3.5 text-primary flex-shrink-0" />
                                <span>Double-Entry Accounts</span>
                            </div>
                            <div class="text-[10px] space-y-1 font-mono">
                                <div class="flex justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Debit (Cash/M-Pesa):</span>
                                    <span class="font-bold text-emerald-700 dark:text-emerald-400">TZS {{ Number(selectedInvoice.paid_amount).toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Credit (Revenue):</span>
                                    <span class="font-bold text-foreground">TZS {{ Number(selectedInvoice.paid_amount).toLocaleString() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-10 text-muted-foreground text-xs">
                        Select an invoice to inspect ledger.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- Payment Collection Accessible Modal Dialog -->
        <Modal :show="showPaymentModal" max-width="3xl" @close="closePaymentModal">
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">
                            <DollarSign class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-foreground">Record POS Payment & Settlement</h3>
                            <p class="text-[11px] text-muted-foreground">Post multi-channel settlements directly to General Ledger</p>
                        </div>
                    </div>
                    <button @click="closePaymentModal" class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="selectedInvoice" class="space-y-4 text-xs">
                    <!-- Invoice Demographic & Balance Banner -->
                    <div class="p-3.5 bg-muted/40 rounded-lg border border-border/70 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-muted-foreground">Invoice & Patient</div>
                            <div class="font-mono font-bold text-foreground truncate mt-0.5">{{ selectedInvoice.invoice_number }}</div>
                            <div class="text-[11px] text-muted-foreground truncate">{{ selectedInvoice.patient?.first_name }} {{ selectedInvoice.patient?.last_name }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-muted-foreground">Total Invoiced</div>
                            <div class="font-mono font-extrabold text-foreground text-sm mt-0.5">TZS {{ Number(selectedInvoice.total_amount).toLocaleString() }}</div>
                            <div class="text-[10px] text-emerald-700 dark:text-emerald-400 font-mono">Paid: TZS {{ Number(selectedInvoice.paid_amount).toLocaleString() }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-muted-foreground">Remaining Balance Due</div>
                            <div class="font-mono font-extrabold text-rose-600 dark:text-rose-400 text-base mt-0.5">
                                TZS {{ (Number(selectedInvoice.total_amount) - Number(selectedInvoice.paid_amount)).toLocaleString() }}
                            </div>
                        </div>
                    </div>

                    <!-- Single vs Split Payment Mode Switch -->
                    <div class="flex items-center justify-between p-1 bg-muted/60 rounded-md border border-border/60">
                        <button
                            type="button"
                            @click="isSplitPayment = false"
                            :class="[
                                'flex-1 py-1.5 px-3 rounded text-xs font-semibold transition-all',
                                !isSplitPayment ? 'bg-card text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'
                            ]"
                        >
                            ⚡ Single Method Payment
                        </button>
                        <button
                            type="button"
                            @click="isSplitPayment = true"
                            :class="[
                                'flex-1 py-1.5 px-3 rounded text-xs font-semibold transition-all',
                                isSplitPayment ? 'bg-card text-primary font-bold shadow-2xs' : 'text-muted-foreground hover:text-foreground'
                            ]"
                        >
                            🔀 Split Payment (e.g. Cash + Lipa Namba)
                        </button>
                    </div>

                    <!-- MODE A: Single Payment Method -->
                    <div v-if="!isSplitPayment" class="space-y-4">
                        <!-- Payment Channel Selector -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block font-bold text-xs text-foreground">Payment Channel</label>
                                <span class="text-[10px] font-mono text-muted-foreground">General ledger account auto-routed</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <button
                                    v-for="method in paymentMethods"
                                    :key="method.id"
                                    type="button"
                                    @click="paymentMethod = method.id"
                                    :class="[
                                        'p-3 rounded-xl border text-left flex flex-col justify-between h-24 transition-all relative overflow-hidden group select-none',
                                        paymentMethod === method.id 
                                            ? 'border-primary ring-2 ring-primary/30 bg-primary/5 shadow-xs' 
                                            : 'border-border/70 bg-card hover:bg-muted/40 hover:border-border'
                                    ]"
                                >
                                    <!-- Top: Icon Pill + Selection Dot -->
                                    <div class="flex items-center justify-between w-full">
                                        <div 
                                            class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 border transition-transform group-hover:scale-105"
                                            :class="method.bg"
                                        >
                                            <component :is="method.icon" class="w-3.5 h-3.5" :class="method.color" />
                                        </div>
                                        <div 
                                            class="w-4 h-4 rounded-full border flex items-center justify-center transition-all"
                                            :class="paymentMethod === method.id ? 'border-primary bg-primary' : 'border-border bg-card'"
                                        >
                                            <span v-if="paymentMethod === method.id" class="w-1.5 h-1.5 rounded-full bg-primary-foreground"></span>
                                        </div>
                                    </div>

                                    <!-- Bottom: Title & Subtitle -->
                                    <div class="min-w-0">
                                        <div class="font-bold text-xs text-foreground truncate group-hover:text-primary transition-colors">
                                            {{ method.name }}
                                        </div>
                                        <div class="text-[10px] text-muted-foreground truncate">
                                            {{ method.subtitle }}
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Amount & Reference Number (2 Columns) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="block font-bold text-xs text-foreground">Amount to Collect (TZS)</label>
                                <Input
                                    v-model="paymentAmount"
                                    type="number"
                                    min="1"
                                    class="w-full font-mono text-sm h-9"
                                    placeholder="Amount in TZS"
                                    autofocus
                                />
                            </div>
                            <div class="space-y-1.5">
                                <label class="block font-bold text-xs text-foreground">
                                    Reference Number / Receipt
                                    <span class="text-[10px] text-muted-foreground font-normal">(Optional)</span>
                                </label>
                                <Input
                                    v-model="paymentReferenceNumber"
                                    type="text"
                                    class="w-full font-mono text-xs h-9"
                                    placeholder="e.g. M-Pesa Code: QK8291X0"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- MODE B: Multi-Method Split Payment -->
                    <div v-else class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block font-bold text-xs text-foreground">Split Payment Allocation</label>
                                <span class="text-[10px] text-muted-foreground">Allocate portions across multiple tender channels</span>
                            </div>
                            <button
                                type="button"
                                @click="addSplitRow"
                                class="text-[11px] text-primary font-semibold hover:underline flex items-center gap-1"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Split Channel</span>
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="(split, idx) in splitMethods"
                                :key="idx"
                                class="p-3 bg-muted/40 rounded-lg border border-border/70 flex items-center gap-2.5"
                            >
                                <Select
                                    v-model="split.method"
                                    :options="paymentMethods.map(m => ({ label: `${m.name} (${m.subtitle})`, value: m.id }))"
                                    class="w-52 h-8 text-xs font-medium flex-shrink-0"
                                />
                                <Input
                                    v-model="split.amount"
                                    type="number"
                                    min="1"
                                    placeholder="Amount (TZS)"
                                    class="w-36 h-8 font-mono text-xs flex-shrink-0"
                                />
                                <Input
                                    v-model="split.reference_number"
                                    type="text"
                                    placeholder="Ref # (e.g. M-Pesa Code / POS Slip)"
                                    class="flex-1 h-8 font-mono text-xs"
                                />
                                <button
                                    v-if="splitMethods.length > 1"
                                    type="button"
                                    @click="removeSplitRow(idx)"
                                    class="w-8 h-8 flex items-center justify-center text-muted-foreground hover:text-rose-600 rounded transition-colors flex-shrink-0"
                                    title="Remove row"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Split Balance Reconciliation Summary -->
                        <div class="p-3 rounded-lg bg-card border border-border/60 flex items-center justify-between text-xs font-mono">
                            <span class="text-muted-foreground">Total Split Sum Allocated:</span>
                            <span class="font-bold text-foreground text-sm">
                                TZS {{ splitMethods.reduce((sum, s) => sum + (Number(s.amount) || 0), 0).toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                    <Button variant="outline" size="sm" @click="closePaymentModal" :disabled="isSubmittingPayment">Cancel</Button>
                    <Button
                        variant="default"
                        size="sm"
                        @click="submitPayment"
                        :disabled="isSubmittingPayment || (!isSplitPayment && !paymentAmount) || (isSplitPayment && splitMethods.every(s => !s.amount))"
                    >
                        <Loader2 v-if="isSubmittingPayment" class="w-3.5 h-3.5 animate-spin mr-1.5" />
                        <span>Post {{ isSplitPayment ? 'Split Payments' : 'Payment' }} to Ledger</span>
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- Refund / Reversal Accessible Modal Dialog -->
        <Modal :show="showRefundModal" max-width="md" @close="closeRefundModal">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Undo2 class="w-4 h-4 text-rose-600" />
                        <h3 class="font-bold text-sm text-foreground">Issue Invoice Refund</h3>
                    </div>
                    <button @click="closeRefundModal" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="refundInvoiceData" class="space-y-3 text-xs">
                    <div class="p-3 bg-rose-50/70 border border-rose-200 rounded text-rose-950 space-y-1">
                        <div class="flex justify-between font-bold">
                            <span>Invoice #{{ refundInvoiceData.invoice_number }}</span>
                            <span>Max Refund: TZS {{ Number(refundInvoiceData.paid_amount).toLocaleString() }}</span>
                        </div>
                        <p class="text-[11px] text-rose-800">
                            Issuing a refund will post an offsetting reversal entry to the General Ledger.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-xs text-foreground">Refund Amount (TZS)</label>
                        <Input
                            v-model="refundAmount"
                            type="number"
                            min="1"
                            :max="refundInvoiceData.paid_amount"
                            class="w-full font-mono"
                            placeholder="Amount in TZS"
                            autofocus
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-xs text-foreground">Reason for Adjustment / Refund</label>
                        <Input
                            v-model="refundReason"
                            type="text"
                            class="w-full"
                            placeholder="e.g. Overpayment, Patient transfer, Service cancelled"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <Button variant="outline" size="sm" @click="closeRefundModal" :disabled="isSubmittingRefund">Cancel</Button>
                    <Button variant="destructive" size="sm" @click="submitRefund" :disabled="isSubmittingRefund || !refundAmount">
                        <Loader2 v-if="isSubmittingRefund" class="w-3.5 h-3.5 animate-spin mr-1.5" />
                        <span>Confirm Ledger Reversal</span>
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- Slide-Over Itemized Charges & Services Drawer -->
        <InvoiceChargesDrawer
            :invoice="selectedInvoice"
            :open="showChargesDrawer"
            @close="showChargesDrawer = false"
            @pay="openPaymentModal"
            @refund="openRefundModal"
        />

        <!-- Cashier Shift Management Accessible Modal Dialog -->
        <CashierShiftModal
            :show="showShiftModal"
            :mode="shiftModalMode"
            :active-shift="activeShift"
            :telemetry="tillTelemetry"
            @close="showShiftModal = false"
        />
    </AfyaShell>
</template>
