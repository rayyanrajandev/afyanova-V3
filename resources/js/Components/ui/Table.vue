<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: {
        type: String,
        default: '',
    },
    wrapperClass: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div :class="cn('w-full relative', props.wrapperClass || 'overflow-x-auto')">
        <table :class="cn('w-full caption-bottom text-xs border-separate border-spacing-0', props.class)">
            <slot />
        </table>
    </div>
</template>
