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
    Info,
    DollarSign,
    ShieldCheck,
    Activity,
    Sparkles,
    ChevronDown,
    ChevronUp,
    AlertCircle,
    Zap
} from '@lucide/vue';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import AfyaMedicationCombobox from '@/Components/Afya/AfyaMedicationCombobox.vue';
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

const filteredFormularies = computed(() => {
    const nonPharma = new Set([
        'Surgical_Consumable', 'Lab_Reagent', 'IPC_Chemical',
        'Stationery_MTUHA', 'Medical_Gas', 'Linen_Apparel',
        'Nutrition_Food', 'Fixed_Asset'
    ]);
    return props.formularies.filter(m => !nonPharma.has(m.drug_class) && !nonPharma.has(m.form));
});

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

// Real-Time CDSS State
const cdssLoading = ref(false);
const cdssResult = ref(null);
const showCdssDrawer = ref(true);

const evaluateCdss = async () => {
    if (!form.medication_id) {
        cdssResult.value = null;
        return;
    }

    const patientId = props.patientId || props.patient?.id;
    if (!patientId) return;

    cdssLoading.value = true;
    try {
        const response = await axios.post(route('clinical.cdss.evaluate-prescription'), {
            patient_id: patientId,
            items: [
                {
                    medication_id: form.medication_id,
                    dosage: form.dosage,
                    frequency: form.frequency,
                }
            ],
            existing_prescriptions: props.existingPrescriptions || [],
        });
        cdssResult.value = response.data;
        if (response.data.critical_count > 0 || response.data.warning_count > 0) {
            showCdssDrawer.value = true;
        }
    } catch (e) {
        console.error('CDSS evaluation error', e);
    } finally {
        cdssLoading.value = false;
    }
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
    evaluateCdss();

    const conflict = checkAllergyContraindication();
    if (conflict) {
        matchedAllergy.value = conflict;
        showAllergyModal.value = true;
    }
};

watch(() => form.dosage, () => {
    if (form.medication_id) evaluateCdss();
});

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
                {{ filteredFormularies.length }} Formulary Items Available
            </span>
        </div>

        <!-- Real-Time Flash Feedback Banners -->
        <div 
            v-if="successMessage" 
            class="p-2.5 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
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
                <AlertTriangle class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                <span>{{ errorMessage || form.errors.prescription }}</span>
            </div>
            <button @click="errorMessage = ''; form.clearErrors('prescription')" class="text-rose-700 hover:text-rose-900">
                <X class="w-3.5 h-3.5" />
            </button>
        </div>

        <!-- CDSS Real-Time Drug Safety & DDI Evaluation Drawer -->
        <div v-if="cdssResult" class="rounded-lg border transition overflow-hidden" :class="{
            'bg-emerald-50/60 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800': cdssResult.is_safe,
            'bg-amber-50/70 dark:bg-amber-950/30 border-amber-300 dark:border-amber-700': !cdssResult.is_safe && cdssResult.critical_count === 0,
            'bg-rose-50/80 dark:bg-rose-950/40 border-rose-400 dark:border-rose-700': cdssResult.critical_count > 0,
        }">
            <!-- Header Bar -->
            <div class="p-2.5 flex items-center justify-between cursor-pointer" @click="showCdssDrawer = !showCdssDrawer">
                <div class="flex items-center gap-2">
                    <ShieldCheck v-if="cdssResult.is_safe" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    <AlertTriangle v-else-if="cdssResult.critical_count === 0" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    <ShieldAlert v-else class="w-4 h-4 text-rose-600 dark:text-rose-400 animate-pulse" />

                    <span class="text-xs font-bold" :class="{
                        'text-emerald-900 dark:text-emerald-200': cdssResult.is_safe,
                        'text-amber-950 dark:text-amber-200': !cdssResult.is_safe && cdssResult.critical_count === 0,
                        'text-rose-950 dark:text-rose-200': cdssResult.critical_count > 0,
                    }">
                        <template v-if="cdssResult.is_safe">
                            CDSS Drug Safety: Clear (0 DDI, 0 Cross-Allergy, Renal Safe)
                        </template>
                        <template v-else>
                            Clinical Decision Alert: {{ cdssResult.critical_count }} Critical / {{ cdssResult.warning_count }} Warnings Detected
                        </template>
                    </span>

                    <span v-if="cdssResult.egfr_info" class="text-[10px] font-mono px-2 py-0.5 rounded bg-black/10 text-foreground font-semibold">
                        eGFR: {{ cdssResult.egfr_info.egfr }} mL/min ({{ cdssResult.egfr_info.stage }})
                    </span>
                </div>

                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span class="text-[11px] font-medium">{{ showCdssDrawer ? 'Hide Details' : 'View Details' }}</span>
                    <ChevronUp v-if="showCdssDrawer" class="w-4 h-4" />
                    <ChevronDown v-else class="w-4 h-4" />
                </div>
            </div>

            <!-- Expanded Alerts Body -->
            <div v-if="showCdssDrawer && cdssResult.alerts.length > 0" class="p-3 pt-0 space-y-2 border-t border-border/40">
                <div 
                    v-for="(alert, idx) in cdssResult.alerts" 
                    :key="idx"
                    class="p-2.5 rounded-md border text-xs space-y-1"
                    :class="{
                        'bg-rose-100/70 dark:bg-rose-950/50 border-rose-300 dark:border-rose-700 text-rose-950 dark:text-rose-100': alert.severity === 'CRITICAL',
                        'bg-amber-100/70 dark:bg-amber-950/50 border-amber-300 dark:border-amber-700 text-amber-950 dark:text-amber-100': alert.severity === 'WARNING',
                        'bg-blue-100/70 dark:bg-blue-950/50 border-blue-300 dark:border-blue-700 text-blue-950 dark:text-blue-100': alert.severity === 'INFO',
                    }"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-bold tracking-wide flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold uppercase" :class="{
                                'bg-rose-600 text-white': alert.severity === 'CRITICAL',
                                'bg-amber-600 text-white': alert.severity === 'WARNING',
                                'bg-blue-600 text-white': alert.severity === 'INFO',
                            }">{{ alert.severity }}</span>
                            {{ alert.title }}
                        </span>
                        <span v-if="alert.requires_override" class="text-[10px] font-mono text-rose-700 dark:text-rose-300 font-bold">
                            Override Required
                        </span>
                    </div>
                    <p class="text-[11px] leading-relaxed opacity-90">{{ alert.description }}</p>
                    <div class="text-[11px] font-medium text-foreground bg-black/5 dark:bg-white/5 p-1.5 rounded mt-1">
                        <strong>💡 Recommendation:</strong> {{ alert.recommendation }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Clinical Override Banner -->
        <div v-if="overrideConfirmed" class="p-2 rounded bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-amber-950 dark:text-amber-200 text-xs flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                <span><strong>Clinical Override Active:</strong> Prescribing despite documented allergy to {{ matchedAllergy?.allergen }}.</span>
            </div>
            <button @click="overrideConfirmed = false" class="text-xs text-amber-800 underline font-semibold">Reset</button>
        </div>
        
        <!-- HIGH-DENSITY PRESCRIPTION FORM -->
        <form @submit.prevent="submitPrescription" class="p-3 bg-muted/20 rounded-lg border border-border/70 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2.5">
                
                <!-- 1. Medication Select (7 cols - Maximum horizontal space to eliminate wrap) -->
                <div class="sm:col-span-2 lg:col-span-7">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Formulary Medication *
                    </label>
                    <AfyaMedicationCombobox
                        v-model="form.medication_id"
                        :formularies="filteredFormularies"
                        :allergies="allergies.length > 0 ? allergies : (patient?.allergies || [])"
                        :existing-prescriptions="existingPrescriptions"
                        :error="!!form.errors.medication_id"
                        placeholder="Search 500+ hospital drugs (e.g. Amox, Panadol, 500mg)..."
                        @change="handleMedicationChange"
                    />
                    <InputError :message="form.errors.medication_id" class="mt-0.5" />
                </div>
                
                <!-- 2. Dosage (1 col) -->
                <div class="lg:col-span-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Dosage *
                    </label>
                    <Input v-model="form.dosage" type="text" placeholder="500mg" class="h-8 text-xs px-2" required />
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
                            { label: 'SC (Subcut)', value: 'SC' },
                            { label: 'Topical', value: 'Topical' },
                            { label: 'Inhalation', value: 'Inhalation' },
                        ]"
                        class="h-8 text-xs"
                    />
                </div>

                <!-- 4. Frequency (2 cols) -->
                <div class="lg:col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1">
                        Frequency *
                    </label>
                    <Select
                        v-model="form.frequency"
                        :options="[
                            { label: 'OD (1x/day)', value: 'OD' },
                            { label: 'BD (2x/day)', value: 'BD' },
                            { label: 'TID (3x/day)', value: 'TID' },
                            { label: 'QID (4x/day)', value: 'QID' },
                            { label: 'Q4H (q4h)', value: 'Q4H' },
                            { label: 'PRN (p.r.n)', value: 'PRN' },
                            { label: 'STAT (Stat)', value: 'STAT' },
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

                <!-- Real-Time Estimated Cost Strip for Transparency -->
                <div v-if="selectedMedication && form.quantity" class="sm:col-span-2 lg:col-span-12 flex items-center justify-between p-2 rounded-md bg-sky-50/80 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 text-xs">
                    <div class="flex items-center gap-2 text-sky-950 dark:text-sky-200">
                        <DollarSign class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 shrink-0" />
                        <span>
                            <strong>Estimated Medication Charge:</strong> 
                            <strong class="text-sky-700 dark:text-sky-300 font-mono text-sm ml-1">
                                TZS {{ Number((selectedMedication.unit_price || 0) * (Number(form.quantity) || 0)).toLocaleString() }}
                            </strong>
                            <span class="text-[10px] text-muted-foreground ml-1.5 font-mono">
                                (TZS {{ Number(selectedMedication.unit_price || 0).toLocaleString() }} / {{ selectedMedication.form || 'unit' }} × {{ form.quantity }} {{ selectedMedication.form || 'unit' }}s)
                            </span>
                        </span>
                    </div>
                    <span class="text-[10px] font-mono text-muted-foreground">
                        Stock on Hand: <strong :class="(selectedMedication.total_stock_on_hand || 0) > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600'">{{ selectedMedication.total_stock_on_hand || 0 }}</strong>
                    </span>
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
                            <TableHead class="py-2 px-3 text-right">Fee (TZS)</TableHead>
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
                            <TableCell class="py-2 px-3 whitespace-nowrap">
                                <div class="flex items-center gap-1.5 font-bold text-foreground whitespace-nowrap">
                                    <Pill class="w-3.5 h-3.5 text-primary shrink-0" />
                                    <span>{{ rx.medication?.generic_name || 'Medication' }}</span>
                                    <span v-if="rx.medication?.brand_name" class="text-muted-foreground text-[11px] font-normal">({{ rx.medication?.brand_name }})</span>
                                    <span class="text-[10px] font-mono px-1.5 py-0.2 bg-muted rounded font-semibold">{{ rx.medication?.strength }}</span>
                                    <span class="text-[9px] px-1.5 py-0.2 rounded font-bold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20">{{ rx.medication?.form }}</span>
                                </div>
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
                            <TableCell class="py-2 px-3 font-mono font-bold text-right text-foreground">
                                TZS {{ Number((rx.medication?.unit_price || 0) * (Number(rx.quantity) || 1)).toLocaleString() }}
                            </TableCell>
                            <TableCell class="py-2 px-3 text-muted-foreground text-[11px]">
                                {{ rx.instructions || 'Standard dosing' }}
                            </TableCell>
                            <TableCell class="py-2 px-3">
                                <AfyaStatusBadge :status="rx.status || 'Pending'" dot />
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="existingPrescriptions.length === 0">
                            <TableCell colspan="7" class="text-center py-6 text-muted-foreground text-xs">
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
                            <AlertTriangle class="w-4 h-4 text-rose-600 shrink-0" />
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
