<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Inscription directeur',
        description: 'Créez votre compte puis renseignez votre école',
    },
});

// Les champs de chaque étape (pour savoir où revenir en cas d'erreur)
const champsEtape1 = ['nom', 'prenom', 'email', 'telephone', 'password', 'password_confirmation'];

const etape = ref<1 | 2>(1);
const blocEtape1 = ref<HTMLElement | null>(null);

// Passe à l'étape 2 seulement si les champs de l'étape 1 sont valides
function suivant() {
    const champs = blocEtape1.value?.querySelectorAll('input') ?? [];

    for (const champ of champs) {
        if (!champ.reportValidity()) {
            return;
        }
    }

    etape.value = 2;
}

// Affiche l'étape qui contient l'erreur renvoyée par le serveur
function surErreur(errors: Record<string, string>) {
    etape.value = Object.keys(errors).some((champ) => champsEtape1.includes(champ)) ? 1 : 2;
}
</script>

<template>
    <Head title="Inscription" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        @error="surErreur"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <!-- Indicateur d'étape -->
        <div class="flex items-center gap-3 text-sm">
            <span
                class="flex size-7 items-center justify-center rounded-full font-semibold"
                :class="etape === 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                >1</span
            >
            <span :class="etape === 1 ? 'font-medium' : 'text-muted-foreground'">Vos informations</span>
            <span class="h-px flex-1 bg-border" />
            <span
                class="flex size-7 items-center justify-center rounded-full font-semibold"
                :class="etape === 2 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                >2</span
            >
            <span :class="etape === 2 ? 'font-medium' : 'text-muted-foreground'">Votre école</span>
        </div>

        <!-- Étape 1 : le directeur -->
        <div v-show="etape === 1" ref="blocEtape1" class="grid gap-6">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="nom">Nom</Label>
                    <Input id="nom" name="nom" required v-focus autocomplete="family-name" placeholder="Nom" />
                    <InputError :message="errors.nom" />
                </div>

                <div class="grid gap-2">
                    <Label for="prenom">Prénom</Label>
                    <Input id="prenom" name="prenom" required autocomplete="given-name" placeholder="Prénom" />
                    <InputError :message="errors.prenom" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" name="email" type="email" required autocomplete="email" placeholder="email@exemple.com" />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="telephone">Téléphone</Label>
                <Input id="telephone" name="telephone" type="tel" autocomplete="tel" placeholder="Téléphone" />
                <InputError :message="errors.telephone" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Mot de passe</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    placeholder="Au moins 8 caractères, lettres et chiffres"
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
                    placeholder="Confirmer le mot de passe"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button type="button" class="mt-2 w-full" @click="suivant">Suivant</Button>
        </div>

        <!-- Étape 2 : l'école -->
        <div v-show="etape === 2" class="grid gap-6">
            <div class="grid gap-2">
                <Label for="ecole_nom">Nom de l'école</Label>
                <Input id="ecole_nom" name="ecole_nom" :required="etape === 2" placeholder="Nom de l'école" />
                <InputError :message="errors.ecole_nom" />
            </div>

            <div class="grid gap-2">
                <Label for="ecole_adresse">Adresse</Label>
                <Input
                    id="ecole_adresse"
                    name="ecole_adresse"
                    :required="etape === 2"
                    autocomplete="street-address"
                    placeholder="Adresse"
                />
                <InputError :message="errors.ecole_adresse" />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="ecole_ville">Ville</Label>
                    <Input id="ecole_ville" name="ecole_ville" :required="etape === 2" placeholder="Ville" />
                    <InputError :message="errors.ecole_ville" />
                </div>

                <div class="grid gap-2">
                    <Label for="ecole_telephone">Téléphone de l'école</Label>
                    <Input
                        id="ecole_telephone"
                        name="ecole_telephone"
                        type="tel"
                        :required="etape === 2"
                        placeholder="Téléphone"
                    />
                    <InputError :message="errors.ecole_telephone" />
                </div>
            </div>

            <div class="mt-2 flex gap-3">
                <Button type="button" variant="outline" class="flex-1" @click="etape = 1">Retour</Button>
                <Button type="submit" class="flex-1" :disabled="processing" data-test="register-user-button">
                    <Spinner v-if="processing" />
                    Envoyer l'inscription
                </Button>
            </div>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Vous avez déjà un compte ?
            <TextLink :href="login()" class="underline underline-offset-4">Se connecter</TextLink>
        </div>
    </Form>
</template>
