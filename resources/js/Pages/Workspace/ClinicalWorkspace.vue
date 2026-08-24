<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    LayoutDashboard, 
    Users, 
    Clock, 
    ClipboardList, 
    FlaskConical, 
    Microscope, 
    Stethoscope, 
    Pill, 
    FileText, 
    Activity,
    AlertTriangle,
    CheckCircle2,
    ArrowUpRight,
    ArrowLeft,
    Plus,
    X,
    PhoneCall,
    CheckSquare,
    ChevronRight,
    FileCheck,
    Search,
    ShieldAlert,
    Loader2,
    HeartPulse,
    Scissors,
    Bandage,
    Film,
    FileSignature,
    Syringe,
    Baby,
    Heart
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import SOAPNote from '@/Pages/Domains/Clinical/SOAPNote.vue';
import VitalsForm from '@/Pages/Domains/Clinical/VitalsForm.vue';
import PrescriptionPad from '@/Pages/Domains/Pharmacy/PrescriptionPad.vue';
import LabOrderPad from '@/Components/Clinical/LabOrderPad.vue';
import LabResultsCard from '@/Components/Clinical/LabResultsCard.vue';
import LabWorkbenchModal from '@/Components/Clinical/LabWorkbenchModal.vue';
import AfyaClinicalAlert from '@/Components/Afya/AfyaClinicalAlert.vue';
import ConsentForm from '@/Pages/Domains/Clinical/ConsentForm.vue';
import ImmunizationForm from '@/Pages/Domains/Clinical/ImmunizationForm.vue';
import ReferralForm from '@/Pages/Domains/Clinical/ReferralForm.vue';
import AncVisitForm from '@/Pages/Domains/Clinical/AncVisitForm.vue';
import PartographForm from '@/Pages/Domains/Clinical/PartographForm.vue';
import ImagingOrderForm from '@/Pages/Domains/Clinical/ImagingOrderForm.vue';
import Modal from '@/Components/Modal.vue';

// Design Foundation Primitives
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import AfyaPatientIdentity from '@/Components/Afya/AfyaPatientIdentity.vue';

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    encounters: {
        type: Array,
        default: () => [],
    },
    patients: {
        type: Array,
        default: () => [],
    },
    allPatients: {
        type: Array,
        default: () => [],
    },
    formularies: {
        type: Array,
        default: () => [],
    },
    labTests: {
        type: Array,
        default: () => [],
    },
    recentLabOrders: {
        type: Array,
        default: () => [],
    },
    procedureCatalogs: {
        type: Array,
        default: () => [],
    },
    encounter: {
        type: Object,
        default: null,
    },
});

import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const { preferences, openContext } = useWorkspacePreferences();

// View State: 'overview' | 'queue' | 'encounters' | 'labs' | 'charting'
const activeSection = ref('overview');
const activePatientId = ref(null);
const activeEncounterId = ref(null);
const chartingTab = ref('soap'); // 'soap' | 'vitals' | 'rx' | 'labs'
const isStartingEncounter = ref(false);
const isCompletingEncounter = ref(false);

// Queue Filter: 'all' | 'vitals_ready' | 'triage_pending'
const queueFilter = ref('all');

const vitalsReadyCount = computed(() => {
    return props.patients.filter(p => p.latest_vital || (p.vitals && p.vitals.length > 0)).length;
});

const triagePendingCount = computed(() => {
    return props.patients.filter(p => !p.latest_vital && (!p.vitals || p.vitals.length === 0)).length;
});

const filteredQueuePatients = computed(() => {
    if (queueFilter.value === 'vitals_ready') {
        return props.patients.filter(p => p.latest_vital || (p.vitals && p.vitals.length > 0));
    }
    if (queueFilter.value === 'triage_pending') {
        return props.patients.filter(p => !p.latest_vital && (!p.vitals || p.vitals.length === 0));
    }
    return props.patients;
});

// New Consultation Modal
const showNewEncounterModal = ref(false);
const newEncounterForm = ref({
    patient_id: '',
    encounter_type: 'OPD Consultation',
    reason_for_visit: '',
});

// Doctor Consultation Procedure Ordering Modal
const showClinicalProcModal = ref(false);
const isSubmittingProcOrder = ref(false);
const clinicalProcForm = ref({
    encounter_id: '',
    procedure_catalog_id: '',
    priority: 'Routine',
    clinical_indication: '',
});

const openDoctorProcedureModal = () => {
    if (!activeEncounter.value) return;
    clinicalProcForm.value = {
        encounter_id: activeEncounter.value.id,
        procedure_catalog_id: props.procedureCatalogs?.[0]?.id || '',
        priority: 'Routine',
        clinical_indication: '',
    };
    showClinicalProcModal.value = true;
};

const submitDoctorProcedureOrder = () => {
    if (!clinicalProcForm.value.procedure_catalog_id || !activeEncounter.value) return;
    clinicalProcForm.value.encounter_id = activeEncounter.value.id;
    isSubmittingProcOrder.value = true;
    router.post(route('procedures.orders.store'), clinicalProcForm.value, {
        onFinish: () => {
            isSubmittingProcOrder.value = false;
            showClinicalProcModal.value = false;
        }
    });
};

// Fully reactive computed Patient & Encounter state synchronized with Inertia props
const activePatient = computed(() => {
    if (props.encounter && props.encounter.patient && (!activePatientId.value || props.encounter.patient.id === activePatientId.value)) {
        return props.encounter.patient;
    }
    if (activePatientId.value) {
        return props.patients.find(p => p.id === activePatientId.value) || 
               props.encounters.find(e => e.patient_id === activePatientId.value)?.patient || 
               null;
    }
    return null;
});

const activeEncounter = computed(() => {
    if (props.encounter && (!activeEncounterId.value || props.encounter.id === activeEncounterId.value)) {
        return props.encounter;
    }
    if (activeEncounterId.value) {
        const found = props.encounters.find(e => e.id === activeEncounterId.value);
        if (found) return found;
    }
    if (activePatient.value) {
        const found = props.encounters.find(e => e.patient_id === activePatient.value.id && e.status !== 'Closed');
        if (found) return found;
    }
    return null;
});

// Lab Workbench Modal for Global Lab View
const showGlobalLabWorkbench = ref(false);
const activeGlobalLabItem = ref(null);

const openGlobalWorkbench = (item) => {
    activeGlobalLabItem.value = item;
    showGlobalLabWorkbench.value = true;
};

// Launch or create consultation
const openConsultation = (patient, encounter = null) => {
    if (!patient) return;
    activePatientId.value = patient.id;
    
    if (encounter) {
        activeEncounterId.value = encounter.id;
        activeSection.value = 'charting';
        openContext();
        return;
    }

    const found = props.encounters.find(e => e.patient_id === patient.id && e.status !== 'Closed');
    if (found) {
        activeEncounterId.value = found.id;
        activeSection.value = 'charting';
        openContext();
    } else {
        // Automatically create a REAL database encounter via router.post
        isStartingEncounter.value = true;
        router.post(route('encounters.store'), {
            patient_id: patient.id,
            encounter_type: 'OPD Consultation',
            reason_for_visit: 'OPD Consultation',
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isStartingEncounter.value = false;
                activeSection.value = 'charting';
                openContext();
            },
            onError: () => {
                isStartingEncounter.value = false;
            }
        });
    }
};

const openNewEncounterModal = (preselectedPatientId = null) => {
    const list = props.allPatients?.length ? props.allPatients : props.patients;
    newEncounterForm.value = {
        patient_id: preselectedPatientId || (list[0]?.id || ''),
        encounter_type: 'OPD Consultation',
        reason_for_visit: '',
    };
    showNewEncounterModal.value = true;
};

const createNewEncounter = () => {
    if (!newEncounterForm.value.patient_id) return;
    isStartingEncounter.value = true;

    router.post(route('encounters.store'), {
        patient_id: newEncounterForm.value.patient_id,
        encounter_type: newEncounterForm.value.encounter_type,
        reason_for_visit: newEncounterForm.value.reason_for_visit || 'General Consultation',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isStartingEncounter.value = false;
            showNewEncounterModal.value = false;
            activeSection.value = 'charting';
            openContext();
        },
        onError: () => {
            isStartingEncounter.value = false;
        }
    });
};

const closeConsultation = () => {
    activeSection.value = 'overview';
    activePatientId.value = null;
    activeEncounterId.value = null;
};

const completeConsultation = () => {
    if (!activeEncounter.value?.id) {
        closeConsultation();
        return;
    }

    isCompletingEncounter.value = true;
    router.post(route('encounters.complete', activeEncounter.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isCompletingEncounter.value = false;
            closeConsultation();
        },
        onError: () => {
            isCompletingEncounter.value = false;
        }
    });
};

const callNextPatient = () => {
    if (props.patients.length > 0) {
        openConsultation(props.patients[0]);
    } else {
        activeSection.value = 'queue';
    }
};

// Initialize module
onMounted(() => {
    if (props.encounter && props.encounter.patient) {
        activePatientId.value = props.encounter.patient.id;
        activeEncounterId.value = props.encounter.id;
        activeSection.value = 'charting';
        openContext();
    } else {
        activeSection.value = 'overview';
        activePatientId.value = null;
        activeEncounterId.value = null;
    }
});

// Watch for prop changes (e.g. after Inertia redirects)
watch(() => props.encounter, (newEnc) => {
    if (newEnc && newEnc.patient) {
        activePatientId.value = newEnc.patient.id;
        activeEncounterId.value = newEnc.id;
        activeSection.value = 'charting';
        openContext();
    }
}, { immediate: true });

// Sidebar navigation items
const navItems = computed(() => [
    { label: 'Overview Matrix', id: 'overview', icon: LayoutDashboard, badge: null },
    { label: 'Patient Queue', id: 'queue', icon: Users, badge: props.patients.length },
    { label: 'Active Encounters', id: 'encounters', icon: ClipboardList, badge: props.encounters.length },
    { label: 'Diagnostic Labs', id: 'labs', icon: FlaskConical, badge: null },
]);

const handleNavClick = (sectionId) => {
    activeSection.value = sectionId;
    if (sectionId !== 'charting') {
        activePatientId.value = null;
        activeEncounterId.value = null;
    }
};

// Dynamic Context Panel Title and Icon based on Active View
const contextTitle = computed(() => {
    if (activeSection.value === 'charting' && activePatient.value) {
        return 'Patient 360';
    }
    if (activeSection.value === 'queue') {
        return 'Queue Insights';
    }
    if (activeSection.value === 'encounters') {
        return 'Encounters Pulse';
    }
    if (activeSection.value === 'labs') {
        return 'Lab Telemetry';
    }
    return 'Station Pulse';
});

const contextIcon = computed(() => {
    if (activeSection.value === 'charting' && activePatient.value) {
        return Users;
    }
    if (activeSection.value === 'queue') {
        return Clock;
    }
    if (activeSection.value === 'encounters') {
        return ClipboardList;
    }
    if (activeSection.value === 'labs') {
        return FlaskConical;
    }
    return Activity;
});

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Derived patient clinical data for Right Inspector
const patientAllergies = computed(() => activePatient.value?.allergies || []);
</script>

<template>
    <Head title="Clinical Workstation — Physician Station" />

    <AfyaShell active-module="clinical">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Standardized Physician Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Physician Station"
                    :icon="Stethoscope"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Operational Views
                    </div>
                    
                    <AfyaSidebarItem
                        v-for="item in navItems"
                        :key="item.id"
                        :label="item.label"
                        :icon="item.icon"
                        :badge="item.badge"
                        :active="activeSection === item.id"
                        :collapsed="state === 'collapsed'"
                        @click="handleNavClick(item.id)"
                    />

                    <!-- Active Consultation Direct Jump (If Chart is Open) -->
                    <template v-if="activePatient && state !== 'collapsed'">
                        <div class="px-2 pt-3 pb-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border mt-2">
                            Active Consultation
                        </div>
                        <div 
                            @click="activeSection = 'charting'"
                            class="p-2.5 rounded-lg bg-primary/10 border border-primary/20 cursor-pointer space-y-0.5 hover:bg-primary/15 transition-all shadow-2xs"
                            :class="{ 'ring-1 ring-primary': activeSection === 'charting' }"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-primary truncate">
                                    {{ activePatient.first_name }} {{ activePatient.last_name }}
                                </span>
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            </div>
                            <div class="text-[10px] font-mono text-muted-foreground">
                                {{ activePatient.primary_mrn }}
                            </div>
                        </div>
                    </template>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER PANEL: Dynamic Modern Surface Views -->
            <template #default>
                
                <!-- ============================================================== -->
                <!-- VIEW 1: OVERVIEW MATRIX                                        -->
                <!-- ============================================================== -->
                <AfyaWorkspaceMain
                    v-if="activeSection === 'overview'"
                    :breadcrumbs="[
                        { label: 'Clinical Workstation', href: route('workspace.clinical') },
                        { label: 'Overview Matrix', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button v-if="can.startEncounter"
                                
                                variant="default" 
                                size="sm" 
                                :disabled="isStartingEncounter"
                                class="h-7 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="callNextPatient"
                            >
                                <Loader2 v-if="isStartingEncounter" class="w-3.5 h-3.5 animate-spin" />
                                <Stethoscope v-else class="w-3.5 h-3.5" />
                                <span>Call Next Patient ({{ patients.length }})</span>
                            </Button>
                            
                            <Button v-if="can.startEncounter"
                                
                                variant="outline" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 bg-card shadow-2xs text-primary hover:bg-primary/5 border-primary/30"
                                @click="openNewEncounterModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Start Consultation</span>
                            </Button>

                            <Link :href="route('patients.index')">
                                <Button variant="outline" size="sm" class="h-7 text-xs font-semibold gap-1.5 bg-card">
                                    <Search class="w-3.5 h-3.5 text-muted-foreground" />
                                    <span>Search MPI</span>
                                </Button>
                            </Link>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        <!-- Modern Telemetry Tile Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 w-full">
                            
                            <!-- Metric 1: Queue Waiting -->
                            <div 
                                @click="handleNavClick('queue')"
                                class="p-3 rounded-lg bg-card hover:bg-muted/40 cursor-pointer transition-all space-y-1 shadow-2xs group"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground group-hover:text-foreground transition-colors">Queue Waiting</span>
                                    <Clock class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-amber-700 dark:text-amber-400">
                                    {{ patients.length }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">Triage pending</div>
                            </div>

                            <!-- Metric 2: Active Consults -->
                            <div 
                                @click="handleNavClick('encounters')"
                                class="p-3 rounded-lg bg-card hover:bg-muted/40 cursor-pointer transition-all space-y-1 shadow-2xs group"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground group-hover:text-foreground transition-colors">Active Consults</span>
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                </div>
                                <div class="text-xl font-mono font-extrabold text-primary">
                                    {{ encounters.length }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">In examination</div>
                            </div>

                            <!-- Metric 3: Formulary Drugs -->
                            <div class="p-3 rounded-lg bg-card space-y-1 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Formulary Rx</span>
                                    <Pill class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-foreground">
                                    {{ formularies.length }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">Active medications</div>
                            </div>

                            <!-- Metric 4: Station Pulse -->
                            <div class="p-3 rounded-lg bg-card space-y-1 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Station Pulse</span>
                                    <Activity class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                                </div>
                                <div class="text-xs font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5 mt-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Operational
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">Physician station online</div>
                            </div>
                        </div>

                        <!-- Live Patient Encounters -->
                        <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                            <div class="px-4 py-2.5 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground uppercase tracking-wider">
                                    <Stethoscope class="w-3.5 h-3.5 text-primary" />
                                    <span>Live Patient Consultations</span>
                                    <span class="text-[10px] font-mono text-muted-foreground ml-1">({{ encounters.length }})</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button v-if="can.startEncounter"
                                
                                        variant="outline" 
                                        size="sm" 
                                        class="h-6 text-[10px] font-bold text-primary gap-1 bg-card border-primary/30"
                                        @click="openNewEncounterModal()"
                                    >
                                        <Plus class="w-3 h-3" />
                                        <span>New Encounter</span>
                                    </Button>
                                    <button 
                                        @click="handleNavClick('queue')"
                                        class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1"
                                    >
                                        <span>View Queue</span>
                                        <ArrowUpRight class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>

                            <div class="w-full overflow-x-auto">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-8 text-[10px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1.5 px-4">Primary MRN</TableHead>
                                            <TableHead class="py-1.5 px-3">Patient Full Name</TableHead>
                                            <TableHead class="py-1.5 px-3">Attending Provider</TableHead>
                                            <TableHead class="py-1.5 px-3">Type</TableHead>
                                            <TableHead class="py-1.5 px-3">Status</TableHead>
                                            <TableHead class="py-1.5 px-4 text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="enc in encounters"
                                            :key="enc.id"
                                            class="h-10 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                            @click="openConsultation(enc.patient, enc)"
                                        >
                                            <TableCell class="py-1.5 px-4 font-mono font-bold text-foreground text-[11px]">{{ enc.patient?.primary_mrn }}</TableCell>
                                            <TableCell class="py-1.5 px-3">
                                                <div class="font-bold text-foreground">{{ enc.patient?.first_name }} {{ enc.patient?.last_name }}</div>
                                            </TableCell>
                                            <TableCell class="py-1.5 px-3 text-muted-foreground text-[11px]">
                                                {{ enc.provider ? `Dr. ${enc.provider.first_name || ''} ${enc.provider.last_name || ''}` : 'On-Duty Officer' }}
                                            </TableCell>
                                            <TableCell class="py-1.5 px-3 font-mono text-[10px] text-muted-foreground">{{ enc.encounter_type || 'OPD' }}</TableCell>
                                            <TableCell class="py-1.5 px-3">
                                                <AfyaStatusBadge :status="enc.status" dot />
                                            </TableCell>
                                            <TableCell class="py-1.5 px-4 text-right">
                                                <Button
                                                    variant="subtle"
                                                    size="sm"
                                                    class="h-6 px-2.5 text-[10px] font-semibold"
                                                    @click.stop="openConsultation(enc.patient, enc)"
                                                >
                                                    Open Chart
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="encounters.length === 0">
                                            <TableCell colspan="6" class="text-center py-10 text-muted-foreground text-xs">
                                                No active clinical encounters in session.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </AfyaWorkspaceMain>

                <!-- ============================================================== -->
                <!-- VIEW 2: PATIENT QUEUE (WAITING LIST)                           -->
                <!-- ============================================================== -->
                <AfyaWorkspaceMain
                    v-else-if="activeSection === 'queue'"
                    :breadcrumbs="[
                        { label: 'Clinical Workstation', href: route('workspace.clinical') },
                        { label: 'Patient Queue', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button v-if="can.startEncounter"
                                
                                variant="default" 
                                size="sm" 
                                :disabled="isStartingEncounter"
                                class="h-7 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="callNextPatient"
                            >
                                <Loader2 v-if="isStartingEncounter" class="w-3.5 h-3.5 animate-spin" />
                                <Stethoscope v-else class="w-3.5 h-3.5" />
                                <span>Call Next Patient</span>
                            </Button>
                            
                            <Button v-if="can.startEncounter"
                                
                                variant="outline" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 bg-card shadow-2xs text-primary hover:bg-primary/5 border-primary/30"
                                @click="openNewEncounterModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Start Consultation</span>
                            </Button>

                            <Link :href="route('patients.index')">
                                <Button variant="outline" size="sm" class="h-7 text-xs font-semibold gap-1.5 bg-card">
                                    <Search class="w-3.5 h-3.5 text-muted-foreground" />
                                    <span>Search MPI</span>
                                </Button>
                            </Link>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Queue Filter Tabs & Stats -->
                        <div class="flex items-center justify-between gap-3">
                            <div class="inline-flex items-center p-1 bg-muted/40 rounded-lg border border-border/50 text-xs">
                                <button
                                    type="button"
                                    class="px-3 py-1 rounded-md font-semibold text-xs transition-colors"
                                    :class="queueFilter === 'all' ? 'bg-card text-foreground shadow-2xs border border-border/60' : 'text-muted-foreground hover:text-foreground'"
                                    @click="queueFilter = 'all'"
                                >
                                    All Waiting ({{ patients.length }})
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-1 rounded-md font-semibold text-xs transition-colors flex items-center gap-1.5"
                                    :class="queueFilter === 'vitals_ready' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 shadow-2xs border border-emerald-300 dark:border-emerald-800' : 'text-muted-foreground hover:text-foreground'"
                                    @click="queueFilter = 'vitals_ready'"
                                >
                                    <HeartPulse class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                    <span>Vitals Ready ({{ vitalsReadyCount }})</span>
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-1 rounded-md font-semibold text-xs transition-colors flex items-center gap-1.5"
                                    :class="queueFilter === 'triage_pending' ? 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300 shadow-2xs border border-amber-300 dark:border-amber-800' : 'text-muted-foreground hover:text-foreground'"
                                    @click="queueFilter = 'triage_pending'"
                                >
                                    <Clock class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                    <span>Awaiting Triage ({{ triagePendingCount }})</span>
                                </button>
                            </div>

                            <button 
                                @click="handleNavClick('overview')"
                                class="text-[11px] text-muted-foreground hover:text-foreground font-medium transition-colors"
                            >
                                Return to Overview
                            </button>
                        </div>

                        <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col border border-border/60">
                            <div class="px-4 py-2.5 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground uppercase tracking-wider">
                                    <Users class="w-3.5 h-3.5 text-primary" />
                                    <span>Physician Waiting Queue</span>
                                    <span class="text-[10px] font-mono text-muted-foreground ml-1">({{ filteredQueuePatients.length }} shown)</span>
                                </div>
                                <span class="text-[10px] text-muted-foreground">Click on any patient or use "Call Patient" to begin consultation</span>
                            </div>

                            <div class="w-full overflow-x-auto">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-8 text-[10px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1.5 px-4">Primary MRN</TableHead>
                                            <TableHead class="py-1.5 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1.5 px-3">Demographics</TableHead>
                                            <TableHead class="py-1.5 px-3">Blood Group</TableHead>
                                            <TableHead class="py-1.5 px-3">Triage Vitals Status</TableHead>
                                            <TableHead class="py-1.5 px-4 text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="patient in filteredQueuePatients"
                                            :key="patient.id"
                                            class="h-11 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                            @click="openConsultation(patient)"
                                        >
                                            <!-- Primary MRN -->
                                            <TableCell class="py-1.5 px-4 font-mono font-bold text-foreground text-[11px]">{{ patient.primary_mrn }}</TableCell>
                                            
                                            <!-- Patient Name & Allergies -->
                                            <TableCell class="py-1.5 px-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-foreground">{{ patient.first_name }} {{ patient.last_name }}</span>
                                                    <span 
                                                        v-if="patient.allergies?.length > 0"
                                                        class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-800"
                                                    >
                                                        ⚠️ {{ patient.allergies[0].allergen }}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            
                                            <!-- Demographics -->
                                            <TableCell class="py-1.5 px-3 text-muted-foreground text-[11px]">
                                                {{ patient.gender || '—' }} · {{ patient.age ? `${patient.age}y` : (patient.formatted_dob || formatDate(patient.dob)) }}
                                            </TableCell>
                                            
                                            <!-- Blood Group -->
                                            <TableCell class="py-1.5 px-3 font-mono text-[11px] font-semibold text-rose-600 dark:text-rose-400">{{ patient.blood_group || 'O+' }}</TableCell>
                                            
                                            <!-- Triage Vitals Status -->
                                            <TableCell class="py-1.5 px-3">
                                                <span 
                                                    v-if="patient.latest_vital || (patient.vitals && patient.vitals.length > 0)"
                                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 shadow-2xs"
                                                >
                                                    <HeartPulse class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                                    <span>{{ (patient.latest_vital || patient.vitals[0]).systolic_bp }}/{{ (patient.latest_vital || patient.vitals[0]).diastolic_bp }} mmHg · {{ (patient.latest_vital || patient.vitals[0]).temperature_c }}°C · {{ (patient.latest_vital || patient.vitals[0]).heart_rate }} bpm</span>
                                                </span>
                                                <span 
                                                    v-else 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-300 dark:border-amber-800"
                                                >
                                                    <Clock class="w-3 h-3 text-amber-600 dark:text-amber-400" />
                                                    <span>Awaiting Triage</span>
                                                </span>
                                            </TableCell>
                                            
                                            <!-- Action Button -->
                                            <TableCell class="py-1.5 px-4 text-right">
                                                <Button
                                                    variant="default"
                                                    size="sm"
                                                    :class="[
                                                        (patient.latest_vital || (patient.vitals && patient.vitals.length > 0))
                                                            ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                                            : ''
                                                    ]"
                                                    class="h-6 px-2.5 text-[10px] font-semibold gap-1 shadow-2xs"
                                                    @click.stop="openConsultation(patient)"
                                                >
                                                    <Stethoscope class="w-3 h-3" />
                                                    <span>{{ (patient.latest_vital || (patient.vitals && patient.vitals.length > 0)) ? 'Call (Vitals Ready)' : 'Start Consult' }}</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredQueuePatients.length === 0">
                                            <TableCell colspan="6" class="text-center py-10 text-muted-foreground text-xs">
                                                No patients match the selected queue filter.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </AfyaWorkspaceMain>

                <!-- ============================================================== -->
                <!-- VIEW 3: ACTIVE ENCOUNTERS STREAM                               -->
                <!-- ============================================================== -->
                <AfyaWorkspaceMain
                    v-else-if="activeSection === 'encounters'"
                    :breadcrumbs="[
                        { label: 'Clinical Workstation', href: route('workspace.clinical') },
                        { label: 'Active Encounters', active: true }
                    ]"
                >
                    <template #actions>
                        <Button v-if="can.startEncounter"
                                
                            variant="default" 
                            size="sm" 
                            class="h-7 text-xs font-semibold gap-1 bg-primary text-primary-foreground shadow-2xs"
                            @click="openNewEncounterModal()"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Start Consultation</span>
                        </Button>
                    </template>

                    <div class="w-full space-y-4">
                        <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                            <div class="px-4 py-2.5 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground uppercase tracking-wider">
                                    <ClipboardList class="w-3.5 h-3.5 text-primary" />
                                    <span>All Active Hospital Encounters</span>
                                    <span class="text-[10px] font-mono text-muted-foreground ml-1">({{ encounters.length }})</span>
                                </div>
                                <button 
                                    @click="handleNavClick('overview')"
                                    class="text-[11px] text-muted-foreground hover:text-foreground font-medium transition-colors"
                                >
                                    Return to Overview
                                </button>
                            </div>

                            <div class="w-full overflow-x-auto">
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-8 text-[10px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1.5 px-4">Primary MRN</TableHead>
                                            <TableHead class="py-1.5 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1.5 px-3">Attending Provider</TableHead>
                                            <TableHead class="py-1.5 px-3">Reason for Visit</TableHead>
                                            <TableHead class="py-1.5 px-3">Status</TableHead>
                                            <TableHead class="py-1.5 px-4 text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="enc in encounters"
                                            :key="enc.id"
                                            class="h-10 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                            @click="openConsultation(enc.patient, enc)"
                                        >
                                            <TableCell class="py-1.5 px-4 font-mono font-bold text-foreground text-[11px]">{{ enc.patient?.primary_mrn }}</TableCell>
                                            <TableCell class="py-1.5 px-3">
                                                <div class="font-bold text-foreground">{{ enc.patient?.first_name }} {{ enc.patient?.last_name }}</div>
                                            </TableCell>
                                            <TableCell class="py-1.5 px-3 text-muted-foreground text-[11px]">
                                                {{ enc.provider ? `Dr. ${enc.provider.first_name || ''} ${enc.provider.last_name || ''}` : 'On-Duty Officer' }}
                                            </TableCell>
                                            <TableCell class="py-1.5 px-3 text-muted-foreground text-[11px] truncate max-w-[160px]">
                                                {{ enc.reason_for_visit || 'Consultation' }}
                                            </TableCell>
                                            <TableCell class="py-1.5 px-3">
                                                <AfyaStatusBadge :status="enc.status" dot />
                                            </TableCell>
                                            <TableCell class="py-1.5 px-4 text-right">
                                                <Button
                                                    variant="subtle"
                                                    size="sm"
                                                    class="h-6 px-2.5 text-[10px] font-semibold"
                                                    @click.stop="openConsultation(enc.patient, enc)"
                                                >
                                                    Resume Chart
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="encounters.length === 0">
                                            <TableCell colspan="6" class="text-center py-10 text-muted-foreground text-xs">
                                                No active encounters currently recorded.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </AfyaWorkspaceMain>

                <!-- ============================================================== -->
                <!-- VIEW 4: DIAGNOSTIC LABS WORKSTATION                            -->
                <!-- ============================================================== -->
                <AfyaWorkspaceMain
                    v-else-if="activeSection === 'labs'"
                    :breadcrumbs="[
                        { label: 'Clinical Workstation', href: route('workspace.clinical') },
                        { label: 'Diagnostic Pathology & Lab Workbench', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>Analyzer Link: Online</span>
                            </span>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        <!-- Top Diagnostic Telemetry Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div class="p-3 rounded-lg bg-card shadow-2xs space-y-1">
                                <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Active Lab Orders</div>
                                <div class="text-xl font-bold font-mono text-foreground">{{ recentLabOrders.length }}</div>
                                <div class="text-[10px] text-muted-foreground">Today's Ingestion Queue</div>
                            </div>
                            <div class="p-3 rounded-lg bg-card shadow-2xs space-y-1">
                                <div class="text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider">STAT Emergencies</div>
                                <div class="text-xl font-bold font-mono text-rose-600 dark:text-rose-400">
                                    {{ recentLabOrders.filter(o => o.priority === 'STAT').length }}
                                </div>
                                <div class="text-[10px] text-rose-600/80 dark:text-rose-400/80">Immediate Priority</div>
                            </div>
                            <div class="p-3 rounded-lg bg-card shadow-2xs space-y-1">
                                <div class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Pending Processing</div>
                                <div class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400">
                                    {{ recentLabOrders.filter(o => o.status !== 'Completed').length }}
                                </div>
                                <div class="text-[10px] text-amber-600/80 dark:text-amber-400/80">Bench In Progress</div>
                            </div>
                            <div class="p-3 rounded-lg bg-card shadow-2xs space-y-1">
                                <div class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Completed Findings</div>
                                <div class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                                    {{ recentLabOrders.filter(o => o.status === 'Completed').length }}
                                </div>
                                <div class="text-[10px] text-emerald-600/80 dark:text-emerald-400/80">Verified & Signed Off</div>
                            </div>
                        </div>

                        <!-- Diagnostic Orders Master Table -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                    <FlaskConical class="w-4 h-4 text-primary" />
                                    <span>Hospital Laboratory Orders Queue</span>
                                </h3>
                                <span class="text-[11px] font-mono text-muted-foreground">{{ recentLabOrders.length }} Orders Loaded</span>
                            </div>

                            <div class="bg-card rounded-lg overflow-hidden border border-border/70 shadow-2xs">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/30 text-[10px] uppercase font-bold text-muted-foreground">
                                            <TableHead class="py-2 px-3">Order Number</TableHead>
                                            <TableHead class="py-2 px-3">Patient Identity</TableHead>
                                            <TableHead class="py-2 px-3">Priority</TableHead>
                                            <TableHead class="py-2 px-3">Investigation Items</TableHead>
                                            <TableHead class="py-2 px-3">Status</TableHead>
                                            <TableHead class="py-2 px-3">Critical Safety</TableHead>
                                            <TableHead class="py-2 px-4 text-right">Bench Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="order in recentLabOrders"
                                            :key="order.id"
                                            class="hover:bg-muted/20 transition-colors"
                                        >
                                            <!-- Order Number -->
                                            <TableCell class="py-2 px-3 font-mono font-bold text-xs text-primary">
                                                {{ order.order_number }}
                                            </TableCell>

                                            <!-- Patient -->
                                            <TableCell class="py-2 px-3">
                                                <div class="min-w-0">
                                                    <div class="font-bold text-xs text-foreground truncate">
                                                        {{ order.patient?.first_name }} {{ order.patient?.last_name }}
                                                    </div>
                                                    <div class="text-[10px] font-mono text-muted-foreground">
                                                        {{ order.patient?.primary_mrn }}
                                                    </div>
                                                </div>
                                            </TableCell>

                                            <!-- Priority -->
                                            <TableCell class="py-2 px-3">
                                                <span 
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold"
                                                    :class="[
                                                        order.priority === 'STAT'
                                                            ? 'bg-rose-600 text-white animate-pulse'
                                                            : (order.priority === 'Urgent'
                                                                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30'
                                                                : 'bg-muted text-muted-foreground')
                                                    ]"
                                                >
                                                    {{ order.priority }}
                                                </span>
                                            </TableCell>

                                            <!-- Items -->
                                            <TableCell class="py-2 px-3">
                                                <div class="flex flex-wrap gap-1 max-w-xs">
                                                    <span 
                                                        v-for="item in (order.items || [])"
                                                        :key="item.id"
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] bg-muted/60 font-medium text-foreground border border-border/40"
                                                    >
                                                        <span>{{ item.lab_test?.name || 'Test' }}</span>
                                                        <span v-if="item.has_critical_value" class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    </span>
                                                </div>
                                            </TableCell>

                                            <!-- Status -->
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge :status="order.status" dot />
                                            </TableCell>

                                            <!-- Critical Alert -->
                                            <TableCell class="py-2 px-3">
                                                <span 
                                                    v-if="(order.items || []).some(i => i.has_critical_value)"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-600 text-white animate-pulse"
                                                >
                                                    <ShieldAlert class="w-3 h-3" />
                                                    <span>PANIC ALERT</span>
                                                </span>
                                                <span v-else class="text-[10px] text-muted-foreground">Normal / Clear</span>
                                            </TableCell>

                                            <!-- Bench Action -->
                                            <TableCell class="py-2 px-4 text-right">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <Button 
                                                        v-if="(order.items || []).length > 0"
                                                        variant="default" 
                                                        size="sm" 
                                                        class="h-6 px-2.5 text-[10px] font-semibold"
                                                        @click="openGlobalWorkbench(order.items[0])"
                                                    >
                                                        {{ order.status === 'Completed' ? 'View Findings' : 'Enter Results' }}
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="recentLabOrders.length === 0">
                                            <TableCell colspan="7" class="text-center py-10 text-muted-foreground text-xs">
                                                No laboratory orders recorded in the system yet.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </div>
                </AfyaWorkspaceMain>

                <!-- ============================================================== -->
                <!-- VIEW 5: ACTIVE CLINICAL CHARTING & CONSULTATION DESK           -->
                <!-- ============================================================== -->
                <AfyaWorkspaceMain
                    v-else-if="activeSection === 'charting' && activePatient"
                    :breadcrumbs="[
                        { label: 'Clinical Desk', href: route('workspace.clinical') },
                        { label: 'Consultation', href: '#' },
                        { label: `${activePatient.first_name} ${activePatient.last_name} (${activePatient.primary_mrn})`, active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1.5 bg-card"
                                @click="closeConsultation"
                            >
                                <ArrowLeft class="w-3 h-3" />
                                <span>Close Chart</span>
                            </Button>
                            <Button
                                v-if="can.completeEncounter"
                                variant="default"
                                size="sm"
                                :disabled="isCompletingEncounter"
                                class="h-7 text-xs font-semibold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                                @click="completeConsultation"
                            >
                                <Loader2 v-if="isCompletingEncounter" class="w-3 h-3 animate-spin" />
                                <CheckSquare v-else class="w-3 h-3" />
                                <span>{{ isCompletingEncounter ? 'Completing...' : 'Complete Visit' }}</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Top Patient Clinical Banner -->
                        <div class="p-2.5 bg-card rounded-lg flex items-center justify-between gap-3 shadow-2xs border border-border/60">
                            
                            <!-- Left: Patient Avatar & Demographics -->
                            <div class="flex items-center space-x-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center flex-shrink-0 border border-primary/20 shadow-2xs">
                                    {{ (activePatient.first_name?.[0] || '') + (activePatient.last_name?.[0] || '') }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-foreground text-xs truncate">{{ activePatient.first_name }} {{ activePatient.last_name }}</span>
                                        <span class="text-[9px] font-mono text-muted-foreground bg-muted/80 px-1.5 py-0.2 rounded border border-border/40">{{ activePatient.primary_mrn }}</span>
                                        <AfyaStatusBadge :status="activeEncounter?.status || 'In Progress'" dot />
                                    </div>
                                    <div class="text-[10px] text-muted-foreground flex items-center gap-1.5 mt-0.5">
                                        <span>{{ activePatient.gender }} · {{ activePatient.age ? `${activePatient.age}y` : (activePatient.formatted_dob || formatDate(activePatient.dob)) }}</span>
                                        <span class="text-border">·</span>
                                        <span class="font-semibold text-rose-600 dark:text-rose-400">Blood: {{ activePatient.blood_group || 'O+' }}</span>
                                        <span class="text-border">·</span>
                                        <span class="font-mono text-primary font-medium">{{ activeEncounter?.encounter_type || 'OPD' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Inline Allergy Alert Pill + Fast Vitals Summary Pill -->
                            <div class="flex items-center gap-2 flex-shrink-0">
                                
                                <!-- Documented Allergies Warning (Inline Chip) -->
                                <div 
                                    v-if="patientAllergies.length > 0"
                                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-rose-50 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-[10px] font-semibold animate-pulse"
                                    title="Patient has recorded critical allergies"
                                >
                                    <ShieldAlert class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 flex-shrink-0" />
                                    <span class="font-bold uppercase tracking-wider text-[9px] text-rose-700 dark:text-rose-300">Allergy:</span>
                                    <span class="truncate max-w-[200px]">{{ patientAllergies.map(a => a.allergen).join(', ') }}</span>
                                </div>

                                <!-- Fast Vitals Summary Pill if Recorded -->
                                <div v-if="(activeEncounter?.vitals || []).length > 0" class="flex items-center gap-1.5 bg-muted/40 px-2 py-1 rounded-md text-[10px] font-mono border border-border/40">
                                    <Activity class="w-3 h-3 text-primary flex-shrink-0" />
                                    <span class="text-foreground font-semibold">
                                        {{ activeEncounter.vitals[0].systolic_bp || activeEncounter.vitals[0].systolic }}/{{ activeEncounter.vitals[0].diastolic_bp || activeEncounter.vitals[0].diastolic }}
                                    </span>
                                    <span class="text-muted-foreground text-[9px]">mmHg</span>
                                    <span class="text-border">|</span>
                                    <span class="text-muted-foreground">
                                        {{ activeEncounter.vitals[0].heart_rate || activeEncounter.vitals[0].pulse_rate || '—' }} bpm
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Clinical Tabs Strip (Segmented Bar) -->
                        <div class="border-b border-border/60">
                            <nav class="-mb-px flex space-x-4 overflow-x-auto scrollbar-none">
                                <button
                                    @click="chartingTab = 'soap'"
                                    :class="chartingTab === 'soap' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <FileText class="w-3.5 h-3.5" />
                                    <span>SOAP Clinical Notes</span>
                                </button>

                                <button
                                    @click="chartingTab = 'vitals'"
                                    :class="chartingTab === 'vitals' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <Activity class="w-3.5 h-3.5" />
                                    <span>Vitals & Triage</span>
                                    <span v-if="(activeEncounter?.vitals || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-muted text-muted-foreground">
                                        {{ activeEncounter.vitals.length }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'rx'"
                                    :class="chartingTab === 'rx' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <Pill class="w-3.5 h-3.5" />
                                    <span>E-Prescription Pad</span>
                                    <span v-if="(activeEncounter?.prescriptions || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300">
                                        {{ activeEncounter.prescriptions.length }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'labs'"
                                    :class="chartingTab === 'labs' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <FlaskConical class="w-3.5 h-3.5" />
                                    <span>Lab Investigations</span>
                                    <span v-if="(activeEncounter?.lab_orders || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-primary/10 text-primary">
                                        {{ (activeEncounter.lab_orders || []).reduce((acc, o) => acc + (o.items?.length || 0), 0) }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'procedures'"
                                    :class="chartingTab === 'procedures' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <Scissors class="w-3.5 h-3.5" />
                                    <span>Procedures & Surgery</span>
                                    <span v-if="(activeEncounter?.procedure_orders || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">
                                        {{ activeEncounter.procedure_orders.length }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'imaging'"
                                    :class="chartingTab === 'imaging' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <Film class="w-3.5 h-3.5" />
                                    <span>Radiology & Imaging</span>
                                    <span v-if="(activeEncounter?.radiology_orders || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300">
                                        {{ activeEncounter.radiology_orders.length }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'consents'"
                                    :class="chartingTab === 'consents' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <FileSignature class="w-3.5 h-3.5" />
                                    <span>Consent</span>
                                    <span v-if="(activeEncounter?.consents || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-primary/10 text-primary">
                                        {{ activeEncounter.consents.length }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'referrals'"
                                    :class="chartingTab === 'referrals' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <ArrowUpRight class="w-3.5 h-3.5" />
                                    <span>Referrals</span>
                                    <span v-if="(activeEncounter?.referrals || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                        {{ activeEncounter.referrals.length }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'anc'"
                                    :class="chartingTab === 'anc' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <Heart class="w-3.5 h-3.5 text-rose-500" />
                                    <span>ANC / RCH</span>
                                    <span v-if="(activeEncounter?.anc_encounters || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                        {{ activeEncounter.anc_encounters.length }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'partograph'"
                                    :class="chartingTab === 'partograph' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <Activity class="w-3.5 h-3.5 text-amber-500" />
                                    <span>Partograph</span>
                                    <span v-if="(activeEncounter?.partograph_entries || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        {{ activeEncounter.partograph_entries.length }}
                                    </span>
                                </button>

                                <button
                                    @click="chartingTab = 'immunizations'"
                                    :class="chartingTab === 'immunizations' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                    class="py-2 px-1 border-b-2 text-xs uppercase tracking-wider flex items-center space-x-1.5 transition-all whitespace-nowrap"
                                >
                                    <Syringe class="w-3.5 h-3.5 text-emerald-500" />
                                    <span>EPI Vaccines</span>
                                    <span v-if="(activeEncounter?.immunizations || []).length" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        {{ activeEncounter.immunizations.length }}
                                    </span>
                                </button>
                            </nav>
                        </div>

                        <!-- Tab 1: SOAP Note -->
                        <div v-if="chartingTab === 'soap'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <SOAPNote
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :existing-notes="activeEncounter?.notes || []"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 2: Vitals -->
                        <div v-else-if="chartingTab === 'vitals'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <VitalsForm
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :existing-vitals="activeEncounter?.vitals || []"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 3: Prescriptions -->
                        <div v-else-if="chartingTab === 'rx'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <PrescriptionPad
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :patient-id="activePatient.id"
                                :patient="activePatient"
                                :allergies="patientAllergies"
                                :formularies="formularies"
                                :existing-prescriptions="activeEncounter?.prescriptions || []"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 4: Lab Investigations -->
                        <div v-else-if="chartingTab === 'labs'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <LabOrderPad
                                :encounter="activeEncounter"
                                :lab-tests="labTests"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 5: Procedures & Surgery Orders -->
                        <div v-else-if="chartingTab === 'procedures'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60 space-y-3">
                            <div class="flex items-center justify-between border-b border-border/60 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <Scissors class="w-4 h-4 text-primary" />
                                    <div>
                                        <div class="font-bold text-xs text-foreground">Clinical Procedures & Surgery Orders</div>
                                        <div class="text-[10px] text-muted-foreground">Order dressing, minor procedures, or theatre bookings routed to treatment desks.</div>
                                    </div>
                                </div>
                                <Button
                                    v-if="can.orderProcedure"
                                    variant="default"
                                    size="sm"
                                    class="h-7 text-xs font-semibold gap-1.5 shadow-2xs"
                                    @click="openDoctorProcedureModal"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>Order Procedure</span>
                                </Button>
                            </div>

                            <!-- Active Orders List for this Encounter -->
                            <div v-if="(activeEncounter?.procedure_orders || []).length > 0" class="space-y-2">
                                <div 
                                    v-for="pOrder in activeEncounter.procedure_orders" 
                                    :key="pOrder.id"
                                    class="p-2.5 bg-muted/20 rounded-lg border border-border/50 flex items-center justify-between text-xs"
                                >
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-primary text-[11px]">{{ pOrder.order_number }}</span>
                                            <span class="font-bold text-foreground">{{ pOrder.catalog?.name || 'Procedure' }}</span>
                                            <span 
                                                class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                                :class="pOrder.priority === 'Emergency' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : (pOrder.priority === 'Urgent' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-muted text-foreground')"
                                            >
                                                {{ pOrder.priority }}
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-muted-foreground">
                                            <span>Indication: {{ pOrder.clinical_indication || 'Clinical routine' }}</span>
                                            <span class="text-border mx-1">·</span>
                                            <span>Category: {{ pOrder.catalog?.category }} ({{ pOrder.catalog?.tier_level === 'Tier1_Minor' ? 'Dispensary / OPD' : 'Operating Theatre' }})</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <AfyaStatusBadge :status="pOrder.status" dot />
                                        <Link :href="route('procedures.workspace')" class="text-[10px] font-semibold text-primary hover:underline flex items-center gap-0.5">
                                            <span>Treatment Desk</span>
                                            <ArrowUpRight class="w-3 h-3" />
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-center py-8 text-muted-foreground text-xs space-y-2">
                                <Scissors class="w-8 h-8 text-muted-foreground/40 mx-auto" />
                                <div>No clinical procedures ordered for this encounter yet.</div>
                                <Button v-if="can.orderProcedure" variant="outline" size="sm" class="h-6.5 text-[10px] font-semibold gap-1" @click="openDoctorProcedureModal">
                                    <Plus class="w-3 h-3" />
                                    <span>Order First Procedure</span>
                                </Button>
                            </div>
                        </div>

                        <!-- Tab 6: Radiology & Imaging -->
                        <div v-else-if="chartingTab === 'imaging'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <ImagingOrderForm
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :existing-orders="activeEncounter?.radiology_orders || []"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 7: Informed Consent -->
                        <div v-else-if="chartingTab === 'consents'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <ConsentForm
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :existing-consents="activeEncounter?.consents || []"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 8: Referrals -->
                        <div v-else-if="chartingTab === 'referrals'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <ReferralForm
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :existing-referrals="activeEncounter?.referrals || []"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 9: Antenatal Care (ANC / RCH) -->
                        <div v-else-if="chartingTab === 'anc'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <AncVisitForm
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :existing-visits="activeEncounter?.anc_encounters || []"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 10: Partograph -->
                        <div v-else-if="chartingTab === 'partograph'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <PartographForm
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :existing-entries="activeEncounter?.partograph_entries || []"
                                :can="can"
                            />
                        </div>

                        <!-- Tab 11: Immunizations / EPI -->
                        <div v-else-if="chartingTab === 'immunizations'" class="bg-card rounded-lg p-3.5 shadow-2xs border border-border/60">
                            <ImmunizationForm
                                :encounter-id="activeEncounter?.id || 'demo'"
                                :existing-immunizations="activeEncounter?.immunizations || []"
                                :can="can"
                            />
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT PANEL: Modern Surface Telemetry Groups -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    :title="contextTitle"
                    :icon="contextIcon"
                    :width="width"
                    @close="close"
                >
                    <!-- CONTEXT 1: ACTIVE PATIENT CONSULTATION (PATIENT 360) -->
                    <div v-if="activeSection === 'charting' && activePatient" class="space-y-3 text-xs">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">
                                Active Consultation
                            </span>
                            <button 
                                @click="closeConsultation"
                                class="text-[10px] font-semibold text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-0.5"
                            >
                                <X class="w-3 h-3" />
                                <span>Close</span>
                            </button>
                        </div>

                        <AfyaPatientIdentity :patient="activePatient">
                            <AfyaStatusBadge :status="activePatient.status || 'Active'" dot />
                        </AfyaPatientIdentity>

                        <!-- Allergies Alert Card -->
                        <div class="p-3 rounded-lg bg-card space-y-1.5 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5 text-[11px] font-bold text-rose-600 dark:text-rose-400">
                                    <AlertTriangle class="w-3.5 h-3.5 text-rose-600 flex-shrink-0" />
                                    <span>Documented Allergies</span>
                                </div>
                                <span v-if="patientAllergies.length" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                    {{ patientAllergies.length }} Listed
                                </span>
                            </div>
                            <div class="text-[11px] space-y-1">
                                <div v-if="patientAllergies.length > 0">
                                    <div v-for="alg in patientAllergies" :key="alg.id" class="text-rose-700 dark:text-rose-300 font-medium truncate flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600 flex-shrink-0"></span>
                                        <span class="truncate">{{ alg.allergen }} ({{ alg.severity || 'Active' }})</span>
                                    </div>
                                </div>
                                <div v-else class="text-muted-foreground text-[10px] italic">
                                    No known drug allergies (NKDA).
                                </div>
                            </div>
                        </div>

                        <!-- Current Consultation Details Card -->
                        <div class="p-3 rounded-lg bg-card space-y-1.5 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                Current Encounter
                            </div>
                            <div class="space-y-1 text-[11px]">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Type:</span>
                                    <span class="font-bold text-foreground font-mono">{{ activeEncounter?.encounter_type || 'OPD' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Reason:</span>
                                    <span class="text-foreground font-medium truncate max-w-[140px]">
                                        {{ activeEncounter?.reason_for_visit || 'Routine Consultation' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Provider:</span>
                                    <span class="text-foreground truncate max-w-[140px]">
                                        {{ activeEncounter?.provider ? `Dr. ${activeEncounter.provider.first_name || ''} ${activeEncounter.provider.last_name || ''}` : 'On-Duty Physician' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <Link :href="route('patients.show', activePatient.id)" class="block pt-1">
                            <Button variant="outline" size="sm" class="w-full justify-center gap-1.5 text-xs h-8 bg-card shadow-2xs font-semibold">
                                <span>Open Full Patient 360</span>
                                <ArrowUpRight class="w-3.5 h-3.5" />
                            </Button>
                        </Link>
                    </div>

                    <!-- CONTEXT 2: PATIENT QUEUE INSIGHTS -->
                    <div v-else-if="activeSection === 'queue'" class="space-y-3 text-xs">
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                <span>Queue Telemetry</span>
                                <span class="flex items-center gap-1 text-amber-700 dark:text-amber-400 font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    Live
                                </span>
                            </div>
                            
                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Total In Queue:</span>
                                    <span class="font-mono font-bold text-amber-700 dark:text-amber-400">{{ patients.length }} Patients</span>
                                </div>
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Avg Wait Time:</span>
                                    <span class="font-mono font-bold text-foreground">~8 mins</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Triage Priority:</span>
                                    <span class="font-semibold text-emerald-700 dark:text-emerald-400">Normal Flow</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                Queue Action
                            </div>
                            <Button v-if="can.startEncounter"
                                
                                variant="default" 
                                size="sm" 
                                :disabled="isStartingEncounter"
                                class="w-full justify-start gap-2 text-xs h-8 font-semibold shadow-2xs"
                                @click="callNextPatient"
                            >
                                <Loader2 v-if="isStartingEncounter" class="w-3.5 h-3.5 animate-spin" />
                                <Stethoscope v-else class="w-3.5 h-3.5" />
                                <span>Call Next Waiting Patient</span>
                            </Button>
                        </div>
                    </div>

                    <!-- CONTEXT 3: ACTIVE ENCOUNTERS PULSE -->
                    <div v-else-if="activeSection === 'encounters'" class="space-y-3 text-xs">
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                <span>Consultations Pulse</span>
                                <span class="flex items-center gap-1 text-primary font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                    Active
                                </span>
                            </div>
                            
                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">In Examination:</span>
                                    <span class="font-mono font-bold text-primary">{{ encounters.length }} Consults</span>
                                </div>
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Consultation Type:</span>
                                    <span class="font-mono font-bold text-foreground">OPD General</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">On-Duty Doctors:</span>
                                    <span class="font-semibold text-foreground">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CONTEXT 4: DIAGNOSTIC LABS -->
                    <div v-else-if="activeSection === 'labs'" class="space-y-3 text-xs">
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                Laboratory Status
                            </div>
                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Lab Analyzer:</span>
                                    <span class="text-emerald-700 dark:text-emerald-400 font-bold">Online</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Turnaround:</span>
                                    <span class="font-mono text-foreground font-semibold">15–30 min</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CONTEXT 5: OVERVIEW MATRIX STATION TELEMETRY (DEFAULT) -->
                    <div v-else class="space-y-3 text-xs">
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                <span>Station Pulse</span>
                                <span class="flex items-center gap-1 text-emerald-700 dark:text-emerald-400 font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Active
                                </span>
                            </div>
                            
                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Waiting Queue:</span>
                                    <span class="font-mono font-bold text-amber-700 dark:text-amber-400">{{ patients.length }} Patients</span>
                                </div>
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Active Encounters:</span>
                                    <span class="font-mono font-bold text-primary">{{ encounters.length }} In Progress</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Formulary Items:</span>
                                    <span class="font-mono font-bold text-foreground">{{ formularies.length }} Ready</span>
                                </div>
                            </div>
                        </div>

                        <!-- 1-Click Fast Actions -->
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                Physician Shortcuts
                            </div>
                            <div class="space-y-1.5">
                                <Button v-if="can.startEncounter"
                                
                                    variant="outline" 
                                    size="sm" 
                                    :disabled="isStartingEncounter"
                                    class="w-full justify-start gap-2 text-xs h-8 bg-card shadow-2xs font-semibold"
                                    @click="callNextPatient"
                                >
                                    <Loader2 v-if="isStartingEncounter" class="w-3.5 h-3.5 animate-spin text-primary" />
                                    <Stethoscope v-else class="w-3.5 h-3.5 text-primary" />
                                    <span>Call Next Patient ({{ patients.length }})</span>
                                </Button>
                                <Button v-if="can.startEncounter"
                                
                                    variant="outline" 
                                    size="sm" 
                                    class="w-full justify-start gap-2 text-xs h-8 bg-card shadow-2xs font-semibold text-primary"
                                    @click="openNewEncounterModal()"
                                >
                                    <Plus class="w-3.5 h-3.5 text-primary" />
                                    <span>Start New Consultation</span>
                                </Button>
                                <Link :href="route('patients.index')" class="block">
                                    <Button variant="outline" size="sm" class="w-full justify-start gap-2 text-xs h-8 bg-card shadow-2xs font-semibold">
                                        <Search class="w-3.5 h-3.5 text-primary" />
                                        <span>Search Medical Records (MPI)</span>
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- Start New Consultation Modal -->
        <Modal :show="showNewEncounterModal" max-width="lg" @close="showNewEncounterModal = false">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Stethoscope class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Start New Patient Consultation</h3>
                    </div>
                    <button @click="showNewEncounterModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="createNewEncounter" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-foreground">Select Patient *</label>
                        <select
                            v-model="newEncounterForm.patient_id"
                            class="w-full h-8 rounded border border-border bg-card px-2 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                            required
                        >
                            <option value="" disabled>Choose patient from waiting list / MPI...</option>
                            <option v-for="p in (allPatients.length ? allPatients : patients)" :key="p.id" :value="p.id">
                                {{ p.first_name }} {{ p.last_name }} ({{ p.primary_mrn }}) · {{ p.gender }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-foreground">Encounter Type *</label>
                        <select
                            v-model="newEncounterForm.encounter_type"
                            class="w-full h-8 rounded border border-border bg-card px-2 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                            required
                        >
                            <option value="OPD Consultation">OPD General Consultation</option>
                            <option value="Specialist Clinic">Specialist Clinic Consultation</option>
                            <option value="Emergency Visit">Emergency / Acute Triage</option>
                            <option value="Antenatal Care (ANC)">Antenatal Care (ANC)</option>
                            <option value="Follow-up Review">Chronic / Follow-up Review</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-foreground">Reason for Visit / Chief Complaint</label>
                        <Input
                            v-model="newEncounterForm.reason_for_visit"
                            placeholder="e.g. Fever and chills for 3 days, headache, routine review..."
                            class="h-8 text-xs"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                        <Button variant="outline" size="sm" type="button" @click="showNewEncounterModal = false">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" :disabled="isStartingEncounter || !newEncounterForm.patient_id" class="gap-1.5 shadow-2xs">
                            <Loader2 v-if="isStartingEncounter" class="w-3.5 h-3.5 animate-spin" />
                            <Stethoscope v-else class="w-3.5 h-3.5" />
                            <span>{{ isStartingEncounter ? 'Creating Encounter...' : 'Start Consultation' }}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Global Lab Workbench Modal -->
        <LabWorkbenchModal 
            :show="showGlobalLabWorkbench" 
            :item="activeGlobalLabItem" 
            @close="showGlobalLabWorkbench = false" 
        />

        <!-- Doctor Consultation Procedure Order Modal -->
        <Modal :show="showClinicalProcModal" max-width="md" @close="showClinicalProcModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Scissors class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Order Clinical Procedure / Surgery</h3>
                    </div>
                    <button @click="showClinicalProcModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitDoctorProcedureOrder" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Select Procedure *</label>
                        <select 
                            v-model="clinicalProcForm.procedure_catalog_id" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option v-for="cat in procedureCatalogs" :key="cat.id" :value="cat.id">
                                {{ cat.name }} ({{ cat.category }}) — TZS {{ Number(cat.standard_price || 0).toLocaleString() }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Priority Urgency *</label>
                        <select 
                            v-model="clinicalProcForm.priority" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option value="Routine">Routine</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Clinical Indication / Instructions</label>
                        <Input 
                            v-model="clinicalProcForm.clinical_indication" 
                            placeholder="e.g. Abscess on left arm, needs I&amp;D; Wound dressing with Betadine" 
                            class="h-8.5 text-xs" 
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showClinicalProcModal = false" :disabled="isSubmittingProcOrder">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isSubmittingProcOrder || !clinicalProcForm.procedure_catalog_id">
                            <Loader2 v-if="isSubmittingProcOrder" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Order & Route to Desk</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    </AfyaShell>
</template>
