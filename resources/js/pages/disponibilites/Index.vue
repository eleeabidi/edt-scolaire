<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDuree, formatHeure } from '@/lib/format';
import { index, update } from '@/routes/disponibilites';

// Écran 18 — Disponibilités (directeur, enseignants actifs de son école)
type Creneau = {
    cle: string; // « jour-heure de début », ex. « 1-08:00 »
    jour: number;
    debut: string;
    fin: string;
    minutes: number; // heures comptées (récréation non déduite)
};

const props = defineProps<{
    enseignants: { id: number; nom: string }[];
    enseignantId: number | null;
    creneaux: Creneau[];
    indisponibles: string[];
    minutesDisponibles: number;
    minutesAffectees: number;
}>();

defineOptions({
    layout: { titre: 'Disponibilités' },
});

const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

// --- Enseignant choisi ---
const enseignant = ref(props.enseignantId);

watch(enseignant, (id) => {
    router.get(
        index.url(),
        { enseignant: id ?? undefined },
        { preserveScroll: true },
    );
});

// --- Grille : une ligne par créneau horaire, une colonne par jour ---
const lignes = computed(() => {
    const vues = new Map<string, { debut: string; fin: string }>();

    for (const c of props.creneaux) {
        vues.set(c.debut, { debut: c.debut, fin: c.fin });
    }

    return [...vues.values()].sort((a, b) => a.debut.localeCompare(b.debut));
});

const creneauDu = (jour: number, debut: string) =>
    props.creneaux.find((c) => c.jour === jour && c.debut === debut);

// Récréation entre deux lignes qui ne se suivent pas (ex. : 09:55 → 10:05)
const recreationApres = (position: number) => {
    const ligne = lignes.value[position];
    const suivante = lignes.value[position + 1];

    return suivante && ligne.fin !== suivante.debut
        ? `Récréation · ${formatHeure(ligne.fin)} – ${formatHeure(suivante.debut)}`
        : null;
};

// --- Créneaux indisponibles (modifiés localement, puis « Enregistrer ») ---
const indisponibles = ref(new Set(props.indisponibles));

watch(
    () => props.indisponibles,
    (valeurs) => (indisponibles.value = new Set(valeurs)),
);

function basculer(cle: string) {
    const copie = new Set(indisponibles.value);

    if (copie.has(cle)) {
        copie.delete(cle);
    } else {
        copie.add(cle);
    }

    indisponibles.value = copie;
}

const minutesDisponibles = computed(() =>
    props.creneaux
        .filter((c) => !indisponibles.value.has(c.cle))
        .reduce((total, c) => total + c.minutes, 0),
);

const enregistrement = ref(false);

function enregistrer() {
    if (!props.enseignantId) {
        return;
    }

    router.put(
        update.url(props.enseignantId),
        { indisponibles: [...indisponibles.value] },
        {
            preserveScroll: true,
            onStart: () => (enregistrement.value = true),
            onFinish: () => (enregistrement.value = false),
        },
    );
}
</script>

<template>
    <Head title="Disponibilités" />

    <PageHeader
        titre="Disponibilités"
        sous-titre="Cliquez sur les créneaux où l'enseignant n'est pas disponible."
    />

    <p
        v-if="enseignants.length === 0"
        class="rounded-xl border border-dashed bg-white p-10 text-center text-muted-foreground"
    >
        Aucun enseignant actif dans votre école.
    </p>

    <template v-else>
        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="grid gap-2">
                <Label for="enseignant">Enseignant</Label>
                <select
                    id="enseignant"
                    v-model="enseignant"
                    class="h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-72"
                >
                    <option v-for="e in enseignants" :key="e.id" :value="e.id">
                        {{ e.nom }}
                    </option>
                </select>
            </div>
            <div class="flex gap-2">
                <span
                    class="rounded-full bg-succes/15 px-3 py-1 text-xs font-semibold text-[#1F8A6B]"
                >
                    {{ formatDuree(minutesDisponibles) }} disponibles
                </span>
                <span
                    class="rounded-full bg-violet-clair px-3 py-1 text-xs font-semibold text-primary"
                >
                    {{ formatDuree(minutesAffectees) }} affectées
                </span>
            </div>
        </div>

        <!-- Avertissement : moins d'heures disponibles que d'heures affectées -->
        <div
            v-if="minutesDisponibles < minutesAffectees"
            class="mb-6 flex items-start gap-3 rounded-xl bg-erreur/15 px-4 py-3 text-sm text-[#C93D3D]"
            role="alert"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0" />
            Attention : {{ formatDuree(minutesDisponibles) }} disponibles pour
            {{ formatDuree(minutesAffectees) }} affectées. La génération ne
            pourra pas placer toutes ses heures.
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-fond text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Créneau</th>
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
                        <tr class="border-t">
                            <td
                                class="px-4 py-2 font-semibold whitespace-nowrap"
                            >
                                {{ formatHeure(ligne.debut) }} –
                                {{ formatHeure(ligne.fin) }}
                                <span
                                    v-if="ligne.debut === '12:00'"
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
                                <button
                                    v-if="creneauDu(j + 1, ligne.debut)"
                                    type="button"
                                    class="h-10 w-full rounded-lg border text-xs font-semibold transition-colors"
                                    :class="
                                        indisponibles.has(
                                            `${j + 1}-${ligne.debut}`,
                                        )
                                            ? 'border-erreur/30 bg-erreur/15 text-[#C93D3D]'
                                            : 'border-bordure bg-white hover:bg-violet-clair'
                                    "
                                    :aria-pressed="
                                        indisponibles.has(
                                            `${j + 1}-${ligne.debut}`,
                                        )
                                    "
                                    :aria-label="`${jour} ${formatHeure(ligne.debut)}`"
                                    @click="basculer(`${j + 1}-${ligne.debut}`)"
                                >
                                    {{
                                        indisponibles.has(
                                            `${j + 1}-${ligne.debut}`,
                                        )
                                            ? 'Indisponible'
                                            : ''
                                    }}
                                </button>
                                <!-- Samedi après-midi : grisé -->
                                <div
                                    v-else
                                    class="flex h-10 items-center justify-center rounded-lg bg-muted text-xs text-muted-foreground"
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

        <div
            class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
                <span class="flex items-center gap-2">
                    <span
                        class="size-4 rounded border border-bordure bg-white"
                    />
                    Disponible
                </span>
                <span class="flex items-center gap-2">
                    <span class="size-4 rounded bg-erreur/15" />
                    Indisponible
                </span>
                <span class="flex items-center gap-2">
                    <span class="size-4 rounded bg-muted" />
                    Pas de cours (samedi après-midi)
                </span>
            </div>
            <Button :disabled="enregistrement" @click="enregistrer">
                <Spinner v-if="enregistrement" />
                Enregistrer
            </Button>
        </div>
    </template>
</template>
