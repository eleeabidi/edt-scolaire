<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import EnseignantFormDialog from '@/components/enseignants/EnseignantFormDialog.vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatutBadge from '@/components/StatutBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, index } from '@/routes/enseignants';
import type { EcoleOption, Enseignant, Pagination } from '@/types';

const props = defineProps<{
    enseignants: Pagination<Enseignant>;
    filtres: { q: string; ecole: number | null };
    ecoles: EcoleOption[];
    estAdmin: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Enseignants', href: index() }],
    },
});

// --- Recherche et filtre (rechargés automatiquement) ---
const recherche = ref(props.filtres.q);
const ecole = ref<number | ''>(props.filtres.ecole ?? '');

watchDebounced(
    [recherche, ecole],
    () => {
        router.get(
            index.url(),
            {
                q: recherche.value || undefined,
                ecole: ecole.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    },
    { debounce: 300 },
);

// --- Fenêtre Ajouter / Modifier ---
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

// --- Fenêtre Supprimer (désactiver) ---
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

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                title="Enseignants"
                :description="`${enseignants.total} enseignant${enseignants.total > 1 ? 's' : ''}`"
            />
            <Button @click="ajouter">
                <Plus />
                Ajouter un enseignant
            </Button>
        </div>

        <!-- Recherche + filtre école (Admin) -->
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="recherche"
                    class="pl-9"
                    placeholder="Rechercher par nom, email ou spécialité…"
                />
            </div>
            <select
                v-if="estAdmin"
                v-model="ecole"
                aria-label="Filtrer par école"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-60 dark:bg-input/30"
            >
                <option value="">Toutes les écoles</option>
                <option v-for="e in ecoles" :key="e.id" :value="e.id">
                    {{ e.nom }}
                </option>
            </select>
        </div>

        <p
            v-if="enseignants.data.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
        >
            Aucun enseignant trouvé.
        </p>

        <template v-else>
            <!-- Ordinateur : tableau -->
            <div class="hidden overflow-x-auto rounded-xl border md:block">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nom</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Téléphone</th>
                            <th class="px-4 py-3 font-medium">Spécialité</th>
                            <th v-if="estAdmin" class="px-4 py-3 font-medium">
                                École
                            </th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="enseignant in enseignants.data"
                            :key="enseignant.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ enseignant.prenom }} {{ enseignant.nom }}
                            </td>
                            <td class="px-4 py-3">{{ enseignant.email }}</td>
                            <td class="px-4 py-3">
                                {{ enseignant.telephone ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ enseignant.specialite ?? '—' }}
                            </td>
                            <td v-if="estAdmin" class="px-4 py-3">
                                {{ enseignant.ecole ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatutBadge :statut="enseignant.statut" />
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    v-if="enseignant.statut !== 'DESACTIVE'"
                                    class="flex justify-end gap-1"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Modifier ${enseignant.prenom} ${enseignant.nom}`"
                                        @click="modifier(enseignant)"
                                    >
                                        <Pencil />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive"
                                        :aria-label="`Supprimer ${enseignant.prenom} ${enseignant.nom}`"
                                        @click="demanderSuppression(enseignant)"
                                    >
                                        <Trash2 />
                                    </Button>
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
                    class="rounded-xl border p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold">
                                {{ enseignant.prenom }} {{ enseignant.nom }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ enseignant.specialite }}
                            </p>
                        </div>
                        <StatutBadge :statut="enseignant.statut" />
                    </div>
                    <div class="mt-3 grid gap-1 text-sm">
                        <p class="break-all">{{ enseignant.email }}</p>
                        <p v-if="enseignant.telephone">
                            {{ enseignant.telephone }}
                        </p>
                        <p v-if="estAdmin" class="text-muted-foreground">
                            {{ enseignant.ecole }}
                        </p>
                    </div>
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
                            class="flex-1 text-destructive"
                            @click="demanderSuppression(enseignant)"
                        >
                            <Trash2 />
                            Supprimer
                        </Button>
                    </div>
                </div>
            </div>

            <PaginationLinks :pagination="enseignants" />
        </template>
    </div>

    <EnseignantFormDialog
        v-model:open="formulaireOuvert"
        :enseignant="enseignantEnCours"
        :ecoles="ecoles"
        :est-admin="estAdmin"
    />

    <ConfirmationDialog
        v-model:open="confirmationOuverte"
        titre="Supprimer cet enseignant ?"
        :message="`Le compte de ${aSupprimer?.prenom} ${aSupprimer?.nom} sera désactivé : il ne pourra plus se connecter, mais son historique est conservé.`"
        libelle-confirmer="Supprimer"
        :en-cours="suppressionEnCours"
        @confirmer="supprimer"
    />
</template>
