<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { CheckCircle2, AlertTriangle, X, Clock, LogOut, ShieldAlert, UserCheck } from 'lucide-vue-next';
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

// Superadmin Impersonation Session
const impersonation = computed(() => page.props.impersonation);
const exitImpersonation = () => {
    router.post(route('superadmin.exit-impersonation'));
};

// Break-Glass State & Countdown
const breakGlass = computed(() => page.props.breakGlass);
const breakGlassRemaining = ref(0);
let breakGlassInterval = null;

const updateBreakGlassTimer = () => {
    if (breakGlass.value?.expiresAt) {
        const remaining = Math.max(0, Math.floor(breakGlass.value.expiresAt - Date.now() / 1000));
        breakGlassRemaining.value = remaining;
    } else {
        breakGlassRemaining.value = 0;
    }
};

const formattedBreakGlassTime = computed(() => {
    const mins = Math.floor(breakGlassRemaining.value / 60);
    const secs = breakGlassRemaining.value % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
});

const revokeBreakGlass = () => {
    router.delete(route('clinical.break-glass.destroy'), {
        preserveScroll: true,
    });
};

watch(breakGlass, (bg) => {
    if (bg?.expiresAt) {
        updateBreakGlassTimer();
        if (!breakGlassInterval) {
            breakGlassInterval = setInterval(updateBreakGlassTimer, 1000);
        }
    } else {
        if (breakGlassInterval) {
            clearInterval(breakGlassInterval);
            breakGlassInterval = null;
        }
    }
}, { immediate: true });

onUnmounted(() => {
    if (breakGlassInterval) clearInterval(breakGlassInterval);
});

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

        <!-- Superadmin Audited Support Impersonation Banner -->
        <div v-if="impersonation && impersonation.is_active" class="bg-purple-700 dark:bg-purple-800 text-white text-xs px-4 py-1.5 flex items-center justify-between shadow-xs transition z-50">
            <div class="flex items-center space-x-2">
                <UserCheck class="w-4 h-4 text-purple-200 animate-pulse" />
                <span class="font-bold tracking-wide">SUPERADMIN SUPPORT SESSION:</span>
                <span class="bg-black/30 text-white px-2 py-0.5 rounded font-mono text-[11px] font-bold">
                    Viewing as {{ impersonation.target_user_name }} ({{ impersonation.target_tenant_name }})
                </span>
                <span class="hidden md:inline text-purple-200 text-[11px]">— Initiated by {{ impersonation.superadmin_name }} (Audited)</span>
            </div>
            <button 
                type="button"
                @click="exitImpersonation" 
                class="bg-black/30 hover:bg-black/50 text-white text-[11px] font-bold px-3 py-0.5 rounded transition border border-white/40 flex items-center gap-1.5 cursor-pointer shadow-xs"
            >
                <LogOut class="w-3 h-3" />
                Exit Support Impersonation
            </button>
        </div>

        <!-- Break-Glass Emergency Override Banner -->
        <div v-if="breakGlass && breakGlassRemaining > 0" class="bg-amber-600 dark:bg-amber-700 text-white text-xs px-4 py-1.5 flex items-center justify-between shadow-xs transition z-50">
            <div class="flex items-center space-x-2">
                <ShieldAlert class="w-4 h-4 text-amber-100" />
                <span class="font-bold">BREAK-GLASS EMERGENCY OVERRIDE ACTIVE</span>
                <span class="text-white font-mono text-[11px] bg-black/25 px-1.5 py-0.5 rounded font-bold">Expires in {{ formattedBreakGlassTime }}</span>
                <span class="hidden sm:inline text-white/85 text-[11px]">— Audited cross-facility access active.</span>
            </div>
            <button 
                type="button"
                @click="revokeBreakGlass" 
                class="bg-black/20 hover:bg-black/35 text-white text-[11px] font-bold px-2.5 py-0.5 rounded transition border border-white/30"
            >
                Revoke Override
            </button>
        </div>

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
