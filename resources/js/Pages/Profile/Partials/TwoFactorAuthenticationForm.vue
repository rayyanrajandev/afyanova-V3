<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import {
    Shield, ShieldCheck, ShieldOff, QrCode, KeyRound,
    Copy, Check, RefreshCw, Loader2, AlertTriangle,
    ChevronRight, LifeBuoy
} from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';
import InputError from '@/Components/InputError.vue';
import Input from '@/Components/ui/Input.vue';

const props = defineProps({
    enabled: {
        type: Boolean,
        required: true,
    },
    // otpauth:// URI for QR rendering — only present while enrollment is pending
    qrCodeUrl: {
        type: String,
        default: null,
    },
    // Recovery codes are flashed via session after confirm() succeeds
    recoveryCodes: {
        type: Array,
        default: null,
    },
});

// ── Phase tracking ──────────────────────────────────────────────────────────
// 'idle'    : MFA not started / user has not clicked Enable
// 'pending' : secret generated, QR shown, waiting for code confirmation
// 'active'  : MFA fully confirmed
const phase = computed(() => {
    if (props.enabled) return 'active';
    if (props.qrCodeUrl) return 'pending';
    return 'idle';
});

// ── QR rendering (otpauth:// → canvas via qrcode.js CDN-free approach) ──────
// We use the browser's built-in URL and a tiny inline QR library loaded
// from the npm package qrcode (bundled via Vite — already in node_modules
// as a transitive dep of many tools).  If it isn't available we fall back
// to showing the raw URI the user can paste into their authenticator.
import { ref as vRef, onMounted, watch } from 'vue';
const qrCanvas = vRef(null);
const qrError = vRef(false);

async function renderQr(url) {
    if (!url || !qrCanvas.value) return;
    try {
        const QRCode = (await import('qrcode')).default;
        await QRCode.toCanvas(qrCanvas.value, url, {
            width: 200,
            margin: 1,
            color: { dark: '#000000', light: '#ffffff' },
        });
        qrError.value = false;
    } catch {
        qrError.value = true;
    }
}

onMounted(() => renderQr(props.qrCodeUrl));
watch(() => props.qrCodeUrl, renderQr);

// ── Enable form (step 1: generate secret) ───────────────────────────────────
const enableForm = useForm({});
const startEnrollment = () => enableForm.post(route('two-factor.enable'));

// ── Confirm form (step 2: prove device) ─────────────────────────────────────
const confirmForm = useForm({ code: '' });
const confirmEnrollment = () => {
    confirmForm.put(route('two-factor.confirm'), {
        preserveScroll: true,
        onError: () => confirmForm.reset('code'),
    });
};

// ── Disable form ─────────────────────────────────────────────────────────────
const disableForm = useForm({});
const disableMfa = () => {
    if (!confirm('Are you sure you want to disable two-factor authentication? This will reduce your account security.')) return;
    disableForm.delete(route('two-factor.disable'));
};

// ── Recovery codes ────────────────────────────────────────────────────────────
const regenForm = useForm({});
const regenerateCodes = () => {
    if (!confirm('This will invalidate all existing recovery codes. Continue?')) return;
    regenForm.post(route('two-factor.recovery-codes'));
};

const recoveryCodes = ref(props.recoveryCodes ?? []);
watch(() => props.recoveryCodes, (codes) => {
    if (codes) recoveryCodes.value = codes;
});

const showRecovery = ref(!!props.recoveryCodes);
const copiedIndex = ref(null);
const copyCode = (code, i) => {
    navigator.clipboard.writeText(code);
    copiedIndex.value = i;
    setTimeout(() => (copiedIndex.value = null), 1500);
};
const copyAll = () => {
    navigator.clipboard.writeText(recoveryCodes.value.join('\n'));
    copiedIndex.value = -1;
    setTimeout(() => (copiedIndex.value = null), 1500);
};
</script>

<template>
    <section class="space-y-5">
        <!-- Section header -->
        <div class="border-b border-border/60 pb-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                <Shield class="w-3.5 h-3.5 text-primary" />
                <span>Two-Factor Authentication (TOTP)</span>
            </h3>
            <p class="text-[11px] text-muted-foreground mt-0.5">
                Add a second layer of security using an authenticator app (Google Authenticator, Authy, Bitwarden, etc.).
                A time-based one-time code will be required at every login.
            </p>
        </div>

        <!-- ── IDLE: MFA not yet enabled ──────────────────────────────────── -->
        <div v-if="phase === 'idle'" class="space-y-4">
            <div class="flex items-start gap-3 p-3.5 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30">
                <AlertTriangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                <div class="text-[11px] text-amber-800 dark:text-amber-300 space-y-0.5">
                    <div class="font-bold">Two-factor authentication is not enabled</div>
                    <div class="text-amber-700 dark:text-amber-400">
                        Your account is protected by password only. Enabling TOTP MFA is strongly recommended for clinical systems.
                    </div>
                </div>
            </div>

            <form @submit.prevent="startEnrollment">
                <Button
                    type="submit"
                    variant="default"
                    size="sm"
                    :disabled="enableForm.processing"
                    class="gap-1.5 shadow-2xs"
                >
                    <Loader2 v-if="enableForm.processing" class="w-3.5 h-3.5 animate-spin" />
                    <ShieldCheck v-else class="w-3.5 h-3.5" />
                    <span>{{ enableForm.processing ? 'Generating secret…' : 'Enable Two-Factor Authentication' }}</span>
                </Button>
            </form>
        </div>

        <!-- ── PENDING: secret generated, awaiting confirmation ────────────── -->
        <div v-else-if="phase === 'pending'" class="space-y-5">
            <div class="flex items-start gap-3 p-3 rounded-lg border border-primary/20 bg-primary/5">
                <QrCode class="w-4 h-4 text-primary shrink-0 mt-0.5" />
                <div class="text-[11px] space-y-0.5">
                    <div class="font-bold text-foreground">Scan the QR code with your authenticator app</div>
                    <div class="text-muted-foreground">
                        Open Google Authenticator, Authy, or any TOTP app and scan the code below.
                        Then enter the 6-digit code to confirm your device.
                    </div>
                </div>
            </div>

            <!-- QR code -->
            <div class="flex flex-col items-center gap-3">
                <div class="p-3 bg-white rounded-xl border border-border shadow-xs inline-block">
                    <canvas ref="qrCanvas" width="200" height="200" />
                </div>

                <!-- Fallback: show raw URI if canvas failed -->
                <div v-if="qrError" class="w-full max-w-sm">
                    <p class="text-[10px] text-muted-foreground mb-1 font-bold uppercase tracking-wide">
                        Manual entry URI (if QR scan fails)
                    </p>
                    <code class="block text-[10px] bg-muted/40 rounded p-2 break-all font-mono border border-border select-all">
                        {{ qrCodeUrl }}
                    </code>
                </div>
            </div>

            <!-- Confirm code form -->
            <form @submit.prevent="confirmEnrollment" class="space-y-3 max-w-xs">
                <div class="space-y-1.5">
                    <label for="totp-code" class="block text-xs font-bold text-foreground">
                        Authenticator Code
                        <span class="text-[10px] text-muted-foreground font-normal ml-1">(6 digits)</span>
                    </label>
                    <Input
                        id="totp-code"
                        v-model="confirmForm.code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        placeholder="000000"
                        class="h-9 text-sm tracking-[0.25em] font-mono w-full"
                        autofocus
                    />
                    <InputError :message="confirmForm.errors.code" />
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="submit"
                        variant="default"
                        size="sm"
                        :disabled="confirmForm.processing || confirmForm.code.length < 6"
                        class="gap-1.5 shadow-2xs"
                    >
                        <Loader2 v-if="confirmForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <Check v-else class="w-3.5 h-3.5" />
                        <span>{{ confirmForm.processing ? 'Verifying…' : 'Confirm & Activate' }}</span>
                    </Button>

                    <form @submit.prevent="disableMfa">
                        <Button type="submit" variant="ghost" size="sm" class="text-muted-foreground text-xs">
                            Cancel
                        </Button>
                    </form>
                </div>
            </form>
        </div>

        <!-- ── ACTIVE: MFA fully confirmed ───────────────────────────────────── -->
        <div v-else class="space-y-5">
            <div class="flex items-start gap-3 p-3.5 rounded-lg border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30">
                <ShieldCheck class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                <div class="text-[11px] text-emerald-800 dark:text-emerald-300 space-y-0.5">
                    <div class="font-bold">Two-factor authentication is active</div>
                    <div class="text-emerald-700 dark:text-emerald-400">
                        Your account requires an authenticator app code at every login. Keep your recovery codes in a safe place.
                    </div>
                </div>
            </div>

            <!-- Recovery codes panel -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        @click="showRecovery = !showRecovery"
                        class="flex items-center gap-1.5 text-[11px] font-bold text-foreground hover:text-primary transition-colors"
                    >
                        <LifeBuoy class="w-3.5 h-3.5" />
                        Recovery Codes
                        <ChevronRight
                            class="w-3 h-3 transition-transform"
                            :class="{ 'rotate-90': showRecovery }"
                        />
                    </button>

                    <button
                        v-if="showRecovery && recoveryCodes.length"
                        type="button"
                        @click="copyAll"
                        class="flex items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <Check v-if="copiedIndex === -1" class="w-3 h-3 text-emerald-600" />
                        <Copy v-else class="w-3 h-3" />
                        <span>{{ copiedIndex === -1 ? 'Copied all!' : 'Copy all' }}</span>
                    </button>
                </div>

                <Transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition-all duration-150"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div v-if="showRecovery" class="space-y-2">
                        <div v-if="recoveryCodes.length" class="grid grid-cols-2 gap-1.5">
                            <button
                                v-for="(code, i) in recoveryCodes"
                                :key="i"
                                type="button"
                                @click="copyCode(code, i)"
                                class="flex items-center justify-between px-2.5 py-1.5 rounded bg-muted/40 border border-border hover:border-primary/40 hover:bg-primary/5 transition-colors group"
                            >
                                <code class="text-[11px] font-mono font-bold text-foreground tracking-wider">
                                    {{ code }}
                                </code>
                                <Check v-if="copiedIndex === i" class="w-3 h-3 text-emerald-600 shrink-0" />
                                <Copy v-else class="w-3 h-3 text-muted-foreground group-hover:text-primary shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" />
                            </button>
                        </div>

                        <p class="text-[10px] text-muted-foreground leading-relaxed">
                            Each code can be used once in place of your TOTP code if you lose access to your device.
                            Store them securely — they will not be shown again.
                        </p>

                        <!-- Regenerate -->
                        <form @submit.prevent="regenerateCodes">
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="regenForm.processing"
                                class="gap-1.5 text-xs"
                            >
                                <Loader2 v-if="regenForm.processing" class="w-3.5 h-3.5 animate-spin" />
                                <RefreshCw v-else class="w-3.5 h-3.5" />
                                <span>{{ regenForm.processing ? 'Regenerating…' : 'Regenerate Recovery Codes' }}</span>
                            </Button>
                        </form>
                    </div>
                </Transition>
            </div>

            <!-- Disable -->
            <div class="pt-2 border-t border-border/40">
                <form @submit.prevent="disableMfa">
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="disableForm.processing"
                        class="gap-1.5 border-destructive/40 text-destructive hover:bg-destructive/5 text-xs"
                    >
                        <Loader2 v-if="disableForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <ShieldOff v-else class="w-3.5 h-3.5" />
                        <span>{{ disableForm.processing ? 'Disabling…' : 'Disable Two-Factor Authentication' }}</span>
                    </Button>
                </form>
            </div>
        </div>
    </section>
</template>
