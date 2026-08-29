<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Activity, Plus, Save, Loader2, CheckCircle2, AlertTriangle, X, HeartPulse, Scale, ShieldAlert, ShieldCheck, Zap } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
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
    avpu: 'A',
});

const applyNormalPreset = () => {
    form.temperature_c = '36.8';
    form.heart_rate = '72';
    form.systolic_bp = '120';
    form.diastolic_bp = '80';
    form.respiratory_rate = '16';
    form.oxygen_saturation = '98';
    form.avpu = 'A';
};

const setBp = (sys, dia) => {
    form.systolic_bp = sys;
    form.diastolic_bp = dia;
};

// Live BMI Calculation Preview
const computedBmi = computed(() => {
    const w = parseFloat(form.weight_kg);
    const h = parseFloat(form.height_cm) / 100;
    if (w > 0 && h > 0) {
        return (w / (h * h)).toFixed(1);
    }
    return null;
});

// Live MEWS (Modified Early Warning Score) Calculation
const mewsScore = computed(() => {
    const sbp = parseFloat(form.systolic_bp);
    const hr = parseFloat(form.heart_rate);
    const rr = parseFloat(form.respiratory_rate);
    const temp = parseFloat(form.temperature_c);
    const spo2 = parseFloat(form.oxygen_saturation);
    const avpu = form.avpu || 'A';

    if (!sbp && !hr && !rr && !temp && !spo2) return null;

    let score = 0;
    // SBP
    if (sbp > 0) {
        if (sbp <= 70) score += 3;
        else if (sbp <= 80) score += 2;
        else if (sbp <= 100) score += 1;
        else if (sbp <= 199) score += 0;
        else score += 2;
    }
    // HR
    if (hr > 0) {
        if (hr <= 40) score += 2;
        else if (hr <= 50) score += 1;
        else if (hr <= 100) score += 0;
        else if (hr <= 110) score += 1;
        else if (hr <= 129) score += 2;
        else score += 3;
    }
    // RR
    if (rr > 0) {
        if (rr < 9) score += 2;
        else if (rr <= 14) score += 0;
        else if (rr <= 20) score += 1;
        else if (rr <= 29) score += 2;
        else score += 3;
    }
    // Temp
    if (temp > 0) {
        if (temp < 35.0) score += 2;
        else if (temp <= 38.4) score += 0;
        else score += 2;
    }
    // SpO2
    if (spo2 > 0) {
        if (spo2 <= 91) score += 3;
        else if (spo2 <= 93) score += 2;
        else if (spo2 <= 95) score += 1;
        else score += 0;
    }
    // AVPU
    if (avpu === 'V') score += 1;
    else if (avpu === 'P') score += 2;
    else if (avpu === 'U') score += 3;

    let risk = 'LOW';
    let title = 'Physiologically Stable';
    let protocol = ['Continue routine 6–8 hourly ward observation schedule.'];

    if (score >= 6) {
        risk = 'CRITICAL';
        title = 'Critical Clinical Deterioration / Code Yellow';
        protocol = [
            'Immediately notify Ward In-Charge and Medical Officer on duty.',
            'Continuous cardiac and pulse oximetry monitoring (repeat vitals Q15min).',
            'Ensure patent airway, deliver high-flow supplemental oxygen.',
            'Secure wide-bore IV cannula access (16G/18G) and prepare resuscitation trolley.',
        ];
    } else if (score >= 4) {
        risk = 'HIGH';
        title = 'High Deterioration Risk';
        protocol = [
            'Inform attending clinician within 30 minutes.',
            'Increase vital signs monitoring frequency to every 30–60 minutes.',
            'Review fluid balance and recent laboratory investigations.',
        ];
    } else if (score >= 2) {
        risk = 'MEDIUM';
        title = 'Moderate Risk - Increased Surveillance';
        protocol = [
            'Repeat vital signs observation in 2–4 hours.',
            'Verify pain control, hydration status, and baseline trajectory.',
        ];
    }

    return { score, risk, title, protocol };
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
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
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
                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>
            <button @click="errorMessage = ''" class="text-rose-700 hover:text-rose-900">
                <X class="w-3 h-3" />
            </button>
        </div>
        
        <!-- Tablet Bedside Quick Presets Strip -->
        <div class="flex items-center justify-between p-1.5 rounded-lg bg-muted/40 border border-border/60 text-[10px]">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="font-bold text-muted-foreground uppercase tracking-wider text-[9px]">Bedside Quick-Fill:</span>
                <button
                    type="button"
                    @click="applyNormalPreset"
                    class="px-2 py-0.5 rounded bg-card hover:bg-muted border border-border/80 font-bold text-primary shadow-2xs hover:scale-105 transition-transform"
                >
                    ⚡ Normal Adult (36.8°C / 72 bpm / 120/80 / 98%)
                </button>
                <button
                    type="button"
                    @click="setBp('110', '70')"
                    class="px-1.5 py-0.5 rounded bg-card hover:bg-muted border border-border/80 font-mono text-muted-foreground hover:text-foreground"
                >
                    110/70
                </button>
                <button
                    type="button"
                    @click="setBp('130', '85')"
                    class="px-1.5 py-0.5 rounded bg-card hover:bg-muted border border-border/80 font-mono text-muted-foreground hover:text-foreground"
                >
                    130/85
                </button>
                <button
                    type="button"
                    @click="setBp('140', '90')"
                    class="px-1.5 py-0.5 rounded bg-card hover:bg-muted border border-border/80 font-mono text-rose-600 dark:text-rose-400 font-bold"
                >
                    140/90
                </button>
            </div>
            <button
                type="button"
                @click="form.reset()"
                class="text-muted-foreground hover:text-rose-600 font-semibold text-[9.5px] px-1"
            >
                Clear
            </button>
        </div>
        
        <!-- Live MEWS Early Warning Score Banner -->
        <div v-if="mewsScore" class="p-3 rounded-lg border transition shadow-2xs space-y-2" :class="{
            'bg-emerald-500/10 border-emerald-500/30 text-emerald-950 dark:text-emerald-200': mewsScore.risk === 'LOW',
            'bg-amber-500/10 border-amber-500/30 text-amber-950 dark:text-amber-200': mewsScore.risk === 'MEDIUM',
            'bg-orange-500/10 border-orange-500/30 text-orange-950 dark:text-orange-200': mewsScore.risk === 'HIGH',
            'bg-rose-500/15 border-rose-500/40 text-rose-950 dark:text-rose-100': mewsScore.risk === 'CRITICAL',
        }">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <ShieldAlert v-if="mewsScore.risk === 'CRITICAL' || mewsScore.risk === 'HIGH'" class="w-4 h-4 text-rose-600 dark:text-rose-400 animate-pulse" />
                    <ShieldCheck v-else class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    <span class="font-bold text-xs">
                        MEWS Deterioration Index: Score {{ mewsScore.score }} — {{ mewsScore.title }}
                    </span>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider" :class="{
                    'bg-emerald-600 text-white': mewsScore.risk === 'LOW',
                    'bg-amber-600 text-white': mewsScore.risk === 'MEDIUM',
                    'bg-orange-600 text-white': mewsScore.risk === 'HIGH',
                    'bg-rose-600 text-white': mewsScore.risk === 'CRITICAL',
                }">
                    {{ mewsScore.risk }} RISK
                </span>
            </div>

            <!-- Escalation Protocol Guidance -->
            <div class="text-[11px] space-y-1 bg-black/5 dark:bg-white/5 p-2 rounded border border-border/40">
                <div class="font-bold text-[10px] uppercase tracking-wider text-muted-foreground">Clinical Escalation Guidance:</div>
                <ul class="list-disc list-inside space-y-0.5 opacity-90">
                    <li v-for="(step, idx) in mewsScore.protocol" :key="idx">{{ step }}</li>
                </ul>
            </div>
        </div>
        
        <form @submit.prevent="submit" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-2.5">
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
                <label class="block text-[11px] font-semibold text-foreground mb-1">Consciousness (AVPU)</label>
                <Select v-model="form.avpu" class="w-full">
                    <option value="A">A - Alert</option>
                    <option value="V">V - Voice</option>
                    <option value="P">P - Pain</option>
                    <option value="U">U - Unresponsive</option>
                </Select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">Weight (kg)</label>
                <Input v-model="form.weight_kg" type="number" step="0.1" placeholder="70.5" class="h-8 text-xs" />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-foreground mb-1">Height (cm)</label>
                <Input v-model="form.height_cm" type="number" step="0.1" placeholder="175" class="h-8 text-xs" />
            </div>
            <div class="flex items-end sm:col-span-2 lg:col-span-2">
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
