<script setup>
import InputError from '@/Components/InputError.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { User, Mail, Save, Loader2, CheckCircle2 } from 'lucide-vue-next';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section class="space-y-4">
        <div class="border-b border-border/60 pb-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                <User class="w-3.5 h-3.5 text-primary" />
                <span>Personal Profile Information</span>
            </h3>
            <p class="text-[11px] text-muted-foreground mt-0.5">
                Update your registered practitioner name, clinical signature identifier, and email address.
            </p>
        </div>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="space-y-4 text-xs"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="name" class="block font-bold text-xs text-foreground">
                        Full Legal / Practitioner Name
                        <span class="text-[10px] text-muted-foreground font-normal lowercase ml-1">(required)</span>
                    </label>

                    <Input
                        id="name"
                        type="text"
                        class="w-full h-9 text-xs"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="e.g. Dr. Sarah Masanja, MD"
                    />

                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="space-y-1.5">
                    <label for="email" class="block font-bold text-xs text-foreground">
                        Hospital Work Email Address
                        <span class="text-[10px] text-muted-foreground font-normal lowercase ml-1">(required)</span>
                    </label>

                    <Input
                        id="email"
                        type="email"
                        class="w-full h-9 text-xs font-mono"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        placeholder="doctor@hospital.org"
                    />

                    <InputError class="mt-1" :message="form.errors.email" />
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-lg border border-amber-200 dark:border-amber-800 text-amber-950 dark:text-amber-300">
                <p class="text-xs">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-bold underline hover:text-amber-800 ml-1"
                    >
                        Click here to re-send the verification link.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-border/40">
                <Button type="submit" variant="default" size="default" :disabled="form.processing" class="gap-1.5 shadow-2xs">
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                    <Save v-else class="w-3.5 h-3.5" />
                    <span>{{ form.processing ? 'Saving...' : 'Save Profile Changes' }}</span>
                </Button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <span
                        v-if="form.recentlySuccessful"
                        class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1"
                    >
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        Saved successfully.
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>
