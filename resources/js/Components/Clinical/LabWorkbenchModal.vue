<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    FlaskConical, 
    AlertTriangle, 
    CheckCircle2, 
    X, 
    Loader2, 
    ShieldAlert,
    Barcode
} from '@lucide/vue';
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    item: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const formResults = ref({});
const technicianRemarks = ref('');
const isSubmitting = ref(false);
const errors = ref({});

watch(() => props.item, (newItem) => {
    if (newItem) {
        formResults.value = { ...(newItem.results || {}) };
        technicianRemarks.value = newItem.technician_remarks || '';
        errors.value = {};
    }
}, { immediate: true });

const parameters = computed(() => props.item?.lab_test?.parameters || []);

const hasLivePanicValue = computed(() => {
    if (!parameters.value) return false;
    for (const param of parameters.value) {
        const val = formResults.value[param.name];
        if (val !== undefined && val !== null && val !== '') {
            if (!isNaN(parseFloat(val)) && isFinite(val)) {
                const num = parseFloat(val);
                if (param.panic_low !== null && param.panic_low !== undefined && num <= parseFloat(param.panic_low)) {
                    return true;
                }
                if (param.panic_high !== null && param.panic_high !== undefined && num >= parseFloat(param.panic_high)) {
                    return true;
                }
            } else if (param.critical_value && String(val).toLowerCase().includes('positive')) {
                return true;
            }
        }
    }
    return false;
});

const submitResults = () => {
    if (!props.item) return;
    isSubmitting.value = true;
    errors.value = {};

    router.post(route('lab-orders.results', props.item.id), {
        results: formResults.value,
        technician_remarks: technicianRemarks.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            emit('close');
        },
        onError: (errs) => {
            isSubmitting.value = false;
            errors.value = errs;
        }
    });
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-5 space-y-4">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-border/60">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
                        <FlaskConical class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">
                            {{ item?.lab_test?.name || 'Diagnostic Result Entry' }}
                        </h3>
                        <p class="text-[11px] text-muted-foreground flex items-center gap-2">
                            <span>Code: {{ item?.lab_test?.test_code }}</span>
                            <span>·</span>
                            <span>Specimen: {{ item?.lab_test?.specimen_type }}</span>
                            <span v-if="item?.specimen_barcode" class="font-mono text-primary font-semibold">
                                ({{ item.specimen_barcode }})
                            </span>
                        </p>
                    </div>
                </div>
                <button 
                    @click="$emit('close')" 
                    class="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted/50 transition-colors"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Live Panic Warning Alert -->
            <div 
                v-if="hasLivePanicValue"
                class="p-3 rounded-lg bg-rose-600 text-white flex items-center gap-2.5 text-xs animate-pulse shadow-sm font-medium"
            >
                <ShieldAlert class="w-5 h-5 flex-shrink-0" />
                <div>
                    <div class="font-bold uppercase tracking-wider text-[11px]">CRITICAL PANIC LIMIT REACHED</div>
                    <div class="text-[11px] opacity-90">Entered findings exceed critical physiological limits. Alert will be flagged immediately.</div>
                </div>
            </div>

            <!-- Dynamic Parameter Form Fields -->
            <form @submit.prevent="submitResults" class="space-y-3.5">
                <div class="space-y-3 max-h-[380px] overflow-y-auto pr-1">
                    <div 
                        v-for="param in parameters" 
                        :key="param.name"
                        class="p-2.5 rounded-lg bg-muted/20 border border-border/50 space-y-1.5"
                    >
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-foreground">
                                {{ param.name }}
                            </label>
                            <span class="text-[10px] font-mono text-muted-foreground">
                                <template v-if="param.min !== undefined && param.max !== undefined">
                                    Ref: {{ param.min }} – {{ param.max }} {{ param.unit }}
                                </template>
                                <template v-else-if="param.normal">
                                    Normal: {{ param.normal }}
                                </template>
                            </span>
                        </div>

                        <!-- Qualitative Dropdown -->
                        <div v-if="param.type === 'qualitative'">
                            <Select 
                                v-model="formResults[param.name]" 
                                class="w-full text-xs h-8"
                            >
                                <option value="">Select finding...</option>
                                <option value="Negative">Negative (Non-Reactive)</option>
                                <option value="Positive (Low Density)">Positive (Low Density / 1+)</option>
                                <option value="Positive (Moderate Density)">Positive (Moderate Density / 2+)</option>
                                <option value="Positive (High Density)">Positive (High Density / 3+)</option>
                                <option value="Trace">Trace</option>
                                <option value="Normal">Normal</option>
                            </Select>
                        </div>

                        <!-- Numeric Input -->
                        <div v-else class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <Input 
                                    type="number" 
                                    step="any"
                                    v-model="formResults[param.name]" 
                                    placeholder="Enter numeric value..."
                                    class="w-full h-8 text-xs font-mono pr-12 font-semibold"
                                />
                                <span v-if="param.unit" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-mono text-muted-foreground">
                                    {{ param.unit }}
                                </span>
                            </div>
                        </div>

                        <!-- Panic Threshold Notice -->
                        <div v-if="param.panic_low || param.panic_high" class="text-[10px] text-rose-600/80 dark:text-rose-400/80 font-mono">
                            Panic Thresholds: 
                            <span v-if="param.panic_low">&lt; {{ param.panic_low }}</span>
                            <span v-if="param.panic_low && param.panic_high"> or </span>
                            <span v-if="param.panic_high">&gt; {{ param.panic_high }}</span>
                            {{ param.unit }}
                        </div>
                    </div>
                </div>

                <!-- Technician Remarks -->
                <div class="space-y-1 pt-1">
                    <label class="text-xs font-bold text-foreground">
                        Technician Remarks & Microscopic Observations
                    </label>
                    <textarea 
                        v-model="technicianRemarks"
                        rows="2"
                        placeholder="Optional remarks, morphology notes, or analyzer flags..."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    ></textarea>
                </div>

                <!-- Action Footer -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border/60">
                    <Button 
                        type="button" 
                        variant="subtle" 
                        size="sm"
                        @click="$emit('close')"
                    >
                        Cancel
                    </Button>

                    <Button 
                        type="submit" 
                        variant="default" 
                        size="sm"
                        :disabled="isSubmitting"
                        class="gap-1.5"
                    >
                        <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
                        <CheckCircle2 v-else class="w-3.5 h-3.5" />
                        <span>Sign & Verify Results</span>
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
