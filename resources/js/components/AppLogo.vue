<script setup lang="ts">
import { CalendarDays } from '@lucide/vue';
</script>

<template>
    <!-- Logo de la charte : tuile violette avec un calendrier + « EDT Scolaire » -->
    <div class="flex items-center gap-2.5">
        <div
            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white"
        >
            <CalendarDays class="size-5" />
        </div>
        <span class="truncate text-xl font-bold text-foreground">
            EDT Scolaire
        </span>
    </div>
</template>
