<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { ShieldAlert, AlertTriangle, Lock, ArrowRight, Loader2, Info } from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    patientId: {
        type: String,
        default: '',
    },
    patientName: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    patient_id: props.patientId,
    justification: '',
});

// Update patient_id if prop changes
watch(() => props.patientId, (patientId) => {
    if (patientId) {
        form.patient_id = patientId;
    }
});

const isJustificationValid = computed(() => form.justification.trim().length >= 20);

const submit = () => {
    if (!form.patient_id && props.patientId) {
        form.patient_id = props.patientId;
    }

    form.post(route('clinical.break-glass.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
};

const handleClose = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="handleClose">
        <div class="p-6 space-y-5 bg-card text-foreground">
            <!-- Header with emergency badge -->
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-destructive/10 border border-destructive/20 flex items-center justify-center shrink-0">
                    <ShieldAlert class="w-5 h-5 text-destructive" />
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-foreground">
                            Emergency Break-Glass Access
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-destructive/15 text-destructive">
                            Audited Override
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Override facility boundary to access out-of-scope clinical records in a life-threatening or urgent medical emergency.
                    </p>
                </div>
            </div>

            <!-- Mandatory Audit Warning -->
            <div class="p-3.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs space-y-2 text-amber-800 dark:text-amber-300">
                <div class="flex items-center gap-2 font-semibold">
                    <AlertTriangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                    <span>Permanent Audit Trail Notice</span>
                </div>
                <p class="text-[11px] leading-relaxed opacity-90">
                    Activating break-glass is permanently recorded in the cryptographic audit trail with your user identifier, timestamp, IP address, and clinical justification. This override is valid for <strong>5 minutes</strong> and will be reviewed during clinical governance audits.
                </p>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-4">
                <!-- Patient Info Display or Input -->
                <div v-if="patientName" class="p-2.5 bg-muted/40 rounded-lg border border-border flex items-center justify-between text-xs">
                    <span class="text-muted-foreground font-medium">Target Patient:</span>
                    <span class="font-bold text-foreground">{{ patientName }}</span>
                </div>
                <div v-else class="space-y-1.5">
                    <label class="text-xs font-semibold text-foreground">
                        Patient UUID <span class="text-destructive">*</span>
                    </label>
                    <input
                        v-model="form.patient_id"
                        type="text"
                        placeholder="e.g. 01952a12-..."
                        required
                        class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-primary focus:outline-hidden"
                    />
                    <InputError :message="form.errors.patient_id" />
                </div>

                <!-- Mandatory Clinical Justification -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold text-foreground">
                            Clinical Emergency Justification <span class="text-destructive">*</span>
                        </label>
                        <span
                            class="text-[10px] font-mono"
                            :class="form.justification.trim().length >= 20 ? 'text-emerald-500' : 'text-muted-foreground'"
                        >
                            {{ form.justification.trim().length }}/20 min chars
                        </span>
                    </div>
                    <textarea
                        v-model="form.justification"
                        rows="3"
                        required
                        placeholder="State clinical reason (e.g. Unconscious trauma patient arriving from Regional Facility without active referral...)"
                        class="w-full p-3 rounded-lg border border-border bg-background text-xs focus:ring-2 focus:ring-primary focus:outline-hidden placeholder:text-muted-foreground"
                    ></textarea>
                    <InputError :message="form.errors.justification" />
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-border/60">
                    <button
                        type="button"
                        @click="handleClose"
                        class="px-4 py-2 rounded-lg text-xs font-semibold border border-border bg-background hover:bg-muted text-muted-foreground hover:text-foreground transition"
                        :disabled="form.processing"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || !isJustificationValid"
                        class="px-4 py-2 rounded-lg text-xs font-bold bg-destructive hover:bg-destructive/90 text-white flex items-center gap-1.5 shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ form.processing ? 'Authorizing…' : 'Activate Emergency Access' }}</span>
                        <ArrowRight v-if="!form.processing" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
