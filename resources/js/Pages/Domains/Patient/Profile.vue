<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { 
    Users, 
    LayoutDashboard,
    Activity, 
    ClipboardList, 
    FileText, 
    HeartPulse, 
    Pill, 
    AlertTriangle, 
    Gauge, 
    FlaskConical, 
    Microscope, 
    ScanLine, 
    Syringe, 
    Folder, 
    Share2, 
    Receipt, 
    Calendar,
    Phone,
    CreditCard,
    ShieldAlert,
    CheckCircle2,
    Clock,
    Plus,
    ArrowLeft,
    ArrowRight,
    Lock,
    ExternalLink,
    Building2,
    CalendarCheck,
    Stethoscope,
    UserCheck,
    HelpCircle,
    Film,
    RefreshCw,
    CheckCircle,
    XCircle
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';

// UI Primitives & Design Foundation
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import AfyaPatientIdentity from '@/Components/Afya/AfyaPatientIdentity.vue';
import AfyaClinicalAlert from '@/Components/Afya/AfyaClinicalAlert.vue';
import AfyaInput from '@/Components/Afya/AfyaInput.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    can: {
        type: Object,
        default: () => ({ radiology: true, billing: true }),
    },
    patient: {
        type: Object,
        required: true,
    },
    tab: {
        type: String,
        default: 'overview',
    },
});

// URL query synchronized tab state
const activeTab = ref(props.tab || 'overview');
const showContext = ref(true);

const setTab = (tabId) => {
    activeTab.value = tabId;
    // Keep URL in sync without reloading page state
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
};

// Handle initial URL tab query on mount
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (tabParam) {
        activeTab.value = tabParam;
    }
});

// Derived Data from Patient Record
const encounters = computed(() => props.patient.encounters || []);
const notes = computed(() => encounters.value.flatMap(e => (e.notes || []).map(n => ({ ...n, encounter: e }))));
const vitals = computed(() => encounters.value.flatMap(e => (e.vitals || []).map(v => ({ ...v, encounter: e }))));
const diagnoses = computed(() => encounters.value.flatMap(e => (e.diagnoses || []).map(d => ({ ...d, encounter: e }))));
const prescriptions = computed(() => encounters.value.flatMap(e => (e.prescriptions || []).map(p => ({ ...p, encounter: e }))));
const problems = computed(() => props.patient.problems || []);
const medicationReconciliations = computed(() => props.patient.medication_reconciliations || props.patient.medicationReconciliations || []);

// ==================== Problem List: record + resolve ====================
const showAddProblemModal = ref(false);
const problemForm = useForm({
    icd10_code: '',
    problem_name: '',
    clinical_status: 'Confirmed',
    severity: 'Moderate',
    onset_date: '',
    notes: '',
});

const openAddProblemModal = () => {
    problemForm.reset();
    problemForm.clearErrors();
    showAddProblemModal.value = true;
};

const submitProblem = () => {
    problemForm.post(route('clinical.problems.store', props.patient.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddProblemModal.value = false;
            problemForm.reset();
        },
    });
};

const resolveProblem = (problem) => {
    router.post(route('clinical.problems.resolve', problem.id), {}, { preserveScroll: true });
};

// ==================== Medication Reconciliation: record ====================
const showReconciliationModal = ref(false);
const emptyReconciliationMedication = () => ({
    medication_name: '',
    dosage: '',
    frequency: '',
    route: '',
    action_taken: 'Continue',
    clinical_rationale: '',
    substitute_medication_name: '',
    new_dosage_instructions: '',
});
const reconciliationForm = useForm({
    stage: 'Admission',
    medications: [emptyReconciliationMedication()],
});

const openReconciliationModal = () => {
    reconciliationForm.reset();
    reconciliationForm.medications = [emptyReconciliationMedication()];
    reconciliationForm.clearErrors();
    showReconciliationModal.value = true;
};

const addReconciliationMedicationRow = () => {
    reconciliationForm.medications.push(emptyReconciliationMedication());
};

const removeReconciliationMedicationRow = (index) => {
    if (reconciliationForm.medications.length > 1) {
        reconciliationForm.medications.splice(index, 1);
    }
};

const submitReconciliation = () => {
    reconciliationForm.post(route('pharmacy.reconciliation.store', props.patient.id), {
        preserveScroll: true,
        onSuccess: () => {
            showReconciliationModal.value = false;
            reconciliationForm.reset();
        },
    });
};

// ==================== Allergies: record + amend ====================
const showAddAllergyModal = ref(false);
const allergyForm = useForm({
    allergen_type: 'Drug',
    allergen: '',
    reaction: '',
    severity: 'Moderate',
});

const openAddAllergyModal = () => {
    allergyForm.reset();
    allergyForm.clearErrors();
    showAddAllergyModal.value = true;
};

const submitAllergy = () => {
    allergyForm.post(route('clinical.allergies.store', props.patient.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddAllergyModal.value = false;
            allergyForm.reset();
        },
    });
};

const showAmendAllergyModal = ref(false);
const amendingAllergy = ref(null);
const amendAllergyForm = useForm({
    allergen_type: 'Drug',
    allergen: '',
    reaction: '',
    severity: 'Moderate',
    status: 'Active',
    reason: '',
});

const openAmendAllergyModal = (allergy) => {
    amendingAllergy.value = allergy;
    amendAllergyForm.reset();
    amendAllergyForm.clearErrors();
    amendAllergyForm.allergen_type = allergy.allergen_type || 'Drug';
    amendAllergyForm.allergen = allergy.allergen;
    amendAllergyForm.reaction = allergy.reaction || '';
    amendAllergyForm.severity = allergy.severity || 'Moderate';
    amendAllergyForm.status = allergy.status || 'Active';
    amendAllergyForm.reason = '';
    showAmendAllergyModal.value = true;
};

const submitAmendAllergy = () => {
    amendAllergyForm.post(route('clinical.allergies.amend', amendingAllergy.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAmendAllergyModal.value = false;
            amendAllergyForm.reset();
        },
    });
};

const referrals = computed(() => props.patient.referrals || encounters.value.flatMap(e => (e.referrals || []).map(r => ({ ...r, encounter: e }))));
const radiologyOrders = computed(() => props.patient.radiology_orders || props.patient.radiologyOrders || encounters.value.flatMap(e => (e.radiology_orders || []).map(o => ({ ...o, encounter: e }))));
const invoices = computed(() => props.patient.invoices || []);
const appointments = computed(() => props.patient.appointments || []);
const allergies = computed(() => props.patient.allergies || []);
const activeAllergies = computed(() => allergies.value.filter(a => !a.is_deprecated));
const identifiers = computed(() => props.patient.identifiers || []);
const contacts = computed(() => props.patient.contacts || []);
const emergencyContacts = computed(() => props.patient.emergencyContacts || []);

const latestVitals = computed(() => vitals.value[0] || null);

// Unified Patient Timeline
const timelineEvents = computed(() => {
    const events = [];

    encounters.value.forEach(e => {
        events.push({
            id: `enc-${e.id}`,
            date: new Date(e.start_time || e.created_at),
            type: 'Encounter',
            title: `${e.encounter_type || 'OPD'} Consultation Started`,
            subtitle: e.reason_for_visit || 'Clinical visit',
            badge: e.status,
            icon: ClipboardList,
            iconColor: 'text-primary',
        });
    });

    notes.value.forEach(n => {
        events.push({
            id: `note-${n.id}`,
            date: new Date(n.created_at),
            type: 'Clinical Note',
            title: `${n.note_type || 'SOAP'} Note Authored`,
            subtitle: n.content?.assessment || 'Clinical documentation',
            badge: n.is_signed ? 'Signed' : 'Draft',
            icon: FileText,
            iconColor: 'text-sky-600',
        });
    });

    prescriptions.value.forEach(p => {
        events.push({
            id: `rx-${p.id}`,
            date: new Date(p.created_at),
            type: 'Prescription',
            title: `Rx: ${p.medication?.generic_name || 'Medication'} (${p.dosage || ''})`,
            subtitle: `${p.frequency || ''} · ${p.duration_days ? p.duration_days + ' days' : ''}`,
            badge: p.status,
            icon: Pill,
            iconColor: 'text-emerald-600',
        });
    });

    invoices.value.forEach(inv => {
        events.push({
            id: `inv-${inv.id}`,
            date: new Date(inv.created_at),
            type: 'Billing',
            title: `Invoice #${inv.invoice_number} Generated`,
            subtitle: `TZS ${Number(inv.total_amount).toLocaleString()} (${inv.status})`,
            badge: inv.status,
            icon: Receipt,
            iconColor: 'text-amber-600',
        });
    });

    return events.sort((a, b) => b.date - a.date);
});

// Categorized Patient 360 Navigation Tabs Tree
const summaryTabs = computed(() => [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard },
    { id: 'timeline', label: 'Timeline', icon: Activity, badge: timelineEvents.value.length },
]);

const clinicalTabs = computed(() => [
    { id: 'encounters', label: 'Encounters', icon: ClipboardList, badge: encounters.value.length },
    { id: 'notes', label: 'Clinical Notes', icon: FileText, badge: notes.value.length },
    { id: 'diagnoses', label: 'Encounter Diagnoses', icon: HeartPulse, badge: diagnoses.value.length },
    { id: 'problems', label: 'Problem List (Chronic)', icon: ClipboardList, badge: problems.value.length },
    { id: 'medications', label: 'Medications', icon: Pill, badge: prescriptions.value.length },
    { id: 'med-reconciliation', label: 'Med Reconciliation', icon: RefreshCw, badge: medicationReconciliations.value.length },
    { id: 'allergies', label: 'Allergies', icon: AlertTriangle, badge: allergies.value.length, alert: allergies.value.length > 0 },
    { id: 'vitals', label: 'Vitals', icon: Gauge, badge: vitals.value.length },
]);

const diagnosticTabs = computed(() => [
    { id: 'orders', label: 'Orders', icon: FlaskConical },
    { id: 'laboratory', label: 'Laboratory', icon: Microscope },
    ...(props.can.radiology ? [{ id: 'imaging', label: 'Imaging', icon: Film, badge: radiologyOrders.value.length }] : []),
    { id: 'procedures', label: 'Procedures', icon: Syringe },
]);

const adminTabs = computed(() => [
    { id: 'documents', label: 'Documents', icon: Folder },
    { id: 'referrals', label: 'Referrals', icon: Share2, badge: referrals.value.length },
    ...(props.can.billing ? [{ id: 'billing', label: 'Billing & Insurance', icon: Receipt, badge: invoices.value.length }] : []),
]);

const allNavTabs = computed(() => [
    ...summaryTabs.value,
    ...clinicalTabs.value,
    ...diagnosticTabs.value,
    ...adminTabs.value,
]);

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const totalOutstanding = computed(() => {
    return invoices.value.reduce((sum, inv) => sum + Math.max(0, Number(inv.total_amount) - Number(inv.paid_amount)), 0);
});
</script>

<template>
    <Head :title="`Patient 360 — ${patient.first_name} ${patient.last_name} (${patient.primary_mrn})`" />

    <AfyaShell active-module="patients">
        <AfyaWorkspace :show-sidebar="true" :show-context="showContext">

            <!-- 1. LEFT SIDEBAR: Categorized Patient 360 Clinical File -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="PATIENT 360"
                    :icon="Users"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <!-- Back to MPI Shortcut Header -->
                    <div class="pb-1.5 border-b border-border/80 mb-1">
                        <Link :href="route('patients.index')" class="w-full block">
                            <Button variant="ghost" size="sm" class="w-full justify-start gap-1.5 text-xs text-muted-foreground hover:text-foreground font-semibold h-8 px-2">
                                <ArrowLeft class="w-3.5 h-3.5" />
                                <span v-if="state !== 'collapsed'">Master Patient Index</span>
                            </Button>
                        </Link>
                    </div>

                    <!-- Category 1: Summary -->
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Summary
                    </div>
                    <AfyaSidebarItem
                        v-for="tab in summaryTabs"
                        :key="tab.id"
                        :label="tab.label"
                        :icon="tab.icon"
                        :badge="tab.badge"
                        :active="activeTab === tab.id"
                        :collapsed="state === 'collapsed'"
                        @click="setTab(tab.id)"
                    />

                    <!-- Category 2: Clinical File -->
                    <div v-if="state !== 'collapsed'" class="pt-2 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border mt-1.5">
                        Clinical File
                    </div>
                    <AfyaSidebarItem
                        v-for="tab in clinicalTabs"
                        :key="tab.id"
                        :label="tab.label"
                        :icon="tab.icon"
                        :badge="tab.badge"
                        :active="activeTab === tab.id"
                        :collapsed="state === 'collapsed'"
                        @click="setTab(tab.id)"
                    />

                    <!-- Category 3: Diagnostics & Procedures -->
                    <div v-if="state !== 'collapsed'" class="pt-2 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border mt-1.5">
                        Diagnostics & Labs
                    </div>
                    <AfyaSidebarItem
                        v-for="tab in diagnosticTabs"
                        :key="tab.id"
                        :label="tab.label"
                        :icon="tab.icon"
                        :badge="tab.badge"
                        :active="activeTab === tab.id"
                        :collapsed="state === 'collapsed'"
                        @click="setTab(tab.id)"
                    />

                    <!-- Category 4: Administrative -->
                    <div v-if="state !== 'collapsed'" class="pt-2 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border mt-1.5">
                        Administrative
                    </div>
                    <AfyaSidebarItem
                        v-for="tab in adminTabs"
                        :key="tab.id"
                        :label="tab.label"
                        :icon="tab.icon"
                        :badge="tab.badge"
                        :active="activeTab === tab.id"
                        :collapsed="state === 'collapsed'"
                        @click="setTab(tab.id)"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER WORKSPACE: Full Patient 360 Viewport -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Master Patient Index', href: route('patients.index') },
                        { label: patient.primary_mrn, href: route('patients.show', patient.id) },
                        { label: allNavTabs.find(t => t.id === activeTab)?.label || 'Overview', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-1.5">
                            <Link :href="route('encounters.workspace', encounters[0]?.id || 'demo')">
                                <Button variant="default" size="sm" class="gap-1.5">
                                    <Stethoscope class="w-3.5 h-3.5" />
                                    <span>Start Consultation</span>
                                </Button>
                            </Link>
                            <Link :href="route('billing.desk')">
                                <Button variant="outline" size="sm" class="gap-1.5">
                                    <Receipt class="w-3.5 h-3.5" />
                                    <span>Point of Sale</span>
                                </Button>
                            </Link>
                        </div>
                    </template>

                    <div class="w-full space-y-3.5">
                        <!-- Sticky Clinical Alert Banner if Patient has Allergies -->
                        <AfyaClinicalAlert
                            v-if="activeAllergies.length > 0"
                            title="Patient Clinical Safety Warning"
                            :message="`Documented Allergies: ${activeAllergies.map(a => `${a.allergen} (${a.severity} — ${a.reaction || 'Reaction unspecified'})`).join(', ')}`"
                            severity="critical"
                        />

                        <!-- ==================== TAB 1: OVERVIEW ==================== -->
                        <div v-if="activeTab === 'overview'" class="space-y-4">
                            <!-- Quick Metric Telemetry Strip -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <Card>
                                    <CardContent class="p-2.5">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Blood Group</div>
                                        <div class="text-xl font-mono font-bold text-primary mt-0.5">{{ patient.blood_group || 'Not Recorded' }}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent class="p-2.5">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Total Encounters</div>
                                        <div class="text-xl font-mono font-bold text-foreground mt-0.5">{{ encounters.length }}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent class="p-2.5">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Active Prescriptions</div>
                                        <div class="text-xl font-mono font-bold text-emerald-700 mt-0.5">{{ prescriptions.length }}</div>
                                    </CardContent>
                                </Card>
                                <Card v-if="can.billing">
                                    <CardContent class="p-2.5">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">POS Balance Due</div>
                                        <div class="text-lg font-mono font-bold mt-0.5" :class="totalOutstanding > 0 ? 'text-rose-700' : 'text-emerald-700'">
                                            TZS {{ formatCurrency(totalOutstanding) }}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- Latest Vitals Snapshot -->
                            <Card v-if="latestVitals">
                                <CardHeader class="pb-1.5">
                                    <CardTitle class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1.5">
                                            <Gauge class="w-3.5 h-3.5 text-primary" />
                                            <span>Latest Vital Signs</span>
                                        </span>
                                        <span class="text-[10px] font-normal text-muted-foreground font-mono">
                                            Recorded {{ new Date(latestVitals.recorded_at || latestVitals.created_at).toLocaleString() }}
                                        </span>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="pt-0">
                                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 text-center">
                                        <div class="p-2 rounded bg-muted/40 border border-border/60">
                                            <div class="text-[9px] text-muted-foreground uppercase font-bold">Temp</div>
                                            <div class="font-mono font-bold text-xs text-foreground">{{ latestVitals.temperature_c ? `${latestVitals.temperature_c}°C` : '—' }}</div>
                                        </div>
                                        <div class="p-2 rounded bg-muted/40 border border-border/60">
                                            <div class="text-[9px] text-muted-foreground uppercase font-bold">Heart Rate</div>
                                            <div class="font-mono font-bold text-xs text-foreground">{{ latestVitals.heart_rate ? `${latestVitals.heart_rate} bpm` : '—' }}</div>
                                        </div>
                                        <div class="p-2 rounded bg-muted/40 border border-border/60">
                                            <div class="text-[9px] text-muted-foreground uppercase font-bold">Blood Pressure</div>
                                            <div class="font-mono font-bold text-xs text-foreground">
                                                {{ latestVitals.systolic_bp && latestVitals.diastolic_bp ? `${latestVitals.systolic_bp}/${latestVitals.diastolic_bp}` : '—' }}
                                            </div>
                                        </div>
                                        <div class="p-2 rounded bg-muted/40 border border-border/60">
                                            <div class="text-[9px] text-muted-foreground uppercase font-bold">SpO2</div>
                                            <div class="font-mono font-bold text-xs text-foreground">{{ latestVitals.oxygen_saturation ? `${latestVitals.oxygen_saturation}%` : '—' }}</div>
                                        </div>
                                        <div class="p-2 rounded bg-muted/40 border border-border/60">
                                            <div class="text-[9px] text-muted-foreground uppercase font-bold">Resp Rate</div>
                                            <div class="font-mono font-bold text-xs text-foreground">{{ latestVitals.respiratory_rate ? `${latestVitals.respiratory_rate}/m` : '—' }}</div>
                                        </div>
                                        <div class="p-2 rounded bg-muted/40 border border-border/60">
                                            <div class="text-[9px] text-muted-foreground uppercase font-bold">Weight</div>
                                            <div class="font-mono font-bold text-xs text-foreground">{{ latestVitals.weight_kg ? `${latestVitals.weight_kg}kg` : '—' }}</div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Active Encounters and Quick Clinical Summary -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                <!-- Recent Consultations -->
                                <Card>
                                    <CardHeader class="pb-1">
                                        <CardTitle class="text-xs flex items-center justify-between">
                                            <span>Recent Consultations</span>
                                            <Button variant="link" size="sm" @click="setTab('encounters')" class="h-auto p-0 text-[11px]">View All</Button>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <div v-if="encounters.length > 0" class="divide-y divide-border/60 text-xs">
                                            <div v-for="enc in encounters.slice(0, 3)" :key="enc.id" class="py-2 flex items-center justify-between">
                                                <div>
                                                    <div class="font-semibold text-foreground">{{ enc.encounter_type || 'OPD' }} Visit</div>
                                                    <div class="text-[10px] text-muted-foreground">{{ enc.reason_for_visit || 'Routine care' }}</div>
                                                </div>
                                                <AfyaStatusBadge :status="enc.status" dot />
                                            </div>
                                        </div>
                                        <div v-else class="py-4 text-center text-muted-foreground text-xs">No recorded visits.</div>
                                    </CardContent>
                                </Card>

                                <!-- Active Prescriptions -->
                                <Card>
                                    <CardHeader class="pb-1">
                                        <CardTitle class="text-xs flex items-center justify-between">
                                            <span>Medication Formulary</span>
                                            <Button variant="link" size="sm" @click="setTab('medications')" class="h-auto p-0 text-[11px]">View All</Button>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <div v-if="prescriptions.length > 0" class="divide-y divide-border/60 text-xs">
                                            <div v-for="rx in prescriptions.slice(0, 3)" :key="rx.id" class="py-2 flex items-center justify-between">
                                                <div>
                                                    <div class="font-semibold text-foreground">{{ rx.medication?.generic_name }}</div>
                                                    <div class="text-[10px] text-muted-foreground">{{ rx.dosage }} · {{ rx.frequency }} ({{ rx.duration_days }}d)</div>
                                                </div>
                                                <AfyaStatusBadge :status="rx.status" dot />
                                            </div>
                                        </div>
                                        <div v-else class="py-4 text-center text-muted-foreground text-xs">No active medications.</div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <!-- ==================== TAB 2: TIMELINE ==================== -->
                        <div v-else-if="activeTab === 'timeline'" class="space-y-3">
                            <div class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-border">
                                <div
                                    v-for="ev in timelineEvents"
                                    :key="ev.id"
                                    class="relative group"
                                >
                                    <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-card border-2 border-primary flex items-center justify-center">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                    </div>
                                    <div class="bg-card border border-border rounded-md p-3 shadow-2xs hover:border-primary/50 transition">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-1.5 font-bold text-xs text-foreground">
                                                <component :is="ev.icon" class="w-3.5 h-3.5" :class="ev.iconColor" />
                                                <span>{{ ev.title }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <AfyaStatusBadge v-if="ev.badge" :status="ev.badge" dot />
                                                <span class="text-[10px] font-mono text-muted-foreground">{{ ev.date.toLocaleString() }}</span>
                                            </div>
                                        </div>
                                        <p v-if="ev.subtitle" class="text-xs text-muted-foreground mt-1">{{ ev.subtitle }}</p>
                                    </div>
                                </div>

                                <div v-if="timelineEvents.length === 0" class="py-12 text-center text-muted-foreground text-xs">
                                    No recorded clinical timeline events for this patient.
                                </div>
                            </div>
                        </div>

                        <!-- ==================== TAB 3: ENCOUNTERS ==================== -->
                        <div v-else-if="activeTab === 'encounters'" class="space-y-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Visit Date</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Attending Doctor</TableHead>
                                        <TableHead>Reason for Visit</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead class="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="enc in encounters" :key="enc.id">
                                        <TableCell class="font-mono text-xs">{{ new Date(enc.start_time || enc.created_at).toLocaleDateString() }}</TableCell>
                                        <TableCell class="font-semibold">{{ enc.encounter_type || 'OPD' }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground">
                                            {{ enc.provider ? `Dr. ${enc.provider.first_name || ''} ${enc.provider.last_name || ''}` : 'On-Duty Doctor' }}
                                        </TableCell>
                                        <TableCell class="text-xs">{{ enc.reason_for_visit || 'Routine Care' }}</TableCell>
                                        <TableCell><AfyaStatusBadge :status="enc.status" dot /></TableCell>
                                        <TableCell class="text-right">
                                            <Link :href="route('encounters.workspace', enc.id)">
                                                <Button variant="subtle" size="sm">Open Chart</Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="encounters.length === 0">
                                        <TableCell colspan="6" class="text-center py-8 text-muted-foreground">No visits recorded.</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 4: CLINICAL NOTES ==================== -->
                        <div v-else-if="activeTab === 'notes'" class="space-y-3">
                            <div v-for="n in notes" :key="n.id" class="bg-card border border-border rounded-md p-3 shadow-2xs space-y-2">
                                <div class="flex items-center justify-between border-b border-border pb-2">
                                    <div class="flex items-center gap-2">
                                        <FileText class="w-3.5 h-3.5 text-primary" />
                                        <span class="font-bold text-xs text-foreground">{{ n.note_type || 'SOAP' }} Consultation Note</span>
                                        <span v-if="n.is_signed" class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                            <Lock class="w-3 h-3 text-emerald-600" /> Signed & Sealed
                                        </span>
                                    </div>
                                    <span class="text-[10px] font-mono text-muted-foreground">{{ new Date(n.created_at).toLocaleString() }}</span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <span class="font-bold text-foreground block text-[11px]">Subjective:</span>
                                        <p class="text-muted-foreground">{{ n.content?.subjective || '—' }}</p>
                                    </div>
                                    <div>
                                        <span class="font-bold text-foreground block text-[11px]">Objective:</span>
                                        <p class="text-muted-foreground">{{ n.content?.objective || '—' }}</p>
                                    </div>
                                    <div>
                                        <span class="font-bold text-foreground block text-[11px]">Assessment:</span>
                                        <p class="text-muted-foreground">{{ n.content?.assessment || '—' }}</p>
                                    </div>
                                    <div>
                                        <span class="font-bold text-foreground block text-[11px]">Plan:</span>
                                        <p class="text-muted-foreground">{{ n.content?.plan || '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="notes.length === 0" class="py-12 text-center text-muted-foreground text-xs">
                                No clinical notes authored yet.
                            </div>
                        </div>

                        <!-- ==================== TAB 5: DIAGNOSES ==================== -->
                        <div v-else-if="activeTab === 'diagnoses'" class="space-y-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Condition / Diagnosis</TableHead>
                                        <TableHead>ICD-10 Code</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Onset Date</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="d in diagnoses" :key="d.id">
                                        <TableCell class="font-bold text-foreground">{{ d.condition_name || d.name }}</TableCell>
                                        <TableCell class="font-mono text-xs">{{ d.icd10_code || '—' }}</TableCell>
                                        <TableCell class="text-xs">{{ d.category || 'Primary' }}</TableCell>
                                        <TableCell class="font-mono text-xs">{{ d.onset_date ? new Date(d.onset_date).toLocaleDateString() : '—' }}</TableCell>
                                        <TableCell><AfyaStatusBadge :status="d.status || 'Active'" dot /></TableCell>
                                    </TableRow>

                                    <TableRow v-if="diagnoses.length === 0">
                                        <TableCell colspan="5" class="text-center py-8 text-muted-foreground">
                                            No active or chronic problem diagnoses recorded.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 5B: PROBLEM LIST (CHRONIC) ==================== -->
                        <div v-else-if="activeTab === 'problems'" class="space-y-3">
                            <div class="flex items-center justify-end">
                                <Button v-if="can.storeProblem" size="sm" @click="openAddProblemModal">
                                    <Plus class="w-3.5 h-3.5 mr-1" /> Add Problem
                                </Button>
                            </div>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Problem / Condition Title</TableHead>
                                        <TableHead>ICD-10 Code</TableHead>
                                        <TableHead>Clinical Status</TableHead>
                                        <TableHead>Onset Date</TableHead>
                                        <TableHead>Severity</TableHead>
                                        <TableHead>Clinical Notes</TableHead>
                                        <TableHead class="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="prob in problems" :key="prob.id">
                                        <TableCell class="font-bold text-foreground">{{ prob.problem_name }}</TableCell>
                                        <TableCell class="font-mono text-xs">{{ prob.icd10_code || '—' }}</TableCell>
                                        <TableCell>
                                            <AfyaStatusBadge :status="prob.status || 'Active'" dot />
                                        </TableCell>
                                        <TableCell class="font-mono text-xs">{{ prob.onset_date ? new Date(prob.onset_date).toLocaleDateString() : '—' }}</TableCell>
                                        <TableCell>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                                :class="prob.severity === 'Severe' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : 'bg-muted text-foreground'"
                                            >
                                                {{ prob.severity || 'Moderate' }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ prob.notes || '—' }}</TableCell>
                                        <TableCell class="text-right">
                                            <Button v-if="prob.status !== 'Resolved' && can.storeProblem" size="sm" variant="outline" @click="resolveProblem(prob)">
                                                <CheckCircle class="w-3.5 h-3.5 mr-1" /> Resolve
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="problems.length === 0">
                                        <TableCell colspan="7" class="text-center py-8 text-muted-foreground">
                                            No chronic problems or longitudinal diagnoses documented.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 6: MEDICATIONS ==================== -->
                        <div v-else-if="activeTab === 'medications'" class="space-y-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Medication Formulation</TableHead>
                                        <TableHead>Dosage & Route</TableHead>
                                        <TableHead>Frequency</TableHead>
                                        <TableHead>Duration</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="rx in prescriptions" :key="rx.id">
                                        <TableCell>
                                            <div class="font-bold text-foreground">{{ rx.medication?.generic_name }}</div>
                                            <div class="text-[10px] text-muted-foreground font-mono">{{ rx.medication?.strength }} · {{ rx.medication?.form }}</div>
                                        </TableCell>
                                        <TableCell class="text-xs">{{ rx.dosage }} ({{ rx.route }})</TableCell>
                                        <TableCell class="font-mono text-xs">{{ rx.frequency }}</TableCell>
                                        <TableCell class="font-mono text-xs">{{ rx.duration_days }} days</TableCell>
                                        <TableCell><AfyaStatusBadge :status="rx.status" dot /></TableCell>
                                    </TableRow>

                                    <TableRow v-if="prescriptions.length === 0">
                                        <TableCell colspan="5" class="text-center py-8 text-muted-foreground">No medications on file.</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 6B: MEDICATION RECONCILIATION ==================== -->
                        <div v-else-if="activeTab === 'med-reconciliation'" class="space-y-3">
                            <div class="flex items-center justify-end">
                                <Button v-if="can.storeReconciliation" size="sm" @click="openReconciliationModal">
                                    <RefreshCw class="w-3.5 h-3.5 mr-1" /> Record Reconciliation
                                </Button>
                            </div>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Reconciled At</TableHead>
                                        <TableHead>Stage</TableHead>
                                        <TableHead>Medication</TableHead>
                                        <TableHead>Dosage / Route</TableHead>
                                        <TableHead>Action Taken</TableHead>
                                        <TableHead>Reconciled By</TableHead>
                                        <TableHead>Rationale</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="mr in medicationReconciliations" :key="mr.id">
                                        <TableCell class="font-mono text-xs text-muted-foreground">{{ mr.reconciled_at ? new Date(mr.reconciled_at).toLocaleDateString() : '—' }}</TableCell>
                                        <TableCell class="font-bold text-foreground">{{ mr.stage }}</TableCell>
                                        <TableCell class="text-xs">{{ mr.medication_name }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ [mr.dosage, mr.frequency, mr.route].filter(Boolean).join(' · ') || '—' }}</TableCell>
                                        <TableCell><AfyaStatusBadge :status="mr.action_taken || 'Continue'" dot /></TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ mr.reconciler ? `${mr.reconciler.first_name} ${mr.reconciler.last_name}` : '—' }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ mr.clinical_rationale || '—' }}</TableCell>
                                    </TableRow>
                                    <TableRow v-if="medicationReconciliations.length === 0">
                                        <TableCell colspan="7" class="text-center py-8 text-muted-foreground">
                                            No medication reconciliation sessions recorded for this patient.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 7: ALLERGIES ==================== -->
                        <div v-else-if="activeTab === 'allergies'" class="space-y-3">
                            <div class="flex items-center justify-end">
                                <Button v-if="can.recordAllergy" size="sm" @click="openAddAllergyModal">
                                    <Plus class="w-3.5 h-3.5 mr-1" /> Add Allergy
                                </Button>
                            </div>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Allergen</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Severity</TableHead>
                                        <TableHead>Reaction</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead class="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="alg in activeAllergies" :key="alg.id">
                                        <TableCell class="font-bold text-rose-700">
                                            {{ alg.allergen }}
                                            <span v-if="alg.is_amendment" class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-muted text-muted-foreground align-middle">Amended</span>
                                        </TableCell>
                                        <TableCell class="text-xs">{{ alg.allergen_type || 'Drug' }}</TableCell>
                                        <TableCell>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                                {{ alg.severity }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ alg.reaction || '—' }}</TableCell>
                                        <TableCell><AfyaStatusBadge :status="alg.status || 'Active'" dot /></TableCell>
                                        <TableCell class="text-right">
                                            <Button v-if="can.amendAllergy" size="sm" variant="outline" @click="openAmendAllergyModal(alg)">
                                                Amend
                                            </Button>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="activeAllergies.length === 0">
                                        <TableCell colspan="6" class="text-center py-8 text-muted-foreground">
                                            No known allergies recorded (NKDA).
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 8: VITALS ==================== -->
                        <div v-else-if="activeTab === 'vitals'" class="space-y-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Recorded At</TableHead>
                                        <TableHead>Temp (°C)</TableHead>
                                        <TableHead>Pulse (bpm)</TableHead>
                                        <TableHead>BP (mmHg)</TableHead>
                                        <TableHead>SpO2 (%)</TableHead>
                                        <TableHead>Resp Rate</TableHead>
                                        <TableHead>Weight (kg)</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="v in vitals" :key="v.id">
                                        <TableCell class="font-mono text-xs">{{ new Date(v.recorded_at || v.created_at).toLocaleString() }}</TableCell>
                                        <TableCell class="font-mono">{{ v.temperature_c || '—' }}</TableCell>
                                        <TableCell class="font-mono">{{ v.heart_rate || '—' }}</TableCell>
                                        <TableCell class="font-mono">{{ v.systolic_bp && v.diastolic_bp ? `${v.systolic_bp}/${v.diastolic_bp}` : '—' }}</TableCell>
                                        <TableCell class="font-mono">{{ v.oxygen_saturation || '—' }}</TableCell>
                                        <TableCell class="font-mono">{{ v.respiratory_rate || '—' }}</TableCell>
                                        <TableCell class="font-mono">{{ v.weight_kg || '—' }}</TableCell>
                                    </TableRow>

                                    <TableRow v-if="vitals.length === 0">
                                        <TableCell colspan="7" class="text-center py-8 text-muted-foreground">No vitals history recorded.</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 9: ORDERS ==================== -->
                        <div v-else-if="activeTab === 'orders'" class="py-16 text-center text-muted-foreground text-xs space-y-2">
                            <FlaskConical class="w-8 h-8 mx-auto text-muted-foreground opacity-40" />
                            <p class="font-semibold text-foreground text-xs">Diagnostic & Clinical Orders</p>
                            <p class="text-[11px]">Active and historical physician orders for laboratory, imaging, and procedures.</p>
                        </div>

                        <!-- ==================== TAB 10: LABORATORY ==================== -->
                        <div v-else-if="activeTab === 'laboratory'" class="py-16 text-center text-muted-foreground text-xs space-y-2">
                            <Microscope class="w-8 h-8 mx-auto text-muted-foreground opacity-40" />
                            <p class="font-semibold text-foreground text-xs">Laboratory Reports & Panels</p>
                            <p class="text-[11px]">Hematology, biochemistry, and microbiology verified reports.</p>
                        </div>

                        <!-- ==================== TAB 11: IMAGING ==================== -->
                        <div v-else-if="activeTab === 'imaging'" class="space-y-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Order #</TableHead>
                                        <TableHead>Date Placed</TableHead>
                                        <TableHead>Modality</TableHead>
                                        <TableHead>Procedure / Study</TableHead>
                                        <TableHead>Priority</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Radiology Impression / Report</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="rad in radiologyOrders" :key="rad.id">
                                        <TableCell class="font-mono font-bold text-primary text-xs">{{ rad.order_number }}</TableCell>
                                        <TableCell class="font-mono text-xs text-muted-foreground">{{ new Date(rad.created_at).toLocaleDateString() }}</TableCell>
                                        <TableCell class="font-bold text-xs">{{ rad.modality }}</TableCell>
                                        <TableCell class="font-semibold text-foreground text-xs">{{ rad.procedure_name }}</TableCell>
                                        <TableCell>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                                :class="rad.priority === 'STAT' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : 'bg-muted text-foreground'"
                                            >
                                                {{ rad.priority }}
                                            </span>
                                        </TableCell>
                                        <TableCell><AfyaStatusBadge :status="rad.status" dot /></TableCell>
                                        <TableCell class="text-xs text-muted-foreground max-w-[250px] truncate">
                                            {{ rad.reports?.[0]?.impression || rad.reports?.[0]?.findings || 'Pending acquisition & reporting' }}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="radiologyOrders.length === 0">
                                        <TableCell colspan="7" class="text-center py-8 text-muted-foreground">
                                            No radiology or diagnostic imaging orders on file.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 12: PROCEDURES ==================== -->
                        <div v-else-if="activeTab === 'procedures'" class="py-16 text-center text-muted-foreground text-xs space-y-2">
                            <Syringe class="w-8 h-8 mx-auto text-muted-foreground opacity-40" />
                            <p class="font-semibold text-foreground text-xs">Clinical Procedures</p>
                            <p class="text-[11px]">Operative reports and minor clinical procedures on record.</p>
                        </div>

                        <!-- ==================== TAB 13: DOCUMENTS ==================== -->
                        <div v-else-if="activeTab === 'documents'" class="py-16 text-center text-muted-foreground text-xs space-y-2">
                            <Folder class="w-8 h-8 mx-auto text-muted-foreground opacity-40" />
                            <p class="font-semibold text-foreground text-xs">Patient Documents & Scans</p>
                            <p class="text-[11px]">Attached national identification cards, insurance cards, and consent forms.</p>
                        </div>

                        <!-- ==================== TAB 14: REFERRALS ==================== -->
                        <div v-else-if="activeTab === 'referrals'" class="space-y-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Referral #</TableHead>
                                        <TableHead>Urgency</TableHead>
                                        <TableHead>Destination Facility</TableHead>
                                        <TableHead>Specialty</TableHead>
                                        <TableHead>Reason for Referral</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="refItem in referrals" :key="refItem.id">
                                        <TableCell class="font-mono text-xs text-muted-foreground">{{ new Date(refItem.created_at).toLocaleDateString() }}</TableCell>
                                        <TableCell class="font-mono font-bold text-primary text-xs">{{ refItem.referral_number || 'REF-EXT' }}</TableCell>
                                        <TableCell>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                                :class="refItem.urgency === 'Emergency' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : (refItem.urgency === 'Urgent' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-muted text-foreground')"
                                            >
                                                {{ refItem.urgency }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="font-bold text-foreground text-xs">{{ refItem.external_facility_name || refItem.to_facility?.name || 'Referral Center' }}</TableCell>
                                        <TableCell class="text-xs">{{ refItem.specialty_required }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground max-w-[220px] truncate">{{ refItem.reason_for_referral }}</TableCell>
                                        <TableCell><AfyaStatusBadge :status="refItem.status || 'Pending'" dot /></TableCell>
                                    </TableRow>
                                    <TableRow v-if="referrals.length === 0">
                                        <TableCell colspan="7" class="text-center py-8 text-muted-foreground">
                                            No outbound or inbound referrals documented.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- ==================== TAB 15: BILLING & INSURANCE ==================== -->
                        <div v-else-if="activeTab === 'billing'" class="space-y-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Invoice #</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Total Amount</TableHead>
                                        <TableHead>Paid Amount</TableHead>
                                        <TableHead>Balance Due</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="inv in invoices" :key="inv.id">
                                        <TableCell class="font-mono font-bold text-foreground">{{ inv.invoice_number }}</TableCell>
                                        <TableCell class="font-mono text-xs">{{ new Date(inv.created_at).toLocaleDateString() }}</TableCell>
                                        <TableCell class="font-mono">TZS {{ formatCurrency(inv.total_amount) }}</TableCell>
                                        <TableCell class="font-mono text-emerald-700">TZS {{ formatCurrency(inv.paid_amount) }}</TableCell>
                                        <TableCell class="font-mono font-bold" :class="Number(inv.total_amount) - Number(inv.paid_amount) > 0 ? 'text-rose-700' : 'text-muted-foreground'">
                                            TZS {{ formatCurrency(Math.max(0, Number(inv.total_amount) - Number(inv.paid_amount))) }}
                                        </TableCell>
                                        <TableCell><AfyaStatusBadge :status="inv.status" dot /></TableCell>
                                    </TableRow>

                                    <TableRow v-if="invoices.length === 0">
                                        <TableCell colspan="6" class="text-center py-8 text-muted-foreground">No invoices generated.</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT PANEL: Patient Demographics & Contacts -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Patient 360 Demographics"
                    :icon="Users"
                    :width="width"
                    @close="close"
                >
                    <div class="space-y-3 text-xs">
                        <AfyaPatientIdentity :patient="patient">
                            <AfyaStatusBadge :status="patient.status || 'Active'" dot />
                        </AfyaPatientIdentity>

                        <!-- Identifiers (NIDA, NHIF) -->
                        <Card>
                            <CardHeader><CardTitle>National Identifiers</CardTitle></CardHeader>
                            <CardContent class="space-y-1.5 text-xs">
                                <div v-for="id in identifiers" :key="id.id" class="flex justify-between">
                                    <span class="text-muted-foreground">{{ id.type }}:</span>
                                    <span class="font-mono font-bold text-foreground">{{ id.identifier_value }}</span>
                                </div>
                                <div v-if="identifiers.length === 0" class="text-muted-foreground text-center py-2">
                                    No external identifiers on file.
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Contacts (Phone, Email) -->
                        <Card>
                            <CardHeader><CardTitle>Contact Information</CardTitle></CardHeader>
                            <CardContent class="space-y-1.5 text-xs">
                                <div v-for="c in contacts" :key="c.id" class="flex justify-between">
                                    <span class="text-muted-foreground">{{ c.contact_type }}:</span>
                                    <span class="font-mono text-foreground">{{ c.value }}</span>
                                </div>
                                <div v-if="contacts.length === 0" class="text-muted-foreground text-center py-2">
                                    No phone or email contacts recorded.
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Emergency Contacts -->
                        <Card>
                            <CardHeader><CardTitle>Emergency Contacts (Next of Kin)</CardTitle></CardHeader>
                            <CardContent class="space-y-2 text-xs">
                                <div v-for="em in emergencyContacts" :key="em.id" class="border-b border-border pb-1.5 last:border-none">
                                    <div class="font-bold text-foreground">{{ em.name }} ({{ em.relationship }})</div>
                                    <div class="font-mono text-muted-foreground text-[11px]">{{ em.phone_number }}</div>
                                </div>
                                <div v-if="emergencyContacts.length === 0" class="text-muted-foreground text-center py-2">
                                    No emergency next-of-kin listed.
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </AfyaContextPanel>
            </template>

        </AfyaWorkspace>

        <!-- Add Problem Modal -->
        <Modal :show="showAddProblemModal" max-width="lg" @close="showAddProblemModal = false">
            <div class="p-6 space-y-4">
                <h3 class="text-base font-bold text-foreground">Add Problem</h3>
                <form @submit.prevent="submitProblem" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <AfyaInput
                            v-model="problemForm.problem_name"
                            label="Problem / Condition Title"
                            required
                            :error="problemForm.errors.problem_name"
                            wrapper-class="col-span-2"
                        />
                        <AfyaInput
                            v-model="problemForm.icd10_code"
                            label="ICD-10 Code"
                            required
                            placeholder="e.g. I10"
                            :error="problemForm.errors.icd10_code"
                        />
                        <AfyaInput
                            v-model="problemForm.onset_date"
                            type="date"
                            label="Onset Date"
                            :error="problemForm.errors.onset_date"
                        />
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-foreground">Clinical Status</label>
                            <select v-model="problemForm.clinical_status" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                                <option value="Confirmed">Confirmed</option>
                                <option value="Provisional">Provisional</option>
                                <option value="Differential">Differential</option>
                            </select>
                            <InputError :message="problemForm.errors.clinical_status" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-foreground">Severity</label>
                            <select v-model="problemForm.severity" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                                <option value="Mild">Mild</option>
                                <option value="Moderate">Moderate</option>
                                <option value="Severe">Severe</option>
                            </select>
                            <InputError :message="problemForm.errors.severity" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-foreground">Clinical Notes</label>
                        <textarea v-model="problemForm.notes" rows="3" class="w-full p-2.5 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 text-xs"></textarea>
                        <InputError :message="problemForm.errors.notes" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <Button type="button" variant="outline" size="sm" @click="showAddProblemModal = false">Cancel</Button>
                        <Button type="submit" size="sm" :disabled="problemForm.processing">
                            {{ problemForm.processing ? 'Saving…' : 'Add Problem' }}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Add Allergy Modal -->
        <Modal :show="showAddAllergyModal" max-width="lg" @close="showAddAllergyModal = false">
            <div class="p-6 space-y-4">
                <h3 class="text-base font-bold text-foreground">Add Allergy</h3>
                <form @submit.prevent="submitAllergy" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <AfyaInput
                            v-model="allergyForm.allergen"
                            label="Allergen"
                            required
                            placeholder="e.g. Penicillin"
                            :error="allergyForm.errors.allergen"
                            wrapper-class="col-span-2"
                        />
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-foreground">Allergen Type</label>
                            <select v-model="allergyForm.allergen_type" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                                <option value="Drug">Drug</option>
                                <option value="Food">Food</option>
                                <option value="Environmental">Environmental</option>
                                <option value="Other">Other</option>
                            </select>
                            <InputError :message="allergyForm.errors.allergen_type" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-foreground">Severity</label>
                            <select v-model="allergyForm.severity" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                                <option value="Mild">Mild</option>
                                <option value="Moderate">Moderate</option>
                                <option value="Severe">Severe</option>
                            </select>
                            <InputError :message="allergyForm.errors.severity" />
                        </div>
                        <AfyaInput
                            v-model="allergyForm.reaction"
                            label="Reaction"
                            placeholder="e.g. Rash, Anaphylaxis"
                            :error="allergyForm.errors.reaction"
                            wrapper-class="col-span-2"
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <Button type="button" variant="outline" size="sm" @click="showAddAllergyModal = false">Cancel</Button>
                        <Button type="submit" size="sm" :disabled="allergyForm.processing">
                            {{ allergyForm.processing ? 'Saving…' : 'Add Allergy' }}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Amend Allergy Modal -->
        <Modal :show="showAmendAllergyModal" max-width="lg" @close="showAmendAllergyModal = false">
            <div class="p-6 space-y-4">
                <h3 class="text-base font-bold text-foreground">Amend Allergy Record</h3>
                <p class="text-xs text-muted-foreground -mt-2">
                    The original record is preserved and superseded by this amendment — it is not overwritten.
                </p>
                <form @submit.prevent="submitAmendAllergy" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <AfyaInput
                            v-model="amendAllergyForm.allergen"
                            label="Allergen"
                            required
                            :error="amendAllergyForm.errors.allergen"
                            wrapper-class="col-span-2"
                        />
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-foreground">Allergen Type</label>
                            <select v-model="amendAllergyForm.allergen_type" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                                <option value="Drug">Drug</option>
                                <option value="Food">Food</option>
                                <option value="Environmental">Environmental</option>
                                <option value="Other">Other</option>
                            </select>
                            <InputError :message="amendAllergyForm.errors.allergen_type" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-foreground">Severity</label>
                            <select v-model="amendAllergyForm.severity" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                                <option value="Mild">Mild</option>
                                <option value="Moderate">Moderate</option>
                                <option value="Severe">Severe</option>
                            </select>
                            <InputError :message="amendAllergyForm.errors.severity" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-foreground">Status</label>
                            <select v-model="amendAllergyForm.status" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <InputError :message="amendAllergyForm.errors.status" />
                        </div>
                        <AfyaInput
                            v-model="amendAllergyForm.reaction"
                            label="Reaction"
                            :error="amendAllergyForm.errors.reaction"
                            wrapper-class="col-span-2"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-semibold text-foreground">Reason for Amendment</label>
                        <textarea v-model="amendAllergyForm.reason" rows="2" class="w-full p-2.5 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 text-xs" placeholder="e.g. Confirmed with patient during medication reconciliation — reaction was intolerance, not true allergy"></textarea>
                        <InputError :message="amendAllergyForm.errors.reason" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <Button type="button" variant="outline" size="sm" @click="showAmendAllergyModal = false">Cancel</Button>
                        <Button type="submit" size="sm" :disabled="amendAllergyForm.processing">
                            {{ amendAllergyForm.processing ? 'Saving…' : 'Save Amendment' }}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Record Medication Reconciliation Modal -->
        <Modal :show="showReconciliationModal" max-width="3xl" @close="showReconciliationModal = false">
            <div class="p-6 space-y-4">
                <h3 class="text-base font-bold text-foreground">Record Medication Reconciliation</h3>
                <form @submit.prevent="submitReconciliation" class="space-y-4">
                    <div class="space-y-1 max-w-xs">
                        <label class="block text-xs font-semibold text-foreground">Stage</label>
                        <select v-model="reconciliationForm.stage" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                            <option value="Admission">Admission</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Discharge">Discharge</option>
                        </select>
                        <InputError :message="reconciliationForm.errors.stage" />
                    </div>

                    <div class="space-y-3 max-h-[45vh] overflow-y-auto pr-1">
                        <div
                            v-for="(med, index) in reconciliationForm.medications"
                            :key="index"
                            class="p-3 rounded-lg border border-border space-y-2"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-muted-foreground uppercase">Medication {{ index + 1 }}</span>
                                <button
                                    v-if="reconciliationForm.medications.length > 1"
                                    type="button"
                                    class="text-[11px] text-destructive font-semibold"
                                    @click="removeReconciliationMedicationRow(index)"
                                >
                                    Remove
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <AfyaInput
                                    v-model="med.medication_name"
                                    label="Medication Name"
                                    required
                                    :error="reconciliationForm.errors[`medications.${index}.medication_name`]"
                                    wrapper-class="col-span-2"
                                />
                                <AfyaInput v-model="med.dosage" label="Dosage" />
                                <AfyaInput v-model="med.frequency" label="Frequency" />
                                <AfyaInput v-model="med.route" label="Route" />
                                <div class="space-y-1">
                                    <label class="block text-xs font-semibold text-foreground">Action Taken</label>
                                    <select v-model="med.action_taken" class="w-full h-8.5 text-xs rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900/90 px-3">
                                        <option value="Continue">Continue</option>
                                        <option value="Discontinue">Discontinue</option>
                                        <option value="Substitute">Substitute</option>
                                        <option value="ModifyDose">Modify Dose</option>
                                        <option value="Hold">Hold</option>
                                    </select>
                                    <InputError :message="reconciliationForm.errors[`medications.${index}.action_taken`]" />
                                </div>
                                <AfyaInput
                                    v-if="med.action_taken === 'Substitute'"
                                    v-model="med.substitute_medication_name"
                                    label="Substitute Medication"
                                    wrapper-class="col-span-2"
                                />
                                <AfyaInput
                                    v-if="med.action_taken === 'ModifyDose'"
                                    v-model="med.new_dosage_instructions"
                                    label="New Dosage Instructions"
                                    wrapper-class="col-span-2"
                                />
                                <AfyaInput
                                    v-model="med.clinical_rationale"
                                    label="Clinical Rationale"
                                    wrapper-class="col-span-2"
                                />
                            </div>
                        </div>
                    </div>

                    <Button type="button" variant="outline" size="sm" @click="addReconciliationMedicationRow">
                        <Plus class="w-3.5 h-3.5 mr-1" /> Add Another Medication
                    </Button>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <Button type="button" variant="outline" size="sm" @click="showReconciliationModal = false">Cancel</Button>
                        <Button type="submit" size="sm" :disabled="reconciliationForm.processing">
                            {{ reconciliationForm.processing ? 'Saving…' : 'Save Reconciliation' }}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    </AfyaShell>
</template>
