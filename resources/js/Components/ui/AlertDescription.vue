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
    <div :class="cn('text-[11px] leading-relaxed [&_p]:leading-relaxed text-muted-foreground', props.class)">
        <slot />
    </div>
</template>
