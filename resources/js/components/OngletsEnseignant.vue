<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bell, CalendarDays, CalendarX2, UserRound } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useNotificationsEnDirect } from '@/composables/useNotificationsEnDirect';
import { index as mesDemandes } from '@/routes/mes-demandes';
import { index as monEmploiDuTemps } from '@/routes/mon-emploi';
import { index as notifications } from '@/routes/notifications';
import { edit as profil } from '@/routes/profile';

// Espace enseignant (téléphone) : barre d'onglets en bas de l'écran (maquettes 27 à 29)
type Onglet = {
    titre: string;
    href: ReturnType<typeof profil>;
    icone: LucideIcon;
    pastille?: boolean; // nombre de notifications non lues
};

const { isCurrentOrParentUrl } = useCurrentUrl();
const { nonLues } = useNotificationsEnDirect();

const onglets: Onglet[] = [
    { titre: 'Emploi du temps', href: monEmploiDuTemps(), icone: CalendarDays },
    {
        titre: 'Notifications',
        href: notifications(),
        icone: Bell,
        pastille: true,
    },
    { titre: 'Demandes', href: mesDemandes(), icone: CalendarX2 },
    { titre: 'Profil', href: profil(), icone: UserRound },
];
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-20 flex border-t border-bordure bg-white pb-[env(safe-area-inset-bottom)] lg:hidden"
        aria-label="Espace enseignant"
    >
        <Link
            v-for="onglet in onglets"
            :key="onglet.titre"
            :href="onglet.href"
            class="flex flex-1 flex-col items-center gap-1 py-2.5 text-xs font-semibold transition-colors"
            :class="
                isCurrentOrParentUrl(onglet.href)
                    ? 'text-primary'
                    : 'text-muted-foreground hover:text-foreground'
            "
            :aria-current="
                isCurrentOrParentUrl(onglet.href) ? 'page' : undefined
            "
        >
            <span class="relative">
                <component :is="onglet.icone" class="size-6" />
                <span
                    v-if="onglet.pastille && nonLues > 0"
                    class="absolute -top-1.5 -right-2.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-erreur px-1 text-[11px] font-bold text-white"
                    :aria-label="`${nonLues} non lues`"
                >
                    {{ nonLues }}
                </span>
            </span>
            {{ onglet.titre }}
        </Link>
    </nav>
</template>
