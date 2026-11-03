<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Bell, CalendarDays, Pencil } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { useNotificationsEnDirect } from '@/composables/useNotificationsEnDirect';
import type { NotificationEnseignant } from '@/types';

// US20 (T20.4) — « Mes notifications » (maquette 28) : « Nouvelles » (non lues à l'ouverture de la page)
// puis « Plus anciennes ». Une notification qui arrive pendant la consultation s'affiche en haut.
const props = defineProps<{
    notifications: NotificationEnseignant[];
}>();

defineOptions({
    layout: { titre: 'Mes notifications' },
});

const liste = ref<NotificationEnseignant[]>([...props.notifications]);
const { derniere } = useNotificationsEnDirect();

watch(derniere, (notification) => {
    if (notification && !liste.value.some((n) => n.id === notification.id)) {
        liste.value.unshift(notification);
    }
});

const groupes = computed(() =>
    [
        { titre: 'Nouvelles', elements: liste.value.filter((n) => !n.lue) },
        {
            titre: 'Plus anciennes',
            elements: liste.value.filter((n) => n.lue),
        },
    ].filter((g) => g.elements.length > 0),
);
</script>

<template>
    <Head title="Mes notifications" />

    <PageHeader
        titre="Mes notifications"
        sous-titre="De la plus récente à la plus ancienne."
    />

    <!-- 3a : aucune notification -->
    <section
        v-if="liste.length === 0"
        class="flex flex-col items-center gap-3 rounded-2xl border bg-white p-10 text-center sm:max-w-xl"
    >
        <div
            class="flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
        >
            <Bell class="size-6" />
        </div>
        <p class="font-semibold">Aucune notification</p>
    </section>

    <div v-else class="grid gap-6 sm:max-w-xl">
        <section v-for="groupe in groupes" :key="groupe.titre">
            <h2 class="mb-3 font-bold">{{ groupe.titre }}</h2>
            <ul class="grid gap-4">
                <li
                    v-for="n in groupe.elements"
                    :key="n.id"
                    class="flex gap-4 rounded-2xl border bg-white p-5"
                >
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-violet-clair text-primary"
                    >
                        <Pencil
                            v-if="n.type === 'modification'"
                            class="size-5"
                        />
                        <CalendarDays v-else class="size-5" />
                    </div>
                    <div class="flex-1">
                        <p class="text-lg font-semibold">{{ n.titre }}</p>
                        <p class="text-muted-foreground">{{ n.message }}</p>
                        <p class="mt-1 text-sm text-muted-foreground/80">
                            {{ n.date }}
                        </p>
                    </div>
                    <span
                        v-if="!n.lue"
                        class="mt-2 size-2.5 shrink-0 rounded-full bg-primary"
                        aria-label="Nouvelle"
                    />
                </li>
            </ul>
        </section>
    </div>
</template>
