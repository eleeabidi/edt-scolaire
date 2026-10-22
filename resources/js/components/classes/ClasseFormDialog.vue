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
import { libelleNiveau } from '@/lib/format';
import { store, update } from '@/routes/classes';
import type { Classe } from '@/types';

// Fenêtre F7 — Ajouter / Modifier une classe
const props = defineProps<{
    // null = ajout, sinon = modification de cette classe
    classe: Classe | null;
}>();

const ouvert = defineModel<boolean>('open', { required: true });

const form = useForm({
    nom: '',
    niveau: 1,
    effectif: '' as number | '',
    cours_samedi: false,
});

const enModification = computed(() => props.classe !== null);
const classesSelect =
    'h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:bg-muted disabled:text-muted-foreground';

// Le samedi n'est un choix qu'en 3e année : non en 1re et 2e, oui de la 4e à la 6e
const samediChoisi = computed(() => form.niveau === 3);
const samediImpose = computed(() => form.niveau >= 4);

watch(ouvert, (estOuvert) => {
    if (!estOuvert) {
        return;
    }

    form.clearErrors();
    form.defaults({
        nom: props.classe?.nom ?? '',
        niveau: props.classe?.niveau ?? 1,
        effectif: props.classe?.effectif ?? '',
        cours_samedi: props.classe?.cours_samedi ?? false,
    });
    form.reset();
});

function enregistrer() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (ouvert.value = false),
    };

    if (props.classe) {
        form.patch(update.url(props.classe.id), options);
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
                            ? 'Modifier la classe'
                            : 'Ajouter une classe'
                    }}
                </DialogTitle>
                <DialogDescription>
                    La classe est créée dans votre école.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="enregistrer">
                <div class="grid gap-2">
                    <Label for="classe-nom">Nom de la classe</Label>
                    <Input
                        id="classe-nom"
                        v-model="form.nom"
                        required
                        placeholder="3e année B"
                    />
                    <InputError :message="form.errors.nom" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="classe-niveau">Niveau</Label>
                        <select
                            id="classe-niveau"
                            v-model.number="form.niveau"
                            required
                            :class="classesSelect"
                        >
                            <option v-for="n in 6" :key="n" :value="n">
                                {{ libelleNiveau(n) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.niveau" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="classe-effectif">Effectif</Label>
                        <Input
                            id="classe-effectif"
                            v-model="form.effectif"
                            type="number"
                            min="1"
                            required
                            placeholder="30"
                        />
                        <InputError :message="form.errors.effectif" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="classe-samedi">Cours le samedi</Label>
                    <select
                        v-if="samediChoisi"
                        id="classe-samedi"
                        v-model="form.cours_samedi"
                        :class="classesSelect"
                    >
                        <option :value="true">Oui</option>
                        <option :value="false">Non</option>
                    </select>
                    <select
                        v-else
                        id="classe-samedi"
                        disabled
                        :class="classesSelect"
                    >
                        <option>{{ samediImpose ? 'Oui' : 'Non' }}</option>
                    </select>
                    <InputError :message="form.errors.cours_samedi" />
                </div>

                <p class="text-sm text-muted-foreground">
                    Ce choix ne concerne que la 3e année : selon l'école, elle
                    étudie ou non le samedi matin. La 1re et la 2e année n'ont
                    jamais cours le samedi ; de la 4e à la 6e, il y a toujours
                    cours le samedi matin.
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
