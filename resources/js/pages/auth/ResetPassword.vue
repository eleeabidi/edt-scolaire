<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { request, update } from '@/routes/password';

// Écran 03 — Nouveau mot de passe
defineOptions({
    layout: {
        titreIllustration: 'Un nouveau départ',
        texteIllustration:
            'Choisissez un mot de passe que vous retiendrez facilement, mais difficile à deviner.',
    },
});

defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Nouveau mot de passe" />

    <div
        class="flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
    >
        <Lock class="size-5" />
    </div>

    <h1 class="mt-5 text-[28px] font-bold">Nouveau mot de passe</h1>
    <p class="mt-2 text-muted-foreground">
        Au moins 8 caractères, avec des lettres et des chiffres.
    </p>

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="mt-8 grid gap-5"
    >
        <!-- 5a : lien expiré (plus de 30 minutes) ou déjà utilisé → nouvelle demande -->
        <div
            v-if="errors.email"
            class="rounded-xl bg-erreur/10 px-4 py-3 text-sm"
        >
            <InputError :message="errors.email" />
            <TextLink :href="request()" class="mt-1 inline-block">
                Faire une nouvelle demande
            </TextLink>
        </div>

        <div class="grid gap-2">
            <Label for="password">Nouveau mot de passe</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                minlength="8"
                autocomplete="new-password"
                autofocus
                placeholder="••••••••"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirmer le mot de passe</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="••••••••"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="reset-password-button"
        >
            <Spinner v-if="processing" />
            Enregistrer
        </Button>
    </Form>
</template>
