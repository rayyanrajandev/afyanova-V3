<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Palette,
    Layers,
    Tag,
    Sliders,
    Table2,
    ShieldAlert,
    ClipboardList,
    AlertTriangle
} from '@lucide/vue';
import AfyaShell from '@/Layouts/AfyaShell.vue';
import AfyaWorkspace from '@/Components/Workspace/AfyaWorkspace.vue';
import AfyaSidebar from '@/Components/Workspace/AfyaSidebar.vue';
import AfyaSidebarItem from '@/Components/Workspace/AfyaSidebarItem.vue';
import AfyaWorkspaceMain from '@/Components/Workspace/AfyaWorkspaceMain.vue';
import AfyaContextPanel from '@/Components/Workspace/AfyaContextPanel.vue';

// UI Primitives
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Badge from '@/Components/ui/Badge.vue';
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

// AfyaNova Components
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import AfyaPatientIdentity from '@/Components/Afya/AfyaPatientIdentity.vue';
import AfyaClinicalAlert from '@/Components/Afya/AfyaClinicalAlert.vue';
import AfyaFilterBar from '@/Components/Afya/AfyaFilterBar.vue';
import AfyaSection from '@/Components/Afya/AfyaSection.vue';

const activeSection = ref('overview');
const searchFilter = ref('');
const showContext = ref(true);

const samplePatients = [
    { id: '1', first_name: 'John', last_name: 'Mwangi', primary_mrn: 'MRN-2026-0081', gender: 'Male', age: 38, blood_group: 'O+', status: 'in-progress', clinical_state: 'critical', bill_status: 'unpaid' },
    { id: '2', first_name: 'Asha', last_name: 'Juma', primary_mrn: 'MRN-2026-0082', gender: 'Female', age: 29, blood_group: 'A+', status: 'waiting', clinical_state: 'attention', bill_status: 'paid' },
    { id: '3', first_name: 'Baraka', last_name: 'Mwamba', primary_mrn: 'MRN-2026-0083', gender: 'Male', age: 45, blood_group: 'B+', status: 'completed', clinical_state: 'normal', bill_status: 'paid' },
    { id: '4', first_name: 'Zulfa', last_name: 'Khamis', primary_mrn: 'MRN-2026-0084', gender: 'Female', age: 52, blood_group: 'AB-', status: 'waiting', clinical_state: 'pending', bill_status: 'partially-paid' },
];

const selectedPatient = ref(samplePatients[0]);

const selectPatient = (patient) => {
    selectedPatient.value = patient;
    showContext.value = true;
};
</script>

<template>
    <Head title="AfyaNova V3 — Design Foundation System" />

    <AfyaShell active-module="dashboard">
        <AfyaWorkspace :show-sidebar="true" :show-context="showContext">
            <!-- 1. LEFT SIDEBAR (3-State Adaptive Navigation) -->
            <template #sidebar="{ state, width, cycle, setState }">
                <AfyaSidebar
                    title="Design Foundation"
                    :icon="Palette"
                    :state="state"
                    :width="width"
                    @cycle-state="cycle"
                    @set-state="setState"
                >
                    <div v-if="state !== 'collapsed'" class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Design Primitives
                    </div>
                    <AfyaSidebarItem
                        label="System Overview"
                        :icon="Layers"
                        :active="activeSection === 'overview'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'overview'"
                    />
                    <AfyaSidebarItem
                        label="Semantic Statuses"
                        :icon="Tag"
                        badge="Unified"
                        :active="activeSection === 'statuses'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'statuses'"
                    />
                    <AfyaSidebarItem
                        label="Forms & Buttons"
                        :icon="Sliders"
                        :active="activeSection === 'forms'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'forms'"
                    />
                    <AfyaSidebarItem
                        label="Data Density Tables"
                        :icon="Table2"
                        :active="activeSection === 'tables'"
                        :collapsed="state === 'collapsed'"
                        @click="activeSection = 'tables'"
                    />
                </AfyaSidebar>
            </template>

            <!-- 2. CENTER WORK AREA (Adaptive / Flexible / Full Width) -->
            <template #default>
                <AfyaWorkspaceMain
                    title="AfyaNova V3 Design Foundation"
                    subtitle="Enterprise Hospital Workstation Specification"
                    :breadcrumbs="[
                        { label: 'Foundation', href: '#' },
                        { label: activeSection.toUpperCase(), active: true }
                    ]"
                >
                    <template #actions>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="showContext = !showContext"
                        >
                            {{ showContext ? 'Hide Context Panel' : 'Show Context Panel' }}
                        </Button>
                    </template>

                    <div class="w-full space-y-4">
                        <!-- Critical Clinical Alert Example -->
                        <AfyaClinicalAlert
                            severity="critical"
                            title="Active High-Risk Alert"
                            message="Patient John Mwangi has recorded severe anaphylactic Penicillin allergy and pending blood cross-match."
                        />

                        <!-- Filter Toolbar -->
                        <AfyaFilterBar
                            v-model:search-model="searchFilter"
                            search-placeholder="Filter hospital records by name, MRN, or status..."
                        >
                            <Button variant="outline" size="sm">Export CSV</Button>
                            <Button variant="default" size="sm">+ New Record</Button>
                        </AfyaFilterBar>

                        <!-- Section: Data Table Demonstration -->
                        <AfyaSection
                            title="High-Density Patient Records"
                            subtitle="Compact 36px table row height with accessible status pills"
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-12">#</TableHead>
                                        <TableHead>Patient Demographics</TableHead>
                                        <TableHead>Flow Status</TableHead>
                                        <TableHead>Clinical State</TableHead>
                                        <TableHead>Billing State</TableHead>
                                        <TableHead class="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(pat, index) in samplePatients"
                                        :key="pat.id"
                                        :selected="selectedPatient?.id === pat.id"
                                        class="cursor-pointer"
                                        @click="selectPatient(pat)"
                                    >
                                        <TableCell class="font-mono text-muted-foreground">{{ index + 1 }}</TableCell>
                                        <TableCell>
                                            <div class="font-bold text-foreground">{{ pat.first_name }} {{ pat.last_name }}</div>
                                            <div class="text-[11px] font-mono text-muted-foreground">{{ pat.primary_mrn }} · {{ pat.age }}y / {{ pat.gender }}</div>
                                        </TableCell>
                                        <TableCell>
                                            <AfyaStatusBadge :status="pat.status" dot />
                                        </TableCell>
                                        <TableCell>
                                            <AfyaStatusBadge :status="pat.clinical_state" />
                                        </TableCell>
                                        <TableCell>
                                            <AfyaStatusBadge :status="pat.bill_status" />
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Button
                                                variant="subtle"
                                                size="sm"
                                                @click.stop="selectPatient(pat)"
                                            >
                                                Inspect
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </AfyaSection>

                        <!-- Section: Design Primitives Matrix -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                            <!-- Card 1: Buttons & Inputs -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Compact Controls & Sizing</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div class="flex flex-wrap gap-2">
                                        <Button variant="default" size="sm">Primary Action</Button>
                                        <Button variant="secondary" size="sm">Secondary</Button>
                                        <Button variant="outline" size="sm">Outline</Button>
                                        <Button variant="destructive" size="sm">Destructive</Button>
                                        <Button variant="default" size="sm" loading>Processing</Button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <Input placeholder="Compact text input" />
                                        <Input placeholder="Invalid field state" :error="true" />
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Card 2: Semantic Badges Matrix -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Standardized Hospital Badges</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-2">
                                    <div class="flex flex-wrap gap-1.5">
                                        <AfyaStatusBadge status="paid" dot />
                                        <AfyaStatusBadge status="partially-paid" dot />
                                        <AfyaStatusBadge status="unpaid" dot />
                                        <AfyaStatusBadge status="overdue" dot />
                                        <AfyaStatusBadge status="refunded" />
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <AfyaStatusBadge status="normal" />
                                        <AfyaStatusBadge status="attention" />
                                        <AfyaStatusBadge status="critical" dot />
                                        <AfyaStatusBadge status="pending" />
                                        <AfyaStatusBadge status="ordered" />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </AfyaWorkspaceMain>
            </template>

            <!-- 3. RIGHT CONTEXT PANEL (Contextual Demographics & Records) -->
            <template #context="{ width, close }">
                <AfyaContextPanel
                    title="Patient Context Inspector"
                    :icon="ClipboardList"
                    :width="width"
                    @close="close"
                >
                    <div v-if="selectedPatient" class="space-y-3">
                        <!-- Patient Identity Component -->
                        <AfyaPatientIdentity :patient="selectedPatient">
                            <AfyaStatusBadge :status="selectedPatient.status" />
                        </AfyaPatientIdentity>

                        <!-- Clinical Alerts Card -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-rose-800 flex items-center gap-1.5">
                                    <AlertTriangle class="w-3.5 h-3.5 text-rose-600" />
                                    <span>Critical Alerts</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="text-[11px] space-y-1.5">
                                <div class="bg-rose-50/70 p-2 rounded border border-rose-200 text-rose-900 font-medium">
                                    Severe Penicillin Allergy (Skin Rash, Anaphylaxis risk)
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Baseline Vitals Card -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Baseline Vitals</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-2 gap-1.5 text-xs">
                                    <div class="bg-muted/50 p-1.5 rounded">
                                        <span class="text-[10px] text-muted-foreground block">BP</span>
                                        <span class="font-bold text-foreground">120/80 mmHg</span>
                                    </div>
                                    <div class="bg-muted/50 p-1.5 rounded">
                                        <span class="text-[10px] text-muted-foreground block">Pulse</span>
                                        <span class="font-bold text-foreground">74 bpm</span>
                                    </div>
                                    <div class="bg-muted/50 p-1.5 rounded">
                                        <span class="text-[10px] text-muted-foreground block">Temp</span>
                                        <span class="font-bold text-foreground">36.8 °C</span>
                                    </div>
                                    <div class="bg-muted/50 p-1.5 rounded">
                                        <span class="text-[10px] text-muted-foreground block">Blood Group</span>
                                        <span class="font-bold text-rose-700">{{ selectedPatient.blood_group }}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </AfyaContextPanel>
            </template>
        </AfyaWorkspace>
    </AfyaShell>
</template>
