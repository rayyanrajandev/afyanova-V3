<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AfyaInput from '@/Components/Afya/AfyaInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, ArrowLeft, Send } from '@lucide/vue';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password — AfyaNova V3" />

        <div class="space-y-2">
            <h2 class="text-2xl font-extrabold tracking-tight text-foreground">
                Recover Staff Account
            </h2>
            <p class="text-xs text-muted-foreground leading-relaxed">
                Provide your registered hospital email address. We will transmit an encrypted password reset link to your inbox.
            </p>
        </div>

        <div
            v-if="status"
            class="p-3 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-medium"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <AfyaInput
                id="email"
                type="email"
                label="Official Hospital Email"
                v-model="form.email"
                :icon="Mail"
                :error="form.errors.email"
                required
                autofocus
                autocomplete="username"
                placeholder="doctor@hospital.co.tz"
            />

            <PrimaryButton
                class="w-full justify-center h-9 text-xs font-bold gap-2 bg-primary hover:bg-primary/90 text-primary-foreground shadow-xs"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                <Send class="w-3.5 h-3.5" />
                <span>Transmit Password Reset Link</span>
            </PrimaryButton>

            <div class="pt-2 text-center text-xs text-muted-foreground">
                <Link :href="route('login')" class="inline-flex items-center gap-1 font-bold text-primary hover:underline">
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Return to Staff Sign In</span>
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
