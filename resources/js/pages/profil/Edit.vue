<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import ChangerMotDePasseDialog from '@/components/ChangerMotDePasseDialog.vue';
import Initiales from '@/components/Initiales.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Ecole } from '@/types';

// Écran 06 — Mon profil (les trois acteurs) + fenêtre F1
const props = defineProps<{
    role: string;
    ecole: Ecole | null;
    passwordRules: string;
}>();

defineOptions({
    layout: { titre: 'Mon profil' },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Ex. : « Directeur · École Ibn Khaldoun » ou « Enseignant · École Les Jasmins »
const description = computed(() =>
    [props.role, props.ecole?.nom].filter(Boolean).join(' · '),
);

const motDePasseOuvert = ref(false);
</script>

<template>
    <Head title="Mon profil" />

    <!-- Sur téléphone, le titre est déjà dans la barre du haut -->
    <div class="hidden lg:block">
        <PageHeader
            titre="Mon profil"
            sous-titre="Gérez vos informations personnelles et votre mot de passe."
        />
    </div>

    <section class="rounded-xl border bg-white p-5 sm:p-7">
        <div
            class="flex flex-col items-center gap-3 text-center sm:flex-row sm:gap-4 sm:text-left"
        >
            <Initiales :prenom="user.prenom" :nom="user.nom" taille="grande" />
            <div>
                <p class="text-xl font-bold">{{ user.name }}</p>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            :options="{ preserveScroll: true }"
            class="mt-7 grid gap-5"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="nom">Nom</Label>
                    <Input
                        id="nom"
                        name="nom"
                        :default-value="user.nom"
                        required
                        autocomplete="family-name"
                    />
                    <InputError :message="errors.nom" />
                </div>

                <div class="grid gap-2">
                    <Label for="prenom">Prénom</Label>
                    <Input
                        id="prenom"
                        name="prenom"
                        :default-value="user.prenom"
                        required
                        autocomplete="given-name"
                    />
                    <InputError :message="errors.prenom" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="telephone">Téléphone</Label>
                    <Input
                        id="telephone"
                        name="telephone"
                        type="tel"
                        :default-value="user.telephone ?? ''"
                        autocomplete="tel"
                        placeholder="+216 00 000 000"
                    />
                    <InputError :message="errors.telephone" />
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    @click="motDePasseOuvert = true"
                >
                    Changer le mot de passe
                </Button>
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    <Spinner v-if="processing" />
                    Enregistrer
                </Button>
            </div>
        </Form>
    </section>

    <ChangerMotDePasseDialog
        v-model:open="motDePasseOuvert"
        :password-rules="passwordRules"
    />
</template>
