<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="nom">Nom</Label>
                <Input
                    id="nom"
                    class="mt-1 block w-full"
                    name="nom"
                    :default-value="user.nom"
                    required
                    autocomplete="family-name"
                    placeholder="Nom"
                />
                <InputError class="mt-2" :message="errors.nom" />
            </div>

            <div class="grid gap-2">
                <Label for="prenom">Prénom</Label>
                <Input
                    id="prenom"
                    class="mt-1 block w-full"
                    name="prenom"
                    :default-value="user.prenom"
                    required
                    autocomplete="given-name"
                    placeholder="Prénom"
                />
                <InputError class="mt-2" :message="errors.prenom" />
            </div>

            <div class="grid gap-2">
                <Label for="telephone">Téléphone</Label>
                <Input
                    id="telephone"
                    type="tel"
                    class="mt-1 block w-full"
                    name="telephone"
                    :default-value="user.telephone ?? ''"
                    autocomplete="tel"
                    placeholder="Téléphone"
                />
                <InputError class="mt-2" :message="errors.telephone" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
