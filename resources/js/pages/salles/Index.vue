<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import BoutonIcone from '@/components/BoutonIcone.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import FiltrePastilles from '@/components/FiltrePastilles.vue';
import PageHeader from '@/components/PageHeader.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import SalleFormDialog from '@/components/salles/SalleFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { typesSalle } from '@/lib/format';
import { enService, horsService, index } from '@/routes/salles';
import type { Pagination, Salle, TypeSalle } from '@/types';

// Écran 17 — Salles (directeur, son école). Une salle n'est jamais supprimée :
// elle est mise hors service (par exemple pendant des travaux), puis mise en service à nouveau.
const props = defineProps<{
    salles: Pagination<Salle>;
    filtres: { q: string; type: TypeSalle | null };
    ecoleNom: string;
}>();

defineOptions({
    layout: { titre: 'Salles' },
});

const sousTitre = computed(
    () =>
        `${props.ecoleNom} · ${props.salles.total} salle${props.salles.total > 1 ? 's' : ''}`,
);

const filtreApplique = computed(
    () => Boolean(props.filtres.q) || props.filtres.type !== null,
);

// --- Recherche et filtre par type ---
const recherche = ref(props.filtres.q);
const type = ref<TypeSalle | null>(props.filtres.type);

const pastilles: { valeur: TypeSalle | null; libelle: string }[] = [
    { valeur: null, libelle: 'Tous' },
    { valeur: 'cours', libelle: 'Cours' },
    { valeur: 'sport', libelle: 'Sport' },
    { valeur: 'technologie', libelle: 'Technologie' },
];

watchDebounced(
    [recherche, type],
    () => {
        router.get(
            index.url(),
            { q: recherche.value || undefined, type: type.value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    },
    { debounce: 300 },
);

// --- Fenêtre Mettre en service une salle / Modifier (F9) ---
const formulaireOuvert = ref(false);
const salleEnCours = ref<Salle | null>(null);

function ajouter() {
    salleEnCours.value = null;
    formulaireOuvert.value = true;
}

function modifier(salle: Salle) {
    salleEnCours.value = salle;
    formulaireOuvert.value = true;
}

// --- Fenêtre Mettre hors service (F10) ou Mettre en service, après confirmation ---
const salleAChanger = ref<Salle | null>(null);
const confirmationOuverte = ref(false);
const changementEnCours = ref(false);

function demanderChangement(salle: Salle) {
    salleAChanger.value = salle;
    confirmationOuverte.value = true;
}

function changerEtat() {
    const salle = salleAChanger.value;

    if (!salle) {
        return;
    }

    const route = salle.en_service ? horsService : enService;

    router.patch(
        route.url(salle.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (changementEnCours.value = true),
            onFinish: () => {
                changementEnCours.value = false;
                confirmationOuverte.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Salles" />

    <PageHeader titre="Salles" :sous-titre="sousTitre">
        <Button @click="ajouter">
            <Plus />
            Mettre en service une salle
        </Button>
    </PageHeader>

    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="relative lg:w-90">
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-indice"
            />
            <Input
                v-model="recherche"
                class="pl-12"
                placeholder="Rechercher une salle"
            />
        </div>
        <FiltrePastilles v-model="type" :options="pastilles" />
    </div>

    <p
        v-if="salles.data.length === 0 && filtreApplique"
        class="rounded-xl border border-dashed bg-white p-10 text-center text-muted-foreground"
    >
        Aucune salle ne correspond à votre recherche.
    </p>

    <div
        v-else-if="salles.data.length === 0"
        class="flex flex-col items-center gap-4 rounded-xl border border-dashed bg-white p-10 text-center"
    >
        <p class="font-semibold">Aucune salle</p>
        <Button @click="ajouter">
            <Plus />
            Mettre en service une salle
        </Button>
    </div>

    <template v-else>
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-fond text-left text-muted-foreground">
                    <tr>
                        <th class="px-6 py-4 font-medium">Salle</th>
                        <th class="px-4 py-4 font-medium">Type</th>
                        <th class="px-4 py-4 font-medium">Capacité</th>
                        <th class="px-4 py-4 font-medium">État</th>
                        <th class="px-4 py-4 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="salle in salles.data"
                        :key="salle.id"
                        class="border-t"
                    >
                        <td class="px-6 py-3.5 text-[15px] font-semibold">
                            {{ salle.nom }}
                        </td>
                        <td class="px-4 py-3.5">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="typesSalle[salle.type].classes"
                            >
                                {{ typesSalle[salle.type].libelle }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">{{ salle.capacite }} élèves</td>
                        <td class="px-4 py-3.5">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
                                :class="
                                    salle.en_service
                                        ? 'bg-succes/15 text-[#1F8A6B]'
                                        : 'bg-erreur/15 text-[#C93D3D]'
                                "
                            >
                                {{
                                    salle.en_service
                                        ? 'En service'
                                        : 'Hors service'
                                }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-2">
                                <BoutonIcone
                                    :libelle="`Modifier ${salle.nom}`"
                                    @click="modifier(salle)"
                                >
                                    <Pencil />
                                </BoutonIcone>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="whitespace-nowrap"
                                    :class="
                                        salle.en_service
                                            ? 'text-[#C93D3D]'
                                            : 'text-primary'
                                    "
                                    @click="demanderChangement(salle)"
                                >
                                    {{
                                        salle.en_service
                                            ? 'Mettre hors service'
                                            : 'Mettre en service'
                                    }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationLinks class="mt-6" :pagination="salles" />
    </template>

    <SalleFormDialog v-model:open="formulaireOuvert" :salle="salleEnCours" />

    <ConfirmationDialog
        v-if="salleAChanger?.en_service ?? true"
        v-model:open="confirmationOuverte"
        titre="Mettre cette salle hors service ?"
        :message="`La salle « ${salleAChanger?.nom} » ne sera plus utilisée pour les emplois du temps, par exemple pendant des travaux. Vous pourrez la mettre en service à nouveau à tout moment.`"
        libelle-confirmer="Mettre hors service"
        icone="alerte"
        :en-cours="changementEnCours"
        @confirmer="changerEtat"
    />
    <ConfirmationDialog
        v-else
        v-model:open="confirmationOuverte"
        titre="Mettre cette salle en service ?"
        :message="`La salle « ${salleAChanger?.nom} » sera de nouveau utilisée pour les emplois du temps.`"
        libelle-confirmer="Mettre en service"
        icone="service"
        :danger="false"
        :en-cours="changementEnCours"
        @confirmer="changerEtat"
    />
</template>
