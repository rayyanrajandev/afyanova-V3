<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AfyaInput from '@/Components/Afya/AfyaInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Mail, Lock, KeyRound } from '@lucide/vue';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Set New Password — AfyaNova V3" />

        <div class="space-y-2">
            <h2 class="text-2xl font-extrabold tracking-tight text-foreground">
                Set New Staff Password
            </h2>
            <p class="text-xs text-muted-foreground">
                Create a new secure password for your verified account.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-3.5">
            <AfyaInput
                id="email"
                type="email"
                label="Hospital Email"
                v-model="form.email"
                :icon="Mail"
                :error="form.errors.email"
                required
                autofocus
                autocomplete="username"
            />

            <AfyaInput
                id="password"
                type="password"
                label="New Password"
                v-model="form.password"
                :icon="Lock"
                :error="form.errors.password"
                required
                autocomplete="new-password"
            />

            <AfyaInput
                id="password_confirmation"
                type="password"
                label="Confirm New Password"
                v-model="form.password_confirmation"
                :icon="Lock"
                :error="form.errors.password_confirmation"
                required
                autocomplete="new-password"
            />

            <PrimaryButton
                class="w-full justify-center h-9 text-xs font-bold gap-2 bg-primary hover:bg-primary/90 text-primary-foreground mt-2 shadow-xs"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                <KeyRound class="w-3.5 h-3.5" />
                <span>Save New Password & Sign In</span>
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
