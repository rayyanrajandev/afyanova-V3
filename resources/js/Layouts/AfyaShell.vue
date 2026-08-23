<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, AlertTriangle, X, Clock, LogOut } from 'lucide-vue-next';
import AfyaHeader from '@/Components/Workspace/AfyaHeader.vue';
import { useIdleTimeout } from '@/Composables/useIdleTimeout';

const props = defineProps({
    activeModule: {
        type: String,
        default: 'dashboard',
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error || (page.props.errors && Object.keys(page.props.errors).length > 0 ? 'Validation error occurred' : null));

// Clinical Session Idle-Timeout management (30 min timeout, 2 min warning)
const { isWarningVisible, remainingSeconds, extendSession, forceLogout } = useIdleTimeout({
    timeoutMinutes: 30,
    warningMinutes: 2,
});

const formattedCountdown = computed(() => {
    const mins = Math.floor(remainingSeconds.value / 60);
    const secs = remainingSeconds.value % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
});
</script>

<template>
    <div class="h-screen w-screen flex flex-col overflow-hidden bg-background text-foreground antialiased font-sans">
        <!-- Persistent Global Header (48px) -->
        <AfyaHeader :active-module="activeModule" />

        <!-- Notification Banner / Flash Toast -->
        <div v-if="flashSuccess" class="bg-emerald-600 text-white text-xs px-4 py-1.5 flex items-center justify-between shadow-xs transition z-50">
            <div class="flex items-center space-x-2">
                <CheckCircle2 class="w-3.5 h-3.5" />
                <span class="font-medium">{{ flashSuccess }}</span>
            </div>
            <button @click="page.props.flash.success = null" class="text-white/80 hover:text-white p-0.5 rounded">
                <X class="w-3.5 h-3.5" />
            </button>
        </div>

        <div v-if="flashError" class="bg-destructive text-destructive-foreground text-xs px-4 py-1.5 flex items-center justify-between shadow-xs transition z-50">
            <div class="flex items-center space-x-2">
                <AlertTriangle class="w-3.5 h-3.5" />
                <span class="font-medium">{{ flashError }}</span>
            </div>
            <button @click="page.props.flash.error = null" class="text-white/80 hover:text-white p-0.5 rounded">
                <X class="w-3.5 h-3.5" />
            </button>
        </div>

        <!-- Session Inactivity Warning Modal -->
        <div v-if="isWarningVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-card text-card-foreground border border-border shadow-lg rounded-xl max-w-md w-full p-6 space-y-4 animate-in fade-in-50 zoom-in-95">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0">
                        <Clock class="w-5 h-5 text-amber-500 animate-pulse" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">Session Inactivity Warning</h3>
                        <p class="text-xs text-muted-foreground">Workstation security policy</p>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground leading-relaxed">
                    You have been inactive for an extended period. For patient data privacy on shared clinical workstations, your session will automatically terminate in:
                </p>

                <div class="py-2.5 px-4 bg-muted/40 rounded-lg border border-border text-center font-mono text-xl font-bold text-amber-500">
                    {{ formattedCountdown }}
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="forceLogout"
                        class="px-3 py-2 rounded-lg text-xs font-semibold border border-border bg-background hover:bg-muted text-muted-foreground hover:text-foreground flex items-center gap-1.5 transition"
                    >
                        <LogOut class="w-3.5 h-3.5" />
                        <span>Sign Out Now</span>
                    </button>
                    <button
                        type="button"
                        @click="extendSession"
                        class="px-4 py-2 rounded-lg text-xs font-bold bg-primary hover:bg-primary/90 text-primary-foreground shadow-xs transition"
                    >
                        Stay Signed In
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Workspace Shell Content -->
        <div class="flex-1 flex overflow-hidden min-h-0 relative">
            <slot />
        </div>
    </div>
</template>
