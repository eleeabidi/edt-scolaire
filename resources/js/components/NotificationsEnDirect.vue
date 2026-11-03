<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { watch } from 'vue';
import { useNotificationsEnDirect } from '@/composables/useNotificationsEnDirect';
import type { NotificationEnseignant } from '@/types';

// US20 (T20.2, T20.3) — Écoute le canal privé de l'enseignant connecté (Reverb) : à chaque nouvelle
// notification, la pastille, « Mon emploi du temps » et « Mes notifications » se mettent à jour sans rechargement.
const page = usePage();
const { nonLues, derniere } = useNotificationsEnDirect();

// Le nombre envoyé par le serveur à chaque page (il revient à zéro sur « Mes notifications »)
watch(
    () => page.props.notificationsNonLues,
    (nombre) => (nonLues.value = nombre ?? 0),
    { immediate: true },
);

useEcho<{ notification: NotificationEnseignant; nonLues: number }>(
    `enseignant.${page.props.auth.user.id}`,
    '.notification.envoyee',
    (donnees) => {
        nonLues.value = donnees.nonLues;
        derniere.value = donnees.notification;

        // L'emploi du temps vient d'être publié ou une séance a changé : on recharge ses séances
        if (page.component === 'mon-emploi-du-temps/Index') {
            router.reload();
        }
    },
);
</script>

<template>
    <span class="hidden" />
</template>
