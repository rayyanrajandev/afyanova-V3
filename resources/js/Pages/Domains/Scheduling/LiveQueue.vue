<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Clock, 
    Stethoscope, 
    FlaskConical, 
    Pill, 
    Activity, 
    CreditCard,
    ArrowRight, 
    Users, 
    Calendar,
    ArrowRightLeft,
    PhoneCall,
    Loader2,
    Syringe,
    Plus,
    X,
    Sparkles,
    ConciergeBell,
    Layers,
    Printer,
    Lock
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';
import AfyaCheckInModal from '@/Components/Afya/AfyaCheckInModal.vue';
import QueueTicketPrint from '@/Components/Print/QueueTicketPrint.vue';
import { useHospitalAudio } from '@/Composables/useHospitalAudio';

// UI Primitives & Design Foundation
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
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

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    tickets: {
        type: Array,
        default: () => [],
    },
    patients: {
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
    currentPoint: {
        type: String,
        default: 'Triage',
    },
    pointCounts: {
        type: Object,
        default: () => ({}),
    },
});

import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const { preferences, openContext } = useWorkspacePreferences();

const activePoint = ref(props.currentPoint);
const selectedTicket = ref(props.tickets?.[0] || null);

const callingTicketId = ref(null);
const transferringPoint = ref(null);
const printingTicket = ref(null);
const { playQueueCallChime } = useHospitalAudio();

const formatWaitTime = (joinedAt) => {
    if (!joinedAt) return '';
    const joined = new Date(joinedAt);
    const now = new Date();
    const diffMs = now - joined;
    const diffMins = Math.max(0, Math.floor(diffMs / (1000 * 60)));
    if (diffMins < 1) return 'Just joined';
    if (diffMins < 60) return `${diffMins}m wait`;
    const hours = Math.floor(diffMins / 60);
    const mins = diffMins % 60;
    return `${hours}h ${mins}m wait`;
};

// Unified Check-In Modal State
const showCheckInModal = ref(false);
const defaultCheckInServicePoint = ref('Triage');
const defaultCheckInVisitType = ref('OPD Consultation');

const openCheckInModal = (opt = null) => {
    if (opt) {
        defaultCheckInServicePoint.value = opt.servicePoint || opt.id || 'Triage';
        defaultCheckInVisitType.value = opt.visitType || 'OPD Consultation';
    } else {
        defaultCheckInServicePoint.value = 'Triage';
        defaultCheckInVisitType.value = 'OPD Consultation';
    }
    showCheckInModal.value = true;
};

const getServiceSummary = (ticket) => {
    if (ticket.encounter?.lab_orders?.[0]?.items?.length) {
        const names = ticket.encounter.lab_orders[0].items
            .map(i => i.lab_test?.name || 'Lab Test')
            .join(', ');
        return names.length > 35 ? names.substring(0, 32) + '...' : names;
    }
    if (ticket.encounter?.procedure_orders?.[0]?.catalog?.name) {
        return ticket.encounter.procedure_orders[0].catalog.name;
    }
    if (ticket.encounter?.reason_for_visit) {
        return ticket.encounter.reason_for_visit;
    }
    return ticket.current_service_point + ' Service';
};

const getPaymentBadge = (ticket) => {
    if (ticket.encounter?.encounter_type === 'Treatment_Followup' || ticket.encounter?.encounter_type === 'Injection Revisit') {
        return {
            label: 'Prepaid Course',
            classes: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
            dot: 'bg-blue-500',
        };
    }
    if (ticket.patient?.policies?.some(p => ['Active', 'Verified'].includes(p.status))) {
        const insurer = ticket.patient.policies[0]?.insurance_company?.name || 'Insurance';
        return {
            label: `${insurer} Approved`,
            classes: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
            dot: 'bg-emerald-500',
        };
    }
    const invoices = ticket.encounter?.invoices || [];
    if (invoices.length > 0) {
        const total = invoices.reduce((sum, inv) => sum + (parseFloat(inv.total_amount) || 0), 0);
        const paid = invoices.reduce((sum, inv) => sum + (parseFloat(inv.paid_amount) || 0), 0);
        if (paid >= total && total > 0) {
            return {
                label: `Paid (TZS ${total.toLocaleString()})`,
                classes: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                dot: 'bg-emerald-500',
            };
        }
        if (total > paid) {
            const due = total - paid;
            return {
                label: `TZS ${due.toLocaleString()} Due at Cashier`,
                classes: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800 font-bold',
                dot: 'bg-rose-500 animate-pulse',
            };
        }
    }
    if (ticket.current_service_point === 'Doctor' || ticket.current_service_point === 'Triage') {
        return {
            label: 'OPD File',
            classes: 'bg-muted text-muted-foreground border-border/60',
            dot: 'bg-slate-400',
        };
    }
    return {
        label: 'Standard Route',
        classes: 'bg-muted text-muted-foreground border-border/60',
        dot: 'bg-slate-400',
    };
};

const directServiceOptions = [
    {
        id: 'Triage',
        servicePoint: 'Triage',
        visitType: 'OPD Consultation',
        title: 'OPD Consultation',
        subtitle: 'General OPD, specialist clinic & doctor assessment',
        icon: Stethoscope,
        badge: 'Doctor & Vitals',
        color: 'text-amber-600 dark:text-amber-400',
        border: 'border-amber-300 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-950/20',
    },
    {
        id: 'Procedure',
        servicePoint: 'Procedure',
        visitType: 'Procedure',
        title: 'Injection & Dressing',
        subtitle: 'Chumba cha Sindano na Vidonda (Minor Care, Dressings, Injections)',
        icon: Syringe,
        badge: 'Chumba cha Sindano',
        color: 'text-rose-600 dark:text-rose-400',
        border: 'border-rose-300 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-950/20',
    },
    {
        id: 'Lab',
        servicePoint: 'Lab',
        visitType: 'Direct_Lab',
        title: 'Direct Lab',
        subtitle: 'Self-Request, Pre-marital, Wellness, Food Handler Fitness',
        icon: FlaskConical,
        badge: 'Direct Lab',
        color: 'text-purple-600 dark:text-purple-400',
        border: 'border-purple-300 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-950/20',
    },
    {
        id: 'Pharmacy',
        servicePoint: 'Pharmacy',
        visitType: 'Pharmacy_OTC',
        title: 'Pharmacy OTC',
        subtitle: 'Over-The-Counter General Sales / Refill without Doctor Visit',
        icon: Pill,
        badge: 'Direct OTC',
        color: 'text-emerald-600 dark:text-emerald-400',
        border: 'border-emerald-300 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20',
    },
];

const servicePoints = [
    { id: 'All', label: 'All Active Queues', icon: Layers },
    { id: 'Triage', label: 'Triage & Vitals Desk', icon: Activity },
    { id: 'Doctor', label: 'Doctor Consultation', icon: Stethoscope },
    { id: 'Procedure', label: 'Injection & Dressing Room', icon: Syringe },
    { id: 'Lab', label: 'Laboratory Service', icon: FlaskConical },
    { id: 'Pharmacy', label: 'Pharmacy Dispensary', icon: Pill },
    { id: 'Cashier', label: 'Cashier & Billing Desk', icon: CreditCard },
];

const refreshQueue = (point) => {
    activePoint.value = point;
    router.get(route('queue.index'), { point: point }, { preserveState: true });
};

const selectTicket = (ticket) => {
    selectedTicket.value = ticket;
    openContext();
};

const isTicketPaymentLocked = (ticket) => {
    if (!ticket) return false;
    // 1. Emergency STAT priority bypasses cashier payment lock
    if (ticket.priority === 'Emergency' || ticket.priority === 'STAT') return false;
    // 2. Insured patients (NHIF / Private) bypass cash payment lock
    if (ticket.patient?.policies?.some(p => ['Active', 'Verified'].includes(p.status))) return false;
    // 3. Triage, Doctor, and Cashier desks do not block upfront direct service payment
    if (ticket.current_service_point === 'Cashier' || ticket.current_service_point === 'Triage' || ticket.current_service_point === 'Doctor') return false;

    // 4. Direct service desks (Procedure, Lab, Pharmacy) check encounter invoices
    const invoices = ticket.encounter?.invoices || [];
    if (invoices.length === 0) return false;

    const total = invoices.reduce((sum, inv) => sum + (parseFloat(inv.total_amount) || 0), 0);
    const paid = invoices.reduce((sum, inv) => sum + (parseFloat(inv.paid_amount) || 0), 0);
    return total > paid;
};

const getUnpaidBalance = (ticket) => {
    const invoices = ticket?.encounter?.invoices || [];
    const total = invoices.reduce((sum, inv) => sum + (parseFloat(inv.total_amount) || 0), 0);
    const paid = invoices.reduce((sum, inv) => sum + (parseFloat(inv.paid_amount) || 0), 0);
    return Math.max(0, total - paid);
};

const canUserCallTicket = (ticket) => {
    if (!props.can?.call) return false;
    if (ticket.status !== 'Waiting') return false;
    if (isTicketPaymentLocked(ticket)) return false;

    if (ticket.current_service_point === 'Doctor') return !!props.can.canCallDoctor;
    if (ticket.current_service_point === 'Triage') return !!props.can.canCallTriage;
    if (ticket.current_service_point === 'Procedure') return !!props.can.canCallProcedure;
    if (ticket.current_service_point === 'Lab') return !!props.can.canCallLab;
    if (ticket.current_service_point === 'Pharmacy') return !!props.can.canCallPharmacy;
    if (ticket.current_service_point === 'Cashier') return !!props.can.canCallCashier;

    return false;
};

const getCallButtonLabel = (ticket) => {
    if (callingTicketId.value === ticket.id) return 'Calling...';
    if (ticket.current_service_point === 'Doctor') return 'Call & Chart';
    if (ticket.current_service_point === 'Procedure') return 'Call to Treatment Room';
    if (ticket.current_service_point === 'Triage') return 'Call to Triage';
    if (ticket.current_service_point === 'Cashier') return 'Call to Cashier';
    if (ticket.current_service_point === 'Lab') return 'Call to Lab';
    if (ticket.current_service_point === 'Pharmacy') return 'Call to Pharmacy';
    return 'Call Patient';
};

const callPatient = (ticketId) => {
    playQueueCallChime();
    callingTicketId.value = ticketId;
    router.post(route('queue.call', ticketId), {}, {
        onFinish: () => {
            callingTicketId.value = null;
        }
    });
};

const showTransferModal = ref(false);
const ticketToTransfer = ref(null);
const targetServicePoint = ref('');
const transferReason = ref('Clinical care pathway progression');
const transferNotes = ref('');
const isSubmittingTransfer = ref(false);

const transferReasons = [
    { id: 'Clinical care pathway progression', label: 'Clinical care pathway progression (e.g. Triage -> Doctor -> Lab/Pharmacy)' },
    { id: 'Front desk routing correction', label: 'Front desk routing correction (Clerical error at check-in)' },
    { id: 'Patient requested additional consult', label: 'Patient requested additional consult/service' },
    { id: 'Clinical escalation / Emergency triage', label: 'Clinical escalation / Emergency triage' },
    { id: 'Doctor direct order / referral', label: 'Doctor direct order / referral' },
    { id: 'Other / Custom clinical rationale', label: 'Other (specify in notes)' },
];

const openTransferModal = (ticket, targetPoint) => {
    ticketToTransfer.value = ticket;
    targetServicePoint.value = targetPoint;
    transferReason.value = 'Clinical care pathway progression';
    transferNotes.value = '';
    showTransferModal.value = true;
};

const confirmAndExecuteTransfer = () => {
    if (!ticketToTransfer.value || !targetServicePoint.value) return;

    isSubmittingTransfer.value = true;
    const finalReason = transferNotes.value 
        ? `${transferReason.value}: ${transferNotes.value}` 
        : transferReason.value;

    router.post(route('queue.transfer', ticketToTransfer.value.id), {
        next_service_point: targetServicePoint.value,
        reason: finalReason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showTransferModal.value = false;
            ticketToTransfer.value = null;
            targetServicePoint.value = '';
            transferNotes.value = '';
        },
        onFinish: () => {
            isSubmittingTransfer.value = false;
        }
    });
};

const transferPatient = (ticketId, nextPoint) => {
    const t = props.tickets?.find(item => item.id === ticketId) || selectedTicket.value;
    if (t) {
        openTransferModal(t, nextPoint);
    }
};

// Keyboard Traversal (Gap 2.3)
const handleTableKeydown = (e) => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
    if (!props.tickets || props.tickets.length === 0) return;

    const currentIndex = props.tickets.findIndex(t => t.id === selectedTicket.value?.id);

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        const nextIndex = Math.min(currentIndex + 1, props.tickets.length - 1);
        selectTicket(props.tickets[nextIndex]);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prevIndex = Math.max(currentIndex - 1, 0);
        selectTicket(props.tickets[prevIndex]);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedTicket.value && selectedTicket.value.status === 'Waiting') {
            callPatient(selectedTicket.value.id);
        }
    }
};

onMounted(() => window.addEventListener('keydown', handleTableKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleTableKeydown));
</script>

<template>
    <Head title="Live Queue & Triage Desk — AfyaNova Workstation" />

    <AfyaShell active-module="scheduling">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            <!-- 1. LEFT SIDEBAR: Service Points Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Service Points"
                    :icon="Clock"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Live Hospital Routing
                    </div>
                    <AfyaSidebarItem
                        v-for="sp in servicePoints"
                        :key="sp.id"
                        :label="sp.label"
                        :icon="sp.icon"
                        :badge="pointCounts[sp.id] > 0 ? pointCounts[sp.id] : null"
                        :active="activePoint === sp.id"
                        :collapsed="state === 'collapsed'"
                        @click="refreshQueue(sp.id)"
                    />

                    <template v-if="can.checkInDirect || can.isReceptionist">
                        <div v-if="state !== 'collapsed'" class="pt-3 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border mt-2">
                            Reception Operations
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
                            :collapsed="state === 'collapsed'"
                            :href="route('patients.index')"
                        />
                        <AfyaSidebarItem
                            label="Appointments Calendar"
                            :icon="Calendar"
                            :collapsed="state === 'collapsed'"
                            :href="route('appointments.index')"
                        />
                    </template>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Live Queue Table (Full Width) -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        ...(can.isReceptionist ? [{ label: 'Front Desk', href: route('dashboard') }] : []),
                        { label: 'Live Queue & Triage', href: route('queue.index') },
                        { label: activePoint === 'All' ? 'All Active Queues' : `${activePoint} Desk`, active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400 animate-pulse"></span>
                                <span>{{ tickets.length }} Patients In Queue</span>
                            </span>

                            <Button
                                v-if="can.checkInDirect"
                                variant="default"
                                size="sm"
                                class="h-7 px-2.5 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="openCheckInModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Direct Walk-in / Revisit</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        <!-- Direct Service Quick Routing Pills (Reception Fast Track - Only visible to staff with check-in permission) -->
                        <div v-if="can.checkInDirect" class="flex flex-wrap items-center gap-2 p-2 bg-card rounded-lg shadow-2xs border border-border/50">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground pl-1 flex items-center gap-1.5 shrink-0">
                                <Sparkles class="w-3.5 h-3.5 text-primary" />
                                Direct Walk-in Routing:
                            </span>
                            <Button
                                v-for="opt in directServiceOptions"
                                :key="opt.title"
                                variant="outline"
                                class="h-7 px-2.5 text-xs gap-1.5 hover:border-primary/50 transition-all shadow-2xs inline-flex items-center"
                                @click="openCheckInModal(opt)"
                            >
                                <component :is="opt.icon" class="w-3.5 h-3.5 shrink-0" :class="opt.color" />
                                <span class="font-medium leading-none">{{ opt.title }}</span>
                                <span class="inline-flex items-center text-[9.5px] px-1 py-0.2 rounded font-semibold bg-muted text-muted-foreground leading-none border border-border/40 shrink-0">{{ opt.badge }}</span>
                            </Button>
                        </div>

                        <div class="w-full bg-card rounded-md overflow-hidden shadow-2xs flex flex-col border border-border/60">
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                        <TableHead class="py-1 px-3 w-40">Ticket #</TableHead>
                                        <TableHead class="py-1 px-3">Patient & Service Details</TableHead>
                                        <TableHead class="py-1 px-3 w-44">Payment Clearance</TableHead>
                                        <TableHead class="py-1 px-3 text-center w-20">Priority</TableHead>
                                        <TableHead class="py-1 px-3 text-center w-24">Wait Time</TableHead>
                                        <TableHead class="py-1 px-3 text-center w-20">Status</TableHead>
                                        <TableHead class="py-1 px-3 text-right whitespace-nowrap min-w-[190px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="ticket in tickets"
                                        :key="ticket.id"
                                        :selected="selectedTicket?.id === ticket.id"
                                        class="h-10 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                        :class="{ 'bg-primary/5': selectedTicket?.id === ticket.id }"
                                        @click="selectTicket(ticket)"
                                    >
                                        <TableCell class="py-1 px-3 font-mono font-bold text-foreground text-xs w-40">
                                            <div class="flex items-center gap-1.5">
                                                <span>{{ ticket.ticket_number }}</span>
                                                <span 
                                                    v-if="activePoint === 'All'" 
                                                    class="px-1.5 py-0.2 rounded text-[8.5px] font-bold uppercase tracking-wider"
                                                    :class="{
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-300': ticket.current_service_point === 'Procedure',
                                                        'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300 border border-purple-300': ticket.current_service_point === 'Lab',
                                                        'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-300': ticket.current_service_point === 'Triage',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-300': ticket.current_service_point === 'Doctor',
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-300': ticket.current_service_point === 'Pharmacy',
                                                        'bg-slate-100 text-slate-800 dark:bg-slate-950/40 dark:text-slate-300 border border-slate-300': ticket.current_service_point === 'Cashier',
                                                    }"
                                                >
                                                    {{ ticket.current_service_point }}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="py-1 px-3">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-foreground text-[11px]">{{ ticket.patient?.first_name }} {{ ticket.patient?.last_name }}</span>
                                                <span class="text-[9.5px] font-mono text-muted-foreground">({{ ticket.patient?.primary_mrn }})</span>
                                            </div>
                                            <div class="text-[10px] text-muted-foreground flex items-center gap-1 truncate max-w-sm">
                                                <span class="font-medium text-foreground/90">{{ getServiceSummary(ticket) }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="py-1 px-3 w-44">
                                            <span 
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9.5px] font-semibold border"
                                                :class="getPaymentBadge(ticket).classes"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full" :class="getPaymentBadge(ticket).dot"></span>
                                                <span>{{ getPaymentBadge(ticket).label }}</span>
                                            </span>
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-center w-20">
                                            <span 
                                                class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                :class="{
                                                    'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800': ticket.priority === 'Emergency',
                                                    'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800': ticket.priority === 'Urgent',
                                                    'bg-muted text-foreground': ticket.priority === 'Normal' || !ticket.priority,
                                                }"
                                            >
                                                {{ ticket.priority || 'Normal' }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-center w-24">
                                            <div class="font-mono text-[10px] font-semibold text-foreground">
                                                {{ new Date(ticket.joined_queue_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                            </div>
                                            <div class="text-[9px] text-muted-foreground font-medium">
                                                {{ formatWaitTime(ticket.joined_queue_at) }}
                                            </div>
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-center w-20">
                                            <AfyaStatusBadge :status="ticket.status" dot />
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-right whitespace-nowrap min-w-[190px]">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-6 w-6 text-muted-foreground hover:text-foreground shrink-0"
                                                    title="Print Paper Ticket"
                                                    @click.stop="printingTicket = ticket"
                                                >
                                                    <Printer class="w-3 h-3" />
                                                </Button>

                                                <!-- 1. WAITING STATE: Show Locked button if unpaid, Call button if cleared, or Route button -->
                                                <template v-if="ticket.status === 'Waiting'">
                                                    <!-- Payment Locked State: Cashier Payment Required -->
                                                    <Button
                                                        v-if="isTicketPaymentLocked(ticket)"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10px] font-bold gap-1 text-rose-700 bg-rose-50/90 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800 cursor-not-allowed opacity-90 shadow-2xs"
                                                        :title="`Payment required at Cashier Desk (TZS ${getUnpaidBalance(ticket).toLocaleString()}) before calling patient.`"
                                                        disabled
                                                    >
                                                        <Lock class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400" />
                                                        <span>Due at Cashier</span>
                                                    </Button>

                                                    <!-- Clinician / Staff authorized to call this desk (Cleared) -->
                                                    <Button
                                                        v-else-if="canUserCallTicket(ticket)"
                                                        variant="default"
                                                        size="sm"
                                                        class="h-6 px-2.5 text-[10.5px] font-semibold gap-1 shadow-2xs"
                                                        :disabled="callingTicketId === ticket.id"
                                                        @click.stop="callPatient(ticket.id)"
                                                    >
                                                        <Loader2 v-if="callingTicketId === ticket.id" class="w-3 h-3 animate-spin" />
                                                        <PhoneCall v-else class="w-3 h-3" />
                                                        <span>{{ getCallButtonLabel(ticket) }}</span>
                                                    </Button>

                                                    <!-- Staff without call permission on this desk: Route button if permitted -->
                                                    <Button
                                                        v-else-if="can.transfer"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10px] font-semibold gap-1 border-border/80 hover:border-primary/60"
                                                        @click.stop="selectTicket(ticket)"
                                                    >
                                                        <ArrowRightLeft class="w-3 h-3 text-muted-foreground" />
                                                        <span>Route</span>
                                                    </Button>

                                                    <!-- Read-only waiting label -->
                                                    <span v-else class="text-[10px] text-muted-foreground italic px-1">
                                                        Waiting
                                                    </span>
                                                </template>

                                                <!-- 2. IN PROGRESS STATE: Patient is already called, show direct shortcut to active desk -->
                                                <template v-else-if="ticket.status === 'In Progress' || ticket.status === 'In_Progress'">
                                                    <Link
                                                        v-if="ticket.current_service_point === 'Procedure'"
                                                        :href="route('procedures.workspace')"
                                                        class="inline-flex items-center gap-1 text-[9.5px] font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded border border-rose-200 dark:border-rose-800 hover:bg-rose-100 transition shadow-2xs"
                                                    >
                                                        <Syringe class="w-3 h-3 text-rose-600" />
                                                        <span>Open Procedure</span>
                                                    </Link>
                                                    <Link
                                                        v-else-if="ticket.current_service_point === 'Doctor'"
                                                        :href="ticket.encounter_id ? route('encounters.workspace', ticket.encounter_id) : route('workspace.clinical')"
                                                        class="inline-flex items-center gap-1 text-[9.5px] font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800 hover:bg-amber-100 transition shadow-2xs"
                                                    >
                                                        <Stethoscope class="w-3 h-3 text-amber-600" />
                                                        <span>Open Consult</span>
                                                    </Link>
                                                    <Link
                                                        v-else-if="ticket.current_service_point === 'Triage'"
                                                        :href="route('workspace.clinical', { tab: 'vitals', patient_id: ticket.patient_id })"
                                                        class="inline-flex items-center gap-1 text-[9.5px] font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 rounded border border-blue-200 dark:border-blue-800 hover:bg-blue-100 transition shadow-2xs"
                                                    >
                                                        <Activity class="w-3 h-3 text-blue-600" />
                                                        <span>Record Vitals</span>
                                                    </Link>
                                                    <Link
                                                        v-else-if="ticket.current_service_point === 'Pharmacy'"
                                                        :href="route('pharmacy.queue')"
                                                        class="inline-flex items-center gap-1 text-[9.5px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 transition shadow-2xs"
                                                    >
                                                        <Pill class="w-3 h-3 text-emerald-600" />
                                                        <span>Open Dispense</span>
                                                    </Link>
                                                    <Link
                                                        v-else-if="ticket.current_service_point === 'Lab'"
                                                        :href="route('laboratory.workspace')"
                                                        class="inline-flex items-center gap-1 text-[9.5px] font-bold text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/40 px-2 py-0.5 rounded border border-purple-200 dark:border-purple-800 hover:bg-purple-100 transition shadow-2xs"
                                                    >
                                                        <FlaskConical class="w-3 h-3 text-purple-600" />
                                                        <span>Open Lab Bench</span>
                                                    </Link>
                                                    <Link
                                                        v-else-if="ticket.current_service_point === 'Cashier'"
                                                        :href="route('billing.desk')"
                                                        class="inline-flex items-center gap-1 text-[9.5px] font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-200 transition shadow-2xs"
                                                    >
                                                        <CreditCard class="w-3 h-3 text-slate-600" />
                                                        <span>Open Billing</span>
                                                    </Link>
                                                    <span
                                                        v-else
                                                        class="inline-flex items-center gap-1 text-[9.5px] font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800"
                                                    >
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                        <span>In Service</span>
                                                    </span>
                                                </template>

                                                <!-- 3. COMPLETED OR OTHER STATE -->
                                                <span v-else class="text-[10px] text-muted-foreground">
                                                    {{ ticket.status }}
                                                </span>
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="tickets.length === 0">
                                        <TableCell colspan="7" class="text-center py-12 text-muted-foreground">
                                            <Clock class="w-8 h-8 mx-auto mb-2 text-muted-foreground opacity-50" />
                                            <p class="font-semibold text-foreground text-xs">
                                                No patients waiting {{ activePoint === 'All' ? 'in any hospital department' : `at ${activePoint} desk` }}
                                            </p>
                                            <p class="text-[11px]">Patients routed to service stations will appear here automatically.</p>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: Ticket & Transfer Inspector -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Queue Ticket Context"
                    :icon="Clock"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedTicket" class="space-y-3 text-xs">
                        <AfyaPatientIdentity v-if="selectedTicket.patient" :patient="selectedTicket.patient">
                            <AfyaStatusBadge :status="selectedTicket.status" dot />
                        </AfyaPatientIdentity>

                        <Card>
                            <CardHeader><CardTitle>Ticket Information</CardTitle></CardHeader>
                            <CardContent class="space-y-1.5 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Ticket Number:</span>
                                    <span class="font-mono font-bold text-foreground text-sm">{{ selectedTicket.ticket_number }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Service Point:</span>
                                    <span class="font-bold text-foreground">{{ selectedTicket.current_service_point }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Priority:</span>
                                    <span class="font-bold">{{ selectedTicket.priority || 'Normal' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Joined At:</span>
                                    <span class="font-mono">{{ new Date(selectedTicket.joined_queue_at).toLocaleTimeString() }}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Quick Service Transfer Actions -->
                        <Card v-if="can.transfer">
                            <CardHeader><CardTitle>Transfer Patient</CardTitle></CardHeader>
                            <CardContent class="space-y-1.5">
                                <Button
                                    v-if="selectedTicket.current_service_point !== 'Triage'"
                                    variant="outline"
                                    size="sm"
                                    class="w-full justify-start gap-1.5"
                                    :disabled="transferringPoint === 'Triage'"
                                    @click="transferPatient(selectedTicket.id, 'Triage')"
                                >
                                    <Loader2 v-if="transferringPoint === 'Triage'" class="w-3.5 h-3.5 animate-spin" />
                                    <Activity v-else class="w-3.5 h-3.5" />
                                    <span>Transfer to Triage</span>
                                </Button>
                                <Button
                                    v-if="selectedTicket.current_service_point !== 'Doctor'"
                                    variant="outline"
                                    size="sm"
                                    class="w-full justify-start gap-1.5"
                                    :disabled="transferringPoint === 'Doctor'"
                                    @click="transferPatient(selectedTicket.id, 'Doctor')"
                                >
                                    <Loader2 v-if="transferringPoint === 'Doctor'" class="w-3.5 h-3.5 animate-spin" />
                                    <Stethoscope v-else class="w-3.5 h-3.5" />
                                    <span>Transfer to Doctor</span>
                                </Button>
                                <Button
                                    v-if="selectedTicket.current_service_point !== 'Procedure'"
                                    variant="outline"
                                    size="sm"
                                    class="w-full justify-start gap-1.5"
                                    :disabled="transferringPoint === 'Procedure'"
                                    @click="transferPatient(selectedTicket.id, 'Procedure')"
                                >
                                    <Loader2 v-if="transferringPoint === 'Procedure'" class="w-3.5 h-3.5 animate-spin" />
                                    <Syringe v-else class="w-3.5 h-3.5" />
                                    <span>Transfer to Injection & Dressing Desk</span>
                                </Button>
                                <Button
                                    v-if="selectedTicket.current_service_point !== 'Lab'"
                                    variant="outline"
                                    size="sm"
                                    class="w-full justify-start gap-1.5"
                                    :disabled="transferringPoint === 'Lab'"
                                    @click="transferPatient(selectedTicket.id, 'Lab')"
                                >
                                    <Loader2 v-if="transferringPoint === 'Lab'" class="w-3.5 h-3.5 animate-spin" />
                                    <FlaskConical v-else class="w-3.5 h-3.5" />
                                    <span>Transfer to Lab</span>
                                </Button>
                                <Button
                                    v-if="selectedTicket.current_service_point !== 'Pharmacy'"
                                    variant="outline"
                                    size="sm"
                                    class="w-full justify-start gap-1.5"
                                    :disabled="transferringPoint === 'Pharmacy'"
                                    @click="transferPatient(selectedTicket.id, 'Pharmacy')"
                                >
                                    <Loader2 v-if="transferringPoint === 'Pharmacy'" class="w-3.5 h-3.5 animate-spin" />
                                    <Pill v-else class="w-3.5 h-3.5" />
                                    <span>Transfer to Pharmacy</span>
                                </Button>
                                <Button
                                    v-if="selectedTicket.current_service_point !== 'Cashier'"
                                    variant="outline"
                                    size="sm"
                                    class="w-full justify-start gap-1.5"
                                    :disabled="transferringPoint === 'Cashier'"
                                    @click="transferPatient(selectedTicket.id, 'Cashier')"
                                >
                                    <Loader2 v-if="transferringPoint === 'Cashier'" class="w-3.5 h-3.5 animate-spin" />
                                    <CreditCard v-else class="w-3.5 h-3.5" />
                                    <span>Transfer to Cashier</span>
                                </Button>
                            </CardContent>
                        </Card>

                        <!-- Print Paper Ticket Action -->
                        <Card>
                            <CardContent class="p-2.5">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="w-full gap-1.5 shadow-2xs font-semibold h-8 text-xs border-border/80 hover:bg-muted/40"
                                    @click="printingTicket = selectedTicket"
                                >
                                    <Printer class="w-3.5 h-3.5 text-primary" />
                                    <span>Print 80mm Queue Ticket</span>
                                </Button>
                            </CardContent>
                        </Card>

                        <!-- Cashier Desk Quick Link -->
                        <Card v-if="can.billing && selectedTicket.current_service_point === 'Cashier'">
                            <CardHeader><CardTitle>Billing Actions</CardTitle></CardHeader>
                            <CardContent>
                                <Button
                                    as="a"
                                    :href="route('billing.workspace')"
                                    variant="default"
                                    size="sm"
                                    class="w-full gap-1.5 shadow-2xs font-semibold"
                                >
                                    <CreditCard class="w-3.5 h-3.5" />
                                    <span>Open Cashier POS Desk</span>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="text-center py-10 text-muted-foreground">
                        Select a queue ticket to inspect.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- Unified Check-In & Queue Routing Modal (4xl) -->
        <AfyaCheckInModal
            :show="showCheckInModal"
            :recent-patients="props.patients"
            :active-tickets="props.tickets"
            :lab-tests="props.labTests"
            :procedure-catalogs="props.procedureCatalogs"
            :default-service-point="defaultCheckInServicePoint"
            :default-visit-type="defaultCheckInVisitType"
            @close="showCheckInModal = false"
        />

        <!-- ========================================================================= -->
        <!-- 5. CONFIRM PATIENT RE-ROUTING & TRANSFER MODAL                            -->
        <!-- ========================================================================= -->
        <Modal :show="showTransferModal" max-width="md" @close="showTransferModal = false">
            <div class="p-5 space-y-4">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border/50 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                            <ArrowRightLeft class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-foreground">Confirm Patient Re-Routing</h3>
                            <p class="text-[11px] text-muted-foreground">Transfer queue ticket to a different department</p>
                        </div>
                    </div>
                    <button class="text-muted-foreground hover:text-foreground" @click="showTransferModal = false">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Transfer Route Visualization Box -->
                <div v-if="ticketToTransfer" class="p-3 rounded-lg bg-muted/20 border border-border/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-foreground">
                                {{ ticketToTransfer.patient?.first_name }} {{ ticketToTransfer.patient?.last_name }}
                            </div>
                            <div class="text-[10.5px] font-mono text-muted-foreground">
                                Ticket: {{ ticketToTransfer.ticket_number }} · MRN: {{ ticketToTransfer.patient?.primary_mrn }}
                            </div>
                        </div>
                        <AfyaStatusBadge :status="ticketToTransfer.status" dot />
                    </div>

                    <div class="flex items-center justify-between p-2 rounded-md bg-card border border-border/50 text-xs">
                        <div class="text-center flex-1">
                            <div class="text-[9.5px] text-muted-foreground uppercase font-bold">Current Station</div>
                            <div class="font-bold text-foreground">{{ ticketToTransfer.current_service_point }} Desk</div>
                        </div>
                        <div class="px-2 text-primary font-bold">
                            <ArrowRight class="w-4 h-4 animate-pulse" />
                        </div>
                        <div class="text-center flex-1">
                            <div class="text-[9.5px] text-primary uppercase font-bold">Target Station</div>
                            <div class="font-bold text-primary">{{ targetServicePoint }} Desk</div>
                        </div>
                    </div>
                </div>

                <!-- Clinical Notice / Warning -->
                <div 
                    v-if="ticketToTransfer && (ticketToTransfer.current_service_point === 'Lab' || ticketToTransfer.current_service_point === 'Procedure') && targetServicePoint === 'Triage'"
                    class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-300 flex items-start gap-2"
                >
                    <Activity class="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-600" />
                    <div>
                        <span class="font-bold">Re-routing to Triage:</span> Patient was queued for {{ ticketToTransfer.current_service_point }}. Moving back to Triage will reset their queue priority.
                    </div>
                </div>

                <!-- Reason Selector -->
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-foreground">Reason for Re-Routing</label>
                    <Select
                        v-model="transferReason"
                        class="w-full text-xs h-8 rounded-md border border-input bg-background px-2.5 py-1 text-foreground shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                    >
                        <option v-for="r in transferReasons" :key="r.id" :value="r.id">
                            {{ r.label }}
                        </option>
                    </Select>
                </div>

                <!-- Additional Notes (Optional) -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-foreground">Optional Clinical Note / Rationale</label>
                    <Input
                        v-model="transferNotes"
                        type="text"
                        placeholder="e.g. Patient feeling faint, referred for urgent vitals check"
                        class="text-xs h-8"
                    />
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border/50">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs font-semibold"
                        :disabled="isSubmittingTransfer"
                        @click="showTransferModal = false"
                    >
                        Cancel
                    </Button>

                    <Button
                        variant="default"
                        size="sm"
                        class="h-8 text-xs font-semibold gap-1.5 shadow-2xs"
                        :disabled="isSubmittingTransfer"
                        @click="confirmAndExecuteTransfer"
                    >
                        <Loader2 v-if="isSubmittingTransfer" class="w-3.5 h-3.5 animate-spin" />
                        <ArrowRightLeft v-else class="w-3.5 h-3.5" />
                        <span>Confirm & Re-Route</span>
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- 80mm ESC/POS Queue Ticket Print Modal -->
        <QueueTicketPrint
            v-if="printingTicket"
            :ticket="printingTicket"
            @close="printingTicket = null"
        />
    </AfyaShell>
</template>
