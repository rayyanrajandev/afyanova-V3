<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import {
    Users,
    UserPlus,
    Search as SearchIcon,
    Clock,
    Stethoscope,
    Receipt,
    Plus,
    ArrowRight,
    Calendar,
    Phone,
    CreditCard,
    ExternalLink,
    AlertTriangle,
    Gauge,
    Pill,
    HeartPulse,
    ShieldAlert,
    ShieldCheck,
    Ticket,
    CheckCircle2,
    Sparkles,
    ConciergeBell,
    GitMerge,
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

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
import AfyaFilterBar from '@/Components/Afya/AfyaFilterBar.vue';
import AfyaClinicalAlert from '@/Components/Afya/AfyaClinicalAlert.vue';
import AfyaCheckInModal from '@/Components/Afya/AfyaCheckInModal.vue';
import BreakGlassModal from '@/Components/Clinical/BreakGlassModal.vue';
import PatientMergeModal from '@/Components/Patient/PatientMergeModal.vue';

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    patients: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({ lobby_waiting: 0, today_appointments: 0, total_patients: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
    selectedId: {
        type: String,
        default: null,
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
});

const search = ref(props.filters.search || '');
const selectedPatient = ref(props.patients?.[0] || null);
const showBreakGlassModal = ref(false);
const showCheckInModal = ref(false);
const showMergeModal = ref(false);

const { openContext } = useWorkspacePreferences();

const openCheckInModal = (patient) => {
    selectedPatient.value = patient;
    showCheckInModal.value = true;
};

onMounted(() => {
    if (props.selectedId) {
        const found = props.patients.find(p => p.id === props.selectedId);
        if (found) {
            selectPatient(found);
            return;
        }
    }
    if (props.patients?.length > 0) {
        selectPatient(props.patients[0]);
    }
});

// Derived records for the selected patient inspector
const encounters = computed(() => selectedPatient.value?.encounters || []);
const notes = computed(() => encounters.value.flatMap(e => (e.notes || []).map(n => ({ ...n, encounter: e }))));
const vitals = computed(() => encounters.value.flatMap(e => (e.vitals || []).map(v => ({ ...v, encounter: e }))));
const diagnoses = computed(() => encounters.value.flatMap(e => (e.diagnoses || []).map(d => ({ ...d, encounter: e }))));
const prescriptions = computed(() => encounters.value.flatMap(e => (e.prescriptions || []).map(p => ({ ...p, encounter: e }))));
const invoices = computed(() => selectedPatient.value?.invoices || []);
const allergies = computed(() => selectedPatient.value?.allergies || []);
const identifiers = computed(() => selectedPatient.value?.identifiers || []);
const contacts = computed(() => selectedPatient.value?.contacts || []);
const emergencyContacts = computed(() => selectedPatient.value?.emergencyContacts || []);

const latestVitals = computed(() => vitals.value[0] || null);

const totalOutstanding = computed(() => {
    return invoices.value.reduce((sum, inv) => sum + Math.max(0, Number(inv.total_amount) - Number(inv.paid_amount)), 0);
});

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Live debounced search
watch(search, debounce((value) => {
    router.get(route('patients.index'), { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300));

// Select patient row and ensure context panel is open
const selectPatient = (patient) => {
    selectedPatient.value = patient;
    openContext();
};

// Keyboard Traversal (Gap 2.3)
const handleTableKeydown = (e) => {
    if (['TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
    if (document.activeElement?.tagName === 'INPUT') {
        if (e.key === 'ArrowDown') {
            document.activeElement.blur();
        } else {
            return;
        }
    }

    if (!props.patients || props.patients.length === 0) return;
    const currentIndex = props.patients.findIndex(p => p.id === selectedPatient.value?.id);

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        const nextIndex = Math.min(currentIndex + 1, props.patients.length - 1);
        selectPatient(props.patients[nextIndex]);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prevIndex = Math.max(currentIndex - 1, 0);
        selectPatient(props.patients[prevIndex]);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedPatient.value) {
            router.visit(route('patients.show', selectedPatient.value.id));
        }
    }
};

onMounted(() => window.addEventListener('keydown', handleTableKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleTableKeydown));
</script>

<template>
    <Head title="Patient Registry — AfyaNova Workstation" />

    <AfyaShell active-module="patients">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">

            <!-- 1. LEFT SIDEBAR: Standard Hospital Registry Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Patient Registry"
                    :icon="Users"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Patient Directory
                    </div>

                    <AfyaSidebarItem
                        label="Front Desk"
                        :icon="ConciergeBell"
                        :collapsed="state === 'collapsed'"
                        :href="route('dashboard')"
                    />
                    <AfyaSidebarItem
                        label="Patient Registry"
                        :icon="Users"
                        :badge="metrics?.total_patients || patients.length"
                        :active="true"
                        :collapsed="state === 'collapsed'"
                        :href="route('patients.index')"
                    />
                    <AfyaSidebarItem
                        v-if="can.registerPatient"
                        label="Register New Patient"
                        :icon="UserPlus"
                        :collapsed="state === 'collapsed'"
                        :href="route('patients.create')"
                    />
                    <AfyaSidebarItem
                        v-if="can.queue"
                        label="Live Queue & Triage"
                        :icon="Clock"
                        :badge="metrics?.lobby_waiting || null"
                        :collapsed="state === 'collapsed'"
                        :href="route('queue.index')"
                    />
                    <AfyaSidebarItem
                        v-if="can.appointments"
                        label="Appointments Calendar"
                        :icon="Calendar"
                        :badge="metrics?.today_appointments || null"
                        :collapsed="state === 'collapsed'"
                        :href="route('appointments.index')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Patient Registry Directory Table -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Front Desk', href: route('dashboard') },
                        { label: 'Patient Registry', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="can.breakGlass"
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-destructive border-destructive/30 hover:bg-destructive/10"
                                @click="showBreakGlassModal = true"
                            >
                                <ShieldAlert class="w-3.5 h-3.5 text-destructive" />
                                <span>Emergency Override</span>
                            </Button>
                            <Link v-if="can.registerPatient" :href="route('patients.create')">
                                <Button variant="default" size="sm" class="gap-1.5">
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Register Patient</span>
                                </Button>
                            </Link>
                        </div>
                    </template>

                    <div class="w-full space-y-3.5">
                        <!-- Compact Search Filter Bar -->
                        <div class="w-full">
                            <AfyaFilterBar
                                v-model="search"
                                placeholder="Filter or search records (Name, MRN, Phone, NIN)..."
                                autofocus
                            />
                        </div>

                        <!-- High Density Patient Table -->
                        <div class="w-full">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-36">Primary MRN</TableHead>
                                        <TableHead>Patient Full Name</TableHead>
                                        <TableHead>Demographics</TableHead>
                                        <TableHead class="w-28">Blood Group</TableHead>
                                        <TableHead class="w-28">Status</TableHead>
                                        <TableHead class="w-24 text-right">Chart</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="patient in patients"
                                        :key="patient.id"
                                        :selected="selectedPatient?.id === patient.id"
                                        class="cursor-pointer group"
                                        @click="selectPatient(patient)"
                                    >
                                        <TableCell class="font-mono font-bold text-foreground">
                                            {{ patient.primary_mrn }}
                                        </TableCell>
                                        <TableCell>
                                            <div class="font-bold text-foreground">
                                                {{ patient.first_name }} {{ patient.middle_name || '' }} {{ patient.last_name }}
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground text-xs">
                                            <span>{{ patient.gender || 'Unknown' }}</span>
                                            <span class="mx-1 text-border">·</span>
                                            <span>{{ patient.age ? `${patient.age}y` : (patient.formatted_dob || formatDate(patient.dob)) }}</span>
                                        </TableCell>
                                        <TableCell class="font-mono text-xs font-semibold">
                                            <span v-if="patient.blood_group" class="px-1.5 py-0.5 rounded bg-muted text-foreground border border-border text-[11px]">
                                                {{ patient.blood_group }}
                                            </span>
                                            <span v-else class="text-muted-foreground">—</span>
                                        </TableCell>
                                        <TableCell>
                                            <AfyaStatusBadge :status="patient.status || 'Active'" dot />
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <div class="flex items-center justify-end gap-1.5" @click.stop>
                                                <Button
                                                    v-if="can.queue"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10.5px] font-semibold gap-1 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                                                    @click="openCheckInModal(patient)"
                                                >
                                                    <Ticket class="w-3 h-3" />
                                                    <span>Check-In</span>
                                                </Button>
                                                <Link
                                                    :href="route('patients.show', patient.id)"
                                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline px-1.5 py-0.5 rounded hover:bg-muted/40"
                                                >
                                                    <span>360</span>
                                                    <ArrowRight class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" />
                                                </Link>
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="patients.length === 0">
                                        <TableCell colspan="6" class="text-center py-16 text-muted-foreground">
                                            <div v-if="search" class="space-y-1.5">
                                                <p class="font-semibold text-foreground">No patients found matching "{{ search }}".</p>
                                                <p class="text-xs">Try searching with a partial MRN, phone number, or national ID.</p>
                                            </div>
                                            <div v-else class="space-y-1.5">
                                                <p class="font-semibold text-foreground">No patient records found.</p>
                                                <p v-if="can.registerPatient" class="text-xs">
                                                    <Link :href="route('patients.create')" class="text-primary underline underline-offset-2">Register a new patient</Link>
                                                    to begin.
                                                </p>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: Standard AfyaNova V3 Context Inspector -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Patient Snapshot"
                    :icon="Users"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedPatient" class="space-y-2 text-xs">
                        <!-- Patient Identity Banner -->
                        <AfyaPatientIdentity :patient="selectedPatient">
                            <AfyaStatusBadge :status="selectedPatient.status || 'Active'" dot />
                        </AfyaPatientIdentity>

                        <!-- Allergy Alert Warning -->
                        <AfyaClinicalAlert
                            v-if="allergies.length > 0"
                            title="Safety Warning"
                            :message="`Allergies: ${allergies.map(a => `${a.allergen} (${a.severity || 'Active'})`).join(', ')}`"
                            severity="critical"
                        />

                        <!-- Telemetry Summary -->
                        <div v-if="latestVitals" class="p-2 rounded bg-card border border-border/80 space-y-1">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                                <Gauge class="w-3 h-3 text-primary shrink-0" />
                                <span>Vital Telemetry</span>
                            </div>
                            <div class="grid grid-cols-3 gap-1 text-center text-xs">
                                <div class="p-1 rounded bg-muted/40 border border-border/50">
                                    <div class="text-[8px] text-muted-foreground uppercase font-bold">Temp</div>
                                    <div class="font-mono font-bold text-[10px]">{{ latestVitals.temperature_c ? `${latestVitals.temperature_c}°C` : '—' }}</div>
                                </div>
                                <div class="p-1 rounded bg-muted/40 border border-border/50">
                                    <div class="text-[8px] text-muted-foreground uppercase font-bold">Pulse</div>
                                    <div class="font-mono font-bold text-[10px]">{{ latestVitals.heart_rate ? `${latestVitals.heart_rate}` : '—' }}</div>
                                </div>
                                <div class="p-1 rounded bg-muted/40 border border-border/50">
                                    <div class="text-[8px] text-muted-foreground uppercase font-bold">BP</div>
                                    <div class="font-mono font-bold text-[10px]">{{ latestVitals.systolic_bp && latestVitals.diastolic_bp ? `${latestVitals.systolic_bp}/${latestVitals.diastolic_bp}` : '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Financial & POS Balance Snapshot -->
                        <div class="p-2 rounded bg-card border border-border/80 flex justify-between items-center">
                            <div>
                                <div class="text-[9px] font-bold uppercase text-muted-foreground">POS Balance</div>
                                <div class="text-xs font-mono font-bold" :class="totalOutstanding > 0 ? 'text-rose-700' : 'text-emerald-700'">
                                    TZS {{ formatCurrency(totalOutstanding) }}
                                </div>
                            </div>
                            <Link v-if="can.billing" :href="route('billing.desk')">
                                <Button variant="outline" size="sm" class="h-5 px-1.5 text-[9px] gap-1">
                                    <Receipt class="w-2.5 h-2.5" />
                                    <span>Cashier</span>
                                </Button>
                            </Link>
                        </div>

                        <!-- Identifiers (NIDA, NHIF) -->
                        <div class="p-2 rounded bg-card border border-border/80 space-y-1">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">National Identifiers</div>
                            <div class="space-y-1 text-[11px]">
                                <div v-for="id in identifiers" :key="id.id" class="flex justify-between items-center">
                                    <span class="text-muted-foreground">{{ id.type }}:</span>
                                    <span class="font-mono font-bold text-foreground truncate max-w-[130px]">{{ id.identifier_value }}</span>
                                </div>
                                <div v-if="identifiers.length === 0" class="text-muted-foreground text-[10px] italic">No national ID on record.</div>
                            </div>
                        </div>

                        <!-- Reception Fast-Track Launchpad (What's Next?) -->
                        <div class="space-y-2 pt-2 border-t border-border">
                            <div class="text-[10px] font-bold text-foreground uppercase tracking-wider flex items-center justify-between">
                                <span class="flex items-center gap-1 text-primary">
                                    <Sparkles class="w-3 h-3 text-amber-500" />
                                    Reception Action Launchpad
                                </span>
                                <span class="text-[9px] font-mono text-muted-foreground">Next Step</span>
                            </div>

                            <!-- 1. Check-In / Route to Queue -->
                            <Button
                                v-if="can.queue"
                                variant="default"
                                size="sm"
                                class="w-full justify-between gap-1 text-xs h-8 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                                @click="openCheckInModal(selectedPatient)"
                            >
                                <div class="flex items-center gap-1.5">
                                    <Ticket class="w-3.5 h-3.5" />
                                    <span class="font-bold">Check-In & Route to Queue</span>
                                </div>
                                <span class="text-[9.5px] font-normal opacity-90">1-Click</span>
                            </Button>

                            <!-- 2. Attach Insurance Policy -->
                            <Link :href="route('patients.show', selectedPatient.id)" class="block">
                                <Button variant="outline" size="sm" class="w-full justify-between gap-1 text-xs h-7.5 border-border/80 hover:bg-muted/40">
                                    <div class="flex items-center gap-1.5">
                                        <ShieldCheck class="w-3.5 h-3.5 text-sky-600" />
                                        <span>Add Insurance / NHIF Policy</span>
                                    </div>
                                    <ArrowRight class="w-3 h-3 text-muted-foreground" />
                                </Button>
                            </Link>

                            <!-- 3. Open Comprehensive Patient 360 -->
                            <Link :href="route('patients.show', selectedPatient.id)" class="block">
                                <Button variant="ghost" size="sm" class="w-full justify-between gap-1 text-xs h-7.5 border border-dashed border-border/70 hover:bg-muted/30">
                                    <div class="flex items-center gap-1.5">
                                        <Users class="w-3.5 h-3.5 text-primary" />
                                        <span class="font-semibold text-foreground">Open Full Patient 360 Record</span>
                                    </div>
                                    <ArrowRight class="w-3 h-3 text-muted-foreground" />
                                </Button>
                            </Link>

                            <!-- 4. Resume Consultation if active encounter exists -->
                            <Link v-if="can.clinical && encounters[0]" :href="route('encounters.workspace', encounters[0].id)" class="block">
                                <Button variant="outline" size="sm" class="w-full justify-between gap-1 text-xs h-7.5 text-primary border-primary/30">
                                    <div class="flex items-center gap-1.5">
                                        <Stethoscope class="w-3.5 h-3.5" />
                                        <span>Resume Open Consultation</span>
                                    </div>
                                    <ArrowRight class="w-3 h-3" />
                                </Button>
                            </Link>

                            <!-- 5. Merge Duplicate Record -->
                            <Button
                                v-if="can.mergePatient"
                                variant="outline"
                                size="sm"
                                class="w-full justify-between gap-1 text-xs h-7.5 text-amber-600 dark:text-amber-400 border-amber-300 dark:border-amber-700/60 hover:bg-amber-50 dark:hover:bg-amber-950/30"
                                @click="showMergeModal = true"
                            >
                                <div class="flex items-center gap-1.5">
                                    <GitMerge class="w-3.5 h-3.5" />
                                    <span>Merge Duplicate Record</span>
                                </div>
                                <ArrowRight class="w-3 h-3 text-muted-foreground" />
                            </Button>
                        </div>
                    </div>

                    <!-- Clean Empty State (When no patient row is selected) -->
                    <div v-else class="text-center py-8 px-2 space-y-2 text-xs">
                        <div class="w-9 h-9 rounded-full bg-muted flex items-center justify-center mx-auto text-muted-foreground">
                            <SearchIcon class="w-4 h-4" />
                        </div>
                        <div class="space-y-0.5">
                            <div class="font-bold text-foreground text-xs">No Patient Selected</div>
                            <p class="text-[11px] text-muted-foreground leading-tight">
                                Click any patient record in the table to inspect demographics, triage vitals, and billing balances.
                            </p>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>

        </AfyaWorkspace>

        <BreakGlassModal
            :show="showBreakGlassModal"
            :patient-id="selectedPatient?.id || ''"
            :patient-name="selectedPatient ? `${selectedPatient.first_name} ${selectedPatient.last_name}` : ''"
            @close="showBreakGlassModal = false"
        />

        <AfyaCheckInModal
            :show="showCheckInModal"
            :patient="selectedPatient"
            :lab-tests="labTests"
            :procedure-catalogs="procedureCatalogs"
            :active-tickets="activeTickets"
            @close="showCheckInModal = false"
        />

        <PatientMergeModal
            :show="showMergeModal"
            :primary-patient="selectedPatient"
            :available-patients="patients?.data || patients || []"
            @close="showMergeModal = false"
        />
    </AfyaShell>
</template>
