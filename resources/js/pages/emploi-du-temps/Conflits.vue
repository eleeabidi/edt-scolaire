<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck, Info, TriangleAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/emploi';

// Conflits de l'emploi du temps (US14) : cause, solution proposée, « Voir dans la grille »
type Conflit = {
    cle: string;
    type: string;
    libelle: string;
    seance: {
        id: number;
        classeId: number;
        classe: string;
        matieres: string;
        enseignant: string;
        salle: string;
        quand: string;
    };
    explication: string;
    solution: string | null;
};

const props = defineProps<{
    annee: string;
    conflits: Conflit[];
}>();

defineOptions({
    layout: { titre: 'Emploi du temps' },
});

const nombre = `${props.conflits.length} conflit${props.conflits.length > 1 ? 's' : ''}`;

const voir = (c: Conflit) =>
    index({
        query: {
            annee: props.annee,
            par: 'classe',
            id: c.seance.classeId,
            seance: c.seance.id,
        },
    });
</script>

<template>
    <Head title="Conflits de l'emploi du temps" />

    <Link
        :href="index({ query: { annee } })"
        class="flex w-fit items-center gap-2 text-sm font-semibold text-primary"
    >
        <ArrowLeft class="size-5" />
        Retour à l'emploi du temps
    </Link>

    <h1 class="mt-4 text-2xl font-bold lg:text-[28px]">
        Conflits de l'emploi du temps
    </h1>
    <p class="mt-1 text-muted-foreground">
        Année scolaire {{ annee }} · {{ nombre }}.
        <template v-if="conflits.length > 0">
            Corrigez-les avant de publier l'emploi du temps.
        </template>
    </p>

    <!-- 2a : aucun conflit -->
    <div
        v-if="conflits.length === 0"
        class="mt-6 flex flex-col items-center gap-3 rounded-xl border bg-white p-10 text-center"
    >
        <CircleCheck class="size-8 text-succes" />
        <p class="font-semibold">Aucun conflit</p>
        <p class="text-sm text-muted-foreground">
            L'emploi du temps peut être publié.
        </p>
    </div>

    <div v-else class="mt-6 grid gap-4">
        <article
            v-for="conflit in conflits"
            :key="conflit.cle"
            class="flex flex-col gap-4 rounded-xl border bg-white p-5 sm:flex-row sm:items-center"
        >
            <div class="flex-1">
                <span
                    class="rounded-full bg-erreur/15 px-2.5 py-0.5 text-xs font-semibold text-[#C93D3D]"
                >
                    {{ conflit.libelle }}
                </span>
                <p class="mt-2 font-semibold">
                    {{ conflit.seance.classe }} ·
                    {{ conflit.seance.matieres }} ·
                    {{ conflit.seance.enseignant }} ·
                    {{ conflit.seance.salle }} · {{ conflit.seance.quand }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ conflit.explication }}
                </p>
                <p
                    v-if="conflit.solution"
                    class="mt-2 flex items-start gap-2 text-sm text-primary"
                >
                    <Info class="mt-0.5 size-4 shrink-0" />
                    Solution proposée : {{ conflit.solution }}
                </p>
                <!-- 3a : aucune solution trouvée -->
                <p
                    v-else
                    class="mt-2 flex items-start gap-2 text-sm text-[#C93D3D]"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    Aucune solution trouvée : modifiez d'abord les affectations,
                    les disponibilités ou les salles de votre école.
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link :href="voir(conflit)">Voir dans la grille</Link>
            </Button>
        </article>
    </div>
</template>
