<script setup>
import AfyaBreadcrumbs from './AfyaBreadcrumbs.vue';

const props = defineProps({
    title: {
        type: String,
        default: null,
    },
    subtitle: {
        type: String,
        default: null,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    flush: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <main class="flex-1 flex flex-col h-full bg-background overflow-hidden min-w-0" role="main">
        <!-- Main Top Bar (Standardized h-10 px-3 across all panels) -->
        <header
            v-if="title || breadcrumbs.length > 0 || $slots.header || $slots.actions"
            class="h-10 px-3 border-b border-border/60 flex items-center justify-between bg-card flex-shrink-0 z-10 select-none"
        >
            <div class="flex items-center space-x-2.5 min-w-0">
                <AfyaBreadcrumbs v-if="breadcrumbs.length > 0" :items="breadcrumbs" />
                <h1 v-else-if="title" class="text-xs font-bold text-foreground truncate">
                    {{ title }}
                </h1>
                <span v-if="subtitle" class="text-[11px] text-muted-foreground font-normal truncate hidden sm:inline">
                    {{ subtitle }}
                </span>
                <slot name="header" />
            </div>

            <div class="flex items-center space-x-2 flex-shrink-0">
                <slot name="actions" />
            </div>
        </header>

        <!-- Main Body Content (Standardized p-3 padding to match sidebar & context panel) -->
        <div
            class="flex-1 overflow-y-auto"
            :class="flush ? '' : 'p-3'"
        >
            <slot />
        </div>

        <!-- Optional Footer -->
        <footer v-if="$slots.footer" class="px-3 py-2 border-t border-border/60 bg-card flex-shrink-0">
            <slot name="footer" />
        </footer>
    </main>
</template>
