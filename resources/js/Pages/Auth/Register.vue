<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AfyaInput from '@/Components/Afya/AfyaInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { User, Mail, Lock, ArrowRight } from '@lucide/vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Staff Registration — AfyaNova V3" />

        <div class="space-y-2">
            <h2 class="text-2xl font-extrabold tracking-tight text-foreground">
                Staff & Practitioner Onboarding
            </h2>
            <p class="text-xs text-muted-foreground">
                Register a new administrative or clinical profile within your facility tenant.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-3.5">
            <!-- Full Name -->
            <AfyaInput
                id="name"
                type="text"
                label="Practitioner / User Full Name"
                v-model="form.name"
                :icon="User"
                :error="form.errors.name"
                required
                autofocus
                autocomplete="name"
                placeholder="Dr. Juma Mkapa"
            />

            <!-- Email -->
            <AfyaInput
                id="email"
                type="email"
                label="Official Hospital Email"
                v-model="form.email"
                :icon="Mail"
                :error="form.errors.email"
                required
                autocomplete="username"
                placeholder="j.mkapa@hospital.co.tz"
            />

            <!-- Password -->
            <AfyaInput
                id="password"
                type="password"
                label="Password"
                v-model="form.password"
                :icon="Lock"
                :error="form.errors.password"
                required
                autocomplete="new-password"
                placeholder="Minimum 8 characters"
            />

            <!-- Confirm Password -->
            <AfyaInput
                id="password_confirmation"
                type="password"
                label="Confirm Password"
                v-model="form.password_confirmation"
                :icon="Lock"
                :error="form.errors.password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Confirm password"
            />

            <!-- Submit Button -->
            <PrimaryButton
                class="w-full justify-center h-9 text-xs font-bold gap-2 bg-primary hover:bg-primary/90 text-primary-foreground mt-2 shadow-xs"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                <span>Complete Staff Registration</span>
                <ArrowRight class="w-3.5 h-3.5" />
            </PrimaryButton>

            <div class="pt-2 text-center text-xs text-muted-foreground">
                Already registered with an active staff account?
                <Link :href="route('login')" class="font-bold text-primary hover:underline ml-1">
                    Sign In
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
