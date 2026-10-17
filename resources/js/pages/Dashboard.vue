<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BriefcaseBusiness, Clock, GraduationCap } from '@lucide/vue';
import { computed } from 'vue';
import Initiales from '@/components/Initiales.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatutBadge from '@/components/StatutBadge.vue';
import { Button } from '@/components/ui/button';
import { index as directeurs, show } from '@/routes/directeurs';

// Écran 07 — Tableau de bord Admin
type Inscription = {
    id: number;
    nom: string;
    prenom: string;
    ecole: string | null;
    ville: string | null;
    recue: string | null;
};

const props = defineProps<{
    statistiques: {
        ecoles: number;
        directeursActifs: number;
        inscriptionsEnAttente: number;
    };
    inscriptions: Inscription[];
}>();

defineOptions({
    layout: { titre: 'Tableau de bord' },
});

const page = usePage();

const cartes = computed(() => [
    {
        valeur: props.statistiques.ecoles,
        libelle: 'Écoles inscrites',
        icone: GraduationCap,
        couleurs: 'bg-violet-clair text-primary',
    },
    {
        valeur: props.statistiques.directeursActifs,
        libelle: 'Directeurs actifs',
        icone: BriefcaseBusiness,
        couleurs: 'bg-succes/15 text-[#1F8A6B]',
    },
    {
        valeur: props.statistiques.inscriptionsEnAttente,
        libelle: 'Inscriptions en attente',
        icone: Clock,
        couleurs: 'bg-crayon/25 text-[#8A6100]',
    },
]);
</script>

<template>
    <Head title="Tableau de bord" />

    <PageHeader
        :titre="`Bonjour, ${page.props.auth.user.prenom}`"
        sous-titre="Voici l'état de la plateforme aujourd'hui."
    />

    <!-- Chiffres clés -->
    <div class="grid gap-4 sm:grid-cols-3 lg:gap-6">
        <div
            v-for="carte in cartes"
            :key="carte.libelle"
            class="flex items-center gap-4 rounded-xl border bg-white p-6"
        >
            <div
                class="flex size-14 shrink-0 items-center justify-center rounded-xl"
                :class="carte.couleurs"
            >
                <component :is="carte.icone" class="size-5" />
            </div>
            <div>
                <p class="text-[32px] leading-none font-extrabold">
                    {{ carte.valeur }}
                </p>
                <p class="mt-1.5 text-sm text-muted-foreground">
                    {{ carte.libelle }}
                </p>
            </div>
        </div>
    </div>

    <!-- Inscriptions en attente -->
    <section class="mt-6 rounded-xl border bg-white p-6 lg:p-7">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold">Inscriptions en attente</h2>
            <Link
                :href="directeurs({ query: { statut: 'EN_ATTENTE' } })"
                class="text-sm font-semibold text-primary hover:underline"
            >
                Tout voir
            </Link>
        </div>

        <p
            v-if="inscriptions.length === 0"
            class="mt-6 rounded-xl bg-fond p-6 text-center text-muted-foreground"
        >
            Aucune inscription à traiter. 🎉
        </p>

        <ul v-else class="mt-4 divide-y divide-bordure">
            <li
                v-for="inscription in inscriptions"
                :key="inscription.id"
                class="flex flex-wrap items-center gap-x-4 gap-y-2 py-4"
            >
                <Initiales
                    :prenom="inscription.prenom"
                    :nom="inscription.nom"
                />
                <div class="min-w-0 flex-1 sm:flex sm:items-center sm:gap-4">
                    <p class="font-semibold sm:w-48 sm:shrink-0">
                        {{ inscription.prenom }} {{ inscription.nom }}
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ inscription.ecole }} · {{ inscription.ville }}
                    </p>
                </div>
                <span class="hidden text-sm text-indice md:inline">
                    {{ inscription.recue }}
                </span>
                <StatutBadge
                    statut="EN_ATTENTE"
                    class="hidden sm:inline-flex"
                />
                <Button variant="outline" as-child>
                    <Link :href="show(inscription.id)">Voir</Link>
                </Button>
            </li>
        </ul>
    </section>
</template>
