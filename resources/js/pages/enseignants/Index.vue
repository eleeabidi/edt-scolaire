<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import BoutonIcone from '@/components/BoutonIcone.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import EnseignantFormDialog from '@/components/enseignants/EnseignantFormDialog.vue';
import FiltrePastilles from '@/components/FiltrePastilles.vue';
import Initiales from '@/components/Initiales.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatutBadge from '@/components/StatutBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, index } from '@/routes/enseignants';
import type { EcoleOption, Enseignant, Pagination } from '@/types';

// Écran 08 — Enseignants (Admin et Directeur)
type FiltreStatut = 'ACTIF' | 'DESACTIVE' | null;

const props = defineProps<{
    enseignants: Pagination<Enseignant>;
    filtres: { q: string; ecole: number | null; statut: FiltreStatut };
    ecoles: EcoleOption[];
    ecoleNom: string | null;
    estAdmin: boolean;
}>();

defineOptions({
    layout: { titre: 'Enseignants' },
});

const sousTitre = computed(() => {
    const total = `${props.enseignants.total} enseignant${props.enseignants.total > 1 ? 's' : ''}`;

    return props.ecoleNom ? `${props.ecoleNom} · ${total}` : total;
});

// --- Recherche et filtres (rechargés automatiquement) ---
const recherche = ref(props.filtres.q);
const ecole = ref<number | ''>(props.filtres.ecole ?? '');
const statut = ref<FiltreStatut>(props.filtres.statut);

const pastilles: { valeur: FiltreStatut; libelle: string }[] = [
    { valeur: null, libelle: 'Tous' },
    { valeur: 'ACTIF', libelle: 'Actifs' },
    { valeur: 'DESACTIVE', libelle: 'Désactivés' },
];

watchDebounced(
    [recherche, ecole, statut],
    () => {
        router.get(
            index.url(),
            {
                q: recherche.value || undefined,
                ecole: ecole.value || undefined,
                statut: statut.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    },
    { debounce: 300 },
);

// --- Fenêtre Ajouter / Modifier (F2) ---
const formulaireOuvert = ref(false);
const enseignantEnCours = ref<Enseignant | null>(null);

function ajouter() {
    enseignantEnCours.value = null;
    formulaireOuvert.value = true;
}

function modifier(enseignant: Enseignant) {
    enseignantEnCours.value = enseignant;
    formulaireOuvert.value = true;
}

// --- Fenêtre Supprimer (F3) ---
const aSupprimer = ref<Enseignant | null>(null);
const confirmationOuverte = ref(false);
const suppressionEnCours = ref(false);

function demanderSuppression(enseignant: Enseignant) {
    aSupprimer.value = enseignant;
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
    <Head title="Enseignants" />

    <PageHeader titre="Enseignants" :sous-titre="sousTitre">
        <Button @click="ajouter">
            <Plus />
            Ajouter un enseignant
        </Button>
    </PageHeader>

    <!-- Recherche + filtres -->
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative lg:w-90">
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-indice"
            />
            <Input
                v-model="recherche"
                class="pl-12"
                placeholder="Rechercher un enseignant"
            />
        </div>
        <FiltrePastilles v-model="statut" :options="pastilles" />
        <select
            v-if="estAdmin"
            v-model="ecole"
            aria-label="Filtrer par école"
            class="h-12 rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 lg:ml-auto lg:w-56"
        >
            <option value="">Toutes les écoles</option>
            <option v-for="e in ecoles" :key="e.id" :value="e.id">
                {{ e.nom }}
            </option>
        </select>
    </div>

    <p
        v-if="enseignants.data.length === 0"
        class="rounded-xl border border-dashed bg-white p-10 text-center text-muted-foreground"
    >
        Aucun enseignant trouvé.
    </p>

    <template v-else>
        <!-- Ordinateur : tableau -->
        <div class="hidden overflow-x-auto rounded-xl border bg-white md:block">
            <table class="w-full text-sm">
                <thead class="bg-fond text-left text-muted-foreground">
                    <tr>
                        <th class="px-6 py-4 font-medium">Nom</th>
                        <th class="px-4 py-4 font-medium">Email</th>
                        <th class="px-4 py-4 font-medium">Téléphone</th>
                        <th v-if="estAdmin" class="px-4 py-4 font-medium">
                            École
                        </th>
                        <th class="px-4 py-4 font-medium">Statut</th>
                        <th class="px-4 py-4 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="enseignant in enseignants.data"
                        :key="enseignant.id"
                        class="border-t"
                    >
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <Initiales
                                    :prenom="enseignant.prenom"
                                    :nom="enseignant.nom"
                                />
                                <span class="text-[15px] font-semibold">
                                    {{ enseignant.prenom }} {{ enseignant.nom }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">{{ enseignant.email }}</td>
                        <td class="px-4 py-3.5">
                            {{ enseignant.telephone ?? '—' }}
                        </td>
                        <td v-if="estAdmin" class="px-4 py-3.5">
                            {{ enseignant.ecole ?? '—' }}
                        </td>
                        <td class="px-4 py-3.5">
                            <StatutBadge :statut="enseignant.statut" />
                        </td>
                        <td class="px-4 py-3.5">
                            <div
                                v-if="enseignant.statut !== 'DESACTIVE'"
                                class="flex gap-2"
                            >
                                <BoutonIcone
                                    :libelle="`Modifier ${enseignant.prenom} ${enseignant.nom}`"
                                    @click="modifier(enseignant)"
                                >
                                    <Pencil />
                                </BoutonIcone>
                                <BoutonIcone
                                    danger
                                    :libelle="`Supprimer ${enseignant.prenom} ${enseignant.nom}`"
                                    @click="demanderSuppression(enseignant)"
                                >
                                    <Trash2 />
                                </BoutonIcone>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Téléphone : cartes -->
        <div class="grid gap-3 md:hidden">
            <div
                v-for="enseignant in enseignants.data"
                :key="enseignant.id"
                class="rounded-xl border bg-white p-4"
            >
                <div class="flex items-center gap-3">
                    <Initiales
                        :prenom="enseignant.prenom"
                        :nom="enseignant.nom"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">
                            {{ enseignant.prenom }} {{ enseignant.nom }}
                        </p>
                        <p
                            v-if="enseignant.telephone"
                            class="text-sm text-muted-foreground"
                        >
                            {{ enseignant.telephone }}
                        </p>
                    </div>
                    <StatutBadge :statut="enseignant.statut" />
                </div>
                <p class="mt-3 text-sm break-all text-muted-foreground">
                    {{ enseignant.email }}
                    <template v-if="estAdmin">
                        · {{ enseignant.ecole }}</template
                    >
                </p>
                <div
                    v-if="enseignant.statut !== 'DESACTIVE'"
                    class="mt-3 flex gap-2"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        @click="modifier(enseignant)"
                    >
                        <Pencil />
                        Modifier
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1 text-erreur"
                        @click="demanderSuppression(enseignant)"
                    >
                        <Trash2 />
                        Supprimer
                    </Button>
                </div>
            </div>
        </div>

        <PaginationLinks class="mt-6" :pagination="enseignants" />
    </template>

    <EnseignantFormDialog
        v-model:open="formulaireOuvert"
        :enseignant="enseignantEnCours"
        :ecoles="ecoles"
        :est-admin="estAdmin"
    />

    <ConfirmationDialog
        v-model:open="confirmationOuverte"
        titre="Supprimer ce compte ?"
        :message="`Le compte de ${aSupprimer?.prenom} ${aSupprimer?.nom} sera désactivé : la personne ne pourra plus se connecter, mais son historique est conservé.`"
        libelle-confirmer="Supprimer"
        :en-cours="suppressionEnCours"
        @confirmer="supprimer"
    />
</template>
