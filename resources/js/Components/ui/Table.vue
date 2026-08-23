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
    <div class="w-full overflow-x-auto">
        <table :class="cn('w-full caption-bottom text-xs border-collapse', props.class)">
            <slot />
        </table>
    </div>
</template>
