<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowUpRight, Save, Loader2, CheckCircle2, AlertTriangle, X, Building2, Truck } from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';
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
    existingReferrals: {
        type: Array,
        default: () => [],
    },
});

const successMessage = ref('');
const errorMessage = ref('');

const form = useForm({
    urgency: 'Routine',
    specialty_required: 'General Surgery',
    external_facility_name: '',
    clinical_summary: '',
    investigations_performed: '',
    treatments_given: '',
    reason_for_referral: '',
    transport_mode: 'Private Transport',
});

const submit = () => {
    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        successMessage.value = '✓ Referral drafted.';
        setTimeout(() => { successMessage.value = ''; }, 3500);
        return;
    }

    form.post(route('clinical.referral.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.urgency = 'Routine';
            form.specialty_required = 'General Surgery';
            form.transport_mode = 'Private Transport';
            successMessage.value = '✓ Clinical referral dispatched & registered.';
            setTimeout(() => { successMessage.value = ''; }, 4500);
        },
        onError: (errs) => {
            errorMessage.value = errs.referral || Object.values(errs)[0] || 'Failed to dispatch referral.';
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
                <ArrowUpRight class="w-3.5 h-3.5 text-primary" />
                <span>Inter-Facility & Specialist Clinical Referral Transfer</span>
            </h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/10 text-blue-600 border border-blue-500/30">
                Tertiary & Zonal Network
            </span>
        </div>

        <div v-if="successMessage" class="p-2 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                <span>{{ successMessage }}</span>
            </div>
            <button @click="successMessage = ''" class="text-emerald-700 hover:text-emerald-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <div v-if="errorMessage" class="p-2 rounded-md bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 text-xs font-semibold flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 flex-shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>
            <button @click="errorMessage = ''" class="text-rose-700 hover:text-rose-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Urgency Level *</label>
                    <select v-model="form.urgency" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                        <option value="Routine">Routine (Elective / Outpatient)</option>
                        <option value="Urgent">Urgent (< 24 Hours)</option>
                        <option value="Emergency">Emergency (Immediate Transfer)</option>
                    </select>
                    <InputError :message="form.errors.urgency" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Specialty Required *</label>
                    <Input v-model="form.specialty_required" placeholder="e.g. Neurosurgery, Cardiology, Oncology" class="h-8 text-xs" />
                    <InputError :message="form.errors.specialty_required" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Destination Facility *</label>
                    <Input v-model="form.external_facility_name" placeholder="e.g. Muhimbili National Hospital / Bugando" class="h-8 text-xs" />
                    <InputError :message="form.errors.external_facility_name" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Clinical Summary *</label>
                    <textarea v-model="form.clinical_summary" rows="2" placeholder="Presenting complaint, clinical examination findings, course of illness..." class="w-full text-xs rounded border border-border bg-card p-2 text-foreground focus:ring-1 focus:ring-primary"></textarea>
                    <InputError :message="form.errors.clinical_summary" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Reason for Referral *</label>
                    <textarea v-model="form.reason_for_referral" rows="2" placeholder="Specific specialist consultation needed, ICU care, MRI/CT, tertiary intervention..." class="w-full text-xs rounded border border-border bg-card p-2 text-foreground focus:ring-1 focus:ring-primary"></textarea>
                    <InputError :message="form.errors.reason_for_referral" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Investigations Done</label>
                    <Input v-model="form.investigations_performed" placeholder="e.g. FBP, CXR, Abdominal US" class="h-8 text-xs" />
                    <InputError :message="form.errors.investigations_performed" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Treatments Given</label>
                    <Input v-model="form.treatments_given" placeholder="e.g. IV Ceftriaxone 1g, Ringers Lactate 1L" class="h-8 text-xs" />
                    <InputError :message="form.errors.treatments_given" class="mt-1" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-foreground mb-1">Mode of Transport</label>
                    <select v-model="form.transport_mode" class="w-full h-8 text-xs rounded border border-border bg-card px-2">
                        <option value="Private Transport">Patient / Family Private Transport</option>
                        <option value="Hospital Ambulance">Hospital Ambulance</option>
                        <option value="Emergency Medical Evacuation">Emergency Medical Evacuation (Aero/STAT)</option>
                    </select>
                    <InputError :message="form.errors.transport_mode" class="mt-1" />
                </div>
            </div>

            <div class="flex justify-end pt-1">
                <Button type="submit" variant="default" size="sm" :disabled="form.processing" class="h-8 px-4 gap-1 shadow-2xs font-semibold">
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin mr-1" />
                    <Save v-else class="w-3.5 h-3.5" />
                    <span>{{ form.processing ? 'Dispatching...' : 'Dispatch Clinical Referral' }}</span>
                </Button>
            </div>
        </form>

        <!-- Existing Referrals History -->
        <div v-if="existingReferrals && existingReferrals.length > 0" class="pt-3 border-t border-border/60">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1.5">
                <ArrowUpRight class="w-3 h-3 text-primary" />
                <span>Active & Historical Referrals ({{ existingReferrals.length }})</span>
            </h4>
            <div class="border border-border/60 rounded-lg overflow-hidden shadow-2xs">
                <Table class="text-xs">
                    <TableHeader>
                        <TableRow class="h-7 text-[10px] uppercase font-bold bg-muted/20">
                            <TableHead class="py-1 px-3">Date</TableHead>
                            <TableHead class="py-1 px-3">Referral #</TableHead>
                            <TableHead class="py-1 px-3">Urgency</TableHead>
                            <TableHead class="py-1 px-3">Destination / Specialty</TableHead>
                            <TableHead class="py-1 px-3">Reason</TableHead>
                            <TableHead class="py-1 px-3">Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="refItem in existingReferrals" :key="refItem.id" class="h-8 border-b border-border/30">
                            <TableCell class="py-1 px-3 font-mono text-[10px] text-muted-foreground">{{ formatDate(refItem.created_at) }}</TableCell>
                            <TableCell class="py-1 px-3 font-mono font-bold text-primary">{{ refItem.referral_number || 'REF-TXZ' }}</TableCell>
                            <TableCell class="py-1 px-3">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                    :class="{
                                        'bg-rose-500/10 text-rose-600': refItem.urgency === 'Emergency',
                                        'bg-amber-500/10 text-amber-600': refItem.urgency === 'Urgent',
                                        'bg-blue-500/10 text-blue-600': refItem.urgency === 'Routine',
                                    }"
                                >
                                    {{ refItem.urgency }}
                                </span>
                            </TableCell>
                            <TableCell class="py-1 px-3 font-bold text-foreground">
                                {{ refItem.external_facility_name || refItem.to_facility?.name || 'Tertiary Center' }}
                                <span class="text-muted-foreground font-normal text-[11px]"> · {{ refItem.specialty_required }}</span>
                            </TableCell>
                            <TableCell class="py-1 px-3 text-muted-foreground text-[11px] truncate max-w-[200px]">{{ refItem.reason_for_referral }}</TableCell>
                            <TableCell class="py-1 px-3 font-semibold text-[10px]">{{ refItem.status || 'Pending' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
