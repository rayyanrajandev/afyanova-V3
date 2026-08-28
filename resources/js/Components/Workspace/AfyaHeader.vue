<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import AfyaCommandPalette from '@/Components/Workspace/AfyaCommandPalette.vue';
import AfyaThemeToggle from '@/Components/Afya/AfyaThemeToggle.vue';
import { 
    PanelLeft, 
    PanelRight,
    LayoutDashboard, 
    Stethoscope, 
    Receipt, 
    Pill, 
    Users, 
    Clock, 
    Search, 
    Bell,
    ChevronDown,
    Bed,
    FlaskConical,
    ShieldCheck,
    Scissors,
    TrendingUp,
    Package,
    Shield,
    ScanLine,
    User,
    KeyRound,
    LogOut,
    Globe
} from '@lucide/vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    activeModule: {
        type: String,
        default: 'dashboard',
    },
});

const { preferences, cycleSidebarState, setSidebarState, restoreSidebar, toggleContext } = useWorkspacePreferences();

const isCommandPaletteOpen = ref(false);

const handleHeaderKeydown = (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        isCommandPaletteOpen.value = true;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleHeaderKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleHeaderKeydown);
});

const page = usePage();
const user = page.props.auth?.user || { first_name: 'Staff', last_name: 'User', email: 'user@afyanova.local' };

const isSuperAdmin = computed(() => !!page.props.auth?.is_superadmin);
const isTenantAdmin = computed(() => !!page.props.auth?.is_tenant_admin);
const userPermissions = computed(() => page.props.auth?.permissions || []);

const hospitalModules = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard, route: 'dashboard', permAny: ['scheduling.queue.view', 'scheduling.appointment.view', 'patient.registry.view', 'clinical.encounter.view', 'billing.invoice.view', 'pharmacy.prescription.view', 'lab.order.view', 'identity.user.manage', 'reports.analytics.view'] },
    { id: 'reports', label: 'Hospital Analytics & BI', icon: TrendingUp, route: 'reports.workspace', permAny: ['reports.clinical.view', 'reports.financial.view', 'reports.pharmacoeconomic.view', 'reports.analytics.view'] },
    { id: 'clinical', label: 'Clinical Workspace', icon: Stethoscope, route: 'workspace.clinical', permAny: ['clinical.encounter.view'] },
    { id: 'inpatient', label: 'Inpatient Wards', icon: Bed, route: 'inpatient.workspace', permAny: ['inpatient.ward.view'] },
    { id: 'procedures', label: 'Procedures & Surgery', icon: Scissors, route: 'procedures.workspace', permAny: ['procedure.order.view'] },
    { id: 'laboratory', label: 'Laboratory', icon: FlaskConical, route: 'laboratory.workspace', permAny: ['lab.order.view'] },
    { id: 'radiology', label: 'Radiology & Imaging', icon: ScanLine, route: 'radiology.workspace', permAny: ['radiology.order.view'] },
    { id: 'insurance', label: 'Insurance & Claims', icon: ShieldCheck, route: 'insurance.workspace', permAny: ['insurance.claim.view'] },
    { id: 'billing', label: 'Billing & POS', icon: Receipt, route: 'billing.desk', permAny: ['billing.invoice.view'] },
    { id: 'pharmacy', label: 'Pharmacy', icon: Pill, route: 'pharmacy.queue', permAny: ['pharmacy.prescription.view', 'pharmacy.inventory.view'] },
    { id: 'inventory', label: 'Inventory & Warehousing', icon: Package, route: 'inventory.workspace', permAny: ['inventory.stock.view', 'inventory.catalog.view', 'inventory.requisition.view', 'inventory.transfer.view', 'inventory.po.view', 'inventory.predictive.view', 'inventory.grn.view', 'inventory.dda.view', 'inventory.gas.view', 'inventory.stocktake.view'] },
    { id: 'access-control', label: 'Access Control & RBAC', icon: Shield, route: 'access-control.workspace', permAny: ['identity.user.manage', 'identity.role.manage', 'identity.roles.assign', 'identity.permissions.manage'] },
    { id: 'audit', label: 'Forensic Audit & Integrity', icon: ShieldCheck, route: 'audit.workspace', permAny: ['audit.log.view', 'identity.user.view', 'identity.user.manage'] },
    { id: 'patients', label: 'Patient Registry', icon: Users, route: 'patients.index', permAny: ['patient.registry.view'] },
    { id: 'scheduling', label: 'Live Queue & Triage', icon: Clock, route: 'queue.index', permAny: ['scheduling.appointment.view', 'scheduling.queue.view'] },
];

const visibleModules = computed(() => {
    const list = hospitalModules.filter((mod) => {
        if (isTenantAdmin.value) return true;
        if (mod.permAny.length === 0) return true;
        return mod.permAny.some((slug) => userPermissions.value.includes(slug));
    });

    // Only Platform Superadmins can see the Superadmin Platform Control plane
    if (isSuperAdmin.value) {
        list.push({
            id: 'superadmin',
            label: 'Superadmin Platform Control',
            icon: Globe,
            route: 'superadmin.workspace',
            permAny: ['platform.superadmin.access'],
        });
    }

    return list;
});

const activeTenantName = computed(() => {
    return page.props.auth?.tenant?.name || page.props.auth?.user?.tenant?.name || 'AfyaNova Health Network';
});

const activeFacilityName = computed(() => {
    return page.props.auth?.facility?.name || '';
});

const currentModuleObj = () => {
    return visibleModules.value.find(m => m.id === props.activeModule) || hospitalModules.find(m => m.id === props.activeModule) || visibleModules.value[0] || hospitalModules[0];
};

const switchModule = (mod) => {
    if (mod.route) {
        router.get(route(mod.route));
    }
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <header class="h-12 bg-card border-b border-border/60 px-3 flex items-center justify-between select-none z-30 flex-shrink-0">
        <!-- Left: Logo & Workspace Module Switcher -->
        <div class="flex items-center space-x-2.5">
            <!-- Sidebar Toggle Button (Shows when hidden or toggleable) -->
            <button
                v-if="preferences.sidebarState === 'hidden'"
                @click="restoreSidebar"
                class="h-7 w-7 flex items-center justify-center text-muted-foreground hover:text-foreground rounded hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                title="Show Sidebar (Ctrl+B)"
                aria-label="Show Sidebar"
            >
                <PanelLeft class="w-4 h-4" />
            </button>

            <!-- Brand Logo -->
            <Link :href="route('dashboard')" class="flex items-center space-x-2 group">
                <div class="w-6 h-6 rounded-md bg-primary flex items-center justify-center text-primary-foreground font-bold text-[11px] shadow-2xs group-hover:bg-primary/90 transition">
                    AN
                </div>
                <div class="hidden sm:block">
                    <span class="font-bold text-foreground text-xs tracking-tight">AfyaNova</span>
                    <span class="text-primary font-semibold text-[10px] ml-1">v3.0</span>
                </div>
            </Link>

            <div class="h-3.5 w-px bg-border/60 hidden md:block"></div>

            <!-- Facility / Tenant Indicator (Clean Tonal Pill) -->
            <div class="hidden lg:flex items-center space-x-1.5 text-xs text-muted-foreground bg-muted/40 px-2.5 py-0.5 rounded-md h-7">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" title="Connected to Primary DB"></span>
                <span class="font-medium text-foreground text-[11px]">{{ activeTenantName }}</span>
                <span v-if="activeFacilityName" class="text-muted-foreground text-[10px]">· {{ activeFacilityName }}</span>
            </div>

            <!-- Workspace Module Switcher Dropdown (Compact 28px height, Clean Semantic Surface) -->
            <Dropdown align="left" width="60" content-classes="p-1 bg-card text-card-foreground border border-border/60">
                <template #trigger>
                    <button
                        type="button"
                        class="inline-flex items-center h-7 px-2.5 border border-border/60 text-xs font-semibold rounded-md text-foreground bg-card hover:bg-muted/70 focus:outline-none focus:ring-1 focus:ring-ring transition shadow-2xs gap-1.5"
                    >
                        <component :is="currentModuleObj().icon" class="w-3.5 h-3.5 text-primary" aria-hidden="true" />
                        <span class="text-[11.5px] font-medium">{{ currentModuleObj().label }}</span>
                        <ChevronDown class="w-3 h-3 text-muted-foreground ml-0.5" />
                    </button>
                </template>

                <template #content>
                    <div class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-b border-border/60">
                        Hospital Workspaces
                    </div>
                    <div class="py-1 space-y-0.5">
                        <button
                            v-for="mod in visibleModules"
                            :key="mod.id"
                            type="button"
                            @click="switchModule(mod)"
                            class="w-full text-left px-2 py-1.5 text-xs flex items-center space-x-2 rounded hover:bg-muted transition"
                            :class="activeModule === mod.id ? 'bg-primary/10 text-primary font-bold' : 'text-foreground'"
                        >
                            <component :is="mod.icon" class="w-3.5 h-3.5" :class="activeModule === mod.id ? 'text-primary' : 'text-muted-foreground'" />
                            <span class="flex-1 text-[11.5px]">{{ mod.label }}</span>
                            <span v-if="activeModule === mod.id" class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                        </button>
                    </div>
                </template>
            </Dropdown>
        </div>

        <!-- Center: Command Search Bar Trigger (Sleek Tonal Search Bar) -->
        <div class="hidden md:flex items-center max-w-xs w-full mx-3">
            <button
                type="button"
                @click="isCommandPaletteOpen = true"
                class="w-full text-left bg-muted/40 hover:bg-muted/70 text-muted-foreground hover:text-foreground px-2.5 h-7 rounded-md text-xs flex items-center justify-between border border-border/50 transition"
            >
                <div class="flex items-center space-x-1.5 truncate">
                    <Search class="w-3 h-3 text-muted-foreground flex-shrink-0" />
                    <span class="text-[11px] truncate">Search MRN, bill, order...</span>
                </div>
                <kbd class="hidden sm:inline-block px-1.5 py-0.2 text-[9px] font-mono bg-card text-muted-foreground rounded border border-border/60 flex-shrink-0 shadow-2xs">
                    ⌘K
                </kbd>
            </button>
        </div>

        <!-- Right: Theme Switcher, Notifications, User Menu -->
        <div class="flex items-center space-x-1.5">
            <!-- Theme Mode Toggle (Light / Dark / System) -->
            <AfyaThemeToggle />

            <!-- Context Panel Global Toggle (Ctrl+I) -->
            <button
                type="button"
                @click="toggleContext"
                class="h-7 w-7 flex items-center justify-center text-muted-foreground hover:text-foreground rounded hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                :class="preferences.contextOpen ? 'text-primary bg-primary/10' : ''"
                :title="preferences.contextOpen ? 'Hide Inspector Panel (Ctrl+I)' : 'Show Inspector Panel (Ctrl+I)'"
                aria-label="Toggle Inspector Panel"
            >
                <PanelRight class="w-3.5 h-3.5" />
            </button>

            <!-- Notifications Bell -->
            <button
                type="button"
                class="h-7 w-7 flex items-center justify-center text-muted-foreground hover:text-foreground rounded hover:bg-muted relative focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                title="Notifications"
                aria-label="Notifications"
            >
                <Bell class="w-3.5 h-3.5" />
                <span class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full bg-primary"></span>
            </button>

            <!-- User Menu Dropdown -->
            <Dropdown align="right" width="56" content-classes="p-1 bg-card text-card-foreground border border-border shadow-lg rounded-lg">
                <template #trigger>
                    <button class="flex items-center space-x-1.5 text-xs font-medium text-foreground hover:bg-muted p-1 rounded-md transition focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring h-7">
                        <div class="w-5 h-5 rounded bg-primary/10 text-primary font-bold flex items-center justify-center text-[10px] border border-primary/20">
                            {{ user.first_name ? user.first_name[0] : 'U' }}
                        </div>
                        <div class="hidden xl:block text-left pr-1">
                            <span class="font-semibold text-foreground text-[11px] leading-none">{{ user.first_name }} {{ user.last_name }}</span>
                        </div>
                        <ChevronDown class="w-3 h-3 text-muted-foreground hidden xl:block" />
                    </button>
                </template>

                <template #content>
                    <div class="px-2.5 py-2 text-[11px] border-b border-border/60">
                        <div class="font-semibold text-foreground truncate">{{ user.first_name }} {{ user.last_name }}</div>
                        <div class="text-[10px] text-muted-foreground truncate">{{ user.email }}</div>
                    </div>
                    <div class="py-1 space-y-0.5">
                        <DropdownLink :href="route('profile.edit')" class="text-xs px-2.5 py-1.5 hover:bg-muted text-foreground flex items-center gap-2 rounded transition-colors">
                            <User class="w-3.5 h-3.5 text-muted-foreground" />
                            <span>User Settings</span>
                        </DropdownLink>
                        <DropdownLink :href="route('profile.edit')" class="text-xs px-2.5 py-1.5 hover:bg-muted text-foreground flex items-center justify-between rounded transition-colors">
                            <div class="flex items-center gap-2">
                                <KeyRound class="w-3.5 h-3.5 text-muted-foreground" />
                                <span>Two-Factor Auth</span>
                            </div>
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" title="2FA Active"></span>
                        </DropdownLink>
                        <div class="my-1 border-t border-border/60"></div>
                        <button
                            @click="logout"
                            class="w-full px-2.5 py-1.5 text-left text-xs text-destructive hover:bg-destructive/10 rounded transition-colors flex items-center gap-2 font-medium"
                        >
                            <LogOut class="w-3.5 h-3.5 text-destructive" />
                            <span>Sign Out</span>
                        </button>
                    </div>
                </template>
            </Dropdown>
        </div>

        <!-- Universal AfyaNova Command Palette Modal -->
        <AfyaCommandPalette
            :open="isCommandPaletteOpen"
            @close="isCommandPaletteOpen = false"
        />
    </header>
</template>
