<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Check, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import GrilleSemaine from '@/components/emploi/GrilleSemaine.vue';
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
import { conflits, generer, index } from '@/routes/emploi';
import type { LigneGrille, SeanceGrille, VueGrille } from '@/types';

// Rubrique « Emploi du temps » (directeur) : générer (US13) et consulter la grille (US15)
type Emploi = {
    id: number;
    annee: string;
    statut: 'brouillon' | 'publie';
    statutLibelle: string;
};

type Option = { id: number; nom: string };

const props = defineProps<{
    ecoleNom: string;
    annees: string[];
    annee: string;
    emploi: Emploi | null;
    // Grille (seulement si l'emploi du temps existe)
    lignes?: LigneGrille[];
    cases?: string[];
    options?: Record<VueGrille, Option[]>;
    par?: VueGrille;
    choix?: number;
    seances?: SeanceGrille[];
    totalMinutes?: number;
    nbConflits?: number;
    surlignee?: number | null;
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

// --- Afficher par classe, enseignant ou salle (T15.3) ---
const vues: { valeur: VueGrille; libelle: string }[] = [
    { valeur: 'classe', libelle: 'Classe' },
    { valeur: 'enseignant', libelle: 'Enseignant' },
    { valeur: 'salle', libelle: 'Salle' },
];

const par = ref<VueGrille>(props.par ?? 'classe');
const choix = ref<number>(props.choix ?? 0);

function afficher() {
    router.get(
        index.url(),
        { annee: props.annee, par: par.value, id: choix.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['par', 'choix', 'seances', 'totalMinutes'],
        },
    );
}

function changerVue(vue: VueGrille) {
    par.value = vue;
    choix.value = props.options?.[vue][0]?.id ?? 0;
    afficher();
}

const nomChoisi = computed(
    () =>
        props.options?.[par.value].find((o) => o.id === choix.value)?.nom ?? '',
);

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
        <Button v-if="emploi" variant="outline" as-child>
            <Link :href="conflits(emploi.id)">
                Conflits ({{ nbConflits ?? 0 }})
            </Link>
        </Button>
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

    <!-- Emploi du temps de l'année : grille de la semaine (US15) -->
    <template v-else>
        <div
            class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Afficher par</span>
                    <div
                        class="flex h-12 rounded-md border bg-white p-1"
                        role="group"
                        aria-label="Afficher par"
                    >
                        <button
                            v-for="vue in vues"
                            :key="vue.valeur"
                            type="button"
                            class="rounded px-4 text-sm font-semibold transition-colors"
                            :class="
                                par === vue.valeur
                                    ? 'bg-primary text-white'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="par === vue.valeur"
                            @click="changerVue(vue.valeur)"
                        >
                            {{ vue.libelle }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="choix">{{
                        vues.find((v) => v.valeur === par)?.libelle
                    }}</Label>
                    <select
                        id="choix"
                        v-model="choix"
                        :class="selectClasses"
                        @change="afficher"
                    >
                        <option
                            v-for="o in options?.[par] ?? []"
                            :key="o.id"
                            :value="o.id"
                        >
                            {{ o.nom }}
                        </option>
                    </select>
                </div>
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
                    {{ formatDuree(totalMinutes ?? 0) }} par semaine
                </span>
            </div>
        </div>

        <!-- 4a : aucune séance pour ce choix -->
        <p
            v-if="(seances ?? []).length === 0"
            class="mb-4 rounded-xl bg-violet-clair px-4 py-3 text-sm"
        >
            {{ nomChoisi }} n'a aucune séance dans cet emploi du temps.
        </p>

        <GrilleSemaine
            :lignes="lignes ?? []"
            :cases="cases ?? []"
            :seances="seances ?? []"
            :vue="par"
            :surlignee="surlignee"
        />
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
