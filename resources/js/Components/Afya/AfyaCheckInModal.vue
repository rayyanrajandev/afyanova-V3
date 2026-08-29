<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import {
    Stethoscope,
    Syringe,
    Bandage,
    Scissors,
    FlaskConical,
    Pill,
    ArrowRightLeft,
    X,
    Loader2,
    Sparkles,
    Search,
    Check,
    AlertTriangle,
    CheckCircle2,
    ConciergeBell,
    Info,
} from '@lucide/vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    patient: {
        type: Object,
        default: null,
    },
    recentPatients: {
        type: Array,
        default: () => [],
    },
    labTests: {
        type: Array,
        default: () => [],
    },
    procedureCatalogs: {
        type: Array,
        default: () => [],
    },
    activeTickets: {
        type: Array,
        default: () => [],
    },
    defaultServicePoint: {
        type: String,
        default: 'Triage',
    },
    defaultVisitType: {
        type: String,
        default: 'OPD Consultation',
    },
});

const emit = defineEmits(['close', 'success', 'transfer']);

const isSubmitting = ref(false);
const patientSearchQuery = ref('');
const internalSelectedPatient = ref(null);

const form = ref({
    patient_id: '',
    service_point: 'Triage',
    selected_desk: 'Triage', // 'Triage' | 'Injection' | 'Dressing' | 'MinorSurgery' | 'Lab' | 'Pharmacy'
    visit_type: 'OPD Consultation',
    medication_source: 'ClinicStock', // 'PatientSupplied' (Sindano ya Nje) | 'ClinicStock' (Dawa ya Ndani)
    priority: 'Routine',
    reason: '',
    payment_mode: 'Cash',
    selected_lab_test_ids: [],
    procedure_catalog_id: '',
});

// Lab Workbench State
const labTestSearchQuery = ref('');

// 6 Core Physical Desks
const serviceOptions = [
    {
        id: 'Triage',
        servicePoint: 'Triage',
        deskType: 'Triage',
        visitType: 'OPD Consultation',
        title: 'OPD Consultation',
        badge: 'Doctor & Vitals',
        icon: Stethoscope,
        color: 'text-amber-600 dark:text-amber-400',
    },
    {
        id: 'Injection',
        servicePoint: 'Procedure',
        deskType: 'Injection',
        visitType: 'Procedure',
        title: 'Injections',
        badge: 'Chumba cha Sindano',
        icon: Syringe,
        color: 'text-rose-600 dark:text-rose-400',
    },
    {
        id: 'Dressing',
        servicePoint: 'Procedure',
        deskType: 'Dressing',
        visitType: 'Procedure',
        title: 'Wound Care',
        badge: 'Chumba cha Vidonda',
        icon: Bandage,
        color: 'text-amber-600 dark:text-amber-400',
    },
    {
        id: 'MinorSurgery',
        servicePoint: 'Procedure',
        deskType: 'MinorSurgery',
        visitType: 'Procedure',
        title: 'Minor Surgery',
        badge: 'Upasuaji Mdogo',
        icon: Scissors,
        color: 'text-sky-600 dark:text-sky-400',
    },
    {
        id: 'Lab',
        servicePoint: 'Lab',
        deskType: 'Lab',
        visitType: 'Direct_Lab',
        title: 'Direct Lab',
        badge: 'Self-Request',
        icon: FlaskConical,
        color: 'text-purple-600 dark:text-purple-400',
    },
    {
        id: 'Pharmacy',
        servicePoint: 'Pharmacy',
        deskType: 'Pharmacy',
        visitType: 'Pharmacy_OTC',
        title: 'Pharmacy OTC',
        badge: 'Direct Sales',
        icon: Pill,
        color: 'text-emerald-600 dark:text-emerald-400',
    },
];

const activePatient = computed(() => props.patient || internalSelectedPatient.value);

// Check if patient already has an active waiting/in-progress ticket
const patientConflictTicket = computed(() => {
    if (!activePatient.value?.id) return null;
    return (props.activeTickets || []).find(
        t => t.patient_id === activePatient.value.id && ['Waiting', 'In Progress', 'In_Progress'].includes(t.status)
    ) || null;
});

const filteredPatients = computed(() => {
    if (!patientSearchQuery.value) return (props.recentPatients || []).slice(0, 8);
    const q = patientSearchQuery.value.toLowerCase().trim();
    return (props.recentPatients || []).filter(p =>
        `${p.first_name || ''} ${p.last_name || ''}`.toLowerCase().includes(q) ||
        (p.primary_mrn || '').toLowerCase().includes(q) ||
        (p.phone_number || '').toLowerCase().includes(q)
    ).slice(0, 8);
});

const filteredLabTests = computed(() => {
    let list = props.labTests || [];
    if (labTestSearchQuery.value) {
        const q = labTestSearchQuery.value.toLowerCase().trim();
        list = list.filter(t =>
            (t.name && t.name.toLowerCase().includes(q)) ||
            (t.test_code && t.test_code.toLowerCase().includes(q))
        );
    }
    return list;
});

const selectedLabTestsTotal = computed(() => {
    if (!props.labTests || !form.value.selected_lab_test_ids.length) return 0;
    return props.labTests
        .filter(t => form.value.selected_lab_test_ids.includes(t.id))
        .reduce((sum, t) => sum + (parseFloat(t.price) || 0), 0);
});

// Filtered Procedure Catalogs by Desk
const injectionCatalogs = computed(() => {
    const list = (props.procedureCatalogs || []).filter(c => c.category === 'Injection');
    return list.length > 0 ? list : (props.procedureCatalogs || []);
});

const dressingCatalogs = computed(() => {
    const list = (props.procedureCatalogs || []).filter(c => c.category === 'Dressing');
    return list.length > 0 ? list : (props.procedureCatalogs || []);
});

const minorSurgeryCatalogs = computed(() => {
    const list = (props.procedureCatalogs || []).filter(c => c.category === 'MinorSurgery' || c.category === 'OBGYN');
    return list.length > 0 ? list : (props.procedureCatalogs || []);
});

// Dynamic Estimated Fee for Pre-Service Capture
const estimatedFee = computed(() => {
    if (form.value.payment_mode === 'Prepaid' || form.value.visit_type === 'Treatment_Followup') {
        return { amount: 0, label: 'Prepaid Course (TZS 0)' };
    }

    if (form.value.selected_desk === 'Injection') {
        if (form.value.medication_source === 'PatientSupplied') {
            return { amount: 2000, label: 'TZS 2,000 (Sindano ya Nje - Admin Fee)' };
        }
        const selectedCat = props.procedureCatalogs?.find(c => c.id === form.value.procedure_catalog_id);
        const price = selectedCat ? (parseFloat(selectedCat.standard_price) || 5000) : 5000;
        return { amount: price, label: `TZS ${price.toLocaleString()} (Clinic Stock Injection)` };
    }

    if (form.value.selected_desk === 'Dressing') {
        const selectedCat = props.procedureCatalogs?.find(c => c.id === form.value.procedure_catalog_id);
        const price = selectedCat ? (parseFloat(selectedCat.standard_price) || 15000) : 15000;
        return { amount: price, label: `TZS ${price.toLocaleString()} (Wound Dressing Tariff)` };
    }

    if (form.value.selected_desk === 'MinorSurgery') {
        const selectedCat = props.procedureCatalogs?.find(c => c.id === form.value.procedure_catalog_id);
        const price = selectedCat ? (parseFloat(selectedCat.standard_price) || 25000) : 25000;
        return { amount: price, label: `TZS ${price.toLocaleString()} (Minor Surgery Tariff)` };
    }

    if (form.value.selected_desk === 'Lab') {
        const total = selectedLabTestsTotal.value;
        return { amount: total, label: `TZS ${total.toLocaleString()} (${form.value.selected_lab_test_ids.length} Tests)` };
    }

    return { amount: 0, label: 'Direct Queue' };
});

const toggleLabTest = (testId) => {
    const idx = form.value.selected_lab_test_ids.indexOf(testId);
    if (idx > -1) {
        form.value.selected_lab_test_ids.splice(idx, 1);
    } else {
        form.value.selected_lab_test_ids.push(testId);
    }
};

const isServiceSelected = (opt) => {
    return form.value.selected_desk === opt.deskType;
};

const selectService = (opt) => {
    form.value.selected_desk = opt.deskType;
    form.value.service_point = opt.servicePoint;
    form.value.visit_type = opt.visitType;

    // Reset default catalog for the selected desk
    if (opt.deskType === 'Injection') {
        form.value.procedure_catalog_id = injectionCatalogs.value[0]?.id || '';
        form.value.medication_source = 'ClinicStock';
        form.value.payment_mode = 'Cash';
    } else if (opt.deskType === 'Dressing') {
        form.value.procedure_catalog_id = dressingCatalogs.value[0]?.id || '';
        form.value.medication_source = 'ClinicStock';
        form.value.payment_mode = 'Cash';
    } else if (opt.deskType === 'MinorSurgery') {
        form.value.procedure_catalog_id = minorSurgeryCatalogs.value[0]?.id || '';
        form.value.medication_source = 'ClinicStock';
        form.value.payment_mode = 'Cash';
    } else if (opt.deskType === 'Lab') {
        form.value.procedure_catalog_id = '';
        form.value.payment_mode = 'Cash';
    } else {
        form.value.procedure_catalog_id = '';
        form.value.payment_mode = 'Cash';
    }
};

const selectPatient = (patient) => {
    internalSelectedPatient.value = patient;
    form.value.patient_id = patient?.id || '';
    patientSearchQuery.value = patient ? `${patient.first_name} ${patient.last_name}` : '';
};

const clearSelectedPatient = () => {
    internalSelectedPatient.value = null;
    form.value.patient_id = '';
    patientSearchQuery.value = '';
};

// Sync form on open
watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            if (props.patient) {
                selectPatient(props.patient);
            } else if (!internalSelectedPatient.value && props.recentPatients?.length > 0) {
                form.value.patient_id = '';
            }
            form.value.selected_desk = 'Triage';
            form.value.service_point = props.defaultServicePoint || 'Triage';
            form.value.visit_type = props.defaultVisitType || 'OPD Consultation';
            form.value.medication_source = 'ClinicStock';
            form.value.priority = 'Routine';
            form.value.reason = '';
            form.value.payment_mode = form.value.visit_type === 'Treatment_Followup' ? 'Prepaid' : 'Cash';
            form.value.selected_lab_test_ids = [];
            form.value.procedure_catalog_id = '';
            labTestSearchQuery.value = '';
        }
    },
    { immediate: true }
);

watch(
    () => props.patient,
    (newPatient) => {
        if (newPatient) {
            selectPatient(newPatient);
        }
    }
);

const handleClose = () => {
    if (!isSubmitting.value) {
        emit('close');
    }
};

const submitCheckIn = () => {
    const targetPatientId = activePatient.value?.id || form.value.patient_id;
    if (!targetPatientId) return;

    isSubmitting.value = true;
    form.value.patient_id = targetPatientId;

    router.post(route('queue.checkin-direct'), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            emit('success');
            emit('close');
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="handleClose">
        <div class="p-4 space-y-3">
            <!-- 1. Header (Ultra-Compact Meta/Apple Style) -->
            <div class="flex items-center justify-between pb-2 border-b border-border/40">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shadow-2xs">
                        <Sparkles class="w-3.5 h-3.5" />
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-foreground">Fast-Track Service & Queue Check-In</h3>
                        <p class="text-[10.5px] text-muted-foreground">Direct front-desk check-in, pre-service charge capture & queue ticket generation</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground p-1 rounded-md hover:bg-muted/40 transition-colors"
                    @click="handleClose"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>

            <!-- 2. Step 1: Patient Selection -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label class="text-[10.5px] font-bold text-muted-foreground uppercase tracking-wider">
                        1. Patient Identification
                    </label>
                    <span v-if="!activePatient" class="text-[10px] text-muted-foreground">Search by Name, Phone, or MRN</span>
                </div>

                <!-- A. When NO patient is selected: Compact Search Input with Dropdown -->
                <div v-if="!activePatient" class="relative">
                    <div class="relative flex items-center">
                        <Search class="w-3.5 h-3.5 absolute left-2.5 text-muted-foreground pointer-events-none z-10" />
                        <Input
                            v-model="patientSearchQuery"
                            type="text"
                            placeholder="Search patient (e.g. Zawadi, Dianna, MRN-202608)..."
                            class="pl-8 text-xs h-8 bg-background border-border/80 rounded-md shadow-2xs focus:ring-1 focus:ring-primary/20 w-full"
                            autofocus
                        />
                    </div>

                    <!-- Floating Search Results Dropdown -->
                    <div
                        v-if="patientSearchQuery || filteredPatients.length"
                        class="mt-1 max-h-36 overflow-y-auto rounded-md border border-border/60 bg-popover shadow-none divide-y divide-border/30"
                    >
                        <div
                            v-for="p in filteredPatients"
                            :key="p.id"
                            class="px-2.5 py-1.5 flex items-center justify-between text-xs cursor-pointer hover:bg-muted/50 transition-colors group"
                            @click="selectPatient(p)"
                        >
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-primary/10 text-primary font-bold text-[10px] flex items-center justify-center shrink-0">
                                    {{ p.first_name?.[0] }}{{ p.last_name?.[0] }}
                                </div>
                                <div>
                                    <div class="font-semibold text-foreground text-xs group-hover:text-primary transition-colors">
                                        {{ p.first_name }} {{ p.last_name }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground font-mono">
                                        {{ p.primary_mrn }} · {{ p.gender }} · {{ p.dob || 'DOB N/A' }}
                                        <span v-if="p.phone_number"> · {{ p.phone_number }}</span>
                                    </div>
                                </div>
                            </div>
                            <span class="text-[11px] text-primary font-semibold opacity-0 group-hover:opacity-100 transition-opacity">
                                Select →
                            </span>
                        </div>
                        <div v-if="filteredPatients.length === 0" class="p-3 text-center text-xs text-muted-foreground">
                            No registered patients matching "<span class="font-semibold text-foreground">{{ patientSearchQuery }}</span>"
                        </div>
                    </div>
                </div>

                <!-- B. When patient IS selected: Compact Profile Card -->
                <div
                    v-else
                    class="p-2 rounded-lg bg-card border border-border/80 shadow-none flex items-center justify-between gap-2"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center ring-1 ring-primary/20 shrink-0">
                            {{ activePatient.first_name?.[0] }}{{ activePatient.last_name?.[0] }}
                        </div>
                        <div>
                            <div class="font-bold text-foreground text-xs flex items-center gap-1.5">
                                <span>{{ activePatient.first_name }} {{ activePatient.last_name }}</span>
                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono bg-muted text-muted-foreground font-semibold">
                                    {{ activePatient.primary_mrn }}
                                </span>
                            </div>
                            <div class="text-[10.5px] text-muted-foreground">
                                {{ activePatient.gender }} · {{ activePatient.age ? `${activePatient.age}y` : (activePatient.dob || 'DOB N/A') }} · {{ activePatient.phone_number || 'No Phone' }}
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="!patient && recentPatients.length > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-6.5 text-[11px] font-semibold text-muted-foreground hover:text-foreground px-2"
                        @click="clearSelectedPatient"
                    >
                        Change
                    </Button>
                </div>

                <!-- Active Queue Conflict Notification -->
                <div
                    v-if="patientConflictTicket"
                    class="p-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-xs flex items-center justify-between gap-2"
                >
                    <div class="flex items-center gap-1.5 text-amber-700 dark:text-amber-300 min-w-0 truncate text-[11px]">
                        <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
                        <span class="truncate">
                            Currently waiting at <strong>{{ patientConflictTicket.current_service_point }} Desk</strong> (Ticket: <span class="font-mono font-bold">{{ patientConflictTicket.ticket_number }}</span>)
                        </span>
                    </div>
                    <span class="text-[10px] font-semibold text-amber-800 dark:text-amber-200 px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 shrink-0">
                        Active Ticket
                    </span>
                </div>
            </div>

            <!-- 3. Step 2: Destination Desk (Ultra-Compact 6-Desk Grid) -->
            <div class="space-y-1">
                <label class="text-[10.5px] font-bold text-muted-foreground uppercase tracking-wider">
                    2. Destination Clinical Desk
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-1.5 p-0.5 rounded-lg bg-muted/40 border border-border/40">
                    <button
                        v-for="opt in serviceOptions"
                        :key="opt.id"
                        type="button"
                        class="p-2 rounded-md flex flex-col gap-1 transition-all text-left"
                        :class="isServiceSelected(opt)
                            ? 'bg-background shadow-2xs text-foreground font-bold ring-1 ring-border/50'
                            : 'text-muted-foreground hover:text-foreground hover:bg-background/40'"
                        @click="selectService(opt)"
                    >
                        <div class="flex items-center gap-1.5">
                            <component :is="opt.icon" class="w-3.5 h-3.5 shrink-0" :class="opt.color" />
                            <span class="text-[11.5px] truncate font-semibold leading-tight">{{ opt.title }}</span>
                        </div>
                        <div class="text-[9.5px] text-muted-foreground truncate leading-tight">{{ opt.badge }}</div>
                    </button>
                </div>
            </div>

            <!-- 4. Step 3: Dynamic Item & Pre-Service Capture Workbench -->

            <!-- ================= A. INJECTIONS DESK WORKBENCH ================= -->
            <div v-if="form.selected_desk === 'Injection'" class="p-2.5 rounded-lg bg-muted/20 border border-border/50 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <Syringe class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                        <span class="text-[11.5px] font-bold text-foreground">Chumba cha Sindano (Injections Desk)</span>
                    </div>
                    <span class="text-[10.5px] font-mono font-bold text-rose-600 px-2 py-0.5 rounded bg-background border border-border/60">
                        {{ estimatedFee.label }}
                    </span>
                </div>

                <!-- Medication Sourcing & Course Type Selector -->
                <div class="grid grid-cols-3 gap-1.5 p-0.5 rounded-md bg-background border border-border/50">
                    <button
                        type="button"
                        class="py-1 px-1.5 rounded text-[10.5px] font-medium transition-all text-center flex items-center justify-center gap-1"
                        :class="form.visit_type === 'Procedure' && form.medication_source === 'PatientSupplied'
                            ? 'bg-blue-500/10 text-blue-700 dark:text-blue-300 font-bold shadow-2xs border border-blue-300 dark:border-blue-800'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="form.visit_type = 'Procedure'; form.medication_source = 'PatientSupplied'; form.payment_mode = 'Cash'"
                    >
                        <span>🔵 Sindano ya Nje (Admin Fee)</span>
                    </button>
                    <button
                        type="button"
                        class="py-1 px-1.5 rounded text-[10.5px] font-medium transition-all text-center flex items-center justify-center gap-1"
                        :class="form.visit_type === 'Procedure' && form.medication_source === 'ClinicStock'
                            ? 'bg-rose-500/10 text-rose-700 dark:text-rose-300 font-bold shadow-2xs border border-rose-300 dark:border-rose-800'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="form.visit_type = 'Procedure'; form.medication_source = 'ClinicStock'; form.payment_mode = 'Cash'"
                    >
                        <span>🟢 Dawa ya Ndani (Clinic Stock)</span>
                    </button>
                    <button
                        type="button"
                        class="py-1 px-1.5 rounded text-[10.5px] font-medium transition-all text-center flex items-center justify-center gap-1"
                        :class="form.visit_type === 'Treatment_Followup'
                            ? 'bg-purple-500/10 text-purple-700 dark:text-purple-300 font-bold shadow-2xs border border-purple-300 dark:border-purple-800'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="form.visit_type = 'Treatment_Followup'; form.payment_mode = 'Prepaid'"
                    >
                        <span>🔁 Course Revisit (Dose #2–5)</span>
                    </button>
                </div>

                <!-- Helper Hint -->
                <div class="text-[10px] text-muted-foreground flex items-center gap-1">
                    <Info class="w-3 h-3 text-primary shrink-0" />
                    <span v-if="form.medication_source === 'PatientSupplied' && form.visit_type !== 'Treatment_Followup'">
                        Patient brought external ampoule with verified prescription. Only consumable & administration fee (TZS 2,000) will be billed.
                    </span>
                    <span v-else-if="form.medication_source === 'ClinicStock' && form.visit_type !== 'Treatment_Followup'">
                        Medication ampoule will be dispensed from clinic stock and administered in treatment room.
                    </span>
                    <span v-else>
                        Patient is returning for scheduled follow-up dose. Billed as Prepaid Course (TZS 0) with zero duplicate charges.
                    </span>
                </div>

                <!-- Procedure Catalog Selector -->
                <div v-if="form.visit_type !== 'Treatment_Followup'" class="space-y-1">
                    <label class="text-[10.5px] font-semibold text-foreground">Injection Service Item</label>
                    <Select
                        v-model="form.procedure_catalog_id"
                        class="w-full text-xs h-8 rounded-md border border-input bg-background px-2 text-foreground shadow-2xs focus:ring-1 focus:ring-primary/20 focus:outline-hidden"
                    >
                        <option v-for="cat in injectionCatalogs" :key="cat.id" :value="cat.id">
                            {{ cat.name }} ({{ cat.procedure_code }}) — TZS {{ Number(cat.standard_price || 2000).toLocaleString() }}
                        </option>
                    </Select>
                </div>
            </div>

            <!-- ================= B. WOUND CARE & DRESSING BENCH ================= -->
            <div v-else-if="form.selected_desk === 'Dressing'" class="p-2.5 rounded-lg bg-muted/20 border border-border/50 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <Bandage class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                        <span class="text-[11.5px] font-bold text-foreground">Chumba cha Vidonda (Wound Care & Dressing)</span>
                    </div>
                    <span class="text-[10.5px] font-mono font-bold text-amber-600 px-2 py-0.5 rounded bg-background border border-border/60">
                        {{ estimatedFee.label }}
                    </span>
                </div>

                <!-- Visit Type Segmented Toggle -->
                <div class="grid grid-cols-2 gap-1.5 p-0.5 rounded-md bg-background border border-border/50">
                    <button
                        type="button"
                        class="py-1 px-2 rounded text-[11px] font-medium transition-all text-center flex items-center justify-center gap-1.5"
                        :class="form.visit_type === 'Procedure'
                            ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 font-bold shadow-2xs border border-amber-300 dark:border-amber-800'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="form.visit_type = 'Procedure'; form.payment_mode = 'Cash'"
                    >
                        <Bandage class="w-3 h-3 text-amber-500" />
                        <span>New Wound Dressing / Debridement</span>
                    </button>
                    <button
                        type="button"
                        class="py-1 px-2 rounded text-[11px] font-medium transition-all text-center flex items-center justify-center gap-1.5"
                        :class="form.visit_type === 'Treatment_Followup'
                            ? 'bg-blue-500/10 text-blue-700 dark:text-blue-300 font-bold shadow-2xs border border-blue-300 dark:border-blue-800'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="form.visit_type = 'Treatment_Followup'; form.payment_mode = 'Prepaid'"
                    >
                        <ArrowRightLeft class="w-3 h-3 text-blue-500" />
                        <span>Dressing Change / Follow-up</span>
                    </button>
                </div>

                <div v-if="form.visit_type === 'Procedure'" class="space-y-1">
                    <label class="text-[10.5px] font-semibold text-foreground">Dressing Service Catalog</label>
                    <Select
                        v-model="form.procedure_catalog_id"
                        class="w-full text-xs h-8 rounded-md border border-input bg-background px-2 text-foreground shadow-2xs focus:ring-1 focus:ring-primary/20 focus:outline-hidden"
                    >
                        <option v-for="cat in dressingCatalogs" :key="cat.id" :value="cat.id">
                            {{ cat.name }} ({{ cat.procedure_code }}) — TZS {{ Number(cat.standard_price || 15000).toLocaleString() }}
                        </option>
                    </Select>
                </div>
            </div>

            <!-- ================= C. MINOR SURGERY BENCH ================= -->
            <div v-else-if="form.selected_desk === 'MinorSurgery'" class="p-2.5 rounded-lg bg-muted/20 border border-border/50 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <Scissors class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" />
                        <span class="text-[11.5px] font-bold text-foreground">Upasuaji Mdogo (Minor Surgery Desk)</span>
                    </div>
                    <span class="text-[10.5px] font-mono font-bold text-sky-600 px-2 py-0.5 rounded bg-background border border-border/60">
                        {{ estimatedFee.label }}
                    </span>
                </div>

                <div class="space-y-1">
                    <label class="text-[10.5px] font-semibold text-foreground">Minor Procedure / Surgical Tariff</label>
                    <Select
                        v-model="form.procedure_catalog_id"
                        class="w-full text-xs h-8 rounded-md border border-input bg-background px-2 text-foreground shadow-2xs focus:ring-1 focus:ring-primary/20 focus:outline-hidden"
                    >
                        <option v-for="cat in minorSurgeryCatalogs" :key="cat.id" :value="cat.id">
                            {{ cat.name }} ({{ cat.procedure_code }}) — TZS {{ Number(cat.standard_price || 25000).toLocaleString() }}
                        </option>
                    </Select>
                </div>
            </div>

            <!-- ================= D. LABORATORY BENCH ================= -->
            <div v-else-if="form.selected_desk === 'Lab'" class="space-y-2 p-2.5 rounded-lg bg-muted/20 border border-border/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <FlaskConical class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                        <span class="text-[11.5px] font-bold text-foreground">Direct Laboratory Investigations</span>
                    </div>
                    <span class="text-[11px] font-mono font-bold text-primary px-2 py-0.5 rounded bg-background border border-border/60">
                        TZS {{ selectedLabTestsTotal.toLocaleString() }}
                    </span>
                </div>

                <!-- Search Input -->
                <div class="relative flex items-center">
                    <Search class="w-3.5 h-3.5 absolute left-2.5 text-muted-foreground pointer-events-none z-10" />
                    <Input
                        v-model="labTestSearchQuery"
                        type="text"
                        placeholder="Search laboratory test (e.g. Malaria, FBC, Glucose, Widal, UPT)..."
                        class="pl-8 text-xs h-7.5 bg-background w-full"
                    />
                </div>

                <!-- Scrollable Tests Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 max-h-36 overflow-y-auto p-1 bg-background rounded-md border border-border/50">
                    <div
                        v-for="test in filteredLabTests"
                        :key="test.id"
                        class="p-1.5 rounded flex items-center justify-between text-xs cursor-pointer border transition-colors"
                        :class="form.selected_lab_test_ids.includes(test.id)
                            ? 'bg-purple-500/10 border-purple-500/40 font-bold'
                            : 'border-transparent hover:bg-muted/40'"
                        @click="toggleLabTest(test.id)"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <div
                                class="w-3.5 h-3.5 rounded-xs border flex items-center justify-center shrink-0"
                                :class="form.selected_lab_test_ids.includes(test.id)
                                    ? 'bg-purple-600 border-purple-600 text-white'
                                    : 'border-border bg-card'"
                            >
                                <Check v-if="form.selected_lab_test_ids.includes(test.id)" class="w-2.5 h-2.5" />
                            </div>
                            <div class="truncate">
                                <div class="text-foreground truncate font-medium text-[11px]">{{ test.name }}</div>
                                <div class="text-[9.5px] text-muted-foreground font-mono">{{ test.test_code }} · {{ test.category }}</div>
                            </div>
                        </div>
                        <span class="text-[11px] font-mono font-bold text-foreground shrink-0 ml-1.5">
                            TZS {{ Number(test.price || 0).toLocaleString() }}
                        </span>
                    </div>
                    <div v-if="filteredLabTests.length === 0" class="col-span-full text-center py-4 text-xs text-muted-foreground">
                        {{ props.labTests?.length ? 'No investigations found matching search.' : 'Direct lab order will be processed at phlebotomy bench.' }}
                    </div>
                </div>

                <div class="flex items-center justify-between text-[10.5px] text-muted-foreground">
                    <span>{{ form.selected_lab_test_ids.length }} test(s) selected</span>
                    <span>Estimated Total: <strong class="text-foreground font-mono">TZS {{ selectedLabTestsTotal.toLocaleString() }}</strong></span>
                </div>
            </div>

            <!-- ================= E. OPD / PHARMACY DESK INFO ================= -->
            <div v-else class="p-2.5 rounded-lg bg-muted/20 border border-border/50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-md bg-primary/10 text-primary flex items-center justify-center">
                        <ConciergeBell class="w-3.5 h-3.5" />
                    </div>
                    <div>
                        <div class="text-xs font-bold text-foreground">{{ form.selected_desk === 'Triage' ? 'OPD & Clinical Triage' : 'Pharmacy Dispensary' }} Desk Routing</div>
                        <div class="text-[10px] text-muted-foreground">Patient will be queued directly to {{ form.selected_desk === 'Triage' ? 'Triage / Doctor' : 'Pharmacy' }} window.</div>
                    </div>
                </div>
                <span class="text-[10.5px] font-mono font-semibold text-muted-foreground bg-background px-2 py-0.5 rounded border border-border/50">
                    Direct Queue
                </span>
            </div>

            <!-- 5. Step 3: Payment Mode (Full Row) -->
            <div class="space-y-1">
                <label class="text-[10.5px] font-bold text-muted-foreground uppercase tracking-wider">
                    3. Payment Mode
                </label>
                <div class="grid grid-cols-3 gap-1.5 p-0.5 rounded-lg bg-muted/40 border border-border/40">
                    <button
                        type="button"
                        class="py-1.5 px-2 rounded-md text-xs font-semibold transition-all text-center flex items-center justify-center gap-1.5"
                        :class="form.payment_mode === 'Cash'
                            ? 'bg-background shadow-2xs text-primary font-bold ring-1 ring-border/50'
                            : 'text-muted-foreground hover:text-foreground hover:bg-background/40'"
                        @click="form.payment_mode = 'Cash'"
                    >
                        <span>💵</span>
                        <span>Cash</span>
                    </button>
                    <button
                        type="button"
                        class="py-1.5 px-2 rounded-md text-xs font-semibold transition-all text-center flex items-center justify-center gap-1.5"
                        :class="form.payment_mode === 'Insurance'
                            ? 'bg-background shadow-2xs text-primary font-bold ring-1 ring-border/50'
                            : 'text-muted-foreground hover:text-foreground hover:bg-background/40'"
                        @click="form.payment_mode = 'Insurance'"
                    >
                        <span>🛡️</span>
                        <span>Insurance / NHIF</span>
                    </button>
                    <button
                        type="button"
                        class="py-1.5 px-2 rounded-md text-xs font-semibold transition-all text-center flex items-center justify-center gap-1.5"
                        :class="form.payment_mode === 'Prepaid' || form.visit_type === 'Treatment_Followup'
                            ? 'bg-background shadow-2xs text-primary font-bold ring-1 ring-border/50'
                            : 'text-muted-foreground hover:text-foreground hover:bg-background/40'"
                        @click="form.payment_mode = 'Prepaid'"
                    >
                        <span>🔁</span>
                        <span>Prepaid / Follow-up</span>
                    </button>
                </div>
            </div>

            <!-- 6. Step 4: Priority (Radio Group - Minimal) -->
            <div class="space-y-1.5">
                <label class="text-[10.5px] font-bold text-muted-foreground uppercase tracking-wider">
                    4. Priority Level
                </label>
                <div class="flex items-center gap-6 pt-0.5" role="radiogroup" aria-label="Priority Level">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="form.priority"
                            type="radio"
                            value="Routine"
                            name="checkin_priority"
                            class="w-3.5 h-3.5 text-emerald-600 border-border focus:ring-emerald-500 focus:ring-offset-0 cursor-pointer"
                        />
                        <div class="flex items-center gap-1.5 text-xs" :class="form.priority === 'Routine' ? 'font-bold text-foreground' : 'text-muted-foreground hover:text-foreground'">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span>Routine</span>
                        </div>
                    </label>

                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="form.priority"
                            type="radio"
                            value="Urgent"
                            name="checkin_priority"
                            class="w-3.5 h-3.5 text-amber-600 border-border focus:ring-amber-500 focus:ring-offset-0 cursor-pointer"
                        />
                        <div class="flex items-center gap-1.5 text-xs" :class="form.priority === 'Urgent' ? 'font-bold text-amber-600 dark:text-amber-400' : 'text-muted-foreground hover:text-foreground'">
                            <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                            <span>Urgent</span>
                        </div>
                    </label>

                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="form.priority"
                            type="radio"
                            value="Emergency"
                            name="checkin_priority"
                            class="w-3.5 h-3.5 text-rose-600 border-border focus:ring-rose-500 focus:ring-offset-0 cursor-pointer"
                        />
                        <div class="flex items-center gap-1.5 text-xs" :class="form.priority === 'Emergency' ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-muted-foreground hover:text-foreground'">
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse shrink-0"></span>
                            <span>Emergency (STAT)</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 7. Step 5: Clinical Note (Full Row) -->
            <div class="space-y-1">
                <label class="text-[10.5px] font-bold text-muted-foreground uppercase tracking-wider">
                    5. Clinical Note (Optional)
                </label>
                <Input
                    v-model="form.reason"
                    type="text"
                    placeholder="e.g. Sindano ya Nje (Depo), Wound dressing change Day 2, Routine blood sugar check..."
                    class="text-xs h-8 bg-background border-border/80 rounded-md shadow-2xs w-full"
                />
            </div>

            <!-- Tanzania NHIF Warning for Lab -->
            <div
                v-if="form.selected_desk === 'Lab' && form.payment_mode === 'Insurance'"
                class="p-2 rounded-md bg-sky-50 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800 text-[10.5px] text-sky-800 dark:text-sky-300 flex items-start gap-1.5"
            >
                <Info class="w-3.5 h-3.5 text-sky-600 shrink-0 mt-0.5" />
                <span>
                    <strong>NHIF Notice:</strong> Direct walk-in lab claims require a signed doctor encounter. Switch to <strong>Cash</strong> if this is a self-requested screening test.
                </span>
            </div>

            <!-- 8. Footer -->
            <div class="flex items-center justify-between pt-2.5 border-t border-border/40">
                <div class="text-[11px] text-muted-foreground truncate max-w-[55%]">
                    <span v-if="activePatient" class="text-foreground font-medium truncate">
                        Route <strong>{{ activePatient.first_name }} {{ activePatient.last_name }}</strong> → <strong>{{ form.selected_desk }} Desk</strong>
                        <span v-if="estimatedFee.amount > 0" class="text-primary font-mono font-bold ml-1">
                            (Bill: TZS {{ estimatedFee.amount.toLocaleString() }})
                        </span>
                    </span>
                    <span v-else class="text-muted-foreground">Select a patient above.</span>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" class="h-7.5 text-xs px-2.5" @click="handleClose">
                        Cancel
                    </Button>
                    <Button
                        variant="default"
                        size="sm"
                        class="gap-1.5 font-bold shadow-2xs h-7.5 text-xs px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                        :disabled="(!activePatient && !form.patient_id) || isSubmitting"
                        @click="submitCheckIn"
                    >
                        <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
                        <CheckCircle2 v-else class="w-3.5 h-3.5" />
                        <span>Issue Ticket & Bill</span>
                    </Button>
                </div>
            </div>
        </div>
    </Modal>
</template>
