<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { 
    Search, 
    X, 
    ChevronDown, 
    Check, 
    AlertTriangle, 
    ShieldCheck, 
    ThermometerSnowflake, 
    Layers,
    Loader2,
    Barcode
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: null,
    },
    items: {
        type: Array,
        default: () => [],
    },
    categoryScope: {
        type: String,
        default: 'ALL',
    },
    searchEndpoint: {
        type: String,
        default: '/inventory/catalog/search',
    },
    placeholder: {
        type: String,
        default: 'Search items by code, name, generic...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'select', 'clear']);

// State
const isOpen = ref(false);
const searchQuery = ref('');
const selectedCategory = ref(props.categoryScope || 'ALL');
const comboboxRef = ref(null);
const searchInputRef = ref(null);
const highlightedIndex = ref(0);
const isLoading = ref(false);
const serverResults = ref([]);
const knownItemsMap = ref(new Map());

// In-Memory Big-Tech LRU Cache for Sub-Millisecond Repeat Queries
const searchCache = new Map();
let abortController = null;
let debounceTimeout = null;

const categories = [
    { key: 'ALL', label: 'All Categories' },
    { key: 'Pharmaceutical', label: 'Medicines' },
    { key: 'Surgical_Consumable', label: 'Surgical' },
    { key: 'Lab_Reagent', label: 'Lab' },
    { key: 'IPC_Chemical', label: 'IPC Chemicals' },
    { key: 'Linen_Bedding', label: 'Linen' },
    { key: 'Stationery_Registers', label: 'Stationery' },
    { key: 'Medical_Gas', label: 'Gases' },
    { key: 'Food_Ration', label: 'Food' },
    { key: 'Fixed_Asset', label: 'Assets' },
];

// Watch categoryScope from parent header
watch(() => props.categoryScope, (newScope) => {
    selectedCategory.value = newScope || 'ALL';
    if (isOpen.value) {
        fetchItems(searchQuery.value, selectedCategory.value);
    }
});

// Seed initial items into memory map
watch(() => props.items, (newItems) => {
    if (newItems && Array.isArray(newItems)) {
        newItems.forEach(item => {
            const key = item.medication_id || item.id;
            knownItemsMap.value.set(key, item);
        });
        if (serverResults.value.length === 0) {
            serverResults.value = [...newItems.slice(0, 30)];
        }
    }
}, { immediate: true });

const selectedItem = computed(() => {
    if (!props.modelValue) return null;
    return knownItemsMap.value.get(props.modelValue) || 
           props.items.find(i => (i.medication_id && i.medication_id === props.modelValue) || i.id === props.modelValue) || 
           null;
});

const displayItems = computed(() => {
    let list = serverResults.value.length > 0 ? serverResults.value : (props.items || []);
    
    // Client-side category restriction if locked
    if (selectedCategory.value !== 'ALL') {
        list = list.filter(i => i.category === selectedCategory.value);
    }
    
    return list.slice(0, 40);
});

const getCategoryCount = (catKey) => {
    if (catKey === 'ALL') return props.items?.length || displayItems.value.length;
    return (props.items || []).filter(i => i.category === catKey).length;
};

const getCategoryColor = (category) => {
    switch (category) {
        case 'Pharmaceutical': return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20';
        case 'Surgical_Consumable': return 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/20';
        case 'Lab_Reagent': return 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20';
        case 'IPC_Chemical': return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20';
        case 'Medical_Gas': return 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border-cyan-500/20';
        case 'Stationery_Registers': return 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-500/20';
        default: return 'bg-muted text-muted-foreground border-border/50';
    }
};

// Async Server-Side Search with Cache & AbortController (Meta Engineering Pattern)
const fetchItems = async (query = '', category = 'ALL') => {
    const effectiveCategory = props.categoryScope !== 'ALL' ? props.categoryScope : category;
    const cacheKey = `${effectiveCategory}::${query.trim().toLowerCase()}`;

    // 1. Check in-memory LRU cache
    if (searchCache.has(cacheKey)) {
        serverResults.value = searchCache.get(cacheKey);
        isLoading.value = false;
        return;
    }

    // 2. Abort previous in-flight request
    if (abortController) {
        abortController.abort();
    }
    abortController = new AbortController();

    isLoading.value = true;

    try {
        const url = new URL(props.searchEndpoint, window.location.origin);
        url.searchParams.set('q', query.trim());
        url.searchParams.set('category', effectiveCategory);
        url.searchParams.set('limit', '35');

        const res = await fetch(url.toString(), {
            signal: abortController.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        });

        if (!res.ok) throw new Error('Search failed');

        const json = await res.json();
        const results = json.data || [];

        // Update known items map
        results.forEach(item => {
            const key = item.medication_id || item.id;
            knownItemsMap.value.set(key, item);
        });

        // Store in LRU cache
        searchCache.set(cacheKey, results);
        serverResults.value = results;
    } catch (err) {
        if (err.name !== 'AbortError') {
            console.error('Combobox search error:', err);
        }
    } finally {
        isLoading.value = false;
    }
};

// Debounced watcher for typing
watch([searchQuery, selectedCategory], ([q, cat]) => {
    highlightedIndex.value = 0;
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchItems(q, cat);
    }, 180);
});

// Dynamic Smart Flip Positioning (Top vs Bottom based on Viewport Space)
const openUpwards = ref(false);

const calculatePlacement = () => {
    if (!comboboxRef.value) return;
    const rect = comboboxRef.value.getBoundingClientRect();
    const dropdownEstimatedHeight = 320;
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;

    // If space below is less than dropdown height and there's more room above, flip to top!
    if (spaceBelow < dropdownEstimatedHeight && spaceAbove > spaceBelow) {
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
    fetchItems(searchQuery.value, selectedCategory.value);
    setTimeout(() => {
        calculatePlacement();
        if (searchInputRef.value) {
            searchInputRef.value.focus();
        }
    }, 40);
};

const closeDropdown = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

const selectItem = (item) => {
    const val = item.medication_id || item.id;
    knownItemsMap.value.set(val, item);
    emit('update:modelValue', val);
    emit('select', item);
    closeDropdown();
};

const clearSelection = (e) => {
    e.stopPropagation();
    emit('update:modelValue', null);
    emit('clear');
};

const handleKeyDown = (e) => {
    if (!isOpen.value) {
        if (e.key === 'Enter' || e.key === 'ArrowDown') {
            openDropdown();
        }
        return;
    }

    if (e.key === 'Escape') {
        closeDropdown();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (highlightedIndex.value < displayItems.value.length - 1) {
            highlightedIndex.value++;
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (highlightedIndex.value > 0) {
            highlightedIndex.value--;
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (displayItems.value[highlightedIndex.value]) {
            selectItem(displayItems.value[highlightedIndex.value]);
        }
    }
};

const handleClickOutside = (e) => {
    if (comboboxRef.value && !comboboxRef.value.contains(e.target)) {
        closeDropdown();
    }
};

const handleScrollOrResize = () => {
    if (isOpen.value) {
        calculatePlacement();
    }
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
    if (abortController) abortController.abort();
    clearTimeout(debounceTimeout);
});
</script>

<template>
    <div ref="comboboxRef" class="w-full text-left transition-all" :class="isOpen ? 'relative z-50' : 'relative z-10'" @keydown="handleKeyDown">
        <!-- TRIGGER BUTTON -->
        <div
            @click="openDropdown"
            class="flex items-center justify-between min-h-8 px-2.5 py-1 text-xs rounded-md border border-border bg-card hover:border-primary/50 cursor-pointer transition-colors shadow-2xs group"
            :class="[
                disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '',
                isOpen ? 'border-primary ring-1 ring-primary/20' : ''
            ]"
        >
            <!-- Selected Item Display -->
            <div v-if="selectedItem" class="flex items-center gap-2 overflow-hidden pr-1">
                <span 
                    class="text-[9px] px-1.5 py-0.5 rounded font-bold border uppercase tracking-wider shrink-0"
                    :class="getCategoryColor(selectedItem.category)"
                >
                    {{ selectedItem.category?.replace('_', ' ') }}
                </span>
                <span class="font-medium text-foreground truncate">
                    {{ selectedItem.name }}
                </span>
                <span v-if="selectedItem.item_code" class="text-[10px] font-mono text-muted-foreground shrink-0">
                    ({{ selectedItem.item_code }})
                </span>
            </div>

            <!-- Placeholder when Empty -->
            <div v-else class="flex items-center gap-1.5 text-muted-foreground truncate">
                <Search class="w-3.5 h-3.5 shrink-0 opacity-60" />
                <span class="truncate">{{ placeholder }}</span>
                <span v-if="categoryScope !== 'ALL'" class="text-[9px] px-1.5 py-0.2 bg-primary/10 text-primary font-bold rounded">
                    Scope: {{ categoryScope.replace('_', ' ') }}
                </span>
            </div>

            <!-- Actions (Clear, Spinner & Chevron) -->
            <div class="flex items-center gap-1.5 shrink-0 ml-1.5 text-muted-foreground">
                <Loader2 v-if="isLoading && !isOpen" class="w-3.5 h-3.5 animate-spin text-primary" />
                <button
                    v-if="selectedItem && !disabled"
                    type="button"
                    @click="clearSelection"
                    class="p-0.5 hover:text-foreground hover:bg-muted/80 rounded transition-colors"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
                <ChevronDown class="w-3.5 h-3.5 opacity-60 transition-transform duration-150" :class="isOpen ? 'rotate-180 text-primary' : ''" />
            </div>
        </div>

        <!-- DROPDOWN POPOVER (Smart Top/Bottom Placement) -->
        <div
            v-if="isOpen"
            class="absolute left-0 z-100 bg-popover text-popover-foreground border border-border/80 rounded-lg shadow-2xl overflow-hidden animate-in fade-in-0 duration-100 flex flex-col max-h-[340px] w-full min-w-[300px] max-w-[calc(100vw-2rem)]"
            :class="openUpwards ? 'bottom-full mb-1 zoom-in-95 origin-bottom' : 'top-full mt-1 zoom-in-95 origin-top'"
        >
            <!-- 1. LIVE SEARCH HEADER -->
            <div class="p-2 border-b border-border/60 bg-muted/20">
                <div class="relative flex items-center">
                    <Search class="w-3.5 h-3.5 absolute left-2.5 text-muted-foreground pointer-events-none" />
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="categoryScope !== 'ALL' ? `Search ${categoryScope.replace('_', ' ')} items...` : 'Type to search 5,000+ items across all categories...'"
                        class="w-full h-8 pl-8 pr-7 text-xs bg-background border border-border rounded-md focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 text-foreground placeholder:text-muted-foreground/70"
                    />
                    <div class="absolute right-2 flex items-center gap-1">
                        <Loader2 v-if="isLoading" class="w-3.5 h-3.5 animate-spin text-primary" />
                        <button
                            v-else-if="searchQuery"
                            type="button"
                            @click="searchQuery = ''"
                            class="text-muted-foreground hover:text-foreground"
                        >
                            <X class="w-3 h-3" />
                        </button>
                    </div>
                </div>

                <!-- 2. CATEGORY PILL FILTER STRIP (Shown when not locked by header) -->
                <div v-if="categoryScope === 'ALL'" class="flex items-center gap-1 overflow-x-auto pt-2 pb-0.5 no-scrollbar">
                    <button
                        v-for="cat in categories"
                        :key="cat.key"
                        type="button"
                        @click="selectedCategory = cat.key"
                        class="px-2 py-0.5 text-[10px] font-medium rounded-full shrink-0 transition-colors cursor-pointer flex items-center gap-1 border"
                        :class="selectedCategory === cat.key 
                            ? 'bg-primary text-primary-foreground border-primary font-bold shadow-2xs' 
                            : 'bg-background hover:bg-muted/60 text-muted-foreground border-border/60'"
                    >
                        <span>{{ cat.label }}</span>
                        <span v-if="cat.key !== 'ALL'" class="text-[9px] opacity-75 font-mono">({{ getCategoryCount(cat.key) }})</span>
                    </button>
                </div>

                <!-- Scope Locked Indicator Banner -->
                <div v-else class="mt-1.5 flex items-center gap-1.5 text-[10px] text-muted-foreground font-medium">
                    <Layers class="w-3 h-3 text-primary" />
                    <span>Locked to Scope: <strong class="text-foreground">{{ categoryScope.replace('_', ' ') }}</strong></span>
                </div>
            </div>

            <!-- 3. RESULTS LIST -->
            <div class="overflow-y-auto max-h-[260px] divide-y divide-border/30">
                <template v-if="displayItems.length > 0">
                    <div
                        v-for="(item, idx) in displayItems"
                        :key="item.id"
                        @click="selectItem(item)"
                        @mouseenter="highlightedIndex = idx"
                        class="p-2.5 cursor-pointer text-xs transition-colors flex items-start justify-between gap-2"
                        :class="idx === highlightedIndex ? 'bg-primary/10 text-foreground' : 'hover:bg-muted/40 text-foreground'"
                    >
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span 
                                    class="text-[9px] px-1.5 py-0.2 rounded font-bold border uppercase tracking-wider"
                                    :class="getCategoryColor(item.category)"
                                >
                                    {{ item.category?.replace('_', ' ') }}
                                </span>
                                <span class="font-bold text-foreground">
                                    {{ item.name }}
                                </span>
                                <span v-if="item.is_dda_narcotic" class="text-[9px] px-1 py-0.2 rounded bg-rose-500/10 text-rose-600 font-bold border border-rose-500/20">
                                    DDA
                                </span>
                                <span v-if="item.requires_cold_chain" class="text-[9px] px-1 py-0.2 rounded bg-sky-500/10 text-sky-600 font-bold border border-sky-500/20">
                                    Cold Chain
                                </span>
                            </div>

                            <div class="text-[11px] text-muted-foreground flex items-center gap-3">
                                <span v-if="item.generic_name" class="italic">Generic: {{ item.generic_name }}</span>
                                <span class="font-mono text-[10px] bg-muted/60 px-1 py-0.2 rounded">{{ item.item_code }}</span>
                            </div>
                        </div>

                        <!-- Price / UOM & Checkmark -->
                        <div class="text-right shrink-0 space-y-0.5">
                            <div class="font-mono font-bold text-xs text-foreground">
                                TZS {{ Number(item.unit_cost_price || 0).toLocaleString() }}
                            </div>
                            <div class="text-[9px] text-muted-foreground">
                                Base: {{ item.base_uom?.symbol || 'unit' }}
                            </div>
                            <div v-if="selectedItem?.id === item.id || (selectedItem?.medication_id && selectedItem?.medication_id === item.medication_id)" class="flex justify-end pt-0.5">
                                <Check class="w-3.5 h-3.5 text-primary" />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- EMPTY STATE -->
                <div v-else class="p-6 text-center text-muted-foreground space-y-1">
                    <Layers class="w-6 h-6 mx-auto opacity-40 text-muted-foreground mb-1" />
                    <div class="text-xs font-bold text-foreground">
                        {{ isLoading ? 'Searching catalog...' : 'No matching items found' }}
                    </div>
                    <div class="text-[10px]">
                        {{ isLoading ? 'Executing sub-millisecond database query...' : 'Try another search term or click "All Categories"' }}
                    </div>
                </div>
            </div>

            <!-- 4. FOOTER NOTE -->
            <div class="px-3 py-1.5 bg-muted/40 border-t border-border/50 text-[10px] text-muted-foreground flex items-center justify-between">
                <span>{{ isLoading ? 'Fetching from catalog...' : `Showing top ${displayItems.length} matches` }}</span>
                <span class="font-mono">Use ↑ / ↓ and Enter</span>
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
