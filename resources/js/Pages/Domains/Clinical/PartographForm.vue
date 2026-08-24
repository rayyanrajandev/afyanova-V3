<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Activity, Save, Loader2, CheckCircle2, AlertTriangle, X, ShieldAlert, Clock } from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import InputError from '@/Components/InputError.vue';
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
    existingEntries: {
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
    cervical_dilation_cm: 4,
    fetal_heart_rate_bpm: 140,
    liquor_status: 'Clear',
    fetal_head_descent: '3/5',
    uterine_contractions_per_10min: 3,
    contraction_duration_seconds: 35,
    maternal_systolic_bp: 120,
    maternal_diastolic_bp: 80,
    maternal_pulse_bpm: 78,
    alert_line_crossed: false,
    action_line_crossed: false,
    midwife_remarks: '',
});

const submit = () => {
    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        successMessage.value = '✓ Partograph entry drafted.';
        setTimeout(() => { successMessage.value = ''; }, 3500);
        return;
    }

    form.post(route('clinical.partograph.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.cervical_dilation_cm = 4;
            form.fetal_heart_rate_bpm = 140;
            form.liquor_status = 'Clear';
            form.fetal_head_descent = '3/5';
            form.alert_line_crossed = false;
            form.action_line_crossed = false;
            successMessage.value = '✓ WHO Partograph plot logged successfully.';
            setTimeout(() => { successMessage.value = ''; }, 4500);
        },
        onError: (errs) => {
            errorMessage.value = errs.partograph || Object.values(errs)[0] || 'Failed to record partograph entry.';
            setTimeout(() => { errorMessage.value = ''; }, 5000);
        },
    });
};

const formatTime = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' · ' + d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-border pb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                <Activity class="w-3.5 h-3.5 text-primary" />
                <span>WHO Intrapartum Partograph Surveillance (Labour Ward)</span>
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 border border-amber-500/30 flex items-center gap-1">
                <Clock class="w-3 h-3" />
                <span>Active Labour Monitoring</span>
            </span>
        </div>

        <div v-if="successMessage" class="p-2 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                <span>{{ successMessage }}</span>
            </div>
            <button @click="successMessage = ''" class="text-emerald-700 hover:text-emerald-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <div v-if="errorMessage" class="p-2 rounded-md bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 flex-shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>
            <button @click="errorMessage = ''" class="text-rose-700 hover:text-rose-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Cervical Dilation (cm) *</label>
                    <Input v-model.number="form.cervical_dilation_cm" type="number" min="0" max="10" step="0.5" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.cervical_dilation_cm" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Fetal Heart Rate (bpm) *</label>
                    <Input v-model.number="form.fetal_heart_rate_bpm" type="number" min="60" max="220" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.fetal_heart_rate_bpm" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Liquor Status</label>
                    <select v-model="form.liquor_status" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                        <option value="Intact">I - Intact Membranes</option>
                        <option value="Clear">C - Clear Liquor</option>
                        <option value="Meconium">M - Meconium Stained</option>
                        <option value="Blood">B - Blood Stained</option>
                        <option value="Absent">A - Absent Liquor</option>
                    </select>
                    <InputError :message="form.errors.liquor_status" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Head Descent (Fifth)</label>
                    <select v-model="form.fetal_head_descent" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                        <option value="5/5">5/5 - High & Free</option>
                        <option value="4/5">4/5 - Above Brim</option>
                        <option value="3/5">3/5 - Engaged</option>
                        <option value="2/5">2/5 - Mid-Pelvis</option>
                        <option value="1/5">1/5 - Deep</option>
                        <option value="0/5">0/5 - On Perineum</option>
                    </select>
                    <InputError :message="form.errors.fetal_head_descent" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Contractions / 10 min</label>
                    <Input v-model.number="form.uterine_contractions_per_10min" type="number" min="0" max="10" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.uterine_contractions_per_10min" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Duration (Secs)</label>
                    <Input v-model.number="form.contraction_duration_seconds" type="number" min="0" max="120" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.contraction_duration_seconds" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Maternal Systolic</label>
                    <Input v-model.number="form.maternal_systolic_bp" type="number" min="60" max="250" placeholder="120" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.maternal_systolic_bp" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Maternal Diastolic</label>
                    <Input v-model.number="form.maternal_diastolic_bp" type="number" min="30" max="150" placeholder="80" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.maternal_diastolic_bp" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Maternal Pulse (bpm)</label>
                    <Input v-model.number="form.maternal_pulse_bpm" type="number" min="40" max="200" placeholder="78" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.maternal_pulse_bpm" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-amber-600 dark:text-amber-400">
                        <input type="checkbox" v-model="form.alert_line_crossed" class="rounded border-border text-amber-600 focus:ring-amber-500" />
                        <span>Alert Line Crossed</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-rose-600 dark:text-rose-400">
                        <input type="checkbox" v-model="form.action_line_crossed" class="rounded border-border text-rose-600 focus:ring-rose-500" />
                        <span>Action Line Crossed (EMOC)</span>
                    </label>
                </div>
                <div>
                    <Input v-model="form.midwife_remarks" placeholder="Midwife observations, oxytocin dosage, analgesia..." class="h-8 text-xs" />
                    <InputError :message="form.errors.midwife_remarks" class="mt-1" />
                </div>
            </div>

            <div class="flex justify-end pt-1">
                <Button v-if="can.recordPartograph" type="submit" variant="default" size="sm" :disabled="form.processing" class="h-8 px-4 gap-1 shadow-2xs font-semibold">
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                    <Save v-else class="w-3.5 h-3.5" />
                    <span>{{ form.processing ? 'Plotting...' : 'Plot Partograph Entry' }}</span>
                </Button>
            </div>
        </form>

        <!-- Existing Partograph Timeline -->
        <div v-if="existingEntries && existingEntries.length > 0" class="pt-3 border-t border-border/60">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1.5">
                <Activity class="w-3 h-3 text-primary" />
                <span>Intrapartum Observations Timeline ({{ existingEntries.length }})</span>
            </h4>
            <div class="border border-border/60 rounded-lg overflow-hidden shadow-2xs">
                <Table class="text-xs">
                    <TableHeader>
                        <TableRow class="h-7 text-[10px] uppercase font-bold bg-muted/20">
                            <TableHead class="py-1 px-3">Time Recorded</TableHead>
                            <TableHead class="py-1 px-3">Cervix</TableHead>
                            <TableHead class="py-1 px-3">FHR</TableHead>
                            <TableHead class="py-1 px-3">Liquor</TableHead>
                            <TableHead class="py-1 px-3">Descent</TableHead>
                            <TableHead class="py-1 px-3">Contractions</TableHead>
                            <TableHead class="py-1 px-3">Maternal BP / Pulse</TableHead>
                            <TableHead class="py-1 px-3">Alert / Action</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="entry in existingEntries" :key="entry.id" class="h-8 border-b border-border/30">
                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">{{ formatTime(entry.entry_time || entry.created_at) }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-bold text-foreground">{{ entry.cervical_dilation_cm }} cm</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-bold" :class="entry.fetal_heart_rate_bpm < 110 || entry.fetal_heart_rate_bpm > 160 ? 'text-rose-600 font-extrabold' : 'text-foreground'">
                                {{ entry.fetal_heart_rate_bpm }} bpm
                            </TableCell>
                            <TableCell class="py-1 px-3 text-[11px]">{{ entry.liquor_status }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono text-[10px]">{{ entry.fetal_head_descent }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono text-[10px]">{{ entry.uterine_contractions_per_10min }}/10m ({{ entry.contraction_duration_seconds }}s)</TableCell>
                            <TableCell class="py-1 px-3 font-mono text-[10px]">{{ entry.maternal_systolic_bp }}/{{ entry.maternal_diastolic_bp }} · {{ entry.maternal_pulse_bpm }}bpm</TableCell>
                            <TableCell class="py-1 px-3">
                                <span v-if="entry.action_line_crossed" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 border border-rose-500/30">
                                    ACTION LINE
                                </span>
                                <span v-else-if="entry.alert_line_crossed" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 border border-amber-500/30">
                                    ALERT LINE
                                </span>
                                <span v-else class="text-emerald-600 text-[10px] font-semibold">Normal</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
