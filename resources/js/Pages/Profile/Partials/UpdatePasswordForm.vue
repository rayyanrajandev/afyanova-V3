<script setup>
import InputError from '@/Components/InputError.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Key, Lock, Save, Loader2, CheckCircle2 } from 'lucide-vue-next';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
            }
            if (form.errors.current_password) {
                form.reset('current_password');
            }
        },
    });
};
</script>

<template>
    <section class="space-y-4">
        <div class="border-b border-border/60 pb-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                <Key class="w-3.5 h-3.5 text-primary" />
                <span>Password & Authentication Security</span>
            </h3>
            <p class="text-[11px] text-muted-foreground mt-0.5">
                Ensure your clinical credentials use a robust, secure password meeting healthcare compliance standards.
            </p>
        </div>

        <form @submit.prevent="updatePassword" class="space-y-4 text-xs">
            <div class="space-y-1.5">
                <label for="current_password" class="block font-bold text-xs text-foreground">
                    Current Password
                    <span class="text-[10px] text-muted-foreground font-normal lowercase ml-1">(required)</span>
                </label>

                <Input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="w-full h-9 text-xs"
                    autocomplete="current-password"
                    placeholder="••••••••••••"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-1"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="password" class="block font-bold text-xs text-foreground">
                        New Password
                        <span class="text-[10px] text-muted-foreground font-normal lowercase ml-1">(min 8 characters)</span>
                    </label>

                    <Input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="w-full h-9 text-xs"
                        autocomplete="new-password"
                        placeholder="••••••••••••"
                    />

                    <InputError :message="form.errors.password" class="mt-1" />
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block font-bold text-xs text-foreground">
                        Confirm New Password
                        <span class="text-[10px] text-muted-foreground font-normal lowercase ml-1">(repeat)</span>
                    </label>

                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="w-full h-9 text-xs"
                        autocomplete="new-password"
                        placeholder="••••••••••••"
                    />

                    <InputError
                        :message="form.errors.password_confirmation"
                        class="mt-1"
                    />
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <Button type="submit" variant="default" size="default" :disabled="form.processing" class="gap-1.5 shadow-2xs">
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                    <Lock v-else class="w-3.5 h-3.5" />
                    <span>{{ form.processing ? 'Updating...' : 'Update Password' }}</span>
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
                        Password updated.
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>
