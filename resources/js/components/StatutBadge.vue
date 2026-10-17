<script setup lang="ts">
import { computed } from 'vue';
import type { User } from '@/types';

const props = defineProps<{
    statut: User['statut'];
}>();

// Couleurs de la charte : Actif (vert), En attente (jaune), Refusé (rouge), Désactivé (gris)
const styles: Record<User['statut'], { libelle: string; classes: string }> = {
    ACTIF: { libelle: 'Actif', classes: 'bg-[#3CC9A0]/15 text-[#1F8A6B]' },
    EN_ATTENTE: {
        libelle: 'En attente',
        classes: 'bg-[#FFC94D]/25 text-[#8A6100]',
    },
    REFUSE: { libelle: 'Refusé', classes: 'bg-[#FF6B6B]/15 text-[#C93D3D]' },
    DESACTIVE: {
        libelle: 'Désactivé',
        classes: 'bg-muted text-muted-foreground',
    },
};

const style = computed(() => styles[props.statut]);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
        :class="style.classes"
    >
        {{ style.libelle }}
    </span>
</template>
