<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

// Écran 01 — Connexion
defineOptions({
    layout: {
        titreIllustration: 'Des emplois du temps sans casse-tête',
        texteIllustration:
            'Générez, vérifiez et partagez les emplois du temps de votre école en quelques clics.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Connexion" />

    <AppLogo />

    <h1 class="mt-6 text-[28px] font-bold">Bienvenue</h1>
    <p class="mt-1 text-muted-foreground">Connectez-vous à votre espace</p>

    <div
        v-if="status"
        class="mt-6 rounded-xl bg-succes/15 px-4 py-3 text-sm font-medium text-[#1F8A6B]"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="mt-8 grid gap-5"
    >
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                v-focus
                autocomplete="email"
                placeholder="nom@ecole.tn"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Mot de passe</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
            />
            <InputError :message="errors.password" />
            <TextLink
                v-if="canResetPassword"
                :href="request()"
                class="mt-2 justify-self-end text-sm"
            >
                Mot de passe oublié ?
            </TextLink>
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            Se connecter
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Directeur d'école ?
            <TextLink :href="register()">Inscrire mon école</TextLink>
        </p>
    </Form>
</template>
