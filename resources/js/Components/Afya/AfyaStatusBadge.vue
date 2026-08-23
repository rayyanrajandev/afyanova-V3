<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    status: {
        type: String,
        required: true,
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
                classes: 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
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
                classes: 'bg-sky-50 text-sky-800 border-sky-200/80',
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
                classes: 'bg-amber-50 text-amber-900 border-amber-200/80',
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
                classes: 'bg-rose-50 text-rose-900 border-rose-200/80 font-bold',
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
                classes: 'bg-slate-100 text-slate-700 border-slate-200/80',
                dotClass: 'bg-slate-400',
                label: props.status,
            };

        default:
            return {
                classes: 'bg-slate-100 text-slate-800 border-slate-200',
                dotClass: 'bg-slate-400',
                label: props.status,
            };
    }
});
</script>

<template>
    <span
        :class="cn(
            'inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9px] font-bold border uppercase tracking-wider select-none whitespace-nowrap flex-shrink-0',
            styleConfig.classes,
            props.class
        )"
    >
        <span
            v-if="dot"
            :class="cn('w-1.5 h-1.5 rounded-full inline-block flex-shrink-0', styleConfig.dotClass)"
            aria-hidden="true"
        />
        <span>{{ styleConfig.label }}</span>
    </span>
</template>
