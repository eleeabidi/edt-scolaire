<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/directeurs';
import type { Directeur } from '@/types';

const props = defineProps<{
    // null = ajout, sinon = modification de ce directeur
    directeur: Directeur | null;
}>();

const ouvert = defineModel<boolean>('open', { required: true });

const form = useForm({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    ecole_nom: '',
    ecole_adresse: '',
    ecole_ville: '',
    ecole_telephone: '',
});

const enModification = computed(() => props.directeur !== null);

// À chaque ouverture, on remplit le formulaire (vide pour un ajout)
watch(ouvert, (estOuvert) => {
    if (!estOuvert) {
        return;
    }

    const d = props.directeur;

    form.clearErrors();
    form.defaults({
        nom: d?.nom ?? '',
        prenom: d?.prenom ?? '',
        email: d?.email ?? '',
        telephone: d?.telephone ?? '',
        ecole_nom: d?.ecole?.nom ?? '',
        ecole_adresse: d?.ecole?.adresse ?? '',
        ecole_ville: d?.ecole?.ville ?? '',
        ecole_telephone: d?.ecole?.telephone ?? '',
    });
    form.reset();
});

function enregistrer() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (ouvert.value = false),
    };

    if (props.directeur) {
        form.patch(update.url(props.directeur.id), options);
    } else {
        form.post(store.url(), options);
    }
}
</script>

<template>
    <Dialog v-model:open="ouvert">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        enModification
                            ? 'Modifier le directeur'
                            : 'Ajouter un directeur'
                    }}
                </DialogTitle>
                <DialogDescription v-if="!enModification">
                    Le compte sera actif tout de suite. Un mot de passe
                    temporaire sera envoyé par email.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="enregistrer">
                <p class="text-sm font-semibold">Le directeur</p>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="dir-nom">Nom</Label>
                        <Input id="dir-nom" v-model="form.nom" required />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="dir-prenom">Prénom</Label>
                        <Input id="dir-prenom" v-model="form.prenom" required />
                        <InputError :message="form.errors.prenom" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="dir-email">Email</Label>
                        <Input
                            id="dir-email"
                            v-model="form.email"
                            type="email"
                            required
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="dir-telephone">Téléphone</Label>
                        <Input
                            id="dir-telephone"
                            v-model="form.telephone"
                            type="tel"
                        />
                        <InputError :message="form.errors.telephone" />
                    </div>
                </div>

                <p class="text-sm font-semibold">Son école</p>

                <div class="grid gap-2">
                    <Label for="dir-ecole-nom">Nom de l'école</Label>
                    <Input
                        id="dir-ecole-nom"
                        v-model="form.ecole_nom"
                        required
                    />
                    <InputError :message="form.errors.ecole_nom" />
                </div>

                <div class="grid gap-2">
                    <Label for="dir-ecole-adresse">Adresse</Label>
                    <Input
                        id="dir-ecole-adresse"
                        v-model="form.ecole_adresse"
                        required
                    />
                    <InputError :message="form.errors.ecole_adresse" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="dir-ecole-ville">Ville</Label>
                        <Input
                            id="dir-ecole-ville"
                            v-model="form.ecole_ville"
                            required
                        />
                        <InputError :message="form.errors.ecole_ville" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="dir-ecole-telephone"
                            >Téléphone de l'école</Label
                        >
                        <Input
                            id="dir-ecole-telephone"
                            v-model="form.ecole_telephone"
                            type="tel"
                            required
                        />
                        <InputError :message="form.errors.ecole_telephone" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="ouvert = false"
                        >Annuler</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        {{ enModification ? 'Enregistrer' : 'Ajouter' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
