<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import AfyaPatientIdentity from '@/Components/Afya/AfyaPatientIdentity.vue';
import AfyaCheckInModal from '@/Components/Afya/AfyaCheckInModal.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import {
    Users,
    Calendar,
    CalendarCheck,
    Clock,
    UserPlus,
    Ticket,
    CheckCircle2,
    ArrowRight,
    Stethoscope,
    FlaskConical,
    Pill,
    Receipt,
    Syringe,
    Sparkles,
    ShieldCheck,
    LayoutDashboard,
    ConciergeBell,
    Layers,
} from '@lucide/vue';

const props = defineProps({
    todayAppointments: {
        type: Array,
        default: () => [],
    },
    recentPatients: {
        type: Array,
        default: () => [],
    },
    recentEncounters: {
        type: Array,
        default: () => [],
    },
    queueTickets: {
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
    activeQueueTickets: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({
            today_registered: 0,
            total_patients: 0,
            today_appointments: 0,
            checked_in_appointments: 0,
            lobby_waiting: 0,
            today_revenue: 0,
            unpaid_invoices: 0,
            point_counts: {},
        }),
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const { openContext } = useWorkspacePreferences();

// Selected record for Right Context Panel
const selectedRecord = ref(null);

const selectRecord = (type, data) => {
    if (selectedRecord.value?.type === type && selectedRecord.value?.data?.id === data.id) {
        selectedRecord.value = null;
    } else {
        selectedRecord.value = { type, data };
        openContext();
    }
};

// Telemetry Cards Navigation (Consistent Queue Board for All Roles)
const getDeskRoute = (point) => {
    if (props.can.queue) {
        return route('queue.index', { point });
    }
    return null;
};

// Fast-Track Direct Check-in Modal
const showCheckInModal = ref(false);
const checkInPatient = ref(null);

const openQuickCheckInModal = (patient = null) => {
    checkInPatient.value = patient || null;
    showCheckInModal.value = true;
};

const checkInAppointment = (appt) => {
    router.post(route('appointments.checkin', appt.id), {}, {
        preserveScroll: true,
    });
};

// Formatting utilities
const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
};

const formatTime = (timeStr) => {
    if (!timeStr) return '—';
    try {
        const d = new Date(timeStr);
        if (isNaN(d.getTime())) return timeStr.substring(0, 5);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch {
        return timeStr;
    }
};

const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '0.00';
    return Number(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};
</script>

<template>
    <Head title="Front Desk Command Center | AfyaNova V3" />

    <AfyaShell active-module="dashboard">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            <!-- 1. LEFT SIDEBAR: Front Desk & Navigation Hub -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Reception Hub"
                    :icon="ConciergeBell"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Reception Operations
                    </div>

                    <AfyaSidebarItem
                        label="Command Center"
                        :icon="LayoutDashboard"
                        :active="true"
                        :collapsed="state === 'collapsed'"
                        :href="route('dashboard')"
                    />
                    <AfyaSidebarItem
                        v-if="can.create_patient"
                        label="Register Patient"
                        :icon="UserPlus"
                        :collapsed="state === 'collapsed'"
                        :href="route('patients.create')"
                    />
                    <AfyaSidebarItem
                        v-if="can.checkin"
                        label="Quick Walk-In Check-In"
                        :icon="Ticket"
                        :collapsed="state === 'collapsed'"
                        @click="openQuickCheckInModal()"
                    />
                    <AfyaSidebarItem
                        v-if="can.patients"
                        label="Patient Registry"
                        :icon="Users"
                        :badge="metrics.total_patients || null"
                        :collapsed="state === 'collapsed'"
                        :href="route('patients.index')"
                    />
                    <AfyaSidebarItem
                        v-if="can.queue"
                        label="Live Queue & Triage"
                        :icon="Clock"
                        :badge="metrics.lobby_waiting || null"
                        :collapsed="state === 'collapsed'"
                        :href="route('queue.index')"
                    />
                    <AfyaSidebarItem
                        v-if="can.appointments"
                        label="Appointments Calendar"
                        :icon="Calendar"
                        :badge="metrics.today_appointments || null"
                        :collapsed="state === 'collapsed'"
                        :href="route('appointments.index')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Meta-Style Full-Width Operational Command Center -->
            <template #default>
                <AfyaWorkspaceMain
                    title="Front Desk & Reception Command Center"
                    subtitle="Today's patient inflow, appointment schedules, and live service queues"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button 
                                v-if="can.checkin"
                                variant="default" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                                @click="openQuickCheckInModal()"
                            >
                                <Ticket class="w-3.5 h-3.5" />
                                <span>Fast-Track Check-In</span>
                            </Button>

                            <Link v-if="can.create_patient" :href="route('patients.create')">
                                <Button variant="outline" size="sm" class="h-7 text-xs font-semibold gap-1.5 border-border/80">
                                    <UserPlus class="w-3.5 h-3.5 text-primary" />
                                    <span>Register Patient</span>
                                </Button>
                            </Link>

                            <Link v-if="can.patients" :href="route('patients.index')">
                                <Button variant="ghost" size="sm" class="h-7 text-xs font-semibold gap-1.5">
                                    <Users class="w-3.5 h-3.5" />
                                    <span>Patient Registry</span>
                                </Button>
                            </Link>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        
                        <!-- ========================================================================= -->
                        <!-- TIER 1: TOP FRONT-DESK KPI STRIP (4 High-Impact Telemetry Cards)          -->
                        <!-- ========================================================================= -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 w-full">
                            
                            <!-- KPI 1: Patient Inflow Today -->
                            <div class="p-3 rounded-lg bg-card border border-border/80 space-y-1 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Patient Inflow</span>
                                    <Users class="w-3.5 h-3.5 text-primary flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-foreground">
                                    {{ metrics.today_inflow || metrics.today_registered || 0 }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">
                                    <span class="text-primary font-semibold">{{ metrics.today_registered || 0 }} new today</span> · {{ metrics.total_patients || 0 }} in registry
                                </div>
                            </div>

                            <!-- KPI 2: Today's Appointments -->
                            <div class="p-3 rounded-lg bg-card border border-border/80 space-y-1 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Today's Appointments</span>
                                    <CalendarCheck class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-sky-700 dark:text-sky-400">
                                    {{ metrics.today_total_appointments ?? metrics.today_appointments ?? 0 }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">
                                    <span class="font-bold text-emerald-600">{{ metrics.checked_in_appointments || 0 }} checked in</span>
                                    <span v-if="metrics.today_appointments > 0" class="text-amber-600"> · {{ metrics.today_appointments }} pending</span>
                                    <span v-else class="text-muted-foreground"> · 0 pending</span>
                                </div>
                            </div>

                            <!-- KPI 3: Lobby Waiting Queue -->
                            <div class="p-3 rounded-lg bg-card border border-border/80 space-y-1 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Lobby Waiting Queue</span>
                                    <Clock class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-amber-700 dark:text-amber-400">
                                    {{ metrics.lobby_waiting || 0 }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate font-medium">
                                    <template v-if="metrics.lobby_waiting">
                                        <span v-if="metrics.point_counts?.Triage" class="text-blue-600 dark:text-blue-400">{{ metrics.point_counts.Triage }} Triage </span>
                                        <span v-if="metrics.point_counts?.Procedure" class="text-rose-600 dark:text-rose-400"><span v-if="metrics.point_counts?.Triage">· </span>{{ metrics.point_counts.Procedure }} Injections </span>
                                        <span v-if="metrics.point_counts?.Lab" class="text-purple-600 dark:text-purple-400"><span v-if="metrics.point_counts?.Triage || metrics.point_counts?.Procedure">· </span>{{ metrics.point_counts.Lab }} Lab </span>
                                        <span v-if="metrics.point_counts?.Doctor" class="text-amber-600 dark:text-amber-400"><span v-if="metrics.point_counts?.Triage || metrics.point_counts?.Procedure || metrics.point_counts?.Lab">· </span>{{ metrics.point_counts.Doctor }} Doctor </span>
                                        <span v-if="metrics.point_counts?.Pharmacy" class="text-emerald-600 dark:text-emerald-400"><span v-if="metrics.point_counts?.Triage || metrics.point_counts?.Procedure || metrics.point_counts?.Lab || metrics.point_counts?.Doctor">· </span>{{ metrics.point_counts.Pharmacy }} Pharmacy</span>
                                    </template>
                                    <template v-else>
                                        <span>All queues clear</span>
                                    </template>
                                </div>
                            </div>

                            <!-- KPI 4: POS Daily Collections -->
                            <div class="p-3 rounded-lg bg-card border border-border/80 space-y-1 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Daily POS Collections</span>
                                    <Receipt class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-emerald-700 dark:text-emerald-400">
                                    TZS {{ formatCurrency(metrics.today_revenue) }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">
                                    <span class="font-semibold text-rose-600">{{ metrics.unpaid_invoices }}</span> open invoices
                                </div>
                            </div>
                        </div>

                        <!-- ========================================================================= -->
                        <!-- TIER 2: LIVE DEPARTMENT QUEUES (RBAC-Safe Telemetry Cards)                -->
                        <!-- ========================================================================= -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between px-0.5">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                    <Layers class="w-3.5 h-3.5 text-primary" />
                                    <span>Live Service Points & Workstation Loads</span>
                                </div>
                                <Link v-if="can.queue" :href="route('queue.index')" class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-0.5">
                                    <span>Full Queue Board</span>
                                    <ArrowRight class="w-3 h-3" />
                                </Link>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5">
                                
                                <!-- Desk 1: Triage & Doctor -->
                                <component
                                    :is="getDeskRoute('Triage') ? Link : 'div'"
                                    :href="getDeskRoute('Triage')"
                                    class="block"
                                >
                                    <div class="p-2.5 rounded-lg border border-border/80 bg-card hover:bg-muted/40 transition-all flex items-center justify-between shadow-2xs group cursor-pointer">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-7.5 h-7.5 rounded-md bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
                                                <Stethoscope class="w-4 h-4" />
                                            </div>
                                            <div class="truncate min-w-0">
                                                <div class="font-bold text-[11px] text-foreground group-hover:text-primary transition-colors truncate">Triage & Doctor</div>
                                                <div class="text-[9px] text-muted-foreground truncate">OPD Consult Queue</div>
                                            </div>
                                        </div>
                                        <div class="text-right pl-1.5 flex-shrink-0">
                                            <div class="font-mono font-extrabold text-sm text-foreground">
                                                {{ (metrics.point_counts?.Triage || 0) + (metrics.point_counts?.Doctor || 0) }}
                                            </div>
                                            <div class="text-[8.5px] text-muted-foreground uppercase">waiting</div>
                                        </div>
                                    </div>
                                </component>

                                <!-- Desk 2: Procedure & Injections -->
                                <component
                                    :is="getDeskRoute('Procedure') ? Link : 'div'"
                                    :href="getDeskRoute('Procedure')"
                                    class="block"
                                >
                                    <div class="p-2.5 rounded-lg border border-rose-200 dark:border-rose-900/60 bg-card hover:bg-rose-50/20 transition-all flex items-center justify-between shadow-2xs group cursor-pointer">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-7.5 h-7.5 rounded-md bg-rose-100 dark:bg-rose-950/40 flex items-center justify-center text-rose-600 flex-shrink-0">
                                                <Syringe class="w-4 h-4" />
                                            </div>
                                            <div class="truncate min-w-0">
                                                <div class="font-bold text-[11px] text-foreground group-hover:text-rose-600 transition-colors truncate">Injection & Dressing</div>
                                                <div class="text-[9px] text-muted-foreground truncate">Minor Procedure Queue</div>
                                            </div>
                                        </div>
                                        <div class="text-right pl-1.5 flex-shrink-0">
                                            <div class="font-mono font-extrabold text-sm text-rose-700 dark:text-rose-400">
                                                {{ metrics.point_counts?.Procedure || 0 }}
                                            </div>
                                            <div class="text-[8.5px] text-muted-foreground uppercase">waiting</div>
                                        </div>
                                    </div>
                                </component>

                                <!-- Desk 3: Laboratory -->
                                <component
                                    :is="getDeskRoute('Lab') ? Link : 'div'"
                                    :href="getDeskRoute('Lab')"
                                    class="block"
                                >
                                    <div class="p-2.5 rounded-lg border border-sky-200 dark:border-sky-900/60 bg-card hover:bg-sky-50/20 transition-all flex items-center justify-between shadow-2xs group cursor-pointer">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-7.5 h-7.5 rounded-md bg-sky-100 dark:bg-sky-950/40 flex items-center justify-center text-sky-600 flex-shrink-0">
                                                <FlaskConical class="w-4 h-4" />
                                            </div>
                                            <div class="truncate min-w-0">
                                                <div class="font-bold text-[11px] text-foreground group-hover:text-sky-600 transition-colors truncate">Laboratory</div>
                                                <div class="text-[9px] text-muted-foreground truncate">Diagnostic Lab Queue</div>
                                            </div>
                                        </div>
                                        <div class="text-right pl-1.5 flex-shrink-0">
                                            <div class="font-mono font-extrabold text-sm text-sky-700 dark:text-sky-400">
                                                {{ metrics.point_counts?.Lab || 0 }}
                                            </div>
                                            <div class="text-[8.5px] text-muted-foreground uppercase">waiting</div>
                                        </div>
                                    </div>
                                </component>

                                <!-- Desk 4: Pharmacy -->
                                <component
                                    :is="getDeskRoute('Pharmacy') ? Link : 'div'"
                                    :href="getDeskRoute('Pharmacy')"
                                    class="block"
                                >
                                    <div class="p-2.5 rounded-lg border border-amber-200 dark:border-amber-900/60 bg-card hover:bg-amber-50/20 transition-all flex items-center justify-between shadow-2xs group cursor-pointer">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-7.5 h-7.5 rounded-md bg-amber-100 dark:bg-amber-950/40 flex items-center justify-center text-amber-600 flex-shrink-0">
                                                <Pill class="w-4 h-4" />
                                            </div>
                                            <div class="truncate min-w-0">
                                                <div class="font-bold text-[11px] text-foreground group-hover:text-amber-600 transition-colors truncate">Dispensary</div>
                                                <div class="text-[9px] text-muted-foreground truncate">Pharmacy Queue</div>
                                            </div>
                                        </div>
                                        <div class="text-right pl-1.5 flex-shrink-0">
                                            <div class="font-mono font-extrabold text-sm text-amber-700 dark:text-amber-400">
                                                {{ metrics.point_counts?.Pharmacy || 0 }}
                                            </div>
                                            <div class="text-[8.5px] text-muted-foreground uppercase">waiting</div>
                                        </div>
                                    </div>
                                </component>

                                <!-- Desk 5: Cashier Desk -->
                                <component
                                    :is="getDeskRoute('Cashier') ? Link : 'div'"
                                    :href="getDeskRoute('Cashier')"
                                    class="block"
                                >
                                    <div class="p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-card hover:bg-emerald-50/20 transition-all flex items-center justify-between shadow-2xs group cursor-pointer">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-7.5 h-7.5 rounded-md bg-emerald-100 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 flex-shrink-0">
                                                <Receipt class="w-4 h-4" />
                                            </div>
                                            <div class="truncate min-w-0">
                                                <div class="font-bold text-[11px] text-foreground group-hover:text-emerald-600 transition-colors truncate">Cashier Desk</div>
                                                <div class="text-[9px] text-muted-foreground truncate">Billing / POS Queue</div>
                                            </div>
                                        </div>
                                        <div class="text-right pl-1.5 flex-shrink-0">
                                            <div class="font-mono font-extrabold text-sm text-emerald-700 dark:text-emerald-400">
                                                {{ metrics.point_counts?.Cashier || 0 }}
                                            </div>
                                            <div class="text-[8.5px] text-muted-foreground uppercase">waiting</div>
                                        </div>
                                    </div>
                                </component>
                            </div>
                        </div>

                        <!-- ========================================================================= -->
                        <!-- TIER 3: TODAY'S SCHEDULED APPOINTMENTS (Full-Width High-Efficiency Grid)  -->
                        <!-- ========================================================================= -->
                        <div class="space-y-2 pt-1">
                            <div class="flex items-center justify-between px-0.5">
                                <div class="flex items-center gap-1.5">
                                    <Calendar class="w-4 h-4 text-primary" />
                                    <h2 class="text-sm font-bold text-foreground">Today's Scheduled Appointments</h2>
                                    <span class="text-xs text-muted-foreground">({{ todayAppointments.length }} scheduled)</span>
                                </div>
                                <Link v-if="can.appointments" :href="route('appointments.index')" class="text-xs font-semibold text-primary hover:underline flex items-center gap-0.5">
                                    <span>Full Booking Calendar</span>
                                    <ArrowRight class="w-3 h-3" />
                                </Link>
                            </div>

                            <div class="rounded-lg border border-border bg-card overflow-hidden shadow-2xs">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="w-[90px]">Time</TableHead>
                                            <TableHead class="min-w-[200px]">Patient Details</TableHead>
                                            <TableHead class="min-w-[180px]">Clinic & Practitioner</TableHead>
                                            <TableHead class="min-w-[140px]">Visit Reason</TableHead>
                                            <TableHead class="w-[130px]">Status</TableHead>
                                            <TableHead class="text-right whitespace-nowrap min-w-[160px]">Reception Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="appt in todayAppointments"
                                            :key="appt.id"
                                            class="cursor-pointer group hover:bg-muted/30"
                                            :selected="selectedRecord?.type === 'appointment' && selectedRecord?.data?.id === appt.id"
                                            @click="selectRecord('appointment', appt)"
                                        >
                                            <TableCell class="font-mono font-bold text-xs text-primary">
                                                {{ formatTime(appt.scheduled_time) }}
                                            </TableCell>
                                            <TableCell>
                                                <div class="font-bold text-foreground text-xs leading-tight">
                                                    {{ appt.patient ? `${appt.patient.first_name} ${appt.patient.last_name}` : 'Unknown Patient' }}
                                                </div>
                                                <div class="text-[10px] text-muted-foreground font-mono">
                                                    {{ appt.patient?.primary_mrn }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-xs">
                                                <div class="font-medium text-foreground">
                                                    {{ appt.practitioner?.name || appt.provider?.first_name || 'General OPD Doctor' }}
                                                </div>
                                                <div class="text-[10px] text-muted-foreground">
                                                    {{ appt.department?.name || 'OPD Clinic' }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-xs text-muted-foreground">
                                                {{ appt.reason || appt.appointment_type || 'Consultation' }}
                                            </TableCell>
                                            <TableCell>
                                                <span 
                                                    v-if="appt.status === 'Completed'" 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800"
                                                >
                                                    <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                                                    <span>✓ Completed</span>
                                                </span>
                                                <span 
                                                    v-else-if="appt.status === 'In Progress'" 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-300 dark:border-sky-800"
                                                >
                                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                                                    <span>In Consultation</span>
                                                </span>
                                                <span 
                                                    v-else-if="appt.status === 'Checked-In'" 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800"
                                                >
                                                    <span>In Queue</span>
                                                </span>
                                                <AfyaStatusBadge v-else :status="appt.status" dot />
                                            </TableCell>
                                            <TableCell class="text-right whitespace-nowrap min-w-[160px]">
                                                <div class="flex items-center justify-end gap-1.5" @click.stop>
                                                    <Button
                                                        v-if="appt.status === 'Scheduled' && can.checkin"
                                                        variant="default"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10.5px] font-semibold gap-1 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                                                        @click="checkInAppointment(appt)"
                                                    >
                                                        <Ticket class="w-3 h-3" />
                                                        <span>Check-In</span>
                                                    </Button>
                                                    <Link
                                                        v-if="appt.patient && can.patients"
                                                        :href="route('patients.show', appt.patient.id)"
                                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline px-1.5 py-0.5 rounded hover:bg-muted/40"
                                                    >
                                                        <span>360</span>
                                                        <ArrowRight class="w-3 h-3" />
                                                    </Link>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="todayAppointments.length === 0">
                                            <TableCell colspan="6" class="text-center py-12 text-muted-foreground text-xs">
                                                No scheduled appointments booked for today.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ========================================================================= -->
                        <!-- TIER 4: RECENT REGISTERED PATIENTS INFLOW (Full-Width Registry Grid)       -->
                        <!-- ========================================================================= -->
                        <div class="space-y-2 pt-2">
                            <div class="flex items-center justify-between px-0.5">
                                <div class="flex items-center gap-1.5">
                                    <Users class="w-4 h-4 text-primary" />
                                    <h2 class="text-sm font-bold text-foreground">Recent Registered Patients (Inflow Directory)</h2>
                                    <span class="text-xs text-muted-foreground">({{ recentPatients.length }} active)</span>
                                </div>
                                <Link v-if="can.patients" :href="route('patients.index')" class="text-xs font-semibold text-primary hover:underline flex items-center gap-0.5">
                                    <span>Patient Registry</span>
                                    <ArrowRight class="w-3 h-3" />
                                </Link>
                            </div>

                            <div class="rounded-lg border border-border bg-card overflow-hidden shadow-2xs">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="min-w-[140px] whitespace-nowrap">Primary MRN</TableHead>
                                            <TableHead class="min-w-[200px]">Full Patient Name</TableHead>
                                            <TableHead class="min-w-[140px]">Demographics</TableHead>
                                            <TableHead class="min-w-[140px]">Registration Date</TableHead>
                                            <TableHead class="w-[120px]">Status</TableHead>
                                            <TableHead class="text-right whitespace-nowrap min-w-[180px]">Reception Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="pt in recentPatients"
                                            :key="pt.id"
                                            class="cursor-pointer group hover:bg-muted/30"
                                            :selected="selectedRecord?.type === 'patient' && selectedRecord?.data?.id === pt.id"
                                            @click="selectRecord('patient', pt)"
                                        >
                                            <TableCell class="font-mono font-bold text-foreground text-xs whitespace-nowrap">
                                                {{ pt.primary_mrn }}
                                            </TableCell>
                                            <TableCell class="font-bold text-foreground text-xs">
                                                {{ pt.first_name }} {{ pt.last_name }}
                                            </TableCell>
                                            <TableCell class="text-xs text-muted-foreground">
                                                <span>{{ pt.gender || 'Unknown' }}</span>
                                                <span class="mx-1 text-border">·</span>
                                                <span>{{ pt.age ? `${pt.age}y` : formatDate(pt.dob) }}</span>
                                            </TableCell>
                                            <TableCell class="text-xs text-muted-foreground font-mono">
                                                {{ formatDate(pt.created_at) }}
                                            </TableCell>
                                            <TableCell>
                                                <AfyaStatusBadge :status="pt.status || 'Active'" dot />
                                            </TableCell>
                                            <TableCell class="text-right whitespace-nowrap min-w-[180px]">
                                                <div class="flex items-center justify-end gap-1.5" @click.stop>
                                                    <Button
                                                        v-if="can.checkin"
                                                        variant="default"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10.5px] font-semibold gap-1 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                                                        @click="openQuickCheckInModal(pt)"
                                                    >
                                                        <Ticket class="w-3 h-3" />
                                                        <span>Check-In</span>
                                                    </Button>
                                                    <Link
                                                        v-if="can.patients"
                                                        :href="route('patients.show', pt.id)"
                                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline px-1.5 py-0.5 rounded hover:bg-muted/40"
                                                    >
                                                        <span>360</span>
                                                        <ArrowRight class="w-3 h-3" />
                                                    </Link>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="recentPatients.length === 0">
                                            <TableCell colspan="6" class="text-center py-10 text-muted-foreground text-xs">
                                                No patient records registered today.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT INSPECTOR: 1-Click Launchpad & Identity Details -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    :title="selectedRecord?.type === 'patient' ? 'Patient Snapshot' : (selectedRecord?.type === 'appointment' ? 'Appointment Detail' : 'Context Details')"
                    :icon="selectedRecord?.type === 'appointment' ? Calendar : Users"
                    :width="width"
                    @close="close"
                >
                    <!-- Patient Context Inspector -->
                    <div v-if="selectedRecord?.type === 'patient'" class="space-y-3 text-xs">
                        <AfyaPatientIdentity :patient="selectedRecord.data">
                            <AfyaStatusBadge :status="selectedRecord.data.status || 'Active'" dot />
                        </AfyaPatientIdentity>

                        <div class="space-y-2 pt-2 border-t border-border">
                            <div class="text-[10px] font-bold text-foreground uppercase tracking-wider flex items-center justify-between">
                                <span class="flex items-center gap-1 text-primary">
                                    <Sparkles class="w-3 h-3 text-amber-500" />
                                    Reception Fast-Track Launchpad
                                </span>
                            </div>

                            <Button
                                v-if="can.checkin"
                                variant="default"
                                size="sm"
                                class="w-full justify-between gap-1 text-xs h-8 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                                @click="openQuickCheckInModal(selectedRecord.data)"
                            >
                                <div class="flex items-center gap-1.5">
                                    <Ticket class="w-3.5 h-3.5" />
                                    <span class="font-bold">Check-In & Route to Queue</span>
                                </div>
                                <span class="text-[9.5px] font-normal opacity-90">1-Click</span>
                            </Button>

                            <Link v-if="can.patients" :href="route('patients.show', selectedRecord.data.id)" class="block">
                                <Button variant="outline" size="sm" class="w-full justify-between gap-1 text-xs h-7.5 border-border/80 hover:bg-muted/40">
                                    <div class="flex items-center gap-1.5">
                                        <ShieldCheck class="w-3.5 h-3.5 text-sky-600" />
                                        <span>Add Insurance / NHIF Policy</span>
                                    </div>
                                    <ArrowRight class="w-3 h-3 text-muted-foreground" />
                                </Button>
                            </Link>

                            <Link v-if="can.patients" :href="route('patients.show', selectedRecord.data.id)" class="block">
                                <Button variant="ghost" size="sm" class="w-full justify-between gap-1 text-xs h-7.5 border border-dashed border-border/70 hover:bg-muted/30">
                                    <div class="flex items-center gap-1.5">
                                        <Users class="w-3.5 h-3.5 text-primary" />
                                        <span class="font-semibold text-foreground">Open Full Patient 360 Record</span>
                                    </div>
                                    <ArrowRight class="w-3 h-3 text-muted-foreground" />
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <!-- Appointment Context Inspector -->
                    <div v-else-if="selectedRecord?.type === 'appointment'" class="space-y-3 text-xs">
                        <div class="p-2.5 rounded-lg bg-card border border-border/80 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold text-muted-foreground uppercase">Scheduled Slot</span>
                                <AfyaStatusBadge :status="selectedRecord.data.status" dot />
                            </div>
                            <div class="font-mono font-bold text-sm text-foreground">
                                {{ formatTime(selectedRecord.data.scheduled_time) }} · {{ formatDate(selectedRecord.data.scheduled_time) }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                Type: <span class="font-semibold text-foreground">{{ selectedRecord.data.appointment_type || 'Consultation' }}</span>
                            </div>
                        </div>

                        <div v-if="selectedRecord.data.patient" class="p-2.5 rounded-lg bg-muted/30 border border-border/60 space-y-1">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase">Patient</div>
                            <div class="font-bold text-xs text-foreground">{{ selectedRecord.data.patient.first_name }} {{ selectedRecord.data.patient.last_name }}</div>
                            <div class="font-mono text-[10px] text-muted-foreground">MRN: {{ selectedRecord.data.patient.primary_mrn }}</div>
                        </div>

                        <div class="pt-2 border-t border-border space-y-1.5">
                            <Button
                                v-if="selectedRecord.data.status === 'Scheduled' && can.checkin"
                                variant="default"
                                size="sm"
                                class="w-full justify-center gap-1.5 text-xs h-8 bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs"
                                @click="checkInAppointment(selectedRecord.data)"
                            >
                                <Ticket class="w-3.5 h-3.5" />
                                <span>Check-In to Queue Now</span>
                            </Button>

                            <Link v-if="selectedRecord.data.patient && can.patients" :href="route('patients.show', selectedRecord.data.patient.id)" class="block">
                                <Button variant="outline" size="sm" class="w-full justify-between gap-1 text-xs h-7.5">
                                    <span>Open Patient 360</span>
                                    <ArrowRight class="w-3 h-3" />
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <!-- Clean Empty State -->
                    <div v-else class="text-center py-8 px-2 space-y-2 text-xs">
                        <div class="w-9 h-9 rounded-full bg-muted flex items-center justify-center mx-auto text-muted-foreground">
                            <LayoutDashboard class="w-4 h-4" />
                        </div>
                        <div class="space-y-0.5">
                            <div class="font-bold text-foreground text-xs">Reception Command Center</div>
                            <p class="text-[11px] text-muted-foreground leading-tight">
                                Click any appointment or registered patient row to view identity, quick triage routing, and appointment details.
                            </p>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>

        </AfyaWorkspace>

        <AfyaCheckInModal
            :show="showCheckInModal"
            :patient="checkInPatient"
            :recent-patients="recentPatients"
            :lab-tests="labTests"
            :procedure-catalogs="procedureCatalogs"
            :active-tickets="activeQueueTickets"
            @close="showCheckInModal = false"
        />
    </AfyaShell>
</template>
