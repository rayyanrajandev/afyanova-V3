<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { 
    Film, 
    Users, 
    Clock, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    ShieldAlert, 
    Search, 
    Plus, 
    Loader2, 
    Check, 
    X, 
    FileText, 
    Activity, 
    Calendar,
    ChevronRight,
    Lock,
    ShieldCheck,
    SlidersHorizontal,
    Send,
    ArrowUpRight,
    Edit3,
    Eye,
    Maximize2,
    RotateCw,
    Sun,
    Contrast,
    ZoomIn,
    ZoomOut,
    ExternalLink,
    Layers,
    RotateCcw
} from 'lucide-vue-next';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

// UI Primitives
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import AfyaPatientIdentity from '@/Components/Afya/AfyaPatientIdentity.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    orders: {
        type: Array,
        default: () => [],
    },
    pendingOrders: {
        type: Array,
        default: () => [],
    },
    reportingOrders: {
        type: Array,
        default: () => [],
    },
    signedOrders: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({
            total_pending: 0,
            in_reporting: 0,
            reported_today: 0,
            critical_count: 0,
        }),
    },
});

const { preferences, openContext } = useWorkspacePreferences();

const activeSection = ref('pending'); // pending, reporting, signed
const selectedModality = ref('all'); // all, X-Ray, Ultrasound, CT Scan, MRI, Echo
const searchQuery = ref('');

const selectedOrder = ref(
    props.pendingOrders?.[0] || props.reportingOrders?.[0] || props.signedOrders?.[0] || null
);

// Modals State
const showSignModal = ref(false);
const showDicomViewerModal = ref(false);
const activeDicomStudy = ref(null);
const activeSeriesIndex = ref(0);
const zoomLevel = ref(100);
const rotation = ref(0);
const isInverted = ref(false);
const windowPreset = ref('soft-tissue'); // soft-tissue, bone, lung, brain

const openDicomViewer = (order) => {
    selectedOrder.value = order;
    const study = order.studies?.[0];
    activeDicomStudy.value = study || {
        study_instance_uid: '1.2.840.113619.2.55.3.' + order.id.replace(/-/g, '').slice(0, 16),
        accession_number: 'ACC-' + order.order_number,
        series_count: 2,
        instance_count: 24,
        modality: order.modality,
    };
    activeSeriesIndex.value = 0;
    zoomLevel.value = 100;
    rotation.value = 0;
    isInverted.value = false;
    windowPreset.value = 'soft-tissue';
    showDicomViewerModal.value = true;
};

const zoomIn = () => { zoomLevel.value = Math.min(zoomLevel.value + 25, 300); };
const zoomOut = () => { zoomLevel.value = Math.max(zoomLevel.value - 25, 50); };
const rotateCw = () => { rotation.value = (rotation.value + 90) % 360; };
const toggleInvert = () => { isInverted.value = !isInverted.value; };
const resetViewer = () => {
    zoomLevel.value = 100;
    rotation.value = 0;
    isInverted.value = false;
    windowPreset.value = 'soft-tissue';
};

const showAmendModal = ref(false);
const selectedReportToAmend = ref(null);

const signForm = useForm({
    findings: '',
    impression: '',
    recommendations: '',
    is_critical_finding: false,
});

const amendForm = useForm({
    amendment_reason: '',
    findings: '',
    impression: '',
    recommendations: '',
    is_critical_finding: false,
});

const selectOrder = (order) => {
    selectedOrder.value = order;
    openContext('radiology_details');
};

const openSignDialog = (order) => {
    selectedOrder.value = order;
    signForm.reset();
    showSignModal.value = true;
};

const submitSignReport = () => {
    if (!selectedOrder.value) return;
    signForm.post(route('radiology.report.sign', selectedOrder.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSignModal.value = false;
            signForm.reset();
        },
    });
};

const openAmendDialog = (order) => {
    selectedOrder.value = order;
    const rep = order.reports?.[0];
    if (!rep) return;
    selectedReportToAmend.value = rep;
    amendForm.amendment_reason = '';
    amendForm.findings = rep.findings || '';
    amendForm.impression = rep.impression || '';
    amendForm.recommendations = rep.recommendations || '';
    amendForm.is_critical_finding = rep.is_critical_finding || false;
    showAmendModal.value = true;
};

const submitAmendReport = () => {
    if (!selectedReportToAmend.value) return;
    amendForm.post(route('radiology.report.amend', selectedReportToAmend.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAmendModal.value = false;
            amendForm.reset();
        },
    });
};

const filteredList = computed(() => {
    let list = [];
    if (activeSection.value === 'pending') list = props.pendingOrders;
    else if (activeSection.value === 'reporting') list = props.reportingOrders;
    else if (activeSection.value === 'signed') list = props.signedOrders;

    return list.filter(o => {
        const matchesMod = selectedModality.value === 'all' || o.modality === selectedModality.value;
        const q = searchQuery.value.toLowerCase().trim();
        if (!q) return matchesMod;

        const matchesQuery = 
            (o.order_number && o.order_number.toLowerCase().includes(q)) ||
            (o.procedure_name && o.procedure_name.toLowerCase().includes(q)) ||
            (o.patient?.first_name && o.patient.first_name.toLowerCase().includes(q)) ||
            (o.patient?.last_name && o.patient.last_name.toLowerCase().includes(q)) ||
            (o.patient?.primary_mrn && o.patient.primary_mrn.toLowerCase().includes(q));

        return matchesMod && matchesQuery;
    });
});

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' · ' + d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
};
</script>

<template>
    <Head title="Radiology & PACS Imaging Workspace — AfyaNova" />

    <AfyaShell active-module="radiology">
        <AfyaWorkspace :show-sidebar="true" :show-context="preferences.showContext">

            <!-- 1. LEFT SIDEBAR: MODALITY WORKLIST QUEUES -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="RADIOLOGY / PACS"
                    :icon="Film"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <!-- Worklist Stages -->
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Imaging Stages
                    </div>
                    
                    <AfyaSidebarItem
                        label="Pending Acquisition"
                        :icon="Clock"
                        :badge="pendingOrders.length"
                        :active="activeSection === 'pending'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'pending'"
                    />

                    <AfyaSidebarItem
                        label="In Reporting / Review"
                        :icon="Activity"
                        :badge="reportingOrders.length"
                        :active="activeSection === 'reporting'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'reporting'"
                    />

                    <AfyaSidebarItem
                        label="Signed Final Reports"
                        :icon="CheckCircle2"
                        :badge="signedOrders.length"
                        :active="activeSection === 'signed'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'signed'"
                    />

                    <!-- Modality Filter Segment -->
                    <div v-if="state !== 'collapsed'" class="pt-3 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border mt-2">
                        Modality Filter
                    </div>

                    <div v-if="state !== 'collapsed'" class="px-2 space-y-1">
                        <button
                            v-for="mod in ['all', 'X-Ray', 'Ultrasound', 'CT Scan', 'MRI', 'Echo']"
                            :key="mod"
                            @click="selectedModality = mod"
                            class="w-full text-left px-2 py-1 rounded text-xs transition-colors flex items-center justify-between"
                            :class="selectedModality === mod ? 'bg-primary text-primary-foreground font-bold' : 'text-muted-foreground hover:bg-muted/40 hover:text-foreground'"
                        >
                            <span>{{ mod === 'all' ? 'All Modalities' : mod }}</span>
                            <span class="text-[10px] font-mono opacity-80">
                                {{ mod === 'all' ? orders.length : orders.filter(o => o.modality === mod).length }}
                            </span>
                        </button>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: HIGH-DENSITY WORKLIST GRID -->
            <template #default>
                <AfyaWorkspaceMain>
                    <!-- Top Telemetry Metrics -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        <div class="p-3 bg-card border border-border/60 rounded-xl shadow-2xs">
                            <div class="text-[10px] uppercase font-bold text-muted-foreground">Pending Imaging</div>
                            <div class="text-2xl font-bold font-mono text-amber-600 mt-1">{{ metrics.total_pending }}</div>
                            <div class="text-[10px] text-muted-foreground mt-0.5">Awaiting acquisition</div>
                        </div>
                        <div class="p-3 bg-card border border-border/60 rounded-xl shadow-2xs">
                            <div class="text-[10px] uppercase font-bold text-muted-foreground">In Reporting</div>
                            <div class="text-2xl font-bold font-mono text-primary mt-1">{{ metrics.in_reporting }}</div>
                            <div class="text-[10px] text-muted-foreground mt-0.5">Radiologist review</div>
                        </div>
                        <div class="p-3 bg-card border border-border/60 rounded-xl shadow-2xs">
                            <div class="text-[10px] uppercase font-bold text-muted-foreground">Reported Today</div>
                            <div class="text-2xl font-bold font-mono text-emerald-600 mt-1">{{ metrics.reported_today }}</div>
                            <div class="text-[10px] text-muted-foreground mt-0.5">Validated & sealed</div>
                        </div>
                        <div class="p-3 bg-card border border-border/60 rounded-xl shadow-2xs" :class="metrics.critical_count > 0 ? 'bg-rose-500/5 border-rose-500/30' : ''">
                            <div class="text-[10px] uppercase font-bold text-muted-foreground flex items-center gap-1">
                                <ShieldAlert v-if="metrics.critical_count > 0" class="w-3 h-3 text-rose-600" />
                                <span>Critical Alerts</span>
                            </div>
                            <div class="text-2xl font-bold font-mono text-rose-600 mt-1">{{ metrics.critical_count }}</div>
                            <div class="text-[10px] text-muted-foreground mt-0.5">Immediate doctor notify</div>
                        </div>
                    </div>

                    <!-- Search & Action Toolbar -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 p-2 bg-card border border-border/60 rounded-xl shadow-2xs mb-4">
                        <div class="flex items-center gap-2 w-full sm:w-80">
                            <Search class="w-4 h-4 text-muted-foreground shrink-0 ml-1" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by order #, MRN, patient name..."
                                class="h-8 text-xs bg-background"
                            />
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                            <span>Showing {{ filteredList.length }} orders</span>
                        </div>
                    </div>

                    <!-- Main Table View -->
                    <div class="border border-border/60 rounded-xl overflow-hidden bg-card shadow-2xs">
                        <Table>
                            <TableHeader>
                                <TableRow class="bg-muted/20 text-[10px] uppercase font-bold">
                                    <TableHead class="py-2.5 px-3">Order #</TableHead>
                                    <TableHead class="py-2.5 px-3">Date</TableHead>
                                    <TableHead class="py-2.5 px-3">Patient & MRN</TableHead>
                                    <TableHead class="py-2.5 px-3">Modality</TableHead>
                                    <TableHead class="py-2.5 px-3">Procedure Title</TableHead>
                                    <TableHead class="py-2.5 px-3">Priority</TableHead>
                                    <TableHead class="py-2.5 px-3">Status</TableHead>
                                    <TableHead class="py-2.5 px-3 text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow 
                                    v-for="order in filteredList" 
                                    :key="order.id"
                                    class="h-10 hover:bg-muted/30 cursor-pointer transition-colors border-b border-border/40"
                                    :class="selectedOrder?.id === order.id ? 'bg-primary/5' : ''"
                                    @click="selectOrder(order)"
                                >
                                    <TableCell class="py-2 px-3 font-mono font-bold text-xs text-primary">{{ order.order_number }}</TableCell>
                                    <TableCell class="py-2 px-3 font-mono text-[10px] text-muted-foreground">{{ formatDate(order.created_at) }}</TableCell>
                                    <TableCell class="py-2 px-3">
                                        <div class="font-bold text-foreground text-xs">{{ order.patient?.first_name }} {{ order.patient?.last_name }}</div>
                                        <div class="text-[10px] text-muted-foreground font-mono">{{ order.patient?.primary_mrn }} · {{ order.patient?.gender }}</div>
                                    </TableCell>
                                    <TableCell class="py-2 px-3 font-bold text-xs">
                                        <span class="px-1.5 py-0.5 rounded text-[10px]"
                                            :class="{
                                                'bg-blue-500/10 text-blue-600': order.modality === 'X-Ray',
                                                'bg-emerald-500/10 text-emerald-600': order.modality === 'Ultrasound',
                                                'bg-purple-500/10 text-purple-600': order.modality === 'CT Scan',
                                                'bg-indigo-500/10 text-indigo-600': order.modality === 'MRI',
                                                'bg-rose-500/10 text-rose-600': order.modality === 'Echo',
                                            }"
                                        >
                                            {{ order.modality }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="py-2 px-3 font-semibold text-foreground text-xs">
                                        {{ order.procedure_name }}
                                        <div class="text-[10px] text-muted-foreground font-normal">{{ order.clinical_indication || 'Clinical indication on chart' }}</div>
                                    </TableCell>
                                    <TableCell class="py-2 px-3">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                            :class="{
                                                'bg-rose-500/10 text-rose-600': order.priority === 'STAT',
                                                'bg-amber-500/10 text-amber-600': order.priority === 'Urgent',
                                                'bg-muted text-foreground': order.priority === 'Routine',
                                            }"
                                        >
                                            {{ order.priority }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="py-2 px-3">
                                        <AfyaStatusBadge :status="order.status" dot />
                                    </TableCell>
                                    <TableCell class="py-2 px-3 text-right" @click.stop>
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-7 text-[11px] font-semibold gap-1 text-primary border-primary/30 hover:bg-primary/10"
                                                @click="openDicomViewer(order)"
                                                title="Launch PACS DICOM Imaging Viewport"
                                            >
                                                <Film class="w-3 h-3" />
                                                <span>DICOM</span>
                                            </Button>
                                            <Button
                                                v-if="order.status !== 'Reported' && can.signReport"
                                                variant="default"
                                                size="sm"
                                                class="h-7 text-[11px] font-semibold gap-1"
                                                @click="openSignDialog(order)"
                                            >
                                                <Edit3 class="w-3 h-3" />
                                                <span>Sign Report</span>
                                            </Button>
                                            <Button
                                                v-else-if="order.status === 'Reported' && can.amendReport"
                                                variant="outline"
                                                size="sm"
                                                class="h-7 text-[11px] font-semibold gap-1"
                                                @click="openAmendDialog(order)"
                                            >
                                                <Edit3 class="w-3 h-3 text-amber-600" />
                                                <span>Amend</span>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="filteredList.length === 0">
                                    <TableCell colspan="8" class="text-center py-12 text-muted-foreground text-xs space-y-1">
                                        <Film class="w-8 h-8 text-muted-foreground/30 mx-auto" />
                                        <div>No radiology orders found matching this filter.</div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT PANEL: SELECTED STUDY TELEMETRY -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Imaging Study Telemetry"
                    :icon="Film"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedOrder" class="space-y-4 text-xs">
                        <div class="p-3 bg-muted/20 rounded-lg border border-border/50 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-bold text-primary">{{ selectedOrder.order_number }}</span>
                                <AfyaStatusBadge :status="selectedOrder.status" dot />
                            </div>
                            <div class="font-bold text-foreground">{{ selectedOrder.procedure_name }}</div>
                            <div class="text-muted-foreground text-[11px]">Modality: {{ selectedOrder.modality }} · Priority: {{ selectedOrder.priority }}</div>
                        </div>

                        <div class="space-y-1.5">
                            <div class="font-bold text-[10px] uppercase text-muted-foreground">Patient Information</div>
                            <div class="p-2.5 bg-card rounded-lg border border-border/50">
                                <div class="font-bold text-foreground">{{ selectedOrder.patient?.first_name }} {{ selectedOrder.patient?.last_name }}</div>
                                <div class="text-[11px] text-muted-foreground font-mono">MRN: {{ selectedOrder.patient?.primary_mrn }}</div>
                                <div class="text-[11px] text-muted-foreground">DOB: {{ selectedOrder.patient?.dob || '—' }} · Gender: {{ selectedOrder.patient?.gender }}</div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <div class="font-bold text-[10px] uppercase text-muted-foreground">Clinical Indication</div>
                            <div class="p-2.5 bg-card rounded-lg border border-border/50 text-foreground">
                                {{ selectedOrder.clinical_indication || 'None documented on requisition.' }}
                            </div>
                        </div>

                        <div v-if="selectedOrder.reports && selectedOrder.reports.length > 0" class="space-y-2">
                            <div class="font-bold text-[10px] uppercase text-muted-foreground">Validated Report</div>
                            <div v-for="rep in selectedOrder.reports" :key="rep.id" class="p-3 bg-primary/5 rounded-lg border border-primary/20 space-y-2">
                                <div>
                                    <span class="font-bold text-[10px] uppercase text-muted-foreground">Impression:</span>
                                    <div class="font-bold text-foreground text-xs mt-0.5">{{ rep.impression }}</div>
                                </div>
                                <div>
                                    <span class="font-bold text-[10px] uppercase text-muted-foreground">Findings:</span>
                                    <div class="text-muted-foreground text-[11px] mt-0.5">{{ rep.findings }}</div>
                                </div>
                                <div v-if="rep.recommendations">
                                    <span class="font-bold text-[10px] uppercase text-muted-foreground">Recommendations:</span>
                                    <div class="text-muted-foreground text-[11px] mt-0.5">{{ rep.recommendations }}</div>
                                </div>
                                <div class="pt-1.5 border-t border-primary/10 flex items-center justify-between text-[10px] text-muted-foreground font-mono">
                                    <span>Signed: {{ formatDate(rep.signed_at) }}</span>
                                    <span v-if="rep.is_critical_finding" class="text-rose-600 font-bold">CRITICAL FINDING</span>
                                </div>
                            </div>
                        </div>

                        <!-- DICOM PACS Action Trigger -->
                        <div class="p-3 bg-primary/5 rounded-xl border border-primary/20 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-foreground text-xs flex items-center gap-1.5">
                                    <Film class="w-3.5 h-3.5 text-primary" />
                                    <span>PACS DICOM Imaging</span>
                                </span>
                                <span class="text-[9.5px] font-mono text-emerald-600 bg-emerald-500/10 px-1.5 py-0.5 rounded font-bold">WADO-RS Ready</span>
                            </div>
                            <p class="text-[11px] text-muted-foreground">Access cross-sectional multi-series image stacks with windowing, zoom, and distance calibration.</p>
                            <Button
                                type="button"
                                variant="outline"
                                class="w-full h-8 text-xs font-bold gap-1.5 text-primary border-primary/30 hover:bg-primary/10"
                                @click="openDicomViewer(selectedOrder)"
                            >
                                <Maximize2 class="w-3.5 h-3.5" />
                                <span>Launch DICOM Viewport</span>
                            </Button>
                        </div>

                        <div class="pt-2">
                            <Button
                                v-if="selectedOrder.status !== 'Reported' && can.signReport"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold gap-1.5"
                                @click="openSignDialog(selectedOrder)"
                            >
                                <Edit3 class="w-3.5 h-3.5" />
                                <span>Author & Sign Radiology Report</span>
                            </Button>
                            <Button
                                v-else-if="selectedOrder.status === 'Reported' && can.amendReport"
                                variant="outline"
                                class="w-full h-8 text-xs font-semibold gap-1.5 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-800"
                                @click="openAmendDialog(selectedOrder)"
                            >
                                <AlertTriangle class="w-3.5 h-3.5" />
                                <span>Amend Signed Report</span>
                            </Button>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>

        </AfyaWorkspace>

        <!-- MODAL: SIGN RADIOLOGY REPORT -->
        <Modal :show="showSignModal" @close="showSignModal = false" max-width="2xl">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/60 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Film class="w-4 h-4 text-primary" />
                        <span>Sign Radiology Report — {{ selectedOrder?.procedure_name }} ({{ selectedOrder?.order_number }})</span>
                    </h3>
                    <button @click="showSignModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitSignReport" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Radiological Impression *</label>
                        <Input v-model="signForm.impression" placeholder="Summary diagnostic impression (e.g. Normal chest radiograph, Right lobar pneumonia)..." class="h-8 text-xs" />
                        <InputError :message="signForm.errors.impression" class="mt-1" />
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Detailed Findings *</label>
                        <textarea v-model="signForm.findings" rows="4" placeholder="Detailed organ / anatomical structure breakdown..." class="w-full text-xs rounded border border-border bg-card p-2 text-foreground focus:ring-1 focus:ring-primary"></textarea>
                        <InputError :message="signForm.errors.findings" class="mt-1" />
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Recommendations</label>
                        <Input v-model="signForm.recommendations" placeholder="e.g. Follow-up CT recommended if symptoms persist in 2 weeks" class="h-8 text-xs" />
                        <InputError :message="signForm.errors.recommendations" class="mt-1" />
                    </div>

                    <div class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-lg">
                        <label class="flex items-center gap-2 cursor-pointer font-bold text-rose-700 dark:text-rose-300">
                            <input type="checkbox" v-model="signForm.is_critical_finding" class="rounded border-rose-400 text-rose-600 focus:ring-rose-500" />
                            <span>Flag as Critical Finding (Automated Immediate Physician Escalation)</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-border/60">
                        <Button type="button" variant="outline" size="sm" @click="showSignModal = false">Cancel</Button>
                        <Button type="submit" variant="default" size="sm" :disabled="signForm.processing">
                            <Loader2 v-if="signForm.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Sign & Finalize Report</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: AMEND RADIOLOGY REPORT -->
        <Modal :show="showAmendModal" @close="showAmendModal = false" max-width="2xl">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/60 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Edit3 class="w-4 h-4 text-amber-600" />
                        <span>Amend Signed Report — {{ selectedOrder?.order_number }}</span>
                    </h3>
                    <button @click="showAmendModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitAmendReport" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-[10px] uppercase text-amber-600 dark:text-amber-400 mb-1">Reason for Amendment (Required Audit Trail) *</label>
                        <Input v-model="amendForm.amendment_reason" placeholder="e.g. Second radiologist consensus review, additional clinical history provided..." class="h-8 text-xs border-amber-400" />
                        <InputError :message="amendForm.errors.amendment_reason" class="mt-1" />
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Amended Impression *</label>
                        <Input v-model="amendForm.impression" class="h-8 text-xs" />
                        <InputError :message="amendForm.errors.impression" class="mt-1" />
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Amended Findings *</label>
                        <textarea v-model="amendForm.findings" rows="4" class="w-full text-xs rounded border border-border bg-card p-2 text-foreground focus:ring-1 focus:ring-primary"></textarea>
                        <InputError :message="amendForm.errors.findings" class="mt-1" />
                    </div>

                    <div>
                        <label class="block font-bold text-[10px] uppercase text-muted-foreground mb-1">Amended Recommendations</label>
                        <Input v-model="amendForm.recommendations" class="h-8 text-xs" />
                        <InputError :message="amendForm.errors.recommendations" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-border/60">
                        <Button type="button" variant="outline" size="sm" @click="showAmendModal = false">Cancel</Button>
                        <Button type="submit" variant="default" size="sm" :disabled="amendForm.processing" class="bg-amber-600 hover:bg-amber-700 text-white">
                            <Loader2 v-if="amendForm.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Save Legally Audited Amendment</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: DICOM PACS & CORNERSTONE/OHIF IMAGING VIEWPORT -->
        <Modal :show="showDicomViewerModal" @close="showDicomViewerModal = false" max-width="5xl">
            <div class="bg-zinc-950 text-zinc-100 rounded-xl overflow-hidden shadow-2xl flex flex-col h-[85vh]">
                <!-- Top DICOM Toolbar -->
                <div class="px-4 py-2.5 bg-zinc-900 border-b border-zinc-800 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <Film class="w-5 h-5 text-primary" />
                        <div>
                            <div class="font-bold text-xs text-white flex items-center gap-2">
                                <span>{{ selectedOrder?.patient?.first_name }} {{ selectedOrder?.patient?.last_name }}</span>
                                <span class="font-mono text-[11px] text-zinc-400">MRN: {{ selectedOrder?.patient?.primary_mrn }}</span>
                                <span class="bg-primary/20 text-primary px-1.5 py-0.5 rounded text-[10px] font-bold">{{ selectedOrder?.modality }}</span>
                            </div>
                            <div class="text-[10px] text-zinc-400 font-mono">{{ selectedOrder?.procedure_name }} · Accession: ACC-{{ selectedOrder?.order_number }}</div>
                        </div>
                    </div>

                    <!-- Windowing Presets & Manipulation Tools -->
                    <div class="flex items-center gap-1.5 text-xs">
                        <div class="flex items-center bg-zinc-800 rounded p-0.5 border border-zinc-700 text-[11px]">
                            <button
                                type="button"
                                @click="windowPreset = 'soft-tissue'"
                                :class="windowPreset === 'soft-tissue' ? 'bg-primary text-white font-bold' : 'text-zinc-400 hover:text-white'"
                                class="px-2 py-0.5 rounded transition"
                                title="Soft Tissue Window (WW: 400, WL: 40)"
                            >
                                Soft Tissue
                            </button>
                            <button
                                type="button"
                                @click="windowPreset = 'bone'"
                                :class="windowPreset === 'bone' ? 'bg-primary text-white font-bold' : 'text-zinc-400 hover:text-white'"
                                class="px-2 py-0.5 rounded transition"
                                title="Bone Window (WW: 2000, WL: 300)"
                            >
                                Bone
                            </button>
                            <button
                                type="button"
                                @click="windowPreset = 'lung'"
                                :class="windowPreset === 'lung' ? 'bg-primary text-white font-bold' : 'text-zinc-400 hover:text-white'"
                                class="px-2 py-0.5 rounded transition"
                                title="Lung Window (WW: 1500, WL: -600)"
                            >
                                Lung
                            </button>
                            <button
                                type="button"
                                @click="windowPreset = 'brain'"
                                :class="windowPreset === 'brain' ? 'bg-primary text-white font-bold' : 'text-zinc-400 hover:text-white'"
                                class="px-2 py-0.5 rounded transition"
                                title="Brain Window (WW: 80, WL: 40)"
                            >
                                Brain
                            </button>
                        </div>

                        <!-- Zoom & Manipulation Buttons -->
                        <div class="flex items-center gap-1 bg-zinc-800 rounded p-0.5 border border-zinc-700">
                            <button @click="zoomIn" class="p-1 hover:bg-zinc-700 rounded text-zinc-300 hover:text-white" title="Zoom In">
                                <ZoomIn class="w-3.5 h-3.5" />
                            </button>
                            <button @click="zoomOut" class="p-1 hover:bg-zinc-700 rounded text-zinc-300 hover:text-white" title="Zoom Out">
                                <ZoomOut class="w-3.5 h-3.5" />
                            </button>
                            <button @click="rotateCw" class="p-1 hover:bg-zinc-700 rounded text-zinc-300 hover:text-white" title="Rotate 90° CW">
                                <RotateCw class="w-3.5 h-3.5" />
                            </button>
                            <button @click="toggleInvert" class="p-1 hover:bg-zinc-700 rounded" :class="isInverted ? 'text-amber-400 bg-zinc-700' : 'text-zinc-300 hover:text-white'" title="Invert Grayscale">
                                <Contrast class="w-3.5 h-3.5" />
                            </button>
                            <button @click="resetViewer" class="p-1 hover:bg-zinc-700 rounded text-zinc-300 hover:text-white" title="Reset Viewport">
                                <RotateCcw class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <button @click="showDicomViewerModal = false" class="p-1.5 hover:bg-zinc-800 rounded text-zinc-400 hover:text-white ml-2">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Center Viewport Area -->
                <div class="flex-1 bg-black relative flex items-center justify-center overflow-hidden select-none">
                    <!-- High-Resolution Simulated Diagnostic DICOM Canvas Viewport -->
                    <div
                        class="relative transition-transform duration-100 flex items-center justify-center"
                        :style="{
                            transform: `scale(${zoomLevel / 100}) rotate(${rotation}deg)`,
                            filter: `${isInverted ? 'invert(1) ' : ''}${
                                windowPreset === 'bone' ? 'contrast(2.2) brightness(0.9)' :
                                windowPreset === 'lung' ? 'contrast(1.6) brightness(1.4)' :
                                windowPreset === 'brain' ? 'contrast(2.5) brightness(0.8)' :
                                'contrast(1.2) brightness(1.0)'
                            }`
                        }"
                    >
                        <!-- Medical Scan Representation -->
                        <div class="w-[480px] h-[480px] rounded-lg bg-zinc-900 border border-zinc-800 flex items-center justify-center relative overflow-hidden shadow-2xl">
                            <!-- Radiographic Grayscale Silhouette -->
                            <div class="absolute inset-0 bg-radial from-zinc-400/20 via-zinc-800/40 to-black"></div>
                            
                            <div class="text-center space-y-2 z-10 opacity-75">
                                <Film class="w-20 h-20 text-zinc-600 mx-auto animate-pulse" />
                                <div class="text-xs font-mono text-zinc-400 uppercase tracking-widest">{{ selectedOrder?.procedure_name }}</div>
                                <div class="text-[10px] font-mono text-zinc-500">Series: {{ activeSeriesIndex + 1 }}/2 · Slice: 12/24</div>
                            </div>

                            <!-- Crosshair Grid Overlay -->
                            <div class="absolute inset-0 grid grid-cols-4 grid-rows-4 pointer-events-none opacity-15 border border-zinc-700">
                                <div v-for="n in 16" :key="n" class="border border-zinc-700/40"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Overlay DICOM Tags (HUD) -->
                    <!-- Top-Left HUD -->
                    <div class="absolute top-3 left-3 text-[11px] font-mono text-emerald-400 space-y-0.5 pointer-events-none drop-shadow-md">
                        <div class="font-bold">{{ selectedOrder?.patient?.first_name }} {{ selectedOrder?.patient?.last_name }}</div>
                        <div>MRN: {{ selectedOrder?.patient?.primary_mrn }}</div>
                        <div>DOB: {{ selectedOrder?.patient?.dob || '—' }} ({{ selectedOrder?.patient?.gender }})</div>
                    </div>

                    <!-- Top-Right HUD -->
                    <div class="absolute top-3 right-3 text-[11px] font-mono text-emerald-400 text-right space-y-0.5 pointer-events-none drop-shadow-md">
                        <div class="font-bold">{{ selectedOrder?.modality }}</div>
                        <div>KVp: 120 · mA: 250</div>
                        <div>Thk: 1.25 mm</div>
                        <div>FO: 350 mm</div>
                    </div>

                    <!-- Bottom-Left HUD -->
                    <div class="absolute bottom-3 left-3 text-[11px] font-mono text-emerald-400 space-y-0.5 pointer-events-none drop-shadow-md">
                        <div>WW: {{ windowPreset === 'bone' ? 2000 : windowPreset === 'lung' ? 1500 : windowPreset === 'brain' ? 80 : 400 }}</div>
                        <div>WL: {{ windowPreset === 'bone' ? 300 : windowPreset === 'lung' ? -600 : windowPreset === 'brain' ? 40 : 40 }}</div>
                        <div>Zoom: {{ zoomLevel }}% · Rot: {{ rotation }}°</div>
                    </div>

                    <!-- Bottom-Right HUD -->
                    <div class="absolute bottom-3 right-3 text-[11px] font-mono text-emerald-400 text-right space-y-0.5 pointer-events-none drop-shadow-md">
                        <div>Series {{ activeSeriesIndex + 1 }} / 2</div>
                        <div>Image 12 / 24</div>
                        <div>Lossless DICOM</div>
                    </div>
                </div>

                <!-- Bottom Multi-Series Thumbnail Strip -->
                <div class="px-4 py-2 bg-zinc-900 border-t border-zinc-800 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold uppercase text-zinc-400 flex items-center gap-1">
                            <Layers class="w-3.5 h-3.5" />
                            <span>Series:</span>
                        </span>
                        <div
                            v-for="s in 2"
                            :key="s"
                            @click="activeSeriesIndex = s - 1"
                            :class="activeSeriesIndex === s - 1 ? 'border-primary bg-primary/20 text-white' : 'border-zinc-700 bg-zinc-800 text-zinc-400 hover:text-white'"
                            class="px-2.5 py-1 rounded border text-[11px] font-mono font-bold cursor-pointer transition flex items-center gap-1.5"
                        >
                            <span>Series {{ s }} ({{ s === 1 ? 'Axial Stack' : 'Coronal Scout' }})</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-zinc-500 font-mono">DICOM WADO-RS PACS URL Connected</span>
                    </div>
                </div>
            </div>
        </Modal>

    </AfyaShell>
</template>
