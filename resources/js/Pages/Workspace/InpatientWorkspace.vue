<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Bed, 
    Users, 
    Stethoscope, 
    CheckCircle2, 
    XCircle, 
    AlertTriangle, 
    Clock, 
    Building2, 
    DollarSign, 
    Plus, 
    ArrowRight, 
    ArrowLeftRight, 
    DoorOpen, 
    Activity, 
    FileText, 
    Check, 
    X, 
    Loader2, 
    Search, 
    ShieldCheck, 
    Sparkles,
    Calendar,
    Thermometer,
    HeartPulse,
    Wrench,
    SlidersHorizontal,
    Pill,
    Syringe,
    Printer
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Modal from '@/Components/Modal.vue';
import PatientWristbandPrint from '@/Components/Print/PatientWristbandPrint.vue';

// UI Primitives & Design Foundation
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
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
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    wards: {
        type: Array,
        default: () => [],
    },
    beds: {
        type: Array,
        default: () => [],
    },
    activeAdmissions: {
        type: Array,
        default: () => [],
    },
    dischargedAdmissions: {
        type: Array,
        default: () => [],
    },
    availablePatients: {
        type: Array,
        default: () => [],
    },
    doctors: {
        type: Array,
        default: () => [],
    },
    nurses: {
        type: Array,
        default: () => [],
    },
    wardCabinets: {
        type: Array,
        default: () => [],
    },
    itemMasters: {
        type: Array,
        default: () => [],
    },
    wardStockBalances: {
        type: Array,
        default: () => [],
    },
});

const { preferences, openContext } = useWorkspacePreferences();

const activeSection = ref('bed_map'); // bed_map, census, discharges
const selectedWardFilter = ref('all');
const selectedBed = ref(props.beds?.[0] || null);
const selectedAdmission = ref(props.activeAdmissions?.[0] || null);
const printingWristbandPatient = ref(null);
const printingWristbandAdmission = ref(null);

// Search query
const searchQuery = ref('');

// Modals
const showAdmitModal = ref(false);
const isAdmitting = ref(false);
const admitForm = ref({
    patient_id: '',
    ward_id: '',
    bed_id: '',
    admitting_doctor_id: '',
    admission_reason: '',
    provisional_diagnosis: '',
});

const showTransferModal = ref(false);
const isTransferring = ref(false);
const transferForm = ref({
    admission_id: '',
    to_ward_id: '',
    to_bed_id: '',
    reason: '',
});

const showDischargeModal = ref(false);
const isDischarging = ref(false);
const dischargeForm = ref({
    admission_id: '',
    discharge_disposition: 'Home',
    discharge_summary: '',
});

// e-MAR Modal & Form State
const showMarModal = ref(false);
const isMarSubmitting = ref(false);
const marForm = ref({
    admission_id: '',
    item_master_id: '',
    item_name: '',
    location_id: '',
    batch_number: '',
    dose_quantity: 1,
    dose_unit: 'dose',
    route: 'IV',
    frequency: 'STAT',
    is_dda_narcotic: false,
    witness_by: '',
    witness_pin_verified: false,
    status: 'Administered',
    charge_amount: 0,
    notes: '',
});

const onMarItemChange = () => {
    const selectedItem = props.itemMasters.find(i => i.id === marForm.value.item_master_id);
    if (selectedItem) {
        marForm.value.item_name = selectedItem.name;
        marForm.value.is_dda_narcotic = !!selectedItem.is_dda_narcotic;
        marForm.value.charge_amount = (Number(selectedItem.unit_selling_price) || 0) * (Number(marForm.value.dose_quantity) || 1);
    }
};

const openMarForAdmission = (adm) => {
    selectedAdmission.value = adm;
    const defaultCabinet = props.wardCabinets.find(c => c.name?.toLowerCase().includes(adm.ward?.name?.toLowerCase())) || props.wardCabinets[0];
    const firstItem = props.itemMasters[0] || null;
    marForm.value = {
        admission_id: adm.id,
        item_master_id: firstItem?.id || '',
        item_name: firstItem?.name || '',
        location_id: defaultCabinet?.id || '',
        batch_number: '',
        dose_quantity: 1,
        dose_unit: 'dose',
        route: 'IV',
        frequency: 'STAT',
        is_dda_narcotic: !!firstItem?.is_dda_narcotic,
        witness_by: '',
        witness_pin_verified: false,
        status: 'Administered',
        charge_amount: firstItem ? Number(firstItem.unit_selling_price) : 0,
        notes: '',
    };
    showMarModal.value = true;
};

const submitMar = () => {
    if (!marForm.value.admission_id) return;
    isMarSubmitting.value = true;
    router.post(route('inpatient.admissions.mar.store', marForm.value.admission_id), marForm.value, {
        onSuccess: () => {
            showMarModal.value = false;
            isMarSubmitting.value = false;
        },
        onError: () => {
            isMarSubmitting.value = false;
        }
    });
};

// Midnight Bed Billing Trigger
const isGeneratingCharges = ref(false);
const triggerBedBilling = () => {
    if (!confirm('Run midnight bed & board billing engine for all currently admitted patients? This will generate daily accommodation charges.')) {
        return;
    }
    isGeneratingCharges.value = true;
    router.post(route('inpatient.generate-bed-charges'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isGeneratingCharges.value = false;
        }
    });
};

// Selection handlers
const selectBedRecord = (bed) => {
    selectedBed.value = bed;
    if (bed.current_admission) {
        selectedAdmission.value = props.activeAdmissions.find(a => a.id === bed.current_admission.id) || bed.current_admission;
    } else {
        selectedAdmission.value = null;
    }
    openContext();
};

const selectAdmissionRecord = (adm) => {
    selectedAdmission.value = adm;
    selectedBed.value = adm.bed || null;
    openContext();
};

// Top Aggregates
const censusMetrics = computed(() => {
    const totalBeds = props.beds.length;
    const occupiedBeds = props.beds.filter(b => b.status === 'Occupied').length;
    const availableBeds = props.beds.filter(b => b.status === 'Available').length;
    const cleaningBeds = props.beds.filter(b => b.status === 'Cleaning' || b.status === 'Maintenance').length;
    const rate = totalBeds > 0 ? Math.round((occupiedBeds / totalBeds) * 100) : 0;
    
    return {
        totalBeds,
        occupiedBeds,
        availableBeds,
        cleaningBeds,
        rate,
        admissionsCount: props.activeAdmissions.length,
    };
});

// Filtered Beds for Grid View
const filteredBeds = computed(() => {
    return props.beds.filter(b => {
        if (selectedWardFilter.value !== 'all' && b.ward_id !== selectedWardFilter.value) {
            return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const bedMatch = b.bed_number.toLowerCase().includes(q);
            const wardMatch = b.ward?.name.toLowerCase().includes(q);
            const patMatch = b.current_admission?.patient && 
                (`${b.current_admission.patient.first_name} ${b.current_admission.patient.last_name} ${b.current_admission.patient.primary_mrn}`).toLowerCase().includes(q);
            return bedMatch || wardMatch || patMatch;
        }
        return true;
    });
});

// Available beds for dropdown selection in Admit/Transfer modals
const availableBedsForAdmit = computed(() => {
    if (!admitForm.value.ward_id) {
        return props.beds.filter(b => b.status === 'Available');
    }
    return props.beds.filter(b => b.status === 'Available' && b.ward_id === admitForm.value.ward_id);
});

const availableBedsForTransfer = computed(() => {
    if (!transferForm.value.to_ward_id) {
        return props.beds.filter(b => b.status === 'Available');
    }
    return props.beds.filter(b => b.status === 'Available' && b.ward_id === transferForm.value.to_ward_id);
});

// Open Modals
const openAdmitForBed = (bed = null) => {
    admitForm.value = {
        patient_id: props.availablePatients[0]?.id || '',
        ward_id: bed ? bed.ward_id : (props.wards[0]?.id || ''),
        bed_id: bed ? bed.id : (props.beds.find(b => b.status === 'Available')?.id || ''),
        admitting_doctor_id: props.doctors[0]?.id || '',
        admission_reason: 'Acute illness requiring intravenous therapy and inpatient observation',
        provisional_diagnosis: 'Severe Malaria with Anemia',
    };
    showAdmitModal.value = true;
};

const openTransferForAdmission = (adm) => {
    transferForm.value = {
        admission_id: adm.id,
        to_ward_id: props.wards.find(w => w.id !== adm.ward_id)?.id || props.wards[0]?.id || '',
        to_bed_id: '',
        reason: 'Step-down care / clinical stability transfer',
    };
    showTransferModal.value = true;
};

const openDischargeForAdmission = (adm) => {
    dischargeForm.value = {
        admission_id: adm.id,
        discharge_disposition: 'Home',
        discharge_summary: 'Patient clinically stable, afebrile, and successfully completed treatment course. Discharged with oral medications and follow-up in 7 days.',
    };
    showDischargeModal.value = true;
};

// Form Submissions
const submitAdmit = () => {
    isAdmitting.value = true;
    router.post(route('inpatient.admissions.store'), admitForm.value, {
        onFinish: () => {
            isAdmitting.value = false;
            showAdmitModal.value = false;
        }
    });
};

const submitTransfer = () => {
    isTransferring.value = true;
    router.post(route('inpatient.admissions.transfer', transferForm.value.admission_id), transferForm.value, {
        onFinish: () => {
            isTransferring.value = false;
            showTransferModal.value = false;
        }
    });
};

const submitDischarge = () => {
    isDischarging.value = true;
    router.post(route('inpatient.admissions.discharge', dischargeForm.value.admission_id), dischargeForm.value, {
        onFinish: () => {
            isDischarging.value = false;
            showDischargeModal.value = false;
        }
    });
};

const markBedCleaned = (bed) => {
    router.post(route('inpatient.beds.status', bed.id), { status: 'Available' });
};

// Ward & Bed Topology Layout State & Methods
const showCreateWardModal = ref(false);
const showEditWardModal = ref(false);
const targetWardItem = ref(null);
const showCreateBedModal = ref(false);
const targetWardForBed = ref(null);
const isWardSubmitting = ref(false);

const createWardForm = ref({
    name: '',
    code: '',
    ward_type: 'General',
    gender_restriction: 'None',
    floor_location: 'Ground Floor',
    daily_base_rate: 25000,
});

const editWardForm = ref({
    name: '',
    code: '',
    ward_type: 'General',
    gender_restriction: 'None',
    floor_location: '',
    daily_base_rate: 25000,
    is_active: true,
});

const createBedForm = ref({
    ward_id: '',
    bed_number: '',
    bed_type: 'Standard',
    daily_rate_amount: 25000,
    notes: '',
});

const openCreateWardModal = () => {
    createWardForm.value = {
        name: '',
        code: `WRD-${Math.floor(10 + Math.random() * 90)}`,
        ward_type: 'General',
        gender_restriction: 'None',
        floor_location: 'First Floor',
        daily_base_rate: 25000,
    };
    showCreateWardModal.value = true;
};

const submitCreateWard = () => {
    isWardSubmitting.value = true;
    router.post(route('inpatient.wards.store'), createWardForm.value, {
        onFinish: () => {
            isWardSubmitting.value = false;
            showCreateWardModal.value = false;
        }
    });
};

const openEditWardModal = (ward) => {
    targetWardItem.value = ward;
    editWardForm.value = {
        name: ward.name,
        code: ward.code,
        ward_type: ward.ward_type || 'General',
        gender_restriction: ward.gender_restriction || 'None',
        floor_location: ward.floor_location || '',
        daily_base_rate: Number(ward.daily_base_rate || 25000),
        is_active: ward.is_active !== undefined ? !!ward.is_active : true,
    };
    showEditWardModal.value = true;
};

const submitEditWard = () => {
    if (!targetWardItem.value) return;
    isWardSubmitting.value = true;
    router.put(route('inpatient.wards.update', targetWardItem.value.id), editWardForm.value, {
        onFinish: () => {
            isWardSubmitting.value = false;
            showEditWardModal.value = false;
        }
    });
};

const openCreateBedModal = (ward = null) => {
    targetWardForBed.value = ward;
    const selectedWard = ward || props.wards?.[0];
    createBedForm.value = {
        ward_id: selectedWard ? selectedWard.id : '',
        bed_number: `B-${Math.floor(100 + Math.random() * 900)}`,
        bed_type: 'Standard',
        daily_rate_amount: Number(selectedWard?.daily_base_rate || 25000),
        notes: 'Equipped with basic bedside monitor and oxygen outlet',
    };
    showCreateBedModal.value = true;
};

const submitCreateBed = () => {
    isWardSubmitting.value = true;
    router.post(route('inpatient.beds.store'), createBedForm.value, {
        onFinish: () => {
            isWardSubmitting.value = false;
            showCreateBedModal.value = false;
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatCurrency = (val) => {
    return Number(val || 0).toLocaleString('en-US');
};
</script>

<template>
    <Head title="Inpatient Ward & Bed Management — AfyaNova Workstation" />

    <AfyaShell active-module="inpatient">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Inpatient Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Inpatient Wards"
                    :icon="Bed"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        IPD Workstation
                    </div>
                    
                    <AfyaSidebarItem
                        label="Hospital Bed Map"
                        :icon="Bed"
                        :badge="censusMetrics.availableBeds"
                        :active="activeSection === 'bed_map'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'bed_map'"
                    />
                    
                    <AfyaSidebarItem
                        label="Active Inpatient Census"
                        :icon="Users"
                        :badge="censusMetrics.admissionsCount"
                        :active="activeSection === 'census'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'census'"
                    />

                    <AfyaSidebarItem
                        label="Discharges & Transfers"
                        :icon="DoorOpen"
                        :active="activeSection === 'discharges'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'discharges'"
                    />

                    <AfyaSidebarItem
                        label="Ward & Bed Topology"
                        :icon="Building2"
                        :active="activeSection === 'topology'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'topology'"
                    />

                    <div v-if="state !== 'collapsed'" class="pt-2 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border/40 mt-1">
                        Wards Census
                    </div>

                    <div v-if="state !== 'collapsed'" class="px-2 space-y-0.5">
                        <button
                            v-for="w in wards"
                            :key="w.id"
                            class="w-full text-left px-2 py-1 rounded text-[11px] flex items-center justify-between hover:bg-muted/50 transition"
                            :class="selectedWardFilter === w.id ? 'bg-primary/10 text-primary font-bold' : 'text-muted-foreground'"
                            @click="selectedWardFilter = w.id; activeSection = 'bed_map'"
                        >
                            <span class="truncate">{{ w.name }}</span>
                            <span class="text-[10px] font-mono font-bold">{{ w.occupied_beds_count }}/{{ w.total_beds_count }}</span>
                        </button>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN WORK AREA -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Inpatient', href: route('inpatient.workspace') },
                        { label: activeSection === 'bed_map' ? 'Hospital Bed Map' : (activeSection === 'census' ? 'Active Inpatient Census' : (activeSection === 'topology' ? 'Ward & Bed Facility Topology' : 'Discharge & Transfer Registry')), active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <ShieldCheck class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span>Occupancy: <strong>{{ censusMetrics.rate }}%</strong></span>
                            </span>
                            <Button
                                v-if="can.generateBedCharges"
                                variant="outline"
                                size="sm"
                                class="h-7.5 px-3 text-xs font-bold gap-1.5 text-indigo-700 dark:text-indigo-400 border-indigo-300 dark:border-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 shadow-2xs"
                                :disabled="isGeneratingCharges"
                                @click="triggerBedBilling"
                                title="Post midnight bed & board accommodation charges to active admissions"
                            >
                                <Loader2 v-if="isGeneratingCharges" class="w-3.5 h-3.5 animate-spin" />
                                <Clock v-else class="w-3.5 h-3.5 text-indigo-600" />
                                <span>Midnight Bed Billing</span>
                            </Button>
                            <Button
                                v-if="can.admit"
                                variant="default"
                                size="sm"
                                class="h-7.5 px-3 text-xs font-semibold gap-1.5 shadow-2xs"
                                @click="openAdmitForBed()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Admit Patient</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Top Bed Census Metrics Strip (Clean Cards, No Outside Border) -->
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Total Beds</div>
                                <div class="text-base font-bold text-foreground font-mono">{{ censusMetrics.totalBeds }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Occupied (IPD)</div>
                                <div class="text-base font-bold text-rose-600 dark:text-rose-400 font-mono">{{ censusMetrics.occupiedBeds }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Available Beds</div>
                                <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ censusMetrics.availableBeds }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Sanitizing / Clean</div>
                                <div class="text-base font-bold text-amber-600 dark:text-amber-400 font-mono">{{ censusMetrics.cleaningBeds }}</div>
                            </div>
                            <div class="p-2.5 px-3 bg-card rounded-lg shadow-2xs flex flex-col justify-between space-y-0.5 col-span-2 sm:col-span-1">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Occupancy Rate</div>
                                <div class="text-base font-bold text-primary font-mono">{{ censusMetrics.rate }}%</div>
                            </div>
                        </div>

                        <!-- Ward Filter & Search Strip (Seamless Container) -->
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-card p-2 rounded-lg shadow-2xs">
                            <div class="flex items-center gap-1.5 flex-1 max-w-xs">
                                <SearchInput
                                    v-model="searchQuery"
                                    placeholder="Search bed, ward, patient or MRN..."
                                    size="sm"
                                />
                            </div>
                            
                            <div class="flex items-center gap-1 overflow-x-auto">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-6 px-2 text-[10.5px]"
                                    :class="{ 'bg-primary text-primary-foreground font-semibold': selectedWardFilter === 'all' }"
                                    @click="selectedWardFilter = 'all'"
                                >
                                    All Wards
                                </Button>
                                <Button
                                    v-for="w in wards"
                                    :key="w.id"
                                    size="sm"
                                    variant="outline"
                                    class="h-6 px-2 text-[10.5px]"
                                    :class="{ 'bg-primary text-primary-foreground font-semibold': selectedWardFilter === w.id }"
                                    @click="selectedWardFilter = w.id"
                                >
                                    {{ w.name }} ({{ w.available_beds_count }})
                                </Button>
                            </div>
                        </div>

                        <!-- ================= VIEW 1: VISUAL WARD & BED MAP (COMPACT SLEEK BEDS) ================= -->
                        <div v-if="activeSection === 'bed_map'" class="w-full">
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                <div
                                    v-for="bed in filteredBeds"
                                    :key="bed.id"
                                    class="p-2 rounded-lg transition-all cursor-pointer bg-card flex flex-col justify-between shadow-2xs hover:shadow-sm select-none gap-1.5"
                                    :class="{
                                        'bg-emerald-500/5 hover:bg-emerald-500/10': bed.status === 'Available',
                                        'bg-rose-500/5 hover:bg-rose-500/10': bed.status === 'Occupied',
                                        'bg-amber-500/5 hover:bg-amber-500/10': bed.status === 'Cleaning',
                                        'bg-muted/20 hover:bg-muted/30': bed.status === 'Maintenance',
                                        'ring-1.5 ring-primary': selectedBed?.id === bed.id
                                    }"
                                    @click="selectBedRecord(bed)"
                                >
                                    <!-- Top Row: Bed Number + Ward + Status -->
                                    <div class="flex items-center justify-between gap-1">
                                        <div class="flex items-center gap-1.5 truncate">
                                            <span class="font-mono font-bold text-xs text-foreground">{{ bed.bed_number }}</span>
                                            <span class="text-[10px] text-muted-foreground truncate">{{ bed.ward?.name }}</span>
                                        </div>
                                        <span 
                                            class="px-1.5 py-0.2 rounded text-[8.5px] font-bold shrink-0"
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': bed.status === 'Available',
                                                'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300': bed.status === 'Occupied',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': bed.status === 'Cleaning',
                                                'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300': bed.status === 'Maintenance',
                                            }"
                                        >
                                            {{ bed.status }}
                                        </span>
                                    </div>

                                    <!-- Middle Content: Compact 1-line or 2-line summary -->
                                    <!-- Occupied: Patient Name & Stay -->
                                    <div v-if="bed.status === 'Occupied' && bed.current_admission" class="flex items-center justify-between text-[10.5px] truncate gap-1 bg-card/60 px-1.5 py-1 rounded">
                                        <div class="font-semibold text-foreground truncate max-w-[130px]">
                                            {{ bed.current_admission.patient?.first_name }} {{ bed.current_admission.patient?.last_name }}
                                        </div>
                                        <div class="font-mono text-[9.5px] text-muted-foreground shrink-0">
                                            {{ bed.current_admission.length_of_stay_days }}d stay
                                        </div>
                                    </div>

                                    <!-- Available: Ready indicator & Type -->
                                    <div v-else-if="bed.status === 'Available'" class="flex items-center justify-between text-[10.5px] text-muted-foreground px-1.5 py-0.5">
                                        <span class="text-emerald-700 dark:text-emerald-400 font-medium flex items-center gap-1 text-[10px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Ready ({{ bed.bed_type }})
                                        </span>
                                        <span class="font-mono text-[9.5px]">TZS {{ formatCurrency(bed.daily_rate_amount) }}/d</span>
                                    </div>

                                    <!-- Cleaning: Sanitizing indicator -->
                                    <div v-else-if="bed.status === 'Cleaning'" class="flex items-center justify-between text-[10.5px] text-amber-700 dark:text-amber-400 px-1.5 py-0.5">
                                        <span class="font-medium flex items-center gap-1 text-[10px]">
                                            <Sparkles class="w-2.5 h-2.5 text-amber-500" />
                                            Sanitizing
                                        </span>
                                        <span class="font-mono text-[9.5px] text-muted-foreground">{{ bed.bed_type }}</span>
                                    </div>

                                    <!-- Maintenance -->
                                    <div v-else class="flex items-center justify-between text-[10px] text-muted-foreground px-1.5 py-0.5">
                                        <span>Under Maintenance</span>
                                        <span class="font-mono text-[9.5px]">{{ bed.bed_type }}</span>
                                    </div>

                                    <!-- Bottom Row: Rate + Action Button -->
                                    <div class="flex items-center justify-between pt-1 border-t border-border/30 text-[10px]">
                                        <span class="font-mono text-[9.5px] text-muted-foreground truncate max-w-[120px]" v-if="bed.status === 'Occupied'">
                                            MRN: {{ bed.current_admission?.patient?.primary_mrn }}
                                        </span>
                                        <span class="font-mono text-[9.5px] text-muted-foreground" v-else>
                                            TZS {{ formatCurrency(bed.daily_rate_amount) }}/d
                                        </span>

                                        <Button
                                            v-if="bed.status === 'Available' && can.admit"
                                            variant="default"
                                            size="sm"
                                            class="h-5 px-2 text-[10px] font-semibold shadow-2xs"
                                            @click.stop="openAdmitForBed(bed)"
                                        >
                                            Admit
                                        </Button>

                                        <Button
                                            v-else-if="bed.status === 'Cleaning' && can.updateBedStatus"
                                            variant="outline"
                                            size="sm"
                                            class="h-5 px-2 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-800"
                                            @click.stop="markBedCleaned(bed)"
                                        >
                                            <Check class="w-2.5 h-2.5 mr-0.5" />
                                            <span>Ready</span>
                                        </Button>

                                        <Button
                                            v-else-if="bed.status === 'Occupied' && bed.current_admission"
                                            variant="subtle"
                                            size="sm"
                                            class="h-5 px-2 text-[10px] font-semibold"
                                            @click.stop="selectAdmissionRecord(bed.current_admission)"
                                        >
                                            Manage
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= VIEW 2: ACTIVE INPATIENTS CENSUS ================= -->
                        <div v-else-if="activeSection === 'census'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <Users class="w-3.5 h-3.5 text-primary" />
                                        <span>Hospital Active Inpatient Census ({{ activeAdmissions.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3">Admission #</TableHead>
                                            <TableHead class="py-1 px-3">Patient Details</TableHead>
                                            <TableHead class="py-1 px-3">Ward & Bed</TableHead>
                                            <TableHead class="py-1 px-3">Attending Physician</TableHead>
                                            <TableHead class="py-1 px-3 text-center">Stay</TableHead>
                                            <TableHead class="py-1 px-3">Clinical Indication</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="adm in activeAdmissions"
                                            :key="adm.id"
                                            class="h-9 border-b border-border/30 hover:bg-muted/20 cursor-pointer transition-colors"
                                            :class="{ 'bg-primary/5': selectedAdmission?.id === adm.id }"
                                            @click="selectAdmissionRecord(adm)"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px]">
                                                {{ adm.admission_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-bold text-foreground truncate max-w-[140px] text-[11px]">{{ adm.patient?.first_name }} {{ adm.patient?.last_name }}</div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">MRN: {{ adm.patient?.primary_mrn }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <div class="font-semibold text-foreground text-[11px]">{{ adm.ward?.name }}</div>
                                                <div class="text-[9.5px] font-mono text-muted-foreground">{{ adm.bed?.bed_number }}</div>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                Dr. {{ adm.admitting_doctor?.first_name }} {{ adm.admitting_doctor?.last_name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono font-bold text-center">
                                                <span class="px-1.5 py-0.2 rounded bg-muted text-[10.5px]">{{ adm.length_of_stay_days }}d</span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[10.5px] truncate max-w-[180px]">
                                                {{ adm.provisional_diagnosis || adm.admission_reason }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <Button
                                                        v-if="can.transfer"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10.5px] font-semibold"
                                                        @click.stop="openTransferForAdmission(adm)"
                                                    >
                                                        Transfer
                                                    </Button>
                                                    <Button
                                                        v-if="can.discharge"
                                                        variant="default"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10.5px] font-semibold"
                                                        @click.stop="openDischargeForAdmission(adm)"
                                                    >
                                                        Discharge
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="activeAdmissions.length === 0">
                                            <TableCell colspan="7" class="text-center py-8 text-muted-foreground text-xs">
                                                No active inpatients currently admitted.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 3: DISCHARGES & TRANSFERS ================= -->
                        <div v-else-if="activeSection === 'discharges'" class="w-full">
                            <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs flex flex-col">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-foreground uppercase tracking-wider">
                                        <DoorOpen class="w-3.5 h-3.5 text-primary" />
                                        <span>Historical Discharge & Transfer Registry ({{ dischargedAdmissions.length }})</span>
                                    </div>
                                </div>

                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                            <TableHead class="py-1 px-3">Admission #</TableHead>
                                            <TableHead class="py-1 px-3">Patient</TableHead>
                                            <TableHead class="py-1 px-3">Ward & Bed Released</TableHead>
                                            <TableHead class="py-1 px-3">Period</TableHead>
                                            <TableHead class="py-1 px-3">Disposition</TableHead>
                                            <TableHead class="py-1 px-3">Discharge Summary</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="d in dischargedAdmissions"
                                            :key="d.id"
                                            class="h-8.5 border-b border-border/30 hover:bg-muted/20 transition-colors"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-muted-foreground text-[10.5px]">
                                                {{ d.admission_number }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-semibold text-foreground text-[11px]">
                                                {{ d.patient?.first_name }} {{ d.patient?.last_name }}
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px]">
                                                {{ d.ward?.name }} ({{ d.bed?.bed_number }})
                                            </TableCell>
                                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">
                                                {{ formatDate(d.admitted_at) }} → {{ formatDate(d.discharged_at) }} ({{ d.length_of_stay_days }}d)
                                            </TableCell>
                                            <TableCell class="py-1 px-3">
                                                <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                    {{ d.discharge_disposition }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-1 px-3 text-muted-foreground text-[10.5px] truncate max-w-[200px]">
                                                {{ d.discharge_summary }}
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-if="dischargedAdmissions.length === 0">
                                            <TableCell colspan="6" class="text-center py-6 text-muted-foreground text-xs">
                                                No completed discharges recorded yet.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ================= VIEW 4: WARD & BED TOPOLOGY ================= -->
                        <div v-else-if="activeSection === 'topology'" class="w-full space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Building2 class="w-4 h-4 text-primary" />
                                    <h3 class="font-bold text-sm text-foreground">Inpatient Hospital Wards & Bed Topologies</h3>
                                </div>
                                <Button
                                    variant="default"
                                    size="sm"
                                    class="h-7 text-xs font-bold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                                    @click="openCreateWardModal"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Register New Ward</span>
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div
                                    v-for="ward in wards"
                                    :key="ward.id"
                                    class="bg-card rounded-xl border border-border/60 shadow-2xs overflow-hidden"
                                >
                                    <!-- Ward Header -->
                                    <div class="px-4 py-3 bg-muted/20 border-b border-border/40 flex items-center justify-between">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-bold text-sm text-foreground">{{ ward.name }}</h4>
                                                <span class="px-1.5 py-0.5 rounded font-mono text-[9px] font-bold bg-primary/10 text-primary">
                                                    {{ ward.code }}
                                                </span>
                                                <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-muted text-muted-foreground">
                                                    {{ ward.ward_type }}
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-muted-foreground mt-0.5">
                                                Floor: {{ ward.floor_location || 'Main' }} • Gender: {{ ward.gender_restriction }} • Base Rate: <strong class="text-foreground font-mono">TZS {{ formatCurrency(ward.daily_base_rate) }} / night</strong>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-6 px-2 text-[10.5px] font-semibold"
                                                @click="openEditWardModal(ward)"
                                            >
                                                Edit Ward
                                            </Button>
                                            <Button
                                                variant="default"
                                                size="sm"
                                                class="h-6 px-2 text-[10.5px] font-bold gap-1 bg-primary text-primary-foreground"
                                                @click="openCreateBedModal(ward)"
                                            >
                                                <Plus class="w-3 h-3" />
                                                <span>Add Bed</span>
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Beds in this ward -->
                                    <div class="p-3">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
                                            <div
                                                v-for="b in beds.filter(bed => bed.ward_id === ward.id)"
                                                :key="b.id"
                                                class="p-2 rounded-lg border text-xs space-y-1 transition"
                                                :class="{
                                                    'bg-emerald-500/5 border-emerald-500/30': b.status === 'Available',
                                                    'bg-rose-500/5 border-rose-500/30': b.status === 'Occupied',
                                                    'bg-amber-500/5 border-amber-500/30': b.status === 'Cleaning',
                                                    'bg-muted/40 border-border/40': b.status === 'Maintenance' || b.status === 'Reserved'
                                                }"
                                            >
                                                <div class="flex items-center justify-between font-mono font-bold">
                                                    <span class="text-foreground text-[11px]">{{ b.bed_number }}</span>
                                                    <span
                                                        class="w-2 h-2 rounded-full"
                                                        :class="{
                                                            'bg-emerald-500': b.status === 'Available',
                                                            'bg-rose-500': b.status === 'Occupied',
                                                            'bg-amber-500': b.status === 'Cleaning',
                                                            'bg-muted-foreground': b.status === 'Maintenance' || b.status === 'Reserved'
                                                        }"
                                                    ></span>
                                                </div>
                                                <div class="text-[9.5px] text-muted-foreground truncate">{{ b.bed_type }}</div>
                                                <div class="text-[9px] font-mono text-muted-foreground">
                                                    TZS {{ formatCurrency(b.daily_rate_amount || ward.daily_base_rate) }}
                                                </div>
                                                <div class="text-[9px] font-bold" :class="b.status === 'Occupied' ? 'text-rose-600' : 'text-emerald-600'">
                                                    {{ b.status }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!beds.filter(bed => bed.ward_id === ward.id).length" class="text-center py-3 text-muted-foreground italic text-xs">
                                            No physical beds registered in this ward yet. Click "Add Bed" to provision.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: INPATIENT CONTEXT INSPECTOR -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Inpatient 360 Inspector"
                    :icon="Bed"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedAdmission" class="space-y-2.5 text-xs">
                        <AfyaPatientIdentity v-if="selectedAdmission.patient" :patient="selectedAdmission.patient">
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300">
                                Inpatient
                            </span>
                        </AfyaPatientIdentity>

                        <!-- Ward & Bed Assignment Card -->
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Current Inpatient Assignment
                            </div>
                            <div class="space-y-1 text-[10.5px]">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Admission #:</span>
                                    <span class="font-mono font-bold text-primary">{{ selectedAdmission.admission_number }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Ward:</span>
                                    <span class="font-bold text-foreground">{{ selectedAdmission.ward?.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Bed Number:</span>
                                    <span class="font-mono font-bold">{{ selectedAdmission.bed?.bed_number }} ({{ selectedAdmission.bed?.bed_type }})</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Admitted At:</span>
                                    <span class="font-mono">{{ formatDate(selectedAdmission.admitted_at) }} ({{ selectedAdmission.length_of_stay_days }}d stay)</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Attending Doctor:</span>
                                    <span class="font-semibold text-foreground">Dr. {{ selectedAdmission.admitting_doctor?.first_name }} {{ selectedAdmission.admitting_doctor?.last_name }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Provisional Diagnosis & Reason -->
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                                Clinical Indication
                            </div>
                            <div class="font-bold text-foreground text-[10.5px]">
                                {{ selectedAdmission.provisional_diagnosis || 'Inpatient Care' }}
                            </div>
                            <p class="text-muted-foreground text-[10px] italic leading-relaxed">
                                "{{ selectedAdmission.admission_reason }}"
                            </p>
                        </div>

                        <!-- Bed Transfer History -->
                        <div v-if="selectedAdmission.transfers && selectedAdmission.transfers.length > 0" class="p-2 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                                <ArrowLeftRight class="w-3 h-3 text-primary" />
                                <span>Transfer History</span>
                            </div>
                            <div v-for="t in selectedAdmission.transfers" :key="t.id" class="text-[9.5px] p-1.5 bg-muted/20 rounded space-y-0.5">
                                <div class="font-semibold text-foreground">{{ t.from_ward?.name }} ({{ t.from_bed?.bed_number }}) → {{ t.to_ward?.name }} ({{ t.to_bed?.bed_number }})</div>
                                <div class="text-muted-foreground">{{ formatDate(t.transferred_at) }} · "{{ t.reason }}"</div>
                            </div>
                        </div>

                        <!-- Nursing e-MAR & Doses Administered -->
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                                    <Pill class="w-3.5 h-3.5 text-primary" />
                                    <span>Nursing e-MAR & Doses</span>
                                </div>
                                <button
                                    v-if="can.administerMar"
                                    @click="openMarForAdmission(selectedAdmission)"
                                    class="text-[10px] font-bold text-primary hover:underline flex items-center gap-0.5"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>Chart Dose</span>
                                </button>
                            </div>

                            <div v-if="selectedAdmission.medication_administration_records && selectedAdmission.medication_administration_records.length > 0" class="space-y-1.5 max-h-[180px] overflow-y-auto pr-0.5">
                                <div 
                                    v-for="mar in selectedAdmission.medication_administration_records" 
                                    :key="mar.id" 
                                    class="p-2 bg-muted/20 border border-border/50 rounded-md text-[10px] space-y-1"
                                >
                                    <div class="flex items-center justify-between font-bold">
                                        <span class="text-foreground truncate max-w-[140px]">{{ mar.item_name }}</span>
                                        <span 
                                            class="px-1.5 py-0.2 rounded text-[8.5px] font-bold uppercase"
                                            :class="mar.status === 'Administered' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'"
                                        >
                                            {{ mar.status }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-muted-foreground text-[9px]">
                                        <span>{{ mar.dose_quantity }} {{ mar.dose_unit }} · {{ mar.route }} ({{ mar.frequency }})</span>
                                        <span class="font-mono">{{ formatDate(mar.administered_at) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[9px] text-muted-foreground border-t border-border/30 pt-0.5">
                                        <span>Nurse: <strong>{{ mar.administered_by_user?.first_name }}</strong></span>
                                        <span v-if="mar.is_dda_narcotic" class="px-1 py-0.2 bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 font-bold rounded text-[8px]">
                                            DDA Witnessed
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="p-3 text-center bg-muted/10 rounded text-[10px] text-muted-foreground">
                                No medication charted yet for this admission.
                            </div>
                        </div>

                        <!-- Actions Bar -->
                        <div class="space-y-1.5 pt-1.5">
                            <Button
                                v-if="can.administerMar"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 bg-primary/90 hover:bg-primary text-primary-foreground shadow-2xs"
                                @click="openMarForAdmission(selectedAdmission)"
                            >
                                <Syringe class="w-3.5 h-3.5" />
                                <span>Chart & Dispense Dose (e-MAR)</span>
                            </Button>

                            <Button
                                variant="outline"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs border-border/80"
                                @click="printingWristbandPatient = selectedAdmission.patient; printingWristbandAdmission = selectedAdmission"
                            >
                                <Printer class="w-3.5 h-3.5 text-primary" />
                                <span>Print Bedside Wristband</span>
                            </Button>

                            <Button
                                v-if="can.transfer"
                                variant="outline"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 shadow-2xs"
                                @click="openTransferForAdmission(selectedAdmission)"
                            >
                                <ArrowLeftRight class="w-3.5 h-3.5" />
                                <span>Transfer Bed / Ward</span>
                            </Button>

                            <Button
                                v-if="can.discharge"
                                variant="default"
                                class="w-full h-8 text-xs font-semibold justify-center gap-1.5 bg-rose-700 hover:bg-rose-800 text-white shadow-2xs"
                                @click="openDischargeForAdmission(selectedAdmission)"
                            >
                                <DoorOpen class="w-3.5 h-3.5" />
                                <span>Discharge Patient</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Available Bed Inspection -->
                    <div v-else-if="selectedBed" class="space-y-2.5 text-xs">
                        <div class="p-2.5 bg-card rounded-lg shadow-2xs space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-bold text-sm text-foreground">{{ selectedBed.bed_number }}</span>
                                <span 
                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300': selectedBed.status === 'Available',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300': selectedBed.status === 'Cleaning',
                                        'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300': selectedBed.status === 'Maintenance',
                                    }"
                                >
                                    {{ selectedBed.status }}
                                </span>
                            </div>
                            <div class="text-muted-foreground text-[10.5px]">
                                Ward: <strong class="text-foreground">{{ selectedBed.ward?.name }}</strong>
                            </div>
                            <div class="text-muted-foreground text-[10.5px]">
                                Daily Rate: <strong class="text-foreground font-mono">TZS {{ formatCurrency(selectedBed.daily_rate_amount) }}/night</strong>
                            </div>
                        </div>

                        <div v-if="selectedBed.status === 'Available' && can.admit" class="pt-1">
                            <Button variant="default" class="w-full h-8 text-xs font-semibold gap-1.5 shadow-2xs" @click="openAdmitForBed(selectedBed)">
                                <Plus class="w-3.5 h-3.5" />
                                <span>Admit Patient to {{ selectedBed.bed_number }}</span>
                            </Button>
                        </div>

                        <div v-else-if="selectedBed.status === 'Cleaning' && can.updateBedStatus" class="pt-1">
                            <Button variant="outline" class="w-full h-8 text-xs font-semibold text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-800 shadow-2xs" @click="markBedCleaned(selectedBed)">
                                <Check class="w-3.5 h-3.5 mr-1" />
                                <span>Mark Bed Sanitized & Ready</span>
                            </Button>
                        </div>
                    </div>

                    <div v-else class="text-center py-6 text-muted-foreground text-xs">
                        Select a bed or inpatient to preview telemetry.
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- ================= MODAL 1: ADMIT PATIENT ================= -->
        <Modal :show="showAdmitModal" max-width="md" @close="showAdmitModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Bed class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Inpatient Admission</h3>
                    </div>
                    <button @click="showAdmitModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitAdmit" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Select Patient *</label>
                        <Select 
                            v-model="admitForm.patient_id" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="p in availablePatients" :key="p.id" :value="p.id">
                                {{ p.first_name }} {{ p.last_name }} (MRN: {{ p.primary_mrn }})
                            </option>
                        </Select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Ward *</label>
                            <Select 
                                v-model="admitForm.ward_id" 
                                required
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                            >
                                <option v-for="w in wards" :key="w.id" :value="w.id">
                                    {{ w.name }}
                                </option>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Available Bed *</label>
                            <Select 
                                v-model="admitForm.bed_id" 
                                required
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs font-mono font-bold text-primary"
                            >
                                <option v-for="b in availableBedsForAdmit" :key="b.id" :value="b.id">
                                    {{ b.bed_number }} ({{ b.bed_type }})
                                </option>
                            </Select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Attending Physician *</label>
                        <Select 
                            v-model="admitForm.admitting_doctor_id" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option v-for="d in doctors" :key="d.id" :value="d.id">
                                Dr. {{ d.first_name }} {{ d.last_name }}
                            </option>
                        </Select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Provisional Diagnosis</label>
                        <Input v-model="admitForm.provisional_diagnosis" placeholder="e.g. Severe Pneumonia / Malaria" class="h-8.5 text-xs" />
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Clinical Admission Indication *</label>
                        <Input v-model="admitForm.admission_reason" required placeholder="e.g. IV therapy and continuous telemetry monitoring" class="h-8.5 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showAdmitModal = false" :disabled="isAdmitting">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isAdmitting || !admitForm.bed_id">
                            <Loader2 v-if="isAdmitting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Confirm Inpatient Admission</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ================= MODAL 2: BED TRANSFER ================= -->
        <Modal :show="showTransferModal" max-width="md" @close="showTransferModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <ArrowLeftRight class="w-4.5 h-4.5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Transfer Inpatient Bed</h3>
                    </div>
                    <button @click="showTransferModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitTransfer" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Destination Ward *</label>
                            <Select 
                                v-model="transferForm.to_ward_id" 
                                required
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                            >
                                <option v-for="w in wards" :key="w.id" :value="w.id">
                                    {{ w.name }}
                                </option>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Destination Bed *</label>
                            <Select 
                                v-model="transferForm.to_bed_id" 
                                required
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs font-mono font-bold text-primary"
                            >
                                <option v-for="b in availableBedsForTransfer" :key="b.id" :value="b.id">
                                    {{ b.bed_number }} ({{ b.bed_type }})
                                </option>
                            </Select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Transfer Clinical Rationale *</label>
                        <Input v-model="transferForm.reason" required placeholder="e.g. ICU step-down to general male ward" class="h-8.5 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showTransferModal = false" :disabled="isTransferring">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isTransferring || !transferForm.to_bed_id">
                            <Loader2 v-if="isTransferring" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Confirm Bed Transfer</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ================= MODAL 3: DISCHARGE PATIENT ================= -->
        <Modal :show="showDischargeModal" max-width="md" @close="showDischargeModal = false">
            <div class="p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <DoorOpen class="w-4.5 h-4.5 text-rose-600 dark:text-rose-400" />
                        <h3 class="font-bold text-sm text-foreground">Discharge Inpatient</h3>
                    </div>
                    <button @click="showDischargeModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitDischarge" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Discharge Disposition *</label>
                        <Select 
                            v-model="dischargeForm.discharge_disposition" 
                            required
                            class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-3 py-1 text-xs shadow-xs"
                        >
                            <option value="Home">Discharged Home (Recovered/Improved)</option>
                            <option value="Transferred_Facility">Transferred to Specialized Referral Hospital</option>
                            <option value="Against_Medical_Advice">Discharged Against Medical Advice (DAMA)</option>
                            <option value="Deceased">Deceased</option>
                        </Select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Discharge Summary & Instructions *</label>
                        <textarea 
                            v-model="dischargeForm.discharge_summary" 
                            required 
                            rows="3" 
                            class="w-full rounded-md border border-input bg-background text-foreground p-2.5 text-xs shadow-xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Enter final clinical status, medications on discharge, and follow-up review plan..."
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showDischargeModal = false" :disabled="isDischarging">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 bg-rose-700 hover:bg-rose-800 text-white shadow-2xs" :disabled="isDischarging || !dischargeForm.discharge_summary">
                            <Loader2 v-if="isDischarging" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Complete Discharge & Free Bed</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ================= MODAL 4: e-MAR DOSE CHARTING & WARD STOCK DISPENSE ================= -->
        <Modal :show="showMarModal" max-width="lg" @close="showMarModal = false">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Syringe class="w-5 h-5 text-primary" />
                        <div>
                            <h3 class="font-bold text-sm text-foreground">Nursing e-MAR: Chart & Dispense Dose</h3>
                            <p class="text-[11px] text-muted-foreground">
                                Administering to <strong>{{ selectedAdmission?.patient?.first_name }} {{ selectedAdmission?.patient?.last_name }}</strong> · Bed {{ selectedAdmission?.bed?.bed_number }}
                            </p>
                        </div>
                    </div>
                    <button @click="showMarModal = false" class="text-muted-foreground hover:text-foreground p-1">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitMar" class="space-y-3.5 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Medication / Consumable *</label>
                            <Select 
                                v-model="marForm.item_master_id" 
                                required
                                @change="onMarItemChange"
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-2.5 py-1 text-xs shadow-xs"
                            >
                                <option v-for="item in itemMasters" :key="item.id" :value="item.id">
                                    [{{ item.category?.replace('_', ' ') }}] {{ item.name }}
                                </option>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Ward Cabinet (Source Stock) *</label>
                            <Select 
                                v-model="marForm.location_id" 
                                required
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-2.5 py-1 text-xs shadow-xs font-semibold"
                            >
                                <option v-for="cab in wardCabinets" :key="cab.id" :value="cab.id">
                                    {{ cab.name }} ({{ cab.type }})
                                </option>
                            </Select>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2.5">
                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Dose Qty *</label>
                            <Input 
                                v-model.number="marForm.dose_quantity" 
                                type="number" 
                                min="0.1" 
                                step="0.1" 
                                required 
                                class="h-8.5 text-xs text-right font-mono"
                                @input="onMarItemChange"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Unit *</label>
                            <Select 
                                v-model="marForm.dose_unit" 
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-2 py-1 text-xs shadow-xs"
                            >
                                <option value="dose">dose</option>
                                <option value="vial">vial</option>
                                <option value="ampoule">ampoule</option>
                                <option value="tablet">tablet</option>
                                <option value="capsule">capsule</option>
                                <option value="ml">ml</option>
                                <option value="piece">piece</option>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Route *</label>
                            <Select 
                                v-model="marForm.route" 
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-2 py-1 text-xs shadow-xs"
                            >
                                <option value="IV">IV (Intravenous)</option>
                                <option value="IM">IM (Intramuscular)</option>
                                <option value="Oral">Oral (PO)</option>
                                <option value="SC">SC (Subcutaneous)</option>
                                <option value="Topical">Topical</option>
                                <option value="Inhalation">Inhalation</option>
                                <option value="PR">PR (Rectal)</option>
                            </Select>
                        </div>

                        <div class="space-y-1">
                            <label class="block font-bold text-xs text-foreground">Frequency</label>
                            <Select 
                                v-model="marForm.frequency" 
                                class="w-full h-8.5 rounded-md border border-input bg-background text-foreground px-2 py-1 text-xs shadow-xs"
                            >
                                <option value="STAT">STAT (Once)</option>
                                <option value="BD">BD (Twice daily)</option>
                                <option value="TDS">TDS (3x daily)</option>
                                <option value="QID">QID (4x daily)</option>
                                <option value="PRN">PRN (As needed)</option>
                            </Select>
                        </div>
                    </div>

                    <!-- DDA Controlled Substance Witness Panel -->
                    <div class="p-3 rounded-lg border" :class="marForm.is_dda_narcotic ? 'bg-rose-50 border-rose-200 dark:bg-rose-950/40 dark:border-rose-800' : 'bg-muted/10 border-border/40'">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <ShieldCheck class="w-4 h-4" :class="marForm.is_dda_narcotic ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'" />
                                <span class="font-bold text-xs" :class="marForm.is_dda_narcotic ? 'text-rose-900 dark:text-rose-200' : 'text-foreground'">
                                    Controlled Narcotic (DDA Register)
                                </span>
                            </div>
                            <label class="flex items-center gap-1.5 cursor-pointer text-[11px] font-semibold">
                                <input type="checkbox" v-model="marForm.is_dda_narcotic" class="rounded border-border text-rose-600 focus:ring-rose-500" />
                                <span>Requires Witness</span>
                            </label>
                        </div>

                        <div v-if="marForm.is_dda_narcotic" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2 mt-2 border-t border-rose-200/60 dark:border-rose-800/60">
                            <div class="space-y-1">
                                <label class="block font-bold text-[10.5px] text-rose-900 dark:text-rose-200">Witness Nurse / Clinician *</label>
                                <Select 
                                    v-model="marForm.witness_by" 
                                    :required="marForm.is_dda_narcotic"
                                    class="w-full h-8 rounded-md border border-rose-300 bg-background text-foreground px-2 py-0.5 text-xs"
                                >
                                    <option value="" disabled>Select witnessing staff...</option>
                                    <option v-for="n in nurses" :key="n.id" :value="n.id">
                                        {{ n.first_name }} {{ n.last_name }} (Staff)
                                    </option>
                                </Select>
                            </div>

                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-1.5 text-[10.5px] text-rose-900 dark:text-rose-200 cursor-pointer font-bold">
                                    <input type="checkbox" v-model="marForm.witness_pin_verified" class="rounded border-rose-300 text-rose-600 focus:ring-rose-500" />
                                    <span>Physical Dual-Check Verified</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Live Billing & Administration Notes -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center p-2.5 bg-muted/20 rounded-lg border border-border/40">
                        <div class="sm:col-span-2 space-y-1">
                            <label class="block font-bold text-[10.5px] text-muted-foreground uppercase">Administration Clinical Note</label>
                            <Input v-model="marForm.notes" placeholder="e.g. Tolerated well, IV site clean and patent..." class="h-8 text-xs" />
                        </div>
                        <div class="text-right">
                            <div class="text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">Charge to Bill</div>
                            <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                                TZS {{ formatCurrency(marForm.charge_amount) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" class="h-8 text-xs font-semibold" @click="showMarModal = false" :disabled="isMarSubmitting">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="h-8 text-xs font-semibold gap-1.5 shadow-2xs" :disabled="isMarSubmitting || !marForm.item_master_id">
                            <Loader2 v-if="isMarSubmitting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <Check class="w-3.5 h-3.5 mr-1" />
                            <span>Confirm & Deduct Ward Stock</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Bedside Patient Wristband Print Modal -->
        <PatientWristbandPrint
            v-if="printingWristbandPatient"
            :patient="printingWristbandPatient"
            :admission="printingWristbandAdmission"
            @close="printingWristbandPatient = null; printingWristbandAdmission = null"
        />

        <!-- MODAL: REGISTER WARD -->
        <Modal :show="showCreateWardModal" max-width="md" @close="showCreateWardModal = false">
            <div class="p-5 space-y-3.5 text-xs">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-emerald-600" />
                        <h3 class="font-bold text-sm text-foreground">Register New Inpatient Ward</h3>
                    </div>
                    <button @click="showCreateWardModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateWard" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Ward Name *</label>
                            <Input v-model="createWardForm.name" required placeholder="e.g. St. Luke Male Surgical" class="h-8 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Ward Code *</label>
                            <Input v-model="createWardForm.code" required class="h-8 text-xs font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Ward Type *</label>
                            <Select v-model="createWardForm.ward_type" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="General">General Medical/Surgical</option>
                                <option value="ICU">Intensive Care Unit (ICU)</option>
                                <option value="HDU">High Dependency Unit (HDU)</option>
                                <option value="Maternity">Maternity & Labour</option>
                                <option value="Pediatric">Pediatric Ward</option>
                                <option value="VIP_Private">VIP / Executive Suite</option>
                                <option value="Isolation">Infectious Disease Isolation</option>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Gender Restriction</label>
                            <Select v-model="createWardForm.gender_restriction" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="None">None (Co-ed / Private)</option>
                                <option value="Male_Only">Male Only</option>
                                <option value="Female_Only">Female Only</option>
                                <option value="Pediatric">Pediatric (&lt; 12 yrs)</option>
                            </Select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Floor / Building Location</label>
                            <Input v-model="createWardForm.floor_location" placeholder="e.g. Block B, 2nd Floor" class="h-8 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Daily Base Rate (TZS/night) *</label>
                            <Input v-model.number="createWardForm.daily_base_rate" type="number" min="0" required class="h-8 text-xs font-mono font-bold" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" @click="showCreateWardModal = false">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold" :disabled="isWardSubmitting">
                            <Loader2 v-if="isWardSubmitting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Save Ward</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: EDIT WARD -->
        <Modal :show="showEditWardModal" max-width="md" @close="showEditWardModal = false">
            <div class="p-5 space-y-3.5 text-xs">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Edit Ward: {{ targetWardItem?.name }}</h3>
                    </div>
                    <button @click="showEditWardModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitEditWard" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Ward Name *</label>
                            <Input v-model="editWardForm.name" required class="h-8 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Ward Code *</label>
                            <Input v-model="editWardForm.code" required class="h-8 text-xs font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Ward Type</label>
                            <Select v-model="editWardForm.ward_type" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="General">General Medical/Surgical</option>
                                <option value="ICU">Intensive Care Unit (ICU)</option>
                                <option value="HDU">High Dependency Unit (HDU)</option>
                                <option value="Maternity">Maternity & Labour</option>
                                <option value="Pediatric">Pediatric Ward</option>
                                <option value="VIP_Private">VIP / Executive Suite</option>
                                <option value="Isolation">Infectious Disease Isolation</option>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Gender Restriction</label>
                            <Select v-model="editWardForm.gender_restriction" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="None">None (Co-ed / Private)</option>
                                <option value="Male_Only">Male Only</option>
                                <option value="Female_Only">Female Only</option>
                                <option value="Pediatric">Pediatric (&lt; 12 yrs)</option>
                            </Select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Floor Location</label>
                            <Input v-model="editWardForm.floor_location" class="h-8 text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Daily Base Rate (TZS)</label>
                            <Input v-model.number="editWardForm.daily_base_rate" type="number" min="0" required class="h-8 text-xs font-mono font-bold" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" v-model="editWardForm.is_active" class="rounded border-input text-primary" />
                        <label class="text-[11px] text-muted-foreground">Ward is active and accepting admissions</label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" @click="showEditWardModal = false">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" :disabled="isWardSubmitting">
                            <Loader2 v-if="isWardSubmitting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Save Changes</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: ADD BED TO WARD -->
        <Modal :show="showCreateBedModal" max-width="md" @close="showCreateBedModal = false">
            <div class="p-5 space-y-3.5 text-xs">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Bed class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Add Bed to {{ targetWardForBed?.name || 'Ward' }}</h3>
                    </div>
                    <button @click="showCreateBedModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateBed" class="space-y-3">
                    <div class="space-y-1">
                        <label class="block font-bold text-muted-foreground text-[10px] uppercase">Target Ward *</label>
                        <Select v-model="createBedForm.ward_id" required class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                            <option v-for="w in wards" :key="w.id" :value="w.id">{{ w.name }} ({{ w.ward_type }})</option>
                        </Select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Bed Number / Label *</label>
                            <Input v-model="createBedForm.bed_number" required placeholder="e.g. Bed-01 or ICU-03" class="h-8 text-xs font-mono font-bold" />
                        </div>
                        <div class="space-y-1">
                            <label class="block font-bold text-muted-foreground text-[10px] uppercase">Bed Type</label>
                            <Select v-model="createBedForm.bed_type" class="w-full h-8 rounded border border-input bg-background px-2 text-xs">
                                <option value="Standard">Standard Hospital Bed</option>
                                <option value="Electric_ICU">Electric ICU / Critical Bed</option>
                                <option value="Pediatric_Crib">Pediatric Cot / Crib</option>
                                <option value="Incubator">Neonatal Incubator</option>
                                <option value="Delivery_Bed">Labour & Delivery Bed</option>
                            </Select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-muted-foreground text-[10px] uppercase">Specific Daily Bed Rate (TZS) - Optional Override</label>
                        <Input v-model.number="createBedForm.daily_rate_amount" type="number" min="0" placeholder="Defaults to Ward Base Rate if empty" class="h-8 text-xs font-mono" />
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-muted-foreground text-[10px] uppercase">Bedside Amenities / Notes</label>
                        <Input v-model="createBedForm.notes" placeholder="e.g. Oxygen point, suction unit, ventilator attached" class="h-8 text-xs" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <Button variant="outline" size="sm" type="button" @click="showCreateBedModal = false">Cancel</Button>
                        <Button variant="default" size="sm" type="submit" :disabled="isWardSubmitting">
                            <Loader2 v-if="isWardSubmitting" class="w-3.5 h-3.5 animate-spin mr-1" />
                            <span>Register Bed</span>
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    </AfyaShell>
</template>
