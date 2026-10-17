<script setup lang="ts">
// En-tête des écrans connectés : titre, sous-titre et bouton d'action à droite
defineProps<{
    titre: string;
    sousTitre?: string;
}>();
</script>

<template>
    <div
        class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between lg:mb-8"
    >
        <div>
            <h1 class="text-2xl font-bold lg:text-[28px]">{{ titre }}</h1>
            <p v-if="sousTitre" class="mt-1 text-muted-foreground">
                {{ sousTitre }}
            </p>
        </div>
        <slot />
    </div>
</template>
