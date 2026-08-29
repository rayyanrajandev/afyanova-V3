<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Scissors, 
    Syringe, 
    Activity, 
    Clock, 
    Building2, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    Search, 
    Plus, 
    Loader2, 
    Check, 
    X, 
    FileText, 
    Calendar, 
    Users, 
    ShieldCheck, 
    Bandage, 
    Sparkles, 
    ArrowUpRight, 
    Layers, 
    Bed, 
    HeartPulse,
    SlidersHorizontal,
    PackageCheck,
    Lock
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';

// UI Primitives
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import Table from '@/Components/ui/Table.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import AfyaDatePicker from '@/Components/Afya/AfyaDatePicker.vue';

import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    procedureCatalogs: {
        type: Array,
        default: () => [],
    },
    injectionQueue: {
        type: Array,
        default: () => [],
    },
    dressingQueue: {
        type: Array,
        default: () => [],
    },
    minorSurgeryQueue: {
        type: Array,
        default: () => [],
    },
    surgicalBookings: {
        type: Array,
        default: () => [],
    },
    operatingSuites: {
        type: Array,
        default: () => [],
    },
    completedExecutions: {
        type: Array,
        default: () => [],
    },
    consumableProducts: {
        type: Array,
        default: () => [],
    },
    wards: {
        type: Array,
        default: () => [],
    },
    encountersForProcedures: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({
            total_procedures_today: 0,
            pending_injections: 0,
            pending_dressings: 0,
            pending_minor_surgeries: 0,
            in_theatre_surgeries: 0,
            pacu_recovery_bay: 0,
            emergency_procedures: 0,
        }),
    },
});

const { preferences, openContext } = useWorkspacePreferences();

// Sub-workstations: injections, dressing, minor_surgery, theatre, checklist, pacu, catalogue
const activeSection = ref('injections');
const selectedCategoryFilter = ref('all');
const searchQuery = ref('');

const selectedRecord = ref(
    props.injectionQueue?.[0] || props.dressingQueue?.[0] || props.minorSurgeryQueue?.[0] || props.surgicalBookings?.[0] || null
);

// Selection handler
const selectRecord = (item) => {
    selectedRecord.value = item;
    openContext();
};

// Modals State
const showOrderModal = ref(false);
const isOrdering = ref(false);
const orderForm = ref({
    encounter_id: '',
    procedure_catalog_id: '',
    priority: 'Routine',
    clinical_indication: '',
});

// Modal 1: Execute Injection
const showInjectionModal = ref(false);
const executeInjectionOrder = ref(null);
const isExecutingInjection = ref(false);
const injectionForm = ref({
    execution_setting: 'InjectionRoom',
    medication_source: 'FacilityPharmacy', // 'FacilityPharmacy' | 'PatientSupplied'
    treatment_plan_type: 'Single', // 'Single' | 'Multi'
    is_multi_dose: false,
    total_doses: 1,
    current_dose_number: 1,
    is_course_completed: true,
    remaining_doses: 0,
    injection_site: 'Right Deltoid (IM)',
    findings_and_technique: '',
    adverse_reaction_monitored: true,
    post_procedure_instructions: 'Single dose administered. Observe for 15 minutes for any adverse reaction.',
    follow_up_date: new Date().toISOString().split('T')[0],
    next_action: 'discharge',
    consumables: [],
});

// Modal 2: Execute Wound Dressing
const showDressingModal = ref(false);
const executeDressingOrder = ref(null);
const isExecutingDressing = ref(false);
const dressingForm = ref({
    execution_setting: 'DressingRoom',
    wound_condition: 'Clean',
    cleansing_solution: 'Normal Saline 0.9%',
    dressing_applied: 'Sterile Gauze Swabs 10x10cm (Pack)',
    findings_and_technique: 'Cleaned wound bed with normal saline and povidone-iodine. Applied sterile non-adherent dressing.',
    post_procedure_instructions: 'Keep dressing intact and dry for 72 hours. Return immediately if pain or fever occurs.',
    follow_up_date: new Date(Date.now() + 3 * 86400000).toISOString().split('T')[0],
    next_action: 'discharge',
    consumables: [],
});

// Modal 3: Execute Minor Surgery
const showMinorSurgeryModal = ref(false);
const executeMinorSurgeryOrder = ref(null);
const isExecutingMinorSurgery = ref(false);
const minorSurgeryForm = ref({
    execution_setting: 'MinorTheatre',
    consent_obtained: true,
    anesthesia_type: 'Local',
    anesthesia_agent: 'Lignocaine 2% Plain',
    anesthesia_volume_ml: 5,
    suture_material: 'Nylon 3-0',
    suture_count: 3,
    suture_removal_date: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
    findings_and_technique: 'Infiltrated local anesthesia. Irrigated and debrided wound edges. Closed with interrupted sutures.',
    post_procedure_instructions: 'Keep dry for 48 hours. Return for suture removal in 7 days.',
    follow_up_date: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
    next_action: 'discharge',
    consumables: [],
});

// Theatre & PACU Modals
const showBookSurgeryModal = ref(false);
const isBookingSurgery = ref(false);
const bookSurgeryForm = ref({
    procedure_order_id: '',
    operating_suite_id: '',
    scheduled_start: new Date(Date.now() + 3600000).toISOString().slice(0, 16),
    scheduled_end: new Date(Date.now() + 3 * 3600000).toISOString().slice(0, 16),
    urgency: 'Elective',
});

const showWhoChecklistModal = ref(false);
const whoChecklistRecord = ref(null);
const isSavingChecklist = ref(false);
const whoChecklistForm = ref({
    stage: 'time_out',
    sponge_and_needle_count_correct: true,
    specimens_labeled_correctly: true,
});

const showPacuModal = ref(false);
const pacuBookingRecord = ref(null);
const isSavingPacu = ref(false);
const pacuForm = ref({
    consciousness_score: 2,
    activity_score: 2,
    respiration_score: 2,
    circulation_score: 2,
    oxygen_saturation_score: 2,
    destination_ward_id: '',
    notes: 'Patient awake, hemodynamically stable, Aldrete score 10/10.',
});

// Category list
const categories = computed(() => {
    const cats = new Set(props.procedureCatalogs.map(p => p.category).filter(Boolean));
    return ['all', ...Array.from(cats)];
});

// Helper for dose calculations on injection orders
const getOrderTotalDoses = (order) => {
    if (!order) return 1;
    const ind = (order.clinical_indication || '').toLowerCase();
    if (ind.includes('stat') || ind.includes('single') || ind.includes('1 dose') || ind.includes('1-off')) return 1;
    const match = ind.match(/(\d+)\s*(days|doses|injections|x)/i);
    if (match) return parseInt(match[1], 10);
    if (order.executions && order.executions.length > 0) {
        return Math.max(order.executions.length + 1, 1);
    }
    return 1;
};

const getCompletedDosesCount = (order) => {
    return order?.executions?.length || 0;
};

// Payment & Billing Clearance Check for Procedure Orders
const getProcedurePaymentStatus = (order) => {
    if (!order) return { isPaid: true, label: 'Unbilled', type: 'unbilled', classes: 'bg-muted text-muted-foreground border-border/60' };

    // 1. Check if patient has active insurance policy (NHIF / Private)
    const policies = order.patient?.policies || [];
    const activePolicy = policies.find(p => p.status === 'Active' || p.status === 'Verified');
    if (activePolicy) {
        return {
            isPaid: true,
            label: `${activePolicy.insurance_company?.name || 'NHIF'} Covered`,
            type: 'insured',
            classes: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
        };
    }

    // 2. If STAT / Emergency priority, allow immediate clinical procedure execution
    if (order.priority === 'Emergency' || order.priority === 'STAT') {
        return {
            isPaid: true,
            label: 'Emergency STAT Bypass',
            type: 'emergency',
            classes: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
        };
    }

    // 3. Check encounter invoices for cash settlement
    const invoices = order.encounter?.invoices || [];
    if (invoices.length === 0) {
        return {
            isPaid: true,
            label: 'Prepaid / Cleared',
            type: 'unbilled',
            classes: 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-800',
        };
    }

    const unpaidInvoices = invoices.filter(inv => inv.status !== 'Paid');
    if (unpaidInvoices.length === 0) {
        const total = invoices.reduce((sum, inv) => sum + (parseFloat(inv.total_amount) || 0), 0);
        return {
            isPaid: true,
            label: `Paid (TZS ${total.toLocaleString()})`,
            type: 'paid',
            classes: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800 font-medium',
        };
    }

    const unpaidTotal = unpaidInvoices.reduce((acc, inv) => acc + (Number(inv.total_amount) - Number(inv.paid_amount || 0)), 0);
    return {
        isPaid: false,
        label: `Unpaid TZS ${unpaidTotal.toLocaleString()} at Cashier`,
        type: 'unpaid',
        classes: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800 font-bold',
    };
};

// Filtered Lists per Sub-Workstation
const filteredInjectionQueue = computed(() => {
    return props.injectionQueue.filter(order => {
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = order.patient ? `${order.patient.first_name} ${order.patient.last_name} ${order.patient.primary_mrn}`.toLowerCase() : '';
            const proc = (order.catalog?.name || '').toLowerCase();
            const ordNum = (order.order_number || '').toLowerCase();
            return pat.includes(q) || proc.includes(q) || ordNum.includes(q);
        }
        return true;
    });
});

const filteredDressingQueue = computed(() => {
    return props.dressingQueue.filter(order => {
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = order.patient ? `${order.patient.first_name} ${order.patient.last_name} ${order.patient.primary_mrn}`.toLowerCase() : '';
            const proc = (order.catalog?.name || '').toLowerCase();
            const ordNum = (order.order_number || '').toLowerCase();
            return pat.includes(q) || proc.includes(q) || ordNum.includes(q);
        }
        return true;
    });
});

const filteredMinorSurgeryQueue = computed(() => {
    return props.minorSurgeryQueue.filter(order => {
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = order.patient ? `${order.patient.first_name} ${order.patient.last_name} ${order.patient.primary_mrn}`.toLowerCase() : '';
            const proc = (order.catalog?.name || '').toLowerCase();
            const ordNum = (order.order_number || '').toLowerCase();
            return pat.includes(q) || proc.includes(q) || ordNum.includes(q);
        }
        return true;
    });
});

const filteredSurgicalBookings = computed(() => {
    return props.surgicalBookings.filter(booking => {
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = booking.order?.patient ? `${booking.order.patient.first_name} ${booking.order.patient.last_name}`.toLowerCase() : '';
            const bNum = (booking.booking_number || '').toLowerCase();
            const proc = (booking.order?.catalog?.name || '').toLowerCase();
            return pat.includes(q) || bNum.includes(q) || proc.includes(q);
        }
        return true;
    });
});

const filteredCatalogs = computed(() => {
    return props.procedureCatalogs.filter(cat => {
        if (selectedCategoryFilter.value !== 'all' && cat.category !== selectedCategoryFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            return cat.name.toLowerCase().includes(q) || cat.procedure_code.toLowerCase().includes(q);
        }
        return true;
    });
});

// Modal Openers
const openOrderModal = () => {
    const firstEnc = props.encountersForProcedures?.[0];
    const firstCat = props.procedureCatalogs?.[0];
    orderForm.value = {
        encounter_id: firstEnc ? firstEnc.id : '',
        procedure_catalog_id: firstCat ? firstCat.id : '',
        priority: 'Routine',
        clinical_indication: '',
    };
    showOrderModal.value = true;
};

// 1. OPEN INJECTION MODAL
const openInjectionModal = (order) => {
    executeInjectionOrder.value = order;
    const completedDoses = getCompletedDosesCount(order);
    const totalDosesFromOrder = getOrderTotalDoses(order);
    const isExplicitMulti = totalDosesFromOrder > 1;
    const treatmentPlanType = isExplicitMulti ? 'Multi' : 'Single';
    const totalDoses = isExplicitMulti ? totalDosesFromOrder : 1;
    const currentDoseNumber = completedDoses + 1;
    const isLastDose = currentDoseNumber >= totalDoses;

    injectionForm.value = {
        execution_setting: 'InjectionRoom',
        medication_source: 'FacilityPharmacy',
        treatment_plan_type: treatmentPlanType,
        is_multi_dose: isExplicitMulti,
        total_doses: totalDoses,
        current_dose_number: currentDoseNumber,
        is_course_completed: isLastDose,
        remaining_doses: Math.max(0, totalDoses - currentDoseNumber),
        injection_site: 'Right Deltoid (IM)',
        findings_and_technique: `[Clinic Stock / Ndani] Administered ${treatmentPlanType === 'Single' ? 'STAT Single Dose' : `Dose #${currentDoseNumber} of ${totalDoses}`} via aseptic technique. Site inspected, no adverse reaction observed.`,
        adverse_reaction_monitored: true,
        post_procedure_instructions: isLastDose ? 'Treatment completed. Observe for 15 minutes for any adverse reaction.' : `Return tomorrow for Dose #${currentDoseNumber + 1} of ${totalDoses}. Keep injection site clean.`,
        follow_up_date: new Date(Date.now() + (isLastDose ? 0 : 1) * 86400000).toISOString().split('T')[0],
        next_action: 'discharge',
        consumables: [
            { item_name: 'Disposable Syringe 5ml with 21G Needle', batch_id: props.consumableProducts?.[0]?.id || null, quantity_used: 1, unit_price: 500, is_billed_to_patient: true },
            { item_name: 'Alcohol Prep Swab (70% Isopropyl)', batch_id: null, quantity_used: 2, unit_price: 200, is_billed_to_patient: true },
            { item_name: 'Disposable Examination Gloves (Pair)', batch_id: props.consumableProducts?.[1]?.id || null, quantity_used: 1, unit_price: 1000, is_billed_to_patient: true },
        ],
    };
    showInjectionModal.value = true;
};

const onInjectionPlanTypeChange = (type) => {
    injectionForm.value.treatment_plan_type = type;
    if (type === 'Single') {
        injectionForm.value.is_multi_dose = false;
        injectionForm.value.total_doses = 1;
        injectionForm.value.current_dose_number = 1;
        injectionForm.value.remaining_doses = 0;
        injectionForm.value.is_course_completed = true;
    } else {
        injectionForm.value.is_multi_dose = true;
        if (injectionForm.value.total_doses <= 1) {
            injectionForm.value.total_doses = 5;
        }
        injectionForm.value.remaining_doses = Math.max(0, injectionForm.value.total_doses - injectionForm.value.current_dose_number);
        injectionForm.value.is_course_completed = injectionForm.value.current_dose_number >= injectionForm.value.total_doses;
    }
    updateInjectionNotes();
};

const onInjectionTotalDosesChange = (doses) => {
    injectionForm.value.total_doses = Math.max(1, parseInt(doses, 10) || 1);
    injectionForm.value.remaining_doses = Math.max(0, injectionForm.value.total_doses - injectionForm.value.current_dose_number);
    injectionForm.value.is_course_completed = injectionForm.value.current_dose_number >= injectionForm.value.total_doses;
    updateInjectionNotes();
};

const onMedicationSourceChange = (source) => {
    injectionForm.value.medication_source = source;
    updateInjectionNotes();
};

const updateInjectionNotes = () => {
    const sourcePrefix = injectionForm.value.medication_source === 'PatientSupplied'
        ? '[Sindano ya Nje / Patient-Supplied - Verified Prescription & Intact Seal] '
        : '[Clinic Pharmacy Stock / Dawa ya Ndani] ';

    const doseText = injectionForm.value.treatment_plan_type === 'Single'
        ? 'Administered STAT Single Dose via aseptic technique.'
        : `Administered Dose #${injectionForm.value.current_dose_number} of ${injectionForm.value.total_doses} via aseptic technique.`;

    injectionForm.value.findings_and_technique = `${sourcePrefix}${doseText} Site: ${injectionForm.value.injection_site}. No adverse reaction observed.`;

    if (injectionForm.value.treatment_plan_type === 'Single' || injectionForm.value.is_course_completed) {
        injectionForm.value.post_procedure_instructions = 'Treatment completed. Observe for 15 minutes for any adverse reaction.';
    } else {
        injectionForm.value.post_procedure_instructions = `Return tomorrow for Dose #${injectionForm.value.current_dose_number + 1} of ${injectionForm.value.total_doses}. Keep injection site clean.`;
    }
};

// 2. OPEN DRESSING MODAL
const openDressingModal = (order) => {
    executeDressingOrder.value = order;
    dressingForm.value = {
        execution_setting: 'DressingRoom',
        wound_condition: 'Clean',
        cleansing_solution: 'Normal Saline 0.9%',
        dressing_applied: 'Sterile Gauze Swabs 10x10cm (Pack)',
        findings_and_technique: `Cleaned wound bed with Normal Saline 0.9% and Povidone-Iodine. Wound margins intact. Applied sterile non-adherent dressing pack.`,
        post_procedure_instructions: 'Keep dressing clean, dry, and intact for 72 hours. Return immediately if redness or fever develops.',
        follow_up_date: new Date(Date.now() + 3 * 86400000).toISOString().split('T')[0],
        next_action: 'discharge',
        consumables: [
            { item_name: 'Sterile Gauze Swabs 10x10cm (Pack)', batch_id: props.consumableProducts?.[0]?.id || null, quantity_used: 2, unit_price: 1500, is_billed_to_patient: true },
            { item_name: 'Surgical Latex Gloves Size 7.5', batch_id: props.consumableProducts?.[1]?.id || null, quantity_used: 1, unit_price: 2000, is_billed_to_patient: true },
            { item_name: 'Povidone-Iodine Antiseptic 10% (15ml)', batch_id: null, quantity_used: 1, unit_price: 1500, is_billed_to_patient: true },
        ],
    };
    showDressingModal.value = true;
};

// 3. OPEN MINOR SURGERY MODAL
const openMinorSurgeryModal = (order) => {
    executeMinorSurgeryOrder.value = order;
    minorSurgeryForm.value = {
        execution_setting: 'MinorTheatre',
        consent_obtained: true,
        anesthesia_type: 'Local',
        anesthesia_agent: 'Lignocaine 2% Plain',
        anesthesia_volume_ml: 5,
        suture_material: 'Nylon 3-0',
        suture_count: 3,
        suture_removal_date: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
        findings_and_technique: `Infiltrated 5ml Lignocaine 2% locally. Irrigated and debrided tissue margins. Closed with 3 interrupted Nylon 3-0 sutures under aseptic technique. Good hemostasis achieved.`,
        post_procedure_instructions: 'Keep wound dry for 48 hours. Return for suture removal in 7 days.',
        follow_up_date: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
        next_action: 'discharge',
        consumables: [
            { item_name: 'Surgical Blade No. 15', batch_id: props.consumableProducts?.[0]?.id || null, quantity_used: 1, unit_price: 1000, is_billed_to_patient: true },
            { item_name: 'Nylon Suture 3-0 with Reverse Cutting Needle', batch_id: null, quantity_used: 1, unit_price: 4500, is_billed_to_patient: true },
            { item_name: 'Lignocaine Injection 2% (20ml)', batch_id: null, quantity_used: 1, unit_price: 3000, is_billed_to_patient: true },
            { item_name: 'Surgical Latex Gloves Size 7.5', batch_id: props.consumableProducts?.[1]?.id || null, quantity_used: 1, unit_price: 2000, is_billed_to_patient: true },
        ],
    };
    showMinorSurgeryModal.value = true;
};

const openBookSurgeryModal = (order) => {
    const firstSuite = props.operatingSuites?.[0];
    bookSurgeryForm.value = {
        procedure_order_id: order.id,
        operating_suite_id: firstSuite ? firstSuite.id : '',
        scheduled_start: new Date(Date.now() + 3600000).toISOString().slice(0, 16),
        scheduled_end: new Date(Date.now() + 3 * 3600000).toISOString().slice(0, 16),
        urgency: order.priority === 'Emergency' ? 'Emergency' : 'Elective',
    };
    showBookSurgeryModal.value = true;
};

const openWhoChecklistModal = (booking) => {
    whoChecklistRecord.value = booking.whoChecklist || { surgical_booking_id: booking.id };
    whoChecklistForm.value = {
        stage: !booking.whoChecklist?.sign_in_completed_at ? 'sign_in' : (!booking.whoChecklist?.time_out_completed_at ? 'time_out' : 'sign_out'),
        sponge_and_needle_count_correct: true,
        specimens_labeled_correctly: true,
    };
    showWhoChecklistModal.value = true;
};

const openPacuModal = (booking) => {
    pacuBookingRecord.value = booking;
    pacuForm.value = {
        consciousness_score: 2,
        activity_score: 2,
        respiration_score: 2,
        circulation_score: 2,
        oxygen_saturation_score: 2,
        destination_ward_id: props.wards?.[0]?.id || '',
        notes: 'Patient fully conscious, stable vitals, Aldrete 10/10. Ready for step-down care.',
    };
    showPacuModal.value = true;
};

// Submissions
const submitOrder = () => {
    if (!orderForm.value.encounter_id || !orderForm.value.procedure_catalog_id) return;
    isOrdering.value = true;
    router.post(route('procedures.orders.store'), orderForm.value, {
        onFinish: () => {
            isOrdering.value = false;
            showOrderModal.value = false;
        }
    });
};

const submitInjection = () => {
    if (!executeInjectionOrder.value) return;
    isExecutingInjection.value = true;
    router.post(route('procedures.orders.execute', executeInjectionOrder.value.id), injectionForm.value, {
        onFinish: () => {
            isExecutingInjection.value = false;
            showInjectionModal.value = false;
        }
    });
};

const submitDressing = () => {
    if (!executeDressingOrder.value) return;
    isExecutingDressing.value = true;
    router.post(route('procedures.orders.execute', executeDressingOrder.value.id), dressingForm.value, {
        onFinish: () => {
            isExecutingDressing.value = false;
            showDressingModal.value = false;
        }
    });
};

const submitMinorSurgery = () => {
    if (!executeMinorSurgeryOrder.value) return;
    isExecutingMinorSurgery.value = true;
    router.post(route('procedures.orders.execute', executeMinorSurgeryOrder.value.id), minorSurgeryForm.value, {
        onFinish: () => {
            isExecutingMinorSurgery.value = false;
            showMinorSurgeryModal.value = false;
        }
    });
};

const submitBookSurgery = () => {
    if (!bookSurgeryForm.value.procedure_order_id) return;
    isBookingSurgery.value = true;
    router.post(route('procedures.orders.book-surgery', bookSurgeryForm.value.procedure_order_id), bookSurgeryForm.value, {
        onFinish: () => {
            isBookingSurgery.value = false;
            showBookSurgeryModal.value = false;
            activeSection.value = 'theatre';
        }
    });
};

const submitWhoChecklist = () => {
    if (!whoChecklistRecord.value?.id) return;
    isSavingChecklist.value = true;
    router.post(route('procedures.who-checklists.save', whoChecklistRecord.value.id), whoChecklistForm.value, {
        onFinish: () => {
            isSavingChecklist.value = false;
            showWhoChecklistModal.value = false;
        }
    });
};

const submitPacuScore = () => {
    if (!pacuBookingRecord.value) return;
    isSavingPacu.value = true;
    router.post(route('procedures.bookings.pacu', pacuBookingRecord.value.id), pacuForm.value, {
        onFinish: () => {
            isSavingPacu.value = false;
            showPacuModal.value = false;
        }
    });
};

// Procedure Catalog & Tariff Management
const showCreateCatalogModal = ref(false);
const showEditCatalogModal = ref(false);
const targetCatalogItem = ref(null);
const isCatalogSubmitting = ref(false);

const createCatalogForm = ref({
    procedure_code: '',
    name: '',
    category: 'Minor',
    tier_level: 'Tier1_Minor',
    default_duration_minutes: 30,
    standard_price: 15000,
    requires_consent: false,
    requires_anesthesia: false,
});

const editCatalogForm = ref({
    name: '',
    category: 'Minor',
    tier_level: 'Tier1_Minor',
    default_duration_minutes: 30,
    standard_price: 0,
    requires_consent: false,
    requires_anesthesia: false,
    is_active: true,
});

const openCreateCatalogModal = () => {
    createCatalogForm.value = {
        procedure_code: `PROC-${Math.floor(100 + Math.random() * 900)}`,
        name: '',
        category: 'Minor',
        tier_level: 'Tier1_Minor',
        default_duration_minutes: 30,
        standard_price: 15000,
        requires_consent: false,
        requires_anesthesia: false,
    };
    showCreateCatalogModal.value = true;
};

const submitCreateCatalog = () => {
    isCatalogSubmitting.value = true;
    router.post(route('procedures.catalog.store'), createCatalogForm.value, {
        onFinish: () => {
            isCatalogSubmitting.value = false;
            showCreateCatalogModal.value = false;
        }
    });
};

const openEditCatalogModal = (cat) => {
    targetCatalogItem.value = cat;
    editCatalogForm.value = {
        name: cat.name,
        category: cat.category,
        tier_level: cat.tier_level || 'Tier1_Minor',
        default_duration_minutes: cat.default_duration_minutes || 30,
        standard_price: Number(cat.standard_price || cat.base_price || 0),
        requires_consent: !!cat.requires_consent,
        requires_anesthesia: !!cat.requires_anesthesia,
        is_active: cat.is_active !== undefined ? !!cat.is_active : true,
    };
    showEditCatalogModal.value = true;
};

const submitEditCatalog = () => {
    if (!targetCatalogItem.value) return;
    isCatalogSubmitting.value = true;
    router.put(route('procedures.catalog.update', targetCatalogItem.value.id), editCatalogForm.value, {
        onFinish: () => {
            isCatalogSubmitting.value = false;
            showEditCatalogModal.value = false;
        }
    });
};

const formatCurrency = (val) => Number(val || 0).toLocaleString('en-US');
const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
const formatTime = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Clinical Procedures & Surgical Care Workstation — AfyaNova" />

    <AfyaShell active-module="procedures">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Treatment Units & Specialized Desks -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Procedures & OT"
                    :icon="Scissors"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Treatment Desks
                    </div>
                    
                    <!-- 1. Injections Desk -->
                    <AfyaSidebarItem
                        label="Injections (Sindano)"
                        :icon="Syringe"
                        :badge="metrics.pending_injections"
                        :active="activeSection === 'injections'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'injections'"
                    />

                    <!-- 2. Wound Care & Dressing Desk -->
                    <AfyaSidebarItem
                        label="Wound Care (Vidonda)"
                        :icon="Bandage"
                        :badge="metrics.pending_dressings"
                        :active="activeSection === 'dressing'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'dressing'"
                    />

                    <!-- 3. Minor Surgery Desk -->
                    <AfyaSidebarItem
                        label="Minor Procedures"
                        :icon="Scissors"
                        :badge="metrics.pending_minor_surgeries"
                        :active="activeSection === 'minor_surgery'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'minor_surgery'"
                    />
                    
                    <div v-if="state !== 'collapsed'" class="pt-2 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border/40 mt-1">
                        Surgical Operating Theatres
                    </div>

                    <!-- 4. Operating Suites -->
                    <AfyaSidebarItem
                        label="Operating Suites"
                        :icon="Building2"
                        :badge="metrics.in_theatre_surgeries"
                        :active="activeSection === 'theatre'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'theatre'"
                    />

                    <!-- 5. WHO Safety Checklists -->
                    <AfyaSidebarItem
                        label="WHO Safety Checklists"
                        :icon="ShieldCheck"
                        :badge="surgicalBookings.length"
                        :active="activeSection === 'checklist'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'checklist'"
                    />

                    <!-- 6. PACU Recovery Bay -->
                    <AfyaSidebarItem
                        label="PACU Recovery Bay"
                        :icon="HeartPulse"
                        :badge="metrics.pacu_recovery_bay"
                        :active="activeSection === 'pacu'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'pacu'"
                    />

                    <!-- 7. Master Catalog -->
                    <AfyaSidebarItem
                        label="Procedure Tariffs Catalog"
                        :icon="FileText"
                        :badge="procedureCatalogs.length"
                        :active="activeSection === 'catalogue'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'catalogue'"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN WORK AREA -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Procedures', href: route('procedures.workspace') },
                        { 
                            label: activeSection === 'injections' ? 'Injections & Medication Administration (Chumba cha Sindano)' : 
                                  (activeSection === 'dressing' ? 'Wound Care & Sterile Dressing (Chumba cha Vidonda)' : 
                                  (activeSection === 'minor_surgery' ? 'Minor Surgical Procedures (Upasuaji Mdogo)' : 
                                  (activeSection === 'theatre' ? 'Operating Theatre Suites & Surgical Schedule' : 
                                  (activeSection === 'checklist' ? 'WHO Surgical Safety Checklist Station' : 
                                  (activeSection === 'pacu' ? 'PACU Post-Anesthesia Recovery Bay' : 'Master Procedure Catalog & Tariffs'))))), 
                            active: true 
                        }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span v-if="metrics.emergency_procedures > 0" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800 animate-pulse">
                                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                                <span>{{ metrics.emergency_procedures }} Urgent / Emergency Procedures</span>
                            </span>

                            <Button
                                v-if="can.orderProcedure"
                                variant="default"
                                size="sm"
                                class="h-7.5 px-3 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="openOrderModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Order Procedure</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Top Enterprise Metric Strip -->
                        <div class="grid grid-cols-2 sm:grid-cols-6 gap-2.5">
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Total Today</div>
                                <div class="text-base font-bold text-foreground font-mono">{{ metrics.total_procedures_today }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Injections Queue</div>
                                <div class="text-base font-bold text-rose-600 dark:text-rose-400 font-mono">{{ metrics.pending_injections }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Wound Dressing</div>
                                <div class="text-base font-bold text-amber-600 dark:text-amber-400 font-mono">{{ metrics.pending_dressings }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Minor Surgery</div>
                                <div class="text-base font-bold text-sky-600 dark:text-sky-400 font-mono">{{ metrics.pending_minor_surgeries }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Theatre Active</div>
                                <div class="text-base font-bold text-primary font-mono">{{ metrics.in_theatre_surgeries }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">PACU Bay</div>
                                <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ metrics.pacu_recovery_bay }}</div>
                            </div>
                        </div>

                        <!-- Search Strip -->
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-card p-2 rounded-lg shadow-2xs">
                            <div class="flex items-center gap-1.5 flex-1 max-w-sm">
                                <SearchInput
                                    v-model="searchQuery"
                                    placeholder="Search by patient name, MRN, or order number..."
                                    size="sm"
                                />
                            </div>
                        </div>

                        <!-- ================= VIEW 1: INJECTIONS DESK (CHUMBA CHA SINDANO) ================= -->
                        <div v-if="activeSection === 'injections'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-rose-50/40 dark:bg-rose-950/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-rose-950 dark:text-rose-200 uppercase tracking-wider">
                                        <Syringe class="w-3.5 h-3.5 text-rose-600" />
                                        <span>Injections & Medication Administration Worklist ({{ filteredInjectionQueue.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Order #</TableHead>
                                            <TableHead class="py-1 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1 px-3">Prescribed Injection</TableHead>
                                            <TableHead class="py-1 px-3">Dosing Schedule & Progress</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Priority</TableHead>
                                            <TableHead class="py-1 px-3">Prescriber</TableHead>
                                            <TableHead class="py-1 px-3">Billing Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[170px]">Nurse Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="order in filteredInjectionQueue"
                                            :key="order.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedRecord?.id === order.id }"
                                            @click="selectRecord(order)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-rose-700 dark:text-rose-400 text-[11px] w-28">
                                                {{ order.order_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[130px] text-[11px]">
                                                    {{ order.patient?.first_name }} {{ order.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ order.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px] flex items-center gap-1.5">
                                                    <Syringe class="w-3.5 h-3.5 text-rose-500 flex-shrink-0" />
                                                    <span>{{ order.catalog?.name }}</span>
                                                </div>
                                                <div class="text-[9.5px] text-muted-foreground truncate max-w-[160px]">{{ order.clinical_indication || 'Medication Administration' }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div v-if="getOrderTotalDoses(order) > 1" class="space-y-1">
                                                    <div class="flex items-center gap-1">
                                                        <span
                                                            v-for="dose in getOrderTotalDoses(order)"
                                                            :key="dose"
                                                            class="w-3.5 h-3.5 rounded-full flex items-center justify-center text-[7.5px] font-bold transition-all shadow-2xs"
                                                            :class="{
                                                                'bg-emerald-600 text-white': dose <= getCompletedDosesCount(order),
                                                                'bg-amber-400 text-amber-950 ring-1.5 ring-amber-500 animate-pulse font-extrabold': dose === getCompletedDosesCount(order) + 1,
                                                                'bg-muted text-muted-foreground border border-border/80': dose > getCompletedDosesCount(order) + 1
                                                            }"
                                                        >
                                                            {{ dose <= getCompletedDosesCount(order) ? '✓' : dose }}
                                                        </span>
                                                    </div>
                                                    <div class="text-[9px] font-mono font-semibold text-muted-foreground">
                                                        Dose {{ getCompletedDosesCount(order) }} / {{ getOrderTotalDoses(order) }} ({{ Math.round((getCompletedDosesCount(order) / getOrderTotalDoses(order)) * 100) }}%)
                                                    </div>
                                                </div>
                                                <div v-else>
                                                    <span class="text-[9.5px] px-2 py-0.5 rounded font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                                        Single STAT Dose
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="{
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300': order.priority === 'Emergency',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': order.priority === 'Urgent',
                                                        'bg-muted text-foreground': order.priority === 'Routine',
                                                    }"
                                                >
                                                    {{ order.priority }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ order.ordering_provider ? `Dr. ${order.ordering_provider.first_name} ${order.ordering_provider.last_name}` : 'Clinician' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div 
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9.5px] border font-semibold"
                                                    :class="getProcedurePaymentStatus(order).classes"
                                                >
                                                    <Lock v-if="!getProcedurePaymentStatus(order).isPaid" class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400 shrink-0" />
                                                    <CheckCircle2 v-else-if="getProcedurePaymentStatus(order).type === 'paid'" class="w-2.5 h-2.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                                    <span>{{ getProcedurePaymentStatus(order).label }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[170px]">
                                                <Button
                                                    v-if="can.executeProcedure"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2.5 text-[10.5px] font-semibold gap-1 shadow-2xs transition-all"
                                                    :class="getProcedurePaymentStatus(order).isPaid ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-muted text-muted-foreground border border-border/80 cursor-not-allowed opacity-85 hover:bg-muted'"
                                                    :disabled="!getProcedurePaymentStatus(order).isPaid"
                                                    :title="!getProcedurePaymentStatus(order).isPaid ? 'Payment required at Cashier Desk before administering injection' : 'Administer Injection'"
                                                    @click.stop="openInjectionModal(order)"
                                                >
                                                    <Lock v-if="!getProcedurePaymentStatus(order).isPaid" class="w-3 h-3 text-rose-500" />
                                                    <Syringe v-else class="w-3 h-3" />
                                                    <span>{{ getProcedurePaymentStatus(order).isPaid ? (getOrderTotalDoses(order) > 1 ? `Administer Dose #${getCompletedDosesCount(order) + 1}` : 'Administer Injection') : 'Pending Payment' }}</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredInjectionQueue.length === 0">
                                            <TableCell colspan="8" class="text-center py-8 text-muted-foreground text-xs">
                                                No pending injection orders in queue.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 2: WOUND DRESSING DESK (CHUMBA CHA VIDONDA) ================= -->
                        <div v-if="activeSection === 'dressing'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-amber-50/40 dark:bg-amber-950/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-amber-950 dark:text-amber-200 uppercase tracking-wider">
                                        <Bandage class="w-3.5 h-3.5 text-amber-600" />
                                        <span>Wound Care & Sterile Dressing Worklist ({{ filteredDressingQueue.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Order #</TableHead>
                                            <TableHead class="py-1 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1 px-3">Dressing Procedure</TableHead>
                                            <TableHead class="py-1 px-3">Clinical Indication</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Priority</TableHead>
                                            <TableHead class="py-1 px-3">Ordering Clinician</TableHead>
                                            <TableHead class="py-1 px-3">Billing Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[160px]">Nurse Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="order in filteredDressingQueue"
                                            :key="order.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedRecord?.id === order.id }"
                                            @click="selectRecord(order)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-amber-700 dark:text-amber-400 text-[11px] w-28">
                                                 {{ order.order_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[130px] text-[11px]">
                                                    {{ order.patient?.first_name }} {{ order.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ order.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px] flex items-center gap-1.5">
                                                    <Bandage class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" />
                                                    <span>{{ order.catalog?.name }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ order.clinical_indication || 'Wound debridement and sterile dressing' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="{
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300': order.priority === 'Emergency',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': order.priority === 'Urgent',
                                                        'bg-muted text-foreground': order.priority === 'Routine',
                                                    }"
                                                >
                                                    {{ order.priority }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ order.ordering_provider ? `Dr. ${order.ordering_provider.first_name} ${order.ordering_provider.last_name}` : 'Clinician' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div 
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9.5px] border font-semibold"
                                                    :class="getProcedurePaymentStatus(order).classes"
                                                >
                                                    <Lock v-if="!getProcedurePaymentStatus(order).isPaid" class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400 shrink-0" />
                                                    <CheckCircle2 v-else-if="getProcedurePaymentStatus(order).type === 'paid'" class="w-2.5 h-2.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                                    <span>{{ getProcedurePaymentStatus(order).label }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[160px]">
                                                <Button
                                                    v-if="can.executeProcedure"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2.5 text-[10.5px] font-semibold gap-1 shadow-2xs transition-all"
                                                    :class="getProcedurePaymentStatus(order).isPaid ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-muted text-muted-foreground border border-border/80 cursor-not-allowed opacity-85 hover:bg-muted'"
                                                    :disabled="!getProcedurePaymentStatus(order).isPaid"
                                                    :title="!getProcedurePaymentStatus(order).isPaid ? 'Payment required at Cashier Desk before performing dressing' : 'Perform Dressing'"
                                                    @click.stop="openDressingModal(order)"
                                                >
                                                    <Lock v-if="!getProcedurePaymentStatus(order).isPaid" class="w-3 h-3 text-rose-500" />
                                                    <Bandage v-else class="w-3 h-3" />
                                                    <span>{{ getProcedurePaymentStatus(order).isPaid ? 'Perform Dressing' : 'Pending Payment' }}</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredDressingQueue.length === 0">
                                            <TableCell colspan="8" class="text-center py-8 text-muted-foreground text-xs">
                                                No pending wound dressing orders in queue.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 3: MINOR SURGERY DESK (UPASUAJI MDOGO) ================= -->
                        <div v-if="activeSection === 'minor_surgery'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-sky-50/40 dark:bg-sky-950/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-sky-950 dark:text-sky-200 uppercase tracking-wider">
                                        <Scissors class="w-3.5 h-3.5 text-sky-600" />
                                        <span>Minor Procedures & Bedside Surgery ({{ filteredMinorSurgeryQueue.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Order #</TableHead>
                                            <TableHead class="py-1 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1 px-3">Procedure Name</TableHead>
                                            <TableHead class="py-1 px-3">Clinical Indication</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Priority</TableHead>
                                            <TableHead class="py-1 px-3">Ordering Clinician</TableHead>
                                            <TableHead class="py-1 px-3">Billing Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[160px]">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="order in filteredMinorSurgeryQueue"
                                            :key="order.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedRecord?.id === order.id }"
                                            @click="selectRecord(order)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-sky-700 dark:text-sky-400 text-[11px] w-28">
                                                {{ order.order_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[130px] text-[11px]">
                                                    {{ order.patient?.first_name }} {{ order.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ order.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px] flex items-center gap-1.5">
                                                    <Scissors class="w-3.5 h-3.5 text-sky-500 flex-shrink-0" />
                                                    <span>{{ order.catalog?.name }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ order.clinical_indication || 'Minor bedside surgical procedure' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="{
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300': order.priority === 'Emergency',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': order.priority === 'Urgent',
                                                        'bg-muted text-foreground': order.priority === 'Routine',
                                                    }"
                                                >
                                                    {{ order.priority }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ order.ordering_provider ? `Dr. ${order.ordering_provider.first_name} ${order.ordering_provider.last_name}` : 'Clinician' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div 
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9.5px] border font-semibold"
                                                    :class="getProcedurePaymentStatus(order).classes"
                                                >
                                                    <Lock v-if="!getProcedurePaymentStatus(order).isPaid" class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400 shrink-0" />
                                                    <CheckCircle2 v-else-if="getProcedurePaymentStatus(order).type === 'paid'" class="w-2.5 h-2.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                                    <span>{{ getProcedurePaymentStatus(order).label }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[160px]">
                                                <Button
                                                    v-if="can.executeProcedure"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2.5 text-[10.5px] font-semibold gap-1 shadow-2xs transition-all"
                                                    :class="getProcedurePaymentStatus(order).isPaid ? 'bg-sky-600 hover:bg-sky-700 text-white' : 'bg-muted text-muted-foreground border border-border/80 cursor-not-allowed opacity-85 hover:bg-muted'"
                                                    :disabled="!getProcedurePaymentStatus(order).isPaid"
                                                    :title="!getProcedurePaymentStatus(order).isPaid ? 'Payment required at Cashier Desk before performing procedure' : 'Perform Procedure'"
                                                    @click.stop="openMinorSurgeryModal(order)"
                                                >
                                                    <Lock v-if="!getProcedurePaymentStatus(order).isPaid" class="w-3 h-3 text-rose-500" />
                                                    <Scissors v-else class="w-3 h-3" />
                                                    <span>{{ getProcedurePaymentStatus(order).isPaid ? 'Perform Procedure' : 'Pending Payment' }}</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredMinorSurgeryQueue.length === 0">
                                            <TableCell colspan="8" class="text-center py-8 text-muted-foreground text-xs">
                                                No pending minor surgical procedure orders in queue.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 4: THEATRE SUITES & BOOKINGS ================= -->
                        <div v-if="activeSection === 'theatre'" class="w-full space-y-3">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Building2 class="w-3.5 h-3.5 text-primary" />
                                        <span>Active Surgical Theatre Schedule ({{ filteredSurgicalBookings.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Booking #</TableHead>
                                            <TableHead class="py-1 px-3">Patient</TableHead>
                                            <TableHead class="py-1 px-3">Procedure & Theatre</TableHead>
                                            <TableHead class="py-1 px-3">Surgical Team</TableHead>
                                            <TableHead class="py-1 px-3">Scheduled Slot</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[140px]">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="booking in filteredSurgicalBookings"
                                            :key="booking.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedRecord?.id === booking.id }"
                                            @click="selectRecord(booking)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ booking.booking_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[130px] text-[11px]">
                                                    {{ booking.order?.patient?.first_name }} {{ booking.order?.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ booking.order?.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px]">{{ booking.order?.catalog?.name }}</div>
                                                <div class="text-[9.5px] text-muted-foreground flex items-center gap-1 font-mono">
                                                    <Building2 class="w-3 h-3 text-primary" />
                                                    <span>{{ booking.suite?.name || 'General OT' }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-[10.5px]">
                                                <div class="font-medium text-foreground">Surgeon: {{ booking.leadSurgeon ? `Dr. ${booking.leadSurgeon.first_name}` : 'TBD' }}</div>
                                                <div class="text-[9.5px] text-muted-foreground">Scrub: {{ booking.scrubNurse?.first_name || 'TBD' }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-[10px]">
                                                <div>{{ formatDate(booking.scheduled_start) }}</div>
                                                <div class="text-muted-foreground">{{ formatTime(booking.scheduled_start) }} - {{ formatTime(booking.scheduled_end) }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="{
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300': booking.status === 'Completed',
                                                        'bg-primary/10 text-primary animate-pulse': booking.status === 'InTheatre',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': booking.status === 'Scheduled',
                                                        'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300': booking.status === 'PACU',
                                                    }"
                                                >
                                                    {{ booking.status }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[140px]">
                                                <div class="flex items-center justify-end gap-1">
                                                    <Button
                                                        v-if="can.whoChecklist"
                                                        size="sm"
                                                        variant="outline"
                                                        class="h-6 px-2 text-[10px] gap-1 font-semibold"
                                                        @click.stop="openWhoChecklistModal(booking)"
                                                    >
                                                        <ShieldCheck class="w-3 h-3 text-primary" />
                                                        <span>WHO</span>
                                                    </Button>
                                                    <Button
                                                        v-if="can.savePacuScore && booking.status === 'PACU'"
                                                        size="sm"
                                                        variant="default"
                                                        class="h-6 px-2 text-[10px] gap-1 font-semibold bg-purple-700 hover:bg-purple-800 text-white"
                                                        @click.stop="openPacuModal(booking)"
                                                    >
                                                        <HeartPulse class="w-3 h-3" />
                                                        <span>PACU</span>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredSurgicalBookings.length === 0">
                                            <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                No surgical theatre bookings scheduled for today.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 5: WHO SAFETY CHECKLISTS ================= -->
                        <div v-if="activeSection === 'checklist'" class="w-full space-y-3">
                            <div class="w-full bg-card rounded-lg p-4 shadow-2xs space-y-3">
                                <div class="flex items-center gap-2 border-b border-border pb-2">
                                    <ShieldCheck class="w-5 h-5 text-primary" />
                                    <div>
                                        <h3 class="font-bold text-sm text-foreground">WHO Surgical Safety Checklist Station</h3>
                                        <p class="text-xs text-muted-foreground">Verification protocol required before incision and transfer</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-3 text-xs">
                                    <div class="p-3 bg-muted/20 rounded-lg border border-border/60 space-y-1">
                                        <div class="font-bold text-primary">1. Sign-In (Before Anesthesia)</div>
                                        <p class="text-[11px] text-muted-foreground">Patient identity confirmed, surgical site marked, consent signed, pulse oximeter placed.</p>
                                    </div>
                                    <div class="p-3 bg-muted/20 rounded-lg border border-border/60 space-y-1">
                                        <div class="font-bold text-primary">2. Time-Out (Before Incision)</div>
                                        <p class="text-[11px] text-muted-foreground">Entire team introduces roles. Surgeon, anesthetist, and scrub nurse confirm procedure & critical events.</p>
                                    </div>
                                    <div class="p-3 bg-muted/20 rounded-lg border border-border/60 space-y-1">
                                        <div class="font-bold text-primary">3. Sign-Out (Before Leaving OR)</div>
                                        <p class="text-[11px] text-muted-foreground">Instrument and needle counts verified complete. Specimen labeled. Key recovery concerns reviewed.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= VIEW 6: PACU RECOVERY BAY ================= -->
                        <div v-if="activeSection === 'pacu'" class="w-full space-y-3">
                            <div class="w-full bg-card rounded-lg p-4 shadow-2xs space-y-3">
                                <div class="flex items-center gap-2 border-b border-border pb-2">
                                    <HeartPulse class="w-5 h-5 text-emerald-600" />
                                    <div>
                                        <h3 class="font-bold text-sm text-foreground">PACU Post-Anesthesia Recovery Bay</h3>
                                        <p class="text-xs text-muted-foreground">Aldrete recovery scoring protocol for safe ward discharge</p>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">Select any patient currently in PACU status from the Theatre Suites view to record Aldrete score and transfer to ward.</p>
                            </div>
                        </div>

                        <!-- ================= VIEW 7: MASTER PROCEDURE CATALOG ================= -->
                        <div v-if="activeSection === 'catalogue'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <FileText class="w-3.5 h-3.5 text-primary" />
                                        <span>Master Procedure Catalog & Tariffs ({{ filteredCatalogs.length }})</span>
                                    </div>
                                    <Button
                                        v-if="can.manageCatalog || true"
                                        variant="default"
                                        size="sm"
                                        class="h-6 px-2.5 text-[10.5px] font-bold gap-1 bg-emerald-600 hover:bg-emerald-700 text-white"
                                        @click="openCreateCatalogModal"
                                    >
                                        <Plus class="w-3 h-3" />
                                        <span>Add Procedure to Catalog</span>
                                    </Button>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Code</TableHead>
                                            <TableHead class="py-1 px-3">Procedure Name</TableHead>
                                            <TableHead class="py-1 px-3">Category</TableHead>
                                            <TableHead class="py-1 px-3">Tier Level</TableHead>
                                            <TableHead class="py-1 px-3">Duration</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Standard Tariff (TZS)</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="cat in filteredCatalogs"
                                            :key="cat.id"
                                            class="h-8 border-b border-border/30 hover:bg-muted/20"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">{{ cat.procedure_code }}</TableCell>
                                            <TableCell class="py-1 px-3 font-medium text-foreground">
                                                <div>{{ cat.name }}</div>
                                                <div v-if="cat.requires_consent || cat.requires_anesthesia" class="text-[9px] text-amber-600 dark:text-amber-400">
                                                    {{ cat.requires_consent ? 'Consent Required • ' : '' }}{{ cat.requires_anesthesia ? 'Anesthesia Req.' : '' }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 capitalize text-muted-foreground">{{ cat.category }}</TableCell>
                                            <TableCell class="py-1 px-3">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="cat.tier_level === 'Tier2_MajorTheatre' || cat.tier_level === 'Tier3_Major' || cat.tier_level === 'Tier4_Specialized' ? 'bg-purple-100 text-purple-800' : 'bg-muted text-foreground'"
                                                >
                                                    {{ (cat.tier_level || 'Minor').replace(/Tier\d_/, '').replace(/_/g, ' ') }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">
                                                {{ cat.default_duration_minutes || 30 }} mins
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right font-mono font-bold text-foreground">
                                                {{ formatCurrency(cat.standard_price || cat.base_price) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-5 px-2 text-[10px] font-semibold text-primary border-primary/30 hover:bg-primary/5"
                                                    @click="openEditCatalogModal(cat)"
                                                >
                                                    Edit
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT PANEL: Patient Identity & Procedure Summary -->
            <template #context="{ state, width }">
                <AfyaContextPanel
                    title="Procedure & Case Details"
                    :state="state"
                    :width="width"
                >
                    <div v-if="selectedRecord" class="space-y-3 p-3 text-xs">
                        <div class="p-2.5 bg-muted/30 rounded-lg border border-border/60 space-y-1">
                            <div class="font-bold text-foreground text-xs">
                                {{ selectedRecord.patient?.first_name || selectedRecord.order?.patient?.first_name }} 
                                {{ selectedRecord.patient?.last_name || selectedRecord.order?.patient?.last_name }}
                            </div>
                            <div class="text-[10px] font-mono text-muted-foreground">
                                MRN: {{ selectedRecord.patient?.primary_mrn || selectedRecord.order?.patient?.primary_mrn }}
                            </div>
                        </div>

                        <div class="space-y-1 text-[11px]">
                            <div class="text-muted-foreground font-semibold">Procedure / Treatment:</div>
                            <div class="font-bold text-foreground">
                                {{ selectedRecord.catalog?.name || selectedRecord.order?.catalog?.name }}
                            </div>
                            <div class="text-[10px] text-muted-foreground">
                                Code: {{ selectedRecord.catalog?.procedure_code || selectedRecord.order?.catalog?.procedure_code }}
                            </div>
                        </div>

                        <div v-if="selectedRecord.executions && selectedRecord.executions.length > 0" class="space-y-1.5 pt-2 border-t border-border/50">
                            <div class="font-bold text-xs text-foreground flex items-center justify-between">
                                <span>Administration History</span>
                                <span class="font-mono text-[10px] text-primary">{{ selectedRecord.executions.length }} Recorded</span>
                            </div>
                            <div class="space-y-1 max-h-48 overflow-y-auto">
                                <div v-for="(ex, idx) in selectedRecord.executions" :key="idx" class="p-2 bg-muted/20 rounded border border-border/40 text-[10.5px] space-y-0.5">
                                    <div class="font-bold text-foreground flex items-center justify-between">
                                        <span>Dose / Session #{{ idx + 1 }}</span>
                                        <span class="text-[9px] font-mono text-muted-foreground">{{ formatDate(ex.completed_at) }}</span>
                                    </div>
                                    <div class="text-[10px] text-muted-foreground">{{ ex.findings_and_technique }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-6 text-center text-xs text-muted-foreground">
                        Select an order or booking to view details.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- ================= MODAL 1: ORDER NEW CLINICAL PROCEDURE ================= -->
        <Modal :show="showOrderModal" max-width="md" @close="showOrderModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Scissors class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Order Clinical Procedure / Treatment</h3>
                    </div>
                    <button @click="showOrderModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitOrder" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Select Patient Encounter *</label>
                        <select v-model="orderForm.encounter_id" required class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option value="" disabled>-- Select Encounter --</option>
                            <option v-for="enc in encountersForProcedures" :key="enc.id" :value="enc.id">
                                {{ enc.patient?.first_name }} {{ enc.patient?.last_name }} ({{ enc.patient?.primary_mrn }}) — Dr. {{ enc.provider?.last_name }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Procedure Catalog *</label>
                        <select v-model="orderForm.procedure_catalog_id" required class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option value="" disabled>-- Select Procedure --</option>
                            <option v-for="cat in procedureCatalogs" :key="cat.id" :value="cat.id">
                                [{{ cat.procedure_code }}] {{ cat.name }} (TZS {{ formatCurrency(cat.base_price) }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Priority Level</label>
                        <select v-model="orderForm.priority" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option value="Routine">Routine</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Clinical Indication & Course Instructions</label>
                        <textarea
                            v-model="orderForm.clinical_indication"
                            rows="2"
                            class="w-full rounded-md border border-input bg-background text-foreground p-2 text-xs"
                            placeholder="e.g. Inj. Diclofenac 75mg STAT OR Inj. Ceftriaxone 1g OD x 5 days"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showOrderModal = false" :disabled="isOrdering">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isOrdering || !orderForm.encounter_id || !orderForm.procedure_catalog_id">
                            <Loader2 v-if="isOrdering" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Route to Treatment Desk</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ================= MODAL 2: EXECUTE INJECTION (CHUMBA CHA SINDANO) ================= -->
        <Modal :show="showInjectionModal" max-width="3xl" @close="showInjectionModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Syringe class="w-4.5 h-4.5 text-rose-600" />
                        <h3 class="font-bold text-sm text-foreground">Administer Clinical Injection (Chumba cha Sindano)</h3>
                    </div>
                    <button @click="showInjectionModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="executeInjectionOrder" class="space-y-3 text-xs">
                    <!-- Patient Banner -->
                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 flex items-center justify-between text-[11px]">
                        <div>
                            <div class="font-bold text-foreground text-xs flex items-center gap-1.5">
                                <Syringe class="w-3.5 h-3.5 text-rose-600" />
                                <span>{{ executeInjectionOrder.catalog?.name }}</span>
                            </div>
                            <div class="text-muted-foreground">Order #{{ executeInjectionOrder.order_number }} · Priority: <span class="font-bold text-rose-600">{{ executeInjectionOrder.priority }}</span></div>
                        </div>
                        <div class="text-right text-[10px] text-muted-foreground">
                            <div class="font-bold text-foreground">{{ executeInjectionOrder.patient?.first_name }} {{ executeInjectionOrder.patient?.last_name }}</div>
                            <div class="font-mono">MRN: {{ executeInjectionOrder.patient?.primary_mrn }}</div>
                        </div>
                    </div>

                    <!-- Payment Clearance Warning Alert -->
                    <div 
                        v-if="!getProcedurePaymentStatus(executeInjectionOrder).isPaid"
                        class="p-2.5 rounded-lg border bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 flex items-center gap-2 text-rose-800 dark:text-rose-200"
                    >
                        <Lock class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                        <div>
                            <div class="font-bold text-[11px]">Payment Required at Cashier Desk</div>
                            <div class="text-[10px] text-rose-700 dark:text-rose-300">Patient has an unpaid balance ({{ getProcedurePaymentStatus(executeInjectionOrder).label }}). Cashier settlement is required before clinical administration.</div>
                        </div>
                    </div>

                    <!-- 1. Medication Source Switcher (Ndani vs Nje) -->
                    <div class="space-y-1.5 p-2.5 rounded-lg border border-border/80 bg-muted/20">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-xs text-foreground">Medication Source / Asili ya Dawa *</label>
                            <span class="text-[9.5px] font-medium text-muted-foreground">Tanzania MoH / TNMC Protocol</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div 
                                class="p-2 rounded-md border text-left cursor-pointer transition-all space-y-0.5"
                                :class="injectionForm.medication_source === 'FacilityPharmacy' ? 'bg-primary/10 border-primary shadow-2xs' : 'border-border/60 hover:bg-muted/30'"
                                @click="onMedicationSourceChange('FacilityPharmacy')"
                            >
                                <div class="font-bold text-xs text-foreground flex items-center gap-1.5">
                                    <Building2 class="w-3.5 h-3.5 text-primary" />
                                    <span>Clinic Stock (Ndani)</span>
                                </div>
                                <div class="text-[9.5px] text-muted-foreground">Dispensed from facility pharmacy inventory</div>
                            </div>
                            <div 
                                class="p-2 rounded-md border text-left cursor-pointer transition-all space-y-0.5"
                                :class="injectionForm.medication_source === 'PatientSupplied' ? 'bg-sky-50 dark:bg-sky-950/40 border-sky-500 shadow-2xs' : 'border-border/60 hover:bg-muted/30'"
                                @click="onMedicationSourceChange('PatientSupplied')"
                            >
                                <div class="font-bold text-xs text-sky-800 dark:text-sky-300 flex items-center gap-1.5">
                                    <ShieldCheck class="w-3.5 h-3.5 text-sky-600" />
                                    <span>Sindano ya Nje (Brought-in)</span>
                                </div>
                                <div class="text-[9.5px] text-muted-foreground">Verified doctor's prescription card & intact seal</div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Treatment Dosing Plan -->
                    <div class="space-y-2 p-2.5 bg-rose-50/50 dark:bg-rose-950/20 rounded-lg border border-rose-200 dark:border-rose-800">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-foreground flex items-center gap-1.5">
                                <Syringe class="w-3.5 h-3.5 text-rose-600" />
                                <span>Treatment Dosing Plan</span>
                            </span>
                            <div class="inline-flex rounded-md border border-border/80 bg-background p-0.5 text-[10.5px]">
                                <button 
                                    type="button" 
                                    class="px-2 py-0.5 rounded font-semibold transition-all"
                                    :class="injectionForm.treatment_plan_type === 'Single' ? 'bg-rose-500 text-white shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    @click="onInjectionPlanTypeChange('Single')"
                                >
                                    Single Dose (STAT)
                                </button>
                                <button 
                                    type="button" 
                                    class="px-2 py-0.5 rounded font-semibold transition-all"
                                    :class="injectionForm.treatment_plan_type === 'Multi' ? 'bg-rose-500 text-white shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                                    @click="onInjectionPlanTypeChange('Multi')"
                                >
                                    Multi-Dose Course
                                </button>
                            </div>
                        </div>

                        <!-- Multi-Dose Presets -->
                        <div v-if="injectionForm.treatment_plan_type === 'Multi'" class="space-y-2 pt-1 border-t border-rose-200/60 dark:border-rose-800/60">
                            <div class="flex flex-wrap items-center justify-between gap-1 text-[10px]">
                                <span class="font-semibold text-muted-foreground">Select Total Doses in Course:</span>
                                <div class="flex items-center gap-1">
                                    <button
                                        v-for="d in [2, 3, 5, 7]"
                                        :key="d"
                                        type="button"
                                        class="px-1.5 py-0.5 rounded border text-[10px] font-bold transition-all"
                                        :class="injectionForm.total_doses === d ? 'bg-rose-600 text-white border-rose-600 shadow-2xs' : 'bg-background border-border/80 text-foreground hover:bg-muted'"
                                        @click="onInjectionTotalDosesChange(d)"
                                    >
                                        {{ d }} {{ d === 2 ? '(Malaria/Steroid)' : (d === 3 ? '(Short)' : (d === 5 ? '(Standard)' : '(Extended)')) }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs font-bold text-rose-900 dark:text-rose-200">
                                <span>Dose #{{ injectionForm.current_dose_number }} of {{ injectionForm.total_doses }}</span>
                                <span class="text-[10px] font-mono text-rose-700 dark:text-rose-300">
                                    {{ injectionForm.remaining_doses }} Dose(s) Remaining After Today
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <div
                                    v-for="d in injectionForm.total_doses"
                                    :key="d"
                                    class="flex-1 h-2 rounded-full transition-all"
                                    :class="{
                                        'bg-emerald-500': d < injectionForm.current_dose_number,
                                        'bg-amber-500 animate-pulse ring-1 ring-amber-400': d === injectionForm.current_dose_number,
                                        'bg-muted border border-border/80': d > injectionForm.current_dose_number
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Injection Anatomical Site & Setting -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Injection Site (Anatomical) *</label>
                            <select v-model="injectionForm.injection_site" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs" @change="updateInjectionNotes">
                                <option value="Right Deltoid (IM)">Right Deltoid Muscle (IM)</option>
                                <option value="Left Deltoid (IM)">Left Deltoid Muscle (IM)</option>
                                <option value="Right Gluteal (IM)">Right Gluteal / Buttock (IM)</option>
                                <option value="Left Gluteal (IM)">Left Gluteal / Buttock (IM)</option>
                                <option value="IV Cannula / Line">IV Cannula / Direct Intravenous</option>
                                <option value="Subcutaneous (SC)">Subcutaneous (SC) - Abdomen/Thigh</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Administration Setting</label>
                            <select v-model="injectionForm.execution_setting" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                                <option value="InjectionRoom">Injection / Nursing Room</option>
                                <option value="Ward">Inpatient Ward Bedside</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Clinical Technique & Administration Notes *</label>
                        <textarea
                            v-model="injectionForm.findings_and_technique"
                            rows="2"
                            required
                            class="w-full rounded-md border border-input bg-background text-foreground p-2 text-xs"
                            placeholder="Describe injection site, dosage, tolerance, asepsis..."
                        ></textarea>
                    </div>

                    <!-- Consumables Pack Checkbox List -->
                    <div class="space-y-1.5 p-2.5 rounded border border-border/60 bg-muted/15">
                        <label class="block font-bold text-xs text-foreground flex items-center justify-between">
                            <span>Consumables Auto-Deduction (Syringe / Needles / Swabs)</span>
                            <span class="text-[9.5px] text-muted-foreground">Deducted from Ward/Nursing Stock</span>
                        </label>
                        <div class="space-y-1">
                            <div v-for="(con, idx) in injectionForm.consumables" :key="idx" class="flex items-center justify-between text-[11px] p-1 bg-card rounded">
                                <span class="font-medium text-foreground">{{ con.item_name }}</span>
                                <span class="font-mono text-muted-foreground font-semibold">Qty: {{ con.quantity_used }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Next Step Routing -->
                    <div class="space-y-1.5 p-2.5 rounded-lg bg-muted/20 border border-border/60">
                        <label class="block font-bold text-xs text-foreground">Post-Injection Patient Routing</label>
                        <div class="grid grid-cols-4 gap-2">
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="injectionForm.next_action === 'discharge' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="injectionForm.next_action = 'discharge'"
                            >
                                🚪 Discharge
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="injectionForm.next_action === 'pharmacy' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="injectionForm.next_action = 'pharmacy'"
                            >
                                💊 To Pharmacy
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="injectionForm.next_action === 'doctor' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="injectionForm.next_action = 'doctor'"
                            >
                                🩺 To Doctor
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="injectionForm.next_action === 'cashier' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="injectionForm.next_action = 'cashier'"
                            >
                                💳 To Cashier
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showInjectionModal = false" :disabled="isExecutingInjection">Cancel</Button>
                        <Button 
                            variant="default" 
                            size="sm" 
                            class="h-8 text-xs font-semibold gap-1.5 shadow-2xs transition-all" 
                            :class="getProcedurePaymentStatus(executeInjectionOrder).isPaid ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-muted text-muted-foreground border border-border cursor-not-allowed opacity-80'"
                            @click="submitInjection" 
                            :disabled="isExecutingInjection || !injectionForm.findings_and_technique || !getProcedurePaymentStatus(executeInjectionOrder).isPaid"
                        >
                            <Loader2 v-if="isExecutingInjection" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <Lock v-else-if="!getProcedurePaymentStatus(executeInjectionOrder).isPaid" class="w-3.5 h-3.5 text-rose-500 mr-1" />
                            <span>{{ getProcedurePaymentStatus(executeInjectionOrder).isPaid ? 'Confirm & Route Patient' : 'Payment Required at Cashier' }}</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 3: EXECUTE WOUND DRESSING (CHUMBA CHA VIDONDA) ================= -->
        <Modal :show="showDressingModal" max-width="3xl" @close="showDressingModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Bandage class="w-4.5 h-4.5 text-amber-600" />
                        <h3 class="font-bold text-sm text-foreground">Execute Wound Care & Sterile Dressing (Chumba cha Vidonda)</h3>
                    </div>
                    <button @click="showDressingModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="executeDressingOrder" class="space-y-3 text-xs">
                    <!-- Patient Banner -->
                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 flex items-center justify-between text-[11px]">
                        <div>
                            <div class="font-bold text-foreground text-xs flex items-center gap-1.5">
                                <Bandage class="w-3.5 h-3.5 text-amber-600" />
                                <span>{{ executeDressingOrder.catalog?.name }}</span>
                            </div>
                            <div class="text-muted-foreground">Order #{{ executeDressingOrder.order_number }} · Priority: <span class="font-bold text-amber-600">{{ executeDressingOrder.priority }}</span></div>
                        </div>
                        <div class="text-right text-[10px] text-muted-foreground">
                            <div class="font-bold text-foreground">{{ executeDressingOrder.patient?.first_name }} {{ executeDressingOrder.patient?.last_name }}</div>
                            <div class="font-mono">MRN: {{ executeDressingOrder.patient?.primary_mrn }}</div>
                        </div>
                    </div>

                    <!-- Payment Clearance Warning Alert -->
                    <div 
                        v-if="!getProcedurePaymentStatus(executeDressingOrder).isPaid"
                        class="p-2.5 rounded-lg border bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 flex items-center gap-2 text-rose-800 dark:text-rose-200"
                    >
                        <Lock class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                        <div>
                            <div class="font-bold text-[11px]">Payment Required at Cashier Desk</div>
                            <div class="text-[10px] text-rose-700 dark:text-rose-300">Patient has an unpaid balance ({{ getProcedurePaymentStatus(executeDressingOrder).label }}). Cashier settlement is required before performing wound care.</div>
                        </div>
                    </div>

                    <!-- Wound Assessment Condition & Cleansing Solution -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Wound Assessment Condition *</label>
                            <select v-model="dressingForm.wound_condition" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                                <option value="Clean">Clean Wound</option>
                                <option value="Contaminated">Contaminated / Traumatic</option>
                                <option value="Purulent">Purulent / Abscess / Infected</option>
                                <option value="Sloughy">Sloughy / Necrotic</option>
                                <option value="Granulating">Granulating / Healing</option>
                                <option value="Epithelializing">Epithelializing</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Cleansing & Antiseptic Solution *</label>
                            <select v-model="dressingForm.cleansing_solution" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                                <option value="Normal Saline 0.9%">Normal Saline 0.9%</option>
                                <option value="Povidone-Iodine 10%">Povidone-Iodine 10%</option>
                                <option value="Hydrogen Peroxide 3%">Hydrogen Peroxide 3% (Diluted)</option>
                                <option value="Chlorhexidine 0.5%">Chlorhexidine 0.5%</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Dressing Technique & Wound Bed Observations *</label>
                        <textarea
                            v-model="dressingForm.findings_and_technique"
                            rows="2"
                            required
                            class="w-full rounded-md border border-input bg-background text-foreground p-2 text-xs"
                            placeholder="Describe wound dimensions, exudate, cleansing, dressing layers applied..."
                        ></textarea>
                    </div>

                    <!-- Dressing Consumables Used -->
                    <div class="space-y-1.5 p-2.5 rounded border border-border/60 bg-muted/15">
                        <label class="block font-bold text-xs text-foreground flex items-center justify-between">
                            <span>Dressing Consumables Consumed</span>
                            <span class="text-[9.5px] text-muted-foreground">Auto-decrements Clinic Stock</span>
                        </label>
                        <div class="space-y-1">
                            <div v-for="(con, idx) in dressingForm.consumables" :key="idx" class="flex items-center justify-between text-[11px] p-1 bg-card rounded">
                                <span class="font-medium text-foreground">{{ con.item_name }}</span>
                                <span class="font-mono text-muted-foreground font-semibold">Qty: {{ con.quantity_used }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Follow-up & Next Dressing Date -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <AfyaDatePicker
                                v-model="dressingForm.follow_up_date"
                                label="Next Dressing Change Date"
                                :min="new Date().toISOString().split('T')[0]"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Post-Dressing Instructions</label>
                            <Input v-model="dressingForm.post_procedure_instructions" class="h-8.5 text-xs" />
                        </div>
                    </div>

                    <!-- Next Step Routing -->
                    <div class="space-y-1.5 p-2.5 rounded-lg bg-muted/20 border border-border/60">
                        <label class="block font-bold text-xs text-foreground">Post-Dressing Patient Routing</label>
                        <div class="grid grid-cols-4 gap-2">
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="dressingForm.next_action === 'discharge' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="dressingForm.next_action = 'discharge'"
                            >
                                🚪 Discharge
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="dressingForm.next_action === 'pharmacy' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="dressingForm.next_action = 'pharmacy'"
                            >
                                💊 To Pharmacy
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="dressingForm.next_action === 'doctor' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="dressingForm.next_action = 'doctor'"
                            >
                                🩺 To Doctor
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="dressingForm.next_action === 'cashier' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="dressingForm.next_action = 'cashier'"
                            >
                                💳 To Cashier
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showDressingModal = false" :disabled="isExecutingDressing">Cancel</Button>
                        <Button 
                            variant="default" 
                            size="sm" 
                            class="h-8 text-xs font-semibold gap-1.5 shadow-2xs transition-all" 
                            :class="getProcedurePaymentStatus(executeDressingOrder).isPaid ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-muted text-muted-foreground border border-border cursor-not-allowed opacity-80'"
                            @click="submitDressing" 
                            :disabled="isExecutingDressing || !dressingForm.findings_and_technique || !getProcedurePaymentStatus(executeDressingOrder).isPaid"
                        >
                            <Loader2 v-if="isExecutingDressing" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <Lock v-else-if="!getProcedurePaymentStatus(executeDressingOrder).isPaid" class="w-3.5 h-3.5 text-rose-500 mr-1" />
                            <span>{{ getProcedurePaymentStatus(executeDressingOrder).isPaid ? 'Complete Dressing & Route' : 'Payment Required at Cashier' }}</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 4: EXECUTE MINOR SURGERY (UPASUAJI MDOGO) ================= -->
        <Modal :show="showMinorSurgeryModal" max-width="3xl" @close="showMinorSurgeryModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Scissors class="w-4.5 h-4.5 text-sky-600" />
                        <h3 class="font-bold text-sm text-foreground">Execute Minor Surgical Procedure (Upasuaji Mdogo)</h3>
                    </div>
                    <button @click="showMinorSurgeryModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="executeMinorSurgeryOrder" class="space-y-3 text-xs">
                    <!-- Patient Banner -->
                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 flex items-center justify-between text-[11px]">
                        <div>
                            <div class="font-bold text-foreground text-xs flex items-center gap-1.5">
                                <Scissors class="w-3.5 h-3.5 text-sky-600" />
                                <span>{{ executeMinorSurgeryOrder.catalog?.name }}</span>
                            </div>
                            <div class="text-muted-foreground">Order #{{ executeMinorSurgeryOrder.order_number }} · Priority: <span class="font-bold text-sky-600">{{ executeMinorSurgeryOrder.priority }}</span></div>
                        </div>
                        <div class="text-right text-[10px] text-muted-foreground">
                            <div class="font-bold text-foreground">{{ executeMinorSurgeryOrder.patient?.first_name }} {{ executeMinorSurgeryOrder.patient?.last_name }}</div>
                            <div class="font-mono">MRN: {{ executeMinorSurgeryOrder.patient?.primary_mrn }}</div>
                        </div>
                    </div>

                    <!-- Payment Clearance Warning Alert -->
                    <div 
                        v-if="!getProcedurePaymentStatus(executeMinorSurgeryOrder).isPaid"
                        class="p-2.5 rounded-lg border bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 flex items-center gap-2 text-rose-800 dark:text-rose-200"
                    >
                        <Lock class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                        <div>
                            <div class="font-bold text-[11px]">Payment Required at Cashier Desk</div>
                            <div class="text-[10px] text-rose-700 dark:text-rose-300">Patient has an unpaid balance ({{ getProcedurePaymentStatus(executeMinorSurgeryOrder).label }}). Cashier settlement is required before performing minor surgery.</div>
                        </div>
                    </div>

                    <!-- Consent & Local Anesthesia -->
                    <div class="p-2 bg-muted/20 rounded border border-border/60 flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer font-bold text-xs text-foreground">
                            <input type="checkbox" v-model="minorSurgeryForm.consent_obtained" class="rounded text-primary focus:ring-0" />
                            <span>Informed Consent Verified and Signed</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Local Anesthesia Agent *</label>
                            <select v-model="minorSurgeryForm.anesthesia_agent" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                                <option value="Lignocaine 2% Plain">Lignocaine 2% Plain</option>
                                <option value="Lignocaine 2% with Adrenaline">Lignocaine 2% with Adrenaline (1:200,000)</option>
                                <option value="Bupivacaine 0.5%">Bupivacaine 0.5%</option>
                                <option value="None">None (Topical / Non-invasive)</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Anesthesia Volume Administered (ml)</label>
                            <Input v-model="minorSurgeryForm.anesthesia_volume_ml" type="number" step="0.5" class="h-8.5 text-xs" placeholder="e.g. 5" />
                        </div>
                    </div>

                    <!-- Suturing Details -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Suture Material / Closure</label>
                            <select v-model="minorSurgeryForm.suture_material" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                                <option value="Nylon 3-0">Nylon 3-0 (Skin / Face / Ext)</option>
                                <option value="Nylon 2-0">Nylon 2-0 (Scalp / Tension)</option>
                                <option value="Vicryl 2-0">Vicryl 2-0 (Absorbable / Subcutaneous)</option>
                                <option value="Silk 2-0">Silk 2-0</option>
                                <option value="None">None (Incision & Drainage / Pack)</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Number of Sutures / Stitches Placed</label>
                            <Input v-model="minorSurgeryForm.suture_count" type="number" class="h-8.5 text-xs" placeholder="e.g. 3" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Surgical Technique & Operative Notes *</label>
                        <textarea
                            v-model="minorSurgeryForm.findings_and_technique"
                            rows="2"
                            required
                            class="w-full rounded-md border border-input bg-background text-foreground p-2 text-xs"
                            placeholder="Describe incision, debridement, hemostasis, suture technique..."
                        ></textarea>
                    </div>

                    <!-- Suture Removal & Follow-up Date -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <AfyaDatePicker
                                v-model="minorSurgeryForm.suture_removal_date"
                                label="Scheduled Suture Removal Date"
                                :min="new Date().toISOString().split('T')[0]"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Post-Op Wound Instructions</label>
                            <Input v-model="minorSurgeryForm.post_procedure_instructions" class="h-8.5 text-xs" />
                        </div>
                    </div>

                    <!-- Next Step Routing -->
                    <div class="space-y-1.5 p-2.5 rounded-lg bg-muted/20 border border-border/60">
                        <label class="block font-bold text-xs text-foreground">Post-Procedure Patient Routing</label>
                        <div class="grid grid-cols-4 gap-2">
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="minorSurgeryForm.next_action === 'discharge' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="minorSurgeryForm.next_action = 'discharge'"
                            >
                                🚪 Discharge
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="minorSurgeryForm.next_action === 'pharmacy' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="minorSurgeryForm.next_action = 'pharmacy'"
                            >
                                💊 To Pharmacy
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="minorSurgeryForm.next_action === 'doctor' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="minorSurgeryForm.next_action = 'doctor'"
                            >
                                🩺 To Doctor
                            </div>
                            <div
                                class="p-2 rounded-md border text-center cursor-pointer text-xs font-semibold transition-all"
                                :class="minorSurgeryForm.next_action === 'cashier' ? 'bg-primary/10 border-primary text-primary shadow-2xs' : 'border-border/60 hover:bg-muted/20 text-muted-foreground'"
                                @click="minorSurgeryForm.next_action = 'cashier'"
                            >
                                💳 To Cashier
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showMinorSurgeryModal = false" :disabled="isExecutingMinorSurgery">Cancel</Button>
                        <Button 
                            variant="default" 
                            size="sm" 
                            class="h-8 text-xs font-semibold gap-1.5 shadow-2xs transition-all" 
                            :class="getProcedurePaymentStatus(executeMinorSurgeryOrder).isPaid ? 'bg-sky-600 hover:bg-sky-700 text-white' : 'bg-muted text-muted-foreground border border-border cursor-not-allowed opacity-80'"
                            @click="submitMinorSurgery" 
                            :disabled="isExecutingMinorSurgery || !minorSurgeryForm.findings_and_technique || !getProcedurePaymentStatus(executeMinorSurgeryOrder).isPaid"
                        >
                            <Loader2 v-if="isExecutingMinorSurgery" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <Lock v-else-if="!getProcedurePaymentStatus(executeMinorSurgeryOrder).isPaid" class="w-3.5 h-3.5 text-rose-500 mr-1" />
                            <span>{{ getProcedurePaymentStatus(executeMinorSurgeryOrder).isPaid ? 'Complete Surgery & Route' : 'Payment Required at Cashier' }}</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 5: BOOK OPERATING THEATRE SURGERY ================= -->
        <Modal :show="showBookSurgeryModal" max-width="md" @close="showBookSurgeryModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Building2 class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Book Surgical Operating Suite</h3>
                    </div>
                    <button @click="showBookSurgeryModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitBookSurgery" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Operating Suite *</label>
                        <select v-model="bookSurgeryForm.operating_suite_id" required class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option value="" disabled>-- Select Suite --</option>
                            <option v-for="suite in operatingSuites" :key="suite.id" :value="suite.id">
                                {{ suite.name }} ({{ suite.suite_type }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Scheduled Start *</label>
                            <Input v-model="bookSurgeryForm.scheduled_start" type="datetime-local" required class="h-8.5 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Scheduled End *</label>
                            <Input v-model="bookSurgeryForm.scheduled_end" type="datetime-local" required class="h-8.5 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Urgency</label>
                        <select v-model="bookSurgeryForm.urgency" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option value="Elective">Elective</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showBookSurgeryModal = false" :disabled="isBookingSurgery">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isBookingSurgery || !bookSurgeryForm.operating_suite_id">
                            <Loader2 v-if="isBookingSurgery" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Confirm OT Booking</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ================= MODAL 6: WHO CHECKLIST SIGN-OFF ================= -->
        <Modal :show="showWhoChecklistModal" max-width="md" @close="showWhoChecklistModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">WHO Surgical Safety Checklist</h3>
                    </div>
                    <button @click="showWhoChecklistModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Checklist Safety Stage *</label>
                        <select v-model="whoChecklistForm.stage" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option value="sign_in">1. Sign-In (Before induction of anesthesia)</option>
                            <option value="time_out">2. Time-Out (Before skin incision - Patient, Procedure, Site)</option>
                            <option value="sign_out">3. Sign-Out (Before patient leaves operating room)</option>
                        </select>
                    </div>

                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 space-y-1.5 text-[10.5px]">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="whoChecklistForm.sponge_and_needle_count_correct" class="rounded text-primary focus:ring-0" />
                            <span class="font-semibold text-foreground">Sponge, needle, and instrument counts verified correct</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="whoChecklistForm.specimens_labeled_correctly" class="rounded text-primary focus:ring-0" />
                            <span class="font-semibold text-foreground">Surgical specimen labeled with patient name and MRN</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showWhoChecklistModal = false" :disabled="isSavingChecklist">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs bg-emerald-700 hover:bg-emerald-800 text-white" @click="submitWhoChecklist" :disabled="isSavingChecklist">
                            <Loader2 v-if="isSavingChecklist" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Sign & Confirm Checklist</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 7: PACU ALDRETE TELEMETRY ================= -->
        <Modal :show="showPacuModal" max-width="md" @close="showPacuModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <HeartPulse class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">PACU Post-Anesthesia Aldrete Score</h3>
                    </div>
                    <button @click="showPacuModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Consciousness (0-2)</span>
                            <select v-model="pacuForm.consciousness_score" class="h-7 text-xs rounded border border-input bg-background font-mono">
                                <option :value="2">2 - Fully awake</option>
                                <option :value="1">1 - Arousable on calling</option>
                                <option :value="0">0 - Unresponsive</option>
                            </select>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Activity (0-2)</span>
                            <select v-model="pacuForm.activity_score" class="h-7 text-xs rounded border border-input bg-background font-mono">
                                <option :value="2">2 - Moves 4 extremities</option>
                                <option :value="1">1 - Moves 2 extremities</option>
                                <option :value="0">0 - Unable to move</option>
                            </select>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Respiration (0-2)</span>
                            <select v-model="pacuForm.respiration_score" class="h-7 text-xs rounded border border-input bg-background font-mono">
                                <option :value="2">2 - Breathes deeply & coughs</option>
                                <option :value="1">1 - Dyspneic / limited</option>
                                <option :value="0">0 - Apneic</option>
                            </select>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Oxygen Saturation (0-2)</span>
                            <select v-model="pacuForm.oxygen_saturation_score" class="h-7 text-xs rounded border border-input bg-background font-mono">
                                <option :value="2">2 - SpO2 > 92% on room air</option>
                                <option :value="1">1 - Needs supplemental O2</option>
                                <option :value="0">0 - SpO2 &lt; 90%</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Step-Down Destination Ward *</label>
                        <select v-model="pacuForm.destination_ward_id" required class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option value="" disabled>-- Select Ward --</option>
                            <option v-for="ward in wards" :key="ward.id" :value="ward.id">
                                {{ ward.name }} ({{ ward.ward_type }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Recovery Notes & Telemetry Observations</label>
                        <textarea
                            v-model="pacuForm.notes"
                            rows="2"
                            class="w-full rounded-md border border-input bg-background text-foreground p-2 text-xs"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showPacuModal = false" :disabled="isSavingPacu">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs bg-purple-700 hover:bg-purple-800 text-white" @click="submitPacuScore" :disabled="isSavingPacu || !pacuForm.destination_ward_id">
                            <Loader2 v-if="isSavingPacu" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Discharge to Ward</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- MODAL: CREATE PROCEDURE CATALOG ITEM -->
        <Modal :show="showCreateCatalogModal" max-width="md" @close="showCreateCatalogModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <FileText class="w-4 h-4 text-emerald-600" />
                        <h3 class="font-bold text-sm text-foreground">Add New Procedure to Catalog</h3>
                    </div>
                    <button @click="showCreateCatalogModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateCatalog" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Procedure Code *</label>
                            <Input v-model="createCatalogForm.procedure_code" required class="h-8 text-xs font-mono" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Category *</label>
                            <select v-model="createCatalogForm.category" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="Minor">Minor Treatment / Dressing</option>
                                <option value="Injection">Injection / Infusion</option>
                                <option value="Surgical">Major Surgical</option>
                                <option value="Diagnostic">Endoscopy / Diagnostic</option>
                                <option value="Dental">Dental Care</option>
                                <option value="Eye">Ophthalmology</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-muted-foreground text-[10px] uppercase">Procedure Name *</label>
                        <Input v-model="createCatalogForm.name" required placeholder="e.g. Incision and Drainage (I&D) of Abscess" class="h-8 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Tier Classification *</label>
                            <select v-model="createCatalogForm.tier_level" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="Tier1_Minor">Tier 1 - Minor / Dressing</option>
                                <option value="Tier2_Intermediate">Tier 2 - Intermediate Minor OR</option>
                                <option value="Tier3_Major">Tier 3 - Major Theatre</option>
                                <option value="Tier4_Specialized">Tier 4 - Specialized Surgical</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Default Duration (Mins)</label>
                            <Input v-model.number="createCatalogForm.default_duration_minutes" type="number" min="5" class="h-8 text-xs font-mono" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-muted-foreground text-[10px] uppercase">Standard Tariff Price (TZS) *</label>
                        <Input v-model.number="createCatalogForm.standard_price" type="number" min="0" required class="h-8 text-xs font-mono font-bold" />
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        <label class="flex items-center gap-1.5 cursor-pointer text-muted-foreground hover:text-foreground">
                            <input type="checkbox" v-model="createCatalogForm.requires_consent" class="rounded border-input text-primary" />
                            <span class="text-[11px]">Requires Clinical Consent</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-muted-foreground hover:text-foreground">
                            <input type="checkbox" v-model="createCatalogForm.requires_anesthesia" class="rounded border-input text-primary" />
                            <span class="text-[11px]">Requires Anesthesia</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs" @click="showCreateCatalogModal = false">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold" :disabled="isCatalogSubmitting">
                            <Loader2 v-if="isCatalogSubmitting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Save to Master Catalog</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: EDIT PROCEDURE CATALOG ITEM -->
        <Modal :show="showEditCatalogModal" max-width="md" @close="showEditCatalogModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <FileText class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Edit Catalog Item: {{ targetCatalogItem?.procedure_code }}</h3>
                    </div>
                    <button @click="showEditCatalogModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitEditCatalog" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-muted-foreground text-[10px] uppercase">Procedure Name *</label>
                        <Input v-model="editCatalogForm.name" required class="h-8 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Category *</label>
                            <select v-model="editCatalogForm.category" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="Minor">Minor Treatment / Dressing</option>
                                <option value="Injection">Injection / Infusion</option>
                                <option value="Surgical">Major Surgical</option>
                                <option value="Diagnostic">Endoscopy / Diagnostic</option>
                                <option value="Dental">Dental Care</option>
                                <option value="Eye">Ophthalmology</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Tier Level</label>
                            <select v-model="editCatalogForm.tier_level" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="Tier1_Minor">Tier 1 - Minor</option>
                                <option value="Tier2_Intermediate">Tier 2 - Intermediate Minor OR</option>
                                <option value="Tier3_Major">Tier 3 - Major Theatre</option>
                                <option value="Tier4_Specialized">Tier 4 - Specialized Surgical</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Standard Fee (TZS) *</label>
                            <Input v-model.number="editCatalogForm.standard_price" type="number" min="0" required class="h-8 text-xs font-mono font-bold" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Duration (Mins)</label>
                            <Input v-model.number="editCatalogForm.default_duration_minutes" type="number" min="5" class="h-8 text-xs font-mono" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-1">
                        <label class="flex items-center gap-1.5 cursor-pointer text-muted-foreground hover:text-foreground">
                            <input type="checkbox" v-model="editCatalogForm.requires_consent" class="rounded border-input text-primary" />
                            <span class="text-[11px]">Requires Consent</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-muted-foreground hover:text-foreground">
                            <input type="checkbox" v-model="editCatalogForm.requires_anesthesia" class="rounded border-input text-primary" />
                            <span class="text-[11px]">Requires Anesthesia</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-muted-foreground hover:text-foreground">
                            <input type="checkbox" v-model="editCatalogForm.is_active" class="rounded border-input text-primary" />
                            <span class="text-[11px]">Active</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs" @click="showEditCatalogModal = false">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs" :disabled="isCatalogSubmitting">
                            <Loader2 v-if="isCatalogSubmitting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Save Changes</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

    </AfyaShell>
</template>
