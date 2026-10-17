<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Pagination } from '@/types';

defineProps<{
    pagination: Pagination<unknown>;
}>();
</script>

<template>
    <div
        v-if="pagination.last_page > 1"
        class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
    >
        <span
            >Page {{ pagination.current_page }} sur
            {{ pagination.last_page }}</span
        >

        <div class="flex gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!pagination.prev_page_url"
                as-child
            >
                <Link
                    v-if="pagination.prev_page_url"
                    :href="pagination.prev_page_url"
                    preserve-scroll
                    >Précédent</Link
                >
                <span v-else>Précédent</span>
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!pagination.next_page_url"
                as-child
            >
                <Link
                    v-if="pagination.next_page_url"
                    :href="pagination.next_page_url"
                    preserve-scroll
                    >Suivant</Link
                >
                <span v-else>Suivant</span>
            </Button>
        </div>
    </div>
</template>
