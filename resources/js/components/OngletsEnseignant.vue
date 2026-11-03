<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, UserRound } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { index as monEmploiDuTemps } from '@/routes/mon-emploi';
import { edit as profil } from '@/routes/profile';

// Espace enseignant (téléphone) : barre d'onglets en bas de l'écran (maquettes 27 à 29)
type Onglet = {
    titre: string;
    href: ReturnType<typeof profil>;
    icone: LucideIcon;
};

const { isCurrentOrParentUrl } = useCurrentUrl();

const onglets: Onglet[] = [
    { titre: 'Emploi du temps', href: monEmploiDuTemps(), icone: CalendarDays },
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
            <component :is="onglet.icone" class="size-6" />
            {{ onglet.titre }}
        </Link>
    </nav>
</template>
