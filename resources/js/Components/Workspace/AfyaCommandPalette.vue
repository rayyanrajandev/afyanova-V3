<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Search, 
    LayoutDashboard, 
    Stethoscope, 
    Receipt, 
    Pill, 
    Users, 
    Clock, 
    Palette, 
    Plus, 
    Calendar,
    ArrowRight,
    X,
    Command,
    Sidebar,
    PanelRight,
    DollarSign,
    UserPlus,
    Activity
} from '@lucide/vue';
import { cn } from '@/lib/utils';
import { useWorkspacePreferences } from '@/Composables/useWorkspacePreferences';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:open', 'close']);

const query = ref('');
const selectedIndex = ref(0);
const inputRef = ref(null);

const { cycleSidebarState, toggleContext } = useWorkspacePreferences();

const navigationItems = [
    // Primary Workspaces
    { id: 'dash', label: 'Hospital Telemetry Dashboard', group: 'Workspaces', icon: LayoutDashboard, route: '/dashboard', shortcut: '⌘D' },
    { id: 'pat', label: 'Master Patient Index (MPI)', group: 'Workspaces', icon: Users, route: '/patients', shortcut: '⌘M' },
    { id: 'clin', label: 'Clinical Consultation Workstation', group: 'Workspaces', icon: Stethoscope, route: '/workspace/clinical', shortcut: '⌘E' },
    { id: 'pharm', label: 'Pharmacy Dispensary Desk', group: 'Workspaces', icon: Pill, route: '/pharmacy', shortcut: '⌘P' },
    { id: 'bill', label: 'Cashier POS & Billing Desk', group: 'Workspaces', icon: DollarSign, route: '/billing/desk', shortcut: '⌘$' },
    { id: 'queue', label: 'Live Triage & Patient Queue', group: 'Workspaces', icon: Clock, route: '/queue', shortcut: '⌘Q' },
    { id: 'app', label: 'Appointments Calendar & Scheduling', group: 'Workspaces', icon: Calendar, route: '/appointments', shortcut: '⌘A' },
    
    // Quick Actions
    { id: 'reg', label: 'Register New Patient (MPI)', group: 'Quick Actions', icon: UserPlus, route: '/patients/create', shortcut: '⌘N' },
    { id: 'vitals', label: 'Record Triage Vitals', group: 'Quick Actions', icon: Activity, route: '/queue' },
    { id: 'found', label: 'Design System Foundation Demo', group: 'Quick Actions', icon: Palette, route: '/workspace/foundation' },

    // Workspace Controls
    { id: 'toggle-sidebar', label: 'Toggle Module Navigation Sidebar', group: 'Workspace Controls', icon: Sidebar, action: 'sidebar', shortcut: '⌘B' },
    { id: 'toggle-context', label: 'Toggle Context Inspector Panel', group: 'Workspace Controls', icon: PanelRight, action: 'context', shortcut: '⌘I' },
];

const filteredItems = computed(() => {
    if (!query.value.trim()) return navigationItems;
    const q = query.value.toLowerCase();
    return navigationItems.filter(item => 
        item.label.toLowerCase().includes(q) || 
        item.group.toLowerCase().includes(q) ||
        (item.shortcut && item.shortcut.toLowerCase().includes(q))
    );
});

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        selectedIndex.value = 0;
        nextTick(() => {
            inputRef.value?.focus();
        });
    }
});

const handleSelect = (item) => {
    emit('close');
    emit('update:open', false);
    
    if (item.action === 'sidebar') {
        cycleSidebarState();
    } else if (item.action === 'context') {
        toggleContext();
    } else if (item.route) {
        router.get(item.route);
    }
};

const handleKeydown = (e) => {
    if (!props.open) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (filteredItems.value.length > 0) {
            selectedIndex.value = (selectedIndex.value + 1) % filteredItems.value.length;
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (filteredItems.value.length > 0) {
            selectedIndex.value = (selectedIndex.value - 1 + filteredItems.value.length) % filteredItems.value.length;
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (filteredItems.value[selectedIndex.value]) {
            handleSelect(filteredItems.value[selectedIndex.value]);
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        emit('close');
        emit('update:open', false);
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center pt-20 sm:pt-28 px-4 bg-black/60 backdrop-blur-[2px]"
                @click.self="emit('close')"
            >
                <div
                    class="w-full max-w-lg rounded-lg border border-border bg-card shadow-2xl overflow-hidden transition-all text-card-foreground animate-in fade-in-0 zoom-in-95"
                    role="dialog"
                    aria-modal="true"
                >
                    <!-- Search Input Bar -->
                    <div class="flex items-center px-3.5 border-b border-border h-11 bg-card">
                        <Search class="w-4 h-4 text-muted-foreground mr-2.5 flex-shrink-0" />
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            placeholder="Type a command, destination, or shortcut (⌘K)..."
                            class="w-full bg-transparent border-0 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-0 p-0"
                        />
                        <kbd class="hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-mono bg-muted text-muted-foreground rounded border border-border">
                            ESC
                        </kbd>
                    </div>

                    <!-- Command Results List -->
                    <div class="max-h-80 overflow-y-auto p-1.5 space-y-0.5 text-xs">
                        <div
                            v-for="(item, idx) in filteredItems"
                            :key="item.id"
                            @click="handleSelect(item)"
                            @mouseenter="selectedIndex = idx"
                            :class="cn(
                                'flex items-center justify-between px-2.5 py-2 rounded cursor-pointer transition select-none',
                                selectedIndex === idx ? 'bg-primary/10 text-primary font-medium' : 'text-foreground hover:bg-muted'
                            )"
                        >
                            <div class="flex items-center space-x-2.5 min-w-0">
                                <component
                                    :is="item.icon"
                                    class="w-4 h-4 flex-shrink-0"
                                    :class="selectedIndex === idx ? 'text-primary' : 'text-muted-foreground'"
                                />
                                <span class="truncate text-[11.5px]">{{ item.label }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <kbd v-if="item.shortcut" class="text-[9px] font-mono text-muted-foreground px-1.5 py-0.5 rounded bg-muted border border-border">
                                    {{ item.shortcut }}
                                </kbd>
                                <span class="text-[10px] text-muted-foreground px-1.5 py-0.5 rounded bg-muted/60 font-mono">
                                    {{ item.group }}
                                </span>
                            </div>
                        </div>

                        <div v-if="filteredItems.length === 0" class="py-8 text-center text-muted-foreground text-xs">
                            No matching commands found for "{{ query }}".
                        </div>
                    </div>

                    <!-- Footer Help -->
                    <div class="px-3 py-1.5 bg-muted/40 border-t border-border flex items-center justify-between text-[10px] text-muted-foreground">
                        <div class="flex items-center gap-3">
                            <span><kbd class="font-mono bg-card px-1 py-0.5 rounded border border-border">↑↓</kbd> Navigate</span>
                            <span><kbd class="font-mono bg-card px-1 py-0.5 rounded border border-border">↵</kbd> Select</span>
                            <span><kbd class="font-mono bg-card px-1 py-0.5 rounded border border-border">esc</kbd> Dismiss</span>
                        </div>
                        <span>AfyaNova Command Center</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
