<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Lock } from '@lucide/vue';
import InfoBox from '@/components/InfoBox.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

// Écran 02 — Mot de passe oublié
defineOptions({
    layout: {
        titreIllustration: 'Pas de panique',
        texteIllustration:
            "Un oubli arrive à tout le monde : quelques secondes suffisent pour retrouver l'accès à votre espace.",
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Mot de passe oublié" />

    <Link
        :href="login()"
        class="flex w-fit items-center gap-2 text-sm font-semibold text-primary"
    >
        <ArrowLeft class="size-5" />
        Retour à la connexion
    </Link>

    <div
        class="mt-6 flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
    >
        <Lock class="size-5" />
    </div>

    <h1 class="mt-5 text-[28px] font-bold">Mot de passe oublié ?</h1>
    <p class="mt-2 leading-relaxed text-muted-foreground">
        Saisissez l'email de votre compte. Nous vous enverrons un lien pour
        choisir un nouveau mot de passe.
    </p>

    <div
        v-if="status"
        class="mt-6 rounded-xl bg-succes/15 px-4 py-3 text-sm font-medium text-[#1F8A6B]"
    >
        {{ status }}
    </div>

    <Form
        v-bind="email.form()"
        v-slot="{ errors, processing }"
        class="mt-6 grid gap-5"
    >
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="email"
                v-focus
                placeholder="nom@ecole.tn"
            />
            <InputError :message="errors.email" />
        </div>

        <Button
            class="w-full"
            :disabled="processing"
            data-test="email-password-reset-link-button"
        >
            <Spinner v-if="processing" />
            Envoyer le lien
        </Button>

        <InfoBox>Le lien reçu par email est valable 30 minutes.</InfoBox>
    </Form>
</template>
