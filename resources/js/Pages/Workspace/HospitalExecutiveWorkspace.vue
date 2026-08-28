<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import Button from '@/Components/ui/Button.vue';
import {
    LayoutDashboard,
    TrendingUp,
    Building2,
    Users,
    Bed,
    Stethoscope,
    Shield,
    AlertTriangle,
    Receipt,
    Pill,
    FlaskConical,
    Scissors,
    Clock,
    DollarSign,
    ShieldCheck,
    HardDrive,
    ArrowUpRight,
    Sparkles,
    CheckCircle2,
    Lock,
    Eye,
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
const { openContext } = useWorkspacePreferences();

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
        <AfyaWorkspace>
            <!-- Left Executive Navigation Sidebar (180px) -->
            <template #sidebar>
                <AfyaSidebar>
                    <div class="px-3 py-2 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Executive Control
                    </div>
                    <AfyaSidebarItem
                        :icon="LayoutDashboard"
                        label="Executive Overview"
                        :active="activeTab === 'overview'"
                        @click="activeTab = 'overview'"
                    />
                    <AfyaSidebarItem
                        :icon="TrendingUp"
                        label="Department Load"
                        :active="activeTab === 'workload'"
                        @click="activeTab = 'workload'"
                    />
                    <AfyaSidebarItem
                        :icon="Receipt"
                        label="Revenue & Ledger"
                        :active="activeTab === 'finance'"
                        @click="activeTab = 'finance'"
                    />
                    <AfyaSidebarItem
                        :icon="Building2"
                        label="Hospital Branches"
                        :count="facilities.length"
                        :active="activeTab === 'branches'"
                        @click="activeTab = 'branches'"
                    />
                    <AfyaSidebarItem
                        :icon="ShieldCheck"
                        label="Security & Audit"
                        :active="activeTab === 'audit'"
                        @click="activeTab = 'audit'"
                    />

                    <div class="mt-4 pt-3 border-t border-border/50 px-2 space-y-1">
                        <div class="text-[9px] font-bold text-muted-foreground uppercase tracking-wider px-1">
                            Quick Operations
                        </div>
                        <Link :href="route('access-control.workspace')" class="flex items-center gap-2 px-2 py-1.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted rounded transition">
                            <Users class="w-3.5 h-3.5" />
                            <span>Staff Accounts</span>
                        </Link>
                        <Link :href="route('reports.workspace')" class="flex items-center gap-2 px-2 py-1.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted rounded transition">
                            <TrendingUp class="w-3.5 h-3.5" />
                            <span>BI & Analytics</span>
                        </Link>
                        <Link :href="route('patients.index')" class="flex items-center gap-2 px-2 py-1.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted rounded transition">
                            <Stethoscope class="w-3.5 h-3.5" />
                            <span>Patient Registry</span>
                        </Link>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- Center Primary Main Panel -->
            <AfyaWorkspaceMain>
                <div class="space-y-4 max-w-7xl mx-auto p-1">
                    <!-- Top Executive Banner -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-card border border-border/60 rounded-xl p-4 shadow-2xs">
                        <div class="flex items-center gap-3.5">
                            <div class="w-11 h-11 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-bold text-lg shrink-0">
                                <Building2 class="w-6 h-6" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h1 class="text-base font-bold text-foreground">{{ tenant.name }}</h1>
                                    <span class="text-[10px] font-mono uppercase font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20">
                                        {{ tenant.plan }}
                                    </span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        Active Operations
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    Hospital Executive Operations & Governance Command Center · Scoped to {{ tenant.slug }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="sm" @click="reloadData" class="h-8 gap-1.5 text-xs">
                                <RefreshCw class="w-3.5 h-3.5" />
                                <span>Refresh</span>
                            </Button>
                            <Link :href="route('reports.workspace')">
                                <Button size="sm" class="h-8 gap-1.5 text-xs">
                                    <TrendingUp class="w-3.5 h-3.5" />
                                    <span>Hospital BI Reports</span>
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <!-- 4 High-Impact KPI Summary Cards Strip -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                        <!-- Revenue Card -->
                        <div class="bg-card border border-border/60 rounded-xl p-3.5 shadow-2xs relative overflow-hidden">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Today's Revenue</span>
                                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                    <DollarSign class="w-4 h-4" />
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="text-xl font-bold text-foreground font-mono">
                                    {{ formatCurrency(finance.today_revenue) }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-1.5 text-[11px] text-muted-foreground">
                                    <span class="text-amber-600 dark:text-amber-400 font-semibold font-mono">{{ finance.unpaid_invoices_count }}</span> unpaid bills ({{ formatCurrency(finance.unpaid_invoices_amount) }})
                                </div>
                            </div>
                        </div>

                        <!-- Bed Census Card -->
                        <div class="bg-card border border-border/60 rounded-xl p-3.5 shadow-2xs relative overflow-hidden">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Inpatient Bed Census</span>
                                <div class="w-7 h-7 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                    <Bed class="w-4 h-4" />
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-bold text-foreground font-mono">{{ census.occupied_beds }} / {{ census.total_beds }}</span>
                                    <span class="text-xs font-bold text-blue-600 dark:text-blue-400 font-mono">{{ census.occupancy_rate }}%</span>
                                </div>
                                <!-- Progress Bar -->
                                <div class="w-full h-1.5 bg-muted rounded-full overflow-hidden mt-2">
                                    <div class="h-full bg-blue-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, census.occupancy_rate)}%` }"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Encounters Today Card -->
                        <div class="bg-card border border-border/60 rounded-xl p-3.5 shadow-2xs relative overflow-hidden">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Clinical Encounters</span>
                                <div class="w-7 h-7 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                    <Stethoscope class="w-4 h-4" />
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="text-xl font-bold text-foreground font-mono">
                                    {{ workload.today_encounters }}
                                </div>
                                <div class="flex items-center gap-2 mt-1.5 text-[11px] text-muted-foreground">
                                    <span class="text-purple-600 dark:text-purple-400 font-semibold">{{ workload.active_encounters }} active now</span>
                                    <span>·</span>
                                    <span>{{ workload.today_admissions }} admissions</span>
                                </div>
                            </div>
                        </div>

                        <!-- Queue Pressure Card -->
                        <div class="bg-card border border-border/60 rounded-xl p-3.5 shadow-2xs relative overflow-hidden">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Facility Queue Load</span>
                                <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                    <Clock class="w-4 h-4" />
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="text-xl font-bold text-foreground font-mono">
                                    {{ Object.values(workload.point_counts || {}).reduce((a, b) => a + b, 0) }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-1.5 text-[11px] text-muted-foreground truncate">
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
                    <div v-if="activeTab === 'overview'" class="space-y-4">
                        <!-- Real-time Department Operations Matrix -->
                        <div class="bg-card border border-border/60 rounded-xl p-4 shadow-2xs">
                            <div class="flex items-center justify-between mb-3 border-b border-border/50 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <LayoutDashboard class="w-4 h-4 text-primary" />
                                    <h2 class="text-xs font-bold text-foreground uppercase tracking-wider">Hospital Department Throughput</h2>
                                </div>
                                <span class="text-[10px] text-muted-foreground">Click any department to inspect workspace</span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <!-- OPD & Clinical -->
                                <Link :href="route('workspace.clinical')" class="p-3 rounded-lg border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                                                <Stethoscope class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs text-foreground group-hover:text-primary transition">Clinical Consultations</div>
                                                <div class="text-[10px] text-muted-foreground">OPD & Specialist Clinics</div>
                                            </div>
                                        </div>
                                        <ArrowUpRight class="w-4 h-4 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                                        <span class="text-muted-foreground text-[11px]">Patients Waiting:</span>
                                        <span class="font-bold font-mono text-purple-600 dark:text-purple-400">{{ workload.point_counts?.Doctor || 0 }}</span>
                                    </div>
                                </Link>

                                <!-- Inpatient Wards -->
                                <Link :href="route('inpatient.workspace')" class="p-3 rounded-lg border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                                                <Bed class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs text-foreground group-hover:text-primary transition">Inpatient Wards</div>
                                                <div class="text-[10px] text-muted-foreground">Bed Census & Admissions</div>
                                            </div>
                                        </div>
                                        <ArrowUpRight class="w-4 h-4 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                                        <span class="text-muted-foreground text-[11px]">Occupancy:</span>
                                        <span class="font-bold font-mono text-blue-600 dark:text-blue-400">{{ census.occupied_beds }} / {{ census.total_beds }} ({{ census.occupancy_rate }}%)</span>
                                    </div>
                                </Link>

                                <!-- Pharmacy -->
                                <Link :href="route('pharmacy.queue')" class="p-3 rounded-lg border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                                                <Pill class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs text-foreground group-hover:text-primary transition">Pharmacy Dispensary</div>
                                                <div class="text-[10px] text-muted-foreground">Rx Verification & Stock</div>
                                            </div>
                                        </div>
                                        <ArrowUpRight class="w-4 h-4 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                                        <span class="text-muted-foreground text-[11px]">Dispense Queue:</span>
                                        <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ workload.point_counts?.Pharmacy || 0 }}</span>
                                    </div>
                                </Link>

                                <!-- Laboratory -->
                                <Link :href="route('laboratory.workspace')" class="p-3 rounded-lg border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 flex items-center justify-center font-bold">
                                                <FlaskConical class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs text-foreground group-hover:text-primary transition">Pathology Laboratory</div>
                                                <div class="text-[10px] text-muted-foreground">Specimens & Diagnostic Tests</div>
                                            </div>
                                        </div>
                                        <ArrowUpRight class="w-4 h-4 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                                        <span class="text-muted-foreground text-[11px]">Lab Queue:</span>
                                        <span class="font-bold font-mono text-cyan-600 dark:text-cyan-400">{{ workload.point_counts?.Lab || 0 }}</span>
                                    </div>
                                </Link>

                                <!-- Procedures & Surgery -->
                                <Link :href="route('procedures.workspace')" class="p-3 rounded-lg border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
                                                <Scissors class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs text-foreground group-hover:text-primary transition">Theatre & Procedures</div>
                                                <div class="text-[10px] text-muted-foreground">Minor Ops & Surgical Suites</div>
                                            </div>
                                        </div>
                                        <ArrowUpRight class="w-4 h-4 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                                        <span class="text-muted-foreground text-[11px]">Theatre Waiting:</span>
                                        <span class="font-bold font-mono text-rose-600 dark:text-rose-400">{{ workload.point_counts?.Procedure || 0 }}</span>
                                    </div>
                                </Link>

                                <!-- Billing & POS -->
                                <Link :href="route('billing.desk')" class="p-3 rounded-lg border border-border/60 bg-muted/20 hover:bg-muted/50 hover:border-primary/40 transition group">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                                                <Receipt class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs text-foreground group-hover:text-primary transition">Billing & Cashier POS</div>
                                                <div class="text-[10px] text-muted-foreground">Invoicing & Collections</div>
                                            </div>
                                        </div>
                                        <ArrowUpRight class="w-4 h-4 text-muted-foreground group-hover:text-primary transition" />
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-xs pt-2 border-t border-border/40">
                                        <span class="text-muted-foreground text-[11px]">Cashier Queue:</span>
                                        <span class="font-bold font-mono text-amber-600 dark:text-amber-400">{{ workload.point_counts?.Cashier || 0 }}</span>
                                    </div>
                                </Link>
                            </div>
                        </div>

                        <!-- 2-Column: Quota Overview & Recent Security Stream -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <!-- Quotas & Fleet Utilization -->
                            <div class="bg-card border border-border/60 rounded-xl p-4 shadow-2xs space-y-3.5">
                                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                                    <div class="flex items-center gap-2">
                                        <HardDrive class="w-4 h-4 text-primary" />
                                        <h3 class="text-xs font-bold text-foreground uppercase tracking-wider">Tenant Subscription Quotas</h3>
                                    </div>
                                    <span class="text-[10px] font-mono font-bold text-muted-foreground">{{ tenant.plan }}</span>
                                </div>

                                <div class="space-y-3">
                                    <!-- User Accounts -->
                                    <div>
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-muted-foreground">Staff User Accounts</span>
                                            <span class="font-bold font-mono">{{ quotas.users_used }} / {{ quotas.max_users }}</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-muted rounded-full overflow-hidden">
                                            <div class="h-full bg-primary rounded-full" :style="{ width: `${Math.min(100, (quotas.users_used / (quotas.max_users || 1)) * 100)}%` }"></div>
                                        </div>
                                    </div>

                                    <!-- Facility Branches -->
                                    <div>
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-muted-foreground">Hospital Facility Branches</span>
                                            <span class="font-bold font-mono">{{ quotas.facilities_used }} / {{ quotas.max_facilities }}</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-muted rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-500 rounded-full" :style="{ width: `${Math.min(100, (quotas.facilities_used / (quotas.max_facilities || 1)) * 100)}%` }"></div>
                                        </div>
                                    </div>

                                    <!-- Storage Quota -->
                                    <div>
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-muted-foreground">Clinical Document Storage</span>
                                            <span class="font-bold font-mono">{{ quotas.storage_used_mb }} MB / {{ quotas.storage_quota_mb }} MB</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-muted rounded-full overflow-hidden">
                                            <div class="h-full bg-blue-500 rounded-full" :style="{ width: `${Math.min(100, (quotas.storage_used_mb / (quotas.storage_quota_mb || 1)) * 100)}%` }"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-border/40 flex items-center justify-between text-xs">
                                    <span class="text-muted-foreground text-[11px]">Need additional capacity?</span>
                                    <span class="text-[11px] font-semibold text-primary">Contact Platform Superadmin</span>
                                </div>
                            </div>

                            <!-- Forensic Security Audit Stream -->
                            <div class="bg-card border border-border/60 rounded-xl p-4 shadow-2xs space-y-3">
                                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                                    <div class="flex items-center gap-2">
                                        <ShieldCheck class="w-4 h-4 text-emerald-500" />
                                        <h3 class="text-xs font-bold text-foreground uppercase tracking-wider">Forensic Audit Stream</h3>
                                    </div>
                                    <Link :href="route('audit.workspace')" class="text-[10px] font-semibold text-primary hover:underline">
                                        View Full Ledger →
                                    </Link>
                                </div>

                                <div class="space-y-2 max-h-52 overflow-y-auto">
                                    <div v-for="log in recentAudits" :key="log.id" class="p-2 rounded-lg bg-muted/30 border border-border/40 text-xs flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="font-bold text-[11.5px] text-foreground truncate">{{ log.action }}</div>
                                            <div class="text-[10px] text-muted-foreground truncate">{{ log.event_category }} · IP: {{ log.ip_address }}</div>
                                        </div>
                                        <span class="text-[10px] text-muted-foreground font-mono shrink-0">{{ formatDate(log.created_at) }}</span>
                                    </div>

                                    <div v-if="recentAudits.length === 0" class="text-center py-6 text-xs text-muted-foreground">
                                        No recent audit events logged.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Workload Detailed Tab -->
                    <div v-else-if="activeTab === 'workload'" class="bg-card border border-border/60 rounded-xl p-4 shadow-2xs space-y-4">
                        <h2 class="text-xs font-bold text-foreground uppercase tracking-wider">Live Service Point Breakdown</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                            <div v-for="(count, point) in workload.point_counts" :key="point" class="p-3 rounded-lg bg-muted/30 border border-border/50 text-center">
                                <div class="text-[10px] font-bold text-muted-foreground uppercase">{{ point }}</div>
                                <div class="text-2xl font-bold font-mono text-foreground mt-1">{{ count }}</div>
                                <div class="text-[10px] text-muted-foreground mt-0.5">waiting</div>
                            </div>
                        </div>
                    </div>

                    <!-- Finance Detailed Tab -->
                    <div v-else-if="activeTab === 'finance'" class="bg-card border border-border/60 rounded-xl p-4 shadow-2xs space-y-4">
                        <h2 class="text-xs font-bold text-foreground uppercase tracking-wider">Today's Collections by Payment Method</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div v-for="(amount, method) in finance.payment_breakdown" :key="method" class="p-3.5 rounded-lg bg-muted/30 border border-border/50">
                                <div class="text-xs font-bold text-foreground uppercase">{{ method }}</div>
                                <div class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1">{{ formatCurrency(amount) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Branches Detailed Tab -->
                    <div v-else-if="activeTab === 'branches'" class="bg-card border border-border/60 rounded-xl p-4 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-bold text-foreground uppercase tracking-wider">Registered Hospital Facilities ({{ facilities.length }})</h2>
                            <span class="text-xs text-muted-foreground">Quota: {{ quotas.facilities_used }} / {{ quotas.max_facilities }} used</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div v-for="fac in facilities" :key="fac.id" class="p-3.5 rounded-lg bg-muted/20 border border-border/60 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-xs text-foreground">{{ fac.name }}</div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">Code: {{ fac.code }} · {{ fac.facility_type }} · {{ fac.city }}</div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">{{ fac.departments_count || 0 }} clinical departments</div>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                                    Active
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Security & Audit Tab -->
                    <div v-else-if="activeTab === 'audit'" class="bg-card border border-border/60 rounded-xl p-4 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-bold text-foreground uppercase tracking-wider">Forensic Audit Trail</h2>
                            <Link :href="route('audit.workspace')" class="text-xs text-primary font-semibold hover:underline">
                                Open Forensic Audit Workspace →
                            </Link>
                        </div>
                        <div class="space-y-2">
                            <div v-for="log in recentAudits" :key="log.id" class="p-3 rounded-lg bg-muted/30 border border-border/40 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-xs text-foreground">{{ log.action }} ({{ log.event_category }})</div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">Entity: {{ log.entity_type }} · IP: {{ log.ip_address }}</div>
                                </div>
                                <span class="text-xs font-mono text-muted-foreground">{{ formatDate(log.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </AfyaWorkspaceMain>

            <!-- Right Executive Inspector Panel (320px) -->
            <template #context>
                <AfyaContextPanel title="Executive Inspector" subtitle="Organization & Governance">
                    <div class="p-4 space-y-5 text-xs">
                        <!-- Organization Quick Info -->
                        <div class="space-y-2 pb-3 border-b border-border/50">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Hospital Organization</div>
                            <div class="font-bold text-sm text-foreground">{{ tenant.name }}</div>
                            <div class="text-muted-foreground text-[11px]">Identifier: <span class="font-mono text-foreground">{{ tenant.slug }}</span></div>
                            <div class="text-muted-foreground text-[11px]">SaaS Plan: <span class="font-semibold text-primary">{{ tenant.plan }}</span></div>
                        </div>

                        <!-- Executive Shortcuts Deck -->
                        <div class="space-y-2">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Administration Shortcuts</div>
                            <div class="space-y-1.5">
                                <Link :href="route('access-control.workspace')" class="w-full flex items-center justify-between p-2 rounded-lg bg-muted/30 hover:bg-muted text-foreground transition border border-border/40">
                                    <div class="flex items-center gap-2">
                                        <Users class="w-4 h-4 text-primary" />
                                        <span class="font-medium text-xs">Manage Staff & RBAC</span>
                                    </div>
                                    <ArrowUpRight class="w-3.5 h-3.5 text-muted-foreground" />
                                </Link>

                                <Link :href="route('reports.workspace')" class="w-full flex items-center justify-between p-2 rounded-lg bg-muted/30 hover:bg-muted text-foreground transition border border-border/40">
                                    <div class="flex items-center gap-2">
                                        <TrendingUp class="w-4 h-4 text-emerald-500" />
                                        <span class="font-medium text-xs">MoH MTUHA & BI Reports</span>
                                    </div>
                                    <ArrowUpRight class="w-3.5 h-3.5 text-muted-foreground" />
                                </Link>

                                <Link :href="route('insurance.workspace')" class="w-full flex items-center justify-between p-2 rounded-lg bg-muted/30 hover:bg-muted text-foreground transition border border-border/40">
                                    <div class="flex items-center gap-2">
                                        <ShieldCheck class="w-4 h-4 text-blue-500" />
                                        <span class="font-medium text-xs">Tariffs & NHIF Claims</span>
                                    </div>
                                    <ArrowUpRight class="w-3.5 h-3.5 text-muted-foreground" />
                                </Link>

                                <Link :href="route('inventory.workspace')" class="w-full flex items-center justify-between p-2 rounded-lg bg-muted/30 hover:bg-muted text-foreground transition border border-border/40">
                                    <div class="flex items-center gap-2">
                                        <HardDrive class="w-4 h-4 text-amber-500" />
                                        <span class="font-medium text-xs">Central Warehouse</span>
                                    </div>
                                    <ArrowUpRight class="w-3.5 h-3.5 text-muted-foreground" />
                                </Link>
                            </div>
                        </div>

                        <!-- System Security & Integrity -->
                        <div class="p-3 rounded-lg bg-primary/5 border border-primary/20 space-y-1.5">
                            <div class="flex items-center gap-1.5 text-primary font-bold text-[11px]">
                                <Lock class="w-3.5 h-3.5" />
                                <span>Multi-Tenant Enterprise Isolation</span>
                            </div>
                            <p class="text-[10px] text-muted-foreground leading-relaxed">
                                PostgreSQL Row-Level Security (RLS) and HIPAA/MoH cryptographic audit signatures are enforced across all operations.
                            </p>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
