<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div :class="cn('flex flex-col space-y-1 p-3 border-b border-border/50 bg-muted/20', props.class)">
        <slot />
    </div>
</template>
