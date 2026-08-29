<script setup>
import { ref, watch } from 'vue';
import { Search, X } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search...',
    },
    debounce: {
        type: Number,
        default: 0,
    },
    clearable: {
        type: Boolean,
        default: true,
    },
    size: {
        type: String,
        default: 'sm',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    class: {
        type: String,
        default: '',
    },
    inputClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'search', 'clear']);

const localValue = ref(props.modelValue ?? '');

watch(() => props.modelValue, (newVal) => {
    if (newVal !== localValue.value) {
        localValue.value = newVal ?? '';
    }
});

let debounceTimer = null;

const onInput = (event) => {
    const val = event.target.value;
    localValue.value = val;

    if (props.debounce > 0) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            emit('update:modelValue', val);
            emit('search', val);
        }, props.debounce);
    } else {
        emit('update:modelValue', val);
        emit('search', val);
    }
};

const onClear = () => {
    localValue.value = '';
    clearTimeout(debounceTimer);
    emit('update:modelValue', '');
    emit('search', '');
    emit('clear');
};
</script>

<template>
    <div
        :class="cn(
            'relative inline-flex items-center w-full',
            props.class
        )"
    >
        <!-- Embedded Search Icon -->
        <Search
            :class="cn(
                'absolute pointer-events-none text-muted-foreground shrink-0',
                size === 'sm' ? 'left-2 w-3.5 h-3.5' : size === 'lg' ? 'left-3 w-4 h-4' : 'left-2.5 w-3.5 h-3.5'
            )"
        />

        <!-- Search Input Field -->
        <input
            type="text"
            :value="localValue"
            :disabled="disabled"
            :placeholder="placeholder"
            :class="cn(
                'w-full border border-slate-300 dark:border-border/80 bg-white dark:bg-card text-slate-900 dark:text-foreground placeholder:text-muted-foreground shadow-2xs transition-all hover:border-slate-400 dark:hover:border-border focus:outline-hidden focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50 font-normal',
                size === 'sm' && 'h-7 text-xs pl-7 pr-6 rounded',
                size === 'default' && 'h-8 text-xs pl-8 pr-7 rounded-md',
                size === 'lg' && 'h-9 text-xs pl-9 pr-8 rounded-md',
                props.inputClass
            )"
            @input="onInput"
            @keydown.esc="onClear"
        />

        <!-- Clear Button -->
        <button
            v-if="clearable && localValue"
            type="button"
            class="absolute right-1.5 top-1/2 -translate-y-1/2 p-0.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
            @click="onClear"
        >
            <X class="w-3 h-3" />
        </button>
    </div>
</template>
