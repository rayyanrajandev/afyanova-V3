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
    ShieldAlert,
    Check,
    ChevronsUpDown
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Combobox from '@/Components/ui/Combobox.vue';
import Input from '@/Components/ui/Input.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
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
const showCreateUserModal = ref(false);
const showEditUserModal = ref(false);
const showResetPasswordModal = ref(false);
const targetUserForAction = ref(null);

// Forms
const assignRoleForm = useForm({
    user_id: '',
    role_id: '',
    facility_id: '',
    department_id: '',
});

// Display label for the currently selected staff member in the Assign Role
// combobox trigger - the form field itself stays a plain user id.
const assignRoleStaffLabel = computed(() => {
    const u = props.users.find(u => u.id === assignRoleForm.user_id);
    return u ? `${u.first_name} ${u.last_name} (${u.email})` : '';
});
const assignRoleFacilityLabel = computed(() => {
    const f = props.facilities.find(f => f.id === assignRoleForm.facility_id);
    return f ? f.name : '';
});
const createUserFacilityLabel = computed(() => {
    const f = props.facilities.find(f => f.id === createUserForm.facility_id);
    return f ? f.name : '';
});

const createUserForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    professional_registration_no: '',
    role_id: '',
    facility_id: '',
    password: 'Password@123',
});

const editUserForm = useForm({
    first_name: '',
    last_name: '',
    phone: '',
    professional_registration_no: '',
    status: 'Active',
});

const resetPasswordForm = useForm({
    password: '',
});

const rolePermissionsForm = useForm({
    permission_ids: [],
});

const openCreateUserModal = () => {
    createUserForm.reset();
    createUserForm.role_id = props.roles[0]?.id || '';
    createUserForm.facility_id = props.facilities[0]?.id || '';
    showCreateUserModal.value = true;
};

const submitCreateUser = () => {
    createUserForm.post(route('access-control.users.store'), {
        onSuccess: () => {
            showCreateUserModal.value = false;
            createUserForm.reset();
        }
    });
};

const openEditUserModal = (user) => {
    targetUserForAction.value = user;
    editUserForm.first_name = user.first_name;
    editUserForm.last_name = user.last_name;
    editUserForm.phone = user.phone || '';
    editUserForm.professional_registration_no = user.professional_registration_no || '';
    editUserForm.status = user.status || 'Active';
    showEditUserModal.value = true;
};

const submitEditUser = () => {
    if (!targetUserForAction.value) return;
    editUserForm.put(route('access-control.users.update', targetUserForAction.value.id), {
        onSuccess: () => {
            showEditUserModal.value = false;
        }
    });
};

const toggleUserStatus = (user) => {
    if (!confirm(`Are you sure you want to change account status for ${user.first_name} ${user.last_name}?`)) return;
    router.post(route('access-control.users.toggle-status', user.id));
};

const openResetPasswordModal = (user) => {
    targetUserForAction.value = user;
    resetPasswordForm.password = '';
    showResetPasswordModal.value = true;
};

const submitResetPassword = () => {
    if (!targetUserForAction.value) return;
    resetPasswordForm.post(route('access-control.users.reset-password', targetUserForAction.value.id), {
        onSuccess: () => {
            showResetPasswordModal.value = false;
            resetPasswordForm.reset();
        }
    });
};

// Facility & Department Management State
const showCreateFacilityModal = ref(false);
const showCreateDepartmentModal = ref(false);
const targetFacilityForDept = ref(null);

const createFacilityForm = useForm({
    name: '',
    code: '',
    facility_type: 'Hospital',
    hfr_code: '',
    license_number: '',
    physical_address: '',
    contact_phone: '',
    contact_email: '',
});

const createDepartmentForm = useForm({
    facility_id: '',
    name: '',
    code: '',
    department_type: 'Clinical',
});

const openCreateFacilityModal = () => {
    createFacilityForm.reset();
    createFacilityForm.code = `FAC-${Math.floor(100 + Math.random() * 900)}`;
    showCreateFacilityModal.value = true;
};

const submitCreateFacility = () => {
    createFacilityForm.post(route('access-control.facilities.store'), {
        onSuccess: () => {
            showCreateFacilityModal.value = false;
            createFacilityForm.reset();
        }
    });
};

const openCreateDepartmentModal = (facility) => {
    targetFacilityForDept.value = facility;
    createDepartmentForm.facility_id = facility.id;
    createDepartmentForm.name = '';
    createDepartmentForm.code = '';
    createDepartmentForm.department_type = 'Clinical';
    showCreateDepartmentModal.value = true;
};

const submitCreateDepartment = () => {
    createDepartmentForm.post(route('access-control.departments.store'), {
        onSuccess: () => {
            showCreateDepartmentModal.value = false;
            createDepartmentForm.reset();
        }
    });
};

// Live Permission Tester State
const testPermissionSlug = ref('clinical.encounter.create');
const testFacilityId = ref('');
const testResult = ref(null);
const isTesting = ref(false);

// Helper to extract the primary display role for a staff member
const getUserPrimaryRole = (user) => {
    if (!user) return 'Staff';
    if (user.role_assignments?.length && user.role_assignments[0].role) {
        return user.role_assignments[0].role.name || user.role_assignments[0].role.slug;
    }
    if (user.roles?.length) {
        return user.roles[0].name || user.roles[0].slug;
    }
    return user.role || 'Staff';
};

const facilityTestOptions = computed(() => [
    { label: 'Global Tenant Check', value: '' },
    ...(props.facilities || []).map(f => ({
        label: f.name,
        value: f.id,
        description: f.facility_type || 'Facility Branch'
    }))
]);

const facilityBranchOptions = computed(() => [
    { label: 'All Branches (Tenant Wide)', value: '' },
    ...(props.facilities || []).map(f => ({
        label: f.name,
        value: f.id,
        description: f.facility_type || 'Facility Branch'
    }))
]);

const staffOptions = computed(() => (props.users || []).map(u => ({
    label: `${u.first_name} ${u.last_name}`,
    value: u.id,
    description: u.email,
    badge: getUserPrimaryRole(u)
})));

const filteredUsers = computed(() => {
    if (!searchQuery.value) return props.users;
    const q = searchQuery.value.toLowerCase();
    return props.users.filter(u => 
        u.first_name?.toLowerCase().includes(q) ||
        u.last_name?.toLowerCase().includes(q) ||
        u.email?.toLowerCase().includes(q) ||
        getUserPrimaryRole(u).toLowerCase().includes(q) ||
        u.role_assignments?.some(ra => 
            ra.role?.name?.toLowerCase().includes(q) || 
            ra.role?.slug?.toLowerCase().includes(q) ||
            ra.facility?.name?.toLowerCase().includes(q)
        )
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
        case 'facilities': return 'Facility Branches & Department Units';
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
                        v-if="can.facilities"
                        :icon="Building2"
                        label="Facilities & Branches"
                        :active="activeSection === 'facilities'"
                        :collapsed="state === 'collapsed'"
                        :badge="facilities.length"
                        @click="activeSection = 'facilities'"
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
                                v-if="can.users"
                                variant="default"
                                size="sm"
                                class="h-7 text-xs font-semibold gap-1 shadow-2xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold"
                                @click="openCreateUserModal"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Staff Member</span>
                            </Button>

                            <Button
                                v-if="can.assignRole"
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs font-semibold gap-1 shadow-2xs"
                                @click="openAssignRoleModal()"
                            >
                                <Key class="w-3.5 h-3.5 text-primary" />
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
                                <SearchInput
                                    v-model="searchQuery"
                                    placeholder="Search staff by name, email, role, or MCT license..."
                                    size="default"
                                    class="max-w-sm"
                                />
                            </div>

                            <div class="bg-card rounded-lg border border-border/60 shadow-2xs overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="bg-muted/40 text-[10px] uppercase tracking-wider font-bold">
                                            <TableHead class="py-2 px-3">Staff Member & Credential</TableHead>
                                            <TableHead class="py-2 px-3">Contact Email & Phone</TableHead>
                                            <TableHead class="py-2 px-3">Primary Role</TableHead>
                                            <TableHead class="py-2 px-3">Assigned Scopes (Facility / Dept)</TableHead>
                                            <TableHead class="py-2 px-3">Account Status</TableHead>
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
                                            <TableCell class="py-2 px-3">
                                                <div class="font-bold text-foreground">{{ u.first_name }} {{ u.last_name }}</div>
                                                <div v-if="u.professional_registration_no" class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400">
                                                    MCT / Lic: {{ u.professional_registration_no }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-2 px-3 font-mono text-xs text-muted-foreground">
                                                <div>{{ u.email }}</div>
                                                <div v-if="u.phone" class="text-[10px] text-muted-foreground">{{ u.phone }}</div>
                                            </TableCell>
                                            <TableCell class="py-2 px-3">
                                                <AfyaStatusBadge status="active" :label="getUserPrimaryRole(u)" />
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
                                                <AfyaStatusBadge 
                                                    :status="u.status === 'Active' ? 'active' : (u.status === 'Suspended' ? 'warning' : 'inactive')" 
                                                    :label="u.status || 'Active'" 
                                                />
                                            </TableCell>
                                            <TableCell class="py-2 px-3 text-right">
                                                <div class="flex items-center justify-end gap-1.5" @click.stop>
                                                    <Button
                                                        v-if="can.users"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10px] font-semibold text-muted-foreground hover:text-foreground"
                                                        @click="openEditUserModal(u)"
                                                        title="Edit staff details and MCT credentials"
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        v-if="can.assignRole"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10px] font-bold text-primary"
                                                        @click="openAssignRoleModal(u)"
                                                    >
                                                        Scope
                                                    </Button>
                                                    <Button
                                                        v-if="can.users"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10px] font-semibold"
                                                        :class="u.status === 'Active' ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50'"
                                                        @click="toggleUserStatus(u)"
                                                    >
                                                        {{ u.status === 'Active' ? 'Suspend' : 'Activate' }}
                                                    </Button>
                                                    <Button
                                                        v-if="can.users"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-6 px-2 text-[10px] font-semibold text-rose-600 hover:bg-rose-50"
                                                        @click="openResetPasswordModal(u)"
                                                        title="Reset staff password"
                                                    >
                                                        PW
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- ============================================================== -->
                        <!-- SECTION: FACILITY BRANCHES & DEPARTMENT TOPOLOGY               -->
                        <!-- ============================================================== -->
                        <div v-if="activeSection === 'facilities'" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-bold text-sm text-foreground">Facility Branches & Operating Units</h3>
                                    <p class="text-xs text-muted-foreground">Manage multi-facility topology, HFR health facility registry codes, and department structures</p>
                                </div>
                                <Button
                                    v-if="can.facilities"
                                    variant="default"
                                    size="sm"
                                    class="h-7 text-xs font-semibold gap-1 bg-sky-600 hover:bg-sky-700 text-white font-bold"
                                    @click="openCreateFacilityModal"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Add Facility Branch</span>
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div
                                    v-for="fac in facilities"
                                    :key="fac.id"
                                    class="bg-card p-4 rounded-xl border border-border/60 shadow-2xs space-y-3"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-2">
                                                <Building2 class="w-4 h-4 text-sky-600" />
                                                <h4 class="font-bold text-sm text-foreground">{{ fac.name }}</h4>
                                            </div>
                                            <div class="flex items-center gap-2 text-[10px] font-mono text-muted-foreground">
                                                <span>Code: {{ fac.code }}</span>
                                                <span v-if="fac.hfr_code" class="text-primary font-bold">HFR: {{ fac.hfr_code }}</span>
                                            </div>
                                        </div>
                                        <AfyaStatusBadge :status="fac.is_active ? 'active' : 'inactive'" :label="fac.is_active ? 'Active Branch' : 'Inactive'" />
                                    </div>

                                    <div class="text-xs text-muted-foreground space-y-1 bg-muted/20 p-2.5 rounded-lg">
                                        <div v-if="fac.physical_address">📍 {{ fac.physical_address }}</div>
                                        <div class="flex items-center gap-4 text-[11px]">
                                            <span v-if="fac.contact_phone">📞 {{ fac.contact_phone }}</span>
                                            <span v-if="fac.contact_email">✉️ {{ fac.contact_email }}</span>
                                            <span v-if="fac.facility_type" class="font-bold uppercase text-[10px] text-foreground">🏥 {{ fac.facility_type }}</span>
                                        </div>
                                    </div>

                                    <!-- Departments in this Facility -->
                                    <div class="space-y-2 border-t border-border/40 pt-2.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-foreground text-[11px] uppercase tracking-wider">Operating Departments ({{ fac.departments?.length || 0 }})</span>
                                            <Button
                                                v-if="can.facilities"
                                                variant="outline"
                                                size="sm"
                                                class="h-5 px-2 text-[9.5px] font-bold text-primary border-primary/30 hover:bg-primary/5"
                                                @click="openCreateDepartmentModal(fac)"
                                            >
                                                <Plus class="w-2.5 h-2.5 mr-0.5" />
                                                <span>Add Dept</span>
                                            </Button>
                                        </div>

                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="dept in fac.departments"
                                                :key="dept.id"
                                                class="px-2 py-0.5 rounded-md bg-muted text-[11px] font-medium text-foreground border border-border/50 flex items-center gap-1"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span>{{ dept.name }}</span>
                                                <span v-if="dept.code" class="text-[9px] font-mono text-muted-foreground">({{ dept.code }})</span>
                                            </span>
                                            <span v-if="!fac.departments?.length" class="text-[11px] text-muted-foreground italic">
                                                No departments registered yet.
                                            </span>
                                        </div>
                                    </div>
                                </div>
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
                            <div class="text-[11px] text-muted-foreground">Primary Role: {{ getUserPrimaryRole(selectedUser) }}</div>
                        </div>

                        <!-- Effective Permissions Count -->
                        <div class="p-3 bg-card rounded-lg border border-border/60 shadow-2xs space-y-1.5">
                            <div class="text-[10px] font-bold uppercase text-muted-foreground tracking-wider flex items-center justify-between">
                                <span>Effective Permissions</span>
                                <span class="font-mono text-primary font-bold">{{ effectivePermissions.length }} granted</span>
                            </div>
                            <div class="max-h-40 overflow-y-auto space-y-1 p-2 bg-muted/20 rounded border border-border/30 font-mono text-[10px]">
                                <div v-for="slug in effectivePermissions" :key="slug" class="text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                    <CheckCircle2 class="w-3 h-3 shrink-0" />
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
                                    <Combobox
                                        v-model="testFacilityId"
                                        :options="facilityTestOptions"
                                        placeholder="Global Tenant Check"
                                        search-placeholder="Search facilities..."
                                        size="sm"
                                    />
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
                        <Combobox
                            v-model="assignRoleForm.user_id"
                            :options="staffOptions"
                            placeholder="Select staff member..."
                            search-placeholder="Search staff by name or email..."
                            size="default"
                        />
                        <InputError :message="assignRoleForm.errors.user_id" class="mt-1" />
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">System Role</label>
                        <Select v-model="assignRoleForm.role_id" class="w-full">
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </Select>
                        <InputError :message="assignRoleForm.errors.role_id" class="mt-1" />
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Facility Scope</label>
                        <Combobox
                            v-model="assignRoleForm.facility_id"
                            :options="facilityBranchOptions"
                            placeholder="All Facilities (Tenant Wide)"
                            search-placeholder="Search facilities..."
                            size="default"
                        />
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
                <div class="flex items-center justify-between border-b border-border/50 pb-3 shrink-0">
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

                <div class="flex justify-end gap-2 border-t border-border/50 pt-3 shrink-0">
                    <Button variant="outline" size="sm" @click="showEditRolePermissionsModal = false">Cancel</Button>
                    <Button variant="default" size="sm" :disabled="rolePermissionsForm.processing" @click="submitRolePermissions">
                        Save Role Permissions
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: ADD NEW STAFF MEMBER -->
        <div v-if="showCreateUserModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-lg p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <UserCheck class="w-4 h-4 text-emerald-600" />
                        <span>Add & Credential New Staff Member</span>
                    </h3>
                    <button @click="showCreateUserModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateUser" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">First Name *</label>
                            <Input v-model="createUserForm.first_name" required placeholder="e.g. Amina" class="h-8 text-xs" />
                            <InputError :message="createUserForm.errors.first_name" class="mt-1" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Last Name *</label>
                            <Input v-model="createUserForm.last_name" required placeholder="e.g. Mussa" class="h-8 text-xs" />
                            <InputError :message="createUserForm.errors.last_name" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Email Address *</label>
                            <Input v-model="createUserForm.email" type="email" required placeholder="doctor@afyanova.co.tz" class="h-8 text-xs font-mono" />
                            <InputError :message="createUserForm.errors.email" class="mt-1" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Phone Number</label>
                            <Input v-model="createUserForm.phone" placeholder="+255 7..." class="h-8 text-xs" />
                            <InputError :message="createUserForm.errors.phone" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Medical Council of Tanganyika (MCT) / Professional License #</label>
                        <Input v-model="createUserForm.professional_registration_no" placeholder="e.g. MCT-DR-84729 or TNMC-RN-48201" class="h-8 text-xs font-mono" />
                        <p class="text-[9.5px] text-muted-foreground mt-0.5">Required for e-prescriptions, surgical bookings, and clinical note signatures.</p>
                        <InputError :message="createUserForm.errors.professional_registration_no" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Primary Role Archetype *</label>
                            <Select v-model="createUserForm.role_id" required class="w-full">
                                <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </Select>
                            <InputError :message="createUserForm.errors.role_id" class="mt-1" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Facility Branch</label>
                            <Combobox
                                v-model="createUserForm.facility_id"
                                :options="facilityBranchOptions"
                                placeholder="All Branches (Tenant Wide)"
                                search-placeholder="Search facilities..."
                                size="default"
                            />
                            <InputError :message="createUserForm.errors.facility_id" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Temporary Password *</label>
                        <Input v-model="createUserForm.password" type="password" required class="h-8 text-xs font-mono" />
                        <InputError :message="createUserForm.errors.password" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border/50 pt-3">
                        <Button type="button" variant="outline" size="sm" @click="showCreateUserModal = false">Cancel</Button>
                        <Button type="submit" variant="default" size="sm" :disabled="createUserForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold">
                            Create Staff Account
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: EDIT STAFF PROFILE -->
        <div v-if="showEditUserModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Users class="w-4 h-4 text-primary" />
                        <span>Edit Staff Details: {{ targetUserForAction?.first_name }} {{ targetUserForAction?.last_name }}</span>
                    </h3>
                    <button @click="showEditUserModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitEditUser" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">First Name *</label>
                            <Input v-model="editUserForm.first_name" required class="h-8 text-xs" />
                            <InputError :message="editUserForm.errors.first_name" class="mt-1" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Last Name *</label>
                            <Input v-model="editUserForm.last_name" required class="h-8 text-xs" />
                            <InputError :message="editUserForm.errors.last_name" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Phone Number</label>
                        <Input v-model="editUserForm.phone" placeholder="+255 7..." class="h-8 text-xs" />
                        <InputError :message="editUserForm.errors.phone" class="mt-1" />
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">MCT / Professional License Registration #</label>
                        <Input v-model="editUserForm.professional_registration_no" placeholder="e.g. MCT-DR-84729" class="h-8 text-xs font-mono" />
                        <InputError :message="editUserForm.errors.professional_registration_no" class="mt-1" />
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Account Status</label>
                        <Select v-model="editUserForm.status" class="w-full">
                            <option value="Active">Active</option>
                            <option value="Suspended">Suspended</option>
                            <option value="Inactive">Inactive</option>
                        </Select>
                        <InputError :message="editUserForm.errors.status" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border/50 pt-3">
                        <Button type="button" variant="outline" size="sm" @click="showEditUserModal = false">Cancel</Button>
                        <Button type="submit" variant="default" size="sm" :disabled="editUserForm.processing">
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: RESET PASSWORD -->
        <div v-if="showResetPasswordModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Key class="w-4 h-4 text-rose-600" />
                        <span>Reset Password for {{ targetUserForAction?.first_name }}</span>
                    </h3>
                    <button @click="showResetPasswordModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitResetPassword" class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">New Password *</label>
                        <Input v-model="resetPasswordForm.password" type="password" required minlength="8" placeholder="Minimum 8 characters..." class="h-8 text-xs font-mono" />
                        <InputError :message="resetPasswordForm.errors.password" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border/50 pt-3">
                        <Button type="button" variant="outline" size="sm" @click="showResetPasswordModal = false">Cancel</Button>
                        <Button type="submit" variant="default" size="sm" :disabled="resetPasswordForm.processing" class="bg-rose-600 hover:bg-rose-700 text-white font-bold">
                            Reset Password
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: ADD FACILITY BRANCH -->
        <div v-if="showCreateFacilityModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-lg p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-sky-600" />
                        <span>Register Facility Branch</span>
                    </h3>
                    <button @click="showCreateFacilityModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateFacility" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Branch Name *</label>
                            <Input v-model="createFacilityForm.name" required placeholder="e.g. AfyaNova Mbezi Beach Clinic" class="h-8 text-xs" />
                            <InputError :message="createFacilityForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Branch Code *</label>
                            <Input v-model="createFacilityForm.code" required placeholder="e.g. MBZ-01" class="h-8 text-xs font-mono" />
                            <InputError :message="createFacilityForm.errors.code" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Facility Type *</label>
                            <Select v-model="createFacilityForm.facility_type" class="w-full">
                                <option value="Hospital">General Hospital</option>
                                <option value="Clinic">Polyclinic / Outpatient</option>
                                <option value="Dispensary">Dispensary</option>
                                <option value="Diagnostic_Center">Diagnostic Center</option>
                            </Select>
                            <InputError :message="createFacilityForm.errors.facility_type" class="mt-1" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">MoH HFR Code</label>
                            <Input v-model="createFacilityForm.hfr_code" placeholder="e.g. 104820-1" class="h-8 text-xs font-mono" />
                            <InputError :message="createFacilityForm.errors.hfr_code" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Physical Address / Street</label>
                        <Input v-model="createFacilityForm.physical_address" placeholder="e.g. Plot 42, Bagamoyo Road, Dar es Salaam" class="h-8 text-xs" />
                        <InputError :message="createFacilityForm.errors.physical_address" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Contact Phone</label>
                            <Input v-model="createFacilityForm.contact_phone" placeholder="+255 22 2..." class="h-8 text-xs" />
                            <InputError :message="createFacilityForm.errors.contact_phone" class="mt-1" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Contact Email</label>
                            <Input v-model="createFacilityForm.contact_email" type="email" placeholder="mbezi@afyanova.co.tz" class="h-8 text-xs font-mono" />
                            <InputError :message="createFacilityForm.errors.contact_email" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border/50 pt-3">
                        <Button type="button" variant="outline" size="sm" @click="showCreateFacilityModal = false">Cancel</Button>
                        <Button type="submit" variant="default" size="sm" :disabled="createFacilityForm.processing" class="bg-sky-600 hover:bg-sky-700 text-white font-bold">
                            Register Branch
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: ADD DEPARTMENT -->
        <div v-if="showCreateDepartmentModal" class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-card border border-border/60 rounded-xl shadow-xl w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border/50 pb-3">
                    <h3 class="font-bold text-foreground text-sm flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-primary" />
                        <span>Add Department: {{ targetFacilityForDept?.name }}</span>
                    </h3>
                    <button @click="showCreateDepartmentModal = false" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateDepartment" class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-muted-foreground text-[10px] uppercase">Department Name *</label>
                        <Input v-model="createDepartmentForm.name" required placeholder="e.g. Obstetrics & Gynecology" class="h-8 text-xs" />
                        <InputError :message="createDepartmentForm.errors.name" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Department Code</label>
                            <Input v-model="createDepartmentForm.code" placeholder="e.g. OBGYN" class="h-8 text-xs font-mono" />
                            <InputError :message="createDepartmentForm.errors.code" class="mt-1" />
                        </div>
                        <div>
                            <label class="font-bold text-muted-foreground text-[10px] uppercase">Department Type *</label>
                            <Select v-model="createDepartmentForm.department_type" class="w-full">
                                <option value="Clinical">Clinical</option>
                                <option value="Diagnostic">Diagnostic / Lab / Rad</option>
                                <option value="Surgical">Surgical / Theatre</option>
                                <option value="Administrative">Administrative / Finance</option>
                                <option value="Support">Support Services</option>
                            </Select>
                            <InputError :message="createDepartmentForm.errors.department_type" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border/50 pt-3">
                        <Button type="button" variant="outline" size="sm" @click="showCreateDepartmentModal = false">Cancel</Button>
                        <Button type="submit" variant="default" size="sm" :disabled="createDepartmentForm.processing">
                            Add Department
                        </Button>
                    </div>
                </form>
            </div>
        </div>

    </AfyaShell>
</template>
