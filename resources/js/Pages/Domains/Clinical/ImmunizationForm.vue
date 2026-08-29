<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Syringe, Save, Loader2, CheckCircle2, AlertTriangle, X, ShieldAlert } from 'lucide-vue-next';
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
    existingImmunizations: {
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

const standardVaccines = [
    { code: 'BCG', name: 'Bacillus Calmette–Guérin (Tuberculosis)', route: 'Intradermal', defaultSite: 'Left Deltoid' },
    { code: 'OPV-0', name: 'Oral Polio Vaccine (Birth Dose)', route: 'Oral', defaultSite: 'Mouth' },
    { code: 'OPV-1', name: 'Oral Polio Vaccine 1', route: 'Oral', defaultSite: 'Mouth' },
    { code: 'OPV-2', name: 'Oral Polio Vaccine 2', route: 'Oral', defaultSite: 'Mouth' },
    { code: 'OPV-3', name: 'Oral Polio Vaccine 3', route: 'Oral', defaultSite: 'Mouth' },
    { code: 'IPV', name: 'Inactivated Polio Vaccine', route: 'Intramuscular', defaultSite: 'Right Anterolateral Thigh' },
    { code: 'PENTA-1', name: 'Pentavalent (DTP-HepB-Hib) 1', route: 'Intramuscular', defaultSite: 'Left Anterolateral Thigh' },
    { code: 'PENTA-2', name: 'Pentavalent (DTP-HepB-Hib) 2', route: 'Intramuscular', defaultSite: 'Left Anterolateral Thigh' },
    { code: 'PENTA-3', name: 'Pentavalent (DTP-HepB-Hib) 3', route: 'Intramuscular', defaultSite: 'Left Anterolateral Thigh' },
    { code: 'PCV-1', name: 'Pneumococcal Conjugate Vaccine 1', route: 'Intramuscular', defaultSite: 'Right Anterolateral Thigh' },
    { code: 'PCV-2', name: 'Pneumococcal Conjugate Vaccine 2', route: 'Intramuscular', defaultSite: 'Right Anterolateral Thigh' },
    { code: 'PCV-3', name: 'Pneumococcal Conjugate Vaccine 3', route: 'Intramuscular', defaultSite: 'Right Anterolateral Thigh' },
    { code: 'ROTA-1', name: 'Rotavirus Vaccine 1', route: 'Oral', defaultSite: 'Mouth' },
    { code: 'ROTA-2', name: 'Rotavirus Vaccine 2', route: 'Oral', defaultSite: 'Mouth' },
    { code: 'MR-1', name: 'Measles-Rubella Vaccine 1', route: 'Subcutaneous', defaultSite: 'Left Upper Arm' },
    { code: 'MR-2', name: 'Measles-Rubella Vaccine 2', route: 'Subcutaneous', defaultSite: 'Left Upper Arm' },
    { code: 'HPV-1', name: 'Human Papillomavirus 1', route: 'Intramuscular', defaultSite: 'Left Deltoid' },
    { code: 'HPV-2', name: 'Human Papillomavirus 2', route: 'Intramuscular', defaultSite: 'Left Deltoid' },
    { code: 'TT', name: 'Tetanus Toxoid (Adult/Maternal)', route: 'Intramuscular', defaultSite: 'Left Deltoid' },
    { code: 'COVID-19', name: 'COVID-19 Vaccine', route: 'Intramuscular', defaultSite: 'Left Deltoid' },
    { code: 'RABIES', name: 'Anti-Rabies Post-Exposure', route: 'Intramuscular', defaultSite: 'Left Deltoid' },
    { code: 'HEPB-ADULT', name: 'Hepatitis B Recombinant', route: 'Intramuscular', defaultSite: 'Left Deltoid' },
    { code: 'OTHER', name: 'Other Specialty Vaccine', route: 'Intramuscular', defaultSite: 'Deltoid' },
];

const form = useForm({
    vaccine_code: 'BCG',
    vaccine_name: 'Bacillus Calmette–Guérin (Tuberculosis)',
    dose_number: 1,
    batch_number: '',
    expiration_date: '',
    administration_site: 'Left Deltoid',
    route: 'Intradermal',
    adverse_reaction_notes: '',
    next_due_date: '',
});

const onVaccineSelect = (e) => {
    const selected = standardVaccines.find(v => v.code === e.target.value);
    if (selected) {
        form.vaccine_name = selected.name;
        form.route = selected.route;
        form.administration_site = selected.defaultSite;
    }
};

const submit = () => {
    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        successMessage.value = '✓ Immunization drafted.';
        setTimeout(() => { successMessage.value = ''; }, 3500);
        return;
    }

    form.post(route('clinical.immunization.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.vaccine_code = 'BCG';
            form.vaccine_name = 'Bacillus Calmette–Guérin (Tuberculosis)';
            form.dose_number = 1;
            form.route = 'Intradermal';
            form.administration_site = 'Left Deltoid';
            successMessage.value = '✓ Vaccine administration logged into national EPI register.';
            setTimeout(() => { successMessage.value = ''; }, 4500);
        },
        onError: (errs) => {
            errorMessage.value = errs.immunization || Object.values(errs)[0] || 'Failed to record immunization.';
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
                <Syringe class="w-3.5 h-3.5 text-primary" />
                <span>Expanded Programme on Immunization (EPI & Adult Vaccines)</span>
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 border border-emerald-500/30">
                MoHCDGEC EPI Standard
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
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Standard Vaccine Schedule *</label>
                    <Select v-model="form.vaccine_code" @change="onVaccineSelect" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                        <option v-for="v in standardVaccines" :key="v.code" :value="v.code">{{ v.code }} - {{ v.name }}</option>
                    </Select>
                    <InputError :message="form.errors.vaccine_code" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Vaccine Name / Antigens *</label>
                    <Input v-model="form.vaccine_name" placeholder="Vaccine Name" class="h-8 text-xs" />
                    <InputError :message="form.errors.vaccine_name" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Dose Sequence Number *</label>
                    <Input v-model.number="form.dose_number" type="number" min="1" max="10" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.dose_number" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Batch / Lot Number *</label>
                    <Input v-model="form.batch_number" placeholder="e.g. BATCH-2026-X" class="h-8 text-xs font-mono" />
                    <InputError :message="form.errors.batch_number" class="mt-1" />
                </div>
                <div>
                    <AfyaDatePicker
                        v-model="form.expiration_date"
                        label="Vial Expiration Date"
                        required
                        :min="new Date().toISOString().split('T')[0]"
                        :error="form.errors.expiration_date"
                    />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Route *</label>
                    <Select v-model="form.route" class="w-full h-8.5 text-xs rounded border border-border bg-card px-2">
                        <option value="Intradermal">Intradermal (ID)</option>
                        <option value="Intramuscular">Intramuscular (IM)</option>
                        <option value="Subcutaneous">Subcutaneous (SC)</option>
                        <option value="Oral">Oral (PO)</option>
                    </Select>
                    <InputError :message="form.errors.route" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Anatomical Site *</label>
                    <Input v-model="form.administration_site" placeholder="e.g. Left Deltoid" class="h-8 text-xs" />
                    <InputError :message="form.errors.administration_site" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Adverse Reactions / AEFI Notes</label>
                    <Input v-model="form.adverse_reaction_notes" placeholder="e.g. Immediate observation 15 min nil acute reactions" class="h-8 text-xs" />
                    <InputError :message="form.errors.adverse_reaction_notes" class="mt-1" />
                </div>
                <div>
                    <AfyaDatePicker
                        v-model="form.next_due_date"
                        label="Next Follow-Up Due Date"
                        :min="new Date().toISOString().split('T')[0]"
                        :error="form.errors.next_due_date"
                    />
                </div>
            </div>

            <div class="flex justify-end pt-1">
                <Button v-if="can.administerImmunization" type="submit" variant="default" size="sm" :disabled="form.processing" class="h-8 px-4 gap-1 shadow-2xs font-semibold">
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                    <Save v-else class="w-3.5 h-3.5" />
                    <span>{{ form.processing ? 'Recording...' : 'Record Immunization' }}</span>
                </Button>
            </div>
        </form>

        <!-- Existing Immunizations History -->
        <div v-if="existingImmunizations && existingImmunizations.length > 0" class="pt-3 border-t border-border/60">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1.5">
                <Syringe class="w-3 h-3 text-primary" />
                <span>Immunization Passport History ({{ existingImmunizations.length }})</span>
            </h4>
            <div class="border border-border/60 rounded-lg overflow-hidden shadow-2xs">
                <Table class="text-xs">
                    <TableHeader>
                        <TableRow class="h-7 text-[10px] uppercase font-bold bg-muted/20">
                            <TableHead class="py-1 px-3">Date Given</TableHead>
                            <TableHead class="py-1 px-3">Vaccine / Antigen</TableHead>
                            <TableHead class="py-1 px-3">Dose #</TableHead>
                            <TableHead class="py-1 px-3">Batch #</TableHead>
                            <TableHead class="py-1 px-3">Route / Site</TableHead>
                            <TableHead class="py-1 px-3">Next Due</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="im in existingImmunizations" :key="im.id" class="h-8 border-b border-border/30">
                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">{{ formatDate(im.administered_at || im.created_at) }}</TableCell>
                            <TableCell class="py-1 px-3 font-bold text-foreground">
                                <span class="font-mono text-primary font-bold mr-1">{{ im.vaccine_code }}</span>
                                <span class="text-muted-foreground text-[11px]">({{ im.vaccine_name }})</span>
                            </TableCell>
                            <TableCell class="py-1 px-3 font-mono font-bold">Dose {{ im.dose_number }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono text-[10px]">{{ im.batch_number || '—' }}</TableCell>
                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">{{ im.route }} · {{ im.administration_site }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono text-[10px] text-primary font-semibold">{{ formatDate(im.next_due_date) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
