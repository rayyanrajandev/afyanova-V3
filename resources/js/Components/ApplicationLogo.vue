<script setup>
const props = defineProps({
    inverted: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="flex items-center gap-2.5 font-bold tracking-tight select-none">
        <div class="w-8 h-8 rounded-md bg-primary flex items-center justify-center text-primary-foreground shadow-xs flex-shrink-0">
            <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
                <path d="M3.22 12H9.5l1.5-3 2 6 1.5-3h6.28" />
            </svg>
        </div>
        <div class="flex flex-col text-left">
            <div class="flex items-center gap-1">
                <span 
                    class="text-sm font-extrabold leading-none transition-colors"
                    :class="inverted ? 'text-white' : 'text-foreground'"
                >
                    AfyaNova
                </span>
                <span 
                    class="text-[9px] font-mono font-bold px-1 py-0.2 rounded border"
                    :class="inverted ? 'bg-primary/30 text-emerald-300 border-primary/40' : 'bg-primary/10 text-primary border-primary/20'"
                >
                    V3
                </span>
            </div>
            <span 
                class="text-[9px] font-medium uppercase tracking-wider leading-none mt-0.5"
                :class="inverted ? 'text-slate-300' : 'text-muted-foreground'"
            >
                Hospital Intelligence
            </span>
        </div>
    </div>
</template>
