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
    <th :class="cn('sticky top-0 z-20 bg-muted/95 backdrop-blur-xs h-8 px-3 text-left align-middle font-semibold text-muted-foreground border-b border-border/50 has-[[role=checkbox]]:pr-0 whitespace-nowrap', props.class)">
        <slot />
    </th>
</template>
