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
    <h3 :class="cn('text-xs font-semibold leading-none tracking-tight text-foreground flex items-center justify-between', props.class)">
        <slot />
    </h3>
</template>
