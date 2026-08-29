<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { 
    FileText, 
    CheckCircle2, 
    Lock, 
    Edit3, 
    Save, 
    AlertTriangle, 
    X, 
    Loader2,
    Sparkles,
    Clock,
    UserCheck,
    Check,
    ChevronDown,
    ChevronUp,
    ShieldCheck,
    Stethoscope,
    Baby,
    HeartPulse,
    Bug,
    Info,
    RotateCcw,
    Trash2
} from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import AfyaStatusBadge from '@/Components/Afya/AfyaStatusBadge.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    encounterId: {
        type: String,
        default: 'demo',
    },
    existingNotes: {
        type: Array,
        default: () => [],
    },
    existingDiagnoses: {
        type: Array,
        default: () => [],
    },
    notes: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    note_type: 'SOAP',
    content: {
        subjective: '',
        objective: '',
        assessment: '',
        plan: ''
    }
});

const successMessage = ref('');
const errorMessage = ref('');
const selectedCategory = ref('All');
const templateSearch = ref('');
const showTemplatesModal = ref(false);

// Sign Modal State
const showSignModal = ref(false);
const signingNoteId = ref(null);
const isSigning = ref(false);

// Amend Modal State
const showAmendModal = ref(false);
const amendingNoteId = ref(null);
const amendReason = ref('');
const amendSubjective = ref('');
const isAmending = ref(false);

// Comprehensive Tanzania Clinical Fast-Fill Templates Suite (Aligned with Ministry of Health STGs)
const tanzaniaTemplates = [
    {
        name: 'Malaria (Uncomplicated - ALu)',
        category: 'Infectious & Tropical',
        icon: '🦟',
        subjective: 'Patient reports high-grade fever with chills, rigors, profuse sweating, arthralgia, and generalized malaise for 3 days. Denies vomiting everything, convulsions, or loss of consciousness.',
        objective: 'Febrile (T: 38.8°C). BP: 116/72 mmHg, HR: 94 bpm. Mild conjunctival pallor. Spleen not palpable. Chest clear bilaterally. mRDT: Positive (P. falciparum).',
        assessment: 'Acute Uncomplicated Plasmodium falciparum Malaria (ICD-10: B50.9).',
        plan: '1. Artemether-Lumefantrine (ALu / Coartem) 20/120mg: 4 tablets stat, then 4 tablets at 8h, then BD for 2 days (total 6 doses) with a fatty meal.\n2. Paracetamol 1g TDS for 3 days for fever/arthralgia.\n3. High oral fluid intake.\n4. Health education on insecticide-treated bed nets (ITNs).\n5. Return immediately if danger signs occur (dark urine, severe vomiting, lethargy).'
    },
    {
        name: 'Enteric / Typhoid Fever',
        category: 'Infectious & Tropical',
        icon: '🌡️',
        subjective: 'Patient presents with step-ladder worsening fever for 6 days, persistent dull frontal headache, dry cough, constipation followed by loose stools, and marked loss of appetite.',
        objective: 'Toxic appearance, febrile (T: 39.2°C), relative bradycardia (HR: 76 bpm). Coated tongue with red edges. Vague right lower quadrant abdominal tenderness, mild hepatomegaly. Chest clear.',
        assessment: 'Enteric Fever / Suspected Typhoid Infection (ICD-10: A01.0).',
        plan: '1. Order FBP (leukopenia check), Widal test / Blood culture, and Stool routine.\n2. Ciprofloxacin 500mg PO BD for 10 days (or Azithromycin 1g OD for 5 days).\n3. Paracetamol 1g TDS.\n4. Strict water sanitation, boiling drinking water, and safe food hygiene counseling.'
    },
    {
        name: 'Acute Gastroenteritis / Diarrhea',
        category: 'Gastrointestinal',
        icon: '🚰',
        subjective: 'Frequent watery loose stools (5-6x/day) for 2 days associated with nausea, diffuse colicky abdominal cramps, and mild vomiting. Denies blood or mucus in stool.',
        objective: 'Moderate dehydration signs: Dry oral mucosa, sunken eyes, skin turgor pinch goes back slowly (<2s). BP: 104/68 mmHg, HR: 88 bpm. Abdomen soft, hyperactive bowel sounds.',
        assessment: 'Acute Gastroenteritis with Moderate Dehydration (ICD-10: A09).',
        plan: '1. Oral Rehydration Salts (ORS): 500-1000ml after each loose stool until diarrhea stops.\n2. Zinc Sulfate 20mg OD for 10 days.\n3. Metronidazole 400mg TDS for 5 days.\n4. Ciprofloxacin 500mg BD for 3 days if bacterial diarrhea.\n5. Continue light feeding (porridge/soup) and abundant clean fluids.'
    },
    {
        name: 'Peptic Ulcer Disease (PUD) / Gastritis',
        category: 'Gastrointestinal',
        icon: '🔥',
        subjective: 'Burning epigastric pain worse on empty stomach and partially relieved by food or antacids for 2 weeks. Associated with heartburn, early satiety, and belching. No hematemesis or melena.',
        objective: 'Hemodynamically stable. BP: 122/76 mmHg. Mild localized epigastric tenderness on deep palpation. No guarding, rigidity, or rebound tenderness. Bowel sounds normal.',
        assessment: 'Peptic Ulcer Disease / Acute Gastritis (ICD-10: K27.9) — Rule out H. pylori.',
        plan: '1. Order H. pylori Stool Antigen Test.\n2. Omeprazole 20mg PO BD before meals for 14 days.\n3. Liquid Antacid (Magnesium/Aluminum Hydroxide) 10ml TDS postprandial.\n4. Dietary counseling: Avoid NSAIDs/Brufen, spicy foods, caffeine, alcohol, and smoking.'
    },
    {
        name: 'Urinary Tract Infection (UTI / Cystitis)',
        category: 'Infectious & Tropical',
        icon: '🚽',
        subjective: 'Burning pain on urination (dysuria), increased frequency, urgency, and lower suprapubic dull aching pain for 3 days. Denies high fever, chills, or loin/flank pain.',
        objective: 'Afebrile (T: 37.1°C). Suprapubic tenderness on palpation. Renal angles non-tender bilaterally. Urinalysis: Leukocytes +++, Nitrites Positive, RBCs 2-4/HPF.',
        assessment: 'Acute Uncomplicated Lower Urinary Tract Infection / Cystitis (ICD-10: N39.0).',
        plan: '1. Nitrofurantoin 100mg PO BD for 5 days (or Ciprofloxacin 500mg BD for 3 days).\n2. Paracetamol 1g PRN for pelvic discomfort.\n3. Encourage high fluid intake (>2.5 to 3 liters of water per day).\n4. Review if symptoms fail to resolve after 3 days.'
    },
    {
        name: 'Community-Acquired Pneumonia (CAP)',
        category: 'Respiratory',
        icon: '🫁',
        subjective: 'Productive cough with purulent yellowish sputum for 5 days, right-sided pleuritic chest pain on deep inspiration, fever, and mild breathlessness.',
        objective: 'Febrile (T: 38.5°C), RR: 24 breaths/min, SpO2: 95% on room air. Coarse inspiratory crepitations and bronchial breathing over right lower lung zone. Heart sounds normal.',
        assessment: 'Community-Acquired Pneumonia (CAP) - Moderate (ICD-10: J18.9).',
        plan: '1. Order Chest X-Ray and Full Blood Picture.\n2. Amoxicillin-Clavulanic Acid (Augmentin) 625mg PO TDS for 7 days + Azithromycin 500mg OD for 3 days.\n3. Paracetamol 1g TDS for fever & pleuritic pain.\n4. Salbutamol expectorant syrup 10ml TDS.\n5. Review in 48 hours or sooner if SpO2 drops or difficulty breathing worsens.'
    },
    {
        name: 'Antenatal Care (ANC Routine Visit)',
        category: 'Maternal & ANC',
        icon: '🤰',
        subjective: 'Pregnant mother (G2P1+0) at 20 weeks gestation by reliable LMP presenting for routine ANC profile. Reports good active fetal movements, denies vaginal bleeding, fluid leak, or dizziness.',
        objective: 'BP: 110/70 mmHg, Weight: 64kg. Conjunctival pallor: Nil. Fundal height matches 20 weeks (umbilicus level). FHR: 144 bpm, regular. Urinalysis: Protein negative, Sugar negative.',
        assessment: 'G2P1+0 at 20 weeks singleton active intrauterine pregnancy, uncomplicated (ICD-10: Z34.0).',
        plan: '1. IPTp-SP (Sulfadoxine-Pyrimethamine) 3 tablets stat under DOT (1st dose for malaria prevention).\n2. Ferrous Sulfate 200mg + Folic Acid 0.4mg OD throughout pregnancy.\n3. Mebendazole 500mg stat for routine deworming.\n4. Administer Td (Tetanus Diphtheria) vaccine.\n5. Educate on obstetric danger signs (severe headache, bleeding, visual blur).\n6. Next ANC visit in 4 weeks.'
    },
    {
        name: 'Diabetes Mellitus Type 2 (T2DM Review)',
        category: 'NCDs & Chronic',
        icon: '🩸',
        subjective: 'Known T2DM for 4 years on oral hypoglycemics. Presenting for quarterly routine glycemic control check. Compliant with diet & medication. No polyuria, polydipsia, or foot sores.',
        objective: 'BP: 126/78 mmHg, BMI: 27.2. Random Blood Glucose (RBG): 7.2 mmol/L. 10g monofilament foot sensation intact bilaterally. Peripheral pulses palpable. No pedal edema.',
        assessment: 'Type 2 Diabetes Mellitus — Fair Glycemic Control (ICD-10: E11.9).',
        plan: '1. Continue Metformin 500mg BD with meals + Glibenclamide 5mg OD morning.\n2. Order HbA1c and Renal Function Test (Creatinine/eGFR).\n3. Diabetic foot care education & low glycemic index diet counseling.\n4. Regular 30-minute daily walking exercise.\n5. Review in 3 months.'
    },
    {
        name: 'Essential Hypertension (Routine Review)',
        category: 'NCDs & Chronic',
        icon: '🫀',
        subjective: 'Patient on regular antihypertensive therapy for 2 years. Denies dizziness, morning occipital headaches, palpitations, chest pain, or visual blurriness. Good compliance.',
        objective: 'BP: 132/84 mmHg, HR: 72 bpm, regular. Heart sounds S1/S2 present, no gallop or murmurs. JVP not elevated. Chest clear. No pedal edema.',
        assessment: 'Primary Essential Hypertension — Controlled (ICD-10: I10).',
        plan: '1. Continue Amlodipine 5mg OD morning + Losartan 50mg OD.\n2. Annual check of serum electrolytes, creatinine, and lipid profile.\n3. Low sodium DASH diet, reduce cooking oil, aerobic physical exercise.\n4. Next BP check in 2 months.'
    },
    {
        name: 'Tinea Corporis / Fungal Skin Infection',
        category: 'Dermatology & Skin',
        icon: '🧴',
        subjective: 'Itchy circular erythematous skin rash on trunk and upper arms for 2 weeks. Aggravated by sweating and humid weather. No fever or joint pains.',
        objective: 'Annular erythematous scaly plaques with raised, active erythematous borders and central clearing (ringworm morphology). No secondary bacterial infection or impetigo.',
        assessment: 'Tinea Corporis / Superficial Dermatophytosis (ICD-10: B35.4).',
        plan: '1. Clotrimazole 1% Topical Cream: Apply thin layer BD for 14 days (continue 7 days after clearance).\n2. Ketoconazole 2% Medicated Soap for daily bathing.\n3. Cetirizine 10mg OD at bedtime for pruritus.\n4. Hygiene education: Keep skin dry, avoid sharing towels or clothing.'
    }
];

const templateCategories = computed(() => {
    return ['All', 'Infectious & Tropical', 'Gastrointestinal', 'Respiratory', 'Maternal & ANC', 'NCDs & Chronic', 'Dermatology & Skin'];
});

const filteredTemplates = computed(() => {
    return tanzaniaTemplates.filter(tpl => {
        const matchesCategory = selectedCategory.value === 'All' || tpl.category === selectedCategory.value;
        const matchesSearch = !templateSearch.value || 
            tpl.name.toLowerCase().includes(templateSearch.value.toLowerCase()) ||
            tpl.category.toLowerCase().includes(templateSearch.value.toLowerCase()) ||
            tpl.assessment.toLowerCase().includes(templateSearch.value.toLowerCase());
        return matchesCategory && matchesSearch;
    });
});

const isFormDirty = computed(() => {
    return Boolean(
        form.content.subjective?.trim() ||
        form.content.objective?.trim() ||
        form.content.assessment?.trim() ||
        form.content.plan?.trim()
    );
});

const clearForm = () => {
    form.content.subjective = '';
    form.content.objective = '';
    form.content.assessment = '';
    form.content.plan = '';
    successMessage.value = '✓ Form cleared. Ready for fresh clinical charting.';
    setTimeout(() => { successMessage.value = ''; }, 3000);
};

const clearField = (field) => {
    form.content[field] = '';
};

const applyTemplate = (tpl) => {
    form.content.subjective = tpl.subjective;
    form.content.objective = tpl.objective;
    form.content.assessment = tpl.assessment;
    form.content.plan = tpl.plan;
    showTemplatesModal.value = false;
    successMessage.value = `✓ Applied "${tpl.name}" clinical template.`;
    setTimeout(() => { successMessage.value = ''; }, 3500);
};

// Quick keyword macro chips
const insertMacro = (field, text) => {
    if (!form.content[field]) {
        form.content[field] = text;
    } else {
        form.content[field] += (form.content[field].endsWith('.') || form.content[field].endsWith(' ') ? ' ' : '. ') + text;
    }
};

const submit = () => {
    if (!form.content.subjective && !form.content.objective && !form.content.assessment && !form.content.plan) {
        errorMessage.value = 'Please enter clinical notes before saving.';
        setTimeout(() => { errorMessage.value = ''; }, 3500);
        return;
    }

    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        form.reset();
        successMessage.value = '✓ SOAP note draft saved.';
        setTimeout(() => { successMessage.value = ''; }, 3500);
        return;
    }

    form.post(route('clinical.notes.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            successMessage.value = '✓ SOAP note drafted and saved to consultation record.';
            setTimeout(() => { successMessage.value = ''; }, 4500);
        },
        onError: (errs) => {
            errorMessage.value = errs.note || 'Failed to save clinical note.';
            setTimeout(() => { errorMessage.value = ''; }, 5000);
        }
    });
};

// Structured Diagnosis Recording (separate from the free-text Assessment
// narrative above — this writes an actual Diagnosis row, which is what
// MTUHA morbidity reporting, insurance claim scrubbing, and FHIR Condition
// export all read from).
const diagnosisForm = useForm({
    icd_10_code: '',
    description: '',
    certainty: 'Confirmed',
    type: 'Primary',
});
const diagnosisError = ref('');

const submitDiagnosis = () => {
    if (!diagnosisForm.description.trim()) {
        diagnosisError.value = 'Enter a diagnosis description first.';
        setTimeout(() => { diagnosisError.value = ''; }, 3000);
        return;
    }

    if (props.encounterId === 'demo' || props.encounterId === 'new') {
        diagnosisError.value = 'Diagnosis can only be saved once the encounter has started.';
        setTimeout(() => { diagnosisError.value = ''; }, 3500);
        return;
    }

    diagnosisForm.post(route('clinical.diagnoses.store', props.encounterId), {
        preserveScroll: true,
        onSuccess: () => {
            diagnosisForm.reset('icd_10_code', 'description');
            successMessage.value = '✓ Diagnosis recorded to encounter.';
            setTimeout(() => { successMessage.value = ''; }, 3500);
        },
        onError: (errs) => {
            diagnosisError.value = errs.description || errs.icd_10_code || 'Failed to record diagnosis.';
            setTimeout(() => { diagnosisError.value = ''; }, 5000);
        }
    });
};

const openSignModal = (noteId) => {
    signingNoteId.value = noteId;
    showSignModal.value = true;
};

const closeSignModal = () => {
    showSignModal.value = false;
    signingNoteId.value = null;
};

const confirmSignNote = () => {
    if (!signingNoteId.value) return;
    isSigning.value = true;
    router.post(route('clinical.notes.sign', signingNoteId.value), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isSigning.value = false;
            closeSignModal();
            successMessage.value = '✓ Clinical note has been legally signed and sealed.';
            setTimeout(() => { successMessage.value = ''; }, 4000);
        },
        onFinish: () => {
            isSigning.value = false;
        }
    });
};

const openAmendModal = (noteId) => {
    amendingNoteId.value = noteId;
    amendReason.value = '';
    amendSubjective.value = '';
    showAmendModal.value = true;
};

const closeAmendModal = () => {
    showAmendModal.value = false;
    amendingNoteId.value = null;
};

const confirmAmendNote = () => {
    if (!amendingNoteId.value || !amendReason.value || !amendSubjective.value) return;
    isAmending.value = true;
    router.post(route('clinical.notes.amend', amendingNoteId.value), {
        amendment_reason: amendReason.value,
        content: { subjective: amendSubjective.value, objective: '', assessment: '', plan: '' }
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isAmending.value = false;
            closeAmendModal();
            successMessage.value = '✓ Addendum appended to the signed medical note.';
            setTimeout(() => { successMessage.value = ''; }, 4000);
        },
        onFinish: () => {
            isAmending.value = false;
        }
    });
};

const displayNotes = computed(() => {
    return props.existingNotes?.length > 0 ? props.existingNotes : props.notes;
});

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) + ' · ' + d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
};
</script>

<template>
    <div class="space-y-2.5">
        
        <!-- Header & Quick Templates Bar -->
        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-border/60">
            <div class="flex items-center gap-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                    <FileText class="w-3.5 h-3.5 text-primary" />
                    <span>SOAP Clinical Documentation</span>
                </h3>
            </div>

            <!-- Quick Template Shortcuts, All Templates Modal & Reset Action -->
            <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-none">
                <button
                    v-for="tpl in tanzaniaTemplates.slice(0, 4)"
                    :key="tpl.name"
                    type="button"
                    @click="applyTemplate(tpl)"
                    class="px-2 py-0.5 rounded text-[10px] font-semibold bg-muted/60 hover:bg-primary/10 hover:text-primary border border-border/50 transition-all text-muted-foreground whitespace-nowrap shadow-2xs"
                >
                    <span>{{ tpl.icon }} {{ tpl.name.split(' ')[0] }}</span>
                </button>

                <!-- Full Tanzania Templates Library Button -->
                <button
                    type="button"
                    @click="showTemplatesModal = true"
                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary/15 text-primary hover:bg-primary/25 border border-primary/30 transition-all whitespace-nowrap flex items-center gap-1 shadow-2xs"
                >
                    <Sparkles class="w-3 h-3 text-primary" />
                    <span>All TZ Templates (10)</span>
                </button>

                <!-- Clear / Reset Form Shortcut -->
                <button
                    v-if="isFormDirty"
                    type="button"
                    @click="clearForm"
                    class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800 transition-all flex items-center gap-1 shadow-2xs animate-fade-in"
                    title="Clear all fields for fresh note"
                >
                    <RotateCcw class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400" />
                    <span>Clear Form</span>
                </button>
            </div>
        </div>

        <!-- Flash Feedback Notifications -->
        <div 
            v-if="successMessage" 
            class="p-2 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between shadow-2xs"
        >
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span>{{ successMessage }}</span>
            </div>
            <button @click="successMessage = ''" class="text-emerald-700 hover:text-emerald-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <div 
            v-if="errorMessage" 
            class="p-2 rounded-md bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 text-xs font-semibold flex items-center justify-between shadow-2xs"
        >
            <div class="flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>
            <button @click="errorMessage = ''" class="text-rose-700 hover:text-rose-900">
                <X class="w-3 h-3" />
            </button>
        </div>

        <!-- 2x2 QUADRANT HIGH-DENSITY SOAP GRID -->
        <form @submit.prevent="submit" class="space-y-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                
                <!-- QUADRANT 1: [S] SUBJECTIVE (Blue Tint) -->
                <div class="p-2 rounded-lg bg-card border border-sky-500/30 dark:border-sky-500/20 shadow-2xs space-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.2 rounded font-mono font-extrabold text-[9px] bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300 border border-sky-300 dark:border-sky-800">
                                S
                            </span>
                            <label class="text-[11px] font-bold text-foreground">
                                Subjective
                            </label>
                            <span class="text-[9px] text-muted-foreground hidden sm:inline">(History & Symptoms)</span>
                        </div>

                        <!-- Macro Chips & Clear Single Field -->
                        <div class="flex items-center gap-1 text-[9px]">
                            <button type="button" @click="insertMacro('subjective', 'Fever & chills 3d')" class="hover:text-primary text-muted-foreground">+Fever</button>
                            <span class="text-border">·</span>
                            <button type="button" @click="insertMacro('subjective', 'Productive cough 4d')" class="hover:text-primary text-muted-foreground">+Cough</button>
                            <span class="text-border">·</span>
                            <button type="button" @click="insertMacro('subjective', 'Dysuria & pelvic pain')" class="hover:text-primary text-muted-foreground">+UTI</button>
                            
                            <button 
                                v-if="form.content.subjective" 
                                type="button" 
                                @click="clearField('subjective')" 
                                class="ml-1 text-muted-foreground hover:text-rose-600 font-bold px-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950" 
                                title="Clear Subjective"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    
                    <textarea
                        v-model="form.content.subjective"
                        rows="2"
                        placeholder="Patient reports onset of symptoms, chief complaint, HPI, duration..."
                        class="w-full rounded border border-border/70 bg-muted/10 p-1.5 text-xs text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary leading-relaxed resize-y min-h-[52px]"
                    ></textarea>
                </div>

                <!-- QUADRANT 2: [O] OBJECTIVE (Purple Tint) -->
                <div class="p-2 rounded-lg bg-card border border-purple-500/30 dark:border-purple-500/20 shadow-2xs space-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.2 rounded font-mono font-extrabold text-[9px] bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-300 dark:border-purple-800">
                                O
                            </span>
                            <label class="text-[11px] font-bold text-foreground">
                                Objective
                            </label>
                            <span class="text-[9px] text-muted-foreground hidden sm:inline">(Physical Examination)</span>
                        </div>

                        <!-- Macro Chips & Clear Single Field -->
                        <div class="flex items-center gap-1 text-[9px]">
                            <button type="button" @click="insertMacro('objective', 'Alert & oriented x3')" class="hover:text-primary text-muted-foreground">+Alert</button>
                            <span class="text-border">·</span>
                            <button type="button" @click="insertMacro('objective', 'Chest clear bilaterally')" class="hover:text-primary text-muted-foreground">+Chest Clear</button>
                            <span class="text-border">·</span>
                            <button type="button" @click="insertMacro('objective', 'mRDT Positive')" class="hover:text-primary text-muted-foreground">+mRDT+</button>
                            
                            <button 
                                v-if="form.content.objective" 
                                type="button" 
                                @click="clearField('objective')" 
                                class="ml-1 text-muted-foreground hover:text-rose-600 font-bold px-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950" 
                                title="Clear Objective"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    
                    <textarea
                        v-model="form.content.objective"
                        rows="2"
                        placeholder="Physical exam findings, vitals correlation, chest, abdomen, neurological..."
                        class="w-full rounded border border-border/70 bg-muted/10 p-1.5 text-xs text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary leading-relaxed resize-y min-h-[52px]"
                    ></textarea>
                </div>

                <!-- QUADRANT 3: [A] ASSESSMENT (Amber Tint) -->
                <div class="p-2 rounded-lg bg-card border border-amber-500/30 dark:border-amber-500/20 shadow-2xs space-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.2 rounded font-mono font-extrabold text-[9px] bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                A
                            </span>
                            <label class="text-[11px] font-bold text-foreground">
                                Assessment
                            </label>
                            <span class="text-[9px] text-muted-foreground hidden sm:inline">(Diagnosis & Differential)</span>
                        </div>

                        <!-- Macro Chips & Clear Single Field -->
                        <div class="flex items-center gap-1 text-[9px]">
                            <button type="button" @click="insertMacro('assessment', 'Acute uncomplicated malaria (B50.9)')" class="hover:text-primary text-muted-foreground">+Malaria</button>
                            <span class="text-border">·</span>
                            <button type="button" @click="insertMacro('assessment', 'Acute Gastroenteritis (A09)')" class="hover:text-primary text-muted-foreground">+AGE</button>
                            <span class="text-border">·</span>
                            <button type="button" @click="insertMacro('assessment', 'PUD / Gastritis (K27.9)')" class="hover:text-primary text-muted-foreground">+PUD</button>
                            
                            <button 
                                v-if="form.content.assessment" 
                                type="button" 
                                @click="clearField('assessment')" 
                                class="ml-1 text-muted-foreground hover:text-rose-600 font-bold px-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950" 
                                title="Clear Assessment"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    
                    <textarea
                        v-model="form.content.assessment"
                        rows="2"
                        placeholder="Primary clinical impression, ICD-10 code, differential diagnoses..."
                        class="w-full rounded border border-border/70 bg-muted/10 p-1.5 text-xs text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary leading-relaxed resize-y min-h-[52px]"
                    ></textarea>

                    <!-- Structured Diagnosis (separate from the narrative above — writes an
                         actual Diagnosis row for morbidity reporting / insurance / FHIR export) -->
                    <div class="pt-1 mt-1 border-t border-amber-500/20 space-y-1">
                        <div v-if="existingDiagnoses.length" class="flex flex-wrap gap-1">
                            <span
                                v-for="dx in existingDiagnoses"
                                :key="dx.id"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-medium border"
                                :class="dx.is_deprecated
                                    ? 'bg-muted/30 text-muted-foreground border-border/60 line-through'
                                    : 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800'"
                                :title="dx.notes || ''"
                            >
                                <Stethoscope class="w-2.5 h-2.5" />
                                {{ dx.description }}<template v-if="dx.icd_10_code"> ({{ dx.icd_10_code }})</template>
                                <span class="opacity-70">· {{ dx.type }}/{{ dx.certainty }}</span>
                            </span>
                        </div>

                        <div v-if="can.recordDiagnosis" class="flex flex-wrap items-center gap-1">
                            <input
                                v-model="diagnosisForm.description"
                                type="text"
                                placeholder="Diagnosis (e.g. Uncomplicated malaria)"
                                class="flex-1 min-w-[120px] rounded border border-border/70 bg-muted/10 px-1.5 py-1 text-[10px] text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary"
                            />
                            <input
                                v-model="diagnosisForm.icd_10_code"
                                type="text"
                                placeholder="ICD-10"
                                class="w-16 rounded border border-border/70 bg-muted/10 px-1.5 py-1 text-[10px] text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary"
                            />
                            <Select
                                v-model="diagnosisForm.certainty"
                                class="rounded border border-border/70 bg-muted/10 px-1 py-1 text-[10px] text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="Confirmed">Confirmed</option>
                                <option value="Suspected">Suspected</option>
                            </Select>
                            <Select
                                v-model="diagnosisForm.type"
                                class="rounded border border-border/70 bg-muted/10 px-1 py-1 text-[10px] text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="Primary">Primary</option>
                                <option value="Secondary">Secondary</option>
                                <option value="Comorbidity">Comorbidity</option>
                            </Select>
                            <button
                                type="button"
                                @click="submitDiagnosis"
                                :disabled="diagnosisForm.processing"
                                class="px-2 py-1 rounded bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-[10px] font-semibold whitespace-nowrap"
                            >
                                {{ diagnosisForm.processing ? 'Saving…' : '+ Diagnosis' }}
                            </button>
                        </div>
                        <p v-if="diagnosisError" class="text-[9px] text-rose-600">{{ diagnosisError }}</p>
                    </div>
                </div>

                <!-- QUADRANT 4: [P] PLAN (Emerald Tint) -->
                <div class="p-2 rounded-lg bg-card border border-emerald-500/30 dark:border-emerald-500/20 shadow-2xs space-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.2 rounded font-mono font-extrabold text-[9px] bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                P
                            </span>
                            <label class="text-[11px] font-bold text-foreground">
                                Plan
                            </label>
                            <span class="text-[9px] text-muted-foreground hidden sm:inline">(Therapeutics & Follow-up)</span>
                        </div>

                        <!-- Macro Chips & Clear Single Field -->
                        <div class="flex items-center gap-1 text-[9px]">
                            <button type="button" @click="insertMacro('plan', 'Prescribe ALu Coartem + Paracetamol')" class="hover:text-primary text-muted-foreground">+ALu</button>
                            <span class="text-border">·</span>
                            <button type="button" @click="insertMacro('plan', 'ORS + Zinc + Ciprofloxacin')" class="hover:text-primary text-muted-foreground">+ORS</button>
                            <span class="text-border">·</span>
                            <button type="button" @click="insertMacro('plan', 'Review in OPD in 3 days')" class="hover:text-primary text-muted-foreground">+Review 3d</button>
                            
                            <button 
                                v-if="form.content.plan" 
                                type="button" 
                                @click="clearField('plan')" 
                                class="ml-1 text-muted-foreground hover:text-rose-600 font-bold px-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950" 
                                title="Clear Plan"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    
                    <textarea
                        v-model="form.content.plan"
                        rows="2"
                        placeholder="Prescription regimen, lab investigations, patient education, review timeline..."
                        class="w-full rounded border border-border/70 bg-muted/10 p-1.5 text-xs text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary leading-relaxed resize-y min-h-[52px]"
                    ></textarea>
                </div>

            </div>

            <!-- Bottom Action Bar -->
            <div class="flex items-center justify-between pt-0.5">
                <div class="text-[10px] text-muted-foreground flex items-center gap-1">
                    <Info class="w-3 h-3 text-primary shrink-0" />
                    <span>Saved notes are timestamped and linked to physician provider ID.</span>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Clear Form Button -->
                    <Button 
                        v-if="isFormDirty"
                        type="button" 
                        variant="outline" 
                        size="sm" 
                        @click="clearForm"
                        class="h-7 px-2.5 text-xs font-semibold gap-1 text-muted-foreground hover:text-rose-600 dark:hover:text-rose-400 bg-card border-border/70 shadow-2xs"
                    >
                        <RotateCcw class="w-3 h-3 text-rose-500" />
                        <span>Clear Form</span>
                    </Button>

                    <Button
                        v-if="can.createNote"
                        type="submit"
                        variant="default"
                        size="sm"
                        :disabled="form.processing || !isFormDirty"
                        class="h-7 px-3 text-xs font-semibold gap-1.5 shadow-2xs"
                    >
                        <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                        <Save v-else class="w-3.5 h-3.5" />
                        <span>{{ form.processing ? 'Saving Note...' : 'Save SOAP Draft' }}</span>
                    </Button>
                </div>
            </div>
        </form>

        <!-- PREVIOUS & SIGNED CLINICAL NOTES TIMELINE -->
        <div v-if="displayNotes.length > 0" class="pt-2 border-t border-border/60 space-y-1.5">
            <div class="flex items-center justify-between">
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <Clock class="w-3 h-3" />
                    <span>Consultation Notes Log ({{ displayNotes.length }})</span>
                </h4>
            </div>
            
            <div class="space-y-1.5 max-h-[160px] overflow-y-auto pr-1 scrollbar-thin">
                <div 
                    v-for="note in displayNotes" 
                    :key="note.id" 
                    class="border border-border/60 rounded-lg p-2 bg-card shadow-2xs space-y-1 transition-all text-xs"
                >
                    <div class="flex items-center justify-between border-b border-border/40 pb-1">
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-[11px] text-foreground">{{ note.note_type }} Note</span>
                            <span class="text-[9px] font-mono text-muted-foreground">{{ formatDate(note.created_at) }}</span>
                            
                            <span v-if="note.is_signed" class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[8px] font-semibold bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                <Lock class="w-2.5 h-2.5 text-emerald-600 dark:text-emerald-400" />
                                <span>Signed & Sealed</span>
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[8px] font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                <Edit3 class="w-2.5 h-2.5 text-amber-600 dark:text-amber-400" />
                                <span>Draft</span>
                            </span>
                        </div>

                        <div class="flex items-center space-x-1.5">
                            <Button
                                v-if="!note.is_signed && can.signNote"
                                variant="default"
                                size="sm"
                                class="h-5 px-2 text-[9px] font-semibold gap-1"
                                @click="openSignModal(note.id)"
                            >
                                <Lock class="w-2.5 h-2.5" />
                                <span>Sign & Lock</span>
                            </Button>
                            <Button
                                v-else-if="note.is_signed && can.signNote"
                                variant="outline"
                                size="sm"
                                class="h-5 px-2 text-[9px] font-semibold gap-1 bg-card"
                                @click="openAmendModal(note.id)"
                            >
                                <Edit3 class="w-2.5 h-2.5" />
                                <span>Add Addendum</span>
                            </Button>
                        </div>
                    </div>
                    
                    <!-- Structured S / O / A / P Breakdown -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 text-[10px] pt-0.5">
                        <div v-if="note.content?.subjective" class="p-1 rounded bg-muted/20 border border-border/30">
                            <span class="font-bold font-mono text-sky-700 dark:text-sky-400 text-[9px] uppercase">S: </span>
                            <span class="text-foreground">{{ note.content.subjective }}</span>
                        </div>
                        <div v-if="note.content?.objective" class="p-1 rounded bg-muted/20 border border-border/30">
                            <span class="font-bold font-mono text-purple-700 dark:text-purple-400 text-[9px] uppercase">O: </span>
                            <span class="text-foreground">{{ note.content.objective }}</span>
                        </div>
                        <div v-if="note.content?.assessment" class="p-1 rounded bg-muted/20 border border-border/30">
                            <span class="font-bold font-mono text-amber-700 dark:text-amber-400 text-[9px] uppercase">A: </span>
                            <span class="text-foreground">{{ note.content.assessment }}</span>
                        </div>
                        <div v-if="note.content?.plan" class="p-1 rounded bg-muted/20 border border-border/30">
                            <span class="font-bold font-mono text-emerald-700 dark:text-emerald-400 text-[9px] uppercase">P: </span>
                            <span class="text-foreground">{{ note.content.plan }}</span>
                        </div>
                    </div>

                    <!-- Addendum if present -->
                    <div v-if="note.amendment_reason" class="p-1 rounded bg-amber-50/60 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 text-[9px] text-amber-900 dark:text-amber-200">
                        <span class="font-bold">Addendum:</span> {{ note.amendment_reason }}
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. Full Tanzania Clinical Templates Modal (Wide 5XL Layout) -->
        <Modal :show="showTemplatesModal" max-width="5xl" @close="showTemplatesModal = false">
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center border border-amber-500/20 shadow-2xs">
                            <Sparkles class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-foreground">Tanzania Standard Clinical Treatment Guidelines (STGs)</h3>
                            <p class="text-xs text-muted-foreground">Select a standard clinical encounter profile to instant-fill the consultation chart</p>
                        </div>
                    </div>
                    <button @click="showTemplatesModal = false" class="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Category Filters & Search -->
                <div class="space-y-2.5">
                    <SearchInput
                        v-model="templateSearch"
                        size="default"
                        placeholder="Search clinical templates by condition, symptoms, medication, or ICD-10 code..."
                    />

                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                        <button
                            v-for="cat in templateCategories"
                            :key="cat"
                            type="button"
                            @click="selectedCategory = cat"
                            class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap"
                            :class="selectedCategory === cat 
                                ? 'bg-primary text-primary-foreground shadow-2xs' 
                                : 'bg-muted/70 text-muted-foreground hover:bg-muted'"
                        >
                            {{ cat }}
                        </button>
                    </div>
                </div>

                <!-- Wide 3-Column Templates Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[460px] overflow-y-auto pr-1 scrollbar-thin">
                    <div
                        v-for="tpl in filteredTemplates"
                        :key="tpl.name"
                        @click="applyTemplate(tpl)"
                        class="p-3 rounded-lg border border-border/80 bg-card hover:border-primary hover:ring-1 hover:ring-primary/40 hover:bg-primary/5 transition-all cursor-pointer space-y-2 text-xs shadow-2xs group flex flex-col justify-between"
                    >
                        <div class="space-y-1.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-bold text-foreground group-hover:text-primary transition-colors flex items-center gap-1.5 text-xs">
                                    <span class="text-base">{{ tpl.icon }}</span>
                                    <span class="leading-tight">{{ tpl.name }}</span>
                                </div>
                                <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-muted/80 text-muted-foreground whitespace-nowrap border border-border/40">
                                    {{ tpl.category }}
                                </span>
                            </div>

                            <div class="text-[11px] font-semibold text-foreground/90 leading-snug">
                                {{ tpl.assessment }}
                            </div>

                            <div class="text-[10px] text-muted-foreground line-clamp-2 leading-relaxed bg-muted/20 p-1.5 rounded border border-border/30">
                                <span class="font-bold text-foreground/70">Plan:</span> {{ tpl.plan }}
                            </div>
                        </div>

                        <div class="text-[10px] text-primary font-bold flex items-center justify-between pt-1.5 border-t border-border/40">
                            <span>Apply to Chart</span>
                            <span class="group-hover:translate-x-0.5 transition-transform">→</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-border">
                    <span class="text-xs text-muted-foreground">
                        Showing {{ filteredTemplates.length }} of {{ tanzaniaTemplates.length }} standardized clinical profiles
                    </span>
                    <Button variant="outline" size="sm" @click="showTemplatesModal = false">Close</Button>
                </div>
            </div>
        </Modal>

        <!-- 2. Sign Note Confirmation Modal -->
        <Modal :show="showSignModal" max-width="md" @close="closeSignModal">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Lock class="w-4 h-4 text-emerald-600" />
                        <h3 class="font-bold text-sm text-foreground">Sign & Seal Clinical Note</h3>
                    </div>
                    <button @click="closeSignModal" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-3 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 rounded text-amber-950 dark:text-amber-200 text-xs space-y-1">
                    <div class="flex items-center gap-1.5 font-bold text-amber-900 dark:text-amber-300">
                        <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>Medico-Legal Immutability Notice</span>
                    </div>
                    <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-snug">
                        Once signed, this consultation note becomes a legally binding, tamper-evident medical record. It cannot be edited directly and can only be amended with a logged audit addendum.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <Button variant="outline" size="sm" @click="closeSignModal" :disabled="isSigning">Cancel</Button>
                    <Button variant="default" size="sm" @click="confirmSignNote" :disabled="isSigning" class="gap-1.5">
                        <Loader2 v-if="isSigning" class="w-3.5 h-3.5 animate-spin" />
                        <ShieldCheck v-else class="w-3.5 h-3.5" />
                        <span>Sign & Lock Note</span>
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- 3. Amend Note Audit Modal -->
        <Modal :show="showAmendModal" max-width="md" @close="closeAmendModal">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <Edit3 class="w-4 h-4 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">Add Clinical Addendum</h3>
                    </div>
                    <button @click="closeAmendModal" class="text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Amendment Rationale *</label>
                        <Input
                            v-model="amendReason"
                            type="text"
                            placeholder="e.g. Additional lab findings received, dosage clarification"
                            class="h-8 text-xs"
                            autofocus
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-xs text-foreground">Addendum Notes & Corrections *</label>
                        <textarea
                            v-model="amendSubjective"
                            rows="3"
                            placeholder="Enter addendum text to append to this signed medical record..."
                            class="w-full rounded border border-border bg-card p-2 text-xs text-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-primary"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <Button variant="outline" size="sm" @click="closeAmendModal" :disabled="isAmending">Cancel</Button>
                    <Button variant="default" size="sm" @click="confirmAmendNote" :disabled="isAmending || !amendReason || !amendSubjective">
                        <Loader2 v-if="isAmending" class="w-3.5 h-3.5 animate-spin mr-1.5" />
                        <span>Save Addendum</span>
                    </Button>
                </div>
            </div>
        </Modal>

    </div>
</template>
