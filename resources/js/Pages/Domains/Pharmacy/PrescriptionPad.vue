<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { 
    Pill, 
    Send, 
    AlertTriangle, 
    ShieldAlert, 
    X, 
    Loader2, 
    CheckSquare,
    CheckCircle2,
    Clock,
    Receipt,
    Trash2,
    Info
} from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import InputError from '@/Components/InputError.vue';
import AfyaClinicalAlert from '@/Components/Afya/AfyaClinicalAlert.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import Modal from '@/Components/Modal.vue';
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
    patientId: {
        type: String,
        default: null,
    },
    patient: {
        type: Object,
        default: () => null,
    },
    allergies: {
        type: Array,
        default: () => [],
    },
    formularies: {
        type: Array,
        default: () => [],
    },
    existingPrescriptions: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    medication_id: '',
    dosage: '',
    frequency: 'TID',
    duration_days: '5',
    route: 'PO',
    quantity: '15',
    instructions: '',
    allergy_override_reason: '',
});

// Allergy Modal State
const showAllergyModal = ref(false);
const matchedAllergy = ref(null);
const overrideConfirmed = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const selectedMedication = computed(() => {
    return props.formularies.find(m => m.id === form.medication_id) || null;
});

// List of already prescribed medications in this encounter
const activePrescribedMedicationIds = computed(() => {
    return new Set(props.existingPrescriptions.map(p => p.medication_id));
});

const isMedicationAlreadyPrescribed = (medId) => {
    return activePrescribedMedicationIds.value.has(medId);
};

// Auto-calculate Total Quantity based on Frequency and Duration
const calculateQuantity = () => {
    const duration = parseInt(form.duration_days) || 0;
    let multiplier;

    switch (form.frequency) {
        case 'OD': multiplier = 1; break;
        case 'BD': multiplier = 2; break;
        case 'TID': multiplier = 3; break;
        case 'QID': multiplier = 4; break;
        case 'Q4H': multiplier = 6; break;
        case 'PRN': multiplier = 1; break;
        case 'STAT': multiplier = 1; break;
        default: multiplier = 1; break;
    }

    if (duration > 0 && multiplier > 0) {
        form.quantity = String(duration * multiplier);
    }
};

watch(() => form.frequency, () => calculateQuantity());
watch(() => form.duration_days, () => calculateQuantity());

// Cross-reactivity & allergy matcher
const checkAllergyContraindication = () => {
    if (!selectedMedication.value) return null;
    const patientAllergies = props.allergies.length > 0 ? props.allergies : (props.patient?.allergies || []);
    if (patientAllergies.length === 0) return null;

    const drugName = (selectedMedication.value.generic_name + ' ' + (selectedMedication.value.brand_name || '')).toLowerCase();

    for (const alg of patientAllergies) {
        const allergen = (alg.allergen || '').toLowerCase();
        if (
            drugName.includes(allergen) || 
            allergen.includes(drugName.split(' ')[0]) || 
            (allergen.includes('cillin') && drugName.includes('cillin')) ||
            (allergen.includes('sulfa') && drugName.includes('sulfa')) ||
            (allergen.includes('nsaid') && (drugName.includes('ibuprofen') || drugName.includes('diclofenac') || drugName.includes('aspirin')))
        ) {
            return alg;
        }
    }
    return null;
};

const handleMedicationChange = () => {
    errorMessage.value = '';
    if (selectedMedication.value) {
        form.dosage = selectedMedication.value.strength || '1 tab';
        if (isMedicationAlreadyPrescribed(selectedMedication.value.id)) {
            errorMessage.value = `Note: ${selectedMedication.value.generic_name} is already actively prescribed for this patient in this visit.`;
        }
    }
    calculateQuantity();

    const conflict = checkAllergyContraindication();
    if (conflict) {
        matchedAllergy.value = conflict;
        showAllergyModal.value = true;
    }
};

const submitPrescription = () => {
    if (!form.medication_id) {
        errorMessage.value = 'Please select a medication from the formulary.';
        return;
    }

    if (isMedicationAlreadyPrescribed(form.medication_id)) {
        errorMessage.value = 'This medication is already actively prescribed in this consultation. Duplicate active prescription is blocked.';
        return;
    }

    const conflict = checkAllergyContraindication();
    if (conflict && !overrideConfirmed.value) {
        matchedAllergy.value = conflict;
        showAllergyModal.value = true;
        return;
    }

    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        overrideConfirmed.value = false;
        successMessage.value = 'Prescription saved.';
        setTimeout(() => successMessage.value = '', 4000);
        return;
    }

    form.post(route('prescriptions.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            const medName = selectedMedication.value?.generic_name || 'Medication';
            form.reset({
                medication_id: '',
                dosage: '',
                frequency: 'TID',
                duration_days: '5',
                route: 'PO',
                quantity: '15',
                instructions: '',
                allergy_override_reason: '',
            });
            overrideConfirmed.value = false;
            errorMessage.value = '';
            successMessage.value = `✓ Successfully prescribed ${medName}. Sent to Pharmacy queue & accrued to patient invoice.`;
            setTimeout(() => {
                successMessage.value = '';
            }, 5000);
        },
        onError: (errs) => {
            errorMessage.value = errs.prescription || 'Failed to submit prescription.';
            setTimeout(() => {
                errorMessage.value = '';
            }, 6000);
        }
    });
};

const confirmOverrideAndSubmit = () => {
    if (!overrideConfirmed.value) return;
    showAllergyModal.value = false;
    submitPrescription();
};

const cancelAllergyOrder = () => {
    showAllergyModal.value = false;
    form.medication_id = '';
    overrideConfirmed.value = false;
};
</script>

<template>
    <div class="space-y-4">
        
        <!-- Header Strip -->
        <div class="flex items-center justify-between border-b border-border/60 pb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                <Pill class="w-3.5 h-3.5 text-primary" />
                <span>E-Prescription & Formulary Dispense Order</span>
            </h3>
            <span class="text-[10px] font-mono text-muted-foreground">
                {{ formularies.length }} Formulary Items Available
            </span>
        </div>

        <!-- Real-Time Flash Feedback Banners -->
        <div 
            v-if="successMessage" 
            class="p-2.5 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                <span>{{ successMessage }}</span>
            </div>
            <button @click="successMessage = ''" class="text-emerald-700 hover:text-emerald-900">
                <X class="w-3.5 h-3.5" />
            </button>
        </div>

        <div 
            v-if="errorMessage || form.errors.prescription" 
            class="p-2.5 rounded-md bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 text-xs font-semibold flex items-center justify-between"
        >
            <div class="flex items-center gap-2">
                <AlertTriangle class="w-4 h-4 text-rose-600 dark:text-rose-400 flex-shrink-0" />
                <span>{{ errorMessage || form.errors.prescription }}</span>
            </div>
            <button @click="errorMessage = ''; form.clearErrors('prescription')" class="text-rose-700 hover:text-rose-900">
                <X class="w-3.5 h-3.5" />
            </button>
        </div>

        <!-- Clinical Override Banner -->
        <div v-if="overrideConfirmed" class="p-2 rounded bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-amber-950 dark:text-amber-200 text-xs flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-amber-600 flex-shrink-0" />
                <span><strong>Clinical Override Active:</strong> Prescribing despite documented allergy to {{ matchedAllergy?.allergen }}.</span>
            </div>
            <button @click="overrideConfirmed = false" class="text-xs text-amber-800 underline font-semibold">Reset</button>
        </div>
        
        <!-- HIGH-DENSITY PRESCRIPTION FORM -->
        <form @submit.prevent="submitPrescription" class="p-3 bg-muted/20 rounded-lg border border-border/70 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2.5">
                
                <!-- 1. Medication Select (5 cols) -->
                <div class="sm:col-span-2 lg:col-span-5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Formulary Medication *
                    </label>
                    <Select
                        v-model="form.medication_id"
                        :placeholder="'Search hospital formulary drug...'"
                        :options="formularies.map(med => ({
                            label: `${med.generic_name} ${med.brand_name ? '(' + med.brand_name + ')' : ''} — ${med.strength} (${med.form}) ${isMedicationAlreadyPrescribed(med.id) ? ' [Already Prescribed]' : ''}`,
                            value: med.id
                        }))"
                        class="h-8 text-xs"
                        @change="handleMedicationChange"
                    />
                    <InputError :message="form.errors.medication_id" class="mt-0.5" />
                </div>
                
                <!-- 2. Dosage (2 cols) -->
                <div class="lg:col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Dosage *
                    </label>
                    <Input v-model="form.dosage" type="text" placeholder="e.g. 500mg" class="h-8 text-xs" required />
                </div>
                
                <!-- 3. Route (2 cols) -->
                <div class="lg:col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Route *
                    </label>
                    <Select
                        v-model="form.route"
                        :options="[
                            { label: 'PO (Oral)', value: 'PO' },
                            { label: 'IV (Intravenous)', value: 'IV' },
                            { label: 'IM (Intramuscular)', value: 'IM' },
                            { label: 'SC (Subcutaneous)', value: 'SC' },
                            { label: 'Topical', value: 'Topical' },
                            { label: 'Inhalation', value: 'Inhalation' },
                        ]"
                        class="h-8 text-xs"
                    />
                </div>

                <!-- 4. Frequency (3 cols) -->
                <div class="lg:col-span-3">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Frequency *
                    </label>
                    <Select
                        v-model="form.frequency"
                        :options="[
                            { label: 'OD (Once daily - 1x)', value: 'OD' },
                            { label: 'BD (Twice daily - 2x)', value: 'BD' },
                            { label: 'TID (Three times daily - 3x)', value: 'TID' },
                            { label: 'QID (Four times daily - 4x)', value: 'QID' },
                            { label: 'Q4H (Every 4 hours - 6x)', value: 'Q4H' },
                            { label: 'PRN (As needed)', value: 'PRN' },
                            { label: 'STAT (Immediate single dose)', value: 'STAT' },
                        ]"
                        class="h-8 text-xs"
                    />
                </div>
                
                <!-- 5. Duration (2 cols) -->
                <div class="lg:col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Days *
                    </label>
                    <Input v-model="form.duration_days" type="number" min="1" placeholder="5" class="h-8 text-xs" required />
                </div>
                
                <!-- 6. Total Qty Auto-Calculated (2 cols) -->
                <div class="lg:col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1 flex items-center justify-between">
                        <span>Total Qty *</span>
                        <span class="text-[9px] text-primary lowercase font-normal">auto</span>
                    </label>
                    <Input v-model="form.quantity" type="number" min="1" placeholder="15" class="h-8 text-xs font-mono font-bold" required />
                </div>
                
                <!-- 7. Instructions (5 cols) -->
                <div class="sm:col-span-2 lg:col-span-5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Dispense Instructions
                    </label>
                    <Input v-model="form.instructions" type="text" placeholder="e.g. Take after meals with plenty of water" class="h-8 text-xs" />
                </div>

                <!-- 8. Submit Action (3 cols) -->
                <div class="sm:col-span-2 lg:col-span-3 flex items-end">
                    <Button
                        v-if="can.prescribe"
                        type="submit"
                        variant="default"
                        size="sm"
                        :disabled="form.processing || !form.medication_id"
                        class="w-full h-8 gap-1.5 text-xs font-semibold shadow-2xs"
                    >
                        <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                        <Send v-else class="w-3.5 h-3.5" />
                        <span>{{ form.processing ? 'Routing...' : 'Sign & Prescribe' }}</span>
                    </Button>
                </div>
            </div>
        </form>

        <!-- ACTIVE CONSULTATION PRESCRIPTIONS LIST -->
        <div class="space-y-2 pt-2 border-t border-border/60">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold text-foreground flex items-center gap-1.5">
                    <CheckSquare class="w-3.5 h-3.5 text-primary" />
                    <span>Active Prescriptions for this Visit</span>
                </h4>
                <span class="text-[10px] font-mono text-muted-foreground">
                    {{ existingPrescriptions.length }} Item(s) Prescribed
                </span>
            </div>

            <div class="bg-card rounded-lg overflow-hidden border border-border/60 shadow-2xs">
                <Table class="w-full text-xs">
                    <TableHeader>
                        <TableRow class="bg-muted/30 text-[10px] uppercase font-bold text-muted-foreground">
                            <TableHead class="py-2 px-3">Medication</TableHead>
                            <TableHead class="py-2 px-3">Dosage & Route</TableHead>
                            <TableHead class="py-2 px-3">Regimen & Duration</TableHead>
                            <TableHead class="py-2 px-3">Quantity</TableHead>
                            <TableHead class="py-2 px-3">Instructions</TableHead>
                            <TableHead class="py-2 px-3">Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow 
                            v-for="rx in existingPrescriptions" 
                            :key="rx.id"
                            class="hover:bg-muted/20 transition-colors border-b border-border/30"
                        >
                            <TableCell class="py-2 px-3">
                                <div class="font-bold text-foreground">{{ rx.medication?.generic_name || 'Medication' }}</div>
                                <div class="text-[10px] text-muted-foreground font-mono">{{ rx.medication?.brand_name || rx.medication?.code }} · {{ rx.medication?.strength }}</div>
                            </TableCell>
                            <TableCell class="py-2 px-3 font-mono font-medium">
                                {{ rx.dosage }} <span class="text-muted-foreground">({{ rx.route || 'PO' }})</span>
                            </TableCell>
                            <TableCell class="py-2 px-3">
                                <span class="font-semibold text-foreground">{{ rx.frequency }}</span>
                                <span class="text-muted-foreground text-[10px] ml-1">for {{ rx.duration_days }} days</span>
                            </TableCell>
                            <TableCell class="py-2 px-3 font-mono font-bold text-primary">
                                {{ rx.quantity }} {{ rx.medication?.form || 'units' }}
                            </TableCell>
                            <TableCell class="py-2 px-3 text-muted-foreground text-[11px]">
                                {{ rx.instructions || 'Standard dosing' }}
                            </TableCell>
                            <TableCell class="py-2 px-3">
                                <AfyaStatusBadge :status="rx.status || 'Pending'" dot />
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="existingPrescriptions.length === 0">
                            <TableCell colspan="6" class="text-center py-6 text-muted-foreground text-xs">
                                No medications prescribed during this consultation yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <!-- Clinical Allergy Contraindication Safety Modal -->
        <Modal :show="showAllergyModal" max-width="md" @close="cancelAllergyOrder">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <ShieldAlert class="w-4 h-4 text-rose-600" />
                        <h3 class="font-bold text-sm text-rose-700">Allergy Contraindication Warning</h3>
                    </div>
                    <button @click="cancelAllergyOrder" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="matchedAllergy && selectedMedication" class="space-y-3 text-xs">
                    <div class="p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 rounded text-rose-950 dark:text-rose-200 space-y-1.5">
                        <div class="font-bold text-rose-900 dark:text-rose-300 flex items-center gap-1">
                            <AlertTriangle class="w-4 h-4 text-rose-600 flex-shrink-0" />
                            <span>Documented Allergen: {{ matchedAllergy.allergen }} (Severity: {{ matchedAllergy.severity || 'Critical' }})</span>
                        </div>
                        <p class="text-[11px] text-rose-800 dark:text-rose-300 leading-snug">
                            The selected drug <strong class="text-rose-950 dark:text-white">{{ selectedMedication.generic_name }}</strong> conflicts with the patient's recorded allergy. Prescribing this medication could trigger an adverse anaphylactic or allergic reaction.
                        </p>
                    </div>

                    <div class="space-y-1.5 pt-1">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input
                                v-model="overrideConfirmed"
                                type="checkbox"
                                class="mt-0.5 rounded border-rose-300 text-rose-600 focus:ring-rose-500"
                            />
                            <span class="text-xs font-semibold text-foreground">
                                I confirm clinical review of this contraindication and authorize a medically justified override.
                            </span>
                        </label>
                    </div>

                    <div v-if="overrideConfirmed" class="space-y-1.5 pt-1">
                        <label class="block font-bold text-xs text-foreground">Override Clinical Rationale *</label>
                        <Input
                            v-model="form.allergy_override_reason"
                            type="text"
                            placeholder="e.g. Desensitized protocol in ICU, No alternative available"
                            autofocus
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <Button variant="outline" size="sm" @click="cancelAllergyOrder">Change Medication</Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        :disabled="!overrideConfirmed || !form.allergy_override_reason"
                        @click="confirmOverrideAndSubmit"
                    >
                        <span>Authorize Clinical Override</span>
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
