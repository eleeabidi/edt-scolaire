<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
import { computed } from 'vue';
import GrilleSemaine from '@/components/emploi/GrilleSemaine.vue';
import PageHeader from '@/components/PageHeader.vue';
import { formatDuree, formatHeure, typesSalle } from '@/lib/format';
import { index } from '@/routes/mon-emploi';
import type { LigneGrille, SeanceGrille } from '@/types';

// US19 — « Mon emploi du temps » (enseignant) : ses séances de l'emploi du temps publié,
// en vue semaine (jours en colonnes, créneaux en lignes) ou en vue jour (maquette 27).
type Vue = 'jour' | 'semaine';

const props = defineProps<{
    prenom: string;
    vue: Vue;
    jour: number; // 1 = lundi … 6 = samedi
    emploi: { annee: string; ecole: string } | null;
    lignes?: LigneGrille[];
    cases?: string[];
    seances?: SeanceGrille[];
    totalMinutes?: number;
}>();

defineOptions({
    layout: { titre: 'Mon emploi du temps' },
});

const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

function afficher(vue: Vue, jour = props.jour) {
    router.get(
        index.url(),
        { vue, jour },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['vue', 'jour'],
        },
    );
}

// --- Vue jour : les séances du jour choisi, avec les récréations ---
const seancesDuJour = computed(() =>
    (props.seances ?? [])
        .filter((s) => s.jour === props.jour)
        .sort((a, b) => a.debut.localeCompare(b.debut)),
);

type Element =
    | { type: 'seance'; seance: SeanceGrille }
    | { type: 'recreation'; libelle: string };

// Une récréation s'affiche entre deux séances du jour (ex. : 9 h 55 – 10 h 05)
const elementsDuJour = computed<Element[]>(() => {
    const elements: Element[] = [];
    const lignes = props.lignes ?? [];

    lignes.forEach((ligne, position) => {
        seancesDuJour.value
            .filter((s) => s.case === `${props.jour}-${ligne.debut}`)
            .forEach((seance) => elements.push({ type: 'seance', seance }));

        const suivante = lignes[position + 1];
        const avant = seancesDuJour.value.some((s) => s.fin <= ligne.fin);
        const apres = seancesDuJour.value.some(
            (s) => suivante && s.debut >= suivante.debut,
        );

        if (suivante && ligne.fin !== suivante.debut && avant && apres) {
            elements.push({
                type: 'recreation',
                libelle: `Récréation · ${formatHeure(ligne.fin)} – ${formatHeure(suivante.debut)}`,
            });
        }
    });

    return elements;
});

const bordures = {
    cours: 'border-primary',
    sport: 'border-succes',
    technologie: 'border-crayon',
} as const;
</script>

<template>
    <Head title="Mon emploi du temps" />

    <PageHeader
        :titre="`Bonjour ${prenom}`"
        :sous-titre="
            emploi
                ? `${emploi.ecole} · année scolaire ${emploi.annee}`
                : undefined
        "
    />

    <!-- 2a : aucun emploi du temps publié pour l'année scolaire en cours (les brouillons restent cachés) -->
    <section
        v-if="!emploi"
        class="flex flex-col items-center gap-3 rounded-2xl border bg-white p-10 text-center"
    >
        <div
            class="flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
        >
            <CalendarDays class="size-6" />
        </div>
        <p class="font-semibold">Aucun emploi du temps publié</p>
        <p class="max-w-md text-sm text-muted-foreground">
            Vous recevrez un email dès que le directeur aura publié l'emploi du
            temps de votre école.
        </p>
    </section>

    <!-- 2b : l'enseignant n'a aucune séance -->
    <section
        v-else-if="(seances ?? []).length === 0"
        class="flex flex-col items-center gap-3 rounded-2xl border bg-white p-10 text-center"
    >
        <div
            class="flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
        >
            <CalendarDays class="size-6" />
        </div>
        <p class="font-semibold">
            Vous n'avez aucune séance dans l'emploi du temps publié.
        </p>
    </section>

    <template v-else>
        <!-- Choix « Jour » ou « Semaine » -->
        <div
            class="mb-5 grid grid-cols-2 rounded-2xl border bg-white p-1.5 sm:max-w-sm"
            role="group"
            aria-label="Vue"
        >
            <button
                v-for="choix in ['jour', 'semaine'] as const"
                :key="choix"
                type="button"
                class="h-11 rounded-xl text-[15px] font-semibold transition-colors"
                :class="
                    vue === choix
                        ? 'bg-primary text-white'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :aria-pressed="vue === choix"
                @click="afficher(choix)"
            >
                {{ choix === 'jour' ? 'Jour' : 'Semaine' }}
            </button>
        </div>

        <!-- Vue jour -->
        <template v-if="vue === 'jour'">
            <div
                class="mb-6 grid grid-cols-6 gap-2 sm:max-w-xl"
                role="group"
                aria-label="Jour"
            >
                <button
                    v-for="(nom, j) in jours"
                    :key="nom"
                    type="button"
                    class="h-12 rounded-xl border text-[15px] font-semibold transition-colors"
                    :class="
                        jour === j + 1
                            ? 'border-primary bg-violet-clair text-primary'
                            : 'border-bordure bg-white text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="jour === j + 1"
                    @click="afficher('jour', j + 1)"
                >
                    {{ nom.slice(0, 3) }}
                </button>
            </div>

            <div class="mb-4 flex items-center justify-between sm:max-w-xl">
                <h2 class="text-xl font-bold">{{ jours[jour - 1] }}</h2>
                <span
                    class="rounded-full bg-violet-clair px-3 py-1 text-sm font-semibold text-primary"
                >
                    {{ seancesDuJour.length }}
                    {{ seancesDuJour.length > 1 ? 'séances' : 'séance' }}
                </span>
            </div>

            <p
                v-if="seancesDuJour.length === 0"
                class="rounded-xl bg-white px-4 py-6 text-center text-sm text-muted-foreground sm:max-w-xl"
            >
                Aucune séance ce jour.
            </p>

            <ul v-else class="grid gap-4 sm:max-w-xl">
                <li
                    v-for="(element, i) in elementsDuJour"
                    :key="i"
                    :class="element.type === 'seance' ? 'flex gap-4' : ''"
                >
                    <p
                        v-if="element.type === 'recreation'"
                        class="rounded-xl border border-dashed border-bordure py-2 text-center text-sm text-muted-foreground"
                    >
                        {{ element.libelle }}
                    </p>
                    <template v-else>
                        <div class="w-16 shrink-0 pt-3">
                            <p class="font-bold">
                                {{ formatHeure(element.seance.debut) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatHeure(element.seance.fin) }}
                            </p>
                        </div>
                        <div
                            class="flex-1 rounded-2xl border-l-4 px-5 py-3.5"
                            :class="[
                                typesSalle[element.seance.typeSalle].classes,
                                bordures[element.seance.typeSalle],
                            ]"
                        >
                            <p class="text-lg font-bold text-foreground">
                                {{ element.seance.classe }}
                            </p>
                            <p
                                v-for="m in element.seance.matieres"
                                :key="m.nom + m.debut"
                                class="text-muted-foreground"
                            >
                                {{ m.nom }}
                                <template
                                    v-if="element.seance.matieres.length > 1"
                                >
                                    · {{ formatHeure(m.debut) }} –
                                    {{ formatHeure(m.fin) }}
                                </template>
                            </p>
                            <p class="mt-1 text-sm text-foreground/80">
                                {{ element.seance.salle }}
                            </p>
                        </div>
                    </template>
                </li>
            </ul>
        </template>

        <!-- Vue semaine : les jours en colonnes, les créneaux en lignes -->
        <template v-else>
            <div class="mb-4 flex justify-end">
                <span
                    class="rounded-full bg-violet-clair px-3 py-1 text-xs font-semibold text-primary"
                >
                    {{ formatDuree(totalMinutes ?? 0) }} par semaine
                </span>
            </div>

            <GrilleSemaine
                :lignes="lignes ?? []"
                :cases="cases ?? []"
                :seances="seances ?? []"
                vue="enseignant"
            />
        </template>
    </template>
</template>
