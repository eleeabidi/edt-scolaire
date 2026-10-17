<script setup lang="ts">
import { Info } from '@lucide/vue';
</script>

<template>
    <!-- Encadré d'information violet clair (maquettes) -->
    <div
        class="flex items-start gap-3 rounded-xl bg-violet-clair px-4 py-3.5 text-sm text-foreground"
    >
        <Info class="mt-0.5 size-5 shrink-0 text-primary" />
        <div><slot /></div>
    </div>
</template>
