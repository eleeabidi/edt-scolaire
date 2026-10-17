<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import IllustrationTuiles from '@/components/IllustrationTuiles.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

// Page d'erreur aux couleurs du site (403, 404, 409, 500…)
const props = defineProps<{
    statut: number;
    message?: string | null;
}>();

const textes: Record<number, { titre: string; texte: string }> = {
    403: {
        titre: 'Accès refusé',
        texte: "Vous n'avez pas le droit d'accéder à cette page.",
    },
    404: {
        titre: 'Page introuvable',
        texte: "Cette page n'existe pas ou a été déplacée.",
    },
    409: {
        titre: 'Action impossible',
        texte: 'Cette action a déjà été effectuée.',
    },
    500: {
        titre: 'Oups, une erreur est survenue',
        texte: 'Un problème est survenu de notre côté. Réessayez dans un instant.',
    },
    503: {
        titre: 'Maintenance en cours',
        texte: 'Le site revient très vite.',
    },
};

const contenu = computed(() => textes[props.statut] ?? textes[500]);
</script>

<template>
    <Head :title="contenu.titre" />

    <div
        class="flex min-h-svh flex-col items-center justify-center bg-fond px-6 py-10 text-center"
    >
        <AppLogo />
        <div class="mt-10">
            <IllustrationTuiles petite />
        </div>
        <p class="mt-10 text-sm font-bold text-primary">Erreur {{ statut }}</p>
        <h1 class="mt-2 text-[28px] font-bold">{{ contenu.titre }}</h1>
        <p class="mt-2 max-w-md text-muted-foreground">
            {{ message || contenu.texte }}
        </p>
        <Button as-child class="mt-8">
            <Link :href="home()">Retour à l'accueil</Link>
        </Button>
    </div>
</template>
