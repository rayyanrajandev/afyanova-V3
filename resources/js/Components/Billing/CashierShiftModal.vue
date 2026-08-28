<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { 
    Wallet, 
    ShieldCheck, 
    X, 
    Loader2, 
    Clock, 
    CheckCircle2, 
    AlertTriangle, 
    DollarSign,
    Receipt
} from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'open', // 'open' or 'close'
    },
    notice: {
        type: String,
        default: '',
    },
    activeShift: {
        type: Object,
        default: null,
    },
    telemetry: {
        type: Object,
        default: () => ({}),
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close', 'opened']);

// Open Form State
const openingFloat = ref(50000);
const openNotes = ref('Main OPD Cashier Window 1');
const isSubmittingOpen = ref(false);

// Close Form State
const closingCashCounted = ref('');
const closeNotes = ref('Shift completed, handover verified.');
const isSubmittingClose = ref(false);

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.mode === 'close' && props.telemetry) {
            closingCashCounted.value = props.telemetry.cash_in_drawer || '';
        }
    }
});

const expectedCash = computed(() => {
    return Number(props.telemetry?.cash_in_drawer || props.activeShift?.opening_float || 0);
});

const calculatedDiscrepancy = computed(() => {
    const counted = Number(closingCashCounted.value || 0);
    return counted - expectedCash.value;
});

const varianceStatus = computed(() => {
    const diff = calculatedDiscrepancy.value;
    if (Math.abs(diff) < 0.01) return 'Balanced';
    if (diff > 0) return 'Overage';
    return 'Shortage';
});

const handleOpenShift = () => {
    isSubmittingOpen.value = true;
    router.post(route('billing.shifts.open'), {
        opening_float: Number(openingFloat.value || 0),
        notes: openNotes.value,
    }, {
        onSuccess: () => {
            emit('opened');
        },
        onFinish: () => {
            isSubmittingOpen.value = false;
            emit('close');
        }
    });
};

const handleCloseShift = () => {
    if (!props.activeShift) return;

    isSubmittingClose.value = true;
    router.post(route('billing.shifts.close', props.activeShift.id), {
        closing_cash_counted: Number(closingCashCounted.value || 0),
        notes: closeNotes.value,
    }, {
        onFinish: () => {
            isSubmittingClose.value = false;
            emit('close');
        }
    });
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <!-- 1. OPEN SHIFT DIALOG -->
        <div v-if="mode === 'open'" class="p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold shadow-2xs">
                        <Wallet class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-foreground">Open Cashier Shift Session</h3>
                        <p class="text-[11px] text-muted-foreground">Start till session and declare opening cash float</p>
                    </div>
                </div>
                <button @click="emit('close')" class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground hover:text-foreground">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Context Prompt Notice Banner -->
                <div v-if="notice" class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl flex items-start gap-2.5 text-amber-900 dark:text-amber-300">
                    <AlertTriangle class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                    <div class="text-[11px] leading-relaxed">
                        {{ notice }}
                    </div>
                </div>

                <div class="p-3.5 bg-muted/40 rounded-xl border border-border/70 space-y-1">
                    <div class="flex items-center gap-2 font-bold text-foreground">
                        <ShieldCheck class="w-4 h-4 text-emerald-600" />
                        <span>Daily Till Integrity Guarantee</span>
                    </div>
                    <p class="text-muted-foreground text-[11px] leading-relaxed">
                        Opening float represents the physical base cash in the till drawer before receiving patient payments.
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-bold text-xs text-foreground">Opening Cash Float (TZS)</label>
                    <Input
                        v-model="openingFloat"
                        type="number"
                        min="0"
                        placeholder="e.g. 50,000"
                        class="w-full font-mono text-sm h-10"
                        autofocus
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="block font-bold text-xs text-foreground">Till / Workstation Identifier</label>
                    <Input
                        v-model="openNotes"
                        type="text"
                        placeholder="e.g. Main OPD Cashier Window 1"
                        class="w-full text-xs h-9"
                    />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <Button variant="outline" size="sm" @click="emit('close')" :disabled="isSubmittingOpen">Cancel</Button>
                <Button v-if="can.openShift" variant="default" size="sm" @click="handleOpenShift" :disabled="isSubmittingOpen">
                    <Loader2 v-if="isSubmittingOpen" class="w-3.5 h-3.5 animate-spin mr-1.5" />
                    <span>Open Shift Session</span>
                </Button>
            </div>
        </div>

        <!-- 2. CLOSE SHIFT & HANDOVER DIALOG -->
        <div v-else-if="mode === 'close' && activeShift" class="p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold shadow-2xs">
                        <Clock class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-foreground">Shift Handover & Till Reconciliation</h3>
                        <p class="text-[11px] text-muted-foreground">Count physical cash in drawer and reconcile with General Ledger</p>
                    </div>
                </div>
                <button @click="emit('close')" class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground hover:text-foreground">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Shift Header Metrics -->
                <div class="p-3.5 bg-muted/40 rounded-xl border border-border/70 grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <div class="text-[10px] uppercase font-bold text-muted-foreground">Shift #</div>
                        <div class="font-mono font-bold text-foreground mt-0.5">{{ activeShift.shift_number }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold text-muted-foreground">Opening Float</div>
                        <div class="font-mono font-bold text-foreground mt-0.5">TZS {{ Number(activeShift.opening_float).toLocaleString() }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold text-muted-foreground">Lipa Namba Till</div>
                        <div class="font-mono font-bold text-emerald-700 dark:text-emerald-400 mt-0.5">
                            TZS {{ Number(telemetry.lipa_namba_total || 0).toLocaleString() }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold text-muted-foreground">Expected Drawer Cash</div>
                        <div class="font-mono font-extrabold text-foreground mt-0.5">
                            TZS {{ expectedCash.toLocaleString() }}
                        </div>
                    </div>
                </div>

                <!-- Blind Physical Cash Input -->
                <div class="space-y-1.5">
                    <label class="block font-bold text-xs text-foreground">Physical Counted Cash in Drawer (TZS)</label>
                    <Input
                        v-model="closingCashCounted"
                        type="number"
                        min="0"
                        placeholder="Enter physical cash counted"
                        class="w-full font-mono text-sm h-10"
                        autofocus
                    />
                </div>

                <!-- Live Reconciliation Status Banner -->
                <div 
                    v-if="closingCashCounted !== ''"
                    class="p-3.5 rounded-xl border flex items-center justify-between text-xs transition-all"
                    :class="{
                        'bg-emerald-50 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800': varianceStatus === 'Balanced',
                        'bg-amber-50 text-amber-950 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200 dark:border-amber-800': varianceStatus === 'Overage',
                        'bg-rose-50 text-rose-950 dark:bg-rose-950/40 dark:text-rose-300 border-rose-200 dark:border-rose-800': varianceStatus === 'Shortage',
                    }"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle2 v-if="varianceStatus === 'Balanced'" class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                        <AlertTriangle v-else class="w-4 h-4 flex-shrink-0" :class="varianceStatus === 'Overage' ? 'text-amber-600' : 'text-rose-600'" />
                        <div>
                            <div class="font-bold">Reconciliation Status: {{ varianceStatus }}</div>
                            <div class="text-[11px] opacity-80">
                                {{ varianceStatus === 'Balanced' ? 'Physical cash matches system ledger exactly.' : `Discrepancy of TZS ${Math.abs(calculatedDiscrepancy).toLocaleString()} detected.` }}
                            </div>
                        </div>
                    </div>
                    <div class="font-mono font-extrabold text-sm text-right">
                        {{ calculatedDiscrepancy >= 0 ? '+' : '' }}{{ calculatedDiscrepancy.toLocaleString() }} TZS
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-bold text-xs text-foreground">Handover Notes / Supervisor Sign-off</label>
                    <Input
                        v-model="closeNotes"
                        type="text"
                        placeholder="e.g. Handover to Afternoon Cashier Window 2"
                        class="w-full text-xs h-9"
                    />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <Button variant="outline" size="sm" @click="emit('close')" :disabled="isSubmittingClose">Cancel</Button>
                <Button v-if="can.closeShift" variant="destructive" size="sm" @click="handleCloseShift" :disabled="isSubmittingClose || closingCashCounted === ''">
                    <Loader2 v-if="isSubmittingClose" class="w-3.5 h-3.5 animate-spin mr-1.5" />
                    <span>Confirm Shift Close & Handover</span>
                </Button>
            </div>
        </div>
    </Modal>
</template>
