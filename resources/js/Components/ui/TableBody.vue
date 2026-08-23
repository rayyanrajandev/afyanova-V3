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
    <tbody :class="cn('[&_tr:last-child]:border-0 divide-y divide-border/60', props.class)">
        <slot />
    </tbody>
</template>
