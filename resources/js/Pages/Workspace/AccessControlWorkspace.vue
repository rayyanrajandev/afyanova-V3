<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Shield,
    Users,
    Key,
    Building2,
    Lock,
    Unlock,
    CheckCircle2,
    XCircle,
    Plus,
    RefreshCw,
    Search,
    Sliders,
    Layers,
    X,
    UserCheck,
    Stethoscope,
    ShieldAlert
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
import InputError from '@/Components/InputError.vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    can: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    permissionsByDomain: { type: Object, default: () => ({}) },
    facilities: { type: Array, default: () => [] },
    selectedUserId: { type: String, default: null },
    effectivePermissions: { type: Array, default: () => [] },
    metrics: { type: Object, default: () => ({}) },
});

const { preferences, openContext } = useWorkspacePreferences();

// View State: 'users' | 'roles' | 'permissions'
const activeSection = ref(
    props.can.users ? 'users' : (Object.keys(props.can).find(k => props.can[k]) ?? null)
);
const selectedUser = ref(props.users.find(u => u.id === props.selectedUserId) || props.users[0] || null);
const selectedRole = ref(props.roles[0] || null);
const searchQuery = ref('');

// Modals
const showAssignRoleModal = ref(false);
const showEditRolePermissionsModal = ref(false);

// Forms
const assignRoleForm = useForm({
    user_id: '',
    role_id: '',
    facility_id: '',
    department_id: '',
});

const rolePermissionsForm = useForm({
    permission_ids: [],
});

// Live Permission Tester State
const testPermissionSlug = ref('clinical.encounter.create');
const testFacilityId = ref('');
const testResult = ref(null);
const isTesting = ref(false);

const filteredUsers = computed(() => {
    if (!searchQuery.value) return props.users;
    const q = searchQuery.value.toLowerCase();
    return props.users.filter(u => 
        u.first_name?.toLowerCase().includes(q) ||
        u.last_name?.toLowerCase().includes(q) ||
        u.email?.toLowerCase().includes(q) ||
        u.role?.toLowerCase().includes(q)
    );
});

const selectUserForInspector = (user) => {
    selectedUser.value = user;
    router.get(route('access-control.workspace'), { user_id: user.id }, {
        preserveState: true,
        preserveScroll: true,
    });
    openContext();
};

const openAssignRoleModal = (user = null) => {
    assignRoleForm.user_id = user ? user.id : (props.users[0]?.id || '');
    assignRoleForm.role_id = props.roles[0]?.id || '';
    assignRoleForm.facility_id = '';
    showAssignRoleModal.value = true;
};

const submitAssignRole = () => {
    assignRoleForm.post(route('access-control.roles.assign'), {
        onSuccess: () => {
            showAssignRoleModal.value = false;
            assignRoleForm.reset();
        }
    });
};

const openEditRolePermissions = (role) => {
    selectedRole.value = role;
    rolePermissionsForm.permission_ids = (role.permissions || []).map(p => p.id);
    showEditRolePermissionsModal.value = true;
};

const submitRolePermissions = () => {
    if (!selectedRole.value) return;
    rolePermissionsForm.post(route('access-control.roles.permissions', selectedRole.value.id), {
        onSuccess: () => {
            showEditRolePermissionsModal.value = false;
        }
    });
};

const togglePermissionCheckbox = (permId) => {
    const idx = rolePermissionsForm.permission_ids.indexOf(permId);
    if (idx > -1) {
        rolePermissionsForm.permission_ids.splice(idx, 1);
    } else {
        rolePermissionsForm.permission_ids.push(permId);
    }
};

const runLivePermissionTest = async () => {
    if (!selectedUser.value || !testPermissionSlug.value) return;
    isTesting.value = true;
    try {
        const res = await axios.post('/api/access-control/test', {
            user_id: selectedUser.value.id,
            permission_slug: testPermissionSlug.value,
            facility_id: testFacilityId.value || null,
        });
        testResult.value = res.data;
    } catch (e) {
        // Fallback local check
        const has = props.effectivePermissions.includes(testPermissionSlug.value);
        testResult.value = {
            user: `${selectedUser.value.first_name} ${selectedUser.value.last_name}`,
            permission: testPermissionSlug.value,
            granted: has,
        };
    } finally {
        isTesting.value = false;
    }
};

const breadcrumbLabel = computed(() => {
    switch (activeSection.value) {
        case 'users': return 'Staff Directory & Access Matrix';
        case 'roles': return 'Standard System Roles & Permissions';
        case 'permissions': return 'Granular Permissions Catalog';
        default: return 'Access Control';
    }
});
</script>

<template>
    <AfyaShell active-module="access-control">
        <Head title="Access Control & RBAC Management" />

        <AfyaWorkspace :show-context="true">
            <!-- 1. LEFT SIDEBAR -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Access Control"
                    :icon="Shield"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                        Authorization Domains
                    </div>

                    <AfyaSidebarItem
                        v-if="can.users"
                        :icon="Users"
                        label="Staff Directory"
                        :active="activeSection === 'users'"
                        :collapsed="state === 'collapsed'"
                        :badge="users.length"
                        @click="activeSection = 'users'"
                    />
                    <AfyaSidebarItem
                        v-if="can.roles"
                        :icon="Key"
                        label="Standard Roles"
                        :active="activeSection === 'roles'"
                        :collapsed="state === 'collapsed'"
                        :badge="roles.length"
                        @click="activeSection = 'roles'"
                    />
                    <AfyaSidebarItem
                        v-if="can.permissions"
                        :icon="Sliders"
                        label="Permissions Matrix"
                        :active="activeSection === 'permissions'"
                        :collapsed="state === 'collapsed'"
                        :badge="metrics.total_permissions"
                        @click="activeSection = 'permissions'"
                    />

                    <!-- Standard Roles Quick Jump -->
                    <div v-if="state !== 'collapsed'" class="px-2 pt-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground border-t border-border/40 mt-2">
                        System Roles (10)
                    </div>
                    <div v-if="state !== 'collapsed' && can.updatePermissions" class="space-y-1 px-1 max-h-56 overflow-y-auto">
                        <button
                            v-for="r in roles"
                            :key="r.id"
                            @click="openEditRolePermissions(r)"
                            class="w-full text-left px-2 py-1 rounded text-xs truncate flex items-center justify-between hover:bg-muted text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <span class="truncate">{{ r.name }}</span>
                            <span class="text-[9px] font-mono bg-muted px-1.5 py-0.5 rounded">{{ r.permissions?.length || 0 }}</span>
                        </button>
                    </div>
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER PANEL -->
            <template #default>
                <AfyaWorkspaceMain
                    :breadcrumbs="[
                        { label: 'Access Control', href: route('access-control.workspace') },
                        { label: breadcrumbLabel, active: true }
                    ]"
                >
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="can.assignRole"
                                variant="default"
                                size="sm"
                                class="h-7 text-xs font-semibold gap-1 shadow-2xs"
                                @click="openAssignRoleModal()"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Assign Role Scope</span>
                            </Button>

                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="h-7 text-xs font-semibold gap-1 bg-card shadow-2xs"
                                @click="router.reload()"
                            >
                                <RefreshCw class="w-3 h-3 text-primary" />
                                <span>Refresh</span>
                            </Button>
                        </div>
                    </template>

                    <div class="w-full space-y-4">
                        
                        <!-- TOP METRICS STRIP -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Total Staff</span>
                                    <Users class="w-3.5 h-3.5 text-primary" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ metrics.total_users }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5">
                                    Provisioned accounts
                                </div>
                            </div>

                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Standard Roles</span>
                                    <Key class="w-3.5 h-3.5 text-indigo-600" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ metrics.total_roles }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5">
                                    Pre-configured archetypes
                                </div>
                            </div>

                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Granular Permissions</span>
                                    <Sliders class="w-3.5 h-3.5 text-emerald-600" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ metrics.total_permissions }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5">
                                    Across all 13 domains
                                </div>
                            </div>

                            <div class="bg-card p-3 rounded-lg border border-border/60 shadow-2xs">
                                <div class="flex items-center justify-between text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <span>Multi-Facility Scopes</span>
                                    <Building2 class="w-3.5 h-3.5 text-sky-600" />
                                </div>
                                <div class="mt-1 text-base font-extrabold text-foreground font-mono">
                                    {{ metrics.multi_facility_assignments }}
                                </div>
                                <div class="text-[9px] text-muted-foreground mt-0.5">
                                    Branch-restricted assignments
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 1: STAFF DIRECTORY & ASSIGNMENTS                       -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'users'" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Search staff by name, email, or role..."
                                    class="h-8 text-xs bg-card max-w-sm"
                                />
                            </div>

                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Staff Name</TableHead>
                                            <TableHead class="py-2 px-3">Email Address</TableHead>
                                            <TableHead class="py-2 px-3">Primary Role</TableHead>
                                            <TableHead class="py-2 px-3">Assigned Scopes (Facility / Dept)</TableHead>
                                            <TableHead class="py-2 px-3">Status</TableHead>
                                            <TableHead class="py-2 px-3 text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="u in filteredUsers" 
                                            :key="u.id"
                                            @click="selectUserForInspector(u)"
                                            :class="selectedUser?.id === u.id ? 'bg-primary/5 font-semibold' : ''"
                                            class="hover:bg-muted/20 cursor-pointer border-b border-border/30"
                                        >
                                            <TableCell class="py-2 px-3 font-bold text-foreground">
                                                {{ u.first_name }} {{ u.last_name }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono text-xs text-muted-foreground">
                                                {{ u.email }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge status="active" :label="u.role || 'Staff'" />
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-xs">
                                                <div v-if="u.role_assignments?.length" class="space-y-1">
                                                    <div v-for="ra in u.role_assignments" :key="ra.id" class="text-[11px] text-muted-foreground">
                                                        <span class="font-bold text-foreground">{{ ra.role?.name }}</span>
                                                        <span v-if="ra.facility" class="text-primary font-mono ml-1">@ {{ ra.facility.name }}</span>
                                                        <span v-else class="text-muted-foreground ml-1">(All Facilities)</span>
                                                    </div>
                                                </div>
                                                <span v-else class="text-muted-foreground italic">Global Tenant Scope</span>
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge status="active" label="Active" />
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right">
                                                <Button
                                                    v-if="can.assignRole"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-6 text-[10px] font-bold"
                                                    @click.stop="openAssignRoleModal(u)"
                                                >
                                                    Add Role Scope
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 2: STANDARD ROLES & PERMISSIONS                        -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'roles'" class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div 
                                    v-for="r in roles" 
                                    :key="r.id"
                                    class="bg-card p-4 rounded-lg border border-border/60 shadow-2xs space-y-3 hover:border-primary/40 transition-all"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-bold text-foreground text-sm flex items-center gap-1.5">
                                                <Key class="w-4 h-4 text-primary" />
                                                <span>{{ r.name }}</span>
                                            </h4>
                                            <div class="text-[10px] font-mono text-muted-foreground">{{ r.slug }}</div>
                                        </div>
                                        <Button
                                            v-if="can.updatePermissions"
                                            variant="outline"
                                            size="sm"
                                            class="h-6 text-[10px] font-bold"
                                            @click="openEditRolePermissions(r)"
                                        >
                                            Configure Permissions
                                        </Button>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ r.description || 'Pre-configured system role archetype.' }}</p>
                                    <div class="flex items-center justify-between text-[11px] border-t border-border/30 pt-2 text-muted-foreground">
                                        <span>Granted Permissions:</span>
                                        <span class="font-mono font-bold text-primary">{{ r.permissions?.length || 0 }} capabilities</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION 3: PERMISSIONS BY DOMAIN CATEGORY                      -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'permissions'" class="space-y-4">
                            <div v-for="(perms, domainName) in permissionsByDomain" :key="domainName" class="bg-card p-4 rounded-lg border border-border/60 shadow-2xs space-y-2">
                                <div class="font-bold text-xs uppercase tracking-wider text-primary border-b border-border/40 pb-1.5 flex items-center justify-between">
                                    <span>Domain: {{ domainName }}</span>
                                    <span class="font-mono text-muted-foreground text-[10px]">{{ perms.length }} permissions</span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
                                    <div v-for="p in perms" :key="p.id" class="p-2 bg-muted/20 rounded border border-border/40 space-y-0.5">
                                        <div class="font-mono text-[11px] font-bold text-foreground">{{ p.slug }}</div>
                                        <div class="text-[10px] text-muted-foreground truncate">{{ p.description || p.name }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT PANEL: LIVE PERMISSION TESTER -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Live Permission Tester"
                    :icon="Shield"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedUser" class="space-y-3.5 text-xs">
                        <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-1">
                            <div class="font-bold text-foreground text-sm">{{ selectedUser.first_name }} {{ selectedUser.last_name }}</div>
                            <div class="font-mono text-primary text-[11px]">{{ selectedUser.email }}</div>
                            <div class="text-[11px] text-muted-foreground">Primary Role: {{ selectedUser.role }}</div>
                        </div>

                        <!-- Effective Permissions Count -->
                        <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-1.5">
                            <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider flex items-center justify-between">
                                <span>Effective Permissions</span>
                                <span class="font-mono text-primary font-bold">{{ effectivePermissions.length }} granted</span>
                            </div>
                            <div class="max-h-40 overflow-y-auto space-y-1 p-2 bg-muted/20 rounded border border-border/30 font-mono text-[10px]">
                                <div v-for="slug in effectivePermissions" :key="slug" class="text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                    <CheckCircle2 class="w-3 h-3 flex-shrink-0" />
                                    <span class="truncate">{{ slug }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Interactive Authorization Test -->
                        <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-2">
                            <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider">
                                Evaluate Action Gate
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Permission Slug</label>
                                    <Input v-model="testPermissionSlug" placeholder="e.g. clinical.notes.sign" class="h-7 text-xs font-mono" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-muted-foreground uppercase">Facility Scope (Optional)</label>
                                    <select v-model="testFacilityId" class="w-full h-7 text-xs rounded border border-border bg-card px-2">
                                        <option value="">Global Tenant Check</option>
                                        <option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
                                    </select>
                                </div>
                                <Button 
                                    variant="default" 
                                    size="sm" 
                                    class="w-full h-7 text-xs font-bold gap-1 shadow-2xs"
                                    :disabled="isTesting"
                                    @click="runLivePermissionTest"
                                >
                                    <span>Test Authorization Gate</span>
                                </Button>
                            </div>

                            <div v-if="testResult" class="p-3 rounded-lg border text-center transition-all mt-2" :class="testResult.granted ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-rose-500/10 border-rose-500/30'">
                                <div class="flex items-center justify-center gap-1.5 font-bold text-xs" :class="testResult.granted ? 'text-emerald-600' : 'text-rose-600'">
                                    <CheckCircle2 v-if="testResult.granted" class="w-4 h-4" />
                                    <XCircle v-else class="w-4 h-4" />
                                    <span>{{ testResult.granted ? 'ACCESS GRANTED' : 'ACCESS DENIED' }}</span>
                                </div>
                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                    Evaluated in {{ testResult.elapsed_ms }}ms ({{ testResult.scope_level }} scope)
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-4 bg-card rounded-lg border border-border/60 shadow-2xs text-center py-12 text-muted-foreground text-xs space-y-2">
                        <Shield class="w-8 h-8 text-muted-foreground/40 mx-auto" />
                        <div>Select a user from the staff directory to inspect and test effective runtime permissions.</div>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>

        <!-- MODAL: ASSIGN ROLE -->
        <div v-if="showAssignRoleModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Key class="w-4 h-4 text-primary" />
                        <span>Assign Role Scope to Staff</span>
                    </h3>
                    <button @click="showAssignRoleModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Staff Member</label>
                        <select v-model="assignRoleForm.user_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.first_name }} {{ u.last_name }} ({{ u.email }})</option>
                        </select>
                        <InputError :message="assignRoleForm.errors.user_id" class="mt-1" />
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">System Role</label>
                        <select v-model="assignRoleForm.role_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                        <InputError :message="assignRoleForm.errors.role_id" class="mt-1" />
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Facility Scope</label>
                        <select v-model="assignRoleForm.facility_id" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                            <option value="">All Facilities (Tenant Wide)</option>
                            <option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
                        </select>
                        <InputError :message="assignRoleForm.errors.facility_id" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-border/50 pt-3">
                    <Button variant="outline" size="sm" @click="showAssignRoleModal = false">Cancel</Button>
                    <Button variant="default" size="sm" :disabled="assignRoleForm.processing" @click="submitAssignRole">
                        Assign Scope
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: CONFIGURE ROLE PERMISSIONS -->
        <div v-if="showEditRolePermissionsModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3 flex-shrink-0">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Sliders class="w-4 h-4 text-primary" />
                        <span>Configure Role: {{ selectedRole?.name }}</span>
                    </h3>
                    <button @click="showEditRolePermissionsModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <InputError :message="rolePermissionsForm.errors.permission_ids" class="px-1" />

                <div class="flex-1 overflow-y-auto space-y-4 text-xs pr-1">
                    <div v-for="(perms, domain) in permissionsByDomain" :key="domain" class="space-y-2">
                        <div class="font-bold text-[10px] uppercase text-primary tracking-wider border-b border-border/40 pb-1">
                            {{ domain }} Domain ({{ perms.length }})
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <label 
                                v-for="p in perms" 
                                :key="p.id"
                                class="flex items-start gap-2 p-2 rounded border border-border/30 hover:bg-muted/30 cursor-pointer"
                            >
                                <input 
                                    type="checkbox" 
                                    :checked="rolePermissionsForm.permission_ids.includes(p.id)"
                                    @change="togglePermissionCheckbox(p.id)"
                                    class="rounded border-border text-primary focus:ring-primary mt-0.5"
                                />
                                <div class="min-w-0">
                                    <div class="font-mono text-[11px] font-bold text-foreground truncate">{{ p.slug }}</div>
                                    <div class="text-[10px] text-muted-foreground truncate">{{ p.name }}</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-border/50 pt-3 flex-shrink-0">
                    <Button variant="outline" size="sm" @click="showEditRolePermissionsModal = false">Cancel</Button>
                    <Button variant="default" size="sm" :disabled="rolePermissionsForm.processing" @click="submitRolePermissions">
                        Save Role Permissions
                    </Button>
                </div>
            </div>
        </div>

    </AfyaShell>
</template>
