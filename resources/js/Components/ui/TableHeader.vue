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
    <thead :class="cn('sticky top-0 z-20 bg-muted/95 backdrop-blur-xs text-[10px] uppercase tracking-wider text-muted-foreground font-semibold', props.class)">
        <slot />
    </thead>
</template>
