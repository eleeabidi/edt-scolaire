<script setup lang="ts">
import { computed } from 'vue';
import IllustrationTuiles from '@/components/IllustrationTuiles.vue';
import { useIllustration } from '@/composables/useIllustration';

// Mise en page des écrans 01 à 05 : illustration à gauche, formulaire à droite.
// Sur téléphone : illustration réduite en haut, formulaire en dessous.
const props = defineProps<{
    titreIllustration?: string;
    texteIllustration?: string;
}>();

const surcharge = useIllustration();
const titre = computed(
    () => surcharge.value?.titre ?? props.titreIllustration ?? '',
);
const texte = computed(
    () => surcharge.value?.texte ?? props.texteIllustration ?? '',
);
</script>

<template>
    <div class="flex min-h-svh flex-col bg-white lg:flex-row">
        <!-- Illustration (ordinateur) -->
        <aside
            class="hidden flex-1 flex-col items-center justify-center bg-violet-clair p-10 text-center lg:flex"
        >
            <IllustrationTuiles />
            <h2 class="mt-12 text-[28px] font-bold">{{ titre }}</h2>
            <p class="mt-3 max-w-md leading-relaxed text-muted-foreground">
                {{ texte }}
            </p>
        </aside>

        <!-- Illustration (téléphone) -->
        <div
            class="flex justify-center rounded-b-[32px] bg-violet-clair pt-14 pb-10 lg:hidden"
        >
            <IllustrationTuiles petite />
        </div>

        <!-- Formulaire -->
        <main
            class="flex flex-1 items-start justify-center px-6 py-8 lg:items-center lg:px-10"
        >
            <div class="w-full max-w-[440px]">
                <slot />
            </div>
        </main>
    </div>
</template>
