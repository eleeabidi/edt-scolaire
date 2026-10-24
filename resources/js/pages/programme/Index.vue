<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FiltrePastilles from '@/components/FiltrePastilles.vue';
import InfoBox from '@/components/InfoBox.vue';
import PageHeader from '@/components/PageHeader.vue';
import { formatDuree, libelleNiveau, typesSalle } from '@/lib/format';
import type { TypeSalle } from '@/types';

// Écran 19 — Guide pédagogique (consultation seule)
type MatiereDuNiveau = {
    nom: string;
    minutes: number;
    autoriseeMidi: boolean;
    typeSalle: TypeSalle;
};

type Niveau = {
    niveau: number;
    totalMinutes: number;
    matieres: MatiereDuNiveau[];
};

const props = defineProps<{
    niveaux: Niveau[];
}>();

defineOptions({
    layout: { titre: 'Guide pédagogique' },
});

const niveauChoisi = ref(1);
const pastilles = props.niveaux.map((n) => ({
    valeur: n.niveau,
    libelle: libelleNiveau(n.niveau),
}));

const niveau = computed(() =>
    props.niveaux.find((n) => n.niveau === niveauChoisi.value)!,
);
</script>

<template>
    <Head title="Guide pédagogique" />

    <PageHeader
        titre="Guide pédagogique"
        sous-titre="Matières et volumes horaires par niveau, fixés par le décret. Consultation seule."
    />

    <FiltrePastilles v-model="niveauChoisi" :options="pastilles" class="mb-6" />

    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-fond text-left text-muted-foreground">
                <tr>
                    <th class="px-6 py-4 font-medium">Matière</th>
                    <th class="px-4 py-4 font-medium">Heures / semaine</th>
                    <th class="px-4 py-4 font-medium">Autorisée à midi</th>
                    <th class="px-4 py-4 font-medium">Type de salle</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="matiere in niveau.matieres"
                    :key="matiere.nom"
                    class="border-t"
                >
                    <td class="px-6 py-3.5 text-[15px] font-semibold">
                        {{ matiere.nom }}
                    </td>
                    <td class="px-4 py-3.5">
                        {{ formatDuree(matiere.minutes) }}
                    </td>
                    <td class="px-4 py-3.5">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="
                                matiere.autoriseeMidi
                                    ? 'bg-succes/15 text-[#1F8A6B]'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ matiere.autoriseeMidi ? 'Oui' : 'Non' }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="typesSalle[matiere.typeSalle].classes"
                        >
                            {{ typesSalle[matiere.typeSalle].libelle }}
                        </span>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="border-t bg-fond">
                    <td class="px-6 py-3.5 font-semibold">Total</td>
                    <td class="px-4 py-3.5 font-bold" colspan="3">
                        {{ formatDuree(niveau.totalMinutes) }} par semaine
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <InfoBox class="mt-6">
        Le guide pédagogique est chargé une fois dans l'application à partir du
        décret n° 2019-1085 (modifié par le décret n° 2021-143). « Autorisée à
        midi » indique si la matière peut être placée de 12 h à 13 h.
    </InfoBox>
</template>
