<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-2.5 py-1.5 text-start text-xs leading-tight text-foreground transition-colors hover:bg-muted focus:bg-muted focus:outline-hidden rounded"
    >
        <slot />
    </Link>
</template>
