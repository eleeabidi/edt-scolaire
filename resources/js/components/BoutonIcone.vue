<script setup lang="ts">
// Petit bouton carré avec une icône (Modifier, Supprimer, Voir) — maquettes 08 et 09
withDefaults(
    defineProps<{
        libelle: string;
        danger?: boolean;
    }>(),
    { danger: false },
);
</script>

<template>
    <button
        type="button"
        class="flex size-9 items-center justify-center rounded-lg border border-bordure bg-white transition-colors [&_svg]:size-[18px]"
        :class="
            danger
                ? 'text-erreur hover:bg-erreur/10'
                : 'text-muted-foreground hover:bg-violet-clair hover:text-primary'
        "
        :aria-label="libelle"
        :title="libelle"
    >
        <slot />
    </button>
</template>
