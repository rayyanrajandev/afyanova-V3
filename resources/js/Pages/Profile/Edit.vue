<script setup>
import { ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { 
    User, 
    Shield, 
    Key, 
    Trash2, 
    Building2, 
    ShieldCheck, 
    CheckCircle2, 
    Sparkles, 
    Lock,
    Mail,
    Phone,
    BadgeCheck,
    Calendar,
    FileText,
    Hospital,
    Info
} from 'lucide-vue-next';

import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';

// UI Primitives
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';

// Partials
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import TwoFactorAuthenticationForm from './Partials/TwoFactorAuthenticationForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    // MFA state passed from ProfileController::edit
    mfaEnabled: {
        type: Boolean,
        default: false,
    },
    qrCodeUrl: {
        type: String,
        default: null,
    },
    recoveryCodes: {
        type: Array,
        default: null,
    },
});

import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const { preferences, openContext } = useWorkspacePreferences();

const page = usePage();
const user = page.props.auth?.user || {};
const activeTab = ref('profile'); // 'profile' | 'security' | 'mfa' | 'role' | 'danger'

const setTab = (tab) => {
    activeTab.value = tab;
    openContext();
};
</script>

<template>
    <Head title="User Account Settings" />

    <AfyaShell active-module="profile">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            <!-- 1. LEFT SIDEBAR: Account Navigation -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="User Settings"
                    :icon="User"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Settings Categories
                    </div>
                    <AfyaSidebarItem
                        label="Profile Information"
                        :icon="User"
                        :active="activeTab === 'profile'"
                        :collapsed="state === 'collapsed'"
                        @click="setTab('profile')"
                    />
                    <AfyaSidebarItem
                        label="Password & Security"
                        :icon="Key"
                        :active="activeTab === 'security'"
                        :collapsed="state === 'collapsed'"
                        @click="setTab('security')"
                    />
                    <AfyaSidebarItem
                        label="Two-Factor Auth"
                        :icon="Shield"
                        :active="activeTab === 'mfa'"
                        :collapsed="state === 'collapsed'"
                        @click="setTab('mfa')"
                    />
                    <AfyaSidebarItem
                        label="Clinical Role & Facility"
                        :icon="Building2"
                        :active="activeTab === 'role'"
                        :collapsed="state === 'collapsed'"
                        @click="setTab('role')"
                    />

                    <div v-if="state !== 'collapsed'" class="pt-3 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border/60 mt-2">
                        Danger Area
                    </div>
                    <AfyaSidebarItem
                        label="Deactivate Account"
                        :icon="Trash2"
                        :active="activeTab === 'danger'"
                        :collapsed="state === 'collapsed'"
                        @click="setTab('danger')"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER MAIN: Tabbed Account Settings (Full Center Space Fit) -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'System', href: route('dashboard') },
                        { label: 'User Account Settings', active: true }
                    ]"
                >
                    <template #actions>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shadow-2xs">
                            <ShieldCheck class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                            <span>Session: Active Authenticated</span>
                        </span>
                    </template>

                    <div class="w-full space-y-4">
                        <!-- Top Practitioner Identity Banner (Surface Elevation) -->
                        <div class="p-3.5 rounded-lg bg-card shadow-2xs flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-sm border border-primary/20 flex-shrink-0">
                                    {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-bold text-foreground flex items-center gap-1.5 truncate">
                                        <span>{{ user.name || 'Clinical Practitioner' }}</span>
                                        <BadgeCheck class="w-4 h-4 text-primary flex-shrink-0" />
                                    </h2>
                                    <div class="text-[11px] text-muted-foreground flex items-center gap-2 truncate mt-0.5">
                                        <span class="font-mono">{{ user.email }}</span>
                                        <span class="text-border">·</span>
                                        <span>{{ user.role?.name || 'Medical Practitioner' }}</span>
                                        <span class="text-border">·</span>
                                        <span class="text-primary font-medium">AfyaNova Central Hospital</span>
                                    </div>
                                </div>
                            </div>
                            <div class="hidden sm:flex items-center gap-2 flex-shrink-0">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Active Practitioner
                                </span>
                            </div>
                        </div>

                        <!-- Modern Settings Tab Bar -->
                        <div class="border-b border-border/60">
                            <nav class="-mb-px flex space-x-6 overflow-x-auto">
                                <button
                                    type="button"
                                    @click="setTab('profile')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'profile'
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                                >
                                    <User class="w-3.5 h-3.5" />
                                    <span>Profile Information</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setTab('security')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'security'
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                                >
                                    <Key class="w-3.5 h-3.5" />
                                    <span>Password & Security</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setTab('mfa')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'mfa'
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                                >
                                    <Shield class="w-3.5 h-3.5" />
                                    <span>Two-Factor Auth</span>
                                    <!-- green dot when MFA is live -->
                                    <span v-if="mfaEnabled" class="w-1.5 h-1.5 rounded-full bg-emerald-500 ml-0.5" />
                                </button>
                                <button
                                    type="button"
                                    @click="setTab('role')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'role'
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                                >
                                    <Building2 class="w-3.5 h-3.5" />
                                    <span>Clinical Role & Scope</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setTab('danger')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'danger'
                                        ? 'border-destructive text-destructive'
                                        : 'border-transparent text-muted-foreground hover:text-destructive hover:border-destructive/30'"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Deactivate Account</span>
                                </button>
                            </nav>
                        </div>

                        <!-- Active Tab Panel Container (Fills Center Space Cleanly) -->
                        <div class="w-full">
                            <!-- TAB 1: Profile Information -->
                            <div v-if="activeTab === 'profile'" class="p-5 bg-card rounded-lg shadow-2xs w-full">
                                <UpdateProfileInformationForm
                                    :must-verify-email="mustVerifyEmail"
                                    :status="status"
                                />
                            </div>

                            <!-- TAB 2: Password & Security -->
                            <div v-else-if="activeTab === 'security'" class="p-5 bg-card rounded-lg shadow-2xs w-full">
                                <UpdatePasswordForm />
                            </div>

                            <!-- TAB 3: Two-Factor Authentication -->
                            <div v-else-if="activeTab === 'mfa'" class="p-5 bg-card rounded-lg shadow-2xs w-full">
                                <TwoFactorAuthenticationForm
                                    :enabled="mfaEnabled"
                                    :qr-code-url="qrCodeUrl"
                                    :recovery-codes="recoveryCodes"
                                />
                            </div>

                            <!-- TAB 4: Clinical Role & Facility Scope -->
                            <div v-else-if="activeTab === 'role'" class="p-5 bg-card rounded-lg shadow-2xs w-full space-y-4">
                                <div class="border-b border-border/60 pb-3">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                                        <Building2 class="w-3.5 h-3.5 text-primary" />
                                        <span>Hospital Facility & Security Scoping</span>
                                    </h3>
                                    <p class="text-[11px] text-muted-foreground mt-0.5">
                                        Cryptographic tenant boundary and clinical permission parameters
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                    <div class="p-3.5 bg-muted/20 rounded-lg space-y-1">
                                        <span class="text-[10px] uppercase font-bold text-muted-foreground">Assigned Clinical Role</span>
                                        <div class="font-bold text-foreground">{{ user.role?.name || 'Staff Practitioner' }}</div>
                                        <div class="text-[10px] text-muted-foreground">Full Consultation & Charting</div>
                                    </div>
                                    <div class="p-3.5 bg-muted/20 rounded-lg space-y-1">
                                        <span class="text-[10px] uppercase font-bold text-muted-foreground">Facility Unit</span>
                                        <div class="font-bold text-foreground">AfyaNova Central Hospital</div>
                                        <div class="text-[10px] text-muted-foreground">Main Outpatient Complex</div>
                                    </div>
                                    <div class="p-3.5 bg-muted/20 rounded-lg space-y-1">
                                        <span class="text-[10px] uppercase font-bold text-muted-foreground">Tenant Isolation Boundary</span>
                                        <div class="font-mono text-primary font-bold truncate">AFYA-TENANT-001</div>
                                        <div class="text-[10px] text-muted-foreground">PostgreSQL RLS Active</div>
                                    </div>
                                </div>

                                <div class="p-3 bg-muted/10 rounded-lg border border-border/40 text-xs space-y-1">
                                    <div class="font-bold text-foreground flex items-center gap-1.5 text-[11px]">
                                        <Info class="w-3.5 h-3.5 text-primary" />
                                        <span>Role Permissions & Access Control</span>
                                    </div>
                                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                                        Your clinical permissions allow viewing live triage queues, creating consultation encounters, issuing digital prescriptions, ordering lab diagnostics, and viewing invoice items within the authorized facility scope.
                                    </p>
                                </div>
                            </div>

                            <!-- TAB 4: Deactivate Account / Danger Zone -->
                            <div v-else-if="activeTab === 'danger'" class="p-5 bg-card rounded-lg border border-destructive/30 shadow-2xs w-full">
                                <DeleteUserForm />
                            </div>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT PANEL: Live Account & Security Telemetry -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Account Summary"
                    :icon="Shield"
                    :width="width"
                    @close="close"
                >
                    <div class="space-y-4 text-xs">
                        <div class="p-3.5 bg-card rounded-lg space-y-2.5 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                Security Posture
                            </div>
                            <div class="space-y-2 text-[11px]">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Email Status:</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                        <CheckCircle2 class="w-3.5 h-3.5" />
                                        Verified
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Active Session:</span>
                                    <span class="font-mono text-foreground font-semibold">127.0.0.1</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">RLS Policy:</span>
                                    <span class="font-mono text-primary font-semibold">Strict Multi-Tenant</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Audit Logging:</span>
                                    <span class="text-foreground font-medium">Compliance Active</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-3.5 bg-card rounded-lg space-y-2 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                Navigation Tabs
                            </div>
                            <div class="space-y-1">
                                <button
                                    type="button"
                                    @click="activeTab = 'profile'"
                                    class="w-full text-left p-2 rounded text-xs transition-colors flex items-center justify-between"
                                    :class="activeTab === 'profile' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'"
                                >
                                    <span>Profile Information</span>
                                    <User class="w-3.5 h-3.5" :class="activeTab === 'profile' ? 'text-primary' : 'text-muted-foreground'" />
                                </button>
                                <button
                                    type="button"
                                    @click="activeTab = 'security'"
                                    class="w-full text-left p-2 rounded text-xs transition-colors flex items-center justify-between"
                                    :class="activeTab === 'security' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'"
                                >
                                    <span>Password & Security</span>
                                    <Key class="w-3.5 h-3.5" :class="activeTab === 'security' ? 'text-primary' : 'text-muted-foreground'" />
                                </button>
                                <button
                                    type="button"
                                    @click="activeTab = 'role'"
                                    class="w-full text-left p-2 rounded text-xs transition-colors flex items-center justify-between"
                                    :class="activeTab === 'role' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'"
                                >
                                    <span>Role & Scope</span>
                                    <Building2 class="w-3.5 h-3.5" :class="activeTab === 'role' ? 'text-primary' : 'text-muted-foreground'" />
                                </button>
                                <button
                                    type="button"
                                    @click="activeTab = 'danger'"
                                    class="w-full text-left p-2 rounded text-xs transition-colors flex items-center justify-between"
                                    :class="activeTab === 'danger' ? 'bg-destructive/10 text-destructive font-bold' : 'hover:bg-muted text-foreground'"
                                >
                                    <span>Deactivate Account</span>
                                    <Trash2 class="w-3.5 h-3.5" :class="activeTab === 'danger' ? 'text-destructive' : 'text-muted-foreground'" />
                                </button>
                            </div>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
