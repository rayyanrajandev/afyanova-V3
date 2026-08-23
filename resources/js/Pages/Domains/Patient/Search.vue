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
import BreakGlassModal from '@/Components/Clinical/BreakGlassModal.vue';

const props = defineProps({
    patients: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

const search = ref(props.filters.search || '');
const selectedPatient = ref(props.patients?.[0] || null);
const showBreakGlassModal = ref(false);
const { openContext } = useWorkspacePreferences();

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
    <Head title="Master Patient Index — AfyaNova Workstation" />

    <AfyaShell active-module="patients">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">

            <!-- 1. LEFT SIDEBAR: Standard Hospital Inflow Navigation -->
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
                        Patient Inflow
                    </div>
                    <AfyaSidebarItem
                        label="Master Patient Index"
                        :icon="SearchIcon"
                        :badge="patients.length"
                        :active="true"
                        :collapsed="state === 'collapsed'"
                    />
                    <AfyaSidebarItem
                        label="Register New Patient"
                        :icon="UserPlus"
                        :collapsed="state === 'collapsed'"
                        :href="route('patients.create')"
                    />
                    <AfyaSidebarItem
                        label="Live Queue & Triage"
                        :icon="Clock"
                        :collapsed="state === 'collapsed'"
                        :href="route('queue.index')"
                    />

                    <div v-if="state !== 'collapsed'" class="pt-3 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border mt-2">
                        Clinical & Visits
                    </div>
                    <AfyaSidebarItem
                        label="Clinical Workstation"
                        :icon="Stethoscope"
                        :collapsed="state === 'collapsed'"
                        :href="route('workspace.clinical')"
                    />
                    <AfyaSidebarItem
                        label="Cashier & Billing POS"
                        :icon="Receipt"
                        :collapsed="state === 'collapsed'"
                        :href="route('billing.desk')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Master Patient Search Directory Table -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Patient Registry', href: route('patients.index') },
                        { label: 'Master Index', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-destructive border-destructive/30 hover:bg-destructive/10"
                                @click="showBreakGlassModal = true"
                            >
                                <ShieldAlert class="w-3.5 h-3.5 text-destructive" />
                                <span>Emergency Override</span>
                            </Button>
                            <Link :href="route('patients.create')">
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
                                placeholder="Search by MRN, Full Name, Phone (+255...), or National ID (NIDA)..."
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
                                            <Link
                                                :href="route('patients.show', patient.id)"
                                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline"
                                                @click.stop
                                            >
                                                <span>360</span>
                                                <ArrowRight class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" />
                                            </Link>
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
                                                <p class="text-xs">
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
                                <Gauge class="w-3 h-3 text-primary flex-shrink-0" />
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
                            <Link :href="route('billing.desk')">
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

                        <!-- Action Launchpad -->
                        <div class="space-y-1 pt-1 border-t border-border">
                            <Link :href="route('patients.show', selectedPatient.id)" class="block">
                                <Button variant="default" size="sm" class="w-full justify-center gap-1 text-xs h-7">
                                    <span>Open Patient 360</span>
                                    <ArrowRight class="w-3 h-3" />
                                </Button>
                            </Link>
                            <Link :href="route('encounters.workspace', encounters[0]?.id || 'demo')" class="block">
                                <Button variant="outline" size="sm" class="w-full justify-center gap-1 text-xs h-7">
                                    <Stethoscope class="w-3 h-3" />
                                    <span>Start Consultation</span>
                                </Button>
                            </Link>
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
    </AfyaShell>
</template>
