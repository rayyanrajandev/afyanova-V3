<script setup>
import { computed } from 'vue';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center font-medium transition-colors focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 select-none whitespace-nowrap',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 active:bg-primary/95',
                destructive: 'bg-destructive text-destructive-foreground shadow-xs hover:bg-destructive/90',
                outline: 'border border-border bg-background shadow-2xs hover:bg-muted hover:text-foreground active:bg-muted/80',
                secondary: 'bg-secondary text-secondary-foreground shadow-2xs hover:bg-secondary/80',
                ghost: 'hover:bg-muted hover:text-foreground',
                link: 'text-primary underline-offset-4 hover:underline p-0 h-auto',
                subtle: 'bg-primary/10 text-primary hover:bg-primary/20',
            },
            size: {
                /* Compact Density Defaults */
                xs: 'h-6 px-2 text-[10.5px] rounded gap-1',
                sm: 'h-7 px-2.5 text-xs rounded-md gap-1.5',
                default: 'h-8 px-3 text-xs rounded-md gap-1.5',
                lg: 'h-9 px-3.5 text-sm rounded-md gap-2',
                icon: 'h-8 w-8 rounded-md p-0',
                'icon-sm': 'h-7 w-7 rounded-md p-0',
                'icon-xs': 'h-6 w-6 rounded p-0',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
    },
    size: {
        type: String,
        default: 'default',
    },
    class: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click']);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :aria-busy="loading ? 'true' : undefined"
        :class="cn(buttonVariants({ variant, size }), props.class)"
        @click="emit('click', $event)"
    >
        <svg
            v-if="loading"
            class="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <slot />
    </button>
</template>
