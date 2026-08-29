<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Heart, Save, Loader2, CheckCircle2, AlertTriangle, X, ShieldAlert, Baby } from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import AfyaDatePicker from '@/Components/Afya/AfyaDatePicker.vue';
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
    existingVisits: {
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
    gravida: 1,
    para: 0,
    last_menstrual_period: '',
    estimated_date_of_delivery: '',
    gestational_age_weeks: 24,
    fundal_height_cm: 24,
    fetal_presentation: 'Cephalic',
    fetal_heart_rate_bpm: 140,
    fetal_movement: 'Normal',
    urinary_protein: 0,
    iptp_malaria_dose: 'IPTp-1',
    iron_folate_given: true,
    high_risk_flag: false,
    high_risk_reason: '',
});

const onLmpChange = () => {
    if (!form.last_menstrual_period) return;
    const lmp = new Date(form.last_menstrual_period);
    if (isNaN(lmp.getTime())) return;
    
    // Naegele's rule: +1 year, -3 months, +7 days (approx +280 days)
    const edd = new Date(lmp.getTime() + (280 * 24 * 60 * 60 * 1000));
    form.estimated_date_of_delivery = edd.toISOString().split('T')[0];

    // Gestation weeks
    const today = new Date();
    const diffMs = today - lmp;
    const diffWeeks = Math.floor(diffMs / (7 * 24 * 60 * 60 * 1000));
    if (diffWeeks >= 0 && diffWeeks <= 44) {
        form.gestational_age_weeks = diffWeeks;
        if (diffWeeks >= 20) {
            form.fundal_height_cm = diffWeeks;
        }
    }
};

watch(() => form.last_menstrual_period, onLmpChange);

const submit = () => {
    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        successMessage.value = '✓ ANC Visit drafted.';
        setTimeout(() => { successMessage.value = ''; }, 3500);
        return;
    }

    form.post(route('clinical.anc.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.fetal_presentation = 'Cephalic';
            form.fetal_movement = 'Normal';
            form.iptp_malaria_dose = 'IPTp-1';
            form.iron_folate_given = true;
            form.high_risk_flag = false;
            successMessage.value = '✓ Antenatal visit recorded into MTUHA Book 6 register.';
            setTimeout(() => { successMessage.value = ''; }, 4500);
        },
        onError: (errs) => {
            errorMessage.value = errs.anc || Object.values(errs)[0] || 'Failed to record ANC visit.';
            setTimeout(() => { errorMessage.value = ''; }, 5000);
        },
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-border pb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                <Heart class="w-3.5 h-3.5 text-rose-500" />
                <span>Antenatal Care (ANC / RCH / MTUHA Book 6)</span>
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 border border-rose-500/30 flex items-center gap-1">
                <Baby class="w-3 h-3" />
                <span>Maternal & Fetal Surveillance</span>
            </span>
        </div>

        <div v-if="successMessage" class="p-2 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span>{{ successMessage }}</span>
            </div>
            <button @click="successMessage = ''" class="text-emerald-700 hover:text-emerald-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <div v-if="errorMessage" class="p-2 rounded-md bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>
            <button @click="errorMessage = ''" class="text-rose-700 hover:text-rose-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Gravida *</label>
                    <Input v-model.number="form.gravida" type="number" min="1" max="25" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.gravida" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Para *</label>
                    <Input v-model.number="form.para" type="number" min="0" max="25" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.para" class="mt-1" />
                </div>
                <div>
                    <AfyaDatePicker
                        v-model="form.last_menstrual_period"
                        label="LMP (Last Menstrual Period)"
                        required
                        :max="new Date().toISOString().split('T')[0]"
                        :error="form.errors.last_menstrual_period"
                    />
                </div>
                <div>
                    <AfyaDatePicker
                        v-model="form.estimated_date_of_delivery"
                        label="EDD (Estimated Delivery Date)"
                        required
                        :error="form.errors.estimated_date_of_delivery"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Gestation (Weeks) *</label>
                    <Input v-model.number="form.gestational_age_weeks" type="number" min="1" max="45" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.gestational_age_weeks" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Fundal Height (cm)</label>
                    <Input v-model.number="form.fundal_height_cm" type="number" min="10" max="50" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.fundal_height_cm" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Fetal Presentation</label>
                    <Select v-model="form.fetal_presentation" class="w-full">
                        <option value="Cephalic">Cephalic (Vertex)</option>
                        <option value="Breech">Breech</option>
                        <option value="Transverse">Transverse / Oblique</option>
                    </Select>
                    <InputError :message="form.errors.fetal_presentation" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Fetal Heart Rate (bpm)</label>
                    <Input v-model.number="form.fetal_heart_rate_bpm" type="number" min="80" max="220" placeholder="120-160" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.fetal_heart_rate_bpm" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Fetal Movement</label>
                    <Select v-model="form.fetal_movement" class="w-full">
                        <option value="Normal">Normal (> 10 kicks/12h)</option>
                        <option value="Reduced">Reduced / Decreased</option>
                        <option value="Absent">Absent</option>
                    </Select>
                    <InputError :message="form.errors.fetal_movement" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Urine Protein (Dipstick)</label>
                    <Select v-model.number="form.urinary_protein" class="w-full">
                        <option :value="0">Nil / Negative</option>
                        <option :value="1">+1 (30 mg/dL)</option>
                        <option :value="2">+2 (100 mg/dL)</option>
                        <option :value="3">+3 (300 mg/dL)</option>
                    </Select>
                    <InputError :message="form.errors.urinary_protein" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">IPTp Malaria Dose</label>
                    <Select v-model="form.iptp_malaria_dose" class="w-full">
                        <option value="None">None / Not Due</option>
                        <option value="IPTp-1">IPTp-1 (SP Dose 1)</option>
                        <option value="IPTp-2">IPTp-2 (SP Dose 2)</option>
                        <option value="IPTp-3">IPTp-3 (SP Dose 3)</option>
                        <option value="IPTp-4+">IPTp-4+ (SP Dose 4+)</option>
                    </Select>
                    <InputError :message="form.errors.iptp_malaria_dose" class="mt-1" />
                </div>
                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-foreground">
                        <input type="checkbox" v-model="form.iron_folate_given" class="rounded border-border text-primary focus:ring-primary" />
                        <span>FEFO (Iron & Folate Given)</span>
                    </label>
                </div>
            </div>

            <!-- High Risk Pregnancy Warning Block -->
            <div class="p-3 rounded-lg border" :class="form.high_risk_flag ? 'bg-rose-500/10 border-rose-500/40' : 'bg-muted/10 border-border/60'">
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold" :class="form.high_risk_flag ? 'text-rose-700 dark:text-rose-300' : 'text-foreground'">
                        <input type="checkbox" v-model="form.high_risk_flag" class="rounded border-border text-rose-600 focus:ring-rose-500" />
                        <span class="flex items-center gap-1">
                            <ShieldAlert class="w-3.5 h-3.5" />
                            <span>Flag as High-Risk Pregnancy</span>
                        </span>
                    </label>
                    <div v-if="form.high_risk_flag" class="flex-1">
                        <Input v-model="form.high_risk_reason" placeholder="State risk reason (e.g. Previous C-Section, Pre-eclampsia, Severe Anemia, Bleeding)..." class="h-8 text-xs border-rose-300 bg-background" />
                    </div>
                </div>
                <InputError :message="form.errors.high_risk_reason" class="mt-1" />
            </div>

            <div class="flex justify-end pt-1">
                <Button v-if="can.recordAncVisit" type="submit" variant="default" size="sm" :disabled="form.processing" class="h-8 px-4 gap-1 shadow-2xs font-semibold">
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                    <Save v-else class="w-3.5 h-3.5" />
                    <span>{{ form.processing ? 'Recording...' : 'Record ANC Visit' }}</span>
                </Button>
            </div>
        </form>

        <!-- Existing ANC Visits History -->
        <div v-if="existingVisits && existingVisits.length > 0" class="pt-3 border-t border-border/60">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1.5">
                <Heart class="w-3 h-3 text-rose-500" />
                <span>Antenatal Visits Timeline ({{ existingVisits.length }})</span>
            </h4>
            <div class="border border-border/60 rounded-lg overflow-hidden shadow-2xs">
                <Table class="text-xs">
                    <TableHeader>
                        <TableRow class="h-7 text-[10px] uppercase font-bold bg-muted/20">
                            <TableHead class="py-1 px-3">Date</TableHead>
                            <TableHead class="py-1 px-3">G/P</TableHead>
                            <TableHead class="py-1 px-3">Gestation</TableHead>
                            <TableHead class="py-1 px-3">Fundal Ht</TableHead>
                            <TableHead class="py-1 px-3">FHR</TableHead>
                            <TableHead class="py-1 px-3">Presentation</TableHead>
                            <TableHead class="py-1 px-3">Risk Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="v in existingVisits" :key="v.id" class="h-8 border-b border-border/30">
                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">{{ formatDate(v.visit_date || v.created_at) }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-bold">G{{ v.gravida }} P{{ v.para }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-semibold">{{ v.gestational_age_weeks }} wks</TableCell>
                            <TableCell class="py-1 px-3 font-mono">{{ v.fundal_height_cm ? `${v.fundal_height_cm} cm` : '—' }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-bold text-foreground">{{ v.fetal_heart_rate_bpm ? `${v.fetal_heart_rate_bpm} bpm` : '—' }}</TableCell>
                            <TableCell class="py-1 px-3 text-muted-foreground">{{ v.fetal_presentation }}</TableCell>
                            <TableCell class="py-1 px-3">
                                <span v-if="v.high_risk_flag" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 border border-rose-500/30">
                                    HIGH RISK ({{ v.high_risk_reason || 'Alert' }})
                                </span>
                                <span v-else class="text-emerald-600 font-semibold text-[10px]">Normal</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
