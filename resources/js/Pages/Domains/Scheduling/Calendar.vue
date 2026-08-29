<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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
    Loader2,
    Save,
    ConciergeBell,
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

// UI Primitives & Design Foundation
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import AfyaDatePicker from '@/Components/Afya/AfyaDatePicker.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
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
    appointments: {
        type: Array,
        default: () => [],
    },
    patients: {
        type: Array,
        default: () => [],
    },
    providers: {
        type: Array,
        default: () => [],
    },
    facilities: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({ lobby_waiting: 0, today_appointments: 0, total_patients: 0 }),
    },
});

const appointmentTypeOptions = [
    { value: 'Consultation', label: 'OPD Consult' },
    { value: 'Follow-up', label: 'Follow-Up' },
    { value: 'Specialist Review', label: 'Specialist' },
    { value: 'ANC Visit', label: 'ANC' },
    { value: 'Procedure', label: 'Procedure' },
];

const selectedAppointment = ref(props.appointments?.[0] || null);
const showContext = ref(true);

// Check-in Modal State
const showCheckInModal = ref(false);
const checkingInApp = ref(null);
const isCheckingIn = ref(false);

// Book Appointment Modal State
const showBookModal = ref(false);
const nowIso = new Date();
nowIso.setMinutes(0, 0, 0);
nowIso.setHours(nowIso.getHours() + 1);
const defaultDateStr = nowIso.toISOString().slice(0, 16);

const bookForm = useForm({
    patient_id: props.patients?.[0]?.id || '',
    facility_id: props.facilities?.[0]?.id || '',
    department_id: props.departments?.[0]?.id || '',
    provider_id: props.providers?.[0]?.id || '',
    scheduled_time: defaultDateStr,
    duration_minutes: 30,
    appointment_type: 'Consultation',
    notes: '',
});

const openBookModal = () => {
    bookForm.patient_id = props.patients?.[0]?.id || '';
    bookForm.facility_id = props.facilities?.[0]?.id || '';
    bookForm.department_id = props.departments?.[0]?.id || '';
    bookForm.provider_id = props.providers?.[0]?.id || '';
    bookForm.clearErrors();
    showBookModal.value = true;
};

const submitBookAppointment = () => {
    bookForm.post(route('appointments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showBookModal.value = false;
            bookForm.reset();
        },
    });
};

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
    <Head title="Appointments & Schedule — AfyaNova" />

    <AfyaShell active-module="scheduling">
        <AfyaWorkspace :show-sidebar="true" :show-context="showContext">
            <!-- 1. LEFT SIDEBAR: Scheduling Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Appointments Hub"
                    :icon="CalendarIcon"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Reception Operations
                    </div>
                    <AfyaSidebarItem
                        label="Front Desk"
                        :icon="ConciergeBell"
                        :collapsed="state === 'collapsed'"
                        :href="route('dashboard')"
                    />
                    <AfyaSidebarItem
                        v-if="can.patients"
                        label="Patient Registry"
                        :icon="Users"
                        :badge="metrics?.total_patients || null"
                        :collapsed="state === 'collapsed'"
                        :href="route('patients.index')"
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
                        label="Appointments Calendar"
                        :icon="CalendarIcon"
                        :badge="metrics?.today_appointments || appointments.length"
                        :active="true"
                        :collapsed="state === 'collapsed'"
                        :href="route('appointments.index')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Appointment Schedule Table -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Front Desk', href: route('dashboard') },
                        { label: 'Appointments Calendar', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-primary/10 text-primary border border-primary/20">
                                <CalendarCheck class="w-3.5 h-3.5" />
                                <span>{{ appointments.length }} Appointments</span>
                            </span>
                            <Button variant="default" size="sm" class="h-7 text-xs font-semibold gap-1" v-if="can.store" @click="openBookModal">
                                <Plus class="w-3 h-3" />
                                <span>Book Appointment</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        <div class="w-full border border-border/60 rounded-xl overflow-hidden bg-card shadow-2xs">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/20 text-[10px] uppercase font-bold">
                                        <TableHead class="py-2.5 px-3">Scheduled Time</TableHead>
                                        <TableHead class="py-2.5 px-3">Patient Details</TableHead>
                                        <TableHead class="py-2.5 px-3">Clinician / Provider</TableHead>
                                        <TableHead class="py-2.5 px-3">Type</TableHead>
                                        <TableHead class="py-2.5 px-3">Status</TableHead>
                                        <TableHead class="py-2.5 px-3 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="app in appointments"
                                        :key="app.id"
                                        :selected="selectedAppointment?.id === app.id"
                                        class="cursor-pointer hover:bg-muted/30 border-b border-border/40"
                                        @click="selectAppointment(app)"
                                    >
                                        <TableCell class="py-2 px-3 font-mono font-bold text-foreground text-xs">
                                            {{ new Date(app.scheduled_time).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) }}
                                        </TableCell>
                                        <TableCell class="py-2 px-3">
                                            <div class="font-bold text-foreground text-xs">{{ app.patient?.first_name }} {{ app.patient?.last_name }}</div>
                                            <div class="text-[10px] font-mono text-muted-foreground">MRN: {{ app.patient?.primary_mrn }}</div>
                                        </TableCell>
                                        <TableCell class="py-2 px-3 text-xs text-muted-foreground">
                                            {{ app.provider ? `Dr. ${app.provider.first_name || ''} ${app.provider.last_name || ''}` : 'General Consultation' }}
                                        </TableCell>
                                        <TableCell class="py-2 px-3 text-xs font-semibold">{{ app.appointment_type }}</TableCell>
                                        <TableCell class="py-2 px-3">
                                            <AfyaStatusBadge :status="app.status" dot />
                                        </TableCell>
                                        <TableCell class="py-2 px-3 text-right" @click.stop>
                                            <Button
                                                v-if="app.status === 'Scheduled' && can.checkIn"
                                                variant="default"
                                                size="sm"
                                                class="h-7 text-[11px] font-semibold gap-1"
                                                @click.stop="openCheckInModal(app)"
                                            >
                                                <UserCheck class="w-3 h-3" />
                                                <span>Check In</span>
                                            </Button>
                                            <Link 
                                                v-else-if="app.status === 'Checked-In'" 
                                                :href="route('queue.index')" 
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
                                            >
                                                <CheckCircle2 class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                                                <span>In Queue</span>
                                            </Link>
                                            <span v-else class="text-[11px] text-muted-foreground font-medium">
                                                {{ app.status }}
                                            </span>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="appointments.length === 0">
                                        <TableCell colspan="6" class="text-center py-12 text-muted-foreground">
                                            <CalendarIcon class="w-8 h-8 mx-auto mb-2 text-muted-foreground opacity-50" />
                                            <p class="font-semibold text-foreground text-xs">No appointments scheduled</p>
                                            <p class="text-[11px]">Booked patient appointments will appear here.</p>
                                            <Button variant="outline" size="sm" class="mt-3 text-xs gap-1" v-if="can.store" @click="openBookModal">
                                                <Plus class="w-3 h-3" />
                                                <span>Book First Appointment</span>
                                            </Button>
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
                            <CardHeader><CardTitle class="text-xs font-bold">Booking Summary</CardTitle></CardHeader>
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
                                <div v-if="selectedAppointment.notes" class="pt-1.5 border-t border-border/50">
                                    <span class="text-muted-foreground font-bold text-[10px] uppercase">Notes:</span>
                                    <p class="text-foreground mt-0.5">{{ selectedAppointment.notes }}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Button
                            v-if="selectedAppointment.status === 'Scheduled' && can.checkIn"
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

        <!-- MODAL: BOOK NEW APPOINTMENT -->
        <Modal :show="showBookModal" max-width="lg" @close="showBookModal = false">
            <div class="p-5 space-y-4 text-xs">
                <div class="flex items-center justify-between border-b border-border/60 pb-3">
                    <div class="flex items-center gap-2">
                        <CalendarCheck class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Book Patient Appointment</h3>
                    </div>
                    <button @click="showBookModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitBookAppointment" class="space-y-3">
                    <div>
                        <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Select Patient *</label>
                        <Select v-model="bookForm.patient_id" class="w-full">
                            <option value="" disabled class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">-- Choose Patient --</option>
                            <option v-for="p in patients" :key="p.id" :value="p.id" class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">{{ p.first_name }} {{ p.last_name }} ({{ p.primary_mrn }})</option>
                        </Select>
                        <InputError :message="bookForm.errors.patient_id" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Facility *</label>
                            <Select v-model="bookForm.facility_id" class="w-full">
                                <option v-for="f in facilities" :key="f.id" :value="f.id" class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">{{ f.name }}</option>
                            </Select>
                            <InputError :message="bookForm.errors.facility_id" class="mt-1" />
                        </div>
                        <div>
                            <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Department</label>
                            <Select v-model="bookForm.department_id" class="w-full">
                                <option value="" class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">General OPD</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id" class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">{{ d.name }}</option>
                            </Select>
                            <InputError :message="bookForm.errors.department_id" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Provider / Doctor</label>
                            <Select v-model="bookForm.provider_id" class="w-full">
                                <option value="" class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">Any Available Clinician</option>
                                <option v-for="u in providers" :key="u.id" :value="u.id" class="bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">Dr. {{ u.first_name }} {{ u.last_name }}</option>
                            </Select>
                            <InputError :message="bookForm.errors.provider_id" class="mt-1" />
                        </div>
                        <div class="col-span-2 space-y-1">
                            <label class="block font-bold text-[10px] uppercase text-muted-foreground">Appointment Type *</label>
                            <SegmentedControl
                                v-model="bookForm.appointment_type"
                                :options="appointmentTypeOptions"
                                size="sm"
                                :full-width="true"
                            />
                            <InputError :message="bookForm.errors.appointment_type" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <AfyaDatePicker
                                v-model="bookForm.scheduled_time"
                                label="Scheduled Date & Time"
                                required
                                :with-time="true"
                                :error="bookForm.errors.scheduled_time"
                                :min="new Date()"
                            />
                        </div>
                        <div>
                            <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Duration (Minutes) *</label>
                            <Input v-model.number="bookForm.duration_minutes" type="number" min="5" max="180" class="h-8 text-xs font-mono" />
                            <InputError :message="bookForm.errors.duration_minutes" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Clinical Reason / Notes</label>
                        <Input v-model="bookForm.notes" placeholder="e.g. Hypertension review and prescription refill" class="h-8 text-xs" />
                        <InputError :message="bookForm.errors.notes" class="mt-1" />
                        <InputError :message="bookForm.errors.schedule" class="mt-1 font-bold" />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-border/60">
                        <Button type="button" variant="outline" size="sm" @click="showBookModal = false">Cancel</Button>
                        <Button type="submit" variant="default" size="sm" :disabled="bookForm.processing">
                            <Loader2 v-if="bookForm.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Confirm Booking</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

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
