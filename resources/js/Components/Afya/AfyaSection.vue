<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: '',
    },
    class: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div :class="cn('space-y-2', props.class)">
        <!-- Section Header -->
        <div class="flex items-center justify-between border-b border-border pb-1.5 pt-1">
            <div class="flex items-center space-x-2 min-w-0">
                <span v-if="icon" class="text-sm" aria-hidden="true">{{ icon }}</span>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-foreground">
                        {{ title }}
                    </h4>
                    <p v-if="subtitle" class="text-[11px] text-muted-foreground mt-0.5">
                        {{ subtitle }}
                    </p>
                </div>
            </div>

            <div class="flex items-center space-x-2 shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <!-- Section Content -->
        <div>
            <slot />
        </div>
    </div>
</template>
