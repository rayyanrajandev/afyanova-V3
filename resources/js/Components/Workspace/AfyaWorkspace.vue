<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';
import { ChevronLeft, PanelRight } from '@lucide/vue';
import AfyaPanelResizer from './AfyaPanelResizer.vue';

const props = defineProps({
    showSidebar: {
        type: Boolean,
        default: true,
    },
    showContext: {
        type: Boolean,
        default: false,
    },
    sidebarWidthMin: {
        type: Number,
        default: 220,
    },
    sidebarWidthMax: {
        type: Number,
        default: 320,
    },
    contextWidthMin: {
        type: Number,
        default: 280,
    },
    contextWidthMax: {
        type: Number,
        default: 480,
    },
});

const {
    preferences,
    cycleSidebarState,
    setSidebarState,
    setSidebarWidth,
    toggleContext,
    openContext,
    setContextWidth,
} = useWorkspacePreferences();

// Global Workspace Keyboard Shortcuts
const handleGlobalKeydown = (event) => {
    // Ctrl+B / Cmd+B: Toggle Sidebar
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') {
        event.preventDefault();
        cycleSidebarState();
    }
    // Ctrl+I / Cmd+I: Toggle Context Panel
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'i') {
        event.preventDefault();
        toggleContext();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <!-- Use relative position so panels can be absolutely positioned if needed, 
         but flex layout is best for taking up space without reserving hidden space -->
    <div class="flex-1 flex h-full w-full overflow-hidden bg-background relative">
        
        <!-- 1. LEFT PANEL: MODULE NAVIGATION -->
        <template v-if="showSidebar && preferences.sidebarState !== 'hidden'">
            <slot
                name="sidebar"
                :state="preferences.sidebarState"
                :width="preferences.sidebarWidth"
                :cycle="cycleSidebarState"
                :set-state="setSidebarState"
            />

            <!-- Left Splitter / Resizer (Only visible when expanded) -->
            <AfyaPanelResizer
                v-if="preferences.sidebarState === 'expanded'"
                side="left"
                v-model="preferences.sidebarWidth"
                :min="sidebarWidthMin"
                :max="sidebarWidthMax"
                @update:model-value="setSidebarWidth"
            />
        </template>

        <!-- 2. CENTER PANEL: PRIMARY WORK AREA -->
        <!-- flex: 1 and min-w-0 ensures it takes all remaining space and allows its children to shrink/truncate -->
        <div class="flex-1 flex flex-col h-full overflow-hidden min-w-0 bg-card z-0 transition-[min-width] duration-150 relative">
            <slot :preferences="preferences" />

            <!-- Sleek Edge Button to re-open Context Panel when closed -->
            <button
                v-if="showContext && !preferences.contextOpen"
                @click="openContext"
                type="button"
                class="absolute right-0 top-1/2 -translate-y-1/2 z-30 h-16 w-5 bg-card/95 hover:bg-muted text-muted-foreground hover:text-primary border-y border-l border-border rounded-l-md shadow-xs flex flex-col items-center justify-center gap-1 transition-all group"
                title="Open Inspector Panel (Ctrl+I)"
                aria-label="Open Inspector Panel"
            >
                <ChevronLeft class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform text-primary" />
            </button>
        </div>

        <!-- 3. RIGHT PANEL: CONTEXT INSPECTOR -->
        <template v-if="showContext && preferences.contextOpen">
            <!-- Right Splitter / Resizer -->
            <AfyaPanelResizer
                side="right"
                v-model="preferences.contextWidth"
                :min="contextWidthMin"
                :max="contextWidthMax"
                @update:model-value="setContextWidth"
            />

            <slot
                name="context"
                :open="preferences.contextOpen"
                :width="preferences.contextWidth"
                :close="toggleContext"
            />
        </template>
    </div>
</template>
