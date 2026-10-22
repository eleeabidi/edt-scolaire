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
import { store, update } from '@/routes/salles';
import type { Salle, TypeSalle } from '@/types';

// Fenêtre F10 — Ajouter / Modifier une salle
const props = defineProps<{
    // null = ajout, sinon = modification de cette salle
    salle: Salle | null;
}>();

const ouvert = defineModel<boolean>('open', { required: true });

const form = useForm({
    nom: '',
    type: 'normale' as TypeSalle,
    capacite: '' as number | '',
});

const enModification = computed(() => props.salle !== null);

watch(ouvert, (estOuvert) => {
    if (!estOuvert) {
        return;
    }

    form.clearErrors();
    form.defaults({
        nom: props.salle?.nom ?? '',
        type: props.salle?.type ?? 'normale',
        capacite: props.salle?.capacite ?? '',
    });
    form.reset();
});

function enregistrer() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (ouvert.value = false),
    };

    if (props.salle) {
        form.patch(update.url(props.salle.id), options);
    } else {
        form.post(store.url(), options);
    }
}
</script>

<template>
    <Dialog v-model:open="ouvert">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        enModification
                            ? 'Modifier la salle'
                            : 'Ajouter une salle'
                    }}
                </DialogTitle>
                <DialogDescription>
                    La salle est créée dans votre école.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="enregistrer">
                <div class="grid gap-2">
                    <Label for="salle-nom">Nom de la salle</Label>
                    <Input
                        id="salle-nom"
                        v-model="form.nom"
                        required
                        placeholder="Salle 5"
                    />
                    <InputError :message="form.errors.nom" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="salle-type">Type</Label>
                        <select
                            id="salle-type"
                            v-model="form.type"
                            required
                            class="h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="normale">Normale</option>
                            <option value="sport">Sport</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="salle-capacite">Capacité (élèves)</Label>
                        <Input
                            id="salle-capacite"
                            v-model="form.capacite"
                            type="number"
                            min="1"
                            required
                            placeholder="30"
                        />
                        <InputError :message="form.errors.capacite" />
                    </div>
                </div>

                <p class="text-sm text-muted-foreground">
                    Une salle est soit normale, soit de sport. L'éducation
                    physique sera placée dans une salle de sport.
                </p>

                <DialogFooter class="gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="ouvert = false"
                    >
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        {{ enModification ? 'Enregistrer' : 'Ajouter' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
