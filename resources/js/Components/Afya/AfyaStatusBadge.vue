<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: null,
    },
    domain: {
        type: String,
        default: 'generic', // 'patient' | 'clinical' | 'billing' | 'lab' | 'generic'
    },
    dot: {
        type: Boolean,
        default: false,
    },
    class: {
        type: String,
        default: '',
    },
});

// Map any hospital state to standardized design tokens
const styleConfig = computed(() => {
    const s = String(props.status || '').toLowerCase().trim().replace(/_/g, '-');

    switch (s) {
        // Positive / Completed / Paid / Normal
        case 'paid':
        case 'completed':
        case 'normal':
        case 'active':
        case 'dispensed':
        case 'success':
            return {
                classes: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
                dotClass: 'bg-emerald-500',
                label: props.status,
            };

        // In Progress / Partial / Processing / Collected
        case 'in-progress':
        case 'in progress':
        case 'partially-paid':
        case 'partial':
        case 'processing':
        case 'collected':
        case 'triage':
            return {
                classes: 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/20',
                dotClass: 'bg-sky-500 animate-pulse',
                label: props.status,
            };

        // Waiting / Pending / Attention / Abnormal
        case 'waiting':
        case 'pending':
        case 'attention':
        case 'abnormal':
        case 'draft':
            return {
                classes: 'bg-amber-500/10 text-amber-800 dark:text-amber-300 border-amber-500/20',
                dotClass: 'bg-amber-500',
                label: props.status,
            };

        // Critical / Overdue / Unpaid / Danger / Severe
        case 'critical':
        case 'overdue':
        case 'unpaid':
        case 'danger':
        case 'high':
            return {
                classes: 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20 font-bold',
                dotClass: 'bg-rose-600 animate-ping',
                label: props.status,
            };

        // Cancelled / Refunded / Inactive / Muted
        case 'cancelled':
        case 'refunded':
        case 'inactive':
        case 'closed':
        case 'ordered':
            return {
                classes: 'bg-muted text-muted-foreground border-border/60',
                dotClass: 'bg-slate-400',
                label: props.status,
            };

        default:
            return {
                classes: 'bg-muted text-foreground border-border/60',
                dotClass: 'bg-slate-400',
                label: props.status,
            };
    }
});
</script>

<template>
    <span
        :class="cn(
            'inline-flex items-center justify-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-semibold leading-none border select-none whitespace-nowrap shrink-0 shadow-2xs',
            styleConfig.classes,
            props.class
        )"
    >
        <span
            v-if="dot"
            :class="cn('w-1.5 h-1.5 rounded-full inline-block shrink-0', styleConfig.dotClass)"
            aria-hidden="true"
        />
        <span class="inline-flex items-center">{{ props.label || styleConfig.label }}</span>
    </span>
</template>
