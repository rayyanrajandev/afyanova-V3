import { ref, computed } from 'vue';

const state = ref({
    module: null,
    section: null,
    resource: null,
    context: null,
});

export function useWorkspace() {
    const setWorkspaceState = (newState) => {
        if (newState.module !== undefined) state.value.module = newState.module;
        if (newState.section !== undefined) state.value.section = newState.section;
        if (newState.resource !== undefined) state.value.resource = newState.resource;
        if (newState.context !== undefined) state.value.context = newState.context;
    };

    const clearWorkspaceState = () => {
        state.value = {
            module: null,
            section: null,
            resource: null,
            context: null,
        };
    };

    const hasContext = computed(() => {
        return !!state.value.resource && !!state.value.context;
    });

    return {
        workspaceState: state,
        setWorkspaceState,
        clearWorkspaceState,
        hasContext
    };
}
