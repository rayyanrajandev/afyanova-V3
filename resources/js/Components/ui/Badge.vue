<script setup>
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold transition-colors focus:outline-hidden focus:ring-1 focus:ring-ring whitespace-nowrap',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground',
                secondary: 'bg-secondary text-secondary-foreground border border-border',
                destructive: 'bg-destructive/10 text-destructive border border-destructive/20 font-medium',
                outline: 'text-foreground border border-border',
                success: 'bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium',
                warning: 'bg-amber-50 text-amber-800 border border-amber-200 font-medium',
                info: 'bg-sky-50 text-sky-700 border border-sky-200 font-medium',
                muted: 'bg-muted text-muted-foreground border border-border',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    }
);

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
    },
    class: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div :class="cn(badgeVariants({ variant }), props.class)">
        <slot />
    </div>
</template>
