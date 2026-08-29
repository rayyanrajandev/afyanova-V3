<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ShieldCheck,
    History,
    Search,
    Filter,
    FileCode,
    Lock,
    User,
    Clock,
    Globe,
    CheckCircle2,
    AlertTriangle,
    RefreshCw,
    Activity,
    Layers,
    Database,
    ArrowRight,
    ChevronDown,
    X
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
    logs: { type: Object, default: () => ({ data: [], links: [] }) },
    categories: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    totalLogsCount: { type: Number, default: 0 },
});

const { contextState, openContext } = useWorkspacePreferences('audit');

const activeCategory = ref(props.filters.category || 'all');
const selectedUserId = ref(props.filters.user_id || '');
const searchInput = ref(props.filters.search || '');
const actionInput = ref(props.filters.action || '');
const isVerifying = ref(false);

const hasActiveFilters = computed(() => {
    return activeCategory.value !== 'all' || !!selectedUserId.value || !!searchInput.value || !!actionInput.value;
});

const selectedUserName = computed(() => {
    if (!selectedUserId.value) return '';
    const u = props.users.find(user => user.id === selectedUserId.value);
    return u ? `${u.first_name} ${u.last_name}` : selectedUserId.value;
});

const selectedLog = ref(props.logs.data?.[0] || null);

const inspectLog = (log) => {
    selectedLog.value = log;
    openContext();
};

const applyFilter = () => {
    router.get(route('audit.workspace'), {
        category: activeCategory.value !== 'all' ? activeCategory.value : undefined,
        user_id: selectedUserId.value || undefined,
        search: searchInput.value || undefined,
        action: actionInput.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilter = () => {
    activeCategory.value = 'all';
    selectedUserId.value = '';
    searchInput.value = '';
    actionInput.value = '';
    router.get(route('audit.workspace'));
};

const verifyIntegrity = () => {
    isVerifying.value = true;
    router.post(route('audit.verify-integrity'), {}, {
        onFinish: () => {
            isVerifying.value = false;
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const getCategoryColor = (cat) => {
    switch (cat?.toUpperCase()) {
        case 'CLINICAL': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'FINANCIAL':
        case 'BILLING': return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
        case 'AUTH':
        case 'IDENTITY': return 'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300';
        case 'PHARMACY':
        case 'INVENTORY': return 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300';
        case 'PROCEDURE': return 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300';
        default: return 'bg-muted text-muted-foreground';
    }
};
</script>

<template>
    <Head title="Cryptographic Forensic Audit Trail — AfyaNova Workstation" />

    <AfyaShell active-module="audit">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            
            <!-- 1. LEFT SIDEBAR: Audit Domain Filters -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Audit & Compliance"
                    :icon="ShieldCheck"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Domain Category
                    </div>

                    <AfyaSidebarItem
                        label="All Audit Events"
                        :icon="History"
                        :badge="totalLogsCount"
                        :active="activeCategory === 'all'"
                        :collapsed="state === 'collapsed'"
                        @click="activeCategory = 'all'; applyFilter()"
                    />

                    <div v-if="state !== 'collapsed'" class="px-2 space-y-0.5 pt-1">
                        <button
                            v-for="cat in categories"
                            :key="cat"
                            class="w-full text-left px-2 py-1 rounded text-[11px] flex items-center justify-between hover:bg-muted/50 transition capitalize"
                            :class="activeCategory === cat ? 'bg-primary/10 text-primary font-bold' : 'text-muted-foreground'"
                            @click="activeCategory = cat; applyFilter()"
                        >
                            <span>{{ cat.toLowerCase() }}</span>
                            <span class="w-1.5 h-1.5 rounded-full" :class="activeCategory === cat ? 'bg-primary' : 'bg-transparent'"></span>
                        </button>
                    </div>

                    <div v-if="state !== 'collapsed'" class="pt-3 px-2 border-t border-border/40 mt-2 space-y-2">
                        <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                            Filter by Staff
                        </div>
                        <div class="relative w-full">
                            <select
                                v-model="selectedUserId"
                                class="w-full h-8 text-xs rounded-md border border-input bg-card pl-2.5 pr-8 text-foreground truncate cursor-pointer focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs appearance-none"
                                @change="applyFilter"
                            >
                                <option value="">All Staff Members</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">
                                    {{ u.first_name }} {{ u.last_name }}
                                </option>
                            </select>
                            <ChevronDown class="w-3.5 h-3.5 absolute right-2.5 top-2.5 pointer-events-none text-muted-foreground opacity-70" />
                        </div>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN WORK AREA -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Compliance & Governance', href: route('audit.workspace') },
                        { label: 'Immutable Audit Trail Ledger', active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">
                                <Lock class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span>SHA-256 Merkle Chain Active</span>
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7.5 px-3 text-xs font-bold gap-1.5 text-primary border-primary/30 hover:bg-primary/5 shadow-2xs"
                                :disabled="isVerifying"
                                @click="verifyIntegrity"
                            >
                                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isVerifying }" />
                                <span>Verify Chain Integrity</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-3">
                        
                        <!-- Unified Search & Filter Toolbar -->
                        <div class="bg-card rounded-xl border border-border/60 shadow-2xs p-3 space-y-3">
                            <!-- Row 1: Search & Action Inputs + Staff Select + Buttons -->
                            <div class="flex flex-wrap items-center gap-2.5">
                                <!-- Main Search Input -->
                                <div class="relative flex-1 min-w-[240px]">
                                    <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        v-model="searchInput"
                                        placeholder="Search entity ID, model type, IP address, justification..."
                                        class="h-9 pl-9 pr-8 text-xs font-mono bg-background border-border/80"
                                        @keyup.enter="applyFilter"
                                    />
                                    <button
                                        v-if="searchInput"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        @click="searchInput = ''; applyFilter()"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <!-- Action Input -->
                                <div class="w-48 min-w-[150px]">
                                    <Input
                                        v-model="actionInput"
                                        placeholder="Action (e.g. RECORD_SAVED)..."
                                        class="h-9 text-xs font-mono uppercase bg-background border-border/80"
                                        @keyup.enter="applyFilter"
                                    />
                                </div>

                                <!-- Staff Dropdown -->
                                <div class="w-56 min-w-[180px] relative">
                                    <select
                                        v-model="selectedUserId"
                                        class="w-full h-9 text-xs rounded-md border border-border/80 bg-background pl-3 pr-8 text-foreground truncate cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-2xs appearance-none font-medium"
                                        @change="applyFilter"
                                    >
                                        <option value="">All Staff Members</option>
                                        <option v-for="u in users" :key="u.id" :value="u.id">
                                            {{ u.first_name }} {{ u.last_name }}
                                        </option>
                                    </select>
                                    <ChevronDown class="w-4 h-4 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-muted-foreground opacity-70" />
                                </div>

                                <!-- Filter & Reset Buttons -->
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="default"
                                        size="sm"
                                        class="h-9 px-3.5 text-xs font-bold gap-1.5 shadow-2xs bg-primary text-primary-foreground hover:bg-primary/90"
                                        @click="applyFilter"
                                    >
                                        <Filter class="w-3.5 h-3.5" />
                                        <span>Filter</span>
                                    </Button>

                                    <Button
                                        v-if="hasActiveFilters"
                                        variant="outline"
                                        size="sm"
                                        class="h-9 px-3 text-xs font-medium text-muted-foreground hover:text-foreground border-border/70"
                                        @click="resetFilter"
                                    >
                                        <span>Reset</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Row 2: Category Chips & Active Filter Badges -->
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-border/40 text-xs">
                                <div class="flex items-center gap-1.5 overflow-x-auto py-0.5 max-w-full">
                                    <span class="text-[10.5px] font-bold text-muted-foreground uppercase tracking-wider mr-1 flex items-center gap-1 flex-shrink-0">
                                        <Layers class="w-3 h-3 text-primary" />
                                        Domain:
                                    </span>
                                    <button
                                        type="button"
                                        class="px-2.5 py-1 rounded-md text-[11px] font-bold transition-all whitespace-nowrap flex-shrink-0"
                                        :class="activeCategory === 'all' 
                                            ? 'bg-primary text-primary-foreground shadow-2xs' 
                                            : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground border border-border/40'"
                                        @click="activeCategory = 'all'; applyFilter()"
                                    >
                                        All ({{ totalLogsCount }})
                                    </button>
                                    <button
                                        v-for="cat in categories"
                                        :key="cat"
                                        type="button"
                                        class="px-2.5 py-1 rounded-md text-[11px] font-medium transition-all whitespace-nowrap flex-shrink-0"
                                        :class="activeCategory === cat 
                                            ? 'bg-primary text-primary-foreground font-bold shadow-2xs' 
                                            : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground border border-border/40'"
                                        @click="activeCategory = cat; applyFilter()"
                                    >
                                        {{ cat }}
                                    </button>
                                </div>

                                <!-- Active Filter Indicator Pills -->
                                <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span class="font-bold text-foreground">Active:</span>
                                    <span v-if="activeCategory !== 'all'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-primary/10 text-primary font-medium">
                                        {{ activeCategory }}
                                        <X class="w-3 h-3 cursor-pointer hover:opacity-80" @click="activeCategory = 'all'; applyFilter()" />
                                    </span>
                                    <span v-if="selectedUserId" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-primary/10 text-primary font-medium">
                                        Staff: {{ selectedUserName }}
                                        <X class="w-3 h-3 cursor-pointer hover:opacity-80" @click="selectedUserId = ''; applyFilter()" />
                                    </span>
                                    <span v-if="actionInput" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-primary/10 text-primary font-medium">
                                        Action: {{ actionInput }}
                                        <X class="w-3 h-3 cursor-pointer hover:opacity-80" @click="actionInput = ''; applyFilter()" />
                                    </span>
                                    <span v-if="searchInput" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-primary/10 text-primary font-medium">
                                        Query: "{{ searchInput }}"
                                        <X class="w-3 h-3 cursor-pointer hover:opacity-80" @click="searchInput = ''; applyFilter()" />
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Master Audit Log Table -->
                        <div class="w-full bg-card rounded-lg overflow-hidden shadow-2xs border border-border/60">
                            <Table class="w-full text-xs">
                                <TableHeader>
                                    <TableRow class="h-7 text-[9.5px] uppercase font-bold text-muted-foreground bg-muted/10 border-b border-border/40">
                                        <TableHead class="py-1 px-3">Timestamp (EAT)</TableHead>
                                        <TableHead class="py-1 px-3">Category</TableHead>
                                        <TableHead class="py-1 px-3">Action</TableHead>
                                        <TableHead class="py-1 px-3">Staff / Actor</TableHead>
                                        <TableHead class="py-1 px-3">Entity</TableHead>
                                        <TableHead class="py-1 px-3">IP Address</TableHead>
                                        <TableHead class="py-1 px-3">Cryptographic Hash</TableHead>
                                        <TableHead class="py-1 px-3 text-right">Inspect</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="log in logs.data"
                                        :key="log.id"
                                        class="h-8.5 border-b border-border/30 hover:bg-muted/20 cursor-pointer"
                                        :class="selectedLog?.id === log.id ? 'bg-primary/5' : ''"
                                        @click="inspectLog(log)"
                                    >
                                        <TableCell class="py-1 px-3 font-mono text-[10.5px] text-muted-foreground whitespace-nowrap">
                                            {{ formatDate(log.created_at) }}
                                        </TableCell>
                                        <TableCell class="py-1 px-3">
                                            <span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold" :class="getCategoryColor(log.event_category)">
                                                {{ log.event_category }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="py-1 px-3 font-mono font-bold text-foreground text-[11px]">
                                            {{ log.action }}
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-foreground text-xs">
                                            <div v-if="log.user" class="font-medium">
                                                {{ log.user.first_name }} {{ log.user.last_name }}
                                            </div>
                                            <div v-else class="text-muted-foreground italic text-[10.5px]">System Automation</div>
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-xs">
                                            <div class="font-mono text-[10px] text-primary truncate max-w-[140px]">
                                                {{ (log.entity_type || '').split('\\').pop() }}
                                            </div>
                                        </TableCell>
                                        <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">
                                            {{ log.ip_address || '127.0.0.1' }}
                                        </TableCell>
                                        <TableCell class="py-1 px-3 font-mono text-[9.5px] text-muted-foreground truncate max-w-[120px]">
                                            {{ log.hash_signature ? log.hash_signature.substring(0, 16) + '...' : '—' }}
                                        </TableCell>
                                        <TableCell class="py-1 px-3 text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-5 px-2 text-[10px] font-semibold text-primary border-primary/30 hover:bg-primary/5"
                                                @click.stop="inspectLog(log)"
                                            >
                                                Diff
                                            </Button>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-if="logs.data.length === 0">
                                        <TableCell colspan="8" class="text-center py-8 text-muted-foreground text-xs">
                                            No audit log records match the search filter.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: AUDIT 360 INSPECTOR -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Forensic Record 360"
                    :icon="ShieldCheck"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedLog" class="p-3 space-y-3 text-xs">
                        
                        <!-- Event Overview Card -->
                        <div class="p-3 bg-muted/30 rounded-xl border border-border/60 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="getCategoryColor(selectedLog.event_category)">
                                    {{ selectedLog.event_category }}
                                </span>
                                <span class="text-[10px] font-mono text-muted-foreground">
                                    {{ formatDate(selectedLog.created_at) }}
                                </span>
                            </div>

                            <div class="font-bold text-sm text-foreground font-mono">
                                {{ selectedLog.action }}
                            </div>

                            <div class="text-[11px] text-muted-foreground">
                                Entity: <strong class="text-foreground font-mono">{{ selectedLog.entity_type }}</strong>
                                <span v-if="selectedLog.entity_id" class="block font-mono text-[10px] truncate text-primary mt-0.5">
                                    ID: {{ selectedLog.entity_id }}
                                </span>
                            </div>
                        </div>

                        <!-- Actor & Session Details -->
                        <div class="p-3 bg-card rounded-xl border border-border/60 space-y-2">
                            <div class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">
                                Session Telemetry & Actor
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[11px]">
                                <div>
                                    <span class="text-muted-foreground block text-[9.5px]">User:</span>
                                    <strong class="text-foreground">
                                        {{ selectedLog.user ? `${selectedLog.user.first_name} ${selectedLog.user.last_name}` : 'System Kernel' }}
                                    </strong>
                                </div>
                                <div>
                                    <span class="text-muted-foreground block text-[9.5px]">IP Address:</span>
                                    <span class="font-mono text-foreground">{{ selectedLog.ip_address }}</span>
                                </div>
                            </div>
                            <div v-if="selectedLog.route_name" class="text-[10px] font-mono text-muted-foreground truncate">
                                Route: {{ selectedLog.route_name }}
                            </div>
                            <div v-if="selectedLog.justification_reason" class="p-2 bg-amber-500/10 rounded border border-amber-500/30 text-amber-700 dark:text-amber-300 text-[10.5px]">
                                <strong>Clinical Justification:</strong> {{ selectedLog.justification_reason }}
                            </div>
                        </div>

                        <!-- Cryptographic Proof Card -->
                        <div class="p-3 bg-card rounded-xl border border-border/60 space-y-1.5">
                            <div class="flex items-center gap-1.5 text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400">
                                <Lock class="w-3 h-3" />
                                <span>Cryptographic Proof</span>
                            </div>
                            <div class="space-y-1 text-[9.5px] font-mono">
                                <div>
                                    <span class="text-muted-foreground block">Hash Signature:</span>
                                    <span class="text-foreground break-all bg-muted/40 p-1 rounded block">{{ selectedLog.hash_signature }}</span>
                                </div>
                                <div v-if="selectedLog.previous_hash">
                                    <span class="text-muted-foreground block">Previous Hash (Link):</span>
                                    <span class="text-muted-foreground break-all bg-muted/20 p-1 rounded block">{{ selectedLog.previous_hash }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- State Mutation Diff -->
                        <div class="p-3 bg-card rounded-xl border border-border/60 space-y-2">
                            <div class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider flex items-center justify-between">
                                <span>State Mutation Payload</span>
                                <FileCode class="w-3.5 h-3.5 text-primary" />
                            </div>

                            <div v-if="selectedLog.before_state || selectedLog.after_state" class="space-y-2">
                                <div v-if="selectedLog.before_state">
                                    <div class="text-[10px] font-bold text-rose-600 dark:text-rose-400 mb-1">State Before Mutation:</div>
                                    <pre class="p-2 bg-muted/40 rounded text-[9.5px] font-mono overflow-x-auto max-h-36">{{ JSON.stringify(selectedLog.before_state, null, 2) }}</pre>
                                </div>

                                <div v-if="selectedLog.after_state">
                                    <div class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mb-1">State After Mutation:</div>
                                    <pre class="p-2 bg-muted/40 rounded text-[9.5px] font-mono overflow-x-auto max-h-36">{{ JSON.stringify(selectedLog.after_state, null, 2) }}</pre>
                                </div>
                            </div>
                            <div v-else class="text-center py-3 text-muted-foreground italic text-[11px]">
                                No explicit state change payload recorded for this action.
                            </div>
                        </div>

                    </div>
                </AfyaContextPanel>
            </template>

        </AfyaWorkspace>
    </AfyaShell>
</template>
