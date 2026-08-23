<script setup>
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const alertVariants = cva(
    'relative w-full rounded-md border p-2.5 text-xs [&>svg~*]:pl-6 [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-2.5 [&>svg]:top-2.5 [&>svg]:text-foreground',
    {
        variants: {
            variant: {
                default: 'bg-background text-foreground border-border',
                destructive: 'border-destructive/30 bg-destructive/5 text-destructive [&>svg]:text-destructive',
                warning: 'border-amber-300 bg-amber-50/80 text-amber-900 [&>svg]:text-amber-700',
                success: 'border-emerald-300 bg-emerald-50/80 text-emerald-900 [&>svg]:text-emerald-700',
                info: 'border-sky-300 bg-sky-50/80 text-sky-900 [&>svg]:text-sky-700',
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
    <div :class="cn(alertVariants({ variant }), props.class)" role="alert">
        <slot />
    </div>
</template>
