<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        titreIllustration: 'Zone sécurisée',
        texteIllustration:
            'Par sécurité, confirmez votre mot de passe avant de continuer.',
    },
});
</script>

<template>
    <Head title="Confirmer le mot de passe" />

    <div
        class="flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
    >
        <Lock class="size-5" />
    </div>

    <h1 class="mt-5 text-[28px] font-bold">Confirmez votre mot de passe</h1>
    <p class="mt-2 text-muted-foreground">
        Cette zone est sécurisée. Saisissez votre mot de passe pour continuer.
    </p>

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="mt-8 grid gap-5"
    >
        <div class="grid gap-2">
            <Label for="password">Mot de passe</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                autofocus
            />
            <InputError :message="errors.password" />
        </div>

        <Button
            class="w-full"
            :disabled="processing"
            data-test="confirm-password-button"
        >
            <Spinner v-if="processing" />
            Confirmer
        </Button>
    </Form>
</template>
