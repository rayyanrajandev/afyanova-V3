<script setup>
import { computed } from 'vue';
import { AlertTriangle, Zap, Info, X } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        default: '',
    },
    severity: {
        type: String,
        default: 'critical', // 'critical' | 'warning' | 'info'
    },
    dismissible: {
        type: Boolean,
        default: false,
    },
    class: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['dismiss']);

const severityStyles = computed(() => {
    switch (props.severity) {
        case 'critical':
            return {
                wrapper: 'bg-rose-50/90 border-rose-200 text-rose-950',
                icon: AlertTriangle,
                iconClass: 'text-rose-600',
                title: 'text-rose-900 font-bold',
            };
        case 'warning':
            return {
                wrapper: 'bg-amber-50/90 border-amber-200 text-amber-950',
                icon: Zap,
                iconClass: 'text-amber-600',
                title: 'text-amber-900 font-bold',
            };
        case 'info':
        default:
            return {
                wrapper: 'bg-sky-50/90 border-sky-200 text-sky-950',
                icon: Info,
                iconClass: 'text-sky-600',
                title: 'text-sky-900 font-bold',
            };
    }
});
</script>

<template>
    <div
        :class="cn(
            'flex items-start justify-between gap-2.5 p-2 rounded border text-xs shadow-2xs',
            severityStyles.wrapper,
            props.class
        )"
        role="alert"
    >
        <div class="flex items-start space-x-2 min-w-0">
            <component
                :is="severityStyles.icon"
                class="w-4 h-4 flex-shrink-0 mt-0.5"
                :class="severityStyles.iconClass"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <div :class="severityStyles.title" class="truncate text-xs">
                    {{ title }}
                </div>
                <div v-if="message" class="text-[11px] text-foreground/80 mt-0.5 leading-snug">
                    {{ message }}
                </div>
                <slot />
            </div>
        </div>

        <button
            v-if="dismissible"
            @click="emit('dismiss')"
            class="text-muted-foreground hover:text-foreground p-0.5 rounded transition focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            title="Dismiss alert"
            aria-label="Dismiss alert"
        >
            <X class="w-3.5 h-3.5" />
        </button>
    </div>
</template>
