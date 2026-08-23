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
    <div :class="cn('rounded-lg bg-card text-card-foreground shadow-2xs transition-all', props.class)">
        <slot />
    </div>
</template>
