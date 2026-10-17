<script setup lang="ts">
import { CalendarDays, GraduationCap, LibraryBig, Pencil } from '@lucide/vue';

// Illustration des pages d'accueil : 4 tuiles colorées
withDefaults(defineProps<{ petite?: boolean }>(), { petite: false });
</script>

<template>
    <div
        class="grid grid-cols-2"
        :class="petite ? 'gap-3.5' : 'gap-5'"
        aria-hidden="true"
    >
        <div
            class="flex items-center justify-center bg-primary text-white"
            :class="petite ? 'size-20 rounded-2xl' : 'size-30 rounded-[28px]'"
        >
            <CalendarDays :class="petite ? 'size-8' : 'size-11'" />
        </div>
        <div
            class="flex items-center justify-center rounded-full bg-crayon text-texte"
            :class="petite ? 'size-20' : 'size-30'"
        >
            <Pencil :class="petite ? 'size-8' : 'size-11'" />
        </div>
        <div
            class="flex items-center justify-center rounded-full bg-succes text-texte"
            :class="petite ? 'size-20' : 'size-30'"
        >
            <LibraryBig :class="petite ? 'size-8' : 'size-11'" />
        </div>
        <div
            class="flex items-center justify-center bg-white text-primary"
            :class="petite ? 'size-20 rounded-2xl' : 'size-30 rounded-[28px]'"
        >
            <GraduationCap :class="petite ? 'size-8' : 'size-11'" />
        </div>
    </div>
</template>
