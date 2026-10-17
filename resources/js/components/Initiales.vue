<script setup lang="ts">
import { computed } from 'vue';

// Avatar rond avec les initiales (ex. : « SB » pour Sami Ben Ali)
const props = withDefaults(
    defineProps<{
        prenom: string;
        nom: string;
        taille?: 'petite' | 'grande';
    }>(),
    { taille: 'petite' },
);

const initiales = computed(() =>
    `${props.prenom.charAt(0)}${props.nom.charAt(0)}`.toUpperCase(),
);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-violet-clair font-semibold text-primary"
        :class="taille === 'grande' ? 'size-18 text-xl' : 'size-9 text-xs'"
        aria-hidden="true"
    >
        {{ initiales }}
    </span>
</template>
