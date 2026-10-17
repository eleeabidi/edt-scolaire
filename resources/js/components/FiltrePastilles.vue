<script setup lang="ts" generic="T extends string | null">
// Boutons de filtre en forme de pastilles (ex. : Tous / Actifs / Désactivés)
defineProps<{
    options: { valeur: T; libelle: string }[];
}>();

const selection = defineModel<T>({ required: true });
</script>

<template>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <button
            v-for="option in options"
            :key="option.libelle"
            type="button"
            class="h-9 shrink-0 rounded-full border px-4 text-sm font-semibold transition-colors"
            :class="
                selection === option.valeur
                    ? 'border-primary bg-primary text-white'
                    : 'border-bordure bg-white text-muted-foreground hover:text-foreground'
            "
            @click="selection = option.valeur"
        >
            {{ option.libelle }}
        </button>
    </div>
</template>
