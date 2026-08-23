<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    Calendar as CalendarIcon, 
    Clock, 
    UserCheck, 
    Building2, 
    Users,
    CheckCircle2,
    CalendarCheck,
    Plus,
    X,
    Loader2
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';

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
    appointments: {
        type: Array,
        default: () => [],
    },
});

const selectedAppointment = ref(props.appointments?.[0] || null);
const showContext = ref(true);

// Check-in Modal State
const showCheckInModal = ref(false);
const checkingInApp = ref(null);
const isCheckingIn = ref(false);

const selectAppointment = (app) => {
    selectedAppointment.value = app;
    showContext.value = true;
};

const openCheckInModal = (app) => {
    checkingInApp.value = app;
    showCheckInModal.value = true;
};

const closeCheckInModal = () => {
    showCheckInModal.value = false;
    checkingInApp.value = null;
};

const confirmCheckIn = () => {
    if (!checkingInApp.value) return;
    isCheckingIn.value = true;
    router.post(route('appointments.check-in', checkingInApp.value.id), {}, {
        onFinish: () => {
            isCheckingIn.value = false;
            closeCheckInModal();
        }
    });
};
</script>

<template>
    <Head title="Appointments & Schedule" />

    <AfyaShell active-module="scheduling">
        <AfyaWorkspace :show-sidebar="true" :show-context="showContext">
            <!-- 1. LEFT SIDEBAR: Scheduling Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Scheduling"
                    :icon="CalendarIcon"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Patient Queue
                    </div>
                    <AfyaSidebarItem
                        label="Appointments Calendar"
                        :icon="CalendarIcon"
                        :badge="appointments.length"
                        :active="true"
                        :collapsed="state === 'collapsed'"
                    />
                    <AfyaSidebarItem
                        label="Live Queue & Triage"
                        :icon="Clock"
                        :collapsed="state === 'collapsed'"
                        :href="route('queue.index')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Appointment Schedule Table -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Scheduling', href: route('appointments.index') },
                        { label: 'Appointments Calendar', active: true }
                    ]"
                >
                    <template #actions>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-primary/10 text-primary border border-primary/20">
                            <CalendarCheck class="w-3.5 h-3.5" />
                            <span>{{ appointments.length }} Appointments Today</span>
                        </span>
                    </template>

                    <div class="w-full space-y-4">
                        <div class="w-full">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Time</TableHead>
                                        <TableHead>Patient Details</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead class="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="app in appointments"
                                        :key="app.id"
                                        :selected="selectedAppointment?.id === app.id"
                                        class="cursor-pointer"
                                        @click="selectAppointment(app)"
                                    >
                                        <TableCell class="font-mono font-bold text-foreground">
                                            {{ new Date(app.scheduled_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        </TableCell>
                                        <TableCell>
                                            <div class="font-bold text-foreground">{{ app.patient?.first_name }} {{ app.patient?.last_name }}</div>
                                            <div class="text-[10px] font-mono text-muted-foreground">MRN: {{ app.patient?.primary_mrn }}</div>
                                        </TableCell>
                                        <TableCell class="text-xs text-muted-foreground">
                                            {{ app.provider ? `Dr. ${app.provider.first_name || ''} ${app.provider.last_name || ''}` : 'General OPD' }}
                                        </TableCell>
                                        <TableCell class="text-xs font-semibold">{{ app.appointment_type }}</TableCell>
                                        <TableCell>
                                            <AfyaStatusBadge :status="app.status" dot />
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Button
                                                v-if="app.status === 'Scheduled'"
                                                variant="default"
                                                size="sm"
                                                class="gap-1"
                                                @click.stop="openCheckInModal(app)"
                                            >
                                                <UserCheck class="w-3 h-3" />
                                                <span>Check In</span>
                                            </Button>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="appointments.length === 0">
                                        <TableCell colspan="6" class="text-center py-12 text-muted-foreground">
                                            <CalendarIcon class="w-8 h-8 mx-auto mb-2 text-muted-foreground opacity-50" />
                                            <p class="font-semibold text-foreground text-xs">No appointments scheduled</p>
                                            <p class="text-[11px]">Booked patient appointments will appear here.</p>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: Appointment Inspector -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Appointment Details"
                    :icon="CalendarIcon"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedAppointment" class="space-y-3 text-xs">
                        <AfyaPatientIdentity v-if="selectedAppointment.patient" :patient="selectedAppointment.patient">
                            <AfyaStatusBadge :status="selectedAppointment.status" dot />
                        </AfyaPatientIdentity>

                        <Card>
                            <CardHeader><CardTitle>Booking Summary</CardTitle></CardHeader>
                            <CardContent class="space-y-1.5 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Date:</span>
                                    <span class="font-bold text-foreground">{{ new Date(selectedAppointment.scheduled_time).toLocaleDateString() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Time:</span>
                                    <span class="font-mono">{{ new Date(selectedAppointment.scheduled_time).toLocaleTimeString() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Duration:</span>
                                    <span>{{ selectedAppointment.duration_minutes }} minutes</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Type:</span>
                                    <span class="font-semibold">{{ selectedAppointment.appointment_type }}</span>
                                </div>
                            </CardContent>
                        </Card>

                        <Button
                            v-if="selectedAppointment.status === 'Scheduled'"
                            variant="default"
                            size="sm"
                            class="w-full justify-center gap-1.5"
                            @click="openCheckInModal(selectedAppointment)"
                        >
                            <UserCheck class="w-3.5 h-3.5" />
                            <span>Check In Patient & Route</span>
                        </Button>
                    </div>

                    <div v-else class="text-center py-10 text-muted-foreground">
                        Select an appointment to inspect.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- Check-In Accessible Modal Dialog -->
        <Modal :show="showCheckInModal" max-width="md" @close="closeCheckInModal">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <UserCheck class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Confirm Patient Check-In</h3>
                    </div>
                    <button @click="closeCheckInModal" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="checkingInApp" class="space-y-3 text-xs">
                    <div class="p-3 bg-muted/50 rounded border border-border space-y-1.5">
                        <div class="flex justify-between font-bold text-foreground">
                            <span>{{ checkingInApp.patient?.first_name }} {{ checkingInApp.patient?.last_name }}</span>
                            <span class="font-mono text-muted-foreground">{{ checkingInApp.patient?.primary_mrn }}</span>
                        </div>
                        <div class="text-[11px] text-muted-foreground">
                            Scheduled: <span class="font-semibold text-foreground">{{ new Date(checkingInApp.scheduled_time).toLocaleString() }}</span>
                        </div>
                        <div class="text-[11px] text-muted-foreground">
                            Type: <span class="font-semibold text-foreground">{{ checkingInApp.appointment_type }}</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                        Checking in this patient will generate an active queue ticket and route them to the Triage & Vitals desk for clinical intake.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <Button variant="outline" size="sm" @click="closeCheckInModal" :disabled="isCheckingIn">Cancel</Button>
                    <Button variant="default" size="sm" @click="confirmCheckIn" :disabled="isCheckingIn">
                        <Loader2 v-if="isCheckingIn" class="w-3.5 h-3.5 animate-spin mr-1.5" />
                        <span>Check In & Route to Queue</span>
                    </Button>
                </div>
            </div>
        </Modal>
    </AfyaShell>
</template>
