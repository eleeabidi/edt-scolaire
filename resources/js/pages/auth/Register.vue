<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { onUnmounted, ref, watchEffect } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import InfoBox from '@/components/InfoBox.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useIllustration } from '@/composables/useIllustration';
import { login } from '@/routes';
import { store } from '@/routes/register';

// Écrans 04-05 — Inscription du directeur en 2 étapes (+ fenêtre F6)
const props = defineProps<{
    passwordRules: string;
    inscriptionEnvoyee: boolean;
}>();

// Les champs de chaque étape (pour savoir où revenir en cas d'erreur)
const champsEtape1 = [
    'nom',
    'prenom',
    'email',
    'telephone',
    'password',
    'password_confirmation',
];

const etape = ref<1 | 2>(1);
const blocEtape1 = ref<HTMLElement | null>(null);
const confirmationOuverte = ref(props.inscriptionEnvoyee);

// Le texte de l'illustration change avec l'étape
const illustration = useIllustration();

watchEffect(() => {
    illustration.value =
        etape.value === 1
            ? {
                  titre: 'Rejoignez la plateforme',
                  texte: "Inscrivez votre école en deux étapes. L'administrateur valide ensuite votre compte.",
              }
            : {
                  titre: 'Presque terminé',
                  texte: "Indiquez les informations de votre école pour qu'elle soit reconnue sur la plateforme.",
              };
});

onUnmounted(() => (illustration.value = null));

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
    etape.value = Object.keys(errors).some((champ) =>
        champsEtape1.includes(champ),
    )
        ? 1
        : 2;
}
</script>

<template>
    <Head title="Inscrire mon école" />

    <AppLogo />

    <!-- Progression -->
    <p class="mt-6 text-sm">
        <span class="font-semibold text-primary">Étape {{ etape }} sur 2</span>
        <span class="text-muted-foreground">
            · {{ etape === 1 ? 'Vos informations' : 'Votre école' }}
        </span>
    </p>
    <div class="mt-2 h-1.5 rounded-full bg-bordure">
        <div
            class="h-full rounded-full bg-primary transition-all duration-300"
            :class="etape === 1 ? 'w-1/2' : 'w-full'"
        />
    </div>

    <h1 class="mt-6 text-[28px] font-bold">
        {{ etape === 1 ? 'Inscrire mon école' : 'Votre école' }}
    </h1>
    <p class="mt-1 text-muted-foreground">
        {{
            etape === 1
                ? 'Créez votre compte directeur.'
                : 'Ces informations identifient votre établissement.'
        }}
    </p>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        @error="surErreur"
        v-slot="{ errors, processing }"
        class="mt-6"
    >
        <!-- Étape 1 : le directeur -->
        <div v-show="etape === 1" ref="blocEtape1" class="grid gap-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="nom">Nom</Label>
                    <Input
                        id="nom"
                        name="nom"
                        required
                        v-focus
                        autocomplete="family-name"
                        placeholder="Ben Ali"
                    />
                    <InputError :message="errors.nom" />
                </div>

                <div class="grid gap-2">
                    <Label for="prenom">Prénom</Label>
                    <Input
                        id="prenom"
                        name="prenom"
                        required
                        autocomplete="given-name"
                        placeholder="Sami"
                    />
                    <InputError :message="errors.prenom" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="nom@ecole.tn"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="telephone">Téléphone</Label>
                <Input
                    id="telephone"
                    name="telephone"
                    type="tel"
                    autocomplete="tel"
                    placeholder="+216 00 000 000"
                />
                <InputError :message="errors.telephone" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="password">Mot de passe</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="••••••••"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmation</Label>
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
            </div>
            <p class="-mt-2 text-xs text-muted-foreground">
                Au moins 8 caractères, avec des lettres et des chiffres.
            </p>

            <Button type="button" class="w-full" @click="suivant">
                Continuer
            </Button>
        </div>

        <!-- Étape 2 : l'école -->
        <div v-show="etape === 2" class="grid gap-5">
            <div class="grid gap-2">
                <Label for="ecole_nom">Nom de l'école</Label>
                <Input
                    id="ecole_nom"
                    name="ecole_nom"
                    :required="etape === 2"
                    placeholder="École Ibn Khaldoun"
                />
                <InputError :message="errors.ecole_nom" />
            </div>

            <div class="grid gap-2">
                <Label for="ecole_adresse">Adresse</Label>
                <Input
                    id="ecole_adresse"
                    name="ecole_adresse"
                    :required="etape === 2"
                    autocomplete="street-address"
                    placeholder="12 rue de la Liberté"
                />
                <InputError :message="errors.ecole_adresse" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="ecole_ville">Ville</Label>
                    <Input
                        id="ecole_ville"
                        name="ecole_ville"
                        :required="etape === 2"
                        placeholder="Tunis"
                    />
                    <InputError :message="errors.ecole_ville" />
                </div>

                <div class="grid gap-2">
                    <Label for="ecole_telephone">Téléphone de l'école</Label>
                    <Input
                        id="ecole_telephone"
                        name="ecole_telephone"
                        type="tel"
                        :required="etape === 2"
                        placeholder="+216 71 000 000"
                    />
                    <InputError :message="errors.ecole_telephone" />
                </div>
            </div>

            <InfoBox>
                Votre compte sera activé après validation par l'administrateur.
                Vous recevrez un email.
            </InfoBox>

            <div class="flex gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="flex-1"
                    @click="etape = 1"
                >
                    Retour
                </Button>
                <Button
                    type="submit"
                    class="flex-1"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Envoyer l'inscription
                </Button>
            </div>
        </div>

        <p class="mt-6 text-center text-sm text-muted-foreground">
            Déjà inscrit ?
            <TextLink :href="login()">Se connecter</TextLink>
        </p>
    </Form>

    <!-- F6 — Inscription envoyée -->
    <Dialog v-model:open="confirmationOuverte">
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-succes/15 text-[#1F8A6B]"
            >
                <Check class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle>Inscription envoyée</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    Votre compte sera activé après validation par
                    l'administrateur. Vous recevrez un email de confirmation.
                </DialogDescription>
            </DialogHeader>
            <Button as-child class="w-full">
                <Link :href="login()">Retour à la connexion</Link>
            </Button>
        </DialogContent>
    </Dialog>
</template>
