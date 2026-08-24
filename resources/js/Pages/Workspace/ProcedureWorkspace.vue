<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Scissors, 
    Syringe, 
    Activity, 
    Clock, 
    Building2, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    Search, 
    Plus, 
    Loader2, 
    Check, 
    X, 
    FileText, 
    Calendar, 
    Users, 
    ShieldCheck, 
    Bandage, 
    Sparkles, 
    ArrowUpRight, 
    Layers, 
    Bed, 
    HeartPulse,
    SlidersHorizontal,
    PackageCheck
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';

// UI Primitives
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import AfyaPatientIdentity from '@/Components/Afya/AfyaPatientIdentity.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    procedureCatalogs: {
        type: Array,
        default: () => [],
    },
    dressingQueue: {
        type: Array,
        default: () => [],
    },
    surgicalBookings: {
        type: Array,
        default: () => [],
    },
    operatingSuites: {
        type: Array,
        default: () => [],
    },
    completedExecutions: {
        type: Array,
        default: () => [],
    },
    consumableProducts: {
        type: Array,
        default: () => [],
    },
    wards: {
        type: Array,
        default: () => [],
    },
    encountersForProcedures: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({
            total_procedures_today: 0,
            pending_dressing_queue: 0,
            in_theatre_surgeries: 0,
            pacu_recovery_bay: 0,
            emergency_procedures: 0,
        }),
    },
});

const { preferences, openContext } = useWorkspacePreferences();

const activeSection = ref('dressing'); // dressing, theatre, checklist, pacu, catalogue
const selectedCategoryFilter = ref('all');
const searchQuery = ref('');

const selectedRecord = ref(
    props.dressingQueue?.[0] || props.surgicalBookings?.[0] || props.completedExecutions?.[0] || null
);

// Modals State
const showOrderModal = ref(false);
const isOrdering = ref(false);
const orderForm = ref({
    encounter_id: '',
    procedure_catalog_id: '',
    priority: 'Routine',
    clinical_indication: '',
});

const showExecuteModal = ref(false);
const executeOrder = ref(null);
const isExecuting = ref(false);
const executeForm = ref({
    execution_setting: 'DressingRoom',
    anesthesia_type: 'Local',
    wound_condition: 'Clean',
    findings_and_technique: '',
    post_procedure_instructions: 'Keep dressing clean and dry. Avoid moisture.',
    follow_up_date: new Date(Date.now() + 3 * 86400000).toISOString().split('T')[0],
    consumables: [],
});

const showBookSurgeryModal = ref(false);
const isBookingSurgery = ref(false);
const bookSurgeryForm = ref({
    procedure_order_id: '',
    operating_suite_id: '',
    scheduled_start: new Date().toISOString().slice(0, 16),
    scheduled_end: new Date(Date.now() + 2 * 3600000).toISOString().slice(0, 16),
    urgency: 'Elective',
});

const showWhoChecklistModal = ref(false);
const whoChecklistRecord = ref(null);
const isSavingChecklist = ref(false);
const whoChecklistForm = ref({
    stage: 'time_out',
    sponge_and_needle_count_correct: true,
    specimens_labeled_correctly: true,
});

const showPacuModal = ref(false);
const pacuBookingRecord = ref(null);
const isSavingPacu = ref(false);
const pacuForm = ref({
    consciousness_score: 2,
    activity_score: 2,
    respiration_score: 2,
    circulation_score: 2,
    oxygen_saturation_score: 2,
    destination_ward_id: '',
    notes: 'Patient awake, hemodynamically stable, Aldrete score 10/10.',
});

// Category list
const categories = computed(() => {
    const cats = new Set(props.procedureCatalogs.map(p => p.category).filter(Boolean));
    return ['all', ...Array.from(cats)];
});

// Filtered Lists
const filteredDressingQueue = computed(() => {
    return props.dressingQueue.filter(order => {
        if (selectedCategoryFilter.value !== 'all' && order.catalog?.category !== selectedCategoryFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = order.patient ? `${order.patient.first_name} ${order.patient.last_name} ${order.patient.primary_mrn}`.toLowerCase() : '';
            const proc = (order.catalog?.name || '').toLowerCase();
            const ordNum = (order.order_number || '').toLowerCase();
            return pat.includes(q) || proc.includes(q) || ordNum.includes(q);
        }
        return true;
    });
});

const filteredSurgicalBookings = computed(() => {
    return props.surgicalBookings.filter(booking => {
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pat = booking.order?.patient ? `${booking.order.patient.first_name} ${booking.order.patient.last_name}`.toLowerCase() : '';
            const bNum = (booking.booking_number || '').toLowerCase();
            const proc = (booking.order?.catalog?.name || '').toLowerCase();
            return pat.includes(q) || bNum.includes(q) || proc.includes(q);
        }
        return true;
    });
});

const filteredCatalogs = computed(() => {
    return props.procedureCatalogs.filter(cat => {
        if (selectedCategoryFilter.value !== 'all' && cat.category !== selectedCategoryFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            return cat.name.toLowerCase().includes(q) || cat.procedure_code.toLowerCase().includes(q);
        }
        return true;
    });
});

// Selection handler
const selectRecord = (item) => {
    selectedRecord.value = item;
    openContext();
};

// Modal Openers
const openOrderModal = () => {
    const firstEnc = props.encountersForProcedures?.[0];
    const firstCat = props.procedureCatalogs?.[0];
    orderForm.value = {
        encounter_id: firstEnc ? firstEnc.id : '',
        procedure_catalog_id: firstCat ? firstCat.id : '',
        priority: 'Routine',
        clinical_indication: '',
    };
    showOrderModal.value = true;
};

const openExecuteModal = (order) => {
    executeOrder.value = order;
    
    // Auto-populate standard dressing room consumable pack
    const standardConsumables = [
        { item_name: 'Sterile Gauze Swabs 10x10cm (Pack)', batch_id: props.consumableProducts?.[0]?.id || null, quantity_used: 2, unit_price: 1500, is_billed_to_patient: true },
        { item_name: 'Surgical Latex Gloves Size 7.5', batch_id: props.consumableProducts?.[1]?.id || null, quantity_used: 1, unit_price: 2000, is_billed_to_patient: true },
        { item_name: 'Povidone-Iodine Antiseptic 10% (15ml)', batch_id: null, quantity_used: 1, unit_price: 1500, is_billed_to_patient: true },
    ];

    executeForm.value = {
        execution_setting: order.catalog?.tier_level === 'Tier2_MajorTheatre' ? 'MajorTheatre' : 'DressingRoom',
        anesthesia_type: order.catalog?.requires_anesthesia ? 'Local' : 'None',
        wound_condition: 'Clean',
        findings_and_technique: `Cleaned wound bed with normal saline and povidone-iodine. Applied sterile non-adherent dressing.`,
        post_procedure_instructions: 'Keep dressing intact and dry for 72 hours. Return immediately if redness or fever occurs.',
        follow_up_date: new Date(Date.now() + 3 * 86400000).toISOString().split('T')[0],
        consumables: standardConsumables,
    };
    showExecuteModal.value = true;
};

const openBookSurgeryModal = (order) => {
    const firstSuite = props.operatingSuites?.[0];
    bookSurgeryForm.value = {
        procedure_order_id: order.id,
        operating_suite_id: firstSuite ? firstSuite.id : '',
        scheduled_start: new Date(Date.now() + 3600000).toISOString().slice(0, 16),
        scheduled_end: new Date(Date.now() + 3 * 3600000).toISOString().slice(0, 16),
        urgency: order.priority === 'Emergency' ? 'Emergency' : 'Elective',
    };
    showBookSurgeryModal.value = true;
};

const openWhoChecklistModal = (booking) => {
    whoChecklistRecord.value = booking.whoChecklist || { surgical_booking_id: booking.id };
    whoChecklistForm.value = {
        stage: !booking.whoChecklist?.sign_in_completed_at ? 'sign_in' : (!booking.whoChecklist?.time_out_completed_at ? 'time_out' : 'sign_out'),
        sponge_and_needle_count_correct: true,
        specimens_labeled_correctly: true,
    };
    showWhoChecklistModal.value = true;
};

const openPacuModal = (booking) => {
    pacuBookingRecord.value = booking;
    pacuForm.value = {
        consciousness_score: 2,
        activity_score: 2,
        respiration_score: 2,
        circulation_score: 2,
        oxygen_saturation_score: 2,
        destination_ward_id: props.wards?.[0]?.id || '',
        notes: 'Patient fully conscious, stable vitals, Aldrete 10/10. Ready for step-down care.',
    };
    showPacuModal.value = true;
};

// Submissions
const submitOrder = () => {
    if (!orderForm.value.encounter_id || !orderForm.value.procedure_catalog_id) return;
    isOrdering.value = true;
    router.post(route('procedures.orders.store'), orderForm.value, {
        onFinish: () => {
            isOrdering.value = false;
            showOrderModal.value = false;
        }
    });
};

const submitExecution = () => {
    if (!executeOrder.value) return;
    isExecuting.value = true;
    router.post(route('procedures.orders.execute', executeOrder.value.id), executeForm.value, {
        onFinish: () => {
            isExecuting.value = false;
            showExecuteModal.value = false;
        }
    });
};

const submitBookSurgery = () => {
    if (!bookSurgeryForm.value.procedure_order_id) return;
    isBookingSurgery.value = true;
    router.post(route('procedures.orders.book-surgery', bookSurgeryForm.value.procedure_order_id), bookSurgeryForm.value, {
        onFinish: () => {
            isBookingSurgery.value = false;
            showBookSurgeryModal.value = false;
            activeSection.value = 'theatre';
        }
    });
};

const submitWhoChecklist = () => {
    if (!whoChecklistRecord.value?.id) return;
    isSavingChecklist.value = true;
    router.post(route('procedures.who-checklists.save', whoChecklistRecord.value.id), whoChecklistForm.value, {
        onFinish: () => {
            isSavingChecklist.value = false;
            showWhoChecklistModal.value = false;
        }
    });
};

const submitPacuScore = () => {
    if (!pacuBookingRecord.value) return;
    isSavingPacu.value = true;
    router.post(route('procedures.bookings.pacu', pacuBookingRecord.value.id), pacuForm.value, {
        onFinish: () => {
            isSavingPacu.value = false;
            showPacuModal.value = false;
        }
    });
};

const formatCurrency = (val) => Number(val || 0).toLocaleString('en-US');
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
    <Head title="Clinical Procedures & Surgical Care Workstation — AfyaNova Workstation" />

    <AfyaShell active-module="procedures">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Procedures & Surgery Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Procedures & OT"
                    :icon="Scissors"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Treatment Units
                    </div>
                    
                    <AfyaSidebarItem
                        label="Dressing & Minor Procedure"
                        :icon="Bandage"
                        :badge="metrics.pending_dressing_queue"
                        :active="activeSection === 'dressing'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'dressing'"
                    />
                    
                    <AfyaSidebarItem
                        label="Operating Theatre Suites"
                        :icon="Building2"
                        :badge="metrics.in_theatre_surgeries"
                        :active="activeSection === 'theatre'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'theatre'"
                    />

                    <AfyaSidebarItem
                        label="WHO Safety Checklists"
                        :icon="ShieldCheck"
                        :badge="surgicalBookings.length"
                        :active="activeSection === 'checklist'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'checklist'"
                    />

                    <AfyaSidebarItem
                        label="PACU Recovery Bay"
                        :icon="HeartPulse"
                        :badge="metrics.pacu_recovery_bay"
                        :active="activeSection === 'pacu'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'pacu'"
                    />

                    <AfyaSidebarItem
                        label="Procedure Master Catalog"
                        :icon="FileText"
                        :badge="procedureCatalogs.length"
                        :active="activeSection === 'catalogue'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'catalogue'"
                    />

                    <div v-if="state !== 'collapsed'" class="pt-2 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border/40 mt-1">
                        Categories
                    </div>

                    <div v-if="state !== 'collapsed'" class="px-2 space-y-0.5">
                        <button
                            v-for="cat in categories"
                            :key="cat"
                            class="w-full text-left px-2 py-1 rounded text-[11px] flex items-center justify-between hover:bg-muted/50 transition capitalize"
                            :class="selectedCategoryFilter === cat ? 'bg-primary/10 text-primary font-bold' : 'text-muted-foreground'"
                            @click="selectedCategoryFilter = cat"
                        >
                            <span>{{ cat === 'all' ? 'All Categories' : cat }}</span>
                        </button>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN WORK AREA -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Procedures', href: route('procedures.workspace') },
                        { label: activeSection === 'dressing' ? 'Dressing & Minor Procedure Treatment Desk' : (activeSection === 'theatre' ? 'Operating Theatre Suites & Surgical Schedule' : (activeSection === 'checklist' ? 'WHO Surgical Safety Checklist Station' : (activeSection === 'pacu' ? 'PACU Post-Anesthesia Recovery Bay' : 'Master Procedure Catalog'))), active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span v-if="metrics.emergency_procedures > 0" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800 animate-pulse">
                                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                                <span>{{ metrics.emergency_procedures }} Urgent / Emergency Procedures</span>
                            </span>

                            <Button
                                v-if="can.orderProcedure"
                                variant="default"
                                size="sm"
                                class="h-7.5 px-3 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="openOrderModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Order Procedure</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Top Enterprise Metric Strip (Clean Cards, No Outside Border) -->
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Total Today</div>
                                <div class="text-base font-bold text-foreground font-mono">{{ metrics.total_procedures_today }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Dressing Queue</div>
                                <div class="text-base font-bold text-amber-600 dark:text-amber-400 font-mono">{{ metrics.pending_dressing_queue }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Theatre Active</div>
                                <div class="text-base font-bold text-primary font-mono">{{ metrics.in_theatre_surgeries }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">PACU Recovery</div>
                                <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ metrics.pacu_recovery_bay }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 col-span-2 sm:col-span-1">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Urgent / Emergency</div>
                                <div class="text-base font-bold text-rose-600 dark:text-rose-400 font-mono">{{ metrics.emergency_procedures }}</div>
                            </div>
                        </div>

                        <!-- Category Filter & Search Strip (Seamless Container) -->
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-card p-2 rounded-lg shadow-2xs">
                            <div class="flex items-center gap-1.5 flex-1 max-w-xs">
                                <Search class="w-3.5 h-3.5 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search procedure, order #, or patient..."
                                    class="h-7 text-xs w-full"
                                />
                            </div>
                            
                            <div class="flex items-center gap-1 overflow-x-auto">
                                <Button
                                    v-for="cat in categories"
                                    :key="cat"
                                    size="sm"
                                    variant="outline"
                                    class="h-6 px-2 text-[10.5px] capitalize"
                                    :class="{ 'bg-primary text-primary-foreground font-semibold': selectedCategoryFilter === cat }"
                                    @click="selectedCategoryFilter = cat"
                                >
                                    {{ cat === 'all' ? 'All Procedures' : cat }}
                                </Button>
                            </div>
                        </div>

                        <!-- ================= VIEW 1: DRESSING & MINOR PROCEDURE DESK ================= -->
                        <div v-if="activeSection === 'dressing'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Bandage class="w-3.5 h-3.5 text-primary" />
                                        <span>Dressing & Minor Procedure Worklist ({{ filteredDressingQueue.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Order #</TableHead>
                                            <TableHead class="py-1 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1 px-3">Procedure Name</TableHead>
                                            <TableHead class="py-1 px-3">Category</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Priority</TableHead>
                                            <TableHead class="py-1 px-3">Ordering Clinician</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Nurse / CO Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="order in filteredDressingQueue"
                                            :key="order.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedRecord?.id === order.id }"
                                            @click="selectRecord(order)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ order.order_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[130px] text-[11px]">
                                                    {{ order.patient?.first_name }} {{ order.patient?.last_name }}
                                                </div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ order.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px]">{{ order.catalog?.name }}</div>
                                                <div class="text-[9.5px] text-muted-foreground truncate max-w-[160px]">{{ order.clinical_indication || 'Standard protocol' }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ order.catalog?.category }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="{
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300': order.priority === 'Emergency',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300': order.priority === 'Urgent',
                                                        'bg-muted text-foreground': order.priority === 'Routine',
                                                    }"
                                                >
                                                    {{ order.priority }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ order.ordering_provider ? `Dr. ${order.ordering_provider.first_name} ${order.ordering_provider.last_name}` : 'Clinician' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    v-if="can.executeProcedure"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10.5px] font-semibold gap-1 shadow-2xs"
                                                    @click.stop="openExecuteModal(order)"
                                                >
                                                    <Scissors class="w-3 h-3" />
                                                    <span>Execute Procedure</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredDressingQueue.length === 0">
                                            <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                No pending dressing or minor procedure orders in queue.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 2: OPERATING THEATRE SUITES & BOARD ================= -->
                        <div v-else-if="activeSection === 'theatre'" class="w-full space-y-3">
                            <!-- Operating Suites Status Cards -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <div 
                                    v-for="suite in operatingSuites" 
                                    :key="suite.id"
                                    class="p-3 bg-card rounded-lg shadow-2xs flex items-center justify-between"
                                >
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-xs text-foreground flex items-center gap-1.5">
                                            <Building2 class="w-3.5 h-3.5 text-primary" />
                                            <span>{{ suite.name }}</span>
                                        </div>
                                        <div class="text-[10px] text-muted-foreground">{{ suite.suite_type }} Suite · Code: <span class="font-mono font-semibold">{{ suite.suite_code }}</span></div>
                                    </div>
                                    <span 
                                        class="px-2 py-0.5 rounded text-[10px] font-bold"
                                        :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300': suite.status === 'Available',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300 animate-pulse': suite.status === 'Occupied',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300': suite.status === 'Cleaning',
                                        }"
                                    >
                                        {{ suite.status }}
                                    </span>
                                </div>
                            </div>

                            <!-- Surgical Schedule Table -->
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Calendar class="w-3.5 h-3.5 text-primary" />
                                        <span>Operating Theatre Surgical Schedule ({{ filteredSurgicalBookings.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Booking #</TableHead>
                                            <TableHead class="py-1 px-3">Suite</TableHead>
                                            <TableHead class="py-1 px-3">Patient</TableHead>
                                            <TableHead class="py-1 px-3">Surgical Procedure</TableHead>
                                            <TableHead class="py-1 px-3">Lead Surgeon</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Schedule</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Status</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="booking in filteredSurgicalBookings"
                                            :key="booking.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedRecord?.id === booking.id }"
                                            @click="selectRecord(booking)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ booking.booking_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-semibold text-foreground text-[11px]">
                                                {{ booking.suite?.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground text-[11px]">{{ booking.order?.patient?.first_name }} {{ booking.order?.patient?.last_name }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-[11px] text-foreground font-medium">
                                                {{ booking.order?.catalog?.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ booking.lead_surgeon ? `Dr. ${booking.lead_surgeon.first_name} ${booking.lead_surgeon.last_name}` : 'Surgeon' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono text-[10px] text-muted-foreground">
                                                {{ formatTime(booking.scheduled_start) }} - {{ formatTime(booking.scheduled_end) }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                                    :class="{
                                                        'bg-primary/10 text-primary': booking.status === 'Scheduled',
                                                        'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 animate-pulse': booking.status === 'InTheatre',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': booking.status === 'PACU',
                                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': booking.status === 'Completed',
                                                    }"
                                                >
                                                    {{ booking.status }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    v-if="(booking.status === 'Scheduled' || booking.status === 'InTheatre') && can.saveWhoChecklist"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10px] font-semibold gap-1 text-primary border-primary/30"
                                                    @click.stop="openWhoChecklistModal(booking)"
                                                >
                                                    <ShieldCheck class="w-3 h-3" />
                                                    <span>WHO Checklist</span>
                                                </Button>
                                                <Button
                                                    v-else-if="booking.status === 'PACU' && can.savePacuScore"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10px] font-semibold gap-1 bg-emerald-700 hover:bg-emerald-800 text-white"
                                                    @click.stop="openPacuModal(booking)"
                                                >
                                                    <HeartPulse class="w-3 h-3" />
                                                    <span>PACU Aldrete</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="filteredSurgicalBookings.length === 0">
                                            <TableCell colspan="8" class="text-center py-8 text-muted-foreground text-xs">
                                                No surgical cases scheduled on theatre board.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 3: WHO SAFETY CHECKLIST STATION ================= -->
                        <div v-else-if="activeSection === 'checklist'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <ShieldCheck class="w-3.5 h-3.5 text-primary" />
                                        <span>WHO Surgical Safety Checklist Station ({{ surgicalBookings.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Booking #</TableHead>
                                            <TableHead class="py-1 px-3">Patient & Surgery</TableHead>
                                            <TableHead class="py-1 px-3 text-center">1. Sign-In (Pre-Anesthesia)</TableHead>
                                            <TableHead class="py-1 px-3 text-center">2. Time-Out (Pre-Incision)</TableHead>
                                            <TableHead class="py-1 px-3 text-center">3. Sign-Out (Pre-Exit)</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Checklist Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="booking in surgicalBookings"
                                            :key="booking.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ booking.booking_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground text-[11px]">{{ booking.order?.patient?.first_name }} {{ booking.order?.patient?.last_name }}</div>
                                                <div class="text-[9.5px] text-muted-foreground">{{ booking.order?.catalog?.name }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="booking.whoChecklist?.sign_in_completed_at ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-muted text-muted-foreground'"
                                                >
                                                    <Check v-if="booking.whoChecklist?.sign_in_completed_at" class="w-2.5 h-2.5 text-emerald-600" />
                                                    <span>{{ booking.whoChecklist?.sign_in_completed_at ? 'Completed' : 'Pending' }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="booking.whoChecklist?.time_out_completed_at ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-muted text-muted-foreground'"
                                                >
                                                    <Check v-if="booking.whoChecklist?.time_out_completed_at" class="w-2.5 h-2.5 text-emerald-600" />
                                                    <span>{{ booking.whoChecklist?.time_out_completed_at ? 'Completed' : 'Pending' }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                                                    :class="booking.whoChecklist?.sign_out_completed_at ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-muted text-muted-foreground'"
                                                >
                                                    <Check v-if="booking.whoChecklist?.sign_out_completed_at" class="w-2.5 h-2.5 text-emerald-600" />
                                                    <span>{{ booking.whoChecklist?.sign_out_completed_at ? 'Completed' : 'Pending' }}</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    v-if="can.saveWhoChecklist"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10px] font-semibold gap-1 text-primary border-primary/30"
                                                    @click="openWhoChecklistModal(booking)"
                                                >
                                                    <ShieldCheck class="w-3 h-3" />
                                                    <span>Sign Checklist</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 4: PACU RECOVERY BAY ================= -->
                        <div v-else-if="activeSection === 'pacu'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <HeartPulse class="w-3.5 h-3.5 text-primary" />
                                        <span>PACU Post-Anesthesia Recovery Bay (Aldrete Scoring)</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Booking #</TableHead>
                                            <TableHead class="py-1 px-3">Patient</TableHead>
                                            <TableHead class="py-1 px-3">Procedure</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Aldrete Score</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Ward Discharge Ready</TableHead>
                                            <TableHead class="py-1 px-3 text-right">PACU Telemetry</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="booking in surgicalBookings"
                                            :key="booking.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px] w-28">
                                                {{ booking.booking_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground text-[11px]">{{ booking.order?.patient?.first_name }} {{ booking.order?.patient?.last_name }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-foreground text-[11px]">
                                                {{ booking.order?.catalog?.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono font-bold text-xs" :class="booking.pacuRecord?.total_aldrete_score >= 9 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600'">
                                                {{ booking.pacuRecord ? `${booking.pacuRecord.total_aldrete_score} / 10` : 'Evaluating' }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center">
                                                <span 
                                                    v-if="booking.pacuRecord?.discharge_ready"
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                                                >
                                                    <Check class="w-2.5 h-2.5 text-emerald-600" />
                                                    <span>Ready for Ward</span>
                                                </span>
                                                <span 
                                                    v-else
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                                                >
                                                    <span>In Recovery</span>
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <Button
                                                    v-if="can.savePacuScore"
                                                    variant="default"
                                                    size="sm"
                                                    class="h-6 px-2 text-[10px] font-semibold gap-1 shadow-2xs"
                                                    @click="openPacuModal(booking)"
                                                >
                                                    <HeartPulse class="w-3 h-3" />
                                                    <span>Record Vitals</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 5: PROCEDURE MASTER CATALOG ================= -->
                        <div v-else-if="activeSection === 'catalogue'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <FileText class="w-3.5 h-3.5 text-primary" />
                                        <span>Clinical Procedure Master Catalog ({{ filteredCatalogs.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3 w-28">Procedure Code</TableHead>
                                            <TableHead class="py-1 px-3">Procedure Name</TableHead>
                                            <TableHead class="py-1 px-3">Category</TableHead>
                                            <TableHead class="py-1 px-3">Tier Level</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Duration</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Standard Fee (TZS)</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="cat in filteredCatalogs"
                                            :key="cat.id"
                                            class="h-8.5 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[10.5px] w-28">
                                                {{ cat.procedure_code }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-bold text-foreground text-[11px]">
                                                {{ cat.name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ cat.category }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-[10px]">
                                                <span 
                                                    class="px-1.5 py-0.2 rounded font-semibold"
                                                    :class="cat.tier_level === 'Tier1_Minor' ? 'bg-primary/10 text-primary' : 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-300'"
                                                >
                                                    {{ cat.tier_level === 'Tier1_Minor' ? 'Dispensary / OPD' : 'Operating Theatre' }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono text-muted-foreground text-[10.5px]">
                                                {{ cat.default_duration_minutes }}m
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-right text-foreground text-[11px]">
                                                TZS {{ formatCurrency(cat.standard_price) }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: PROCEDURE 360 CONTEXT INSPECTOR -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Procedure 360 Inspector"
                    :icon="Scissors"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedRecord" class="space-y-2.5 text-xs">
                        <AfyaPatientIdentity v-if="selectedRecord.patient || selectedRecord.order?.patient" :patient="selectedRecord.patient || selectedRecord.order?.patient">
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-primary/10 text-primary">
                                {{ selectedRecord.status || 'Active' }}
                            </span>
                        </AfyaPatientIdentity>

                        <!-- Procedure Info Card -->
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Procedure Details
                            </div>
                            <div class="space-y-1 text-[10.5px]">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Procedure:</span>
                                    <span class="font-bold text-foreground">{{ selectedRecord.catalog?.name || selectedRecord.order?.catalog?.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Category:</span>
                                    <span class="text-foreground">{{ selectedRecord.catalog?.category || selectedRecord.order?.catalog?.category }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Setting:</span>
                                    <span class="font-semibold text-primary">{{ selectedRecord.execution_setting || (selectedRecord.suite?.name || 'Dressing Room') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Consumables Logged & Stock Movement -->
                        <div v-if="selectedRecord.consumables && selectedRecord.consumables.length > 0" class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                <span>Consumables Stock Deducted</span>
                                <PackageCheck class="w-3 h-3 text-emerald-600" />
                            </div>
                            <div class="space-y-1">
                                <div 
                                    v-for="con in selectedRecord.consumables" 
                                    :key="con.id"
                                    class="p-1.5 bg-muted/30 rounded border border-border/40 flex items-center justify-between text-[10.5px]"
                                >
                                    <div>
                                        <div class="font-semibold text-foreground">{{ con.item_name }}</div>
                                        <div class="text-[9px] text-muted-foreground font-mono">Qty: {{ con.quantity_used }} · Stock Decremented</div>
                                    </div>
                                    <div class="font-mono font-bold text-foreground text-right">
                                        TZS {{ formatCurrency(con.unit_price * con.quantity_used) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Execution Clinical Notes -->
                        <div v-if="selectedRecord.findings_and_technique" class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Procedure & Wound Notes
                            </div>
                            <p class="text-[10px] text-foreground leading-relaxed italic">
                                "{{ selectedRecord.findings_and_technique }}"
                            </p>
                            <div v-if="selectedRecord.follow_up_date" class="pt-1 text-[9.5px] text-primary font-semibold">
                                📅 Scheduled Return: {{ formatDate(selectedRecord.follow_up_date) }}
                            </div>
                        </div>

                        <!-- Action Triggers -->
                        <div class="space-y-1.5 pt-1">
                            <Button
                                v-if="selectedRecord.status === 'Ordered' && selectedRecord.catalog?.tier_level === 'Tier1_Minor' && can.executeProcedure"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs"
                                @click="openExecuteModal(selectedRecord)"
                            >
                                <Scissors class="w-3.5 h-3.5" />
                                <span>Execute Dressing / Minor Procedure</span>
                            </Button>

                            <Button
                                v-else-if="selectedRecord.status === 'Ordered' && selectedRecord.catalog?.tier_level === 'Tier2_MajorTheatre' && can.bookSurgery"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs bg-purple-700 hover:bg-purple-800 text-white"
                                @click="openBookSurgeryModal(selectedRecord)"
                            >
                                <Building2 class="w-3.5 h-3.5" />
                                <span>Book Operating Suite</span>
                            </Button>
                        </div>
                    </div>

                    <div v-else class="text-center py-6 text-muted-foreground text-xs">
                        Select a procedure record to preview clinical telemetry.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- ================= MODAL 1: ORDER PROCEDURE ================= -->
        <Modal :show="showOrderModal" max-width="md" @close="showOrderModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Scissors class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Order Clinical Procedure / Surgery</h3>
                    </div>
                    <button @click="showOrderModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitOrder" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Select Patient Encounter *</label>
                        <select 
                            v-model="orderForm.encounter_id" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option v-for="enc in encountersForProcedures" :key="enc.id" :value="enc.id">
                                {{ enc.patient?.first_name }} {{ enc.patient?.last_name }} — {{ enc.encounter_type }} (MRN: {{ enc.patient?.primary_mrn }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Select Procedure Catalog Item *</label>
                        <select 
                            v-model="orderForm.procedure_catalog_id" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option v-for="cat in procedureCatalogs" :key="cat.id" :value="cat.id">
                                {{ cat.name }} ({{ cat.category }}) — TZS {{ formatCurrency(cat.standard_price) }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Priority Urgency *</label>
                        <select 
                            v-model="orderForm.priority" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option value="Routine">Routine</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Clinical Indication / Reason</label>
                        <Input v-model="orderForm.clinical_indication" placeholder="e.g. Forearm laceration, dirty wound requiring debridement" class="h-8.5 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showOrderModal = false" :disabled="isOrdering">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isOrdering || !orderForm.encounter_id">
                            <Loader2 v-if="isOrdering" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Create Order & Bill</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ================= MODAL 2: EXECUTE DRESSING / MINOR PROCEDURE ================= -->
        <Modal :show="showExecuteModal" max-width="lg" @close="showExecuteModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Bandage class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Execute Dressing & Minor Procedure</h3>
                    </div>
                    <button @click="showExecuteModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="executeOrder" class="space-y-3 text-xs">
                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 flex items-center justify-between text-[11px]">
                        <div>
                            <div class="font-bold text-foreground text-xs">{{ executeOrder.catalog?.name }}</div>
                            <div class="text-muted-foreground">Order #{{ executeOrder.order_number }} · Priority: <span class="font-bold text-primary">{{ executeOrder.priority }}</span></div>
                        </div>
                        <div class="text-right text-[10px] text-muted-foreground">
                            <div>{{ executeOrder.patient?.first_name }} {{ executeOrder.patient?.last_name }}</div>
                            <div class="font-mono">MRN: {{ executeOrder.patient?.primary_mrn }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Wound Assessment Condition</label>
                            <select v-model="executeForm.wound_condition" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                                <option value="Clean">Clean Wound</option>
                                <option value="Contaminated">Contaminated / Traumatic</option>
                                <option value="Purulent">Purulent / Abscess</option>
                                <option value="Granulating">Granulating / Healing</option>
                                <option value="Epithelializing">Epithelializing</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Local Anesthesia</label>
                            <select v-model="executeForm.anesthesia_type" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                                <option value="None">None</option>
                                <option value="Local">Local Infiltration (Lignocaine 2%)</option>
                                <option value="Sedation">Procedural Sedation</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Clinical Technique & Findings *</label>
                        <textarea
                            v-model="executeForm.findings_and_technique"
                            rows="2"
                            required
                            class="w-full rounded-md border border-input bg-background text-foreground p-2 text-xs"
                            placeholder="Describe procedure steps, irrigation, debridement, suture type..."
                        ></textarea>
                    </div>

                    <!-- Consumables Pack Checkbox List -->
                    <div class="space-y-1.5 p-2.5 rounded border border-border/60 bg-muted/15">
                        <label class="block font-bold text-xs text-foreground flex items-center justify-between">
                            <span>Consumable Materials Consumed (Stock Deduction)</span>
                            <span class="text-[9.5px] text-muted-foreground">Auto-decrements Inventory</span>
                        </label>
                        <div class="space-y-1 max-h-28 overflow-y-auto">
                            <div v-for="(con, idx) in executeForm.consumables" :key="idx" class="flex items-center justify-between text-[11px] p-1 bg-card rounded">
                                <span class="font-medium text-foreground">{{ con.item_name }}</span>
                                <span class="font-mono text-muted-foreground font-semibold">Qty: {{ con.quantity_used }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Scheduled Return / Follow-up Date</label>
                            <Input v-model="executeForm.follow_up_date" type="date" class="h-8.5 text-xs font-mono" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Post-Procedure Instructions</label>
                            <Input v-model="executeForm.post_procedure_instructions" class="h-8.5 text-xs" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showExecuteModal = false" :disabled="isExecuting">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" @click="submitExecution" :disabled="isExecuting || !executeForm.findings_and_technique">
                            <Loader2 v-if="isExecuting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Complete & Deduct Stock</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 3: WHO CHECKLIST SIGN-OFF ================= -->
        <Modal :show="showWhoChecklistModal" max-width="md" @close="showWhoChecklistModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">WHO Surgical Safety Checklist</h3>
                    </div>
                    <button @click="showWhoChecklistModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Checklist Safety Stage *</label>
                        <select v-model="whoChecklistForm.stage" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option value="sign_in">1. Sign-In (Before induction of anesthesia)</option>
                            <option value="time_out">2. Time-Out (Before skin incision - Patient, Procedure, Site)</option>
                            <option value="sign_out">3. Sign-Out (Before patient leaves operating room)</option>
                        </select>
                    </div>

                    <div class="p-2.5 bg-muted/30 rounded border border-border/60 space-y-1.5 text-[10.5px]">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="whoChecklistForm.sponge_and_needle_count_correct" class="rounded text-primary focus:ring-0" />
                            <span class="font-semibold text-foreground">Sponge, needle, and instrument counts verified correct</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="whoChecklistForm.specimens_labeled_correctly" class="rounded text-primary focus:ring-0" />
                            <span class="font-semibold text-foreground">Surgical specimen labeled with patient name and MRN</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showWhoChecklistModal = false" :disabled="isSavingChecklist">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs bg-emerald-700 hover:bg-emerald-800 text-white" @click="submitWhoChecklist" :disabled="isSavingChecklist">
                            <Loader2 v-if="isSavingChecklist" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Sign & Confirm Checklist</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL 4: PACU ALDRETE TELEMETRY ================= -->
        <Modal :show="showPacuModal" max-width="md" @close="showPacuModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <HeartPulse class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">PACU Post-Anesthesia Aldrete Score</h3>
                    </div>
                    <button @click="showPacuModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Consciousness (0-2)</span>
                            <select v-model="pacuForm.consciousness_score" class="h-7 text-xs rounded border border-input bg-background font-mono">
                                <option :value="2">2 - Fully awake</option>
                                <option :value="1">1 - Arousable on calling</option>
                                <option :value="0">0 - Unresponsive</option>
                            </select>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Activity (0-2)</span>
                            <select v-model="pacuForm.activity_score" class="h-7 text-xs rounded border border-input bg-background font-mono">
                                <option :value="2">2 - Moves 4 extremities</option>
                                <option :value="1">1 - Moves 2 extremities</option>
                                <option :value="0">0 - Unable to move</option>
                            </select>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Respiration (0-2)</span>
                            <select v-model="pacuForm.respiration_score" class="h-7 text-xs rounded border border-input bg-background font-mono">
                                <option :value="2">2 - Breathes deeply & coughs</option>
                                <option :value="1">1 - Dyspneic / limited</option>
                                <option :value="0">0 - Apneic</option>
                            </select>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Oxygen Saturation (0-2)</span>
                            <select v-model="pacuForm.oxygen_saturation_score" class="h-7 text-xs rounded border border-input bg-background font-mono">
                                <option :value="2">2 - SpO2 > 92% on room air</option>
                                <option :value="1">1 - Needs supplemental O2</option>
                                <option :value="0">0 - SpO2 &lt; 90%</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-2 rounded bg-muted/40 flex justify-between items-center font-bold text-xs">
                        <span>Computed Total Aldrete Score:</span>
                        <span class="font-mono text-emerald-600 text-sm">
                            {{ Number(pacuForm.consciousness_score) + Number(pacuForm.activity_score) + Number(pacuForm.respiration_score) + Number(pacuForm.circulation_score) + Number(pacuForm.oxygen_saturation_score) }} / 10
                        </span>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Step-Down Destination Ward</label>
                        <select v-model="pacuForm.destination_ward_id" class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs">
                            <option v-for="w in wards" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showPacuModal = false" :disabled="isSavingPacu">Cancel</Button>
                        <Button variant="default" size="sm" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs bg-emerald-700 hover:bg-emerald-800 text-white" @click="submitPacuScore" :disabled="isSavingPacu">
                            <Loader2 v-if="isSavingPacu" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Confirm Recovery & Transfer</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

    </AfyaShell>
</template>
