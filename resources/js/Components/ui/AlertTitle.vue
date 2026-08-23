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
    <h5 :class="cn('mb-0.5 font-semibold leading-none tracking-tight text-xs', props.class)">
        <slot />
    </h5>
</template>
