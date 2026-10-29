<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
import GrilleSemaine from '@/components/emploi/GrilleSemaine.vue';
import PageHeader from '@/components/PageHeader.vue';
import { formatDuree } from '@/lib/format';
import type { LigneGrille, SeanceGrille } from '@/types';

// « Mon emploi du temps » (enseignant) : ses séances de la semaine, en lecture seule (US17, T17.3)
defineProps<{
    emploi: { annee: string; ecole: string } | null;
    lignes?: LigneGrille[];
    cases?: string[];
    seances?: SeanceGrille[];
    totalMinutes?: number;
}>();

defineOptions({
    layout: { titre: 'Mon emploi du temps' },
});
</script>

<template>
    <Head title="Mon emploi du temps" />

    <PageHeader
        titre="Mon emploi du temps"
        :sous-titre="
            emploi
                ? `${emploi.ecole} · année scolaire ${emploi.annee}.`
                : 'Vos séances de la semaine.'
        "
    />

    <!-- Aucun emploi du temps publié : rien à montrer (les brouillons restent cachés) -->
    <section
        v-if="!emploi"
        class="flex flex-col items-center gap-3 rounded-2xl border bg-white p-10 text-center"
    >
        <div
            class="flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
        >
            <CalendarDays class="size-6" />
        </div>
        <p class="font-semibold">Aucun emploi du temps publié pour le moment</p>
        <p class="max-w-md text-sm text-muted-foreground">
            Vous recevrez un email dès que le directeur aura publié l'emploi du
            temps de votre école.
        </p>
    </section>

    <template v-else>
        <div class="mb-4 flex justify-end">
            <span
                class="rounded-full bg-violet-clair px-3 py-1 text-xs font-semibold text-primary"
            >
                {{ formatDuree(totalMinutes ?? 0) }} par semaine
            </span>
        </div>

        <p
            v-if="(seances ?? []).length === 0"
            class="mb-4 rounded-xl bg-violet-clair px-4 py-3 text-sm"
        >
            Vous n'avez aucune séance dans cet emploi du temps.
        </p>

        <GrilleSemaine
            :lignes="lignes ?? []"
            :cases="cases ?? []"
            :seances="seances ?? []"
            vue="enseignant"
        />
    </template>
</template>
