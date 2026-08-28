<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Button from '@/Components/ui/Button.vue';
import {
    LayoutDashboard,
    TrendingUp,
    Building2,
    Users,
    Bed,
    Stethoscope,
    ShieldCheck,
    Receipt,
    Pill,
    FlaskConical,
    Scissors,
    Clock,
    DollarSign,
    HardDrive,
    ArrowUpRight,
    Lock,
    RefreshCw
} from '@lucide/vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    executive: {
        type: Object,
        required: true,
    },
});

const activeTab = ref('overview');
const { preferences, cycleSidebarState, setSidebarState, toggleContext, openContext } = useWorkspacePreferences();

const tenant = computed(() => props.executive.tenant || {});
const quotas = computed(() => props.executive.quotas || {});
const finance = computed(() => props.executive.finance || {});
const census = computed(() => props.executive.census || {});
const workload = computed(() => props.executive.workload || {});
const alerts = computed(() => props.executive.alerts || {});
const recentAudits = computed(() => props.executive.recent_audits || []);
const facilities = computed(() => props.executive.facilities || []);

const formatCurrency = (val) => {
    return new Intl.NumberFormat('en-TZ', {
        style: 'currency',
        currency: 'TZS',
        maximumFractionDigits: 0,
    }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' · ' + d.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const reloadData = () => {
    router.reload({ preserveScroll: true });
};
</script>

<template>
    <Head title="Executive Command Center" />

    <AfyaShell active-module="dashboard">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            <!-- 1. LEFT SIDEBAR: Executive Control Navigation (Collapsible) -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Executive Control"
                    :icon="Building2"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2.5 py-1.5 text-[9.5px] font-bold text-muted-foreground uppercase tracking-wider">
                        Executive Operations
                    </div>
                    <AfyaSidebarItem
                        :icon="LayoutDashboard"
                        label="Executive Overview"
                        :active="activeTab === 'overview'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'overview'"
                    />
                    <AfyaSidebarItem
                        :icon="TrendingUp"
                        label="Department Load"
                        :active="activeTab === 'workload'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'workload'"
                    />
                    <AfyaSidebarItem
                        :icon="Receipt"
                        label="Revenue & Ledger"
                        :active="activeTab === 'finance'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'finance'"
                    />
                    <AfyaSidebarItem
                        :icon="Building2"
                        label="Hospital Branches"
                        :badge="facilities.length.toString()"
                        :active="activeTab === 'branches'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'branches'"
                    />
                    <AfyaSidebarItem
                        :icon="ShieldCheck"
                        label="Security & Audit"
                        :active="activeTab === 'audit'"
                        :collapsed="state === 'collapsed'"
                        @click="activeTab = 'audit'"
                    />

                    <div v-if="state !== 'collapsed'" class="mt-3 pt-2.5 border-t border-border/50 px-1.5 space-y-0.5">
                        <div class="text-[9px] font-bold text-muted-foreground uppercase tracking-wider px-1">
                            Quick Operations
                        </div>
                        <Link :href="route('access-control.workspace')" class="flex items-center gap-1.5 px-2 py-1 text-[11px] text-muted-foreground hover:text-foreground hover:bg-muted rounded transition">
                            <Users class="w-3 h-3" />
                            <span>Staff Accounts</span>
                        </Link>
                        <Link :href="route('reports.workspace')" class="flex items-center gap-1.5 px-2 py-1 text-[11px] text-muted-foreground hover:text-foreground hover:bg-muted rounded transition">
                            <TrendingUp class="w-3 h-3" />
                            <span>BI & Analytics</span>
                        </Link>
                        <Link :href="route('patients.index')" class="flex items-center gap-1.5 px-2 py-1 text-[11px] text-muted-foreground hover:text-foreground hover:bg-muted rounded transition">
                            <Stethoscope class="w-3 h-3" />
                            <span>Patient Registry</span>
                        </Link>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER PANEL: Primary Executive Workspace -->
            <AfyaWorkspaceMain :breadcrumbs="[{ label: 'Executive Command Center', active: true }]">
                <div class="w-full space-y-4 text-xs">
                    <!-- Top Compact Executive Banner -->
                    <div class="flex items-center justify-between bg-card border border-border/60 rounded-lg px-3 py-2 shadow-2xs">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-7 h-7 rounded-md bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-bold shrink-0">
                                <Building2 class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h1 class="text-xs font-bold text-foreground truncate">{{ tenant.name }}</h1>
                                    <span class="text-[9px] font-mono uppercase font-bold px-1.5 py-0.2 rounded bg-primary/10 text-primary border border-primary/20">
                                        {{ tenant.plan }}
                                    </span>
                                    <span class="text-[9px] font-semibold px-1.5 py-0.2 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        Active Operations
                                    </span>
                                </div>
                                <p class="text-[10.5px] text-muted-foreground truncate">
                                    Hospital Executive Operations & Governance Command Center · {{ tenant.slug }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <Button variant="outline" size="sm" @click="reloadData" class="h-6 px-2 text-[11px] gap-1">
                                <RefreshCw class="w-3 h-3" />
                                <span>Refresh</span>
                            </Button>
                            <Link :href="route('reports.workspace')">
                                <Button size="sm" class="h-6 px-2 text-[11px] gap-1">
                                    <TrendingUp class="w-3 h-3" />
                                    <span>BI Reports</span>
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <!-- 4 Compact High-Density KPI Metric Cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                        <!-- Revenue Card -->
                        <div class="bg-card border border-border/60 rounded-lg p-2.5 shadow-2xs relative">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Today's Revenue</span>
                                <div class="w-5 h-5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                    <DollarSign class="w-3 h-3" />
                                </div>
                            </div>
                            <div class="mt-1">
                                <div class="text-base font-bold text-foreground font-mono leading-none">
                                    {{ formatCurrency(finance.today_revenue) }}
                                </div>
                                <div class="flex items-center gap-1 mt-1 text-[10px] text-muted-foreground truncate">
                                    <span class="text-amber-600 dark:text-amber-400 font-semibold font-mono">{{ finance.unpaid_invoices_count }}</span> unpaid bills ({{ formatCurrency(finance.unpaid_invoices_amount) }})
                                </div>
                            </div>
                        </div>

                        <!-- Bed Census Card -->
                        <div class="bg-card border border-border/60 rounded-lg p-2.5 shadow-2xs relative">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Bed Census</span>
                                <div class="w-5 h-5 rounded bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                    <Bed class="w-3 h-3" />
                                </div>
                            </div>
                            <div class="mt-1">
                                <div class="flex items-baseline gap-1.5 leading-none">
                                    <span class="text-base font-bold text-foreground font-mono">{{ census.occupied_beds }} / {{ census.total_beds }}</span>
                                    <span class="text-[10.5px] font-bold text-blue-600 dark:text-blue-400 font-mono">({{ census.occupancy_rate }}%)</span>
                                </div>
                                <div class="w-full h-1 bg-muted rounded-full overflow-hidden mt-1.5">
                                    <div class="h-full bg-blue-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, census.occupancy_rate)}%` }"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Encounters Today Card -->
                        <div class="bg-card border border-border/60 rounded-lg p-2.5 shadow-2xs relative">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Clinical Encounters</span>
                                <div class="w-5 h-5 rounded bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                    <Stethoscope class="w-3 h-3" />
                                </div>
                            </div>
                            <div class="mt-1">
                                <div class="text-base font-bold text-foreground font-mono leading-none">
                                    {{ workload.today_encounters }}
                                </div>
                                <div class="flex items-center gap-1 mt-1 text-[10px] text-muted-foreground truncate">
                                    <span class="text-purple-600 dark:text-purple-400 font-semibold">{{ workload.active_encounters }} active</span>
                                    <span>·</span>
                                    <span>{{ workload.today_admissions }} IPD</span>
                                </div>
                            </div>
                        </div>

                        <!-- Queue Pressure Card -->
                        <div class="bg-card border border-border/60 rounded-lg p-2.5 shadow-2xs relative">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Queue Load</span>
                                <div class="w-5 h-5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                    <Clock class="w-3 h-3" />
                                </div>
                            </div>
                            <div class="mt-1">
                                <div class="text-base font-bold text-foreground font-mono leading-none">
                                    {{ Object.values(workload.point_counts || {}).reduce((a, b) => a + b, 0) }}
                                </div>
                                <div class="flex items-center gap-1 mt-1 text-[10px] text-muted-foreground truncate">
                                    <span>Dr: {{ workload.point_counts?.Doctor || 0 }}</span>
                                    <span>·</span>
                                    <span>Lab: {{ workload.point_counts?.Lab || 0 }}</span>
                                    <span>·</span>
                                    <span>Rx: {{ workload.point_counts?.Pharmacy || 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Tab Content -->
                    <div v-if="activeTab === 'overview'" class="space-y-2.5">
                        <!-- Compact Real-time Department Operations Matrix -->
                        <div class="bg-card border border-border/60 rounded-lg p-3 shadow-2xs">
                            <div class="flex items-center justify-between mb-2 pb-1.5 border-b border-border/50">
                                <div class="flex items-center gap-1.5">
                                    <LayoutDashboard class="w-3.5 h-3.5 text-primary" />
                                    <h2 class="text-[11px] font-bold text-foreground uppercase tracking-wider">Hospital Department Throughput</h2>
                                </div>
                                <span class="text-[9.5px] text-muted-foreground">Click any department to switch</span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                                <!-- OPD & Clinical -->
                                <Link :href="route('workspace.clinical')" class="p-2 rounded-md border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group flex flex-col justify-between">
                                    <div class="flex items-center justify-between">
                                        <div class="w-6 h-6 rounded bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                                            <Stethoscope class="w-3.5 h-3.5" />
                                        </div>
                                        <ArrowUpRight class="w-3 h-3 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-2">
                                        <div class="font-bold text-[11px] text-foreground group-hover:text-primary transition truncate">Consultations</div>
                                        <div class="text-[9.5px] text-muted-foreground">OPD Suites</div>
                                    </div>
                                    <div class="mt-1.5 pt-1 border-t border-border/40 flex items-center justify-between text-[10px]">
                                        <span class="text-muted-foreground">Waiting:</span>
                                        <span class="font-bold font-mono text-purple-600 dark:text-purple-400">{{ workload.point_counts?.Doctor || 0 }}</span>
                                    </div>
                                </Link>

                                <!-- Inpatient Wards -->
                                <Link :href="route('inpatient.workspace')" class="p-2 rounded-md border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group flex flex-col justify-between">
                                    <div class="flex items-center justify-between">
                                        <div class="w-6 h-6 rounded bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                                            <Bed class="w-3.5 h-3.5" />
                                        </div>
                                        <ArrowUpRight class="w-3 h-3 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-2">
                                        <div class="font-bold text-[11px] text-foreground group-hover:text-primary transition truncate">Inpatient Wards</div>
                                        <div class="text-[9.5px] text-muted-foreground">Bed Census</div>
                                    </div>
                                    <div class="mt-1.5 pt-1 border-t border-border/40 flex items-center justify-between text-[10px]">
                                        <span class="text-muted-foreground">Occupied:</span>
                                        <span class="font-bold font-mono text-blue-600 dark:text-blue-400">{{ census.occupied_beds }}/{{ census.total_beds }}</span>
                                    </div>
                                </Link>

                                <!-- Pharmacy -->
                                <Link :href="route('pharmacy.queue')" class="p-2 rounded-md border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group flex flex-col justify-between">
                                    <div class="flex items-center justify-between">
                                        <div class="w-6 h-6 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                                            <Pill class="w-3.5 h-3.5" />
                                        </div>
                                        <ArrowUpRight class="w-3 h-3 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-2">
                                        <div class="font-bold text-[11px] text-foreground group-hover:text-primary transition truncate">Pharmacy</div>
                                        <div class="text-[9.5px] text-muted-foreground">Dispensary</div>
                                    </div>
                                    <div class="mt-1.5 pt-1 border-t border-border/40 flex items-center justify-between text-[10px]">
                                        <span class="text-muted-foreground">Rx Queue:</span>
                                        <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ workload.point_counts?.Pharmacy || 0 }}</span>
                                    </div>
                                </Link>

                                <!-- Laboratory -->
                                <Link :href="route('laboratory.workspace')" class="p-2 rounded-md border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group flex flex-col justify-between">
                                    <div class="flex items-center justify-between">
                                        <div class="w-6 h-6 rounded bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 flex items-center justify-center font-bold">
                                            <FlaskConical class="w-3.5 h-3.5" />
                                        </div>
                                        <ArrowUpRight class="w-3 h-3 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-2">
                                        <div class="font-bold text-[11px] text-foreground group-hover:text-primary transition truncate">Laboratory</div>
                                        <div class="text-[9.5px] text-muted-foreground">Diagnostic Lab</div>
                                    </div>
                                    <div class="mt-1.5 pt-1 border-t border-border/40 flex items-center justify-between text-[10px]">
                                        <span class="text-muted-foreground">Lab Orders:</span>
                                        <span class="font-bold font-mono text-cyan-600 dark:text-cyan-400">{{ workload.point_counts?.Lab || 0 }}</span>
                                    </div>
                                </Link>

                                <!-- Procedures & Surgery -->
                                <Link :href="route('procedures.workspace')" class="p-2 rounded-md border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group flex flex-col justify-between">
                                    <div class="flex items-center justify-between">
                                        <div class="w-6 h-6 rounded bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
                                            <Scissors class="w-3.5 h-3.5" />
                                        </div>
                                        <ArrowUpRight class="w-3 h-3 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-2">
                                        <div class="font-bold text-[11px] text-foreground group-hover:text-primary transition truncate">Theatre & Surgery</div>
                                        <div class="text-[9.5px] text-muted-foreground">Procedure Suite</div>
                                    </div>
                                    <div class="mt-1.5 pt-1 border-t border-border/40 flex items-center justify-between text-[10px]">
                                        <span class="text-muted-foreground">Waiting:</span>
                                        <span class="font-bold font-mono text-rose-600 dark:text-rose-400">{{ workload.point_counts?.Procedure || 0 }}</span>
                                    </div>
                                </Link>

                                <!-- Billing & POS -->
                                <Link :href="route('billing.desk')" class="p-2 rounded-md border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group flex flex-col justify-between">
                                    <div class="flex items-center justify-between">
                                        <div class="w-6 h-6 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                                            <Receipt class="w-3.5 h-3.5" />
                                        </div>
                                        <ArrowUpRight class="w-3 h-3 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-2">
                                        <div class="font-bold text-[11px] text-foreground group-hover:text-primary transition truncate">Billing & Cashier</div>
                                        <div class="text-[9.5px] text-muted-foreground">POS Tills</div>
                                    </div>
                                    <div class="mt-1.5 pt-1 border-t border-border/40 flex items-center justify-between text-[10px]">
                                        <span class="text-muted-foreground">Cashier:</span>
                                        <span class="font-bold font-mono text-amber-600 dark:text-amber-400">{{ workload.point_counts?.Cashier || 0 }}</span>
                                    </div>
                                </Link>
                            </div>
                        </div>

                        <!-- 2-Column: Compact Quotas & Security Audit Stream -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-2.5">
                            <!-- Quotas & Fleet Utilization -->
                            <div class="bg-card border border-border/60 rounded-lg p-3 shadow-2xs space-y-2.5">
                                <div class="flex items-center justify-between border-b border-border/50 pb-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <HardDrive class="w-3.5 h-3.5 text-primary" />
                                        <h3 class="text-[10.5px] font-bold text-foreground uppercase tracking-wider">Tenant Subscription Quotas</h3>
                                    </div>
                                    <span class="text-[9.5px] font-mono font-bold text-muted-foreground">{{ tenant.plan }}</span>
                                </div>

                                <div class="space-y-2">
                                    <!-- User Accounts -->
                                    <div>
                                        <div class="flex justify-between text-[10.5px] mb-0.5">
                                            <span class="text-muted-foreground">Staff Accounts</span>
                                            <span class="font-bold font-mono">{{ quotas.users_used }} / {{ quotas.max_users }}</span>
                                        </div>
                                        <div class="w-full h-1 bg-muted rounded-full overflow-hidden">
                                            <div class="h-full bg-primary rounded-full" :style="{ width: `${Math.min(100, (quotas.users_used / (quotas.max_users || 1)) * 100)}%` }"></div>
                                        </div>
                                    </div>

                                    <!-- Facility Branches -->
                                    <div>
                                        <div class="flex justify-between text-[10.5px] mb-0.5">
                                            <span class="text-muted-foreground">Facility Branches</span>
                                            <span class="font-bold font-mono">{{ quotas.facilities_used }} / {{ quotas.max_facilities }}</span>
                                        </div>
                                        <div class="w-full h-1 bg-muted rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-500 rounded-full" :style="{ width: `${Math.min(100, (quotas.facilities_used / (quotas.max_facilities || 1)) * 100)}%` }"></div>
                                        </div>
                                    </div>

                                    <!-- Storage Quota -->
                                    <div>
                                        <div class="flex justify-between text-[10.5px] mb-0.5">
                                            <span class="text-muted-foreground">Document Storage</span>
                                            <span class="font-bold font-mono">{{ quotas.storage_used_mb }} MB / {{ quotas.storage_quota_mb }} MB</span>
                                        </div>
                                        <div class="w-full h-1 bg-muted rounded-full overflow-hidden">
                                            <div class="h-full bg-blue-500 rounded-full" :style="{ width: `${Math.min(100, (quotas.storage_used_mb / (quotas.storage_quota_mb || 1)) * 100)}%` }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Forensic Security Audit Stream -->
                            <div class="bg-card border border-border/60 rounded-lg p-3 shadow-2xs space-y-2">
                                <div class="flex items-center justify-between border-b border-border/50 pb-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
                                        <h3 class="text-[10.5px] font-bold text-foreground uppercase tracking-wider">Forensic Audit Stream</h3>
                                    </div>
                                    <Link :href="route('audit.workspace')" class="text-[9.5px] font-semibold text-primary hover:underline">
                                        Audit Ledger →
                                    </Link>
                                </div>

                                <div class="space-y-1.5 max-h-40 overflow-y-auto">
                                    <div v-for="log in recentAudits" :key="log.id" class="p-1.5 rounded bg-muted/30 border border-border/40 text-[10px] flex items-center justify-between gap-1.5">
                                        <div class="min-w-0">
                                            <div class="font-bold text-foreground truncate">{{ log.action }}</div>
                                            <div class="text-[9px] text-muted-foreground truncate">{{ log.event_category }} · IP: {{ log.ip_address }}</div>
                                        </div>
                                        <span class="text-[9px] text-muted-foreground font-mono shrink-0">{{ formatDate(log.created_at) }}</span>
                                    </div>

                                    <div v-if="recentAudits.length === 0" class="text-center py-4 text-[10px] text-muted-foreground">
                                        No recent audit events logged.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Workload Detailed Tab -->
                    <div v-else-if="activeTab === 'workload'" class="bg-card border border-border/60 rounded-lg p-3 shadow-2xs space-y-2.5">
                        <h2 class="text-[11px] font-bold text-foreground uppercase tracking-wider">Live Service Point Breakdown</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                            <div v-for="(count, point) in workload.point_counts" :key="point" class="p-2.5 rounded-md bg-muted/30 border border-border/50 text-center">
                                <div class="text-[9.5px] font-bold text-muted-foreground uppercase">{{ point }}</div>
                                <div class="text-xl font-bold font-mono text-foreground mt-0.5">{{ count }}</div>
                                <div class="text-[9px] text-muted-foreground">waiting</div>
                            </div>
                        </div>
                    </div>

                    <!-- Finance Detailed Tab -->
                    <div v-else-if="activeTab === 'finance'" class="bg-card border border-border/60 rounded-lg p-3 shadow-2xs space-y-2.5">
                        <h2 class="text-[11px] font-bold text-foreground uppercase tracking-wider">Today's Collections by Payment Method</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div v-for="(amount, method) in finance.payment_breakdown" :key="method" class="p-2.5 rounded-md bg-muted/30 border border-border/50">
                                <div class="text-[10px] font-bold text-foreground uppercase">{{ method }}</div>
                                <div class="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">{{ formatCurrency(amount) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Branches Detailed Tab -->
                    <div v-else-if="activeTab === 'branches'" class="bg-card border border-border/60 rounded-lg p-3 shadow-2xs space-y-2.5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-[11px] font-bold text-foreground uppercase tracking-wider">Registered Hospital Facilities ({{ facilities.length }})</h2>
                            <span class="text-[10px] text-muted-foreground">Quota: {{ quotas.facilities_used }} / {{ quotas.max_facilities }} used</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div v-for="fac in facilities" :key="fac.id" class="p-2.5 rounded-md bg-muted/20 border border-border/60 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-[11px] text-foreground">{{ fac.name }}</div>
                                    <div class="text-[9.5px] text-muted-foreground mt-0.5">Code: {{ fac.code }} · {{ fac.facility_type }} · {{ fac.city }}</div>
                                    <div class="text-[9.5px] text-muted-foreground">{{ fac.departments_count || 0 }} clinical departments</div>
                                </div>
                                <span class="text-[9px] font-bold px-1.5 py-0.2 rounded-full bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                                    Active
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Security & Audit Tab -->
                    <div v-else-if="activeTab === 'audit'" class="bg-card border border-border/60 rounded-lg p-3 shadow-2xs space-y-2.5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-[11px] font-bold text-foreground uppercase tracking-wider">Forensic Audit Trail</h2>
                            <Link :href="route('audit.workspace')" class="text-[10.5px] text-primary font-semibold hover:underline">
                                Open Forensic Audit Workspace →
                            </Link>
                        </div>
                        <div class="space-y-1.5">
                            <div v-for="log in recentAudits" :key="log.id" class="p-2 rounded bg-muted/30 border border-border/40 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-[11px] text-foreground">{{ log.action }} ({{ log.event_category }})</div>
                                    <div class="text-[9.5px] text-muted-foreground mt-0.5">Entity: {{ log.entity_type }} · IP: {{ log.ip_address }}</div>
                                </div>
                                <span class="text-[10px] font-mono text-muted-foreground">{{ formatDate(log.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </AfyaWorkspaceMain>

            <!-- 3. RIGHT PANEL: Executive Inspector Panel (Collapsible) -->
            <template #context="{ open, width, close }">
                <AfyaContextPanel
                    title="Executive Inspector"
                    :icon="Building2"
                    :open="open"
                    :width="width"
                    @close="close"
                >
                    <div class="p-3 space-y-3.5 text-xs">
                        <!-- Organization Quick Info -->
                        <div class="space-y-1 pb-2.5 border-b border-border/50">
                            <div class="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground">Hospital Organization</div>
                            <div class="font-bold text-xs text-foreground">{{ tenant.name }}</div>
                            <div class="text-muted-foreground text-[10.5px]">Identifier: <span class="font-mono text-foreground">{{ tenant.slug }}</span></div>
                            <div class="text-muted-foreground text-[10.5px]">SaaS Plan: <span class="font-semibold text-primary">{{ tenant.plan }}</span></div>
                        </div>

                        <!-- Executive Shortcuts Deck -->
                        <div class="space-y-1.5">
                            <div class="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground">Administration Shortcuts</div>
                            <div class="space-y-1">
                                <Link :href="route('access-control.workspace')" class="w-full flex items-center justify-between p-1.5 rounded-md bg-muted/30 hover:bg-muted text-foreground transition border border-border/40">
                                    <div class="flex items-center gap-1.5">
                                        <Users class="w-3.5 h-3.5 text-primary" />
                                        <span class="font-medium text-[11px]">Manage Staff & RBAC</span>
                                    </div>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground" />
                                </Link>

                                <Link :href="route('reports.workspace')" class="w-full flex items-center justify-between p-1.5 rounded-md bg-muted/30 hover:bg-muted text-foreground transition border border-border/40">
                                    <div class="flex items-center gap-1.5">
                                        <TrendingUp class="w-3.5 h-3.5 text-emerald-500" />
                                        <span class="font-medium text-[11px]">MoH MTUHA & BI Reports</span>
                                    </div>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground" />
                                </Link>

                                <Link :href="route('insurance.workspace')" class="w-full flex items-center justify-between p-1.5 rounded-md bg-muted/30 hover:bg-muted text-foreground transition border border-border/40">
                                    <div class="flex items-center gap-1.5">
                                        <ShieldCheck class="w-3.5 h-3.5 text-blue-500" />
                                        <span class="font-medium text-[11px]">Tariffs & NHIF Claims</span>
                                    </div>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground" />
                                </Link>

                                <Link :href="route('inventory.workspace')" class="w-full flex items-center justify-between p-1.5 rounded-md bg-muted/30 hover:bg-muted text-foreground transition border border-border/40">
                                    <div class="flex items-center gap-1.5">
                                        <HardDrive class="w-3.5 h-3.5 text-amber-500" />
                                        <span class="font-medium text-[11px]">Central Warehouse</span>
                                    </div>
                                    <ArrowUpRight class="w-3 h-3 text-muted-foreground" />
                                </Link>
                            </div>
                        </div>

                        <!-- System Security & Integrity -->
                        <div class="p-2.5 rounded-md bg-primary/5 border border-primary/20 space-y-1">
                            <div class="flex items-center gap-1 text-primary font-bold text-[10px]">
                                <Lock class="w-3 h-3" />
                                <span>Multi-Tenant RLS Protection</span>
                            </div>
                            <p class="text-[9.5px] text-muted-foreground leading-relaxed">
                                Strict database row-level security & HIPAA/MoH cryptographic audit signatures active.
                            </p>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
