<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { 
    Clock, 
    Stethoscope, 
    FlaskConical, 
    Pill, 
    Activity, 
    ArrowRight, 
    ArrowUpRight, 
    Users, 
    CheckCircle2,
    Calendar,
    ArrowRightLeft,
    PhoneCall,
    Loader2
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

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    tickets: {
        type: Array,
        default: () => [],
    },
    currentPoint: {
        type: String,
        default: 'Triage',
    },
});

import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const { preferences, openContext } = useWorkspacePreferences();

const activePoint = ref(props.currentPoint);
const selectedTicket = ref(props.tickets?.[0] || null);

const callingTicketId = ref(null);
const transferringPoint = ref(null);

const servicePoints = [
    { id: 'Triage', label: 'Triage & Vitals Desk', icon: Activity },
    { id: 'Doctor', label: 'Doctor Consultation', icon: Stethoscope },
    { id: 'Lab', label: 'Laboratory Service', icon: FlaskConical },
    { id: 'Pharmacy', label: 'Pharmacy Dispensary', icon: Pill },
];

const refreshQueue = (point) => {
    activePoint.value = point;
    router.get(route('queue.index'), { point: point }, { preserveState: true });
};

const selectTicket = (ticket) => {
    selectedTicket.value = ticket;
    openContext();
};

const callPatient = (ticketId) => {
    callingTicketId.value = ticketId;
    router.post(route('queue.call', ticketId), {}, {
        onFinish: () => {
            callingTicketId.value = null;
        }
    });
};

const transferPatient = (ticketId, nextPoint) => {
    transferringPoint.value = nextPoint;
    router.post(route('queue.transfer', ticketId), {
        next_service_point: nextPoint,
    }, {
        onFinish: () => {
            transferringPoint.value = null;
        }
    });
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
                        :active="activePoint === sp.id"
                        :collapsed="state === 'collapsed'"
                        @click="refreshQueue(sp.id)"
                    />

                    <div v-if="state !== 'collapsed'" class="pt-3 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Patient Inflow
                    </div>
                    <AfyaSidebarItem
                        label="Appointments Calendar"
                        :icon="Calendar"
                        :collapsed="state === 'collapsed'"
                        :href="route('appointments.index')"
                    />
                    <AfyaSidebarItem
                        label="Master Patient Index"
                        :icon="Users"
                        :collapsed="state === 'collapsed'"
                        :href="route('patients.index')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Live Queue Table (Full Width) -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Scheduling', href: route('appointments.index') },
                        { label: `${activePoint} Queue`, active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400 animate-pulse"></span>
                                <span>{{ tickets.length }} Patients In Queue</span>
                            </span>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        <div class="w-full bg-card rounded-md overflow-hidden shadow-2xs flex flex-col border border-border/60">
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                        <TableHead class="py-1 px-3 w-28">Ticket #</TableHead>
                                        <TableHead class="py-1 px-3">Patient Details</TableHead>
                                        <TableHead class="py-1 px-3 text-center w-24">Priority</TableHead>
                                        <TableHead class="py-1 px-3 text-center w-24">Joined At</TableHead>
                                        <TableHead class="py-1 px-3 text-center w-24">Status</TableHead>
                                        <TableHead class="py-1 px-3 text-right w-32">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="ticket in tickets"
                                        :key="ticket.id"
                                        :selected="selectedTicket?.id === ticket.id"
                                        class="h-9 cursor-pointer hover:bg-muted/30 border-b border-border/30 transition-colors"
                                        :class="{ 'bg-primary/5': selectedTicket?.id === ticket.id }"
                                        @click="selectTicket(ticket)"
                                    >
                                        <TableCell class="py-1 px-3 font-mono font-bold text-foreground text-xs w-28">
                                            {{ ticket.ticket_number }}
                                        </TableCell>
                                        <TableCell class="py-1 px-3">
                                            <div class="font-bold text-foreground text-[11px]">{{ ticket.patient?.first_name }} {{ ticket.patient?.last_name }}</div>
                                            <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ ticket.patient?.primary_mrn }}</div>
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-center w-24">
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
                                        <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground text-center w-24">
                                            {{ new Date(ticket.joined_queue_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-center w-24">
                                            <AfyaStatusBadge :status="ticket.status" dot />
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-right w-32">
                                            <Button
                                                v-if="can.call"
                                                variant="default"
                                                size="sm"
                                                class="h-5 px-2 text-[9.5px] font-semibold gap-1"
                                                :disabled="callingTicketId === ticket.id"
                                                @click.stop="callPatient(ticket.id)"
                                            >
                                                <Loader2 v-if="callingTicketId === ticket.id" class="w-3 h-3 animate-spin" />
                                                <PhoneCall v-else class="w-2.5 h-2.5" />
                                                <span>{{ callingTicketId === ticket.id ? 'Calling...' : 'Call & Chart' }}</span>
                                            </Button>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="tickets.length === 0">
                                        <TableCell colspan="6" class="text-center py-12 text-muted-foreground">
                                            <Clock class="w-8 h-8 mx-auto mb-2 text-muted-foreground opacity-50" />
                                            <p class="font-semibold text-foreground text-xs">No patients waiting at {{ activePoint }} desk</p>
                                            <p class="text-[11px]">Patients routed to this station will appear here automatically.</p>
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
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="text-center py-10 text-muted-foreground">
                        Select a queue ticket to inspect.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
