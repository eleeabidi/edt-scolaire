<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { index } from '@/routes/enseignants';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Enseignants',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Enseignants" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <!-- Page provisoire : la liste et la gestion des enseignants arrivent à l'étape 5 -->
        <Heading
            title="Enseignants"
            description="La liste des enseignants sera disponible ici prochainement."
        />
    </div>
</template>
