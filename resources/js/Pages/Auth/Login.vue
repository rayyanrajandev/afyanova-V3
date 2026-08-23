<script setup>
import { computed } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AfyaInput from '@/Components/Afya/AfyaInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Lock, Mail, ArrowRight, Clock, ShieldAlert } from 'lucide-vue-next';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
    },
});

const isIdleTimeout = computed(() => {
    if (typeof window !== 'undefined') {
        return new URLSearchParams(window.location.search).get('reason') === 'idle_timeout';
    }
    return false;
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Staff Sign In — AfyaNova V3" />

        <div class="space-y-2">
            <h2 class="text-2xl font-extrabold tracking-tight text-foreground">
                Staff Portal Sign In
            </h2>
            <p class="text-xs text-muted-foreground">
                Enter your verified hospital credentials to access your clinical or operational desk.
            </p>
        </div>

        <!-- Inactivity Alert -->
        <div v-if="isIdleTimeout" class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs flex items-center gap-2.5">
            <Clock class="w-4 h-4 shrink-0 animate-pulse" />
            <span>Your session expired due to inactivity. Please sign in again to continue your work.</span>
        </div>

        <div v-else-if="status" class="p-3 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-medium">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email -->
            <AfyaInput
                id="email"
                type="email"
                label="Official Email / Staff Identifier"
                v-model="form.email"
                :icon="Mail"
                :error="form.errors.email"
                required
                autofocus
                autocomplete="username"
                placeholder="doctor@hospital.co.tz"
            />

            <!-- Password -->
            <AfyaInput
                id="password"
                type="password"
                label="Security Password"
                v-model="form.password"
                :icon="Lock"
                :error="form.errors.password"
                required
                autocomplete="current-password"
                placeholder="••••••••••••"
            >
                <template #corner>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-[11px] font-semibold text-primary hover:underline"
                    >
                        Forgot password?
                    </Link>
                </template>
            </AfyaInput>

            <!-- Remember Me -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-xs text-muted-foreground select-none">Remember this workstation</span>
                </label>
            </div>

            <!-- Submit Button -->
            <PrimaryButton
                class="w-full justify-center h-9 text-xs font-bold gap-2 bg-primary hover:bg-primary/90 text-primary-foreground shadow-xs"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                <span>Access Clinical & Financial Workstation</span>
                <ArrowRight class="w-3.5 h-3.5" />
            </PrimaryButton>

            <div class="pt-2 text-center text-xs text-muted-foreground">
                Need to onboard a new facility or user?
                <Link :href="route('register')" class="font-bold text-primary hover:underline ml-1">
                    Create Account
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
