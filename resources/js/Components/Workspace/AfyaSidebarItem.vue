<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    icon: {
        type: [String, Object, Function],
        default: null,
    },
    active: {
        type: Boolean,
        default: false,
    },
    badge: {
        type: [String, Number],
        default: null,
    },
    badgeColor: {
        type: String,
        default: 'bg-primary/10 text-primary border border-primary/20',
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    href: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['click']);

const isComponentIcon = computed(() => typeof props.icon === 'object' || typeof props.icon === 'function');

const itemClasses = computed(() => [
    'group flex items-center w-full px-2 py-1.5 text-xs font-medium rounded transition-colors relative select-none',
    props.active
        ? 'bg-primary/10 text-primary font-semibold'
        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    props.collapsed ? 'justify-center' : 'justify-between',
]);
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :class="itemClasses"
        :title="collapsed ? label : null"
        :aria-label="label"
        :aria-current="active ? 'page' : null"
        @click="emit('click')"
    >
        <!-- Active indicator bar on left edge -->
        <span
            v-if="active && !collapsed"
            class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-r bg-primary"
            aria-hidden="true"
        />

        <div class="flex items-center space-x-2 truncate min-w-0">
            <component
                v-if="isComponentIcon"
                :is="icon"
                class="w-3.5 h-3.5 flex-shrink-0 transition-transform group-hover:scale-105"
                :class="active ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground'"
                aria-hidden="true"
            />
            <span v-else-if="icon" class="text-xs flex-shrink-0" aria-hidden="true">{{ icon }}</span>
            <span v-if="!collapsed" class="truncate text-[11.5px]">{{ label }}</span>
        </div>

        <span
            v-if="!collapsed && badge !== null && badge !== undefined && badge !== 0 && badge !== '0' && badge !== ''"
            class="ml-auto inline-block py-0.2 px-1.5 text-[10px] font-semibold rounded"
            :class="badgeColor"
        >
            {{ badge }}
        </span>

        <!-- Collapsed Mini Dot Badge -->
        <span
            v-if="collapsed && badge !== null && badge !== undefined && badge !== 0 && badge !== '0' && badge !== ''"
            class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full bg-primary"
            aria-hidden="true"
        />
    </component>
</template>
