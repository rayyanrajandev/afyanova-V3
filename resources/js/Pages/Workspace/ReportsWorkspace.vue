<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    TrendingUp,
    BarChart3,
    Activity,
    ShieldAlert,
    AlertTriangle,
    CheckCircle2,
    DollarSign,
    Receipt,
    Pill,
    Bed,
    Clock,
    Users,
    Calendar,
    ArrowUpRight,
    ArrowDownRight,
    Filter,
    Layers,
    Search,
    ShieldCheck,
    FlaskConical,
    Scissors,
    Stethoscope,
    RefreshCw,
    X,
    FileSpreadsheet,
    Building2,
    PieChart,
    Package,
    FileCode,
    Download
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    can: {
        type: Object,
        default: () => ({}),
    },
    morbidity: {
        type: Object,
        default: () => ({}),
    },
    financial: {
        type: Object,
        default: () => ({}),
    },
    pharmaco: {
        type: Object,
        default: () => ({}),
    },
    operational: {
        type: Object,
        default: () => ({}),
    },
    metrics: {
        type: Object,
        default: () => ({}),
    },
    mtuha: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({ preset: 'month', start_date: null, end_date: null }),
    },
});

const { preferences, openContext } = useWorkspacePreferences();

// View State: 'morbidity' | 'notifiable' | 'financial' | 'payermix' | 'pharmaco' | 'operations' | 'mtuha_book1' | 'mtuha_book2' | 'mtuha_book5'
const sectionSource = {
    morbidity: 'morbidity',
    notifiable: 'morbidity',
    financial: 'financial',
    payermix: 'financial',
    pharmaco: 'pharmaco',
    operations: 'operational',
    mtuha_book1: 'mtuha',
    mtuha_book2: 'mtuha',
    mtuha_book5: 'mtuha',
};
const firstAvailableSection = Object.keys(sectionSource).find(s => props.can[sectionSource[s]]) ?? null;
const activeSection = ref(firstAvailableSection);
const selectedDiagnosis = ref(null);
const selectedDepartment = ref(null);
const selectedPayer = ref(null);
const selectedMedication = ref(null);
const searchQuery = ref('');

const exportDhis2 = () => {
    window.open(route('reports.mtuha.export', { format: 'dhis2', start_date: props.filters.start_date, end_date: props.filters.end_date }));
};

const exportMtuhaCsv = () => {
    window.open(route('reports.mtuha.export', { format: 'csv', start_date: props.filters.start_date, end_date: props.filters.end_date }));
};

const setPreset = (presetName) => {
    router.get(route('reports.workspace'), { preset: presetName }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const selectDiagnosis = (diag) => {
    selectedDiagnosis.value = diag;
    openContext();
};

const selectDepartment = (dept) => {
    selectedDepartment.value = dept;
    openContext();
};

const selectPayer = (payer) => {
    selectedPayer.value = payer;
    openContext();
};

const selectMedication = (med) => {
    selectedMedication.value = med;
    openContext();
};

// Formatting helpers
const formatCurrency = (val) => {
    return 'TZS ' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const formatPercent = (val) => {
    return Number(val || 0).toFixed(1) + '%';
};

const breadcrumbLabel = computed(() => {
    switch (activeSection.value) {
        case 'morbidity': return 'Top 10 Morbidity & Disease Surveillance';
        case 'notifiable': return 'Epidemic & Notifiable Watchtower';
        case 'financial': return 'Revenue & Cost Center P&L';
        case 'payermix': return 'Payer Mix & Insurance Reconciliation';
        case 'pharmaco': return 'Pharmacoeconomics & Drug Velocity';
        case 'operations': return 'Bed Occupancy & Operational Efficiency';
        default: return 'Hospital Intelligence';
    }
});

const contextTitle = computed(() => {
    if (activeSection.value === 'morbidity' && selectedDiagnosis.value) {
        return `ICD-10: ${selectedDiagnosis.value.icd10_code}`;
    }
    if (activeSection.value === 'financial' && selectedDepartment.value) {
        return `Cost Center: ${selectedDepartment.value.department}`;
    }
    if (activeSection.value === 'payermix' && selectedPayer.value) {
        return `Payer: ${selectedPayer.value.payer_name}`;
    }
    if (activeSection.value === 'pharmaco' && selectedMedication.value) {
        return `Formulary: ${selectedMedication.value.generic_name}`;
    }
    return 'Analytics 360 Telemetry';
});

const contextIcon = computed(() => {
    if (activeSection.value === 'morbidity') return Activity;
    if (activeSection.value === 'notifiable') return ShieldAlert;
    if (activeSection.value === 'financial') return DollarSign;
    if (activeSection.value === 'payermix') return ShieldCheck;
    if (activeSection.value === 'pharmaco') return Pill;
    return TrendingUp;
});
</script>

<template>
    <AfyaShell active-module="reports">
        <Head title="Hospital Analytics & Business Intelligence" />

        <AfyaWorkspace :show-context="true">
            <!-- 1. LEFT SIDEBAR: ANALYTICS MODULES & TIME FILTERS -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar 
                    title="Hospital Intelligence" 
                    :icon="TrendingUp"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    
                    <!-- Section 1: Clinical & Disease Surveillance -->
                    <div v-if="state !== 'collapsed'" class="px-2 py-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                        Clinical Surveillance
                    </div>
                    <AfyaSidebarItem
                        v-if="can.morbidity"
                        :icon="Activity"
                        label="Top 10 Morbidity"
                        :active="activeSection === 'morbidity'"
                        :collapsed="state === 'collapsed'"
                        :badge="morbidity.top_10_morbidity?.length ? `${morbidity.top_10_morbidity.length}` : null"
                        @click="activeSection = 'morbidity'"
                    />
                    <AfyaSidebarItem
                        v-if="can.morbidity"
                        :icon="ShieldAlert"
                        label="Epidemic Watchtower"
                        :active="activeSection === 'notifiable'"
                        :collapsed="state === 'collapsed'"
                        :badge="morbidity.notifiable_alert_count > 0 ? `${morbidity.notifiable_alert_count} Alerts` : null"
                        @click="activeSection = 'notifiable'"
                    />

                    <!-- Section 2: Executive Financials -->
                    <div v-if="state !== 'collapsed' && can.financial" class="px-2 pt-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground border-t border-border/40 mt-2">
                        Financial Intelligence
                    </div>
                    <AfyaSidebarItem
                        v-if="can.financial"
                        :icon="DollarSign"
                        label="Revenue & Cost Centers"
                        :active="activeSection === 'financial'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'financial'"
                    />
                    <AfyaSidebarItem
                        v-if="can.financial"
                        :icon="ShieldCheck"
                        label="Payer Mix & NHIF"
                        :active="activeSection === 'payermix'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'payermix'"
                    />

                    <!-- Section 3: Supply Chain & Efficiency -->
                    <div v-if="state !== 'collapsed' && (can.pharmaco || can.operational)" class="px-2 pt-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground border-t border-border/40 mt-2">
                        Operations & Supply
                    </div>
                    <AfyaSidebarItem
                        v-if="can.pharmaco"
                        :icon="Pill"
                        label="Pharmacoeconomics"
                        :active="activeSection === 'pharmaco'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'pharmaco'"
                    />
                    <AfyaSidebarItem
                        v-if="can.operational"
                        :icon="Bed"
                        label="Bed Occupancy (BOR)"
                        :active="activeSection === 'operations'"
                        :collapsed="state === 'collapsed'"
                        :badge="`${operational.bed_occupancy?.bor_percent || 0}%`"
                        @click="activeSection = 'operations'"
                    />

                    <!-- Section: MTUHA MoH Reporting -->
                    <div v-if="state !== 'collapsed' && can.mtuha" class="px-2 pt-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground border-t border-border/40 mt-2">
                        MoH MTUHA & DHIS2
                    </div>
                    <AfyaSidebarItem
                        v-if="can.mtuha"
                        :icon="FileSpreadsheet"
                        label="Book 1 - OPD Registry"
                        :active="activeSection === 'mtuha_book1'"
                        :collapsed="state === 'collapsed'"
                        :badge="mtuha.book1?.summary?.total_opd_attendances"
                        @click="activeSection = 'mtuha_book1'"
                    />
                    <AfyaSidebarItem
                        v-if="can.mtuha"
                        :icon="Building2"
                        label="Book 2 - IPD & Mortality"
                        :active="activeSection === 'mtuha_book2'"
                        :collapsed="state === 'collapsed'"
                        :badge="mtuha.book2?.summary?.total_admissions"
                        @click="activeSection = 'mtuha_book2'"
                    />
                    <AfyaSidebarItem
                        v-if="can.mtuha"
                        :icon="FlaskConical"
                        label="Book 5 - Lab & Logistics"
                        :active="activeSection === 'mtuha_book5'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'mtuha_book5'"
                    />

                    <!-- Section 4: Time Period Filter Strip -->
                    <div v-if="state !== 'collapsed'" class="mt-4 pt-3 border-t border-border/40 px-2 space-y-1.5">
                        <div class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                            <span>Reporting Period</span>
                            <Calendar class="w-3 h-3 text-primary" />
                        </div>
                        <div class="grid grid-cols-2 gap-1 text-[10.5px]">
                            <button 
                                @click="setPreset('all')"
                                :class="filters.preset === 'all' ? 'bg-primary text-primary-foreground font-bold shadow-2xs col-span-2' : 'bg-muted/40 hover:bg-muted text-muted-foreground col-span-2'"
                                class="px-2 py-1 rounded-md text-center transition-all font-medium"
                            >
                                All Time Records
                            </button>
                            <button 
                                @click="setPreset('today')"
                                :class="filters.preset === 'today' ? 'bg-primary text-primary-foreground font-bold shadow-2xs' : 'bg-muted/40 hover:bg-muted text-muted-foreground'"
                                class="px-2 py-1 rounded-md text-center transition-all font-medium"
                            >
                                Today
                            </button>
                            <button 
                                @click="setPreset('week')"
                                :class="filters.preset === 'week' ? 'bg-primary text-primary-foreground font-bold shadow-2xs' : 'bg-muted/40 hover:bg-muted text-muted-foreground'"
                                class="px-2 py-1 rounded-md text-center transition-all font-medium"
                            >
                                This Week
                            </button>
                            <button 
                                @click="setPreset('month')"
                                :class="filters.preset === 'month' ? 'bg-primary text-primary-foreground font-bold shadow-2xs' : 'bg-muted/40 hover:bg-muted text-muted-foreground'"
                                class="px-2 py-1 rounded-md text-center transition-all font-medium"
                            >
                                This Month
                            </button>
                            <button 
                                @click="setPreset('year')"
                                :class="filters.preset === 'year' ? 'bg-primary text-primary-foreground font-bold shadow-2xs' : 'bg-muted/40 hover:bg-muted text-muted-foreground'"
                                class="px-2 py-1 rounded-md text-center transition-all font-medium"
                            >
                                This Year
                            </button>
                        </div>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. MAIN WORKSPACE CONTENT -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Hospital Intelligence', href: route('reports.workspace') },
                        { label: breadcrumbLabel, active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-mono text-muted-foreground bg-muted px-2 py-1 rounded border border-border/50">
                                Period: {{ filters.start_date || 'All' }} to {{ filters.end_date || 'Present' }}
                            </span>
                            <Button
                                v-if="can.mtuha"
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs font-bold gap-1 text-indigo-600 dark:text-indigo-400 border-indigo-300 dark:border-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 shadow-2xs"
                                @click="exportDhis2"
                                title="Export DHIS2 DataValueSet JSON formatted for MoH national HMIS sync"
                            >
                                <FileCode class="w-3 h-3" />
                                <span>DHIS2 JSON</span>
                            </Button>
                            <Button
                                v-if="can.mtuha"
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs font-bold gap-1 text-emerald-600 dark:text-emerald-400 border-emerald-300 dark:border-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 shadow-2xs"
                                @click="exportMtuhaCsv"
                                title="Download MoH MTUHA Tabular CSV summary"
                            >
                                <Download class="w-3 h-3" />
                                <span>MoH CSV</span>
                            </Button>
                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 bg-card shadow-2xs"
                                @click="setPreset(filters.preset)"
                            >
                                <RefreshCw class="w-3 h-3 text-primary" />
                                <span>Refresh</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        
                        <!-- TOP METRICS STRIP (Borderless Modern Cards) -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            
                            <!-- Metric 1: Total Revenue Collected -->
                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Revenue Collected</span>
                                    <DollarSign class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ formatCurrency(metrics.total_revenue_tzs) }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5 flex items-center justify-between">
                                    <span>Billed: {{ formatCurrency(metrics.total_billed_tzs) }}</span>
                                </div>
                            </div>

                            <!-- Metric 2: Total Clinical Diagnoses -->
                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Total Diagnoses</span>
                                    <Activity class="w-3.5 h-3.5 text-primary" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ metrics.total_diagnoses }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5">
                                    Under-5: {{ morbidity.demographic_summary?.pediatric_under_5 || 0 }} cases
                                </div>
                            </div>

                            <!-- Metric 3: Top Morbidity Disease -->
                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Top Morbidity</span>
                                    <TrendingUp class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <div class="mt-1 text-xs font-bold text-foreground truncate">
                                    {{ metrics.top_diagnosis }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5">
                                    {{ morbidity.top_10_morbidity?.[0]?.percentage || 0 }}% of total cases
                                </div>
                            </div>

                            <!-- Metric 4: Bed Occupancy Rate (BOR) -->
                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Bed Occupancy (BOR)</span>
                                    <Bed class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ formatPercent(metrics.bed_occupancy_rate) }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5">
                                    {{ metrics.active_inpatients }} active inpatients
                                </div>
                            </div>

                            <!-- Metric 5: Epidemic Alert Flags -->
                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Notifiable Alerts</span>
                                    <ShieldAlert class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                                </div>
                                <div class="mt-1 text-base font-extrabold font-mono" :class="metrics.notifiable_alerts_count > 0 ? 'text-rose-600 animate-pulse' : 'text-emerald-600'">
                                    {{ metrics.notifiable_alerts_count }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5">
                                    {{ metrics.notifiable_alerts_count > 0 ? 'Immediate MoH Action' : 'Zero Active Epidemics' }}
                                </div>
                            </div>

                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 1: TOP 10 MORBIDITY & DISEASE SURVEILLANCE                -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'morbidity'" class="space-y-3">
                            <div class="bg-card rounded-lg overflow-hidden border border-border/60 shadow-2xs">
                                <div class="p-3 bg-muted/20 border-b border-border/60 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Activity class="w-4 h-4 text-primary" />
                                        <span class="text-xs font-bold text-foreground uppercase tracking-wider">
                                            Top 10 Disease Morbidity Distribution (ICD-10 Surveillance)
                                        </span>
                                    </div>
                                    <span class="text-[10px] font-mono text-muted-foreground">
                                        Total Diagnoses: {{ morbidity.total_diagnoses }}
                                    </span>
                                </div>

                                <div class="p-3 space-y-3">
                                    <div 
                                        v-for="item in (morbidity.top_10_morbidity || [])" 
                                        :key="item.rank"
                                        @click="selectDiagnosis(item)"
                                        class="p-3 rounded-lg border border-border/40 hover:border-primary/50 hover:bg-muted/20 transition-all cursor-pointer space-y-2 bg-card"
                                    >
                                        <div class="flex items-center justify-between text-xs">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <div class="w-6 h-6 rounded-md bg-primary/10 text-primary font-bold font-mono text-[11px] flex items-center justify-center shrink-0">
                                                    #{{ item.rank }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-foreground flex items-center gap-2">
                                                        <span class="truncate">{{ item.description }}</span>
                                                        <span class="font-mono text-[10px] text-muted-foreground bg-muted px-1.5 py-0.2 rounded border border-border/40">
                                                            {{ item.icd10_code }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3 font-mono text-xs shrink-0">
                                                <span class="font-bold text-foreground">{{ item.total_cases }} cases</span>
                                                <span class="text-muted-foreground font-semibold">({{ item.percentage }}%)</span>
                                            </div>
                                        </div>

                                        <!-- Progress Distribution Bar -->
                                        <div class="w-full bg-muted/60 h-2 rounded-full overflow-hidden">
                                            <div 
                                                class="bg-primary h-full rounded-full transition-all duration-500"
                                                :style="{ width: `${item.percentage}%` }"
                                            ></div>
                                        </div>

                                        <!-- Demographic Sub-strip -->
                                        <div class="flex items-center justify-between text-[10px] text-muted-foreground pt-1 border-t border-border/30">
                                            <div class="flex items-center gap-3">
                                                <span>Under 5: <b class="text-foreground">{{ item.demographics?.under_5 || 0 }}</b></span>
                                                <span>5-17y: <b class="text-foreground">{{ item.demographics?.age_5_17 || 0 }}</b></span>
                                                <span>18-59y: <b class="text-foreground">{{ item.demographics?.age_18_59 || 0 }}</b></span>
                                                <span>60+y: <b class="text-foreground">{{ item.demographics?.age_60_plus || 0 }}</b></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span>Male: <b class="text-foreground">{{ item.demographics?.male || 0 }}</b></span>
                                                <span>Female: <b class="text-foreground">{{ item.demographics?.female || 0 }}</b></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="(morbidity.top_10_morbidity || []).length === 0" class="text-center py-12 text-muted-foreground text-xs">
                                        No diagnoses recorded in the selected reporting period.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 2: EPIDEMIC & NOTIFIABLE DISEASE WATCHTOWER               -->
                        <!-- ============================================================== -->
                        <div v-else-if="activeSection === 'notifiable'" class="space-y-3">
                            <div class="bg-card rounded-lg overflow-hidden border border-border/60 shadow-2xs">
                                <div class="p-3 bg-rose-500/10 border-b border-rose-500/30 flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-rose-700 dark:text-rose-400">
                                        <ShieldAlert class="w-4 h-4 animate-pulse" />
                                        <span class="text-xs font-bold uppercase tracking-wider">
                                            MoH Epidemic-Prone & Immediate Notifiable Diseases
                                        </span>
                                    </div>
                                    <span class="text-[10px] font-bold text-rose-700 dark:text-rose-400 bg-rose-500/20 px-2 py-0.5 rounded-full">
                                        {{ morbidity.notifiable_alerts?.length || 0 }} Active Epidemic Triggers
                                    </span>
                                </div>

                                <div class="p-3">
                                    <Table class="w-full text-xs">
                                        <TableHeader>
                                            <TableRow class="bg-muted/10 border-b border-border/40 text-[10px] uppercase font-bold text-muted-foreground">
                                                <TableHead class="py-2 px-3">Disease Condition</TableHead>
                                                <TableHead class="py-2 px-3">Total Cases</TableHead>
                                                <TableHead class="py-2 px-3">Confirmed</TableHead>
                                                <TableHead class="py-2 px-3">Suspected</TableHead>
                                                <TableHead class="py-2 px-3">Last Detected</TableHead>
                                                <TableHead class="py-2 px-3">Primary Patient</TableHead>
                                                <TableHead class="py-2 px-3 text-right">MoH Severity</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow 
                                                v-for="alert in (morbidity.notifiable_alerts || [])" 
                                                :key="alert.disease_name"
                                                class="hover:bg-muted/20 border-b border-border/30"
                                            >
                                                <TableCell class="py-2 px-3 font-bold text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                                                    <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
                                                    <span>{{ alert.disease_name }}</span>
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono font-bold text-foreground">
                                                    {{ alert.case_count }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono text-emerald-600 font-semibold">
                                                    {{ alert.confirmed_count }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono text-amber-600 font-semibold">
                                                    {{ alert.suspected_count }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono text-[11px] text-muted-foreground">
                                                    {{ alert.last_detected_at }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3">
                                                    <div class="font-medium text-foreground">{{ alert.patient_name }}</div>
                                                    <div class="text-[10px] font-mono text-muted-foreground">{{ alert.patient_mrn }}</div>
                                                </TableCell>
                                                <TableCell class="py-2 px-3 text-right">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-rose-600 text-white animate-pulse">
                                                        IMMEDIATE REPORT
                                                    </span>
                                                </TableCell>
                                            </TableRow>

                                            <TableRow v-if="(morbidity.notifiable_alerts || []).length === 0">
                                                <TableCell colspan="7" class="text-center py-12 text-muted-foreground text-xs">
                                                    <CheckCircle2 class="w-8 h-8 text-emerald-500 mx-auto mb-2" />
                                                    <div>No immediate notifiable disease outbreaks detected in this period.</div>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 3: REVENUE & COST CENTER P&L                              -->
                        <!-- ============================================================== -->
                        <div v-else-if="activeSection === 'financial'" class="space-y-3">
                            
                            <!-- Financial Summary Breakdown -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Gross Billed</div>
                                    <div class="text-lg font-extrabold text-foreground font-mono mt-1">
                                        {{ formatCurrency(financial.summary?.total_billed_tzs) }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">Total Invoices: {{ financial.summary?.total_invoices }}</div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Cash Collected</div>
                                    <div class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400 font-mono mt-1">
                                        {{ formatCurrency(financial.summary?.total_collected_tzs) }}
                                    </div>
                                    <div class="text-[10px] text-emerald-700 dark:text-emerald-300 font-semibold mt-0.5">
                                        Collection Rate: {{ financial.summary?.collection_rate_percent }}%
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Pending / Outstanding</div>
                                    <div class="text-lg font-extrabold text-amber-600 dark:text-amber-400 font-mono mt-1">
                                        {{ formatCurrency(financial.summary?.total_outstanding_tzs) }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">Avg/Invoice: {{ formatCurrency(financial.summary?.avg_revenue_per_invoice) }}</div>
                                </div>
                            </div>

                            <!-- Cost Center Revenue Breakdown Table -->
                            <div class="bg-card rounded-lg overflow-hidden border border-border/60 shadow-2xs">
                                <div class="p-3 bg-muted/20 border-b border-border/60 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Building2 class="w-4 h-4 text-primary" />
                                        <span class="text-xs font-bold text-foreground uppercase tracking-wider">
                                            Revenue Contribution by Hospital Cost Center
                                        </span>
                                    </div>
                                </div>

                                <div class="p-3">
                                    <Table class="w-full text-xs">
                                        <TableHeader>
                                            <TableRow class="bg-muted/10 border-b border-border/40 text-[10px] uppercase font-bold text-muted-foreground">
                                                <TableHead class="py-2 px-3">Service Cost Center</TableHead>
                                                <TableHead class="py-2 px-3">Revenue (TZS)</TableHead>
                                                <TableHead class="py-2 px-3">Revenue Contribution</TableHead>
                                                <TableHead class="py-2 px-3 text-right">Share %</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow 
                                                v-for="dept in (financial.departmental_revenue || [])" 
                                                :key="dept.department"
                                                @click="selectDepartment(dept)"
                                                class="hover:bg-muted/20 border-b border-border/30 cursor-pointer"
                                            >
                                                <TableCell class="py-2 px-3 font-bold text-foreground flex items-center gap-2">
                                                    <FlaskConical v-if="dept.department === 'Laboratory'" class="w-3.5 h-3.5 text-primary" />
                                                    <Pill v-else-if="dept.department === 'Pharmacy'" class="w-3.5 h-3.5 text-sky-600" />
                                                    <Scissors v-else-if="dept.department === 'Procedures'" class="w-3.5 h-3.5 text-purple-600" />
                                                    <Bed v-else-if="dept.department === 'Inpatient'" class="w-3.5 h-3.5 text-indigo-600" />
                                                    <Stethoscope v-else class="w-3.5 h-3.5 text-emerald-600" />
                                                    <span>{{ dept.department }}</span>
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono font-bold text-foreground">
                                                    {{ formatCurrency(dept.revenue_tzs) }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3">
                                                    <div class="w-full bg-muted/60 h-2 rounded-full overflow-hidden max-w-xs">
                                                        <div 
                                                            class="bg-emerald-600 h-full rounded-full transition-all duration-500"
                                                            :style="{ width: `${dept.percentage}%` }"
                                                        ></div>
                                                    </div>
                                                </TableCell>
                                                <TableCell class="py-2 px-3 text-right font-mono font-bold text-foreground">
                                                    {{ dept.percentage }}%
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 4: PAYER MIX & INSURANCE RECONCILIATION                   -->
                        <!-- ============================================================== -->
                        <div v-else-if="activeSection === 'payermix'" class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs space-y-1.5">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Direct Cash & Mobile Money</div>
                                    <div class="text-lg font-extrabold text-foreground font-mono">
                                        {{ formatCurrency(financial.payer_mix?.cash_and_mobile?.revenue_tzs) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Share: <b class="text-foreground">{{ financial.payer_mix?.cash_and_mobile?.share_percent }}%</b> of total facility cashflow
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs space-y-1.5">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Health Insurance Claims</div>
                                    <div class="text-lg font-extrabold text-primary font-mono">
                                        {{ formatCurrency(financial.payer_mix?.insurance_claims?.total_claimed_tzs) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Total Claims: <b class="text-foreground">{{ financial.payer_mix?.insurance_claims?.claims_count }}</b> · Approved: {{ formatCurrency(financial.payer_mix?.insurance_claims?.total_approved_tzs) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Payer Breakdown Table -->
                            <div class="bg-card rounded-lg overflow-hidden border border-border/60 shadow-2xs">
                                <div class="p-3 bg-muted/20 border-b border-border/60 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <ShieldCheck class="w-4 h-4 text-primary" />
                                        <span class="text-xs font-bold text-foreground uppercase tracking-wider">
                                            Insurance Payer Portfolios & Reimbursement Rates
                                        </span>
                                    </div>
                                </div>

                                <div class="p-3">
                                    <Table class="w-full text-xs">
                                        <TableHeader>
                                            <TableRow class="bg-muted/10 border-b border-border/40 text-[10px] uppercase font-bold text-muted-foreground">
                                                <TableHead class="py-2 px-3">Health Insurer</TableHead>
                                                <TableHead class="py-2 px-3">Claims Filed</TableHead>
                                                <TableHead class="py-2 px-3">Total Claimed</TableHead>
                                                <TableHead class="py-2 px-3">Total Approved</TableHead>
                                                <TableHead class="py-2 px-3 text-right">Approval Rate %</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow 
                                                v-for="payer in (financial.payer_mix?.insurance_claims?.payers || [])" 
                                                :key="payer.payer_name"
                                                @click="selectPayer(payer)"
                                                class="hover:bg-muted/20 border-b border-border/30 cursor-pointer"
                                            >
                                                <TableCell class="py-2 px-3 font-bold text-foreground">
                                                    {{ payer.payer_name }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono text-foreground font-semibold">
                                                    {{ payer.total_claims }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono font-bold text-foreground">
                                                    {{ formatCurrency(payer.claimed_amount) }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono text-emerald-600 font-bold">
                                                    {{ formatCurrency(payer.approved_amount) }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 text-right font-mono font-extrabold text-foreground">
                                                    {{ payer.reimbursement_rate }}%
                                                </TableCell>
                                            </TableRow>

                                            <TableRow v-if="(financial.payer_mix?.insurance_claims?.payers || []).length === 0">
                                                <TableCell colspan="5" class="text-center py-10 text-muted-foreground text-xs">
                                                    No insurance claims processed in this reporting period.
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 5: PHARMACOECONOMICS & INVENTORY VELOCITY                 -->
                        <!-- ============================================================== -->
                        <div v-else-if="activeSection === 'pharmaco'" class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Total Stock Valuation (Cost)</div>
                                    <div class="text-lg font-extrabold text-foreground font-mono mt-1">
                                        {{ formatCurrency(pharmaco.valuation?.total_cost_value_tzs) }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        Active Batches: {{ pharmaco.valuation?.active_batches_count }} · Units: {{ pharmaco.valuation?.total_units_on_hand }}
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Potential Retail Value</div>
                                    <div class="text-lg font-extrabold text-emerald-600 font-mono mt-1">
                                        {{ formatCurrency(pharmaco.valuation?.total_retail_value_tzs) }}
                                    </div>
                                    <div class="text-[10px] text-emerald-700 dark:text-emerald-300 font-semibold mt-0.5">
                                        Gross Margin: {{ pharmaco.valuation?.gross_margin_percent }}%
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Near-Expiry Financial Risk</div>
                                    <div class="text-lg font-extrabold text-rose-600 font-mono mt-1">
                                        {{ formatCurrency(pharmaco.expiry_risk?.total_at_risk_value_tzs) }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        {{ (pharmaco.expiry_risk?.critical_30_days || []).length }} batches &lt;30 days
                                    </div>
                                </div>
                            </div>

                            <!-- Fast-Moving Formularies Table -->
                            <div class="bg-card rounded-lg overflow-hidden border border-border/60 shadow-2xs">
                                <div class="p-3 bg-muted/20 border-b border-border/60 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Pill class="w-4 h-4 text-primary" />
                                        <span class="text-xs font-bold text-foreground uppercase tracking-wider">
                                            Top 10 Fast-Moving Medications (Consumption Velocity)
                                        </span>
                                    </div>
                                </div>

                                <div class="p-3">
                                    <Table class="w-full text-xs">
                                        <TableHeader>
                                            <TableRow class="bg-muted/10 border-b border-border/40 text-[10px] uppercase font-bold text-muted-foreground">
                                                <TableHead class="py-2 px-3">Rank</TableHead>
                                                <TableHead class="py-2 px-3">Formulary Item</TableHead>
                                                <TableHead class="py-2 px-3">Units Dispensed</TableHead>
                                                <TableHead class="py-2 px-3">Dispense Events</TableHead>
                                                <TableHead class="py-2 px-3 text-right">Revenue Generated</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow 
                                                v-for="med in (pharmaco.fast_moving_medications || [])" 
                                                :key="med.medication_id"
                                                @click="selectMedication(med)"
                                                class="hover:bg-muted/20 border-b border-border/30 cursor-pointer"
                                            >
                                                <TableCell class="py-2 px-3 font-mono font-bold text-primary text-[11px]">
                                                    #{{ med.rank }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-bold text-foreground">
                                                    {{ med.generic_name }} <span v-if="med.brand_name" class="text-muted-foreground font-normal">({{ med.brand_name }})</span>
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono font-bold text-foreground">
                                                    {{ med.units_dispensed }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono text-muted-foreground">
                                                    {{ med.dispense_events_count }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 text-right font-mono font-bold text-emerald-600">
                                                    {{ formatCurrency(med.total_revenue_tzs) }}
                                                </TableCell>
                                            </TableRow>

                                            <TableRow v-if="(pharmaco.fast_moving_medications || []).length === 0">
                                                <TableCell colspan="5" class="text-center py-10 text-muted-foreground text-xs">
                                                    No medication dispensation logged in this period.
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 6: BED OCCUPANCY & OPERATIONAL EFFICIENCY                 -->
                        <!-- ============================================================== -->
                        <div v-else-if="activeSection === 'operations'" class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Bed Occupancy (BOR)</div>
                                    <div class="text-lg font-extrabold text-sky-600 font-mono mt-1">
                                        {{ formatPercent(operational.bed_occupancy?.bor_percent) }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        Occupied: {{ operational.bed_occupancy?.occupied_beds }} / {{ operational.bed_occupancy?.total_beds }} Beds
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Avg Length of Stay (ALOS)</div>
                                    <div class="text-lg font-extrabold text-foreground font-mono mt-1">
                                        {{ operational.inpatient_throughput?.alos_days }} Days
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        Discharges: {{ operational.inpatient_throughput?.total_discharges }}
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Antibiotic Prescribing Rate</div>
                                    <div class="text-lg font-extrabold font-mono mt-1" :class="(operational.clinical_efficiency?.antibiotic_prescribing_rate_percent || 0) > 40 ? 'text-amber-600' : 'text-emerald-600'">
                                        {{ formatPercent(operational.clinical_efficiency?.antibiotic_prescribing_rate_percent) }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        Avg Consult Time: {{ operational.clinical_efficiency?.avg_consultation_time_mins }} mins
                                    </div>
                                </div>
                            </div>

                            <!-- Ward Occupancy List -->
                            <div class="bg-card rounded-lg overflow-hidden border border-border/60 shadow-2xs">
                                <div class="p-3 bg-muted/20 border-b border-border/60 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Bed class="w-4 h-4 text-primary" />
                                        <span class="text-xs font-bold text-foreground uppercase tracking-wider">
                                            Ward-by-Ward Census & Bed Utilization
                                        </span>
                                    </div>
                                </div>

                                <div class="p-3">
                                    <Table class="w-full text-xs">
                                        <TableHeader>
                                            <TableRow class="bg-muted/10 border-b border-border/40 text-[10px] uppercase font-bold text-muted-foreground">
                                                <TableHead class="py-2 px-3">Ward Name</TableHead>
                                                <TableHead class="py-2 px-3">Ward Type</TableHead>
                                                <TableHead class="py-2 px-3">Total Beds</TableHead>
                                                <TableHead class="py-2 px-3">Occupied</TableHead>
                                                <TableHead class="py-2 px-3">Available</TableHead>
                                                <TableHead class="py-2 px-3 text-right">Occupancy %</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow 
                                                v-for="ward in (operational.bed_occupancy?.ward_breakdown || [])" 
                                                :key="ward.ward_id"
                                                class="hover:bg-muted/20 border-b border-border/30"
                                            >
                                                <TableCell class="py-2 px-3 font-bold text-foreground">
                                                    {{ ward.name }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 text-muted-foreground">
                                                    {{ ward.type }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono font-bold text-foreground">
                                                    {{ ward.total_beds }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono text-rose-600 font-semibold">
                                                    {{ ward.occupied_beds }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 font-mono text-emerald-600 font-semibold">
                                                    {{ ward.available_beds }}
                                                </TableCell>
                                                <TableCell class="py-2 px-3 text-right font-mono font-extrabold text-foreground">
                                                    {{ ward.occupancy_percent }}%
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 7: MTUHA BOOK 1 — OPD MORBIDITY & ATTENDANCES REGISTER    -->
                        <!-- ============================================================== -->
                        <div v-else-if="activeSection === 'mtuha_book1'" class="space-y-3">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground">Total OPD Attendances</div>
                                    <div class="text-lg font-extrabold text-primary font-mono mt-1">
                                        {{ mtuha.book1?.summary?.total_opd_attendances || 0 }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">Ministry Stat. OPD Register</div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground">Under-5 Years</div>
                                    <div class="text-lg font-extrabold text-indigo-600 font-mono mt-1">
                                        {{ mtuha.book1?.summary?.total_under_five || 0 }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        M: {{ mtuha.book1?.summary?.under_five_male || 0 }} • F: {{ mtuha.book1?.summary?.under_five_female || 0 }}
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground">5 Years and Above</div>
                                    <div class="text-lg font-extrabold text-foreground font-mono mt-1">
                                        {{ mtuha.book1?.summary?.total_over_five || 0 }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        M: {{ mtuha.book1?.summary?.over_five_male || 0 }} • F: {{ mtuha.book1?.summary?.over_five_female || 0 }}
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground">DHIS2 Synchronized</div>
                                    <div class="text-lg font-extrabold text-emerald-600 font-mono mt-1">
                                        Active
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">MoH DataValueSet v3 Ready</div>
                                </div>
                            </div>

                            <!-- OPD Morbidity Tally Table -->
                            <div class="bg-card rounded-lg border border-border/60 overflow-hidden shadow-2xs">
                                <div class="px-3 py-2 border-b border-border/60 bg-muted/20 flex items-center justify-between">
                                    <span class="text-xs font-bold text-foreground uppercase tracking-wider">MoH OPD Morbidity Surveillance Tally (ICD-10)</span>
                                </div>
                                <Table class="w-full text-xs">
                                    <TableHeader>
                                        <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10">
                                            <TableHead class="py-1 px-3 w-20">ICD-10</TableHead>
                                            <TableHead class="py-1 px-3">Disease / Clinical Diagnosis</TableHead>
                                            <TableHead class="py-1 px-3 text-center">&lt; 5y Male</TableHead>
                                            <TableHead class="py-1 px-3 text-center">&lt; 5y Female</TableHead>
                                            <TableHead class="py-1 px-3 text-center">&gt;= 5y Male</TableHead>
                                            <TableHead class="py-1 px-3 text-center">&gt;= 5y Female</TableHead>
                                            <TableHead class="py-1 px-3 text-right">Total Cases</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="item in mtuha.book1?.top_morbidity_tallies || []"
                                            :key="item.icd10_code"
                                            class="h-8 border-b border-border/30 hover:bg-muted/20"
                                        >
                                            <TableCell class="py-1 px-3 font-mono font-bold text-primary text-[11px]">{{ item.icd10_code }}</TableCell>
                                            <TableCell class="py-1 px-3 font-medium text-foreground">{{ item.description }}</TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono text-muted-foreground">{{ item.under_5_male }}</TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono text-muted-foreground">{{ item.under_5_female }}</TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono text-muted-foreground">{{ item.over_5_male }}</TableCell>
                                            <TableCell class="py-1 px-3 text-center font-mono text-muted-foreground">{{ item.over_5_female }}</TableCell>
                                            <TableCell class="py-1 px-3 text-right font-mono font-bold text-foreground">{{ item.total_cases }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 8: MTUHA BOOK 2 — IPD ADMISSIONS, DISCHARGES & MORTALITY  -->
                        <!-- ============================================================== -->
                        <div v-else-if="activeSection === 'mtuha_book2'" class="space-y-3">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground">Total Admissions</div>
                                    <div class="text-lg font-extrabold text-foreground font-mono mt-1">
                                        {{ mtuha.book2?.summary?.total_admissions || 0 }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">IPD Book 2 Registry</div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground">Total Discharges</div>
                                    <div class="text-lg font-extrabold text-emerald-600 font-mono mt-1">
                                        {{ mtuha.book2?.summary?.total_discharges || 0 }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        Alive: {{ mtuha.book2?.summary?.discharged_alive || 0 }} • Trx: {{ mtuha.book2?.summary?.transferred_out || 0 }}
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground">Total Deaths</div>
                                    <div class="text-lg font-extrabold text-rose-600 font-mono mt-1">
                                        {{ mtuha.book2?.summary?.total_deaths || 0 }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        U5: {{ mtuha.book2?.summary?.deaths_under_five || 0 }} • &gt;5y: {{ mtuha.book2?.summary?.deaths_five_and_above || 0 }}
                                    </div>
                                </div>
                                <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                    <div class="text-[10px] font-bold uppercase text-muted-foreground">Bed Occupancy Rate (BOR)</div>
                                    <div class="text-lg font-extrabold text-indigo-600 font-mono mt-1">
                                        {{ mtuha.book2?.summary?.bed_occupancy_rate_pct || 0 }}%
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        ALOS: {{ mtuha.book2?.summary?.average_length_of_stay_days || 0 }} Days
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- VIEW 9: MTUHA BOOK 5 — LAB & LOGISTICS TRACER SURVEILLANCE     -->
                        <!-- ============================================================== -->
                        <div v-else-if="activeSection === 'mtuha_book5'" class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Lab Diagnostics -->
                                <div class="bg-card p-4 rounded-xl border border-border/60 shadow-2xs space-y-2.5">
                                    <div class="flex items-center gap-2 border-b border-border/40 pb-2">
                                        <FlaskConical class="w-4 h-4 text-primary" />
                                        <h4 class="font-bold text-xs text-foreground uppercase tracking-wider">Laboratory Diagnostic Surveillance</h4>
                                    </div>
                                    <div class="space-y-2 text-xs">
                                        <div class="flex items-center justify-between p-2 bg-muted/20 rounded">
                                            <span>Total Investigations Performed</span>
                                            <strong class="font-mono">{{ mtuha.book5?.laboratory?.total_investigations_performed || 0 }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between p-2 bg-muted/20 rounded">
                                            <span>Malaria Tests (mRDT / Blood Slide)</span>
                                            <strong class="font-mono">{{ mtuha.book5?.laboratory?.malaria_tests_performed || 0 }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between p-2 bg-muted/20 rounded">
                                            <span>Malaria Positivity Rate</span>
                                            <strong class="font-mono text-rose-600">{{ mtuha.book5?.laboratory?.malaria_positivity_rate_pct || 0 }}% ({{ mtuha.book5?.laboratory?.malaria_positive_cases || 0 }} positive)</strong>
                                        </div>
                                        <div class="flex items-center justify-between p-2 bg-muted/20 rounded">
                                            <span>Full Blood Pictures (FBP / CBC)</span>
                                            <strong class="font-mono">{{ mtuha.book5?.laboratory?.full_blood_pictures_done || 0 }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pharmacy Essential Medicines Tracer -->
                                <div class="bg-card p-4 rounded-xl border border-border/60 shadow-2xs space-y-2.5">
                                    <div class="flex items-center gap-2 border-b border-border/40 pb-2">
                                        <Pill class="w-4 h-4 text-emerald-600" />
                                        <h4 class="font-bold text-xs text-foreground uppercase tracking-wider">Essential Medicines Availability</h4>
                                    </div>
                                    <div class="space-y-2 text-xs">
                                        <div class="flex items-center justify-between p-2 bg-muted/20 rounded">
                                            <span>Tracer Drug Items Monitored</span>
                                            <strong class="font-mono">{{ mtuha.book5?.pharmacy_tracer_medicines?.total_managed_items || 0 }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between p-2 bg-muted/20 rounded">
                                            <span>Stockout Rate</span>
                                            <strong class="font-mono text-amber-600">{{ mtuha.book5?.pharmacy_tracer_medicines?.stockout_rate_pct || 0 }}% ({{ mtuha.book5?.pharmacy_tracer_medicines?.stockout_items_count || 0 }} out)</strong>
                                        </div>
                                        <div class="flex items-center justify-between p-2 bg-muted/20 rounded">
                                            <span>Tracer Availability Rate</span>
                                            <strong class="font-mono text-emerald-600 font-bold">{{ mtuha.book5?.pharmacy_tracer_medicines?.availability_rate_pct || 100 }}%</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT PANEL: ANALYTICS 360 TELEMETRY -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    :title="contextTitle"
                    :icon="contextIcon"
                    :width="width"
                    @close="close"
                >
                    <!-- Morbidity 360 -->
                    <div v-if="activeSection === 'morbidity' && selectedDiagnosis" class="space-y-3.5 text-xs">
                        <div class="p-3 bg-muted/20 rounded-lg border border-border/50 space-y-1.5">
                            <div class="font-bold text-foreground text-sm">{{ selectedDiagnosis.description }}</div>
                            <div class="font-mono text-primary text-[11px]">ICD-10 Code: {{ selectedDiagnosis.icd10_code }}</div>
                            <div class="text-[11px] text-muted-foreground">Rank #{{ selectedDiagnosis.rank }} · Total: {{ selectedDiagnosis.total_cases }} cases ({{ selectedDiagnosis.percentage }}%)</div>
                        </div>

                        <div class="space-y-2">
                            <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Age Group Stratification</div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between py-1 border-b border-border/30">
                                    <span class="text-muted-foreground">Pediatric (&lt;5 years)</span>
                                    <span class="font-mono font-bold text-foreground">{{ selectedDiagnosis.demographics?.under_5 }} cases</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-border/30">
                                    <span class="text-muted-foreground">Children (5 - 17 years)</span>
                                    <span class="font-mono font-bold text-foreground">{{ selectedDiagnosis.demographics?.age_5_17 }} cases</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-border/30">
                                    <span class="text-muted-foreground">Adults (18 - 59 years)</span>
                                    <span class="font-mono font-bold text-foreground">{{ selectedDiagnosis.demographics?.age_18_59 }} cases</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-border/30">
                                    <span class="text-muted-foreground">Geriatric (60+ years)</span>
                                    <span class="font-mono font-bold text-foreground">{{ selectedDiagnosis.demographics?.age_60_plus }} cases</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">Clinical Certainty</div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="p-2 bg-emerald-500/10 rounded border border-emerald-500/30 text-center">
                                    <div class="text-[9px] text-emerald-700 dark:text-emerald-300 font-bold uppercase">Confirmed</div>
                                    <div class="text-sm font-extrabold text-emerald-600 font-mono">{{ selectedDiagnosis.confirmed_cases }}</div>
                                </div>
                                <div class="p-2 bg-amber-500/10 rounded border border-amber-500/30 text-center">
                                    <div class="text-[9px] text-amber-700 dark:text-amber-300 font-bold uppercase">Suspected</div>
                                    <div class="text-sm font-extrabold text-amber-600 font-mono">{{ selectedDiagnosis.suspected_cases }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- General Analytics Context -->
                    <div v-else class="text-center py-12 text-muted-foreground text-xs space-y-2">
                        <TrendingUp class="w-8 h-8 text-muted-foreground/40 mx-auto" />
                        <div>Click any diagnosis, cost center, or formulary item to inspect deep demographic & financial telemetry.</div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
