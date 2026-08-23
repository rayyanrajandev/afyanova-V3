<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    FlaskConical, 
    Search, 
    Trash2, 
    Clock, 
    Receipt, 
    CheckCircle2, 
    AlertTriangle, 
    ShieldAlert, 
    Loader2,
    Send,
    Check,
    X,
    Info,
    Plus
} from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import LabResultsCard from '@/Components/Clinical/LabResultsCard.vue';
import LabWorkbenchModal from '@/Components/Clinical/LabWorkbenchModal.vue';

const props = defineProps({
    encounter: {
        type: Object,
        required: true,
    },
    labTests: {
        type: Array,
        default: () => [],
    },
});

const selectedCategory = ref('all');
const searchQuery = ref('');
const selectedTestIds = ref([]);
const priority = ref('Routine');
const clinicalNotes = ref('');
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

// Active Modal for Results Entry
const showWorkbenchModal = ref(false);
const activeWorkbenchItem = ref(null);

const categories = computed(() => {
    const cats = new Set(props.labTests.map(t => t.category).filter(Boolean));
    return ['all', ...Array.from(cats)];
});

// Set of test IDs already ordered in this active encounter
const orderedTestMap = computed(() => {
    const map = new Map();
    (props.encounter?.lab_orders || []).forEach(order => {
        (order.items || []).forEach(item => {
            if (item.lab_test_id) {
                map.set(item.lab_test_id, {
                    orderNumber: order.order_number,
                    itemStatus: item.status,
                    hasCritical: item.has_critical_value,
                });
            }
        });
    });
    return map;
});

const isTestAlreadyOrdered = (testId) => {
    return orderedTestMap.value.has(testId);
};

const getTestOrderStatus = (testId) => {
    return orderedTestMap.value.get(testId) || null;
};

const filteredTests = computed(() => {
    return props.labTests.filter(test => {
        const matchesCategory = selectedCategory.value === 'all' || test.category === selectedCategory.value;
        const matchesSearch = !searchQuery.value || 
            test.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            test.test_code.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            test.specimen_type.toLowerCase().includes(searchQuery.value.toLowerCase());
        return matchesCategory && matchesSearch;
    });
});

const selectedTests = computed(() => {
    return props.labTests.filter(t => selectedTestIds.value.includes(t.id));
});

const totalOrderCost = computed(() => {
    return selectedTests.value.reduce((sum, t) => sum + parseFloat(t.price || 0), 0);
});

const toggleTest = (testId) => {
    if (isTestAlreadyOrdered(testId)) {
        errorMessage.value = 'This test is already ordered in this encounter.';
        setTimeout(() => { errorMessage.value = ''; }, 3500);
        return;
    }

    if (selectedTestIds.value.includes(testId)) {
        selectedTestIds.value = selectedTestIds.value.filter(id => id !== testId);
    } else {
        selectedTestIds.value.push(testId);
        errorMessage.value = '';
    }
};

const removeTest = (testId) => {
    selectedTestIds.value = selectedTestIds.value.filter(id => id !== testId);
};

const formatCurrency = (val) => Number(val || 0).toLocaleString('en-US');

const submitLabOrder = () => {
    if (selectedTestIds.value.length === 0) return;
    isSubmitting.value = true;
    errorMessage.value = '';

    router.post(route('lab-orders.store', props.encounter.id), {
        test_ids: selectedTestIds.value,
        priority: priority.value,
        clinical_notes: clinicalNotes.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            const count = selectedTestIds.value.length;
            selectedTestIds.value = [];
            clinicalNotes.value = '';
            priority.value = 'Routine';
            successMessage.value = `✓ Successfully ordered ${count} investigation(s). Added to billing ledger.`;
            setTimeout(() => {
                successMessage.value = '';
            }, 4000);
        },
        onError: (errors) => {
            isSubmitting.value = false;
            errorMessage.value = errors.lab_order || 'Failed to place lab order.';
            setTimeout(() => {
                errorMessage.value = '';
            }, 5000);
        }
    });
};

const openWorkbench = (item) => {
    activeWorkbenchItem.value = item;
    showWorkbenchModal.value = true;
};

// Flatten all items across all lab orders for this encounter
const encounterLabOrders = computed(() => props.encounter?.lab_orders || []);
const encounterLabItems = computed(() => {
    const items = [];
    encounterLabOrders.value.forEach(order => {
        (order.items || []).forEach(item => {
            items.push({
                ...item,
                parent_order: order
            });
        });
    });
    return items;
});

const hasAnyCriticalResults = computed(() => {
    return encounterLabItems.value.some(item => item.has_critical_value);
});
</script>

<template>
    <div class="space-y-2.5">
        
        <!-- Compact Flash Feedback Banners -->
        <div 
            v-if="successMessage" 
            class="p-2 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between shadow-2xs"
        >
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                <span>{{ successMessage }}</span>
            </div>
            <button @click="successMessage = ''" class="text-emerald-700 hover:text-emerald-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <div 
            v-if="errorMessage" 
            class="p-2 rounded-md bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 text-xs font-semibold flex items-center justify-between shadow-2xs"
        >
            <div class="flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 flex-shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>
            <button @click="errorMessage = ''" class="text-rose-700 hover:text-rose-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <!-- ULTRA-COMPACT SIDE-BY-SIDE CLINICAL DESK (ZERO-SCROLL VIEWPORT) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
            
            <!-- LEFT COLUMN: Test Catalog & Basket Ordering Station (5 cols) -->
            <div class="lg:col-span-5 space-y-2">
                <div class="p-2.5 rounded-lg bg-muted/20 border border-border/70 space-y-2">
                    
                    <!-- Search + Category Pills in 1 Compact Row -->
                    <div class="space-y-1.5">
                        <div class="relative">
                            <Search class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <Input 
                                v-model="searchQuery" 
                                placeholder="Search test name, code..." 
                                class="pl-7 text-xs h-6.5 text-[11px]"
                            />
                        </div>

                        <!-- Category Filter Pills -->
                        <div class="flex items-center gap-1 overflow-x-auto pb-0.5 scrollbar-none">
                            <button
                                v-for="cat in categories"
                                :key="cat"
                                type="button"
                                @click="selectedCategory = cat"
                                class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase transition-all whitespace-nowrap"
                                :class="selectedCategory === cat 
                                    ? 'bg-primary text-primary-foreground shadow-2xs' 
                                    : 'bg-muted/70 text-muted-foreground hover:bg-muted'"
                            >
                                {{ cat }}
                            </button>
                        </div>
                    </div>

                    <!-- Catalog Test List (Compact, Scrollable Inner Pane) -->
                    <div class="space-y-1 max-h-[160px] overflow-y-auto pr-1 scrollbar-thin">
                        <div
                            v-for="test in filteredTests"
                            :key="test.id"
                            @click="toggleTest(test.id)"
                            class="p-1.5 rounded-md border text-left transition-all flex items-center justify-between gap-2 select-none text-xs"
                            :class="[
                                isTestAlreadyOrdered(test.id)
                                    ? 'bg-muted/40 border-border/30 opacity-60 cursor-not-allowed'
                                    : (selectedTestIds.includes(test.id)
                                        ? 'bg-primary/10 border-primary ring-1 ring-primary shadow-2xs cursor-pointer'
                                        : 'bg-card border-border/50 hover:bg-muted/30 cursor-pointer')
                            ]"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-foreground truncate text-[11px]">
                                    {{ test.name }}
                                </div>
                                <div class="text-[9px] text-muted-foreground font-mono flex items-center gap-1.5">
                                    <span>{{ test.test_code }}</span>
                                    <span>·</span>
                                    <span>{{ test.turnaround_time_minutes }}m TAT</span>
                                </div>
                            </div>
                            
                            <!-- Price & Check -->
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <span class="font-bold font-mono text-[10px] text-emerald-700 dark:text-emerald-400">
                                    TZS {{ formatCurrency(test.price) }}
                                </span>

                                <span v-if="isTestAlreadyOrdered(test.id)" class="px-1 py-0.2 rounded text-[8px] font-bold uppercase" :class="getTestOrderStatus(test.id)?.itemStatus === 'Completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ getTestOrderStatus(test.id)?.itemStatus || 'Ordered' }}
                                </span>

                                <div 
                                    v-else
                                    class="w-3.5 h-3.5 rounded flex items-center justify-center transition-colors"
                                    :class="selectedTestIds.includes(test.id) ? 'bg-primary text-primary-foreground' : 'border border-muted-foreground/40'"
                                >
                                    <Check v-if="selectedTestIds.includes(test.id)" class="w-2.5 h-2.5" />
                                    <Plus v-else class="w-2.5 h-2.5 text-muted-foreground" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Basket Config & Action Strip -->
                    <div class="pt-2 border-t border-border/60 space-y-1.5">
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Priority:</span>
                                <div class="inline-flex rounded-md p-0.5 bg-muted/60 text-[9px] font-bold">
                                    <button 
                                        type="button" 
                                        @click="priority = 'Routine'" 
                                        class="px-1.5 py-0.5 rounded transition-all"
                                        :class="priority === 'Routine' ? 'bg-background text-foreground shadow-2xs font-extrabold' : 'text-muted-foreground'"
                                    >
                                        Routine
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="priority = 'Urgent'" 
                                        class="px-1.5 py-0.5 rounded transition-all"
                                        :class="priority === 'Urgent' ? 'bg-amber-500 text-white shadow-2xs font-extrabold' : 'text-muted-foreground'"
                                    >
                                        Urgent
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="priority = 'STAT'" 
                                        class="px-1.5 py-0.5 rounded transition-all"
                                        :class="priority === 'STAT' ? 'bg-rose-600 text-white shadow-2xs font-extrabold animate-pulse' : 'text-muted-foreground'"
                                    >
                                        STAT
                                    </button>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-[9px] text-muted-foreground uppercase mr-1">Total:</span>
                                <span class="font-mono font-bold text-xs text-emerald-700 dark:text-emerald-400">
                                    TZS {{ formatCurrency(totalOrderCost) }}
                                </span>
                            </div>
                        </div>

                        <!-- Compact Indication Field -->
                        <Input
                            v-model="clinicalNotes"
                            placeholder="Clinical indications / notes..."
                            class="text-xs h-6.5 text-[11px]"
                        />

                        <!-- Place Order Button -->
                        <Button
                            variant="default"
                            size="sm"
                            class="w-full justify-center gap-1.5 text-xs h-7 shadow-2xs font-semibold"
                            :disabled="selectedTestIds.length === 0 || isSubmitting"
                            @click="submitLabOrder"
                        >
                            <Loader2 v-if="isSubmitting" class="w-3 h-3 animate-spin" />
                            <Send v-else class="w-3 h-3" />
                            <span>{{ isSubmitting ? 'Placing Order...' : `Place Lab Order (${selectedTestIds.length})` }}</span>
                        </Button>
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN: Ordered Investigations & Live Results Stream (7 cols) -->
            <div class="lg:col-span-7 space-y-2">
                <div class="p-2.5 rounded-lg bg-card border border-border/70 shadow-2xs space-y-2">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-1.5 border-b border-border/60">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-foreground">
                            <FlaskConical class="w-3.5 h-3.5 text-primary" />
                            <span>Active Ordered Investigations & Findings</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Critical Panic Alert Pill if Detected -->
                            <span 
                                v-if="hasAnyCriticalResults"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-rose-600 text-white animate-pulse"
                            >
                                <ShieldAlert class="w-2.5 h-2.5" />
                                <span>CRITICAL PANIC</span>
                            </span>

                            <span class="text-[10px] font-mono text-muted-foreground">
                                {{ encounterLabItems.length }} Item(s)
                            </span>
                        </div>
                    </div>

                    <!-- Investigations Scrollable List (Scrolls Internally so Whole Page Doesn't Scroll!) -->
                    <div 
                        v-if="encounterLabItems.length > 0" 
                        class="space-y-2 max-h-[310px] overflow-y-auto pr-1 scrollbar-thin"
                    >
                        <LabResultsCard
                            v-for="item in encounterLabItems"
                            :key="item.id"
                            :item="item"
                            @open-workbench="openWorkbench(item)"
                        />
                    </div>

                    <div v-else class="py-8 text-center text-muted-foreground text-xs bg-muted/10 rounded-md border border-dashed border-border/60">
                        <FlaskConical class="w-4 h-4 mx-auto mb-1 text-muted-foreground/40" />
                        <p class="font-medium text-[11px]">No active investigations ordered yet</p>
                        <p class="text-[9px] text-muted-foreground/80">Select from catalog on the left to order tests</p>
                    </div>

                </div>
            </div>

        </div>

        <!-- Lab Workbench Modal for Entering Results -->
        <LabWorkbenchModal
            :show="showWorkbenchModal"
            :item="activeWorkbenchItem"
            @close="showWorkbenchModal = false"
        />

    </div>
</template>
