<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import BoutonIcone from '@/components/BoutonIcone.vue';
import ClasseFormDialog from '@/components/classes/ClasseFormDialog.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import FiltrePastilles from '@/components/FiltrePastilles.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { libelleNiveau } from '@/lib/format';
import { destroy, index } from '@/routes/classes';
import type { Classe, Pagination } from '@/types';

// Écran 16 — Classes (directeur, son école)
const props = defineProps<{
    classes: Pagination<Classe>;
    filtres: { q: string; niveau: number | null };
    ecoleNom: string;
}>();

defineOptions({
    layout: { titre: 'Classes' },
});

const sousTitre = computed(
    () =>
        `${props.ecoleNom} · ${props.classes.total} classe${props.classes.total > 1 ? 's' : ''}`,
);

const filtreApplique = computed(
    () => Boolean(props.filtres.q) || props.filtres.niveau !== null,
);

// --- Recherche et filtre par niveau ---
const recherche = ref(props.filtres.q);
const niveau = ref<number | null>(props.filtres.niveau);

const pastilles: { valeur: number | null; libelle: string }[] = [
    { valeur: null, libelle: 'Tous les niveaux' },
    ...[1, 2, 3, 4, 5, 6].map((n) => ({
        valeur: n,
        libelle: n === 1 ? '1re' : `${n}e`,
    })),
];

watchDebounced(
    [recherche, niveau],
    () => {
        router.get(
            index.url(),
            {
                q: recherche.value || undefined,
                niveau: niveau.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    },
    { debounce: 300 },
);

// --- Fenêtre Ajouter / Modifier (F7) ---
const formulaireOuvert = ref(false);
const classeEnCours = ref<Classe | null>(null);

function ajouter() {
    classeEnCours.value = null;
    formulaireOuvert.value = true;
}

function modifier(classe: Classe) {
    classeEnCours.value = classe;
    formulaireOuvert.value = true;
}

// --- Fenêtre Supprimer (F8) ---
const aSupprimer = ref<Classe | null>(null);
const confirmationOuverte = ref(false);
const suppressionEnCours = ref(false);

function demanderSuppression(classe: Classe) {
    aSupprimer.value = classe;
    confirmationOuverte.value = true;
}

function supprimer() {
    if (!aSupprimer.value) {
        return;
    }

    router.delete(destroy.url(aSupprimer.value.id), {
        preserveScroll: true,
        onStart: () => (suppressionEnCours.value = true),
        onFinish: () => {
            suppressionEnCours.value = false;
            confirmationOuverte.value = false;
        },
    });
}
</script>

<template>
    <Head title="Classes" />

    <PageHeader titre="Classes" :sous-titre="sousTitre">
        <Button @click="ajouter">
            <Plus />
            Ajouter une classe
        </Button>
    </PageHeader>

    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative lg:w-80">
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-indice"
            />
            <Input
                v-model="recherche"
                class="pl-12"
                placeholder="Rechercher une classe"
            />
        </div>
        <FiltrePastilles v-model="niveau" :options="pastilles" />
    </div>

    <p
        v-if="classes.data.length === 0 && filtreApplique"
        class="rounded-xl border border-dashed bg-white p-10 text-center text-muted-foreground"
    >
        Aucune classe ne correspond à votre recherche.
    </p>

    <div
        v-else-if="classes.data.length === 0"
        class="flex flex-col items-center gap-4 rounded-xl border border-dashed bg-white p-10 text-center"
    >
        <p class="font-semibold">Aucune classe</p>
        <Button @click="ajouter">
            <Plus />
            Ajouter une classe
        </Button>
    </div>

    <template v-else>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-fond text-left text-muted-foreground">
                    <tr>
                        <th class="px-6 py-4 font-medium">Classe</th>
                        <th class="px-4 py-4 font-medium">Niveau</th>
                        <th class="px-4 py-4 font-medium">Effectif</th>
                        <th class="px-4 py-4 font-medium">Cours le samedi</th>
                        <th class="px-4 py-4 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="classe in classes.data"
                        :key="classe.id"
                        class="border-t"
                    >
                        <td class="px-6 py-3.5 text-[15px] font-semibold">
                            {{ classe.nom }}
                        </td>
                        <td class="px-4 py-3.5">
                            {{ libelleNiveau(classe.niveau) }}
                        </td>
                        <td class="px-4 py-3.5">{{ classe.effectif }}</td>
                        <td class="px-4 py-3.5">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="
                                    classe.cours_samedi
                                        ? 'bg-succes/15 text-[#1F8A6B]'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ classe.cours_samedi ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex gap-2">
                                <BoutonIcone
                                    :libelle="`Modifier ${classe.nom}`"
                                    @click="modifier(classe)"
                                >
                                    <Pencil />
                                </BoutonIcone>
                                <BoutonIcone
                                    danger
                                    :libelle="`Supprimer ${classe.nom}`"
                                    @click="demanderSuppression(classe)"
                                >
                                    <Trash2 />
                                </BoutonIcone>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationLinks class="mt-6" :pagination="classes" />
    </template>

    <ClasseFormDialog v-model:open="formulaireOuvert" :classe="classeEnCours" />

    <ConfirmationDialog
        v-model:open="confirmationOuverte"
        titre="Supprimer cette classe ?"
        :message="`La classe ${aSupprimer?.nom} sera supprimée, avec les affectations de ses enseignants.`"
        libelle-confirmer="Supprimer"
        :en-cours="suppressionEnCours"
        @confirmer="supprimer"
    />
</template>
