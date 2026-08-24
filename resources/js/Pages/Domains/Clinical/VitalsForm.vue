<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Activity, Plus, Save, Loader2, CheckCircle2, AlertTriangle, X, HeartPulse, Scale } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';

const props = defineProps({
    encounterId: {
        type: String,
        default: 'demo',
    },
    existingVitals: {
        type: Array,
        default: () => [],
    },
    vitals: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const successMessage = ref('');
const errorMessage = ref('');

const form = useForm({
    temperature_c: '',
    heart_rate: '',
    systolic_bp: '',
    diastolic_bp: '',
    respiratory_rate: '',
    oxygen_saturation: '',
    weight_kg: '',
    height_cm: '',
});

// Live BMI Calculation Preview
const computedBmi = computed(() => {
    const w = parseFloat(form.weight_kg);
    const h = parseFloat(form.height_cm) / 100;
    if (w > 0 && h > 0) {
        return (w / (h * h)).toFixed(1);
    }
    return null;
});

const submit = () => {
    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        successMessage.value = '✓ Vitals drafted.';
        setTimeout(() => { successMessage.value = ''; }, 3500);
        return;
    }

    form.post(route('clinical.vitals.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            successMessage.value = '✓ Clinical vitals successfully recorded into the patient medical record.';
            setTimeout(() => { successMessage.value = ''; }, 4500);
        },
        onError: (errs) => {
            errorMessage.value = errs.vitals || Object.values(errs)[0] || 'Failed to save clinical vitals.';
            setTimeout(() => { errorMessage.value = ''; }, 5000);
        }
    });
};

const displayVitals = () => {
    return props.existingVitals?.length > 0 ? props.existingVitals : props.vitals;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' · ' + d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between border-b border-border pb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                <Activity class="w-3.5 h-3.5 text-primary" />
                <span>Record Clinical Vitals & Triage</span>
            </h3>
            <span v-if="computedBmi" class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary/10 text-primary border border-primary/30 flex items-center gap-1">
                <Scale class="w-3 h-3" />
                <span>Live BMI: {{ computedBmi }} kg/m²</span>
            </span>
        </div>

        <!-- Flash Messages -->
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
        
        <form @submit.prevent="submit" class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">Temp (°C)</label>
                <Input v-model="form.temperature_c" type="number" step="0.1" placeholder="36.8" class="h-8 text-xs" />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">Heart Rate (bpm)</label>
                <Input v-model="form.heart_rate" type="number" placeholder="72" class="h-8 text-xs" />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">BP (Sys / Dia mmHg)</label>
                <div class="flex items-center space-x-1">
                    <Input v-model="form.systolic_bp" type="number" placeholder="120" class="h-8 text-xs" />
                    <span class="text-muted-foreground text-xs">/</span>
                    <Input v-model="form.diastolic_bp" type="number" placeholder="80" class="h-8 text-xs" />
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">SpO2 (%)</label>
                <Input v-model="form.oxygen_saturation" type="number" placeholder="98" class="h-8 text-xs" />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">Resp Rate (/min)</label>
                <Input v-model="form.respiratory_rate" type="number" placeholder="16" class="h-8 text-xs" />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">Weight (kg)</label>
                <Input v-model="form.weight_kg" type="number" step="0.1" placeholder="70.5" class="h-8 text-xs" />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">Height (cm)</label>
                <Input v-model="form.height_cm" type="number" step="0.1" placeholder="175" class="h-8 text-xs" />
            </div>
            <div class="flex items-end">
                <Button v-if="can.recordVitals" type="submit" variant="default" size="sm" :disabled="form.processing" class="w-full h-8 justify-center gap-1 shadow-2xs">
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                    <Save v-else class="w-3.5 h-3.5" />
                    <span>{{ form.processing ? 'Saving...' : 'Save Vitals' }}</span>
                </Button>
            </div>
        </form>

        <div v-if="displayVitals() && displayVitals().length > 0" class="pt-3 border-t border-border/60">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1.5">
                <HeartPulse class="w-3 h-3" />
                <span>Recorded Vitals History ({{ displayVitals().length }})</span>
            </h4>
            <div class="border border-border/60 rounded-lg overflow-hidden shadow-2xs">
                <Table class="text-xs">
                    <TableHeader>
                        <TableRow class="h-7 text-[10px] uppercase font-bold bg-muted/20">
                            <TableHead class="py-1 px-3">Recorded Time</TableHead>
                            <TableHead class="py-1 px-3">Temp</TableHead>
                            <TableHead class="py-1 px-3">HR</TableHead>
                            <TableHead class="py-1 px-3">Blood Pressure</TableHead>
                            <TableHead class="py-1 px-3">SpO2</TableHead>
                            <TableHead class="py-1 px-3">Weight</TableHead>
                            <TableHead class="py-1 px-3">BMI</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="vital in displayVitals()" :key="vital.id" class="h-8 border-b border-border/30">
                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">{{ formatDate(vital.recorded_at || vital.created_at) }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono">{{ vital.temperature_c ? `${vital.temperature_c}°C` : '—' }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono">{{ vital.heart_rate ? `${vital.heart_rate} bpm` : '—' }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-bold">{{ vital.systolic_bp && vital.diastolic_bp ? `${vital.systolic_bp}/${vital.diastolic_bp} mmHg` : '—' }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono">{{ vital.oxygen_saturation ? `${vital.oxygen_saturation}%` : '—' }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono">{{ vital.weight_kg ? `${vital.weight_kg} kg` : '—' }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-semibold text-primary">{{ vital.bmi ? `${vital.bmi}` : '—' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
