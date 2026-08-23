<script setup>
import { cn } from '@/lib/utils';
import Input from '@/Components/ui/Input.vue';

const props = defineProps({
    searchModel: {
        type: String,
        default: '',
    },
    searchPlaceholder: {
        type: String,
        default: 'Filter or search records...',
    },
    class: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:searchModel']);
</script>

<template>
    <div
        :class="cn(
            'flex flex-wrap items-center justify-between gap-2 p-2 bg-card rounded-md border border-border text-xs',
            props.class
        )"
    >
        <!-- Search Input Box -->
        <div class="relative flex-1 min-w-[200px] max-w-xs">
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-muted-foreground">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input
                type="text"
                :value="searchModel"
                :placeholder="searchPlaceholder"
                class="w-full pl-8 pr-2.5 py-1 h-7 text-xs rounded border border-input bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring focus:border-ring"
                @input="emit('update:searchModel', $event.target.value)"
            />
        </div>

        <!-- Filter Slots & Actions -->
        <div class="flex items-center gap-2 flex-wrap">
            <slot />
        </div>
    </div>
</template>
