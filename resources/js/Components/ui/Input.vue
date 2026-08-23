<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    placeholder: {
        type: String,
        default: '',
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

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <input
        :type="type"
        :value="modelValue"
        :disabled="disabled"
        :placeholder="placeholder"
        :class="cn(
            'flex h-8 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-900 shadow-2xs transition-all file:border-0 file:bg-transparent file:text-xs file:font-medium placeholder:text-slate-400 hover:border-slate-400 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50',
            error && 'border-destructive focus:border-destructive focus:ring-destructive/20',
            props.class
        )"
        @input="emit('update:modelValue', $event.target.value)"
    />
</template>
