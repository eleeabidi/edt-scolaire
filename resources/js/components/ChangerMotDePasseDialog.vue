<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/user-password';

// Fenêtre F1 — Changer le mot de passe
defineProps<{
    passwordRules: string;
}>();

const ouvert = defineModel<boolean>('open', { required: true });

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// Formulaire vide à chaque ouverture
watch(ouvert, (estOuvert) => {
    if (estOuvert) {
        form.reset();
        form.clearErrors();
    }
});

function enregistrer() {
    form.put(update.url(), {
        preserveScroll: true,
        onSuccess: () => (ouvert.value = false),
        onError: () =>
            form.reset('current_password', 'password', 'password_confirmation'),
    });
}
</script>

<template>
    <Dialog v-model:open="ouvert">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Changer le mot de passe</DialogTitle>
                <DialogDescription>
                    Au moins 8 caractères, avec des lettres et des chiffres.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="enregistrer">
                <div class="grid gap-2">
                    <Label for="current_password">Ancien mot de passe</Label>
                    <PasswordInput
                        id="current_password"
                        v-model="form.current_password"
                        required
                        autocomplete="current-password"
                    />
                    <InputError :message="form.errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Nouveau mot de passe</Label>
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        Confirmer le nouveau mot de passe
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <DialogFooter class="gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="ouvert = false"
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="update-password-button"
                    >
                        <Spinner v-if="form.processing" />
                        Enregistrer
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
