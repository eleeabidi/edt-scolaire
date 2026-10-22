<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Link2, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import BanniereEchecEnvoi from '@/components/BanniereEchecEnvoi.vue';
import BarreHeures from '@/components/BarreHeures.vue';
import BoutonIcone from '@/components/BoutonIcone.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import EnseignantFormDialog from '@/components/enseignants/EnseignantFormDialog.vue';
import InfoBox from '@/components/InfoBox.vue';
import FiltrePastilles from '@/components/FiltrePastilles.vue';
import Initiales from '@/components/Initiales.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatutBadge from '@/components/StatutBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { show as affectations } from '@/routes/affectations';
import { destroy, identifiants, index } from '@/routes/enseignants';
import type { EcoleOption, Enseignant, Pagination } from '@/types';

// Écran 08 (Admin) / 14 (Directeur, avec les affectations) — Enseignants
type FiltreStatut = 'ACTIF' | 'DESACTIVE' | 'SANS_AFFECTATION' | null;

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

// Une recherche ou un filtre est-il appliqué ? (pour choisir le message « liste vide »)
const filtreApplique = computed(
    () =>
        Boolean(props.filtres.q) ||
        props.filtres.ecole !== null ||
        props.filtres.statut !== null,
);

// --- Recherche et filtres (rechargés automatiquement) ---
const recherche = ref(props.filtres.q);
const ecole = ref<number | ''>(props.filtres.ecole ?? '');
const statut = ref<FiltreStatut>(props.filtres.statut);

const pastilles: { valeur: FiltreStatut; libelle: string }[] = [
    { valeur: null, libelle: 'Tous' },
    { valeur: 'ACTIF', libelle: 'Actifs' },
    { valeur: 'DESACTIVE', libelle: 'Désactivés' },
    // Le directeur voit aussi les enseignants sans affectation (maquette 14)
    ...(props.estAdmin
        ? []
        : [
              {
                  valeur: 'SANS_AFFECTATION' as const,
                  libelle: 'Sans affectation',
              },
          ]),
];

// Liste courte des classes ou des matières (« Aucune » s'il n'y en a pas)
const resume = (valeurs: string[]) =>
    valeurs.length === 0 ? 'Aucune' : valeurs.join(', ');

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

    <BanniereEchecEnvoi :url-renvoi="(id) => identifiants.url(id)" />

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

    <!-- 3a : la recherche ne donne aucun résultat -->
    <p
        v-if="enseignants.data.length === 0 && filtreApplique"
        class="rounded-xl border border-dashed bg-white p-10 text-center text-muted-foreground"
    >
        Aucun enseignant ne correspond à votre recherche.
    </p>

    <!-- 2a : aucun enseignant n'est enregistré → proposer d'en ajouter un -->
    <div
        v-else-if="enseignants.data.length === 0"
        class="flex flex-col items-center gap-4 rounded-xl border border-dashed bg-white p-10 text-center"
    >
        <p class="font-semibold">Aucun enseignant</p>
        <Button @click="ajouter">
            <Plus />
            Ajouter un enseignant
        </Button>
    </div>

    <template v-else>
        <!-- Ordinateur : tableau -->
        <div class="hidden overflow-x-auto rounded-xl border bg-white md:block">
            <table class="w-full text-sm">
                <thead class="bg-fond text-left text-muted-foreground">
                    <tr>
                        <th class="px-6 py-4 font-medium">Nom</th>
                        <template v-if="estAdmin">
                            <th class="px-4 py-4 font-medium">Email</th>
                            <th class="px-4 py-4 font-medium">Téléphone</th>
                            <th class="px-4 py-4 font-medium">École</th>
                        </template>
                        <template v-else>
                            <th class="px-4 py-4 font-medium">Classes</th>
                            <th class="px-4 py-4 font-medium">Matières</th>
                            <th class="px-4 py-4 font-medium">Heures</th>
                        </template>
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
                        <template v-if="estAdmin">
                            <td class="px-4 py-3.5">{{ enseignant.email }}</td>
                            <td class="px-4 py-3.5">
                                {{ enseignant.telephone ?? '—' }}
                            </td>
                            <td class="px-4 py-3.5">
                                {{ enseignant.ecole ?? '—' }}
                            </td>
                        </template>
                        <template v-else>
                            <td
                                class="max-w-48 truncate px-4 py-3.5"
                                :class="{
                                    'text-indice':
                                        enseignant.classes.length === 0,
                                }"
                                :title="resume(enseignant.classes)"
                            >
                                {{ resume(enseignant.classes) }}
                            </td>
                            <td
                                class="max-w-56 truncate px-4 py-3.5"
                                :class="{
                                    'text-indice':
                                        enseignant.matieres.length === 0,
                                }"
                                :title="resume(enseignant.matieres)"
                            >
                                {{ resume(enseignant.matieres) }}
                            </td>
                            <td class="px-4 py-3.5">
                                <BarreHeures
                                    :minutes="enseignant.minutes"
                                    :maximum-heures="
                                        enseignant.volume_horaire_max
                                    "
                                />
                            </td>
                        </template>
                        <td class="px-4 py-3.5">
                            <StatutBadge :statut="enseignant.statut" />
                        </td>
                        <td class="px-4 py-3.5">
                            <div
                                v-if="enseignant.statut !== 'DESACTIVE'"
                                class="flex gap-2"
                            >
                                <BoutonIcone
                                    v-if="!estAdmin"
                                    class="text-primary"
                                    :libelle="`Affectations de ${enseignant.prenom} ${enseignant.nom}`"
                                    @click="
                                        router.visit(
                                            affectations(enseignant.id),
                                        )
                                    "
                                >
                                    <Link2 />
                                </BoutonIcone>
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
                <p
                    v-if="estAdmin"
                    class="mt-3 text-sm break-all text-muted-foreground"
                >
                    {{ enseignant.email }} · {{ enseignant.ecole }}
                </p>
                <div v-else class="mt-3 grid gap-2 text-sm">
                    <p class="text-muted-foreground">
                        {{ resume(enseignant.classes) }} ·
                        {{ resume(enseignant.matieres) }}
                    </p>
                    <BarreHeures
                        :minutes="enseignant.minutes"
                        :maximum-heures="enseignant.volume_horaire_max"
                    />
                </div>
                <div
                    v-if="enseignant.statut !== 'DESACTIVE'"
                    class="mt-3 flex gap-2"
                >
                    <Button
                        v-if="!estAdmin"
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        @click="router.visit(affectations(enseignant.id))"
                    >
                        <Link2 />
                        Affectations
                    </Button>
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

        <InfoBox v-if="!estAdmin" class="mt-6">
            Le bouton « Affectations » (icône lien) ouvre les classes et les
            matières de l'enseignant. Un enseignant ne peut pas dépasser son
            volume horaire maximum (18 h par défaut).
        </InfoBox>
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
