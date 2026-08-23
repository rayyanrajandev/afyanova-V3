<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AfyaInput from '@/Components/Afya/AfyaInput.vue';
import { Shield, Fingerprint, LifeBuoy, ArrowRight, Loader2 } from 'lucide-vue-next';

const props = defineProps({
    maskedEmail: {
        type: String,
        required: true,
    },
});

// Toggle between TOTP code input and recovery code input.
const useRecovery = ref(false);

const form = useForm({
    code:          '',
    recovery_code: '',
});

const submit = () => {
    form.post(route('two-factor.login.post'), {
        onError: () => {
            form.reset('code', 'recovery_code');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Two-Factor Authentication — AfyaNova V3" />

        <!-- Header -->
        <div class="space-y-2">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center border border-primary/20">
                    <Shield class="w-4 h-4 text-primary" />
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-foreground">
                    Two-Factor Verification
                </h2>
            </div>
            <p class="text-xs text-muted-foreground">
                Password accepted for
                <span class="font-semibold font-mono text-foreground">{{ maskedEmail }}</span>.
                Enter the 6-digit code from your authenticator app to complete sign-in.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">

            <!-- TOTP code mode -->
            <template v-if="!useRecovery">
                <AfyaInput
                    id="code"
                    type="text"
                    inputmode="numeric"
                    label="Authenticator Code"
                    v-model="form.code"
                    :icon="Fingerprint"
                    :error="form.errors.code"
                    autocomplete="one-time-code"
                    maxlength="6"
                    placeholder="000000"
                    autofocus
                    required
                />
            </template>

            <!-- Recovery code mode -->
            <template v-else>
                <AfyaInput
                    id="recovery_code"
                    type="text"
                    label="Recovery Code"
                    v-model="form.recovery_code"
                    :icon="LifeBuoy"
                    :error="form.errors.code"
                    autocomplete="off"
                    placeholder="XXXX-XXXX"
                    autofocus
                    required
                />
                <p class="text-[11px] text-muted-foreground -mt-2">
                    Each recovery code can only be used once. This will consume the code.
                </p>
            </template>

            <!-- Submit -->
            <PrimaryButton
                class="w-full justify-center h-9 text-xs font-bold gap-2 bg-primary hover:bg-primary/90 text-primary-foreground shadow-xs"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                <span>{{ form.processing ? 'Verifying…' : 'Complete Sign In' }}</span>
                <ArrowRight v-if="!form.processing" class="w-3.5 h-3.5" />
            </PrimaryButton>

            <!-- Toggle between TOTP and recovery code -->
            <div class="text-center">
                <button
                    type="button"
                    @click="useRecovery = !useRecovery; form.reset()"
                    class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1 mx-auto"
                >
                    <LifeBuoy class="w-3 h-3" />
                    {{
                        useRecovery
                            ? 'Use authenticator app code instead'
                            : 'Use a recovery code instead'
                    }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
