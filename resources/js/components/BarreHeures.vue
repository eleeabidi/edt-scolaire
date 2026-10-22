<script setup lang="ts">
import { computed } from 'vue';
import { formatDuree } from '@/lib/format';

// Heures affectées / maximum, avec une jauge (maquettes 14 et 15)
const props = withDefaults(
    defineProps<{
        minutes: number;
        maximumHeures: number;
        grande?: boolean;
    }>(),
    { grande: false },
);

const pourcentage = computed(() =>
    Math.min(100, (props.minutes / (props.maximumHeures * 60)) * 100),
);
</script>

<template>
    <div :class="grande ? 'w-full max-w-56' : 'w-20'">
        <p :class="grande ? 'text-xl font-bold' : 'text-[13px] font-semibold'">
            <template v-if="grande">
                {{ formatDuree(minutes) }} sur {{ maximumHeures }} h
            </template>
            <template v-else>
                {{ minutes % 60 === 0 ? minutes / 60 : formatDuree(minutes) }} /
                {{ maximumHeures }} h
            </template>
        </p>
        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-bordure">
            <div
                class="h-full rounded-full bg-primary"
                :style="{ width: `${pourcentage}%` }"
            />
        </div>
    </div>
</template>
