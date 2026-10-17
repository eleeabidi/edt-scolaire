<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import DirecteurFormDialog from '@/components/directeurs/DirecteurFormDialog.vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatutBadge from '@/components/StatutBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, index, show } from '@/routes/directeurs';
import type { Directeur, Pagination, User } from '@/types';

type Statut = User['statut'];

const props = defineProps<{
    directeurs: Pagination<Directeur>;
    filtres: { q: string; statut: Statut | null };
    compteurs: Partial<Record<Statut, number>>;
}>();

defineOptions({
    layout: { titre: 'Directeurs' },
});

// --- Onglets par statut ---
const onglets: { statut: Statut | null; libelle: string }[] = [
    { statut: null, libelle: 'Tous' },
    { statut: 'EN_ATTENTE', libelle: 'En attente' },
    { statut: 'ACTIF', libelle: 'Actifs' },
    { statut: 'REFUSE', libelle: 'Refusés' },
    { statut: 'DESACTIVE', libelle: 'Désactivés' },
];

function compteur(statut: Statut | null): number {
    if (statut === null) {
        return Object.values(props.compteurs).reduce(
            (total, n) => total + (n ?? 0),
            0,
        );
    }

    return props.compteurs[statut] ?? 0;
}

// --- Recherche et filtre (rechargés automatiquement) ---
const recherche = ref(props.filtres.q);
const statut = ref<Statut | null>(props.filtres.statut);

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

// --- Fenêtre Ajouter / Modifier ---
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

// --- Fenêtre Supprimer (désactiver) ---
const aSupprimer = ref<Directeur | null>(null);
const confirmationOuverte = ref(false);
const suppressionEnCours = ref(false);

const messageSuppression = computed(
    () =>
        `Le compte de ${aSupprimer.value?.prenom} ${aSupprimer.value?.nom} sera désactivé : il ne pourra plus se connecter, mais son historique est conservé.`,
);

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

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                title="Directeurs"
                description="Les directeurs et leurs écoles"
            />
            <Button @click="ajouter">
                <Plus />
                Ajouter un directeur
            </Button>
        </div>

        <!-- Onglets par statut -->
        <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="onglet in onglets"
                :key="onglet.libelle"
                type="button"
                class="flex shrink-0 items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors"
                :class="
                    statut === onglet.statut
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-muted'
                "
                @click="statut = onglet.statut"
            >
                {{ onglet.libelle }}
                <span
                    class="rounded-full px-1.5 text-xs"
                    :class="
                        onglet.statut === 'EN_ATTENTE' &&
                        compteur(onglet.statut) > 0
                            ? 'bg-[#FFC94D] text-[#2D2B45]'
                            : 'bg-black/10 dark:bg-white/15'
                    "
                >
                    {{ compteur(onglet.statut) }}
                </span>
            </button>
        </div>

        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="recherche"
                class="pl-9"
                placeholder="Rechercher par nom, email ou école…"
            />
        </div>

        <p
            v-if="directeurs.data.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
        >
            Aucun directeur trouvé.
        </p>

        <template v-else>
            <!-- Ordinateur : tableau -->
            <div class="hidden overflow-x-auto rounded-xl border md:block">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Directeur</th>
                            <th class="px-4 py-3 font-medium">École</th>
                            <th class="px-4 py-3 font-medium">Ville</th>
                            <th class="px-4 py-3 font-medium">Inscrit le</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="directeur in directeurs.data"
                            :key="directeur.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">
                                    {{ directeur.prenom }} {{ directeur.nom }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ directeur.email }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                {{ directeur.ecole?.nom ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ directeur.ecole?.ville ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ directeur.date_creation }}
                            </td>
                            <td class="px-4 py-3">
                                <StatutBadge :statut="directeur.statut" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        v-if="directeur.statut === 'EN_ATTENTE'"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="show(directeur.id)"
                                            >Examiner</Link
                                        >
                                    </Button>
                                    <Button
                                        v-else
                                        variant="ghost"
                                        size="icon"
                                        as-child
                                        :aria-label="`Voir ${directeur.prenom} ${directeur.nom}`"
                                    >
                                        <Link :href="show(directeur.id)"
                                            ><Eye
                                        /></Link>
                                    </Button>
                                    <template
                                        v-if="directeur.statut === 'ACTIF'"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Modifier ${directeur.prenom} ${directeur.nom}`"
                                            @click="modifier(directeur)"
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="text-destructive"
                                            :aria-label="`Supprimer ${directeur.prenom} ${directeur.nom}`"
                                            @click="
                                                demanderSuppression(directeur)
                                            "
                                        >
                                            <Trash2 />
                                        </Button>
                                    </template>
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
                    class="rounded-xl border p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold">
                                {{ directeur.prenom }} {{ directeur.nom }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ directeur.ecole?.nom }} ·
                                {{ directeur.ecole?.ville }}
                            </p>
                        </div>
                        <StatutBadge :statut="directeur.statut" />
                    </div>
                    <p class="mt-3 text-sm break-all">{{ directeur.email }}</p>
                    <div class="mt-3 flex gap-2">
                        <Button
                            :variant="
                                directeur.statut === 'EN_ATTENTE'
                                    ? 'default'
                                    : 'outline'
                            "
                            size="sm"
                            class="flex-1"
                            as-child
                        >
                            <Link :href="show(directeur.id)">
                                {{
                                    directeur.statut === 'EN_ATTENTE'
                                        ? 'Examiner'
                                        : 'Voir'
                                }}
                            </Link>
                        </Button>
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
                                class="flex-1 text-destructive"
                                @click="demanderSuppression(directeur)"
                            >
                                <Trash2 />
                                Supprimer
                            </Button>
                        </template>
                    </div>
                </div>
            </div>

            <PaginationLinks :pagination="directeurs" />
        </template>
    </div>

    <DirecteurFormDialog
        v-model:open="formulaireOuvert"
        :directeur="directeurEnCours"
    />

    <ConfirmationDialog
        v-model:open="confirmationOuverte"
        titre="Supprimer ce directeur ?"
        :message="messageSuppression"
        libelle-confirmer="Supprimer"
        :en-cours="suppressionEnCours"
        @confirmer="supprimer"
    />
</template>
