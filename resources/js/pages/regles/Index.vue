<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Clock,
    Info,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FiltrePastilles from '@/components/FiltrePastilles.vue';
import InfoBox from '@/components/InfoBox.vue';
import PageHeader from '@/components/PageHeader.vue';
import { formatDuree, formatHeure, libelleNiveau } from '@/lib/format';

// Écran 20 — Règles de gestion des emplois du temps (consultation seule)
type Creneau = { debut: string; fin: string };

type NiveauRegles = {
    niveau: number;
    regles: string[];
    totalMinutes: number;
    reglesPropres: boolean;
    coursSamedi: 'oui' | 'non' | 'selon_classe';
};

const props = defineProps<{
    generales: {
        journee: string;
        horaireSamedi: string;
        pauseMidi: string;
        minimumParMatiere: number;
        recreations: string[];
        midiLibere: string;
        plusieursMatieres: string;
    };
    creneaux: Creneau[];
    creneauxSamedi: Creneau[];
    matieresMidi: string[];
    niveaux: NiveauRegles[];
}>();

defineOptions({
    layout: { titre: 'Règles de gestion' },
});

// « 08:00-17:00 » → ['08:00', '17:00']
const plage = (valeur: string) => valeur.split('-');

const cartes = computed(() => {
    const [debut, fin] = plage(props.generales.journee);
    const [midiDebut, midiFin] = plage(props.generales.pauseMidi);

    return [
        {
            titre: 'Lundi → samedi',
            texte: 'Le samedi : matin seulement',
            icone: CalendarDays,
        },
        {
            titre: `${formatHeure(debut)} → ${formatHeure(fin)}`,
            texte: `${props.creneaux.length} créneaux par jour`,
            icone: Clock,
        },
        {
            titre: `${formatHeure(midiDebut)} → ${formatHeure(midiFin)}`,
            texte: 'Pause déjeuner, de préférence',
            icone: SlidersHorizontal,
        },
        {
            titre: `${props.generales.minimumParMatiere} min`,
            texte: 'Minimum par matière dans une séance',
            icone: Check,
        },
    ];
});

const niveauChoisi = ref(1);
const pastilles = props.niveaux.map((n) => ({
    valeur: n.niveau,
    libelle: libelleNiveau(n.niveau),
}));
const niveau = computed(() =>
    props.niveaux.find((n) => n.niveau === niveauChoisi.value)!,
);

function moment(creneau: Creneau): 'Matin' | 'Midi' | 'Après-midi' {
    const [midiDebut, midiFin] = plage(props.generales.pauseMidi);

    if (creneau.debut === midiDebut && creneau.fin === midiFin) {
        return 'Midi';
    }

    return creneau.debut < midiDebut ? 'Matin' : 'Après-midi';
}

const recreations = computed(() =>
    props.generales.recreations
        .map((r) => formatHeure(plage(r)[0]))
        .join(' et '),
);
</script>

<template>
    <Head title="Règles de gestion des emplois du temps" />

    <PageHeader
        titre="Règles de gestion des emplois du temps"
        sous-titre="Règles nationales que la génération respectera. Consultation seule."
    />

    <h2 class="mb-4 text-xl font-bold">Règles générales</h2>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            v-for="carte in cartes"
            :key="carte.titre"
            class="flex items-start gap-3 rounded-xl border bg-white p-5"
        >
            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-violet-clair text-primary"
            >
                <component :is="carte.icone" class="size-5" />
            </div>
            <div>
                <p class="text-lg font-bold">{{ carte.titre }}</p>
                <p class="text-sm text-muted-foreground">{{ carte.texte }}</p>
            </div>
        </div>
    </div>

    <FiltrePastilles v-model="niveauChoisi" :options="pastilles" class="my-6" />

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border bg-white p-6">
            <h3 class="text-xl font-bold">
                Règles propres à la {{ libelleNiveau(niveau.niveau) }}
            </h3>
            <p
                v-if="!niveau.reglesPropres"
                class="mt-4 flex items-start gap-3 rounded-xl bg-violet-clair px-4 py-3 text-sm"
            >
                <Info class="mt-0.5 size-5 shrink-0 text-primary" />
                Ce niveau n'a pas de règle propre : les règles générales
                s'appliquent.
            </p>
            <ul class="mt-4 grid gap-3 text-[15px]">
                <li class="flex items-start gap-3">
                    <Check class="mt-0.5 size-5 shrink-0 text-succes" />
                    {{ formatDuree(niveau.totalMinutes) }} de cours par semaine
                </li>
                <li
                    v-for="regle in niveau.regles"
                    :key="regle"
                    class="flex items-start gap-3"
                >
                    <Check class="mt-0.5 size-5 shrink-0 text-succes" />
                    {{ regle }}
                </li>
                <li class="flex items-start gap-3">
                    <Check class="mt-0.5 size-5 shrink-0 text-succes" />
                    {{ generales.plusieursMatieres }}
                </li>
            </ul>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <h3 class="text-xl font-bold">Créneaux de la journée</h3>
            <ul class="mt-4 grid gap-3 text-[15px]">
                <li
                    v-for="creneau in creneaux"
                    :key="creneau.debut"
                    class="flex flex-wrap items-center gap-3"
                >
                    <span
                        class="w-22 rounded-full px-2.5 py-0.5 text-center text-xs font-semibold"
                        :class="
                            moment(creneau) === 'Midi'
                                ? 'bg-crayon/25 text-[#8A6100]'
                                : 'bg-violet-clair text-primary'
                        "
                    >
                        {{ moment(creneau) }}
                    </span>
                    <span class="font-semibold">
                        {{ formatHeure(creneau.debut) }} →
                        {{ formatHeure(creneau.fin) }}
                    </span>
                    <span
                        v-if="moment(creneau) === 'Midi'"
                        class="text-sm text-muted-foreground sm:ml-auto"
                    >
                        {{ matieresMidi.join(', ') }}
                    </span>
                </li>
            </ul>

            <p class="mt-5 border-t pt-4 text-sm">
                <span class="font-semibold">Samedi : </span>
                <template v-if="niveau.coursSamedi === 'non'">
                    pas de cours pour ce niveau.
                </template>
                <template v-else>
                    <span
                        v-for="(creneau, i) in creneauxSamedi"
                        :key="creneau.debut"
                    >
                        {{ formatHeure(creneau.debut) }} →
                        {{ formatHeure(creneau.fin)
                        }}{{ i < creneauxSamedi.length - 1 ? ', ' : '' }}
                    </span>
                    <span
                        v-if="niveau.coursSamedi === 'selon_classe'"
                        class="text-muted-foreground"
                    >
                        (seulement si la classe a « cours le samedi »)</span
                    >
                </template>
            </p>
        </section>
    </div>

    <InfoBox class="mt-6">
        Récréations de 10 min ({{ recreations }}), comptées dans les heures de
        cours. {{ generales.midiLibere }}, pour pouvoir déjeuner.
    </InfoBox>
</template>
