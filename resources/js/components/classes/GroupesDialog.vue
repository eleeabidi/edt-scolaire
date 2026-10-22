<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BoutonIcone from '@/components/BoutonIcone.vue';
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
import { groupes as enregistrerGroupes } from '@/routes/classes';
import type { Classe, Groupe } from '@/types';

// Fenêtre F8 — Groupes d'une classe : on modifie la liste, puis « Enregistrer » envoie tout
const props = defineProps<{
    classe: Classe | null;
}>();

const ouvert = defineModel<boolean>('open', { required: true });

const form = useForm({ groupes: [] as Groupe[] });
const nouveauNom = ref('');
const nouvelEffectif = ref<number | ''>('');

const prochainNom = computed(() => `Groupe ${form.groupes.length + 1}`);

watch(ouvert, (estOuvert) => {
    if (!estOuvert) {
        return;
    }

    form.clearErrors();
    form.groupes = props.classe?.groupes.map((g) => ({ ...g })) ?? [];
    nouveauNom.value = '';
    nouvelEffectif.value = '';
});

function ajouter() {
    if (!nouvelEffectif.value) {
        return;
    }

    form.groupes.push({
        id: null,
        nom: nouveauNom.value.trim() || prochainNom.value,
        effectif: Number(nouvelEffectif.value),
    });
    nouveauNom.value = '';
    nouvelEffectif.value = '';
}

function retirer(position: number) {
    form.groupes.splice(position, 1);
}

// Les erreurs du serveur arrivent sous la forme « groupes.0.nom »
const erreurs = computed(() => Object.values(form.errors));

function enregistrer() {
    if (!props.classe) {
        return;
    }

    form.put(enregistrerGroupes.url(props.classe.id), {
        preserveScroll: true,
        onSuccess: () => (ouvert.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="ouvert">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Groupes de la {{ classe?.nom }}</DialogTitle>
                <DialogDescription>
                    Effectif de la classe : {{ classe?.effectif }} élèves.
                </DialogDescription>
            </DialogHeader>

            <ul
                v-if="form.groupes.length > 0"
                class="divide-y divide-bordure rounded-xl border"
            >
                <li
                    v-for="(groupe, position) in form.groupes"
                    :key="groupe.id ?? `nouveau-${position}`"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <span class="flex-1 font-semibold">{{ groupe.nom }}</span>
                    <span class="text-sm text-muted-foreground">
                        {{ groupe.effectif }} élèves
                    </span>
                    <BoutonIcone
                        danger
                        :libelle="`Retirer ${groupe.nom}`"
                        @click="retirer(position)"
                    >
                        <Trash2 />
                    </BoutonIcone>
                </li>
            </ul>
            <p
                v-else
                class="rounded-xl bg-fond p-4 text-center text-sm text-muted-foreground"
            >
                Cette classe n'a pas de groupe.
            </p>

            <form
                class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]"
                @submit.prevent="ajouter"
            >
                <div class="grid gap-2">
                    <Label for="groupe-nom">Nouveau groupe</Label>
                    <Input
                        id="groupe-nom"
                        v-model="nouveauNom"
                        :placeholder="prochainNom"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="groupe-effectif">Effectif</Label>
                    <Input
                        id="groupe-effectif"
                        v-model="nouvelEffectif"
                        type="number"
                        min="1"
                        placeholder="10"
                    />
                </div>
                <Button type="submit" variant="outline">Ajouter</Button>
            </form>

            <InputError
                v-for="erreur in erreurs"
                :key="erreur"
                :message="erreur"
            />

            <DialogFooter class="gap-3">
                <Button type="button" variant="outline" @click="ouvert = false">
                    Annuler
                </Button>
                <Button :disabled="form.processing" @click="enregistrer">
                    <Spinner v-if="form.processing" />
                    Enregistrer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
