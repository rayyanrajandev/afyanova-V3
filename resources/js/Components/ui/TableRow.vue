<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: {
        type: String,
        default: '',
    },
    selected: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <tr
        :class="cn(
            'border-b border-border/70 last:border-b-0 transition-colors hover:bg-muted/40 data-[state=selected]:bg-muted',
            selected && 'bg-primary/5 hover:bg-primary/10',
            props.class
        )"
    >
        <slot />
    </tr>
</template>
