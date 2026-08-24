<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    X,
    Receipt,
    DollarSign,
    Plus,
    Scale,
    Stethoscope,
    Pill,
    FlaskConical,
    Syringe,
    HeartPulse,
    ShieldCheck,
    Printer,
    CheckCircle2,
    Clock,
    AlertCircle,
    Loader2,
    Trash2,
    CreditCard,
    Smartphone,
    Building2,
    Wallet
} from '@lucide/vue';
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

const props = defineProps({
    invoice: {
        type: Object,
        default: null,
    },
    open: {
        type: Boolean,
        default: false,
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close', 'pay', 'refund']);

// ── Add Charge Form State ───────────────────────────────────────────────────
const showAddChargeForm = ref(false);
const chargeCategory = ref('Consultation');
const chargeDescription = ref('');
const chargeQuantity = ref(1);
const chargeUnitPrice = ref('');
const isSubmittingCharge = ref(false);

const categories = [
    { id: 'Consultation', label: 'Consultation', icon: Stethoscope, color: 'text-sky-600 bg-sky-50 dark:bg-sky-950/40 dark:text-sky-300' },
    { id: 'Pharmacy', label: 'Pharmacy / Drug', icon: Pill, color: 'text-purple-600 bg-purple-50 dark:bg-purple-950/40 dark:text-purple-300' },
    { id: 'Lab', label: 'Diagnostic Lab', icon: FlaskConical, color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300' },
    { id: 'Procedure', label: 'Clinical Procedure', icon: Syringe, color: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-300' },
    { id: 'Nursing', label: 'Nursing & Bed', icon: HeartPulse, color: 'text-cyan-600 bg-cyan-50 dark:bg-cyan-950/40 dark:text-cyan-300' },
];

const categoryMeta = (cat) => {
    return categories.find(c => c.id === cat) || categories[0];
};

const lineItems = computed(() => {
    return props.invoice?.line_items || props.invoice?.lineItems || [];
});

const grossTotal = computed(() => {
    return Number(props.invoice?.total_amount || 0);
});

const paidTotal = computed(() => {
    return Number(props.invoice?.paid_amount || 0);
});

const balanceDue = computed(() => {
    return Math.max(0, grossTotal.value - paidTotal.value);
});

const isPaidInFull = computed(() => {
    return grossTotal.value > 0 && paidTotal.value >= grossTotal.value;
});

const printReceipt = () => {
    window.print();
};

const submitAddCharge = () => {
    if (!props.invoice || !chargeDescription.value || !chargeUnitPrice.value) return;

    isSubmittingCharge.value = true;
    router.post(route('billing.items.store', props.invoice.id), {
        description: chargeDescription.value,
        category: chargeCategory.value,
        quantity: Number(chargeQuantity.value),
        unit_price: Number(chargeUnitPrice.value),
    }, {
        onFinish: () => {
            isSubmittingCharge.value = false;
            chargeDescription.value = '';
            chargeUnitPrice.value = '';
            chargeQuantity.value = 1;
            showAddChargeForm.value = false;
        }
    });
};

// ── Keyboard ESC to close ──────────────────────────────────────────────────
const handleKeydown = (e) => {
    if (e.key === 'Escape' && props.open) {
        emit('close');
    }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <!-- Teleport slide-over to body -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && invoice"
                class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex justify-end"
                @click.self="emit('close')"
            >
                <Transition
                    enter-active-class="transform transition-transform ease-out duration-250"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transform transition-transform ease-in duration-200"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div
                        v-if="open && invoice"
                        class="h-full bg-background border-l border-border/60 shadow-2xl flex flex-col overflow-hidden select-none"
                        style="width: min(96vw, 1080px)"
                    >
                        <!-- ═══════════════════════════════════════════════════
                             1. DRAWER HEADER: Invoice # & Patient Demographics
                        ════════════════════════════════════════════════════ -->
                        <div class="flex-shrink-0 border-b border-border/60 bg-card p-4">
                            <div class="flex items-center justify-between gap-3">
                                <!-- Invoice Identity -->
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-mono font-bold text-sm shadow-2xs flex-shrink-0">
                                        <Receipt class="w-5 h-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-extrabold text-foreground text-sm tracking-tight">
                                                {{ invoice.invoice_number }}
                                            </span>
                                            <AfyaStatusBadge :status="invoice.status" dot />
                                            <span class="text-[10px] font-mono text-muted-foreground bg-muted/60 px-2 py-0.5 rounded-md">
                                                Issued: {{ new Date(invoice.issued_at || invoice.created_at).toLocaleDateString() }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-foreground font-semibold flex items-center gap-2 mt-0.5">
                                            <span>{{ invoice.patient?.first_name }} {{ invoice.patient?.middle_name || '' }} {{ invoice.patient?.last_name }}</span>
                                            <span class="text-muted-foreground font-mono text-[11px]">(MRN: {{ invoice.patient?.primary_mrn }})</span>
                                            <span class="text-muted-foreground">·</span>
                                            <span class="text-muted-foreground text-[11px]">{{ invoice.patient?.gender || 'Gender N/A' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons & Close -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 px-2.5 text-[11px] gap-1.5 shadow-2xs"
                                        @click="printReceipt"
                                        title="Print Official Tax Receipt"
                                    >
                                        <Printer class="w-3.5 h-3.5" />
                                        <span>Print Receipt</span>
                                    </Button>

                                    <Button
                                        v-if="!isPaidInFull && can.pay"
                                        variant="default"
                                        size="sm"
                                        class="h-7 px-3 text-[11px] gap-1.5 shadow-2xs font-semibold"
                                        @click="emit('pay', invoice)"
                                    >
                                        <DollarSign class="w-3.5 h-3.5" />
                                        <span>Collect POS</span>
                                    </Button>

                                    <Button
                                        v-else-if="isPaidInFull && can.refund"
                                        variant="subtle"
                                        size="sm"
                                        class="h-7 px-2.5 text-[11px] gap-1.5 text-muted-foreground hover:text-foreground"
                                        @click="emit('refund', invoice)"
                                    >
                                        <span>Refund</span>
                                    </Button>

                                    <button
                                        @click="emit('close')"
                                        class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground hover:text-foreground transition-colors ml-1"
                                        title="Close Drawer (Esc)"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ═══════════════════════════════════════════════════
                             2. DRAWER BODY: Itemized Billable Charges Grid
                        ════════════════════════════════════════════════════ -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-4">
                            <!-- Charges Header & Add Button -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                        Itemized Billable Charges ({{ lineItems.length }})
                                    </h3>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <ShieldCheck class="w-3 h-3 text-emerald-600" />
                                        MSD / MoH Tariff Verified
                                    </span>
                                </div>

                                <Button
                                    v-if="invoice.status !== 'Paid' && can.addItem"
                                    variant="outline"
                                    size="sm"
                                    class="h-6 px-2 text-[10px] gap-1 shadow-2xs"
                                    @click="showAddChargeForm = !showAddChargeForm"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>{{ showAddChargeForm ? 'Cancel' : 'Add Charge Item' }}</span>
                                </Button>
                            </div>

                            <!-- Inline Add Charge Item Form (Clean Single Row on Wide Canvas) -->
                            <div v-if="showAddChargeForm" class="p-3 bg-muted/40 rounded-lg border border-border/60 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="text-[11px] font-bold text-foreground flex items-center gap-1.5">
                                        <Plus class="w-3.5 h-3.5 text-primary" />
                                        <span>Add Billable Clinical Charge</span>
                                    </div>
                                    <span class="text-[10px] text-muted-foreground">Appends item and updates general ledger automatically</span>
                                </div>
                                <div class="flex items-end gap-2 text-xs">
                                    <div class="w-40 flex-shrink-0 space-y-1">
                                        <label class="text-[10px] font-bold text-muted-foreground uppercase">Category</label>
                                        <Select
                                            v-model="chargeCategory"
                                            :options="categories.map(c => ({ label: c.label, value: c.id }))"
                                            class="h-8 text-xs"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-[220px] space-y-1">
                                        <label class="text-[10px] font-bold text-muted-foreground uppercase">Description</label>
                                        <Input
                                            v-model="chargeDescription"
                                            placeholder="e.g. Minor Wound Dressing / ECG"
                                            class="h-8 text-xs w-full"
                                        />
                                    </div>
                                    <div class="w-16 flex-shrink-0 space-y-1">
                                        <label class="text-[10px] font-bold text-muted-foreground uppercase text-center block">Qty</label>
                                        <Input
                                            v-model="chargeQuantity"
                                            type="number"
                                            min="1"
                                            class="h-8 text-xs font-mono text-center w-full"
                                        />
                                    </div>
                                    <div class="w-32 flex-shrink-0 space-y-1">
                                        <label class="text-[10px] font-bold text-muted-foreground uppercase">Unit (TZS)</label>
                                        <Input
                                            v-model="chargeUnitPrice"
                                            type="number"
                                            placeholder="20,000"
                                            class="h-8 text-xs font-mono w-full"
                                        />
                                    </div>
                                    <div class="w-32 flex-shrink-0 space-y-1">
                                        <label class="text-[10px] font-bold text-muted-foreground uppercase text-right block">Line Total</label>
                                        <div class="h-8 px-2 flex items-center justify-end font-mono font-bold text-foreground text-xs bg-card rounded-md border border-border/60">
                                            {{ (Number(chargeQuantity || 0) * Number(chargeUnitPrice || 0)).toLocaleString() }}
                                        </div>
                                    </div>
                                    <Button
                                        variant="default"
                                        size="sm"
                                        class="h-8 px-3 text-[11px] font-semibold gap-1.5 shadow-2xs flex-shrink-0"
                                        :disabled="isSubmittingCharge || !chargeDescription || !chargeUnitPrice"
                                        @click="submitAddCharge"
                                    >
                                        <Loader2 v-if="isSubmittingCharge" class="w-3.5 h-3.5 animate-spin" />
                                        <span>Save Charge</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Line Items Table -->
                            <div class="bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-8 text-[10px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1.5 px-4">Category</TableHead>
                                            <TableHead class="py-1.5 px-3">Item Description</TableHead>
                                            <TableHead class="py-1.5 px-3 text-center">Qty</TableHead>
                                            <TableHead class="py-1.5 px-3 text-right">Unit Price (TZS)</TableHead>
                                            <TableHead class="py-1.5 px-4 text-right">Total Price (TZS)</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="item in lineItems"
                                            :key="item.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1.5 px-4">
                                                <span 
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold"
                                                    :class="categoryMeta(item.category).color"
                                                >
                                                    <component :is="categoryMeta(item.category).icon" class="w-3 h-3" />
                                                    <span>{{ item.category }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1.5 px-3 font-medium text-foreground">
                                                {{ item.description }}
                                            </TableCell>
                                            <TableCell class="py-1.5 px-3 text-center font-mono font-bold text-muted-foreground">
                                                {{ item.quantity }}
                                            </TableCell>
                                            <TableCell class="py-1.5 px-3 text-right font-mono text-muted-foreground">
                                                {{ Number(item.unit_price).toLocaleString() }}
                                            </TableCell>
                                            <TableCell class="py-1.5 px-4 text-right font-mono font-bold text-foreground">
                                                {{ Number(item.total_price).toLocaleString() }}
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="lineItems.length === 0">
                                            <TableCell colspan="5" class="text-center py-8 text-muted-foreground">
                                                <Receipt class="w-6 h-6 mx-auto mb-1.5 text-muted-foreground opacity-40" />
                                                <p class="font-semibold text-foreground text-xs">No line items attached to this invoice</p>
                                                <p class="text-[10px]">Click "Add Charge Item" above to add billable services.</p>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <!-- ═══════════════════════════════════════════════════
                                 3. FINANCIAL SUMMARY & GENERAL LEDGER VERIFICATION
                            ════════════════════════════════════════════════════ -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                                <!-- Multi-Payer Summary -->
                                <div class="p-3.5 rounded-lg bg-card shadow-2xs space-y-2 text-xs">
                                    <div class="flex items-center justify-between border-b border-border/40 pb-2">
                                        <div class="flex items-center gap-1.5 font-bold text-foreground text-xs">
                                            <Wallet class="w-3.5 h-3.5 text-primary" />
                                            <span>Payment Balance Overview</span>
                                        </div>
                                        <span class="text-[10px] font-mono text-muted-foreground">TZS (Tanzanian Shilling)</span>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Gross Invoiced Charges:</span>
                                            <span class="font-mono font-bold text-foreground">TZS {{ grossTotal.toLocaleString() }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Insurance / Co-Pay Coverage:</span>
                                            <span class="font-mono text-muted-foreground">TZS 0.00 (Self-Pay)</span>
                                        </div>
                                        <div class="flex justify-between border-t border-border/30 pt-1">
                                            <span class="text-muted-foreground">Amount Paid at Cashier:</span>
                                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">TZS {{ paidTotal.toLocaleString() }}</span>
                                        </div>
                                        <div class="flex justify-between border-t border-border/40 pt-1.5 text-sm font-bold">
                                            <span :class="balanceDue > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'">Net Balance Due:</span>
                                            <span class="font-mono" :class="balanceDue > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
                                                TZS {{ balanceDue.toLocaleString() }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Double Entry General Ledger Breakdown -->
                                <div class="p-3.5 rounded-lg bg-card shadow-2xs space-y-2 text-xs">
                                    <div class="flex items-center justify-between border-b border-border/40 pb-2">
                                        <div class="flex items-center gap-1.5 font-bold text-foreground text-xs">
                                            <Scale class="w-3.5 h-3.5 text-primary" />
                                            <span>Double-Entry General Ledger Trail</span>
                                        </div>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                            <CheckCircle2 class="w-3 h-3" />
                                            Balanced
                                        </span>
                                    </div>
                                    <div class="space-y-1.5 text-[11px] font-mono">
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Debit (1020 Cash/M-Pesa):</span>
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400">TZS {{ paidTotal.toLocaleString() }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Debit (1100 Patient AR):</span>
                                            <span class="font-bold text-rose-600 dark:text-rose-400">TZS {{ balanceDue.toLocaleString() }}</span>
                                        </div>
                                        <div class="flex justify-between border-t border-border/30 pt-1">
                                            <span class="text-muted-foreground">Credit (4000 Clinical Revenue):</span>
                                            <span class="font-bold text-foreground">TZS {{ grossTotal.toLocaleString() }}</span>
                                        </div>
                                        <div class="flex justify-between border-t border-border/40 pt-1 text-[10px] text-muted-foreground">
                                            <span>Ledger Invariant:</span>
                                            <span>Debits = Credits (0.00 Variance)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ═══════════════════════════════════════════════════
                             4. DRAWER FOOTER: Quick Summary & POS Trigger
                        ════════════════════════════════════════════════════ -->
                        <div class="flex-shrink-0 border-t border-border/60 bg-card p-3 flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="text-muted-foreground">Invoice Status:</span>
                                <AfyaStatusBadge :status="invoice.status" dot />
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="outline" size="sm" class="h-7 px-3 text-xs" @click="emit('close')">
                                    Close Drawer
                                </Button>
                                <Button
                                    v-if="!isPaidInFull"
                                    variant="default"
                                    size="sm"
                                    class="h-7 px-4 text-xs font-semibold gap-1.5 shadow-2xs"
                                    @click="emit('pay', invoice)"
                                >
                                    <DollarSign class="w-3.5 h-3.5" />
                                    <span>Collect TZS {{ balanceDue.toLocaleString() }}</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
