<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import {
    GitMerge,
    AlertTriangle,
    CheckCircle2,
    ArrowRight,
    Loader2,
    ShieldAlert,
    User,
    Calendar,
    Phone,
    CreditCard,
    Search,
    X
} from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    primaryPatient: {
        type: Object,
        default: null,
    },
    availablePatients: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const selectedCandidate = ref(null);
const searchQuery = ref('');
const winnerChoice = ref('primary'); // 'primary' or 'candidate'

const form = useForm({
    winner_id: '',
    loser_id: '',
    justification_reason: '',
});

watch(() => props.primaryPatient, (patient) => {
    if (patient) {
        updateFormIds();
    }
}, { immediate: true });

watch(winnerChoice, () => {
    updateFormIds();
});

watch(selectedCandidate, () => {
    updateFormIds();
});

function updateFormIds() {
    if (!props.primaryPatient || !selectedCandidate.value) {
        form.winner_id = props.primaryPatient ? props.primaryPatient.id : '';
        form.loser_id = '';
        return;
    }

    if (winnerChoice.value === 'primary') {
        form.winner_id = props.primaryPatient.id;
        form.loser_id = selectedCandidate.value.id;
    } else {
        form.winner_id = selectedCandidate.value.id;
        form.loser_id = props.primaryPatient.id;
    }
}

const filteredCandidates = computed(() => {
    if (!props.availablePatients) return [];
    const query = searchQuery.value.toLowerCase().trim();
    return props.availablePatients.filter((p) => {
        if (props.primaryPatient && p.id === props.primaryPatient.id) return false;
        if (p.status === 'Merged' || p.status === 'Deceased') return false;
        if (!query) return true;
        const name = `${p.first_name || ''} ${p.last_name || ''}`.toLowerCase();
        const mrn = (p.primary_mrn || '').toLowerCase();
        const phone = (p.phone_number || p.contacts?.[0]?.value || '').toLowerCase();
        return name.includes(query) || mrn.includes(query) || phone.includes(query);
    });
});

const winnerPatient = computed(() => {
    return winnerChoice.value === 'primary' ? props.primaryPatient : selectedCandidate.value;
});

const loserPatient = computed(() => {
    return winnerChoice.value === 'primary' ? selectedCandidate.value : props.primaryPatient;
});

const isFormValid = computed(() => {
    return form.winner_id && form.loser_id && form.winner_id !== form.loser_id && form.justification_reason.trim().length >= 10;
});

const selectPatient = (patient) => {
    selectedCandidate.value = patient;
    updateFormIds();
};

const clearSelection = () => {
    selectedCandidate.value = null;
    updateFormIds();
};

const submit = () => {
    if (!isFormValid.value) return;

    form.post(route('patients.merge'), {
        preserveScroll: true,
        onSuccess: () => {
            handleClose();
        },
    });
};

const handleClose = () => {
    form.reset();
    form.clearErrors();
    selectedCandidate.value = null;
    searchQuery.value = '';
    winnerChoice.value = 'primary';
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="handleClose">
        <div class="p-6 bg-white dark:bg-slate-900 rounded-xl shadow-xl">
            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                <div class="flex items-center space-x-3">
                    <div class="p-2.5 bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl border border-amber-500/20">
                        <GitMerge class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            Merge Duplicate Patient Records
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Permanently consolidate duplicate patient charts, encounters, diagnoses, prescriptions, and billing ledger entries.
                        </p>
                    </div>
                </div>
                <button
                    @click="handleClose"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Form Body -->
            <form @submit.prevent="submit" class="mt-5 space-y-6">
                <!-- Warning Banner -->
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/50 flex items-start gap-3">
                    <AlertTriangle class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div class="text-xs text-amber-900 dark:text-amber-200/90 space-y-1">
                        <span class="font-bold">Irreversible Clinical Audit Action:</span>
                        <p>
                            The duplicate record will be deactivated and marked as <code class="px-1 py-0.5 bg-amber-200/60 dark:bg-amber-900/80 rounded font-mono font-bold">Merged</code>.
                            All historical encounters, clinical SOAP notes, vitals, allergies, lab orders, prescriptions, and invoices will be permanently re-linked to the winning primary record with an immutable audit trail.
                        </p>
                    </div>
                </div>

                <!-- Step 1: Candidate Selection (if not selected) -->
                <div v-if="!selectedCandidate" class="space-y-3">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                        1. Select Duplicate Patient Record to Merge
                    </label>
                    <div class="relative">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search duplicate by MRN, First/Last Name, or Phone..."
                            class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-hidden transition"
                        />
                    </div>

                    <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="cand in filteredCandidates.slice(0, 15)"
                            :key="cand.id"
                            @click="selectPatient(cand)"
                            class="p-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer transition"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                                    {{ cand.first_name?.[0] }}{{ cand.last_name?.[0] }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                        {{ cand.first_name }} {{ cand.last_name }}
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-mono">
                                            {{ cand.primary_mrn }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-3 mt-0.5">
                                        <span>DOB: {{ cand.dob || 'Unknown' }}</span>
                                        <span>Gender: {{ cand.gender }}</span>
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/50 dark:text-amber-300 dark:hover:bg-amber-900/60 transition"
                            >
                                Select Duplicate
                            </button>
                        </div>
                        <div v-if="filteredCandidates.length === 0" class="p-6 text-center text-xs text-slate-400">
                            No matching patient records found.
                        </div>
                    </div>
                </div>

                <!-- Step 2: Side-by-Side Comparison & Winner Selection -->
                <div v-else class="space-y-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            2. Review & Designate Canonical (Surviving) Record
                        </label>
                        <button
                            type="button"
                            @click="clearSelection"
                            class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 underline"
                        >
                            Change Selected Duplicate
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Left Card: Primary Patient Record -->
                        <div
                            @click="winnerChoice = 'primary'"
                            :class="[
                                'p-4 rounded-xl border-2 cursor-pointer transition relative',
                                winnerChoice === 'primary'
                                    ? 'border-emerald-500 bg-emerald-50/20 dark:bg-emerald-950/20 shadow-sm'
                                    : 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 opacity-70 hover:opacity-100'
                            ]"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                                    :class="winnerChoice === 'primary' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'">
                                    {{ winnerChoice === 'primary' ? '★ Canonical Surviving Record' : 'Duplicate to be Merged' }}
                                </span>
                                <input
                                    type="radio"
                                    name="winner"
                                    value="primary"
                                    v-model="winnerChoice"
                                    class="text-emerald-600 focus:ring-emerald-500"
                                />
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ primaryPatient?.first_name }} {{ primaryPatient?.last_name }}
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">MRN:</span>
                                    <span class="font-mono font-semibold">{{ primaryPatient?.primary_mrn }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">DOB / Age:</span>
                                    <span>{{ primaryPatient?.dob || 'N/A' }} ({{ primaryPatient?.gender }})</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">Blood Group:</span>
                                    <span>{{ primaryPatient?.blood_group || 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">Encounters:</span>
                                    <span>{{ primaryPatient?.encounters?.length || 0 }} visits</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Card: Candidate Patient Record -->
                        <div
                            @click="winnerChoice = 'candidate'"
                            :class="[
                                'p-4 rounded-xl border-2 cursor-pointer transition relative',
                                winnerChoice === 'candidate'
                                    ? 'border-emerald-500 bg-emerald-50/20 dark:bg-emerald-950/20 shadow-sm'
                                    : 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 opacity-70 hover:opacity-100'
                            ]"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                                    :class="winnerChoice === 'candidate' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'">
                                    {{ winnerChoice === 'candidate' ? '★ Canonical Surviving Record' : 'Duplicate to be Merged' }}
                                </span>
                                <input
                                    type="radio"
                                    name="winner"
                                    value="candidate"
                                    v-model="winnerChoice"
                                    class="text-emerald-600 focus:ring-emerald-500"
                                />
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ selectedCandidate?.first_name }} {{ selectedCandidate?.last_name }}
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">MRN:</span>
                                    <span class="font-mono font-semibold">{{ selectedCandidate?.primary_mrn }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">DOB / Age:</span>
                                    <span>{{ selectedCandidate?.dob || 'N/A' }} ({{ selectedCandidate?.gender }})</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">Blood Group:</span>
                                    <span>{{ selectedCandidate?.blood_group || 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">Encounters:</span>
                                    <span>{{ selectedCandidate?.encounters?.length || 0 }} visits</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Justification Reason -->
                    <div class="space-y-2 pt-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            3. Clinical & Legal Justification Reason <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="form.justification_reason"
                            rows="2"
                            placeholder="State the reason for merge (e.g. Duplicate registration created during emergency intake; verified by NIDA/phone)..."
                            class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-hidden transition"
                        ></textarea>
                        <InputError :message="form.errors.justification_reason || form.errors.merge" />
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
                    <button
                        type="button"
                        @click="handleClose"
                        class="px-4 py-2.5 text-xs font-semibold rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    >
                        Cancel
                    </button>

                    <button
                        v-if="selectedCandidate"
                        type="submit"
                        :disabled="!isFormValid || form.processing"
                        class="px-5 py-2.5 text-xs font-bold rounded-xl text-white bg-linear-to-r from-amber-600 to-amber-700 hover:from-amber-500 hover:to-amber-600 shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition"
                    >
                        <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <GitMerge v-else class="w-4 h-4" />
                        <span>Confirm & Execute Merge</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
