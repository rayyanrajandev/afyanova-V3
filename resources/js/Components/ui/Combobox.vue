<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';
import { Search, ChevronDown, Check, X } from 'lucide-vue-next';

const triggerVariants = cva(
    'flex w-full items-center justify-between rounded-md border border-slate-300 dark:border-border/80 bg-white dark:bg-card text-slate-900 dark:text-foreground shadow-2xs transition-all hover:border-slate-400 dark:hover:border-border focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer font-medium select-none',
    {
        variants: {
            size: {
                xs: 'h-6 text-[10.5px] px-2 rounded',
                sm: 'h-7 text-xs px-2.5 rounded',
                default: 'h-8 text-xs px-2.5 rounded-md',
                lg: 'h-9 text-sm px-3 rounded-md',
            },
        },
        defaultVariants: {
            size: 'default',
        },
    }
);

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean, Object],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Select option...',
    },
    searchPlaceholder: {
        type: String,
        default: 'Type to filter options...',
    },
    size: {
        type: String,
        default: 'default',
    },
    clearable: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: Boolean,
        default: false,
    },
    class: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'select', 'clear']);

const isOpen = ref(false);
const searchQuery = ref('');
const rootRef = ref(null);
const searchInputRef = ref(null);
const highlightedIndex = ref(0);
const openUpwards = ref(false);

const normalizedOptions = computed(() => {
    return props.options.map((opt) => {
        if (typeof opt === 'object' && opt !== null) {
            return {
                label: opt.label ?? opt.name ?? opt.title ?? opt.text ?? String(opt.value ?? opt.id),
                value: opt.value !== undefined ? opt.value : (opt.id !== undefined ? opt.id : opt.name),
                description: opt.description ?? opt.subtext ?? opt.email ?? null,
                badge: opt.badge ?? opt.role ?? opt.category ?? null,
                disabled: !!opt.disabled,
            };
        }
        return {
            label: String(opt),
            value: opt,
            description: null,
            badge: null,
            disabled: false,
        };
    });
});

const filteredOptions = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return normalizedOptions.value;
    return normalizedOptions.value.filter((opt) => {
        const matchLabel = opt.label.toLowerCase().includes(q);
        const matchDesc = opt.description ? opt.description.toLowerCase().includes(q) : false;
        const matchBadge = opt.badge ? opt.badge.toLowerCase().includes(q) : false;
        return matchLabel || matchDesc || matchBadge;
    });
});

const selectedOption = computed(() => {
    return normalizedOptions.value.find((opt) => opt.value === props.modelValue) || null;
});

const calculatePlacement = () => {
    if (!rootRef.value) return;
    const rect = rootRef.value.getBoundingClientRect();
    const dropdownHeight = 260;
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    openUpwards.value = spaceBelow < dropdownHeight && spaceAbove > spaceBelow;
};

const openDropdown = () => {
    if (props.disabled) return;
    calculatePlacement();
    isOpen.value = true;
    highlightedIndex.value = 0;
    nextTick(() => {
        if (searchInputRef.value) {
            searchInputRef.value.focus();
        }
    });
};

const closeDropdown = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

const selectOption = (opt) => {
    if (opt.disabled) return;
    emit('update:modelValue', opt.value);
    emit('change', opt.value);
    emit('select', opt);
    closeDropdown();
};

const clearSelection = (e) => {
    e.stopPropagation();
    emit('update:modelValue', '');
    emit('change', '');
    emit('clear');
};

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
        if (highlightedIndex.value < filteredOptions.value.length - 1) {
            highlightedIndex.value++;
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (highlightedIndex.value > 0) {
            highlightedIndex.value--;
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const active = filteredOptions.value[highlightedIndex.value];
        if (active) {
            selectOption(active);
        }
    }
};

const handleClickOutside = (e) => {
    if (rootRef.value && !rootRef.value.contains(e.target)) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div
        ref="rootRef"
        class="relative w-full text-left transition-all"
        :class="isOpen ? 'z-50' : 'z-10'"
        @keydown="handleKeyDown"
    >
        <!-- TRIGGER -->
        <div
            @click="openDropdown"
            :class="cn(
                triggerVariants({ size }),
                error && 'border-destructive focus:border-destructive ring-1 ring-destructive/20',
                isOpen && 'border-primary ring-2 ring-primary/20',
                props.class
            )"
        >
            <!-- Selected Value or Placeholder -->
            <div class="flex items-center gap-2 min-w-0 flex-1 truncate pr-2">
                <template v-if="selectedOption">
                    <span class="font-medium text-foreground truncate">
                        {{ selectedOption.label }}
                    </span>
                    <span
                        v-if="selectedOption.badge"
                        class="text-[9.5px] px-1.5 py-0.2 rounded bg-primary/10 text-primary font-semibold shrink-0"
                    >
                        {{ selectedOption.badge }}
                    </span>
                </template>
                <span v-else class="text-muted-foreground truncate font-normal">
                    {{ placeholder }}
                </span>
            </div>

            <!-- Actions (Clear & Chevron) -->
            <div class="flex items-center gap-1 shrink-0 text-muted-foreground">
                <button
                    v-if="clearable && selectedOption && !disabled"
                    type="button"
                    @click="clearSelection"
                    class="p-0.5 hover:text-foreground hover:bg-muted/80 rounded transition-colors"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
                <ChevronDown
                    class="w-3.5 h-3.5 opacity-60 transition-transform duration-150"
                    :class="isOpen ? 'rotate-180 text-primary' : ''"
                />
            </div>
        </div>

        <!-- DROPDOWN POPOVER -->
        <div
            v-if="isOpen"
            class="absolute left-0 z-50 w-full min-w-[220px] bg-popover text-popover-foreground border border-border/80 rounded-lg shadow-xl overflow-hidden animate-in fade-in-0 duration-100 flex flex-col"
            :class="openUpwards ? 'bottom-full mb-1 origin-bottom zoom-in-95' : 'top-full mt-1 origin-top zoom-in-95'"
        >
            <!-- Search Header -->
            <div class="flex items-center border-b border-border/60 bg-muted/20 px-2.5 py-1.5 gap-2">
                <Search class="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-full bg-transparent text-xs text-foreground placeholder:text-muted-foreground outline-none border-0 p-0 focus:ring-0"
                />
                <button
                    v-if="searchQuery"
                    type="button"
                    @click="searchQuery = ''"
                    class="text-muted-foreground hover:text-foreground p-0.5"
                >
                    <X class="w-3 h-3" />
                </button>
            </div>

            <!-- Options Viewport -->
            <div class="max-h-60 overflow-y-auto p-1 scrollbar-thin divide-y divide-border/20">
                <template v-if="filteredOptions.length > 0">
                    <div
                        v-for="(opt, idx) in filteredOptions"
                        :key="opt.value"
                        @click="selectOption(opt)"
                        @mouseenter="highlightedIndex = idx"
                        class="p-2 cursor-pointer text-xs rounded-md transition-colors flex items-center justify-between gap-2 select-none"
                        :class="[
                            idx === highlightedIndex ? 'bg-primary/10 text-primary font-semibold' : 'hover:bg-muted/50 text-foreground',
                            opt.disabled ? 'opacity-50 pointer-events-none' : ''
                        ]"
                    >
                        <div class="min-w-0 flex-1 space-y-0.5">
                            <div class="flex items-center gap-1.5 truncate">
                                <span class="truncate">{{ opt.label }}</span>
                                <span
                                    v-if="opt.badge"
                                    class="text-[9px] px-1.5 py-0.2 rounded bg-muted text-muted-foreground font-mono"
                                >
                                    {{ opt.badge }}
                                </span>
                            </div>
                            <div v-if="opt.description" class="text-[10px] text-muted-foreground truncate">
                                {{ opt.description }}
                            </div>
                        </div>

                        <div v-if="selectedOption?.value === opt.value" class="shrink-0 flex items-center text-primary">
                            <Check class="w-3.5 h-3.5" />
                        </div>
                    </div>
                </template>

                <div v-else class="py-4 px-3 text-center text-xs text-muted-foreground font-medium">
                    No matching options found.
                </div>
            </div>
        </div>
    </div>
</template>
