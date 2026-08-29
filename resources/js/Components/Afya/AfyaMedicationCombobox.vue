<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { 
    Search, 
    X, 
    ChevronDown, 
    Check, 
    AlertTriangle, 
    Pill, 
    Syringe, 
    Droplets, 
    Layers, 
    DollarSign,
    Sparkles
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: null,
    },
    formularies: {
        type: Array,
        default: () => [],
    },
    allergies: {
        type: Array,
        default: () => [],
    },
    existingPrescriptions: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Search hospital formulary (e.g. Amoxicillin, Panadol, 500mg)...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'select', 'clear']);

// State
const isOpen = ref(false);
const searchQuery = ref('');
const selectedFormFilter = ref('ALL');
const comboboxRef = ref(null);
const searchInputRef = ref(null);
const listContainerRef = ref(null);
const highlightedIndex = ref(0);
const openUpwards = ref(false);

const formTabs = [
    { key: 'ALL', label: 'All Drugs' },
    { key: 'Oral', label: 'Tabs & Caps' },
    { key: 'Injectable', label: 'Injections & IV' },
    { key: 'Liquid', label: 'Syrups & Susp.' },
    { key: 'Topical', label: 'Topical & Drops' },
];

// Map of active already prescribed IDs in encounter
const alreadyPrescribedIds = computed(() => {
    return new Set((props.existingPrescriptions || []).map(p => p.medication_id));
});

// Currently selected medication object
const selectedMedication = computed(() => {
    if (!props.modelValue) return null;
    return props.formularies.find(m => m.id === props.modelValue) || null;
});

// Check if a specific medication has cross-allergy risks
const getAllergyWarning = (med) => {
    if (!med || !props.allergies || props.allergies.length === 0) return null;
    const drugName = `${med.generic_name || ''} ${med.brand_name || ''} ${med.drug_class || ''}`.toLowerCase();

    for (const alg of props.allergies) {
        const allergen = (alg.allergen || '').toLowerCase();
        if (!allergen) continue;
        if (
            drugName.includes(allergen) || 
            allergen.includes(drugName.split(' ')[0]) || 
            (allergen.includes('cillin') && drugName.includes('cillin')) ||
            (allergen.includes('sulfa') && drugName.includes('sulfa')) ||
            (allergen.includes('nsaid') && (drugName.includes('ibuprofen') || drugName.includes('diclofenac') || drugName.includes('aspirin')))
        ) {
            return alg.allergen;
        }
    }
    return null;
};

// Form classification helper
const matchesFormTab = (med, tabKey) => {
    if (tabKey === 'ALL') return true;
    const form = (med.form || '').toLowerCase();
    const route = (med.route || '').toLowerCase();

    if (tabKey === 'Oral') {
        return form.includes('tab') || form.includes('cap') || form.includes('pill') || route === 'po';
    }
    if (tabKey === 'Injectable') {
        return form.includes('inj') || form.includes('vial') || form.includes('amp') || route === 'iv' || route === 'im' || route === 'sc';
    }
    if (tabKey === 'Liquid') {
        return form.includes('syr') || form.includes('susp') || form.includes('sol') || form.includes('elixir') || form.includes('liq');
    }
    if (tabKey === 'Topical') {
        return form.includes('cream') || form.includes('oint') || form.includes('gel') || form.includes('drop') || form.includes('lotion') || route === 'topical';
    }
    return true;
};

// Real-Time Multi-Token Search Filter
const filteredMedications = computed(() => {
    const rawQuery = searchQuery.value.trim().toLowerCase();
    const tokens = rawQuery.length > 0 ? rawQuery.split(/\s+/).filter(Boolean) : [];

    return props.formularies.filter(med => {
        // 1. Form tab check
        if (!matchesFormTab(med, selectedFormFilter.value)) {
            return false;
        }

        // 2. Query token matching
        if (tokens.length === 0) return true;

        const targetString = `${med.generic_name || ''} ${med.brand_name || ''} ${med.strength || ''} ${med.form || ''} ${med.drug_class || ''} ${med.route || ''}`.toLowerCase();
        
        return tokens.every(token => targetString.includes(token));
    });
});

// Top slice of items for performance
const displayMedications = computed(() => {
    return filteredMedications.value.slice(0, 60);
});

// Viewport smart placement
const calculatePlacement = () => {
    if (!comboboxRef.value) return;
    const rect = comboboxRef.value.getBoundingClientRect();
    const estimatedHeight = 360;
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;

    if (spaceBelow < estimatedHeight && spaceAbove > spaceBelow) {
        openUpwards.value = true;
    } else {
        openUpwards.value = false;
    }
};

const openDropdown = () => {
    if (props.disabled) return;
    calculatePlacement();
    isOpen.value = true;
    highlightedIndex.value = 0;
    nextTick(() => {
        calculatePlacement();
        if (searchInputRef.value) {
            searchInputRef.value.focus();
        }
    });
};

const closeDropdown = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

const selectMedication = (med) => {
    emit('update:modelValue', med.id);
    emit('select', med);
    emit('change', med);
    closeDropdown();
};

const clearSelection = (e) => {
    e.stopPropagation();
    emit('update:modelValue', '');
    emit('clear');
    emit('change', null);
};

// Keyboard navigation
const handleKeyDown = (e) => {
    if (!isOpen.value) {
        if (e.key === 'Enter' || e.key === 'ArrowDown' || e.key === ' ') {
            e.preventDefault();
            openDropdown();
        }
        return;
    }

    if (e.key === 'Escape') {
        e.preventDefault();
        closeDropdown();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (highlightedIndex.value < displayMedications.value.length - 1) {
            highlightedIndex.value++;
            scrollToHighlighted();
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (highlightedIndex.value > 0) {
            highlightedIndex.value--;
            scrollToHighlighted();
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (displayMedications.value[highlightedIndex.value]) {
            selectMedication(displayMedications.value[highlightedIndex.value]);
        }
    }
};

const scrollToHighlighted = () => {
    nextTick(() => {
        if (!listContainerRef.value) return;
        const activeEl = listContainerRef.value.children[highlightedIndex.value];
        if (activeEl) {
            activeEl.scrollIntoView({ block: 'nearest' });
        }
    });
};

// Reset highlight on query change
watch(searchQuery, () => {
    highlightedIndex.value = 0;
});

// Click outside handler
const handleClickOutside = (e) => {
    if (comboboxRef.value && !comboboxRef.value.contains(e.target)) {
        closeDropdown();
    }
};

const handleScrollOrResize = () => {
    if (isOpen.value) calculatePlacement();
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('resize', handleScrollOrResize);
    window.addEventListener('scroll', handleScrollOrResize, true);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('resize', handleScrollOrResize);
    window.removeEventListener('scroll', handleScrollOrResize, true);
});
</script>

<template>
    <div 
        ref="comboboxRef" 
        class="w-full text-left transition-all relative"
        :class="isOpen ? 'z-50' : 'z-10'"
        @keydown="handleKeyDown"
    >
        <!-- TRIGGER BUTTON -->
        <div
            @click="openDropdown"
            class="flex items-center justify-between min-h-8 px-2.5 py-1 text-xs rounded-md border bg-card hover:border-primary/60 cursor-pointer transition-colors shadow-2xs group select-none"
            :class="[
                disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '',
                error ? 'border-destructive focus:border-destructive ring-1 ring-destructive/20' : 'border-border',
                isOpen ? 'border-primary ring-2 ring-primary/20' : ''
            ]"
        >
            <!-- 1. SELECTED MEDICATION DISPLAY (Single line, no wrap) -->
            <div v-if="selectedMedication" class="flex items-center gap-2 overflow-hidden pr-1 min-w-0 flex-1 whitespace-nowrap">
                <Pill class="w-3.5 h-3.5 text-primary shrink-0" />
                <span class="font-bold text-foreground truncate whitespace-nowrap">
                    {{ selectedMedication.generic_name }}
                    <span v-if="selectedMedication.brand_name" class="text-muted-foreground font-normal text-[11px] ml-1">
                        ({{ selectedMedication.brand_name }})
                    </span>
                </span>

                <span class="text-[10.5px] font-mono px-1.5 py-0.2 bg-muted rounded shrink-0 text-foreground font-semibold whitespace-nowrap">
                    {{ selectedMedication.strength }}
                </span>

                <span class="text-[9px] px-1.5 py-0.2 rounded font-bold uppercase tracking-wider shrink-0 bg-primary/10 text-primary border border-primary/20 whitespace-nowrap">
                    {{ selectedMedication.form }}
                </span>

                <!-- Unit Price Badge -->
                <span v-if="selectedMedication.unit_price > 0" class="text-[10px] font-mono font-bold text-emerald-700 dark:text-emerald-400 shrink-0 whitespace-nowrap bg-emerald-50 dark:bg-emerald-950/40 px-1.5 py-0.2 rounded border border-emerald-200 dark:border-emerald-800">
                    TZS {{ Number(selectedMedication.unit_price).toLocaleString() }}/{{ selectedMedication.form || 'unit' }}
                </span>

                <!-- Allergy Warning Badge -->
                <span 
                    v-if="getAllergyWarning(selectedMedication)" 
                    class="text-[9px] px-1.5 py-0.2 rounded font-bold uppercase tracking-wider shrink-0 bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800 animate-pulse flex items-center gap-1 whitespace-nowrap"
                >
                    <AlertTriangle class="w-3 h-3 shrink-0" />
                    <span>Allergy: {{ getAllergyWarning(selectedMedication) }}</span>
                </span>
            </div>

            <!-- 2. PLACEHOLDER WHEN EMPTY -->
            <div v-else class="flex items-center gap-2 text-muted-foreground truncate">
                <Search class="w-3.5 h-3.5 shrink-0 opacity-60 text-primary" />
                <span class="truncate">{{ placeholder }}</span>
                <span class="text-[9px] font-mono px-1.5 py-0.2 bg-muted/60 text-muted-foreground rounded shrink-0 hidden sm:inline-block">
                    {{ formularies.length }} in Formulary
                </span>
            </div>

            <!-- 3. ACTIONS (Clear & Chevron) -->
            <div class="flex items-center gap-1.5 shrink-0 ml-1.5 text-muted-foreground">
                <button
                    v-if="selectedMedication && !disabled"
                    type="button"
                    @click="clearSelection"
                    class="p-0.5 hover:text-foreground hover:bg-muted/80 rounded transition-colors"
                    title="Clear selected drug"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
                <ChevronDown class="w-3.5 h-3.5 opacity-60 transition-transform duration-150" :class="isOpen ? 'rotate-180 text-primary' : ''" />
            </div>
        </div>

        <!-- DROPDOWN POPOVER -->
        <div
            v-if="isOpen"
            class="absolute left-0 z-100 bg-card border border-border/80 rounded-lg shadow-2xl overflow-hidden animate-in fade-in-0 duration-100 flex flex-col w-full min-w-[360px] sm:min-w-[560px]"
            :class="openUpwards ? 'bottom-full mb-1 zoom-in-95 origin-bottom' : 'top-full mt-1 zoom-in-95 origin-top'"
        >
            <!-- 1. LIVE SEARCH HEADER -->
            <div class="p-2.5 border-b border-border/60 bg-muted/20 space-y-2">
                <div class="relative flex items-center">
                    <Search class="w-3.5 h-3.5 absolute left-2.5 text-primary pointer-events-none" />
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Type generic, brand, strength or form (e.g. Amox 500, Panadol, IV)..."
                        class="w-full h-8 pl-8 pr-7 text-xs bg-background border border-border rounded-md focus:outline-hidden focus:border-primary focus:ring-1 focus:ring-primary/20 text-foreground placeholder:text-muted-foreground/70"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="searchQuery = ''"
                        class="absolute right-2 text-muted-foreground hover:text-foreground p-0.5"
                    >
                        <X class="w-3 h-3" />
                    </button>
                </div>

                <!-- 2. QUICK FORM FILTER CHIPS -->
                <div class="flex items-center gap-1 overflow-x-auto no-scrollbar pt-0.5">
                    <button
                        v-for="tab in formTabs"
                        :key="tab.key"
                        type="button"
                        @click="selectedFormFilter = tab.key"
                        class="px-2 py-0.5 text-[10px] font-medium rounded-full shrink-0 transition-colors cursor-pointer flex items-center gap-1 border"
                        :class="selectedFormFilter === tab.key 
                            ? 'bg-primary text-primary-foreground border-primary font-bold shadow-2xs' 
                            : 'bg-background hover:bg-muted/60 text-muted-foreground border-border/60'"
                    >
                        <span>{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <!-- 3. RESULTS LIST (Single-Line Ultra-Crisp Display) -->
            <div ref="listContainerRef" class="overflow-y-auto max-h-[280px] divide-y divide-border/30">
                <template v-if="displayMedications.length > 0">
                    <div
                        v-for="(med, idx) in displayMedications"
                        :key="med.id"
                        @click="selectMedication(med)"
                        @mouseenter="highlightedIndex = idx"
                        class="p-2.5 cursor-pointer text-xs transition-colors flex items-center justify-between gap-3 select-none whitespace-nowrap"
                        :class="idx === highlightedIndex ? 'bg-primary/10 text-foreground' : 'hover:bg-muted/40 text-foreground'"
                    >
                        <div class="flex items-center gap-2 min-w-0 flex-1 whitespace-nowrap overflow-hidden">
                            <span class="font-bold text-foreground truncate shrink-0">
                                {{ med.generic_name }}
                            </span>
                            <span v-if="med.brand_name" class="text-muted-foreground font-medium text-[11px] truncate shrink-0">
                                ({{ med.brand_name }})
                            </span>
                            <span class="text-[10.5px] font-mono px-1.5 py-0.2 bg-muted/60 rounded text-foreground font-semibold shrink-0">
                                {{ med.strength }}
                            </span>
                            <span class="text-[9px] px-1.5 py-0.2 rounded font-bold uppercase tracking-wider bg-primary/10 text-primary border border-primary/20 shrink-0">
                                {{ med.form }}
                            </span>
                            <span v-if="med.drug_class" class="text-[10px] text-muted-foreground italic shrink-0">
                                · {{ med.drug_class }}
                            </span>
                            <span v-if="med.route" class="text-[10px] font-mono text-muted-foreground shrink-0">
                                · Route: {{ med.route }}
                            </span>

                            <!-- Allergy Warning Tag -->
                            <span 
                                v-if="getAllergyWarning(med)" 
                                class="ml-2 px-1.5 py-0.5 rounded text-[8.5px] font-semibold bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20 flex items-center gap-1 shrink-0"
                            >
                                <AlertTriangle class="w-2.5 h-2.5 text-rose-600 dark:text-rose-400" />
                                <span>Allergy: {{ getAllergyWarning(med) }}</span>
                            </span>

                            <!-- Already Prescribed Tag -->
                            <span 
                                v-if="alreadyPrescribedIds.has(med.id)" 
                                class="ml-2.5 px-1.5 py-0.5 rounded text-[8.5px] font-medium bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 shrink-0"
                            >
                                Prescribed
                            </span>
                        </div>

                        <!-- Price Tag & Selection Checkmark -->
                        <div class="text-right shrink-0 flex items-center gap-2">
                            <div v-if="med.unit_price > 0" class="font-mono font-bold text-xs text-emerald-700 dark:text-emerald-400">
                                TZS {{ Number(med.unit_price).toLocaleString() }}<span class="text-[9px] text-muted-foreground font-normal">/{{ med.form || 'unit' }}</span>
                            </div>
                            <div v-else class="text-[10px] text-muted-foreground">
                                Free / Standard
                            </div>

                            <div v-if="selectedMedication?.id === med.id" class="flex justify-end">
                                <Check class="w-3.5 h-3.5 text-primary" />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- EMPTY SEARCH RESULTS -->
                <div v-else class="p-6 text-center text-muted-foreground space-y-1">
                    <Pill class="w-6 h-6 mx-auto opacity-40 text-muted-foreground mb-1" />
                    <div class="text-xs font-bold text-foreground">
                        No matching hospital drugs found
                    </div>
                    <div class="text-[10px]">
                        Try searching with fewer characters or select "All Drugs".
                    </div>
                </div>
            </div>

            <!-- 4. FOOTER INFO -->
            <div class="px-3 py-1.5 bg-muted/40 border-t border-border/50 text-[10px] text-muted-foreground flex items-center justify-between">
                <span>Showing {{ displayMedications.length }} of {{ filteredMedications.length }} matches</span>
                <span class="font-mono text-[9px]">Use ↑ / ↓ and Enter to select</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
