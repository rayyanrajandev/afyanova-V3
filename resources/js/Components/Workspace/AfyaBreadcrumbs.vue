<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav class="flex items-center text-xs text-muted-foreground font-medium" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-1.5 overflow-hidden">
            <li v-for="(item, index) in items" :key="index" class="flex items-center space-x-1.5 truncate">
                <span v-if="index > 0" class="text-muted-foreground/40 select-none">/</span>
                <component
                    :is="item.href ? Link : 'span'"
                    :href="item.href"
                    :class="[
                        item.active || index === items.length - 1
                            ? 'text-foreground font-bold truncate'
                            : 'text-muted-foreground hover:text-foreground transition-colors truncate',
                    ]"
                >
                    {{ item.label }}
                </component>
            </li>
        </ol>
    </nav>
</template>
