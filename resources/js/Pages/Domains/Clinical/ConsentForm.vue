<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FileSignature, Save, Loader2, CheckCircle2, AlertTriangle, X, ShieldCheck } from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
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
    existingConsents: {
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
    consent_type: 'Surgical',
    procedure_title: '',
    explanation_of_risks: '',
    alternative_treatments: '',
    signatory_type: 'Patient',
    signatory_name: '',
    witness_name: '',
    interpreter_used: false,
    language_used: '',
});

const submit = () => {
    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        successMessage.value = '✓ Consent drafted.';
        setTimeout(() => { successMessage.value = ''; }, 3500);
        return;
    }

    form.post(route('clinical.consent.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.consent_type = 'Surgical';
            form.signatory_type = 'Patient';
            form.interpreter_used = false;
            successMessage.value = '✓ Informed consent recorded and sealed.';
            setTimeout(() => { successMessage.value = ''; }, 4500);
        },
        onError: (errs) => {
            errorMessage.value = errs.consent || Object.values(errs)[0] || 'Failed to record consent.';
            setTimeout(() => { errorMessage.value = ''; }, 5000);
        },
    });
};

const formatDate = (dateStr) => {
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
                <FileSignature class="w-3.5 h-3.5 text-primary" />
                <span>Statutory Informed Consent & Procedure Authorization</span>
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary/10 text-primary border border-primary/30 flex items-center gap-1">
                <ShieldCheck class="w-3 h-3" />
                <span>Legal & Clinical Governance Active</span>
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
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Consent Type *</label>
                    <Select v-model="form.consent_type" class="w-full">
                        <option value="Surgical">Surgical Procedure</option>
                        <option value="Anesthesia">Anesthesia Administration</option>
                        <option value="BloodTransfusion">Blood & Blood Products Transfusion</option>
                        <option value="InvasiveProcedure">Invasive Diagnostic / Interventional</option>
                        <option value="GeneralTreatment">General Treatment & Admission</option>
                    </Select>
                    <InputError :message="form.errors.consent_type" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Procedure Title *</label>
                    <Input v-model="form.procedure_title" placeholder="e.g. Emergency Appendectomy" class="h-8 text-xs" />
                    <InputError :message="form.errors.procedure_title" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Explanation of Clinical Risks *</label>
                    <textarea v-model="form.explanation_of_risks" rows="2" placeholder="Document risks discussed (infection, bleeding, anesthesia risks)..." class="w-full text-xs rounded border border-border bg-card p-2 text-foreground focus:ring-1 focus:ring-primary"></textarea>
                    <InputError :message="form.errors.explanation_of_risks" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Alternative Treatments Discussed</label>
                    <textarea v-model="form.alternative_treatments" rows="2" placeholder="Conservative management options, medical therapies..." class="w-full text-xs rounded border border-border bg-card p-2 text-foreground focus:ring-1 focus:ring-primary"></textarea>
                    <InputError :message="form.errors.alternative_treatments" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Signatory Type *</label>
                    <Select v-model="form.signatory_type" class="w-full">
                        <option value="Patient">Patient Self</option>
                        <option value="NextOfKin">Next of Kin</option>
                        <option value="Guardian">Legal Guardian</option>
                        <option value="MedicalPowerOfAttorney">Medical Power of Attorney</option>
                    </Select>
                    <InputError :message="form.errors.signatory_type" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Signatory Full Name *</label>
                    <Input v-model="form.signatory_name" placeholder="Full Name as signed" class="h-8 text-xs" />
                    <InputError :message="form.errors.signatory_name" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Witness Name</label>
                    <Input v-model="form.witness_name" placeholder="Nurse / Clinical Witness" class="h-8 text-xs" />
                    <InputError :message="form.errors.witness_name" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center pt-1">
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-foreground">
                        <input type="checkbox" v-model="form.interpreter_used" class="rounded border-border text-primary focus:ring-primary" />
                        <span>Interpreter / Translation Used</span>
                    </label>
                    <div v-if="form.interpreter_used" class="flex-1">
                        <Input v-model="form.language_used" placeholder="Language (e.g. Swahili, Maasai)" class="h-8 text-xs" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <Button v-if="can.recordConsent" type="submit" variant="default" size="sm" :disabled="form.processing" class="h-8 px-4 gap-1 shadow-2xs font-semibold">
                        <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                        <Save v-else class="w-3.5 h-3.5" />
                        <span>{{ form.processing ? 'Recording...' : 'Record & Seal Consent' }}</span>
                    </Button>
                </div>
            </div>
        </form>

        <!-- Existing Consents History -->
        <div v-if="existingConsents && existingConsents.length > 0" class="pt-3 border-t border-border/60">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1.5">
                <FileSignature class="w-3 h-3 text-primary" />
                <span>Documented Consents History ({{ existingConsents.length }})</span>
            </h4>
            <div class="border border-border/60 rounded-lg overflow-hidden shadow-2xs">
                <Table class="text-xs">
                    <TableHeader>
                        <TableRow class="h-7 text-[10px] uppercase font-bold bg-muted/20">
                            <TableHead class="py-1 px-3">Date Signed</TableHead>
                            <TableHead class="py-1 px-3">Consent Type</TableHead>
                            <TableHead class="py-1 px-3">Procedure Title</TableHead>
                            <TableHead class="py-1 px-3">Signatory</TableHead>
                            <TableHead class="py-1 px-3">Witness</TableHead>
                            <TableHead class="py-1 px-3">Clinician</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="c in existingConsents" :key="c.id" class="h-8 border-b border-border/30">
                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">{{ formatDate(c.signed_at || c.created_at) }}</TableCell>
                            <TableCell class="py-1 px-3 font-semibold">{{ c.consent_type }}</TableCell>
                            <TableCell class="py-1 px-3 font-bold text-foreground">{{ c.procedure_title }}</TableCell>
                            <TableCell class="py-1 px-3">{{ c.signatory_name }} ({{ c.signatory_type }})</TableCell>
                            <TableCell class="py-1 px-3">{{ c.witness_name || '—' }}</TableCell>
                            <TableCell class="py-1 px-3 text-muted-foreground">{{ c.clinician?.first_name ? `${c.clinician.first_name} ${c.clinician.last_name}` : 'Staff Doctor' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
