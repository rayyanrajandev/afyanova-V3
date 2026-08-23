import { ref, watch, onMounted, onUnmounted } from 'vue';

const STORAGE_KEY = 'afyanova_workspace_prefs_v2';

const defaultPreferences = {
    sidebarState: 'expanded', // 'expanded', 'collapsed', 'hidden'
    previousSidebarState: 'expanded', // 'expanded' | 'collapsed'
    sidebarWidth: 240,
    contextOpen: true,
    contextWidth: 340,
    activeModule: 'dashboard',
};

// Load initial UI preferences safely from localStorage with viewport awareness
const loadPreferences = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = JSON.parse(stored);
            return { ...defaultPreferences, ...parsed };
        }
    } catch (e) {
        console.warn('Unable to read workspace preferences from storage:', e);
    }

    // Viewport-aware initial defaults
    const initialPrefs = { ...defaultPreferences };
    if (typeof window !== 'undefined') {
        const w = window.innerWidth;
        if (w < 1024) {
            initialPrefs.sidebarState = 'hidden';
            initialPrefs.contextOpen = false;
        } else if (w < 1280) {
            initialPrefs.sidebarState = 'collapsed';
            initialPrefs.contextOpen = false;
        }
    }

    return initialPrefs;
};

const state = ref(loadPreferences());

// Sync only UI layout preferences to storage
watch(
    state,
    (newVal) => {
        try {
            // Strictly UI settings — NEVER store clinical or financial data
            const safePayload = {
                sidebarState: ['expanded', 'collapsed', 'hidden'].includes(newVal.sidebarState) ? newVal.sidebarState : 'expanded',
                previousSidebarState: ['expanded', 'collapsed'].includes(newVal.previousSidebarState) ? newVal.previousSidebarState : 'expanded',
                sidebarWidth: Math.min(Math.max(Number(newVal.sidebarWidth) || 240, 220), 320),
                contextOpen: !!newVal.contextOpen,
                contextWidth: Math.min(Math.max(Number(newVal.contextWidth) || 340, 280), 480),
                activeModule: String(newVal.activeModule || 'dashboard'),
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(safePayload));
        } catch (e) {
            console.warn('Unable to save workspace preferences to storage:', e);
        }
    },
    { deep: true }
);

// Adaptive Viewport Auto-Tuning
let viewportListenerAttached = false;
const initViewportListener = () => {
    if (typeof window === 'undefined' || viewportListenerAttached) return;
    viewportListenerAttached = true;

    let lastWidth = window.innerWidth;
    window.addEventListener('resize', () => {
        const currentWidth = window.innerWidth;
        // Crossed below 1280px threshold
        if (currentWidth < 1280 && lastWidth >= 1280) {
            if (state.value.sidebarState === 'expanded') {
                state.value.previousSidebarState = 'expanded';
                state.value.sidebarState = 'collapsed';
            }
        }
        // Crossed above 1280px threshold
        else if (currentWidth >= 1280 && lastWidth < 1280) {
            if (state.value.sidebarState === 'collapsed' && state.value.previousSidebarState === 'expanded') {
                state.value.sidebarState = 'expanded';
            }
        }
        lastWidth = currentWidth;
    }, { passive: true });
};

export function useWorkspacePreferences() {
    initViewportListener();

    const cycleSidebarState = () => {
        if (state.value.sidebarState === 'expanded') {
            state.value.previousSidebarState = 'expanded';
            state.value.sidebarState = 'collapsed';
        } else if (state.value.sidebarState === 'collapsed') {
            state.value.previousSidebarState = 'collapsed';
            state.value.sidebarState = 'hidden';
        } else {
            state.value.sidebarState = state.value.previousSidebarState || 'expanded';
        }
    };

    const setSidebarState = (newState) => {
        if (['expanded', 'collapsed', 'hidden'].includes(newState)) {
            if (newState === 'hidden' && state.value.sidebarState !== 'hidden') {
                state.value.previousSidebarState = state.value.sidebarState;
            }
            state.value.sidebarState = newState;
        }
    };

    const restoreSidebar = () => {
        if (state.value.sidebarState === 'hidden') {
            state.value.sidebarState = state.value.previousSidebarState || 'expanded';
        } else {
            setSidebarState('hidden');
        }
    };

    const setSidebarWidth = (width) => {
        state.value.sidebarWidth = Math.min(Math.max(width, 220), 320);
    };

    const toggleContext = () => {
        state.value.contextOpen = !state.value.contextOpen;
    };

    const openContext = () => {
        state.value.contextOpen = true;
    };

    const closeContext = () => {
        state.value.contextOpen = false;
    };

    const setContextWidth = (width) => {
        state.value.contextWidth = Math.min(Math.max(width, 280), 480);
    };

    const setActiveModule = (moduleName) => {
        state.value.activeModule = moduleName;
    };

    return {
        preferences: state,
        cycleSidebarState,
        setSidebarState,
        restoreSidebar,
        setSidebarWidth,
        toggleContext,
        openContext,
        closeContext,
        setContextWidth,
        setActiveModule,
    };
}
