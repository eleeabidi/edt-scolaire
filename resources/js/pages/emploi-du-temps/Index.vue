<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, Check, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDuree } from '@/lib/format';
import { generer, index } from '@/routes/emploi';

// Rubrique « Emploi du temps » (directeur) : choisir l'année scolaire et générer (US13)
type Emploi = {
    id: number;
    annee: string;
    statut: 'brouillon' | 'publie';
    statutLibelle: string;
    seances: number;
    classes: { nom: string; minutes: number; attendues: number }[];
};

const props = defineProps<{
    ecoleNom: string;
    annees: string[];
    annee: string;
    emploi: Emploi | null;
}>();

defineOptions({
    layout: { titre: 'Emploi du temps' },
});

// --- Année scolaire ---
const anneeChoisie = ref(props.annee);

function changerAnnee() {
    router.get(
        index.url(),
        { annee: anneeChoisie.value },
        { preserveScroll: true },
    );
}

const sousTitre = computed(() =>
    props.emploi
        ? `${props.ecoleNom} · année scolaire ${props.emploi.annee}.`
        : `${props.ecoleNom} · générez l'emploi du temps de toutes les classes de l'école.`,
);

// --- Générer ---
const generation = ref(false);
const remplacementOuvert = ref(false);
const causes = ref<string[]>([]);
const impossibleOuvert = ref(false);

// 3a : un brouillon existe déjà → confirmer son remplacement avant de générer
function demanderGeneration() {
    if (props.emploi) {
        remplacementOuvert.value = true;

        return;
    }

    lancer(false);
}

function lancer(remplacer: boolean) {
    router.post(
        generer.url(),
        { annee_scolaire: anneeChoisie.value, remplacer },
        {
            preserveScroll: true,
            onStart: () => (generation.value = true),
            onFinish: () => {
                generation.value = false;
                remplacementOuvert.value = false;
            },
            onError: (erreurs) => {
                // 4a / 5a : les causes, une par ligne
                if (erreurs.generation) {
                    causes.value = erreurs.generation.split('\n');
                    impossibleOuvert.value = true;
                } else if (erreurs.remplacer) {
                    remplacementOuvert.value = true;
                }
            },
        },
    );
}

const selectClasses =
    'h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-56';
</script>

<template>
    <Head title="Emploi du temps" />

    <PageHeader titre="Emploi du temps" :sous-titre="sousTitre">
        <Button
            v-if="emploi && emploi.statut === 'brouillon'"
            variant="outline"
            :disabled="generation"
            @click="demanderGeneration"
        >
            <Spinner v-if="generation" />
            Générer
        </Button>
    </PageHeader>

    <!-- 2a : aucun emploi du temps pour cette année → proposer de le générer -->
    <section
        v-if="!emploi"
        class="mx-auto flex max-w-3xl flex-col items-center rounded-2xl border bg-white p-8 text-center"
    >
        <div
            class="flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
        >
            <CalendarDays class="size-6" />
        </div>
        <h2 class="mt-4 text-xl font-bold">
            Aucun emploi du temps pour {{ anneeChoisie }}
        </h2>
        <p class="mt-2 max-w-xl text-sm text-muted-foreground">
            Choisissez l'année scolaire, puis lancez la génération.
            L'application place les séances de toutes les classes en respectant
            le guide pédagogique, les règles de gestion des emplois du temps,
            les disponibilités des enseignants et les salles en service.
        </p>

        <div
            class="mt-6 flex w-full flex-col items-stretch gap-3 sm:w-auto sm:flex-row sm:items-end"
        >
            <div class="grid gap-2 text-left">
                <Label for="annee">Année scolaire</Label>
                <select
                    id="annee"
                    v-model="anneeChoisie"
                    :class="selectClasses"
                    @change="changerAnnee"
                >
                    <option v-for="a in annees" :key="a" :value="a">
                        {{ a }}
                    </option>
                </select>
            </div>
            <Button :disabled="generation" @click="demanderGeneration">
                <Spinner v-if="generation" />
                Générer
            </Button>
        </div>

        <div
            class="mt-6 w-full max-w-xl rounded-xl bg-fond p-5 text-left text-sm"
        >
            <p class="font-semibold">Avant de générer, le système vérifie :</p>
            <ul class="mt-3 grid gap-2 text-muted-foreground">
                <li class="flex items-start gap-2">
                    <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                    Chaque matière de chaque classe a un enseignant (Détails des
                    enseignants)
                </li>
                <li class="flex items-start gap-2">
                    <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                    Au moins une salle de technologie est en service (Salles)
                </li>
                <li class="flex items-start gap-2">
                    <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                    Au moins une salle de sport est en service (Salles)
                </li>
            </ul>
        </div>
    </section>

    <!-- Emploi du temps de l'année : statut et heures placées par classe -->
    <template v-else>
        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="grid gap-2">
                <Label for="annee">Année scolaire</Label>
                <select
                    id="annee"
                    v-model="anneeChoisie"
                    :class="selectClasses"
                    @change="changerAnnee"
                >
                    <option v-for="a in annees" :key="a" :value="a">
                        {{ a }}
                    </option>
                </select>
            </div>
            <div class="flex gap-2">
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="
                        emploi.statut === 'publie'
                            ? 'bg-succes/15 text-[#1F8A6B]'
                            : 'bg-[#FFC94D]/25 text-[#8A6100]'
                    "
                >
                    {{ emploi.statutLibelle }}
                </span>
                <span
                    class="rounded-full bg-violet-clair px-3 py-1 text-xs font-semibold text-primary"
                >
                    {{ emploi.seances }} séances
                </span>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-fond text-left text-muted-foreground">
                    <tr>
                        <th class="px-6 py-4 font-medium">Classe</th>
                        <th class="px-4 py-4 font-medium">Heures placées</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="classe in emploi.classes"
                        :key="classe.nom"
                        class="border-t"
                    >
                        <td class="px-6 py-3.5 text-[15px] font-semibold">
                            {{ classe.nom }}
                        </td>
                        <td class="px-4 py-3.5">
                            {{ formatDuree(classe.minutes) }} sur
                            {{ formatDuree(classe.attendues) }} par semaine
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </template>

    <!-- 3a : remplacer le brouillon -->
    <ConfirmationDialog
        v-model:open="remplacementOuvert"
        titre="Remplacer le brouillon ?"
        :message="`Un emploi du temps en brouillon existe déjà pour l'année scolaire ${anneeChoisie}. La nouvelle génération le remplacera, avec les modifications faites à la main.`"
        libelle-confirmer="Remplacer"
        icone="alerte"
        :en-cours="generation"
        @confirmer="lancer(true)"
    />

    <!-- 4a / 5a : génération impossible -->
    <Dialog v-model:open="impossibleOuvert">
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-erreur/15 text-erreur"
            >
                <TriangleAlert class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle>Génération impossible</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    Corrigez ces données, puis relancez la génération :
                </DialogDescription>
            </DialogHeader>
            <ul
                class="grid list-disc gap-1.5 pl-5 text-sm text-muted-foreground"
            >
                <li v-for="cause in causes" :key="cause">{{ cause }}</li>
            </ul>
            <DialogFooter>
                <Button @click="impossibleOuvert = false">Compris</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
