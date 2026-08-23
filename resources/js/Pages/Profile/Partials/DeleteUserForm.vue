<script setup>
import InputError from '@/Components/InputError.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { AlertTriangle, Trash2, X, Loader2 } from 'lucide-vue-next';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => {},
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-4">
        <div class="border-b border-border/60 pb-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-destructive flex items-center gap-1.5">
                <AlertTriangle class="w-3.5 h-3.5 text-destructive" />
                <span>Account Deactivation & Removal</span>
            </h3>
            <p class="text-[11px] text-muted-foreground mt-0.5">
                Permanently revoke user access and credentials. Clinical audit logs created by this user will be preserved for regulatory compliance.
            </p>
        </div>

        <div class="flex items-center justify-between pt-1">
            <p class="text-xs text-muted-foreground">
                Once deleted, this user will no longer be able to log in or sign clinical records.
            </p>
            <Button variant="destructive" size="sm" @click="confirmUserDeletion" class="gap-1.5 shadow-2xs font-bold">
                <Trash2 class="w-3.5 h-3.5" />
                <span>Delete Account</span>
            </Button>
        </div>

        <Modal :show="confirmingUserDeletion" max-width="md" @close="closeModal">
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2 text-destructive font-bold text-sm">
                        <AlertTriangle class="w-4 h-4" />
                        <span>Confirm Account Deletion</span>
                    </div>
                    <button @click="closeModal" class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <p class="text-xs text-muted-foreground leading-relaxed">
                    Are you sure you want to delete your account? Please enter your password to confirm permanent revocation.
                </p>

                <div class="space-y-1.5">
                    <label for="delete_password" class="block font-bold text-xs text-foreground">
                        Confirm Password
                    </label>
                    <Input
                        id="delete_password"
                        v-model="form.password"
                        type="password"
                        class="w-full h-9 text-xs"
                        placeholder="Enter password to confirm"
                        @keyup.enter="deleteUser"
                        autofocus
                    />
                    <InputError :message="form.errors.password" class="mt-1" />
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-border">
                    <Button variant="outline" size="sm" @click="closeModal" :disabled="form.processing">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        :disabled="form.processing || !form.password"
                        @click="deleteUser"
                    >
                        <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin mr-1.5" />
                        <span>Confirm Delete</span>
                    </Button>
                </div>
            </div>
        </Modal>
    </section>
</template>
