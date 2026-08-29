<script setup>
import { computed } from 'vue';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from '@lucide/vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    currentPage: {
        type: Number,
        default: 1,
    },
    perPage: {
        type: Number,
        default: 25,
    },
    totalItems: {
        type: Number,
        required: true,
    },
    perPageOptions: {
        type: Array,
        default: () => [10, 25, 50, 100],
    },
    itemLabel: {
        type: String,
        default: 'items',
    },
});

const emit = defineEmits(['update:currentPage', 'update:perPage', 'page-change']);

const totalPages = computed(() => Math.ceil(props.totalItems / props.perPage) || 1);
const fromItem = computed(() => (props.totalItems === 0 ? 0 : (props.currentPage - 1) * props.perPage + 1));
const toItem = computed(() => Math.min(props.currentPage * props.perPage, props.totalItems));

const setPage = (page) => {
    const target = Math.max(1, Math.min(page, totalPages.value));
    emit('update:currentPage', target);
    emit('page-change', target);
};

const handlePerPageChange = (e) => {
    const newPerPage = Number(e.target.value);
    emit('update:perPage', newPerPage);
    emit('update:currentPage', 1);
    emit('page-change', 1);
};
</script>

<template>
    <div class="px-3 py-2 border-t border-border/50 bg-muted/20 flex flex-wrap items-center justify-between gap-2 text-xs select-none">
        <div class="text-muted-foreground text-[11px]">
            Showing <span class="font-bold text-foreground">{{ fromItem }}</span>
            to <span class="font-bold text-foreground">{{ toItem }}</span>
            of <span class="font-bold text-foreground">{{ totalItems }}</span> {{ itemLabel }}
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                <span>Per page:</span>
                <select
                    :value="perPage"
                    @change="handlePerPageChange"
                    class="h-6 text-xs bg-background border border-border rounded px-1.5 py-0 focus:outline-none focus:border-primary text-foreground"
                >
                    <option v-for="size in perPageOptions" :key="size" :value="size">{{ size }}</option>
                </select>
            </div>

            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-6 w-6 p-0"
                    :disabled="currentPage <= 1"
                    @click="setPage(1)"
                    title="First page"
                >
                    <ChevronsLeft class="w-3.5 h-3.5" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-6 w-6 p-0"
                    :disabled="currentPage <= 1"
                    @click="setPage(currentPage - 1)"
                    title="Previous page"
                >
                    <ChevronLeft class="w-3.5 h-3.5" />
                </Button>
                <span class="text-[11px] font-mono text-muted-foreground px-1.5 min-w-[48px] text-center">
                    {{ currentPage }} / {{ totalPages }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-6 w-6 p-0"
                    :disabled="currentPage >= totalPages"
                    @click="setPage(currentPage + 1)"
                    title="Next page"
                >
                    <ChevronRight class="w-3.5 h-3.5" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-6 w-6 p-0"
                    :disabled="currentPage >= totalPages"
                    @click="setPage(totalPages)"
                    title="Last page"
                >
                    <ChevronsRight class="w-3.5 h-3.5" />
                </Button>
            </div>
        </div>
    </div>
</template>
