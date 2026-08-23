<script setup>
import { computed } from 'vue';
import { X, Info } from '@lucide/vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Context Inspector',
    },
    icon: {
        type: [String, Object, Function],
        default: () => Info,
    },
    open: {
        type: Boolean,
        default: true,
    },
    width: {
        type: Number,
        default: 340,
    },
});

const emit = defineEmits(['close', 'toggle']);

const isComponentIcon = computed(() => typeof props.icon === 'object' || typeof props.icon === 'function');

const panelStyle = computed(() => ({
    width: `${props.width}px`,
}));
</script>

<template>
    <aside
        v-if="open"
        :style="panelStyle"
        class="h-full flex flex-col bg-muted/30 border-l border-border/60 transition-[width] duration-150 ease-out flex-shrink-0 select-none overflow-hidden"
        aria-label="Task Context Panel"
    >
        <!-- High-Density Meta Workstation Header (Aligned with Left Sidebar and Center Main) -->
        <div class="h-10 px-3 border-b border-border/60 flex items-center justify-between bg-card flex-shrink-0">
            <div class="flex items-center space-x-2 truncate min-w-0 pr-1">
                <component
                    v-if="isComponentIcon"
                    :is="icon"
                    class="w-4 h-4 text-primary flex-shrink-0"
                    aria-hidden="true"
                />
                <span v-else-if="icon" class="text-sm flex-shrink-0" aria-hidden="true">{{ icon }}</span>
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-foreground truncate">
                    {{ title }}
                </h3>
            </div>

            <div class="flex items-center space-x-1 flex-shrink-0">
                <slot name="header-actions" />
                <button
                    @click="emit('close')"
                    class="h-6 w-6 flex items-center justify-center rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    title="Close context panel (Ctrl+I)"
                    aria-label="Close context panel"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>

        <!-- High-Density Scrollable Body (Standardized p-3 padding to match center workstation) -->
        <div class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-2.5 text-xs text-foreground">
            <slot />
        </div>

        <!-- Optional Context Footer -->
        <div v-if="$slots.footer" class="px-3 py-2 border-t border-border/60 bg-card flex-shrink-0 text-xs">
            <slot name="footer" />
        </div>
    </aside>
</template>

<style scoped>
/* Ultra-thin scrollbar for narrow inspector panel */
div::-webkit-scrollbar {
    width: 4px;
}
div::-webkit-scrollbar-track {
    background: transparent;
}
div::-webkit-scrollbar-thumb {
    background: hsl(var(--border) / 0.8);
    border-radius: 4px;
}
div::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--muted-foreground) / 0.5);
}
</style>
