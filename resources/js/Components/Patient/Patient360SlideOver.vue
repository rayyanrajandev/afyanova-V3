<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    X,
    Search,
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
    Stethoscope,
    ChevronRight,
    ExternalLink,
    UserCheck,
} from '@lucide/vue';
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
import AfyaClinicalAlert from '@/Components/Afya/AfyaClinicalAlert.vue';

const props = defineProps({
    patient: { type: Object, required: true },
    patients: { type: Array, default: () => [] },
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'switch-patient']);

// ── Active tab ─────────────────────────────────────────────────────────────
const activeTab = ref('overview');

// Reset tab when patient changes
watch(() => props.patient?.id, () => { activeTab.value = 'overview'; });

// ── Patient switcher ────────────────────────────────────────────────────────
const switchSearch = ref('');
const showSwitcher = ref(false);

const switcherResults = computed(() => {
    const q = switchSearch.value.toLowerCase().trim();
    if (!q) return props.patients.slice(0, 8);
    return props.patients.filter(p =>
        `${p.first_name} ${p.last_name}`.toLowerCase().includes(q) ||
        (p.primary_mrn || '').toLowerCase().includes(q)
    ).slice(0, 8);
});

const doSwitch = (patient) => {
    emit('switch-patient', patient);
    showSwitcher.value = false;
    switchSearch.value = '';
};

// ── Derived clinical data ───────────────────────────────────────────────────
const encounters    = computed(() => props.patient?.encounters || []);
const notes         = computed(() => encounters.value.flatMap(e => (e.notes || []).map(n => ({ ...n, _encounter: e }))));
const vitals        = computed(() => encounters.value.flatMap(e => (e.vitals || []).map(v => ({ ...v, _encounter: e }))));
const diagnoses     = computed(() => encounters.value.flatMap(e => (e.diagnoses || []).map(d => ({ ...d, _encounter: e }))));
const prescriptions = computed(() => encounters.value.flatMap(e => (e.prescriptions || []).map(p => ({ ...p, _encounter: e }))));
const invoices      = computed(() => props.patient?.invoices || []);
const allergies     = computed(() => props.patient?.allergies || []);
const identifiers   = computed(() => props.patient?.identifiers || []);
const contacts      = computed(() => props.patient?.contacts || []);
const emergencyContacts = computed(() => props.patient?.emergencyContacts || []);
const latestVitals  = computed(() => vitals.value[0] || null);
const totalBalance  = computed(() =>
    invoices.value.reduce((s, i) => s + Math.max(0, Number(i.total_amount) - Number(i.paid_amount)), 0)
);

const page = usePage();
const canClinical = computed(() => page.props.auth?.permissions?.includes('clinical.encounter.view') || page.props.auth?.roles?.some(r => r.name === 'tenant-admin' || r.slug === 'tenant-admin'));
const canBilling = computed(() => page.props.auth?.permissions?.includes('billing.invoice.view') || page.props.auth?.roles?.some(r => r.name === 'tenant-admin' || r.slug === 'tenant-admin'));

// ── Timeline ────────────────────────────────────────────────────────────────
const timelineEvents = computed(() => {
    const evs = [];
    encounters.value.forEach(e => evs.push({
        id: `enc-${e.id}`, date: new Date(e.start_time || e.created_at),
        title: `${e.encounter_type || 'OPD'} Consultation`, subtitle: e.reason_for_visit || 'Clinical visit',
        icon: ClipboardList, color: 'text-primary bg-primary/10 border-primary/30',
    }));
    notes.value.forEach(n => evs.push({
        id: `note-${n.id}`, date: new Date(n.created_at),
        title: `${n.note_type || 'SOAP'} Note`, subtitle: n.content?.assessment || '—',
        icon: FileText, color: 'text-sky-700 bg-sky-50 border-sky-200',
    }));
    prescriptions.value.forEach(p => evs.push({
        id: `rx-${p.id}`, date: new Date(p.created_at),
        title: `Rx: ${p.medication?.generic_name || 'Drug'} ${p.dosage || ''}`,
        subtitle: `${p.frequency || ''} · ${p.duration_days ? p.duration_days + 'd' : ''}`,
        icon: Pill, color: 'text-emerald-700 bg-emerald-50 border-emerald-200',
    }));
    invoices.value.forEach(i => evs.push({
        id: `inv-${i.id}`, date: new Date(i.created_at),
        title: `Invoice ${i.invoice_number}`, subtitle: `TZS ${fmtN(i.total_amount)} · ${i.status}`,
        icon: Receipt, color: 'text-amber-700 bg-amber-50 border-amber-200',
    }));
    return evs.sort((a, b) => b.date - a.date);
});

// ── Tab definitions ─────────────────────────────────────────────────────────
const tabs = computed(() => [
    { id: 'overview',    label: 'Overview',           icon: LayoutDashboard },
    { id: 'timeline',   label: 'Timeline',            icon: Activity,       badge: timelineEvents.value.length },
    { id: 'encounters', label: 'Encounters',           icon: ClipboardList,  badge: encounters.value.length },
    { id: 'notes',      label: 'Clinical Notes',      icon: FileText,       badge: notes.value.length },
    { id: 'diagnoses',  label: 'Diagnoses',           icon: HeartPulse,     badge: diagnoses.value.length },
    { id: 'medications',label: 'Medications',         icon: Pill,           badge: prescriptions.value.length },
    { id: 'allergies',  label: 'Allergies',           icon: AlertTriangle,  badge: allergies.value.length, alert: allergies.value.length > 0 },
    { id: 'vitals',     label: 'Vitals',              icon: Gauge,          badge: vitals.value.length },
    { id: 'orders',     label: 'Orders',              icon: FlaskConical },
    { id: 'laboratory', label: 'Laboratory',          icon: Microscope },
    { id: 'imaging',    label: 'Imaging',             icon: ScanLine },
    { id: 'procedures', label: 'Procedures',          icon: Syringe },
    { id: 'documents',  label: 'Documents',           icon: Folder },
    { id: 'referrals',  label: 'Referrals',           icon: Share2 },
    { id: 'billing',    label: 'Billing & Insurance', icon: Receipt,        badge: invoices.value.length },
]);

// ── Helpers ─────────────────────────────────────────────────────────────────
const fmtN = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 0 });

const fmtDate = (d) => {
    if (!d) return '—';
    const parsed = new Date(d);
    if (isNaN(parsed.getTime())) return d;
    return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const initials = computed(() => {
    const f = props.patient?.first_name?.[0] || '';
    const l = props.patient?.last_name?.[0] || '';
    return `${f}${l}`.toUpperCase() || 'PT';
});

// ── Keyboard: Escape to close ───────────────────────────────────────────────
const onKeydown = (e) => {
    if (e.key === 'Escape') {
        if (showSwitcher.value) { showSwitcher.value = false; return; }
        emit('close');
    }
};
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <!-- Full-screen overlay -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-stretch justify-end"
                role="dialog"
                aria-modal="true"
                :aria-label="`Patient 360 — ${patient.first_name} ${patient.last_name}`"
            >
                <!-- Backdrop: click to close -->
                <div
                    class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"
                    @click="emit('close')"
                />

                <!-- Slide-over panel -->
                <Transition
                    enter-active-class="transition-transform duration-250 ease-out"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition-transform duration-200 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div
                        v-if="open"
                        class="relative z-10 flex flex-col h-full bg-background border-l border-border shadow-2xl"
                        style="width: min(88vw, 1140px)"
                    >

                        <!-- ═══════════════════════════════════════════════════
                             HEADER — Identity + Switcher + Close
                        ════════════════════════════════════════════════════ -->
                        <div class="flex-shrink-0 border-b border-border bg-card">
                            <!-- Top bar -->
                            <div class="h-12 px-4 flex items-center justify-between gap-3">
                                <!-- Patient Avatar + Identity -->
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-md bg-primary/10 text-primary font-bold text-sm flex items-center justify-center border border-primary/20 flex-shrink-0">
                                        {{ initials }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-foreground text-sm truncate">
                                                {{ patient.first_name }} {{ patient.middle_name || '' }} {{ patient.last_name }}
                                            </span>
                                            <span class="font-mono text-[11px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded border border-border/60 flex-shrink-0">
                                                {{ patient.primary_mrn }}
                                            </span>
                                            <AfyaStatusBadge :status="patient.status || 'Active'" dot />
                                        </div>
                                        <div class="text-[11px] text-muted-foreground flex items-center gap-2 mt-0.5">
                                            <span>{{ patient.gender || '—' }}</span>
                                            <span class="text-border">·</span>
                                            <span>DOB: {{ patient.formatted_dob || fmtDate(patient.dob) }}</span>
                                            <span class="text-border">·</span>
                                            <span class="font-semibold text-rose-700">{{ patient.blood_group || 'BG Unknown' }}</span>
                                            <template v-if="allergies.length > 0">
                                                <span class="text-border">·</span>
                                                <span class="font-bold text-rose-600 flex items-center gap-0.5">
                                                    <AlertTriangle class="w-3 h-3" />
                                                    {{ allergies.length }} Allerg{{ allergies.length === 1 ? 'y' : 'ies' }}
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right controls: Patient switcher + Close -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <!-- Patient Switcher -->
                                    <div class="relative">
                                        <button
                                            @click="showSwitcher = !showSwitcher"
                                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-border bg-muted/60 hover:bg-muted text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                                            title="Switch patient"
                                        >
                                            <Users class="w-3.5 h-3.5" />
                                            Switch Patient
                                            <ChevronRight class="w-3 h-3 rotate-90" />
                                        </button>

                                        <!-- Switcher Dropdown -->
                                        <div
                                            v-if="showSwitcher"
                                            class="absolute right-0 top-9 z-20 w-72 bg-card border border-border rounded-lg shadow-xl overflow-hidden"
                                        >
                                            <div class="p-2 border-b border-border">
                                                <div class="flex items-center gap-2 px-2 py-1 bg-muted/50 rounded border border-border/50">
                                                    <Search class="w-3 h-3 text-muted-foreground flex-shrink-0" />
                                                    <input
                                                        v-model="switchSearch"
                                                        placeholder="Search patients…"
                                                        class="flex-1 bg-transparent text-xs text-foreground placeholder:text-muted-foreground outline-none"
                                                        autofocus
                                                    />
                                                </div>
                                            </div>
                                            <div class="max-h-60 overflow-y-auto">
                                                <button
                                                    v-for="p in switcherResults"
                                                    :key="p.id"
                                                    @click="doSwitch(p)"
                                                    :class="[
                                                        'w-full flex items-center gap-2.5 px-3 py-2 text-left hover:bg-accent transition-colors',
                                                        p.id === patient.id ? 'bg-primary/5' : ''
                                                    ]"
                                                >
                                                    <div class="w-6 h-6 rounded bg-primary/10 text-primary text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                                                        {{ (p.first_name?.[0] || '') + (p.last_name?.[0] || '') }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="text-xs font-semibold text-foreground truncate">{{ p.first_name }} {{ p.last_name }}</div>
                                                        <div class="text-[10px] text-muted-foreground font-mono">{{ p.primary_mrn }}</div>
                                                    </div>
                                                    <UserCheck v-if="p.id === patient.id" class="w-3.5 h-3.5 text-primary ml-auto flex-shrink-0" />
                                                </button>
                                                <div v-if="switcherResults.length === 0" class="px-4 py-6 text-center text-xs text-muted-foreground">
                                                    No patients found
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Close -->
                                    <button
                                        @click="emit('close')"
                                        class="w-8 h-8 flex items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted border border-border transition-colors"
                                        title="Close Patient 360 (Esc)"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Allergy Alert Strip -->
                            <div v-if="allergies.length > 0" class="px-4 pb-2">
                                <AfyaClinicalAlert
                                    title="Clinical Safety — Documented Allergies"
                                    :message="allergies.map(a => `${a.allergen} (${a.severity || 'Severity Unknown'} — ${a.reaction || 'Reaction not specified'})`).join(' | ')"
                                    severity="critical"
                                />
                            </div>
                        </div>

                        <!-- ═══════════════════════════════════════════════════
                             BODY — Left Nav + Right Content
                        ════════════════════════════════════════════════════ -->
                        <div class="flex-1 flex overflow-hidden">

                            <!-- LEFT VERTICAL NAV -->
                            <nav class="flex-shrink-0 w-48 border-r border-border bg-muted/30 flex flex-col overflow-y-auto">
                                <div class="px-3 pt-3 pb-1 text-[9px] font-bold uppercase tracking-widest text-muted-foreground">
                                    Patient 360
                                </div>
                                <div class="flex-1 py-1">
                                    <button
                                        v-for="tab in tabs"
                                        :key="tab.id"
                                        @click="activeTab = tab.id"
                                        :class="[
                                            'w-full flex items-center gap-2.5 px-3 py-1.5 text-left text-xs transition-colors rounded-none',
                                            activeTab === tab.id
                                                ? 'bg-primary text-primary-foreground font-semibold'
                                                : 'text-muted-foreground hover:text-foreground hover:bg-accent/60',
                                            tab.alert && activeTab !== tab.id ? 'text-rose-700' : ''
                                        ]"
                                    >
                                        <component
                                            :is="tab.icon"
                                            class="w-3.5 h-3.5 flex-shrink-0"
                                            :class="tab.alert && activeTab !== tab.id ? 'text-rose-600' : ''"
                                        />
                                        <span class="flex-1 truncate">{{ tab.label }}</span>
                                        <span
                                            v-if="tab.badge !== undefined && tab.badge > 0"
                                            :class="[
                                                'text-[9px] font-mono rounded-full px-1.5 py-0 font-bold flex-shrink-0',
                                                activeTab === tab.id
                                                    ? 'bg-primary-foreground/20 text-primary-foreground'
                                                    : tab.alert ? 'bg-rose-100 text-rose-700' : 'bg-muted text-muted-foreground'
                                            ]"
                                        >
                                            {{ tab.badge }}
                                        </span>
                                    </button>
                                </div>

                                <!-- Nav Footer Actions -->
                                <div class="border-t border-border p-2 space-y-1">
                                    <Link v-if="canClinical" :href="route('encounters.workspace', encounters[0]?.id || 'demo')">
                                        <Button variant="default" size="sm" class="w-full justify-start gap-2 text-xs h-7">
                                            <Stethoscope class="w-3.5 h-3.5" />
                                            Consultation
                                        </Button>
                                    </Link>
                                    <Link v-if="canBilling" :href="route('billing.desk')">
                                        <Button variant="outline" size="sm" class="w-full justify-start gap-2 text-xs h-7">
                                            <Receipt class="w-3.5 h-3.5" />
                                            Point of Sale
                                        </Button>
                                    </Link>
                                    <Link :href="route('patients.show', patient.id)">
                                        <Button variant="ghost" size="sm" class="w-full justify-start gap-2 text-xs h-7 text-muted-foreground">
                                            <ExternalLink class="w-3.5 h-3.5" />
                                            Full 360 Page
                                        </Button>
                                    </Link>
                                </div>
                            </nav>

                            <!-- RIGHT CONTENT AREA -->
                            <div class="flex-1 overflow-y-auto p-5 space-y-4 bg-background">

                                <!-- ========== OVERVIEW ========== -->
                                <template v-if="activeTab === 'overview'">
                                    <!-- Metric strip -->
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <Card>
                                            <CardContent class="p-3">
                                                <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Blood Group</div>
                                                <div class="text-2xl font-mono font-black text-primary mt-0.5">{{ patient.blood_group || '—' }}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent class="p-3">
                                                <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Encounters</div>
                                                <div class="text-2xl font-mono font-black text-foreground mt-0.5">{{ encounters.length }}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent class="p-3">
                                                <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Active Rx</div>
                                                <div class="text-2xl font-mono font-black text-foreground mt-0.5">{{ prescriptions.length }}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent class="p-3">
                                                <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">POS Balance</div>
                                                <div class="text-2xl font-mono font-black mt-0.5" :class="totalBalance > 0 ? 'text-rose-700' : 'text-emerald-700'">
                                                    {{ fmtN(totalBalance) }}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <!-- Latest Vitals -->
                                    <Card v-if="latestVitals">
                                        <CardHeader class="pb-2 pt-3 px-4"><CardTitle class="text-sm">Latest Vitals</CardTitle></CardHeader>
                                        <CardContent class="px-4 pb-3">
                                            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                                                <div v-for="(val, label) in {
                                                    'Temp':       latestVitals.temperature_c ? latestVitals.temperature_c + '°C' : '—',
                                                    'Pulse':      latestVitals.heart_rate ? latestVitals.heart_rate + ' bpm' : '—',
                                                    'BP':         latestVitals.systolic_bp ? `${latestVitals.systolic_bp}/${latestVitals.diastolic_bp}` : '—',
                                                    'SpO₂':       latestVitals.oxygen_saturation ? latestVitals.oxygen_saturation + '%' : '—',
                                                    'Wt':         latestVitals.weight_kg ? latestVitals.weight_kg + ' kg' : '—',
                                                    'Ht':         latestVitals.height_cm ? latestVitals.height_cm + ' cm' : '—',
                                                }" :key="label" class="text-center p-2 rounded bg-muted/40 border border-border/60">
                                                    <div class="text-[9px] font-bold uppercase text-muted-foreground">{{ label }}</div>
                                                    <div class="font-mono font-bold text-sm text-foreground mt-0.5">{{ val }}</div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <!-- Recent Prescriptions -->
                                    <Card v-if="prescriptions.length > 0">
                                        <CardHeader class="pb-2 pt-3 px-4"><CardTitle class="text-sm">Current Medications</CardTitle></CardHeader>
                                        <CardContent class="px-0 pb-0">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Drug</TableHead>
                                                        <TableHead>Dose</TableHead>
                                                        <TableHead>Frequency</TableHead>
                                                        <TableHead>Duration</TableHead>
                                                        <TableHead>Status</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    <TableRow v-for="rx in prescriptions.slice(0, 5)" :key="rx.id">
                                                        <TableCell class="font-semibold text-foreground">{{ rx.medication?.generic_name || '—' }}</TableCell>
                                                        <TableCell class="font-mono text-xs">{{ rx.dosage || '—' }}</TableCell>
                                                        <TableCell class="text-xs">{{ rx.frequency || '—' }}</TableCell>
                                                        <TableCell class="text-xs">{{ rx.duration_days ? rx.duration_days + 'd' : '—' }}</TableCell>
                                                        <TableCell><AfyaStatusBadge :status="rx.status" dot /></TableCell>
                                                    </TableRow>
                                                </TableBody>
                                            </Table>
                                        </CardContent>
                                    </Card>
                                </template>

                                <!-- ========== TIMELINE ========== -->
                                <template v-else-if="activeTab === 'timeline'">
                                    <div v-if="timelineEvents.length === 0" class="py-16 text-center text-muted-foreground text-sm">No timeline events recorded.</div>
                                    <div class="relative pl-6 space-y-3 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-border">
                                        <div v-for="ev in timelineEvents" :key="ev.id" class="relative">
                                            <div class="absolute -left-6 top-2 w-5 h-5 rounded-full border-2 border-background flex items-center justify-center"
                                                :class="ev.color">
                                                <component :is="ev.icon" class="w-2.5 h-2.5" />
                                            </div>
                                            <div class="bg-card border border-border rounded-lg p-3">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-semibold text-foreground text-sm">{{ ev.title }}</span>
                                                    <span class="text-[10px] font-mono text-muted-foreground flex-shrink-0">{{ ev.date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                                                </div>
                                                <p v-if="ev.subtitle" class="text-xs text-muted-foreground mt-0.5">{{ ev.subtitle }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- ========== ENCOUNTERS ========== -->
                                <template v-else-if="activeTab === 'encounters'">
                                    <div v-if="encounters.length === 0" class="py-16 text-center text-muted-foreground text-sm">No encounters on record.</div>
                                    <div v-for="enc in encounters" :key="enc.id" class="bg-card border border-border rounded-lg p-4 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="font-semibold text-foreground">{{ enc.encounter_type || 'OPD' }} Visit</span>
                                            <AfyaStatusBadge :status="enc.status" dot />
                                        </div>
                                        <p class="text-xs text-muted-foreground">{{ enc.reason_for_visit || 'Routine Consultation' }}</p>
                                        <div class="flex items-center justify-between text-[11px] text-muted-foreground pt-2 border-t border-border/50">
                                            <span class="font-mono">{{ new Date(enc.start_time || enc.created_at).toLocaleString('en-GB') }}</span>
                                            <Link v-if="canClinical" :href="route('encounters.workspace', enc.id)">
                                                <Button variant="outline" size="sm" class="h-6 text-xs gap-1 px-2">
                                                    <ExternalLink class="w-3 h-3" /> Open Chart
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </template>

                                <!-- ========== CLINICAL NOTES ========== -->
                                <template v-else-if="activeTab === 'notes'">
                                    <div v-if="notes.length === 0" class="py-16 text-center text-muted-foreground text-sm">No clinical notes authored.</div>
                                    <div v-for="n in notes" :key="n.id" class="bg-card border border-border rounded-lg p-4 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="font-semibold text-foreground">{{ n.note_type || 'SOAP' }} Note</span>
                                            <span v-if="n.is_signed" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Signed</span>
                                            <span v-else class="text-[10px] px-2 py-0.5 rounded-full bg-muted text-muted-foreground border border-border">Draft</span>
                                        </div>
                                        <div class="text-xs space-y-1.5">
                                            <div v-if="n.content?.subjective"><span class="font-semibold text-foreground">S: </span><span class="text-muted-foreground">{{ n.content.subjective }}</span></div>
                                            <div v-if="n.content?.objective"><span class="font-semibold text-foreground">O: </span><span class="text-muted-foreground">{{ n.content.objective }}</span></div>
                                            <div v-if="n.content?.assessment"><span class="font-semibold text-foreground">A: </span><span class="text-muted-foreground">{{ n.content.assessment }}</span></div>
                                            <div v-if="n.content?.plan"><span class="font-semibold text-foreground">P: </span><span class="text-muted-foreground">{{ n.content.plan }}</span></div>
                                        </div>
                                    </div>
                                </template>

                                <!-- ========== DIAGNOSES ========== -->
                                <template v-else-if="activeTab === 'diagnoses'">
                                    <div v-if="diagnoses.length === 0" class="py-16 text-center text-muted-foreground text-sm">No active diagnoses on record.</div>
                                    <Table v-else>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Condition</TableHead>
                                                <TableHead>ICD-10</TableHead>
                                                <TableHead>Type</TableHead>
                                                <TableHead>Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="d in diagnoses" :key="d.id">
                                                <TableCell class="font-semibold text-foreground">{{ d.condition_name || d.name || '—' }}</TableCell>
                                                <TableCell class="font-mono text-xs text-muted-foreground">{{ d.icd10_code || '—' }}</TableCell>
                                                <TableCell class="text-xs text-muted-foreground">{{ d.diagnosis_type || 'Primary' }}</TableCell>
                                                <TableCell><AfyaStatusBadge :status="d.status || 'Active'" dot /></TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </template>

                                <!-- ========== MEDICATIONS ========== -->
                                <template v-else-if="activeTab === 'medications'">
                                    <div v-if="prescriptions.length === 0" class="py-16 text-center text-muted-foreground text-sm">No prescriptions on record.</div>
                                    <Table v-else>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Drug (Generic)</TableHead>
                                                <TableHead>Dosage</TableHead>
                                                <TableHead>Route</TableHead>
                                                <TableHead>Frequency</TableHead>
                                                <TableHead>Duration</TableHead>
                                                <TableHead>Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="rx in prescriptions" :key="rx.id">
                                                <TableCell class="font-semibold text-foreground">{{ rx.medication?.generic_name || '—' }}</TableCell>
                                                <TableCell class="font-mono text-xs">{{ rx.dosage || '—' }}</TableCell>
                                                <TableCell class="text-xs text-muted-foreground">{{ rx.route || 'Oral' }}</TableCell>
                                                <TableCell class="text-xs">{{ rx.frequency || '—' }}</TableCell>
                                                <TableCell class="text-xs font-mono">{{ rx.duration_days ? rx.duration_days + 'd' : '—' }}</TableCell>
                                                <TableCell><AfyaStatusBadge :status="rx.status" dot /></TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </template>

                                <!-- ========== ALLERGIES ========== -->
                                <template v-else-if="activeTab === 'allergies'">
                                    <div v-if="allergies.length === 0" class="py-16 text-center text-muted-foreground text-sm">No Known Drug Allergies (NKDA).</div>
                                    <div v-for="alg in allergies" :key="alg.id" class="bg-rose-50/70 border border-rose-200 rounded-lg p-4 flex items-start justify-between gap-4">
                                        <div class="space-y-1">
                                            <div class="font-bold text-rose-800 text-sm">{{ alg.allergen }}</div>
                                            <div class="text-xs text-rose-700">
                                                <span class="font-semibold">Type:</span> {{ alg.allergen_type || 'Drug' }}
                                                <span class="mx-2">·</span>
                                                <span class="font-semibold">Reaction:</span> {{ alg.reaction || 'Not specified' }}
                                            </div>
                                        </div>
                                        <span class="flex-shrink-0 text-[11px] font-bold px-2.5 py-1 rounded-full bg-rose-100 border border-rose-300 text-rose-800">
                                            {{ alg.severity || 'Unknown' }}
                                        </span>
                                    </div>
                                </template>

                                <!-- ========== VITALS ========== -->
                                <template v-else-if="activeTab === 'vitals'">
                                    <div v-if="vitals.length === 0" class="py-16 text-center text-muted-foreground text-sm">No vitals recorded yet.</div>
                                    <Table v-else>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Recorded</TableHead>
                                                <TableHead>Temp</TableHead>
                                                <TableHead>Pulse</TableHead>
                                                <TableHead>BP</TableHead>
                                                <TableHead>SpO₂</TableHead>
                                                <TableHead>Weight</TableHead>
                                                <TableHead>Height</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="v in vitals" :key="v.id">
                                                <TableCell class="font-mono text-xs">{{ new Date(v.recorded_at || v.created_at).toLocaleString('en-GB') }}</TableCell>
                                                <TableCell class="font-mono text-xs">{{ v.temperature_c ? v.temperature_c + '°C' : '—' }}</TableCell>
                                                <TableCell class="font-mono text-xs">{{ v.heart_rate ? v.heart_rate + ' bpm' : '—' }}</TableCell>
                                                <TableCell class="font-mono text-xs">{{ v.systolic_bp && v.diastolic_bp ? `${v.systolic_bp}/${v.diastolic_bp}` : '—' }}</TableCell>
                                                <TableCell class="font-mono text-xs">{{ v.oxygen_saturation ? v.oxygen_saturation + '%' : '—' }}</TableCell>
                                                <TableCell class="font-mono text-xs">{{ v.weight_kg ? v.weight_kg + ' kg' : '—' }}</TableCell>
                                                <TableCell class="font-mono text-xs">{{ v.height_cm ? v.height_cm + ' cm' : '—' }}</TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </template>

                                <!-- ========== BILLING & INSURANCE ========== -->
                                <template v-else-if="activeTab === 'billing'">
                                    <!-- Balance Summary -->
                                    <div class="grid grid-cols-3 gap-3">
                                        <Card>
                                            <CardContent class="p-3">
                                                <div class="text-[10px] font-bold uppercase text-muted-foreground">Total Invoiced</div>
                                                <div class="font-mono font-bold text-foreground text-lg mt-0.5">TZS {{ fmtN(invoices.reduce((s,i) => s + Number(i.total_amount||0), 0)) }}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent class="p-3">
                                                <div class="text-[10px] font-bold uppercase text-muted-foreground">Total Paid</div>
                                                <div class="font-mono font-bold text-emerald-700 text-lg mt-0.5">TZS {{ fmtN(invoices.reduce((s,i) => s + Number(i.paid_amount||0), 0)) }}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent class="p-3">
                                                <div class="text-[10px] font-bold uppercase text-muted-foreground">Outstanding</div>
                                                <div class="font-mono font-bold text-lg mt-0.5" :class="totalBalance > 0 ? 'text-rose-700' : 'text-emerald-700'">TZS {{ fmtN(totalBalance) }}</div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                    <div v-if="invoices.length === 0" class="py-10 text-center text-muted-foreground text-sm">No invoices on record.</div>
                                    <Table v-else>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead class="min-w-[175px] whitespace-nowrap">Invoice #</TableHead>
                                                <TableHead class="min-w-[100px] whitespace-nowrap">Date</TableHead>
                                                <TableHead class="whitespace-nowrap">Total (TZS)</TableHead>
                                                <TableHead class="whitespace-nowrap">Paid (TZS)</TableHead>
                                                <TableHead class="whitespace-nowrap">Balance</TableHead>
                                                <TableHead class="whitespace-nowrap">Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="inv in invoices" :key="inv.id">
                                                <TableCell class="font-mono font-bold text-foreground whitespace-nowrap">{{ inv.invoice_number }}</TableCell>
                                                <TableCell class="text-xs text-muted-foreground">{{ new Date(inv.created_at).toLocaleDateString('en-GB') }}</TableCell>
                                                <TableCell class="font-mono text-xs">{{ fmtN(inv.total_amount) }}</TableCell>
                                                <TableCell class="font-mono text-xs text-emerald-700">{{ fmtN(inv.paid_amount) }}</TableCell>
                                                <TableCell class="font-mono text-xs" :class="(inv.total_amount - inv.paid_amount) > 0 ? 'text-rose-700 font-bold' : 'text-muted-foreground'">
                                                    {{ fmtN(Math.max(0, inv.total_amount - inv.paid_amount)) }}
                                                </TableCell>
                                                <TableCell><AfyaStatusBadge :status="inv.status" dot /></TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </template>

                                <!-- ========== STUB TABS: Orders / Lab / Imaging / Procedures / Docs / Referrals ========== -->
                                <template v-else>
                                    <div class="py-20 text-center space-y-2 text-muted-foreground">
                                        <component :is="tabs.find(t => t.id === activeTab)?.icon" class="w-10 h-10 mx-auto opacity-20" />
                                        <p class="font-semibold text-foreground text-sm">{{ tabs.find(t => t.id === activeTab)?.label }}</p>
                                        <p class="text-xs">No data available yet for this section.</p>
                                    </div>
                                </template>

                            </div><!-- end right content -->
                        </div><!-- end body -->

                    </div><!-- end panel -->
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
