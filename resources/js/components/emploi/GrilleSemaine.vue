<script setup lang="ts">
import { computed } from 'vue';
import { formatHeure, typesSalle } from '@/lib/format';
import type { LigneGrille, SeanceGrille, VueGrille } from '@/types';

// T15.1 — Grille de la semaine : les jours en colonnes, les créneaux en lignes ;
// chaque case montre les séances du créneau (une, ou deux avec 1 h d'EPS ou de technologie).
const props = defineProps<{
    lignes: LigneGrille[];
    cases: string[]; // cases qui existent (« jour-HH:MM ») ; les autres sont grisées
    seances: SeanceGrille[];
    vue: VueGrille; // classe, enseignant ou salle : ce qu'on n'affiche pas sur les séances
}>();

const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

const existe = (jour: number, debut: string) =>
    props.cases.includes(`${jour}-${debut}`);

const seancesDe = (jour: number, debut: string) =>
    props.seances.filter((s) => s.case === `${jour}-${debut}`);

// Récréation entre deux lignes qui ne se suivent pas (ex. : 09:55 → 10:05)
const recreationApres = (position: number) => {
    const ligne = props.lignes[position];
    const suivante = props.lignes[position + 1];

    return suivante && ligne.fin !== suivante.debut
        ? `Récréation · ${formatHeure(ligne.fin)} – ${formatHeure(suivante.debut)}`
        : null;
};

// Titre d'une séance : ce qui n'est pas déjà choisi dans « Afficher par »
const titre = (s: SeanceGrille) =>
    ({
        classe: `${s.enseignant} · ${s.salle}`,
        enseignant: `${s.classe} · ${s.salle}`,
        salle: `${s.classe} · ${s.enseignant}`,
    })[props.vue];

const legende = computed(() => [
    { libelle: 'Salle de cours', classes: typesSalle.cours.classes },
    { libelle: 'Salle de sport', classes: typesSalle.sport.classes },
    {
        libelle: 'Salle de technologie',
        classes: typesSalle.technologie.classes,
    },
]);
</script>

<template>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="w-full min-w-[900px] table-fixed text-sm">
            <thead class="bg-fond text-muted-foreground">
                <tr>
                    <th class="w-28 px-4 py-3 text-left font-medium">
                        Créneau
                    </th>
                    <th
                        v-for="jour in jours"
                        :key="jour"
                        class="px-2 py-3 font-medium"
                    >
                        {{ jour }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <template
                    v-for="(ligne, position) in lignes"
                    :key="ligne.debut"
                >
                    <tr class="border-t align-top">
                        <td class="px-4 py-3 font-semibold whitespace-nowrap">
                            {{ formatHeure(ligne.debut) }} –
                            {{ formatHeure(ligne.fin) }}
                            <span
                                v-if="ligne.midi"
                                class="block text-xs font-normal text-muted-foreground"
                            >
                                Midi
                            </span>
                        </td>
                        <td
                            v-for="(jour, j) in jours"
                            :key="jour"
                            class="px-1.5 py-2"
                        >
                            <div
                                v-if="existe(j + 1, ligne.debut)"
                                class="grid gap-1.5"
                            >
                                <template
                                    v-for="seance in seancesDe(
                                        j + 1,
                                        ligne.debut,
                                    )"
                                    :key="seance.id"
                                >
                                    <slot
                                        name="seance"
                                        :seance="seance"
                                        :titre="titre(seance)"
                                    >
                                        <div
                                            class="rounded-lg px-2.5 py-2 text-xs leading-snug"
                                            :class="
                                                typesSalle[seance.typeSalle]
                                                    .classes
                                            "
                                        >
                                            <p
                                                class="font-bold text-foreground"
                                            >
                                                {{ titre(seance) }}
                                            </p>
                                            <p
                                                v-for="m in seance.matieres"
                                                :key="m.nom + m.debut"
                                                class="text-muted-foreground"
                                            >
                                                {{ m.nom }} ·
                                                {{ formatHeure(m.debut) }} –
                                                {{ formatHeure(m.fin) }}
                                            </p>
                                        </div>
                                    </slot>
                                </template>
                            </div>
                            <!-- Samedi après-midi (et samedi midi) : grisé -->
                            <div
                                v-else
                                class="flex h-full min-h-12 items-center justify-center rounded-lg bg-muted text-xs text-muted-foreground"
                            >
                                Pas de cours
                            </div>
                        </td>
                    </tr>
                    <tr
                        v-if="recreationApres(position)"
                        class="border-t bg-fond"
                    >
                        <td
                            colspan="7"
                            class="py-1.5 text-center text-xs text-muted-foreground"
                        >
                            {{ recreationApres(position) }}
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-wrap gap-4 text-sm text-muted-foreground">
        <span
            v-for="element in legende"
            :key="element.libelle"
            class="flex items-center gap-2"
        >
            <span class="size-4 rounded" :class="element.classes" />
            {{ element.libelle }}
        </span>
        <span class="flex items-center gap-2">
            <span class="size-4 rounded bg-muted" />
            Pas de cours
        </span>
    </div>
</template>
