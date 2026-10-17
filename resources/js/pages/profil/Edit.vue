<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/profile';

type Ecole = {
    nom: string;
    adresse: string;
    ville: string;
    telephone: string;
};

defineProps<{
    role: string;
    ecole: Ecole | null;
    passwordRules: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mon profil', href: edit() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Mon profil" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Heading title="Mon profil" :description="role" />

        <!-- Mes informations -->
        <Card>
            <CardHeader>
                <CardTitle>Mes informations</CardTitle>
                <CardDescription
                    >Modifiez vos informations personnelles.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="ProfileController.update.form()"
                    :options="{ preserveScroll: true }"
                    class="grid gap-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-6 sm:grid-cols-2">
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
                            />
                            <InputError :message="errors.telephone" />
                        </div>
                    </div>

                    <!-- Champ non modifiable : géré par le directeur ou l'admin -->
                    <div v-if="user.specialite" class="grid gap-2">
                        <Label for="specialite">Spécialité</Label>
                        <Input
                            id="specialite"
                            :default-value="user.specialite"
                            disabled
                        />
                    </div>

                    <div>
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                        >
                            <Spinner v-if="processing" />
                            Enregistrer
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <!-- Mon école (lecture seule) -->
        <Card v-if="ecole">
            <CardHeader>
                <CardTitle>Mon école</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Nom</dt>
                        <dd class="font-medium">{{ ecole.nom }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Téléphone</dt>
                        <dd class="font-medium">{{ ecole.telephone }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted-foreground">Adresse</dt>
                        <dd class="font-medium">
                            {{ ecole.adresse }}, {{ ecole.ville }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- Changer mon mot de passe -->
        <Card>
            <CardHeader>
                <CardTitle>Changer mon mot de passe</CardTitle>
                <CardDescription
                    >Au moins 8 caractères, avec des lettres et des
                    chiffres.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="ProfileController.updatePassword.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="grid gap-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="current_password"
                            >Mot de passe actuel</Label
                        >
                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="password">Nouveau mot de passe</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                :passwordrules="passwordRules"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation"
                                >Confirmer le mot de passe</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                :passwordrules="passwordRules"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                    </div>

                    <div>
                        <Button
                            :disabled="processing"
                            data-test="update-password-button"
                        >
                            <Spinner v-if="processing" />
                            Changer le mot de passe
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
