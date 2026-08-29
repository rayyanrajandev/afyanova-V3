<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Film, Save, Loader2, CheckCircle2, AlertTriangle, X, ShieldAlert, Sparkles } from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import InputError from '@/Components/InputError.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableCell from '@/Components/ui/TableCell.vue';

const props = defineProps({
    encounterId: {
        type: String,
        default: 'demo',
    },
    existingOrders: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const successMessage = ref('');
const errorMessage = ref('');

const standardProcedures = {
    'X-Ray': [
        { name: 'Chest X-Ray (PA & Lateral)', site: 'Thorax' },
        { name: 'Abdominal X-Ray (Erect & Supine)', site: 'Abdomen' },
        { name: 'Pelvis X-Ray (AP)', site: 'Pelvis' },
        { name: 'Cervical Spine X-Ray (AP & Lateral)', site: 'C-Spine' },
        { name: 'Lumbosacral Spine X-Ray', site: 'L-Spine' },
        { name: 'Extremity / Long Bone X-Ray', site: 'Limb' },
    ],
    'Ultrasound': [
        { name: 'Obstetric / Fetal Viability & Growth Ultrasound', site: 'Pelvis' },
        { name: 'Abdominal Ultrasound (Complete)', site: 'Abdomen' },
        { name: 'Pelvic & Transvaginal Ultrasound', site: 'Pelvis' },
        { name: 'Kidney, Ureter, Bladder (KUB) Ultrasound', site: 'Renal' },
        { name: 'Thyroid & Neck Ultrasound', site: 'Neck' },
        { name: 'Deep Venous Thrombosis (DVT) Doppler', site: 'Lower Limbs' },
    ],
    'CT Scan': [
        { name: 'CT Brain / Head (Non-Contrast)', site: 'Head' },
        { name: 'CT Brain (Contrast Enhanced)', site: 'Head' },
        { name: 'CT Chest (High Resolution HRCT)', site: 'Thorax' },
        { name: 'CT Abdomen & Pelvis (Triple Phase Contrast)', site: 'Abdomen' },
        { name: 'CT Pulmonary Angiogram (CTPA)', site: 'Chest / Vascular' },
    ],
    'MRI': [
        { name: 'MRI Brain & Cranial Nerves', site: 'Head' },
        { name: 'MRI Lumbar Spine with Contrast', site: 'Spine' },
        { name: 'MRI Knee / Musculoskeletal Joint', site: 'Joint' },
    ],
    'Echo': [
        { name: 'Transthoracic Echocardiogram (2D Echo + Doppler)', site: 'Heart' },
        { name: 'Pediatric Congenital Echocardiogram', site: 'Heart' },
    ],
};

const form = useForm({
    modality: 'X-Ray',
    procedure_name: 'Chest X-Ray (PA & Lateral)',
    body_site: 'Thorax',
    clinical_indication: '',
    priority: 'Routine',
});

const onModalityChange = () => {
    const list = standardProcedures[form.modality];
    if (list && list.length > 0) {
        form.procedure_name = list[0].name;
        form.body_site = list[0].site;
    }
};

const onProcedureSelect = (e) => {
    const item = standardProcedures[form.modality]?.find(p => p.name === e.target.value);
    if (item) {
        form.body_site = item.site;
    }
};

const submit = () => {
    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        successMessage.value = '✓ Imaging order drafted.';
        setTimeout(() => { successMessage.value = ''; }, 3500);
        return;
    }

    form.post(route('radiology.orders.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.modality = 'X-Ray';
            form.procedure_name = 'Chest X-Ray (PA & Lateral)';
            form.body_site = 'Thorax';
            form.priority = 'Routine';
            successMessage.value = '✓ Radiology requisition transmitted to PACS/RIS worklist.';
            setTimeout(() => { successMessage.value = ''; }, 4500);
        },
        onError: (errs) => {
            errorMessage.value = errs.order || Object.values(errs)[0] || 'Failed to place imaging order.';
            setTimeout(() => { errorMessage.value = ''; }, 5000);
        },
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-border pb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                <Film class="w-3.5 h-3.5 text-primary" />
                <span>Diagnostic Imaging & Radiology Requisition (PACS / DICOM)</span>
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-violet-500/10 text-violet-600 border border-violet-500/30 flex items-center gap-1">
                <Sparkles class="w-3 h-3" />
                <span>Integrated DICOM Worklist</span>
            </span>
        </div>

        <div v-if="successMessage" class="p-2 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span>{{ successMessage }}</span>
            </div>
            <button @click="successMessage = ''" class="text-emerald-700 hover:text-emerald-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <div v-if="errorMessage" class="p-2 rounded-md bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>
            <button @click="errorMessage = ''" class="text-rose-700 hover:text-rose-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Modality *</label>
                    <Select v-model="form.modality" @change="onModalityChange" class="w-full">
                        <option value="X-Ray">X-Ray (Plain Radiography)</option>
                        <option value="Ultrasound">Ultrasound (US / Doppler)</option>
                        <option value="CT Scan">Computed Tomography (CT)</option>
                        <option value="MRI">Magnetic Resonance Imaging (MRI)</option>
                        <option value="Echo">Echocardiography (Echo)</option>
                    </Select>
                    <InputError :message="form.errors.modality" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Standard Study Preset</label>
                    <Select v-model="form.procedure_name" @change="onProcedureSelect" class="w-full">
                        <option v-for="p in standardProcedures[form.modality] || []" :key="p.name" :value="p.name">{{ p.name }}</option>
                    </Select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Procedure Name *</label>
                    <Input v-model="form.procedure_name" placeholder="Specific Investigation Title" class="h-8 text-xs" />
                    <InputError :message="form.errors.procedure_name" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Priority *</label>
                    <Select v-model="form.priority" class="w-full font-bold"
                        :class="{
                            'text-rose-600': form.priority === 'STAT',
                            'text-amber-600': form.priority === 'Urgent',
                            'text-foreground': form.priority === 'Routine',
                        }"
                    >
                        <option value="Routine">Routine</option>
                        <option value="Urgent">Urgent (&lt; 4 Hours)</option>
                        <option value="STAT">STAT (Immediate Critical)</option>
                    </Select>
                    <InputError :message="form.errors.priority" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Anatomical Body Site</label>
                    <Input v-model="form.body_site" placeholder="e.g. Chest, Left Knee, Pelvis" class="h-8 text-xs" />
                    <InputError :message="form.errors.body_site" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Clinical Indication / Diagnostic Question *</label>
                    <Input v-model="form.clinical_indication" placeholder="e.g. Rule out consolidation, acute abdominal pain, R/O fracture" class="h-8 text-xs" />
                    <InputError :message="form.errors.clinical_indication" class="mt-1" />
                </div>
            </div>

            <div class="flex justify-end pt-1">
                <Button v-if="can.orderImaging" type="submit" variant="default" size="sm" :disabled="form.processing" class="h-8 px-4 gap-1 shadow-2xs font-semibold">
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                    <Save v-else class="w-3.5 h-3.5" />
                    <span>{{ form.processing ? 'Transmitting...' : 'Send Imaging Requisition' }}</span>
                </Button>
            </div>
        </form>

        <!-- Existing Orders History -->
        <div v-if="existingOrders && existingOrders.length > 0" class="pt-3 border-t border-border/60">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1.5">
                <Film class="w-3 h-3 text-primary" />
                <span>Imaging Orders for Encounter ({{ existingOrders.length }})</span>
            </h4>
            <div class="border border-border/60 rounded-lg overflow-hidden shadow-2xs">
                <Table class="text-xs">
                    <TableHeader>
                        <TableRow class="h-7 text-[10px] uppercase font-bold bg-muted/20">
                            <TableHead class="py-1 px-3">Date</TableHead>
                            <TableHead class="py-1 px-3">Order #</TableHead>
                            <TableHead class="py-1 px-3">Modality</TableHead>
                            <TableHead class="py-1 px-3">Procedure</TableHead>
                            <TableHead class="py-1 px-3">Priority</TableHead>
                            <TableHead class="py-1 px-3">Status</TableHead>
                            <TableHead class="py-1 px-3">Report Findings</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="ord in existingOrders" :key="ord.id" class="h-8 border-b border-border/30">
                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">{{ formatDate(ord.created_at) }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-bold text-primary">{{ ord.order_number }}</TableCell>
                            <TableCell class="py-1 px-3 font-semibold">{{ ord.modality }}</TableCell>
                            <TableCell class="py-1 px-3 font-bold text-foreground">{{ ord.procedure_name }}</TableCell>
                            <TableCell class="py-1 px-3">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                    :class="{
                                        'bg-rose-500/10 text-rose-600': ord.priority === 'STAT',
                                        'bg-amber-500/10 text-amber-600': ord.priority === 'Urgent',
                                        'bg-muted/40 text-foreground': ord.priority === 'Routine',
                                    }"
                                >
                                    {{ ord.priority }}
                                </span>
                            </TableCell>
                            <TableCell class="py-1 px-3 font-semibold text-[10px]">{{ ord.status }}</TableCell>
                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px] truncate max-w-[200px]">
                                {{ ord.reports?.[0]?.impression || ord.reports?.[0]?.findings || 'Pending acquisition & reporting' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
