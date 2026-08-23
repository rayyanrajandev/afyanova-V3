<script setup>
import { ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    User, Shield, Key, Trash2, Building2, ShieldCheck,
    CheckCircle2, Lock, BadgeCheck, Info
} from 'lucide-vue-next';

import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';

import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';

import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    mustVerifyEmail: { type: Boolean },
    status:          { type: String },
    // MFA state passed down from the controller
    enabled:         { type: Boolean, required: true },
    qrCodeUrl:       { type: String,  default: null },
    recoveryCodes:   { type: Array,   default: null },
});

const { preferences, openContext } = useWorkspacePreferences();
const page = usePage();
const user = page.props.auth?.user || {};
const activeTab = ref('profile');

const setTab = (tab) => {
    activeTab.value = tab;
    openContext();
};
</script>

<template>
    <Head title="User Account Settings — Security" />

    <AfyaShell active-module="profile">
        <AfyaWorkspace :show-sidebar="true" :show-context="true">
            <!-- Left sidebar -->
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
                    <AfyaSidebarItem label="Profile Information"     :icon="User"     :active="activeTab === 'profile'"  :collapsed="state === 'collapsed'" @click="setTab('profile')" />
                    <AfyaSidebarItem label="Password & Security"     :icon="Key"      :active="activeTab === 'security'" :collapsed="state === 'collapsed'" @click="setTab('security')" />
                    <AfyaSidebarItem label="Two-Factor Auth (TOTP)"  :icon="Shield"   :active="activeTab === 'mfa'"      :collapsed="state === 'collapsed'" @click="setTab('mfa')" />
                    <AfyaSidebarItem label="Clinical Role & Facility" :icon="Building2" :active="activeTab === 'role'"   :collapsed="state === 'collapsed'" @click="setTab('role')" />

                    <div v-if="state !== 'collapsed'" class="pt-3 px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-t border-border/60 mt-2">
                        Danger Area
                    </div>
                    <AfyaSidebarItem label="Deactivate Account" :icon="Trash2" :active="activeTab === 'danger'" :collapsed="state === 'collapsed'" @click="setTab('danger')" />
                </AfyaSidebar>
            </template>

            <!-- Center main -->
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
                        <!-- Identity banner -->
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
                                    </div>
                                </div>
                            </div>
                            <!-- MFA status badge -->
                            <div class="hidden sm:flex items-center gap-2 flex-shrink-0">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-semibold border"
                                    :class="enabled
                                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                                        : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200 dark:border-amber-800'"
                                >
                                    <ShieldCheck v-if="enabled" class="w-3.5 h-3.5" />
                                    <Shield v-else class="w-3.5 h-3.5" />
                                    {{ enabled ? 'MFA Active' : 'MFA Not Enabled' }}
                                </span>
                            </div>
                        </div>

                        <!-- Tab bar -->
                        <div class="border-b border-border/60">
                            <nav class="-mb-px flex space-x-5 overflow-x-auto">
                                <button
                                    type="button"
                                    @click="setTab('profile')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'profile' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                                >
                                    <User class="w-3.5 h-3.5" />
                                    Profile
                                </button>
                                <button
                                    type="button"
                                    @click="setTab('security')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'security' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                                >
                                    <Key class="w-3.5 h-3.5" />
                                    Password
                                </button>
                                <button
                                    type="button"
                                    @click="setTab('mfa')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'mfa' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                                >
                                    <Shield class="w-3.5 h-3.5" />
                                    Two-Factor Auth
                                    <span
                                        v-if="enabled"
                                        class="ml-0.5 w-1.5 h-1.5 rounded-full bg-emerald-500"
                                    />
                                </button>
                                <button
                                    type="button"
                                    @click="setTab('role')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'role' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                                >
                                    <Building2 class="w-3.5 h-3.5" />
                                    Role & Scope
                                </button>
                                <button
                                    type="button"
                                    @click="setTab('danger')"
                                    class="py-2.5 px-1 border-b-2 text-xs font-bold transition-all flex items-center gap-1.5 whitespace-nowrap"
                                    :class="activeTab === 'danger' ? 'border-destructive text-destructive' : 'border-transparent text-muted-foreground hover:text-destructive hover:border-destructive/30'"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    Deactivate
                                </button>
                            </nav>
                        </div>

                        <!-- Tab panels -->
                        <div class="w-full">
                            <div v-if="activeTab === 'profile'" class="p-5 bg-card rounded-lg shadow-2xs">
                                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
                            </div>

                            <div v-else-if="activeTab === 'security'" class="p-5 bg-card rounded-lg shadow-2xs">
                                <UpdatePasswordForm />
                            </div>

                            <div v-else-if="activeTab === 'mfa'" class="p-5 bg-card rounded-lg shadow-2xs">
                                <TwoFactorAuthenticationForm
                                    :enabled="enabled"
                                    :qr-code-url="qrCodeUrl"
                                    :recovery-codes="recoveryCodes"
                                />
                            </div>

                            <div v-else-if="activeTab === 'role'" class="p-5 bg-card rounded-lg shadow-2xs space-y-4">
                                <div class="border-b border-border/60 pb-3">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                                        <Building2 class="w-3.5 h-3.5 text-primary" />
                                        Hospital Facility & Security Scoping
                                    </h3>
                                    <p class="text-[11px] text-muted-foreground mt-0.5">
                                        Cryptographic tenant boundary and clinical permission parameters
                                    </p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                    <div class="p-3.5 bg-muted/20 rounded-lg space-y-1">
                                        <span class="text-[10px] uppercase font-bold text-muted-foreground">Assigned Clinical Role</span>
                                        <div class="font-bold text-foreground">{{ user.role?.name || 'Staff Practitioner' }}</div>
                                    </div>
                                    <div class="p-3.5 bg-muted/20 rounded-lg space-y-1">
                                        <span class="text-[10px] uppercase font-bold text-muted-foreground">Facility Unit</span>
                                        <div class="font-bold text-foreground">AfyaNova Central Hospital</div>
                                    </div>
                                    <div class="p-3.5 bg-muted/20 rounded-lg space-y-1">
                                        <span class="text-[10px] uppercase font-bold text-muted-foreground">Tenant Isolation</span>
                                        <div class="font-mono text-primary font-bold truncate">PostgreSQL RLS Active</div>
                                    </div>
                                </div>
                                <div class="p-3 bg-muted/10 rounded-lg border border-border/40 text-[11px] flex gap-2">
                                    <Info class="w-3.5 h-3.5 text-primary flex-shrink-0 mt-0.5" />
                                    <p class="text-muted-foreground leading-relaxed">
                                        Role assignments and facility scoping are managed by your tenant administrator in the Access Control workspace.
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="activeTab === 'danger'" class="p-5 bg-card rounded-lg border border-destructive/30 shadow-2xs">
                                <DeleteUserForm />
                            </div>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- Right context panel -->
            <template #context="{ width, close }">
                <AfyaContextPanel title="Account Security" :icon="Shield" :width="width" @close="close">
                    <div class="space-y-4 text-xs">
                        <div class="p-3.5 bg-card rounded-lg space-y-2.5 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Security Posture</div>
                            <div class="space-y-2 text-[11px]">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Email:</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                        <CheckCircle2 class="w-3.5 h-3.5" /> Verified
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">TOTP MFA:</span>
                                    <span
                                        class="font-bold flex items-center gap-1"
                                        :class="enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                                    >
                                        <ShieldCheck v-if="enabled" class="w-3.5 h-3.5" />
                                        <Shield v-else class="w-3.5 h-3.5" />
                                        {{ enabled ? 'Active' : 'Not Enabled' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">RLS Policy:</span>
                                    <span class="font-mono text-primary font-semibold">Strict</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-3.5 bg-card rounded-lg space-y-1.5 shadow-2xs">
                            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Quick Navigation</div>
                            <div class="space-y-1">
                                <button v-for="tab in [
                                    { id: 'profile', label: 'Profile Information', icon: User },
                                    { id: 'security', label: 'Password', icon: Key },
                                    { id: 'mfa', label: 'Two-Factor Auth', icon: Shield },
                                    { id: 'role', label: 'Role & Scope', icon: Building2 },
                                ]" :key="tab.id" type="button" @click="activeTab = tab.id"
                                    class="w-full text-left p-2 rounded text-xs transition-colors flex items-center justify-between"
                                    :class="activeTab === tab.id ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'"
                                >
                                    <span>{{ tab.label }}</span>
                                    <component :is="tab.icon" class="w-3.5 h-3.5"
                                        :class="activeTab === tab.id ? 'text-primary' : 'text-muted-foreground'" />
                                </button>
                            </div>
                        </div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
