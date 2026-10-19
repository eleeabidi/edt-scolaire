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
import { store, update } from '@/routes/enseignants';
import type { EcoleOption, Enseignant } from '@/types';

const props = defineProps<{
    // null = ajout, sinon = modification de cet enseignant
    enseignant: Enseignant | null;
    ecoles: EcoleOption[];
    estAdmin: boolean;
}>();

const ouvert = defineModel<boolean>('open', { required: true });

const form = useForm({
    nom: '',
    prenom: '',
    email: '',
    telephone: '',
    ecole_id: '' as number | '',
});

const enModification = computed(() => props.enseignant !== null);

// À chaque ouverture, on remplit le formulaire (vide pour un ajout)
watch(ouvert, (estOuvert) => {
    if (!estOuvert) {
        return;
    }

    form.clearErrors();
    form.defaults({
        nom: props.enseignant?.nom ?? '',
        prenom: props.enseignant?.prenom ?? '',
        email: props.enseignant?.email ?? '',
        telephone: props.enseignant?.telephone ?? '',
        ecole_id: props.enseignant?.ecole_id ?? '',
    });
    form.reset();
});

function enregistrer() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (ouvert.value = false),
    };

    if (props.enseignant) {
        form.patch(update.url(props.enseignant.id), options);
    } else {
        form.post(store.url(), options);
    }
}
</script>

<template>
    <Dialog v-model:open="ouvert">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{
                    enModification
                        ? "Modifier l'enseignant"
                        : 'Ajouter un enseignant'
                }}</DialogTitle>
                <DialogDescription v-if="!enModification">
                    Un mot de passe temporaire lui sera envoyé par email.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="enregistrer">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="ens-nom">Nom</Label>
                        <Input
                            id="ens-nom"
                            placeholder="Trabelsi"
                            v-model="form.nom"
                            required
                        />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ens-prenom">Prénom</Label>
                        <Input
                            id="ens-prenom"
                            placeholder="Leila"
                            v-model="form.prenom"
                            required
                        />
                        <InputError :message="form.errors.prenom" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="ens-email">Email</Label>
                    <Input
                        id="ens-email"
                        placeholder="nom@ecole.tn"
                        v-model="form.email"
                        type="email"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="ens-telephone">Téléphone</Label>
                    <Input
                        id="ens-telephone"
                        placeholder="+216 00 000 000"
                        v-model="form.telephone"
                        type="tel"
                    />
                    <InputError :message="form.errors.telephone" />
                </div>

                <!-- Seul l'Admin choisit l'école -->
                <div v-if="estAdmin" class="grid gap-2">
                    <Label for="ens-ecole">École</Label>
                    <select
                        id="ens-ecole"
                        v-model="form.ecole_id"
                        required
                        class="h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="" disabled>Choisir une école</option>
                        <option
                            v-for="ecole in ecoles"
                            :key="ecole.id"
                            :value="ecole.id"
                        >
                            {{ ecole.nom }}
                        </option>
                    </select>
                    <InputError :message="form.errors.ecole_id" />
                </div>

                <DialogFooter class="gap-3">
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
