<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { 
    LayoutDashboard, 
    Stethoscope, 
    Clock, 
    Receipt, 
    Pill, 
    Users, 
    Activity, 
    ArrowRight, 
    ArrowUpRight, 
    Plus, 
    Calendar, 
    Wallet,
    X,
    PhoneCall,
    CheckCircle2,
    CalendarCheck
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';

// Design Foundation Primitives
import Button from '@/Components/ui/Button.vue';
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
    metrics: {
        type: Object,
        default: () => ({
            total_patients: 0,
            active_encounters: 0,
            today_appointments: 0,
            queue_waiting: 0,
            pending_pharmacy: 0,
            unpaid_invoices: 0,
            today_revenue: 0,
        }),
    },
    recentEncounters: {
        type: Array,
        default: () => [],
    },
    recentPatients: {
        type: Array,
        default: () => [],
    },
    recentInvoices: {
        type: Array,
        default: () => [],
    },
    queueTickets: {
        type: Array,
        default: () => [],
    },
    todayAppointments: {
        type: Array,
        default: () => [],
    },
    pendingPrescriptions: {
        type: Array,
        default: () => [],
    },
});

import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const { preferences, openContext } = useWorkspacePreferences();

const page = usePage();
const user = computed(() => page.props.auth.user);
const activeTab = ref('all'); // 'all' | 'clinical' | 'queue' | 'appointments' | 'pharmacy' | 'billing' | 'patients'
const selectedRecord = ref(null);

const selectRecord = (type, data) => {
    selectedRecord.value = { type, data };
    openContext();
};

const clearSelectedRecord = () => {
    selectedRecord.value = null;
};

const breadcrumbTitle = computed(() => {
    switch (activeTab.value) {
        case 'clinical': return 'Active Clinical Encounters';
        case 'queue': return 'Live Triage & Service Queue';
        case 'appointments': return 'Scheduled Appointments';
        case 'pharmacy': return 'Pharmacy Dispensing Queue';
        case 'billing': return 'Cashier & Invoicing Desk';
        case 'patients': return 'Master Patient Index (MPI)';
        default: return 'Hospital Operations Overview';
    }
});

const formatCurrency = (amount) => {
    return Number(amount || 0).toLocaleString('en-US');
};

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
    <Head title="Hospital Command Center — AfyaNova Workstation" />

    <AfyaShell active-module="dashboard">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Hospital Command Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Hospital Command"
                    :icon="LayoutDashboard"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Operational Views
                    </div>
                    
                    <AfyaSidebarItem
                        label="Overview Dashboard"
                        :icon="LayoutDashboard"
                        :active="activeTab === 'all'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'all'"
                    />
                    
                    <AfyaSidebarItem
                        v-if="can.clinical"
                        label="Clinical Encounters"
                        :icon="Stethoscope"
                        :badge="metrics.active_encounters"
                        :active="activeTab === 'clinical'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'clinical'"
                    />

                    <AfyaSidebarItem
                        v-if="can.queue"
                        label="Live Queue & Triage"
                        :icon="Clock"
                        :badge="metrics.queue_waiting"
                        :active="activeTab === 'queue'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'queue'"
                    />

                    <AfyaSidebarItem
                        v-if="can.appointments"
                        label="Scheduled Appointments"
                        :icon="Calendar"
                        :badge="metrics.today_appointments"
                        :active="activeTab === 'appointments'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'appointments'"
                    />

                    <AfyaSidebarItem
                        v-if="can.pharmacy"
                        label="Pharmacy Dispensary"
                        :icon="Pill"
                        :badge="metrics.pending_pharmacy"
                        :active="activeTab === 'pharmacy'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'pharmacy'"
                    />

                    <AfyaSidebarItem
                        v-if="can.billing"
                        label="Cashier & Billing Desk"
                        :icon="Receipt"
                        :badge="metrics.unpaid_invoices"
                        badge-color="bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
                        :active="activeTab === 'billing'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'billing'"
                    />

                    <AfyaSidebarItem
                        v-if="can.patients"
                        label="Patient Registry"
                        :icon="Users"
                        :badge="metrics.total_patients"
                        :active="activeTab === 'patients'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'patients'"
                    />

                    <!-- Direct Workstation Jump Links -->
                    <template v-if="state !== 'collapsed'">
                        <div class="px-2 pt-3 pb-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border/60 mt-2">
                            Workstations
                        </div>
                        <AfyaSidebarItem
                            label="Clinical Workspace"
                            :icon="Stethoscope"
                            :collapsed="false"
                            :href="route('workspace.clinical')"
                        />
                        <AfyaSidebarItem
                            label="Cashier POS Desk"
                            :icon="Receipt"
                            :collapsed="false"
                            :href="route('billing.desk')"
                        />
                        <AfyaSidebarItem
                            label="Pharmacy Queue"
                            :icon="Pill"
                            :collapsed="false"
                            :href="route('pharmacy.queue')"
                        />
                    </template>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: High-Density Hospital Operations Hub -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Hospital Operations', href: route('dashboard') },
                        { label: breadcrumbTitle, active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="activeTab !== 'all'"
                                @click="activeTab = 'all'"
                                class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 mr-1"
                            >
                                <span>Show Overview</span>
                            </button>
                            <Link :href="route('patients.create')">
                                <Button variant="default" size="sm" class="h-7 text-xs font-semibold gap-1.5 shadow-2xs">
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Register Patient</span>
                                </Button>
                            </Link>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        
                        <!-- Top Enterprise Metric Strip (Interactive View Switchers) -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 w-full">
                            
                            <!-- Metric 1: Active Consultations -->
                            <div 
                                @click="activeTab = activeTab === 'clinical' ? 'all' : 'clinical'"
                                class="p-3 rounded-lg bg-card hover:bg-muted/40 transition-all cursor-pointer space-y-1 select-none shadow-2xs group"
                                :class="activeTab === 'clinical' ? 'ring-2 ring-primary bg-primary/5' : ''"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Active Consults</span>
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                </div>
                                <div class="text-xl font-mono font-extrabold text-primary">
                                    {{ metrics.active_encounters }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">In examination</div>
                            </div>

                            <!-- Metric 2: Live Queue Waiting -->
                            <div 
                                @click="activeTab = activeTab === 'queue' ? 'all' : 'queue'"
                                class="p-3 rounded-lg bg-card hover:bg-muted/40 transition-all cursor-pointer space-y-1 select-none shadow-2xs group"
                                :class="activeTab === 'queue' ? 'ring-2 ring-amber-500 bg-amber-50/20' : ''"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Queue Waiting</span>
                                    <Clock class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-amber-700 dark:text-amber-400">
                                    {{ metrics.queue_waiting }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">Triage pending</div>
                            </div>

                            <!-- Metric 3: Today's Appointments -->
                            <div 
                                @click="activeTab = activeTab === 'appointments' ? 'all' : 'appointments'"
                                class="p-3 rounded-lg bg-card hover:bg-muted/40 transition-all cursor-pointer space-y-1 select-none shadow-2xs group"
                                :class="activeTab === 'appointments' ? 'ring-2 ring-primary bg-primary/5' : ''"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Scheduled</span>
                                    <Calendar class="w-3.5 h-3.5 text-primary flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-foreground">
                                    {{ metrics.today_appointments }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">Today's booked slots</div>
                            </div>

                            <!-- Metric 4: Pharmacy Dispensing Orders -->
                            <div 
                                @click="activeTab = activeTab === 'pharmacy' ? 'all' : 'pharmacy'"
                                class="p-3 rounded-lg bg-card hover:bg-muted/40 transition-all cursor-pointer space-y-1 select-none shadow-2xs group"
                                :class="activeTab === 'pharmacy' ? 'ring-2 ring-sky-500 bg-sky-50/20' : ''"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Pharmacy Rx</span>
                                    <Pill class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-sky-800 dark:text-sky-400">
                                    {{ metrics.pending_pharmacy }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">Orders dispensing</div>
                            </div>

                            <!-- Metric 5: Prepaid Revenue Today -->
                            <div 
                                @click="activeTab = activeTab === 'billing' ? 'all' : 'billing'"
                                class="p-3 rounded-lg bg-card hover:bg-muted/40 transition-all cursor-pointer space-y-1 select-none shadow-2xs group"
                                :class="activeTab === 'billing' ? 'ring-2 ring-emerald-500 bg-emerald-50/20' : ''"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Daily Revenue</span>
                                    <Wallet class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                                </div>
                                <div class="text-base font-mono font-extrabold text-emerald-700 dark:text-emerald-400 truncate">
                                    TZS {{ formatCurrency(metrics.today_revenue) }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">Prepaid collected</div>
                            </div>

                            <!-- Metric 6: Total Patients in Master Registry -->
                            <div 
                                @click="activeTab = activeTab === 'patients' ? 'all' : 'patients'"
                                class="p-3 rounded-lg bg-card hover:bg-muted/40 transition-all cursor-pointer space-y-1 select-none shadow-2xs group"
                                :class="activeTab === 'patients' ? 'ring-2 ring-primary bg-primary/5' : ''"
                            >
                                <div class="flex items-center justify-between text-muted-foreground">
                                    <span class="text-[10px] font-bold uppercase tracking-wider group-hover:text-foreground transition-colors">Total Patients</span>
                                    <Users class="w-3.5 h-3.5 text-foreground flex-shrink-0" />
                                </div>
                                <div class="text-xl font-mono font-extrabold text-foreground">
                                    {{ metrics.total_patients }}
                                </div>
                                <div class="text-[10px] text-muted-foreground truncate">MPI registered</div>
                            </div>
                        </div>

                        <!-- =============================================================== -->
                        <!-- VIEW 1: DEFAULT COMPREHENSIVE OVERVIEW (activeTab === 'all')    -->
                        <!-- =============================================================== -->
                        <div v-if="activeTab === 'all'" class="space-y-4 w-full">
                            
                            <!-- Row 1: 2-Column Grid (Encounters on Left, Billing on Right) -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 w-full">
                                
                                <!-- Col 1: Active Encounters -->
                                <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                    <div class="px-4 py-2.5 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                        <div class="flex items-center gap-2 text-xs font-bold text-foreground uppercase tracking-wider">
                                            <Stethoscope class="w-3.5 h-3.5 text-primary" />
                                            <span>Active Clinical Encounters</span>
                                        </div>
                                        <Link :href="route('workspace.clinical')" class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1">
                                            <span>Clinical Desk</span>
                                            <ArrowUpRight class="w-3 h-3" />
                                        </Link>
                                    </div>
                                    <div class="w-full overflow-x-auto">
                                        <Table class="w-full text-xs">
                                            <TableHeader>
                                                <TableRow class="h-8 text-[10px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                                    <TableHead class="py-1.5 px-4">Patient</TableHead>
                                                    <TableHead class="py-1.5 px-3">Provider</TableHead>
                                                    <TableHead class="py-1.5 px-3">Type</TableHead>
                                                    <TableHead class="py-1.5 px-3">Status</TableHead>
                                                    <TableHead class="py-1.5 px-4 text-right">Action</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow 
                                                    v-for="enc in recentEncounters" 
                                                    :key="enc.id"
                                                    class="h-10 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                                    :class="{ 'bg-primary/5': selectedRecord?.type === 'encounter' && selectedRecord?.data?.id === enc.id }"
                                                    @click="selectRecord('encounter', enc)"
                                                >
                                                    <TableCell class="py-1.5 px-4">
                                                        <div class="font-bold text-foreground truncate max-w-[130px]">{{ enc.patient?.first_name }} {{ enc.patient?.last_name }}</div>
                                                        <div class="text-[9px] font-mono text-muted-foreground">{{ enc.patient?.primary_mrn }}</div>
                                                    </TableCell>
                                                    <TableCell class="py-1.5 px-3 text-muted-foreground text-[11px] truncate max-w-[100px]">
                                                        {{ enc.provider ? `Dr. ${enc.provider.name || enc.provider.first_name || ''}` : 'On-Duty Officer' }}
                                                    </TableCell>
                                                    <TableCell class="py-1.5 px-3 font-mono text-[10px] text-muted-foreground">{{ enc.encounter_type || 'OPD' }}</TableCell>
                                                    <TableCell class="py-1.5 px-3">
                                                        <AfyaStatusBadge :status="enc.status" dot />
                                                    </TableCell>
                                                    <TableCell class="py-1.5 px-4 text-right">
                                                        <Link :href="route('encounters.workspace', enc.id)" @click.stop>
                                                            <Button variant="subtle" size="sm" class="h-6 px-2.5 text-[10px] font-semibold">
                                                                Chart
                                                            </Button>
                                                        </Link>
                                                    </TableCell>
                                                </TableRow>

                                                <TableRow v-if="recentEncounters.length === 0">
                                                    <TableCell colspan="5" class="text-center py-8 text-muted-foreground text-xs">
                                                        No active encounters currently in progress.
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>

                                <!-- Col 2: Recent Invoices & POS Collections -->
                                <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                    <div class="px-4 py-2.5 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                        <div class="flex items-center gap-2 text-xs font-bold text-foreground uppercase tracking-wider">
                                            <Receipt class="w-3.5 h-3.5 text-primary" />
                                            <span>Recent Invoices & POS Collections</span>
                                        </div>
                                        <Link :href="route('billing.desk')" class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1">
                                            <span>Cashier Desk</span>
                                            <ArrowUpRight class="w-3 h-3" />
                                        </Link>
                                    </div>
                                    <div class="w-full overflow-x-auto">
                                        <Table class="w-full text-xs">
                                            <TableHeader>
                                                <TableRow class="h-8 text-[10px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                                    <TableHead class="py-1.5 px-4">Invoice #</TableHead>
                                                    <TableHead class="py-1.5 px-3">Patient</TableHead>
                                                    <TableHead class="py-1.5 px-3">Total (TZS)</TableHead>
                                                    <TableHead class="py-1.5 px-3">Status</TableHead>
                                                    <TableHead class="py-1.5 px-4 text-right">Action</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow 
                                                    v-for="inv in recentInvoices" 
                                                    :key="inv.id"
                                                    class="h-10 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                                    :class="{ 'bg-primary/5': selectedRecord?.type === 'invoice' && selectedRecord?.data?.id === inv.id }"
                                                    @click="selectRecord('invoice', inv)"
                                                >
                                                    <TableCell class="py-1.5 px-4 font-mono font-bold text-foreground text-[11px]">{{ inv.invoice_number }}</TableCell>
                                                    <TableCell class="py-1.5 px-3">
                                                        <div class="font-bold text-foreground truncate max-w-[120px]">{{ inv.patient?.first_name }} {{ inv.patient?.last_name }}</div>
                                                        <div class="text-[9px] font-mono text-muted-foreground">{{ inv.patient?.primary_mrn }}</div>
                                                    </TableCell>
                                                    <TableCell class="py-1.5 px-3 font-mono font-semibold text-[11px]">{{ formatCurrency(inv.total_amount) }}</TableCell>
                                                    <TableCell class="py-1.5 px-3">
                                                        <AfyaStatusBadge :status="inv.status" dot />
                                                    </TableCell>
                                                    <TableCell class="py-1.5 px-4 text-right">
                                                        <Link :href="route('billing.desk')" @click.stop>
                                                            <Button variant="subtle" size="sm" class="h-6 px-2.5 text-[10px] font-semibold">
                                                                Inspect
                                                            </Button>
                                                        </Link>
                                                    </TableCell>
                                                </TableRow>

                                                <TableRow v-if="recentInvoices.length === 0">
                                                    <TableCell colspan="5" class="text-center py-8 text-muted-foreground text-xs">
                                                        No billing transactions recorded today.
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>

                            </div>

                            <!-- Row 2: Full-Width Patient Inflow Directory Across Bottom -->
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-4 py-2.5 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-xs font-bold text-foreground uppercase tracking-wider">
                                        <Users class="w-3.5 h-3.5 text-primary" />
                                        <span>Master Patient Index — Recent Registrations</span>
                                    </div>
                                    <Link :href="route('patients.index')" class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1">
                                        <span>All Patients Directory</span>
                                        <ArrowUpRight class="w-3 h-3" />
                                    </Link>
                                </div>
                                
                                <div class="w-full overflow-x-auto">
                                    <Table class="w-full text-xs">
                                        <TableHeader>
                                            <TableRow class="h-8 text-[10px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                                <TableHead class="py-1.5 px-4">Primary MRN</TableHead>
                                                <TableHead class="py-1.5 px-3">Patient Name</TableHead>
                                                <TableHead class="py-1.5 px-3">Demographics</TableHead>
                                                <TableHead class="py-1.5 px-3">Blood Group</TableHead>
                                                <TableHead class="py-1.5 px-3">Registered</TableHead>
                                                <TableHead class="py-1.5 px-3">Status</TableHead>
                                                <TableHead class="py-1.5 px-4 text-right">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow 
                                                v-for="pat in recentPatients" 
                                                :key="pat.id"
                                                class="h-10 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                                :class="{ 'bg-primary/5': selectedRecord?.type === 'patient' && selectedRecord?.data?.id === pat.id }"
                                                @click="selectRecord('patient', pat)"
                                            >
                                                <TableCell class="py-1.5 px-4 font-mono font-bold text-foreground text-[11px]">
                                                    {{ pat.primary_mrn }}
                                                </TableCell>
                                                <TableCell class="py-1.5 px-3 font-bold text-foreground">
                                                    {{ pat.first_name }} {{ pat.last_name }}
                                                </TableCell>
                                                <TableCell class="py-1.5 px-3 text-muted-foreground text-[11px]">
                                                    {{ pat.gender }} · {{ pat.age ? `${pat.age}y` : (pat.formatted_dob || formatDate(pat.dob)) }}
                                                </TableCell>
                                                <TableCell class="py-1.5 px-3 font-mono font-semibold text-[11px] text-rose-600 dark:text-rose-400">
                                                    {{ pat.blood_group || 'O+' }}
                                                </TableCell>
                                                <TableCell class="py-1.5 px-3 font-mono text-muted-foreground text-[10px]">
                                                    {{ formatDate(pat.created_at) }}
                                                </TableCell>
                                                <TableCell class="py-1.5 px-3">
                                                    <AfyaStatusBadge :status="pat.status || 'Active'" />
                                                </TableCell>
                                                <TableCell class="py-1.5 px-4 text-right">
                                                    <Link :href="route('patients.show', pat.id)" @click.stop>
                                                        <Button variant="subtle" size="sm" class="h-6 px-2.5 text-[10px] font-semibold">
                                                            Profile
                                                        </Button>
                                                    </Link>
                                                </TableCell>
                                            </TableRow>

                                            <TableRow v-if="recentPatients.length === 0">
                                                <TableCell colspan="7" class="text-center py-10 text-muted-foreground text-xs">
                                                    No patient records registered yet.
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>

                        </div>

                        <!-- =============================================================== -->
                        <!-- VIEW 2: CLINICAL ENCOUNTERS (activeTab === 'clinical')          -->
                        <!-- =============================================================== -->
                        <div v-else-if="activeTab === 'clinical'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col space-y-0">
                            <div class="px-4 py-3 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                                    <Stethoscope class="w-4 h-4 text-primary" />
                                    <span>Active Clinical Encounters & Consultations</span>
                                </div>
                                <Link :href="route('workspace.clinical')">
                                    <Button variant="default" size="sm" class="h-7 text-xs font-semibold gap-1.5 shadow-2xs">
                                        <span>Open Clinical Workstation</span>
                                        <ArrowUpRight class="w-3.5 h-3.5" />
                                    </Button>
                                </Link>
                            </div>
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="bg-muted/30 text-[10px] uppercase font-bold text-muted-foreground">
                                        <TableHead class="py-2 px-4">Patient</TableHead>
                                        <TableHead class="py-2 px-3">Attending Physician</TableHead>
                                        <TableHead class="py-2 px-3">Encounter Type</TableHead>
                                        <TableHead class="py-2 px-3">Reason for Visit</TableHead>
                                        <TableHead class="py-2 px-3">Status</TableHead>
                                        <TableHead class="py-2 px-4 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow 
                                        v-for="enc in recentEncounters" 
                                        :key="enc.id"
                                        class="cursor-pointer hover:bg-muted/30 border-b border-border/30"
                                        :class="{ 'bg-primary/5': selectedRecord?.type === 'encounter' && selectedRecord?.data?.id === enc.id }"
                                        @click="selectRecord('encounter', enc)"
                                    >
                                        <TableCell class="py-2.5 px-4 font-bold text-foreground">
                                            {{ enc.patient?.first_name }} {{ enc.patient?.last_name }}
                                            <div class="text-[10px] font-mono text-muted-foreground">{{ enc.patient?.primary_mrn }}</div>
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 text-muted-foreground">
                                            {{ enc.provider ? `Dr. ${enc.provider.name || enc.provider.first_name || ''}` : 'On-Duty Officer' }}
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 font-mono">{{ enc.encounter_type || 'OPD' }}</TableCell>
                                        <TableCell class="py-2.5 px-3 text-muted-foreground">{{ enc.reason_for_visit || 'General Consultation' }}</TableCell>
                                        <TableCell class="py-2.5 px-3">
                                            <AfyaStatusBadge :status="enc.status" dot />
                                        </TableCell>
                                        <TableCell class="py-2.5 px-4 text-right">
                                            <Link :href="route('encounters.workspace', enc.id)" @click.stop>
                                                <Button variant="default" size="sm" class="h-7 px-3 text-xs font-semibold">
                                                    Launch Chart
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="recentEncounters.length === 0">
                                        <TableCell colspan="6" class="text-center py-10 text-muted-foreground text-xs">
                                            No active clinical encounters found.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- =============================================================== -->
                        <!-- VIEW 3: LIVE QUEUE & TRIAGE (activeTab === 'queue')             -->
                        <!-- =============================================================== -->
                        <div v-else-if="activeTab === 'queue'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col space-y-0">
                            <div class="px-4 py-3 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                                    <Clock class="w-4 h-4 text-amber-600" />
                                    <span>Live Triage & Service Point Queue</span>
                                </div>
                                <Link :href="route('queue.index')">
                                    <Button variant="default" size="sm" class="h-7 text-xs font-semibold gap-1.5 shadow-2xs">
                                        <span>Open Full Queue Desk</span>
                                        <ArrowUpRight class="w-3.5 h-3.5" />
                                    </Button>
                                </Link>
                            </div>
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="bg-muted/30 text-[10px] uppercase font-bold text-muted-foreground">
                                        <TableHead class="py-2 px-4">Ticket #</TableHead>
                                        <TableHead class="py-2 px-3">Patient</TableHead>
                                        <TableHead class="py-2 px-3">Service Point</TableHead>
                                        <TableHead class="py-2 px-3">Priority</TableHead>
                                        <TableHead class="py-2 px-3">Status</TableHead>
                                        <TableHead class="py-2 px-4 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow 
                                        v-for="t in queueTickets" 
                                        :key="t.id"
                                        class="cursor-pointer hover:bg-muted/30 border-b border-border/30"
                                        :class="{ 'bg-primary/5': selectedRecord?.type === 'ticket' && selectedRecord?.data?.id === t.id }"
                                        @click="selectRecord('ticket', t)"
                                    >
                                        <TableCell class="py-2.5 px-4 font-mono font-bold text-foreground text-sm">
                                            {{ t.ticket_number }}
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 font-bold">
                                            {{ t.patient?.first_name }} {{ t.patient?.last_name }}
                                            <div class="text-[10px] font-mono text-muted-foreground">{{ t.patient?.primary_mrn }}</div>
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 font-medium text-foreground">{{ t.service_point || 'Triage' }}</TableCell>
                                        <TableCell class="py-2.5 px-3">
                                            <span 
                                                class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                :class="t.priority === 'Emergency' ? 'bg-rose-600 text-white animate-pulse' : 'bg-muted text-muted-foreground'"
                                            >
                                                {{ t.priority || 'Normal' }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3">
                                            <AfyaStatusBadge :status="t.status" dot />
                                        </TableCell>
                                        <TableCell class="py-2.5 px-4 text-right">
                                            <Link :href="route('queue.index')" @click.stop>
                                                <Button variant="default" size="sm" class="h-7 px-3 text-xs font-semibold">
                                                    Call Patient
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="queueTickets.length === 0">
                                        <TableCell colspan="6" class="text-center py-10 text-muted-foreground text-xs">
                                            No patients currently waiting in triage queue.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- =============================================================== -->
                        <!-- VIEW 4: APPOINTMENTS (activeTab === 'appointments')             -->
                        <!-- =============================================================== -->
                        <div v-else-if="activeTab === 'appointments'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col space-y-0">
                            <div class="px-4 py-3 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                                    <CalendarCheck class="w-4 h-4 text-primary" />
                                    <span>Today's Scheduled Appointments</span>
                                </div>
                                <Link :href="route('appointments.index')">
                                    <Button variant="default" size="sm" class="h-7 text-xs font-semibold gap-1.5 shadow-2xs">
                                        <span>Open Booking Calendar</span>
                                        <ArrowUpRight class="w-3.5 h-3.5" />
                                    </Button>
                                </Link>
                            </div>
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="bg-muted/30 text-[10px] uppercase font-bold text-muted-foreground">
                                        <TableHead class="py-2 px-4">Time Slot</TableHead>
                                        <TableHead class="py-2 px-3">Patient</TableHead>
                                        <TableHead class="py-2 px-3">Doctor / Practitioner</TableHead>
                                        <TableHead class="py-2 px-3">Department</TableHead>
                                        <TableHead class="py-2 px-3">Status</TableHead>
                                        <TableHead class="py-2 px-4 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow 
                                        v-for="appt in todayAppointments" 
                                        :key="appt.id"
                                        class="cursor-pointer hover:bg-muted/30 border-b border-border/30"
                                        :class="{ 'bg-primary/5': selectedRecord?.type === 'appointment' && selectedRecord?.data?.id === appt.id }"
                                        @click="selectRecord('appointment', appt)"
                                    >
                                        <TableCell class="py-2.5 px-4 font-mono font-bold text-primary">
                                            {{ formatTime(appt.scheduled_time) }}
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 font-bold">
                                            {{ appt.patient?.first_name }} {{ appt.patient?.last_name }}
                                            <div class="text-[10px] font-mono text-muted-foreground">{{ appt.patient?.primary_mrn }}</div>
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 text-muted-foreground">
                                            {{ appt.practitioner?.name || 'Assigned Doctor' }}
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3">{{ appt.department?.name || 'OPD General' }}</TableCell>
                                        <TableCell class="py-2.5 px-3">
                                            <AfyaStatusBadge :status="appt.status || 'Booked'" dot />
                                        </TableCell>
                                        <TableCell class="py-2.5 px-4 text-right">
                                            <Link :href="route('appointments.index')" @click.stop>
                                                <Button variant="default" size="sm" class="h-7 px-3 text-xs font-semibold">
                                                    Check-In
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="todayAppointments.length === 0">
                                        <TableCell colspan="6" class="text-center py-10 text-muted-foreground text-xs">
                                            No appointments scheduled for today.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- =============================================================== -->
                        <!-- VIEW 5: PHARMACY RX (activeTab === 'pharmacy')                  -->
                        <!-- =============================================================== -->
                        <div v-else-if="activeTab === 'pharmacy'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col space-y-0">
                            <div class="px-4 py-3 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                                    <Pill class="w-4 h-4 text-sky-600" />
                                    <span>Prescriptions Awaiting Dispensing</span>
                                </div>
                                <Link :href="route('pharmacy.queue')">
                                    <Button variant="default" size="sm" class="h-7 text-xs font-semibold gap-1.5 shadow-2xs">
                                        <span>Open Pharmacy Queue</span>
                                        <ArrowUpRight class="w-3.5 h-3.5" />
                                    </Button>
                                </Link>
                            </div>
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="bg-muted/30 text-[10px] uppercase font-bold text-muted-foreground">
                                        <TableHead class="py-2 px-4">Prescription</TableHead>
                                        <TableHead class="py-2 px-3">Patient</TableHead>
                                        <TableHead class="py-2 px-3">Dosage & Frequency</TableHead>
                                        <TableHead class="py-2 px-3">Status</TableHead>
                                        <TableHead class="py-2 px-4 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow 
                                        v-for="rx in pendingPrescriptions" 
                                        :key="rx.id"
                                        class="cursor-pointer hover:bg-muted/30 border-b border-border/30"
                                        :class="{ 'bg-primary/5': selectedRecord?.type === 'rx' && selectedRecord?.data?.id === rx.id }"
                                        @click="selectRecord('rx', rx)"
                                    >
                                        <TableCell class="py-2.5 px-4 font-bold text-foreground">
                                            {{ rx.medication?.name || 'Medication' }}
                                            <div class="text-[10px] font-mono text-muted-foreground">{{ rx.medication?.code }}</div>
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 font-bold">
                                            {{ rx.encounter?.patient?.first_name }} {{ rx.encounter?.patient?.last_name }}
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 text-muted-foreground">
                                            {{ rx.dosage }} · {{ rx.frequency }} ({{ rx.duration_days }} days)
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3">
                                            <AfyaStatusBadge :status="rx.status" dot />
                                        </TableCell>
                                        <TableCell class="py-2.5 px-4 text-right">
                                            <Link :href="route('pharmacy.queue')" @click.stop>
                                                <Button variant="default" size="sm" class="h-7 px-3 text-xs font-semibold">
                                                    Dispense
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="pendingPrescriptions.length === 0">
                                        <TableCell colspan="5" class="text-center py-10 text-muted-foreground text-xs">
                                            No active pharmacy prescription orders pending.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- =============================================================== -->
                        <!-- VIEW 6: CASHIER & BILLING (activeTab === 'billing')             -->
                        <!-- =============================================================== -->
                        <div v-else-if="activeTab === 'billing'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col space-y-0">
                            <div class="px-4 py-3 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                                    <Receipt class="w-4 h-4 text-emerald-600" />
                                    <span>Hospital Invoicing & POS Payment Desk</span>
                                </div>
                                <Link :href="route('billing.desk')">
                                    <Button variant="default" size="sm" class="h-7 text-xs font-semibold gap-1.5 shadow-2xs">
                                        <span>Launch Cashier Desk</span>
                                        <ArrowUpRight class="w-3.5 h-3.5" />
                                    </Button>
                                </Link>
                            </div>
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="bg-muted/30 text-[10px] uppercase font-bold text-muted-foreground">
                                        <TableHead class="py-2 px-4">Invoice #</TableHead>
                                        <TableHead class="py-2 px-3">Patient</TableHead>
                                        <TableHead class="py-2 px-3">Total Amount</TableHead>
                                        <TableHead class="py-2 px-3">Paid</TableHead>
                                        <TableHead class="py-2 px-3">Status</TableHead>
                                        <TableHead class="py-2 px-4 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow 
                                        v-for="inv in recentInvoices" 
                                        :key="inv.id"
                                        class="cursor-pointer hover:bg-muted/30 border-b border-border/30"
                                        :class="{ 'bg-primary/5': selectedRecord?.type === 'invoice' && selectedRecord?.data?.id === inv.id }"
                                        @click="selectRecord('invoice', inv)"
                                    >
                                        <TableCell class="py-2.5 px-4 font-mono font-bold text-foreground">{{ inv.invoice_number }}</TableCell>
                                        <TableCell class="py-2.5 px-3 font-bold">
                                            {{ inv.patient?.first_name }} {{ inv.patient?.last_name }}
                                            <div class="text-[10px] font-mono text-muted-foreground">{{ inv.patient?.primary_mrn }}</div>
                                        </TableCell>
                                        <TableCell class="py-2.5 px-3 font-mono font-bold text-foreground">TZS {{ formatCurrency(inv.total_amount) }}</TableCell>
                                        <TableCell class="py-2.5 px-3 font-mono font-semibold text-emerald-700 dark:text-emerald-400">TZS {{ formatCurrency(inv.paid_amount) }}</TableCell>
                                        <TableCell class="py-2.5 px-3">
                                            <AfyaStatusBadge :status="inv.status" dot />
                                        </TableCell>
                                        <TableCell class="py-2.5 px-4 text-right">
                                            <Link :href="route('billing.desk')" @click.stop>
                                                <Button variant="default" size="sm" class="h-7 px-3 text-xs font-semibold">
                                                    Collect Payment
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="recentInvoices.length === 0">
                                        <TableCell colspan="6" class="text-center py-10 text-muted-foreground text-xs">
                                            No invoice records registered.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- =============================================================== -->
                        <!-- VIEW 7: MASTER PATIENT INDEX (activeTab === 'patients')         -->
                        <!-- =============================================================== -->
                        <div v-else-if="activeTab === 'patients'" class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col space-y-0">
                            <div class="px-4 py-3 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                                    <Users class="w-4 h-4 text-primary" />
                                    <span>Master Patient Index (MPI) Registry</span>
                                </div>
                                <Link :href="route('patients.index')">
                                    <Button variant="default" size="sm" class="h-7 text-xs font-semibold gap-1.5 shadow-2xs">
                                        <span>Full Patient Directory</span>
                                        <ArrowUpRight class="w-3.5 h-3.5" />
                                    </Button>
                                </Link>
                            </div>
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="bg-muted/30 text-[10px] uppercase font-bold text-muted-foreground">
                                        <TableHead class="py-2 px-4">Primary MRN</TableHead>
                                        <TableHead class="py-2 px-3">Full Name</TableHead>
                                        <TableHead class="py-2 px-3">Demographics</TableHead>
                                        <TableHead class="py-2 px-3">Blood Group</TableHead>
                                        <TableHead class="py-2 px-3">Created</TableHead>
                                        <TableHead class="py-2 px-3">Status</TableHead>
                                        <TableHead class="py-2 px-4 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow 
                                        v-for="pat in recentPatients" 
                                        :key="pat.id"
                                        class="cursor-pointer hover:bg-muted/30 border-b border-border/30"
                                        :class="{ 'bg-primary/5': selectedRecord?.type === 'patient' && selectedRecord?.data?.id === pat.id }"
                                        @click="selectRecord('patient', pat)"
                                    >
                                        <TableCell class="py-2.5 px-4 font-mono font-bold text-foreground">{{ pat.primary_mrn }}</TableCell>
                                        <TableCell class="py-2.5 px-3 font-bold">{{ pat.first_name }} {{ pat.last_name }}</TableCell>
                                        <TableCell class="py-2.5 px-3 text-muted-foreground">{{ pat.gender }} · {{ pat.age ? `${pat.age}y` : (pat.formatted_dob || formatDate(pat.dob)) }}</TableCell>
                                        <TableCell class="py-2.5 px-3 font-mono font-semibold text-rose-600">{{ pat.blood_group || 'O+' }}</TableCell>
                                        <TableCell class="py-2.5 px-3 font-mono text-[10px]">{{ formatDate(pat.created_at) }}</TableCell>
                                        <TableCell class="py-2.5 px-3">
                                            <AfyaStatusBadge :status="pat.status || 'Active'" />
                                        </TableCell>
                                        <TableCell class="py-2.5 px-4 text-right">
                                            <Link :href="route('patients.show', pat.id)" @click.stop>
                                                <Button variant="default" size="sm" class="h-7 px-3 text-xs font-semibold">
                                                    Patient 360
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: Live Hospital Telemetry & Selected Record Context -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Command Inspector"
                    :icon="Activity"
                    :width="width"
                    @close="close"
                >
                    <!-- RECORD INSPECTOR: Active when user clicks an encounter, invoice, patient, ticket, or rx -->
                    <div v-if="selectedRecord" class="space-y-3 text-xs">
                        
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">
                                Selected {{ selectedRecord.type }}
                            </span>
                            <button 
                                @click="clearSelectedRecord"
                                class="text-[10px] font-semibold text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-0.5"
                            >
                                <X class="w-3 h-3" />
                                <span>Close</span>
                            </button>
                        </div>

                        <!-- 1. Encounter Record Inspector -->
                        <div v-if="selectedRecord.type === 'encounter'" class="space-y-3">
                            <AfyaPatientIdentity v-if="selectedRecord.data.patient" :patient="selectedRecord.data.patient">
                                <AfyaStatusBadge :status="selectedRecord.data.status" dot />
                            </AfyaPatientIdentity>

                            <div class="p-3 rounded-lg bg-card space-y-1.5 shadow-2xs">
                                <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Encounter Details</div>
                                <div class="space-y-1 text-[11px]">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Type:</span>
                                        <span class="font-bold text-foreground font-mono">{{ selectedRecord.data.encounter_type || 'OPD' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Reason:</span>
                                        <span class="font-medium text-foreground truncate max-w-[140px]">{{ selectedRecord.data.reason_for_visit || 'Consultation' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Start Time:</span>
                                        <span class="font-mono text-muted-foreground text-[10px]">{{ selectedRecord.data.start_time || 'In Progress' }}</span>
                                    </div>
                                </div>
                            </div>

                            <Link :href="route('encounters.workspace', selectedRecord.data.id)" class="block pt-1">
                                <Button variant="default" size="sm" class="w-full justify-center gap-1.5 text-xs h-8 shadow-2xs font-semibold">
                                    <Stethoscope class="w-3.5 h-3.5" />
                                    <span>Launch Clinical Charting</span>
                                </Button>
                            </Link>
                        </div>

                        <!-- 2. Invoice Record Inspector -->
                        <div v-else-if="selectedRecord.type === 'invoice'" class="space-y-3">
                            <div class="p-3 rounded-lg bg-card space-y-1.5 shadow-2xs">
                                <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                    <span>Invoice Breakdown</span>
                                    <span class="font-mono text-foreground">{{ selectedRecord.data.invoice_number }}</span>
                                </div>
                                <div class="space-y-1 text-[11px]">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Total:</span>
                                        <span class="font-mono font-bold text-foreground">TZS {{ formatCurrency(selectedRecord.data.total_amount) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Paid:</span>
                                        <span class="font-mono font-bold text-emerald-700 dark:text-emerald-400">TZS {{ formatCurrency(selectedRecord.data.paid_amount) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Status:</span>
                                        <AfyaStatusBadge :status="selectedRecord.data.status" dot />
                                    </div>
                                </div>
                            </div>

                            <Link :href="route('billing.desk')" class="block pt-1">
                                <Button variant="default" size="sm" class="w-full justify-center gap-1.5 text-xs h-8 shadow-2xs font-semibold">
                                    <Receipt class="w-3.5 h-3.5" />
                                    <span>Open in Cashier POS Desk</span>
                                </Button>
                            </Link>
                        </div>

                        <!-- 3. Patient Record Inspector -->
                        <div v-else-if="selectedRecord.type === 'patient'" class="space-y-3">
                            <AfyaPatientIdentity :patient="selectedRecord.data">
                                <AfyaStatusBadge :status="selectedRecord.data.status || 'Active'" />
                            </AfyaPatientIdentity>

                            <Link :href="route('patients.show', selectedRecord.data.id)" class="block pt-1">
                                <Button variant="default" size="sm" class="w-full justify-center gap-1.5 text-xs h-8 shadow-2xs font-semibold">
                                    <ArrowRight class="w-3.5 h-3.5" />
                                    <span>Open Full Patient 360</span>
                                </Button>
                            </Link>
                        </div>

                        <!-- 4. Queue Ticket Inspector -->
                        <div v-else-if="selectedRecord.type === 'ticket'" class="space-y-3">
                            <div class="p-3 rounded-lg bg-card space-y-1.5 shadow-2xs">
                                <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                    <span>Triage Ticket</span>
                                    <span class="font-mono font-bold text-primary">{{ selectedRecord.data.ticket_number }}</span>
                                </div>
                                <div class="space-y-1 text-[11px]">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Service Point:</span>
                                        <span class="font-bold text-foreground">{{ selectedRecord.data.service_point }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Priority:</span>
                                        <span class="font-bold text-foreground">{{ selectedRecord.data.priority }}</span>
                                    </div>
                                </div>
                            </div>

                            <Link :href="route('queue.index')" class="block pt-1">
                                <Button variant="default" size="sm" class="w-full justify-center gap-1.5 text-xs h-8 shadow-2xs font-semibold">
                                    <PhoneCall class="w-3.5 h-3.5" />
                                    <span>Call Patient Now</span>
                                </Button>
                            </Link>
                        </div>

                        <!-- 5. Appointment / Rx Inspector -->
                        <div v-else class="space-y-3">
                            <div class="p-3 rounded-lg bg-card space-y-1.5 shadow-2xs">
                                <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Record Details</div>
                                <div class="text-xs text-foreground font-medium">Selected Item Loaded</div>
                            </div>
                        </div>
                    </div>

                    <!-- DEFAULT STATE: Live Hospital Operations Pulse (When nothing is selected) -->
                    <div v-else class="space-y-3 text-xs">
                        
                        <!-- Live Operations Pulse Card -->
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                <span>Hospital Pulse</span>
                                <span class="flex items-center gap-1 text-emerald-700 dark:text-emerald-400 font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Operational
                                </span>
                            </div>
                            
                            <div class="space-y-1.5 text-[11px]">
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Triage Queue:</span>
                                    <span class="font-mono font-bold" :class="metrics.queue_waiting > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400'">
                                        {{ metrics.queue_waiting }} Waiting
                                    </span>
                                </div>
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Pharmacy Load:</span>
                                    <span class="font-mono font-bold text-sky-800 dark:text-sky-400">
                                        {{ metrics.pending_pharmacy }} Pending
                                    </span>
                                </div>
                                <div class="flex items-center justify-between border-b border-border/40 pb-1">
                                    <span class="text-muted-foreground">Unpaid Invoices:</span>
                                    <span class="font-mono font-bold" :class="metrics.unpaid_invoices > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400'">
                                        {{ metrics.unpaid_invoices }} Invoices
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Ledger State:</span>
                                    <span class="font-bold text-emerald-700 dark:text-emerald-400 text-[10px]">Balanced</span>
                                </div>
                            </div>
                        </div>

                        <!-- Fast Hospital Navigation Shortcuts -->
                        <div class="p-3 rounded-lg bg-card space-y-2 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                Workstation Hub
                            </div>
                            <div class="space-y-1.5">
                                <Link :href="route('workspace.clinical')" class="block">
                                    <Button variant="outline" size="sm" class="w-full justify-start gap-2 text-xs h-8 bg-card shadow-2xs font-semibold">
                                        <Stethoscope class="w-3.5 h-3.5 text-primary" />
                                        <span>Clinical Workstation</span>
                                    </Button>
                                </Link>
                                <Link :href="route('billing.desk')" class="block">
                                    <Button variant="outline" size="sm" class="w-full justify-start gap-2 text-xs h-8 bg-card shadow-2xs font-semibold">
                                        <Receipt class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                        <span>Cashier POS Desk</span>
                                    </Button>
                                </Link>
                                <Link :href="route('pharmacy.queue')" class="block">
                                    <Button variant="outline" size="sm" class="w-full justify-start gap-2 text-xs h-8 bg-card shadow-2xs font-semibold">
                                        <Pill class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" />
                                        <span>Dispensary & Queue</span>
                                    </Button>
                                </Link>
                                <Link :href="route('queue.index')" class="block">
                                    <Button variant="outline" size="sm" class="w-full justify-start gap-2 text-xs h-8 bg-card shadow-2xs font-semibold">
                                        <Clock class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                                        <span>Triage Desk</span>
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
