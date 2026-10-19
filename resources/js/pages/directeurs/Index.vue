<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import BanniereEchecEnvoi from '@/components/BanniereEchecEnvoi.vue';
import BoutonIcone from '@/components/BoutonIcone.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import DirecteurFormDialog from '@/components/directeurs/DirecteurFormDialog.vue';
import FiltrePastilles from '@/components/FiltrePastilles.vue';
import Initiales from '@/components/Initiales.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatutBadge from '@/components/StatutBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, identifiants, index, show } from '@/routes/directeurs';
import type { Directeur, Pagination, User } from '@/types';

// Écran 09 — Directeurs (Admin)
type Statut = User['statut'];

const props = defineProps<{
    directeurs: Pagination<Directeur>;
    filtres: { q: string; statut: Statut | null };
    compteurs: Partial<Record<Statut, number>>;
}>();

defineOptions({
    layout: { titre: 'Directeurs' },
});

// --- Recherche et filtre par statut (rechargés automatiquement) ---
const recherche = ref(props.filtres.q);
const statut = ref<Statut | null>(props.filtres.statut);

const pastilles = computed<{ valeur: Statut | null; libelle: string }[]>(() => {
    const enAttente = props.compteurs.EN_ATTENTE ?? 0;

    return [
        { valeur: null, libelle: 'Tous' },
        {
            valeur: 'EN_ATTENTE',
            libelle: enAttente ? `En attente (${enAttente})` : 'En attente',
        },
        { valeur: 'ACTIF', libelle: 'Actifs' },
        { valeur: 'REFUSE', libelle: 'Refusés' },
        { valeur: 'DESACTIVE', libelle: 'Désactivés' },
    ];
});

watchDebounced(
    [recherche, statut],
    () => {
        router.get(
            index.url(),
            {
                q: recherche.value || undefined,
                statut: statut.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    },
    { debounce: 300 },
);

function voir(directeur: Directeur) {
    router.visit(show(directeur.id));
}

// --- Fenêtre Ajouter / Modifier (F4) ---
const formulaireOuvert = ref(false);
const directeurEnCours = ref<Directeur | null>(null);

function ajouter() {
    directeurEnCours.value = null;
    formulaireOuvert.value = true;
}

function modifier(directeur: Directeur) {
    directeurEnCours.value = directeur;
    formulaireOuvert.value = true;
}

// --- Fenêtre Supprimer (F3) ---
const aSupprimer = ref<Directeur | null>(null);
const confirmationOuverte = ref(false);
const suppressionEnCours = ref(false);

function demanderSuppression(directeur: Directeur) {
    aSupprimer.value = directeur;
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
    <Head title="Directeurs" />

    <PageHeader
        titre="Directeurs"
        sous-titre="Gérez les comptes des directeurs et validez les inscriptions."
    >
        <Button @click="ajouter">
            <Plus />
            Ajouter un directeur
        </Button>
    </PageHeader>

    <BanniereEchecEnvoi :url-renvoi="(id) => identifiants.url(id)" />

    <!-- Recherche + filtres -->
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative lg:w-80">
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-indice"
            />
            <Input
                v-model="recherche"
                class="pl-12"
                placeholder="Rechercher un directeur"
            />
        </div>
        <FiltrePastilles v-model="statut" :options="pastilles" />
    </div>

    <!-- Valider une inscription 2a : aucune inscription en attente -->
    <p
        v-if="
            directeurs.data.length === 0 &&
            filtres.statut === 'EN_ATTENTE' &&
            !filtres.q
        "
        class="rounded-xl border border-dashed bg-white p-10 text-center text-muted-foreground"
    >
        Aucune inscription en attente.
    </p>

    <!-- 3a : la recherche ne donne aucun résultat -->
    <p
        v-else-if="
            directeurs.data.length === 0 && (filtres.q || filtres.statut)
        "
        class="rounded-xl border border-dashed bg-white p-10 text-center text-muted-foreground"
    >
        Aucun directeur ne correspond à votre recherche.
    </p>

    <!-- 2a : aucun directeur n'est enregistré → proposer d'en ajouter un -->
    <div
        v-else-if="directeurs.data.length === 0"
        class="flex flex-col items-center gap-4 rounded-xl border border-dashed bg-white p-10 text-center"
    >
        <p class="font-semibold">Aucun directeur</p>
        <Button @click="ajouter">
            <Plus />
            Ajouter un directeur
        </Button>
    </div>

    <template v-else>
        <!-- Ordinateur : tableau -->
        <div class="hidden overflow-x-auto rounded-xl border bg-white md:block">
            <table class="w-full text-sm">
                <thead class="bg-fond text-left text-muted-foreground">
                    <tr>
                        <th class="px-6 py-4 font-medium">Nom</th>
                        <th class="px-4 py-4 font-medium">École</th>
                        <th class="px-4 py-4 font-medium">Email</th>
                        <th class="px-4 py-4 font-medium">Statut</th>
                        <th class="px-4 py-4 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="directeur in directeurs.data"
                        :key="directeur.id"
                        class="border-t"
                    >
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <Initiales
                                    :prenom="directeur.prenom"
                                    :nom="directeur.nom"
                                />
                                <span class="text-[15px] font-semibold">
                                    {{ directeur.prenom }} {{ directeur.nom }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            {{ directeur.ecole?.nom ?? '—' }}
                        </td>
                        <td class="px-4 py-3.5">{{ directeur.email }}</td>
                        <td class="px-4 py-3.5">
                            <StatutBadge :statut="directeur.statut" />
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex gap-2">
                                <template v-if="directeur.statut === 'ACTIF'">
                                    <BoutonIcone
                                        :libelle="`Modifier ${directeur.prenom} ${directeur.nom}`"
                                        @click="modifier(directeur)"
                                    >
                                        <Pencil />
                                    </BoutonIcone>
                                    <BoutonIcone
                                        danger
                                        :libelle="`Supprimer ${directeur.prenom} ${directeur.nom}`"
                                        @click="demanderSuppression(directeur)"
                                    >
                                        <Trash2 />
                                    </BoutonIcone>
                                </template>
                                <BoutonIcone
                                    v-else
                                    class="text-primary"
                                    :libelle="`Voir ${directeur.prenom} ${directeur.nom}`"
                                    @click="voir(directeur)"
                                >
                                    <Eye />
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
                v-for="directeur in directeurs.data"
                :key="directeur.id"
                class="rounded-xl border bg-white p-4"
            >
                <div class="flex items-center gap-3">
                    <Initiales
                        :prenom="directeur.prenom"
                        :nom="directeur.nom"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">
                            {{ directeur.prenom }} {{ directeur.nom }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ directeur.ecole?.nom }}
                        </p>
                    </div>
                    <StatutBadge :statut="directeur.statut" />
                </div>
                <p class="mt-3 text-sm break-all text-muted-foreground">
                    {{ directeur.email }}
                </p>
                <div class="mt-3 flex gap-2">
                    <template v-if="directeur.statut === 'ACTIF'">
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1"
                            @click="modifier(directeur)"
                        >
                            <Pencil />
                            Modifier
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1 text-erreur"
                            @click="demanderSuppression(directeur)"
                        >
                            <Trash2 />
                            Supprimer
                        </Button>
                    </template>
                    <Button
                        v-else
                        :variant="
                            directeur.statut === 'EN_ATTENTE'
                                ? 'default'
                                : 'outline'
                        "
                        size="sm"
                        class="flex-1"
                        @click="voir(directeur)"
                    >
                        <Eye />
                        {{
                            directeur.statut === 'EN_ATTENTE'
                                ? "Examiner l'inscription"
                                : 'Voir'
                        }}
                    </Button>
                </div>
            </div>
        </div>

        <PaginationLinks class="mt-6" :pagination="directeurs" />
    </template>

    <DirecteurFormDialog
        v-model:open="formulaireOuvert"
        :directeur="directeurEnCours"
    />

    <ConfirmationDialog
        v-model:open="confirmationOuverte"
        titre="Supprimer ce compte ?"
        :message="`Le compte de ${aSupprimer?.prenom} ${aSupprimer?.nom} sera désactivé : il ne pourra plus se connecter. Son école et ses enseignants restent enregistrés.`"
        libelle-confirmer="Supprimer"
        :en-cours="suppressionEnCours"
        @confirmer="supprimer"
    />
</template>
