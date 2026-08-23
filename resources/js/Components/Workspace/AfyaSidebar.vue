<script setup>
import { computed } from 'vue';
import { PanelLeftClose, PanelLeftOpen, EyeOff } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = defineProps({
    title: {
        type: String,
        default: 'Navigation',
    },
    icon: {
        type: [String, Object, Function],
        default: null,
    },
    state: {
        type: String,
        default: 'expanded', // 'expanded' | 'collapsed' | 'hidden'
    },
    width: {
        type: Number,
        default: 240,
    },
});

const emit = defineEmits(['cycle-state', 'set-state']);

const isComponentIcon = computed(() => typeof props.icon === 'object' || typeof props.icon === 'function');
const isCollapsed = computed(() => props.state === 'collapsed');

const sidebarStyle = computed(() => {
    if (props.state === 'hidden') {
        return { width: '0px', padding: '0px', border: 'none' };
    }
    return {
        width: props.state === 'collapsed' ? '56px' : `${props.width}px`,
    };
});
</script>

<template>
    <aside
        v-if="state !== 'hidden'"
        :style="sidebarStyle"
        class="h-full flex flex-col bg-muted/30 border-r border-border/60 transition-[width] duration-150 ease-out flex-shrink-0 select-none overflow-hidden"
        aria-label="Module Navigation"
    >
        <!-- Clean Module Header (Standardized h-10 px-3 across all panels) -->
        <div class="h-10 px-3 border-b border-border/60 flex items-center justify-between bg-card flex-shrink-0">
            <!-- Expanded: Title & Icon -->
            <div v-if="!isCollapsed" class="flex items-center space-x-2 truncate min-w-0">
                <component
                    v-if="isComponentIcon"
                    :is="icon"
                    class="w-4 h-4 text-primary flex-shrink-0"
                    aria-hidden="true"
                />
                <span v-else-if="icon" class="text-sm flex-shrink-0" aria-hidden="true">{{ icon }}</span>
                <h2 class="text-[11px] font-bold uppercase tracking-wider text-foreground truncate">
                    {{ title }}
                </h2>
            </div>

            <!-- Collapsed: Centered Module Icon with tooltip -->
            <div v-else class="w-full flex justify-center items-center">
                <button
                    @click="emit('set-state', 'expanded')"
                    class="w-7 h-7 flex items-center justify-center rounded hover:bg-muted text-muted-foreground hover:text-foreground transition focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    title="Expand navigation (Ctrl+B)"
                    aria-label="Expand navigation"
                >
                    <component
                        v-if="isComponentIcon"
                        :is="icon"
                        class="w-4 h-4 text-primary"
                        aria-hidden="true"
                    />
                    <span v-else-if="icon" class="text-sm" aria-hidden="true">{{ icon }}</span>
                </button>
            </div>
        </div>

        <!-- Navigation Items Slot -->
        <nav class="flex-1 overflow-y-auto px-2 py-2.5 space-y-0.5">
            <slot />
        </nav>

        <!-- Consistent Bottom Controls: Unified Collapse/Expand Position -->
        <div class="p-2 border-t border-border/60 bg-card flex-shrink-0 flex items-center justify-between">
            <slot name="footer">
                <!-- 1. COLLAPSED STATE: Expand button in bottom footer -->
                <button
                    v-if="isCollapsed"
                    @click="emit('set-state', 'expanded')"
                    class="w-full h-7 flex items-center justify-center rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    title="Expand sidebar (Ctrl+B)"
                    aria-label="Expand sidebar"
                >
                    <PanelLeftOpen class="w-3.5 h-3.5" />
                </button>

                <!-- 2. EXPANDED STATE: Primary Collapse button in exact same bottom footer -->
                <div v-else class="flex items-center justify-between w-full gap-1">
                    <!-- Primary Action: Collapse to Rail -->
                    <button
                        @click="emit('set-state', 'collapsed')"
                        class="flex-1 flex items-center justify-between px-2 h-7 rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        title="Collapse sidebar (Ctrl+B)"
                        aria-label="Collapse sidebar"
                    >
                        <div class="flex items-center space-x-1.5">
                            <PanelLeftClose class="w-3.5 h-3.5" />
                            <span class="text-[11px] font-medium">Collapse</span>
                        </div>
                        <kbd class="px-1 py-0.2 text-[9px] font-mono bg-muted rounded border border-border text-muted-foreground">
                            ⌘B
                        </kbd>
                    </button>

                    <!-- Secondary Action: Subtle Hide Button -->
                    <button
                        @click="emit('set-state', 'hidden')"
                        class="h-7 w-7 flex items-center justify-center rounded text-muted-foreground/60 hover:text-muted-foreground hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        title="Hide navigation completely"
                        aria-label="Hide navigation completely"
                    >
                        <EyeOff class="w-3.5 h-3.5" />
                    </button>
                </div>
            </slot>
        </div>
    </aside>
</template>
