<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
    HelpCircle
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

const props = defineProps({
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
const invoices = computed(() => props.patient.invoices || []);
const appointments = computed(() => props.patient.appointments || []);
const allergies = computed(() => props.patient.allergies || []);
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
    { id: 'diagnoses', label: 'Diagnoses / Problems', icon: HeartPulse, badge: diagnoses.value.length },
    { id: 'medications', label: 'Medications', icon: Pill, badge: prescriptions.value.length },
    { id: 'allergies', label: 'Allergies', icon: AlertTriangle, badge: allergies.value.length, alert: allergies.value.length > 0 },
    { id: 'vitals', label: 'Vitals', icon: Gauge, badge: vitals.value.length },
]);

const diagnosticTabs = computed(() => [
    { id: 'orders', label: 'Orders', icon: FlaskConical },
    { id: 'laboratory', label: 'Laboratory', icon: Microscope },
    { id: 'imaging', label: 'Imaging', icon: ScanLine },
    { id: 'procedures', label: 'Procedures', icon: Syringe },
]);

const adminTabs = computed(() => [
    { id: 'documents', label: 'Documents', icon: Folder },
    { id: 'referrals', label: 'Referrals', icon: Share2 },
    { id: 'billing', label: 'Billing & Insurance', icon: Receipt, badge: invoices.value.length },
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
                            v-if="allergies.length > 0"
                            title="Patient Clinical Safety Warning"
                            :message="`Documented Allergies: ${allergies.map(a => `${a.allergen} (${a.severity || 'Active'} — ${a.reaction || 'Reaction unspecified'})`).join(', ')}`"
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
                                <Card>
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

                        <!-- ==================== TAB 5: DIAGNOSES / PROBLEMS ==================== -->
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

                        <!-- ==================== TAB 7: ALLERGIES ==================== -->
                        <div v-else-if="activeTab === 'allergies'" class="space-y-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Allergen</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Severity</TableHead>
                                        <TableHead>Reaction</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="alg in allergies" :key="alg.id">
                                        <TableCell class="font-bold text-rose-700">{{ alg.allergen }}</TableCell>
                                        <TableCell class="text-xs">{{ alg.allergen_type || 'Drug' }}</TableCell>
                                        <TableCell>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                                {{ alg.severity || 'Active' }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ alg.reaction || 'Anaphylaxis / Rash' }}</TableCell>
                                        <TableCell><AfyaStatusBadge :status="alg.status || 'Active'" dot /></TableCell>
                                    </TableRow>

                                    <TableRow v-if="allergies.length === 0">
                                        <TableCell colspan="5" class="text-center py-8 text-muted-foreground">
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
                        <div v-else-if="activeTab === 'imaging'" class="py-16 text-center text-muted-foreground text-xs space-y-2">
                            <ScanLine class="w-8 h-8 mx-auto text-muted-foreground opacity-40" />
                            <p class="font-semibold text-foreground text-xs">Radiology & PACS Imaging</p>
                            <p class="text-[11px]">X-Ray, Ultrasound, CT, and MRI image studies and radiologist impressions.</p>
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
                        <div v-else-if="activeTab === 'referrals'" class="py-16 text-center text-muted-foreground text-xs space-y-2">
                            <Share2 class="w-8 h-8 mx-auto text-muted-foreground opacity-40" />
                            <p class="font-semibold text-foreground text-xs">Inter-Facility Referrals</p>
                            <p class="text-[11px]">Inbound referral letters and outbound hospital transfers.</p>
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
    </AfyaShell>
</template>
