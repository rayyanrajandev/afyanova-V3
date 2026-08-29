<script setup>
import { ref } from 'vue';
import { useTheme } from '@/Composables/useTheme';
import { Sun, Moon, Monitor, Check } from '@lucide/vue';
import Dropdown from '@/Components/Dropdown.vue';

const { theme, resolvedTheme, setTheme } = useTheme();

const options = [
    { id: 'light', label: 'Light', icon: Sun },
    { id: 'dark', label: 'Dark', icon: Moon },
    { id: 'system', label: 'System', icon: Monitor },
];
</script>

<template>
    <Dropdown align="right" width="36" content-classes="p-1 bg-card text-card-foreground border border-border">
        <template #trigger>
            <button
                type="button"
                class="h-7 w-7 flex items-center justify-center text-muted-foreground hover:text-foreground rounded hover:bg-muted transition-colors focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                :title="`Theme: ${theme} (Current: ${resolvedTheme})`"
                aria-label="Toggle Color Theme"
            >
                <Sun v-if="theme === 'light'" class="w-3.5 h-3.5 text-amber-500 transition-transform duration-200 rotate-0" />
                <Moon v-else-if="theme === 'dark'" class="w-3.5 h-3.5 text-sky-400 transition-transform duration-200 rotate-0" />
                <Monitor v-else class="w-3.5 h-3.5 text-muted-foreground" />
            </button>
        </template>

        <template #content>
            <div class="px-2 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider border-b border-border/60">
                Appearance
            </div>
            <div class="py-1 space-y-0.5">
                <button
                    v-for="opt in options"
                    :key="opt.id"
                    type="button"
                    @click="setTheme(opt.id)"
                    class="w-full text-left px-2 py-1.5 text-xs flex items-center justify-between rounded hover:bg-muted transition"
                    :class="theme === opt.id ? 'bg-primary/10 text-primary font-bold' : 'text-foreground'"
                >
                    <div class="flex items-center gap-2">
                        <component :is="opt.icon" class="w-3.5 h-3.5" :class="theme === opt.id ? 'text-primary' : 'text-muted-foreground'" />
                        <span>{{ opt.label }}</span>
                    </div>
                    <Check v-if="theme === opt.id" class="w-3 h-3 text-primary shrink-0" />
                </button>
            </div>
        </template>
    </Dropdown>
</template>
