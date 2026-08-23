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
    <thead :class="cn('bg-muted/40 text-[10px] uppercase tracking-wider text-muted-foreground font-semibold', props.class)">
        <slot />
    </thead>
</template>
