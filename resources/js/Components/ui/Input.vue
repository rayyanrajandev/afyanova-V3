<script setup>
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const inputVariants = cva(
    'flex w-full rounded-md border border-slate-300 dark:border-border/80 bg-white dark:bg-card px-2.5 py-1 text-xs text-slate-900 dark:text-foreground shadow-2xs transition-all file:border-0 file:bg-transparent file:text-xs file:font-medium placeholder:text-muted-foreground hover:border-slate-400 dark:hover:border-border focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50',
    {
        variants: {
            size: {
                sm: 'h-7 text-xs py-0.5 rounded',
                default: 'h-8 text-xs py-1 rounded-md',
                lg: 'h-9 text-xs py-1.5 rounded-md',
            },
        },
        defaultVariants: {
            size: 'default',
        },
    }
);

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
    size: {
        type: String,
        default: 'default',
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
            inputVariants({ size }),
            error && 'border-destructive focus:border-destructive focus:ring-destructive/20',
            props.class
        )"
        @input="emit('update:modelValue', $event.target.value)"
    />
</template>
