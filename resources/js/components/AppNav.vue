<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    DoorOpen,
    House,
    LibraryBig,
    LogOut,
    Presentation,
    SlidersHorizontal,
    UserRound,
    Users,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard, home, logout } from '@/routes';
import { index as classes } from '@/routes/classes';
import { index as directeurs } from '@/routes/directeurs';
import { index as enseignants } from '@/routes/enseignants';
import { index as programme } from '@/routes/programme';
import { edit as profil } from '@/routes/profile';
import { index as regles } from '@/routes/regles';
import { index as salles } from '@/routes/salles';

type Lien = {
    titre: string;
    href: ReturnType<typeof dashboard>;
    icone: LucideIcon;
    badge?: number;
};

const emit = defineEmits<{ navigation: [] }>();

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

// Le menu dépend du rôle de l'utilisateur connecté
const liens = computed<Lien[]>(() => {
    const role = page.props.auth.user.role;
    const items: Lien[] = [];

    if (role === 'admin') {
        items.push(
            { titre: 'Tableau de bord', href: dashboard(), icone: House },
            {
                titre: 'Directeurs',
                href: directeurs(),
                icone: BriefcaseBusiness,
                badge: page.props.inscriptionsEnAttente,
            },
        );
    }

    if (role === 'admin' || role === 'directeur') {
        items.push({ titre: 'Enseignants', href: enseignants(), icone: Users });
    }

    // Sprint 2 : données de l'école et consultation du programme et des règles (directeur)
    if (role === 'directeur') {
        items.push(
            { titre: 'Classes', href: classes(), icone: Presentation },
            { titre: 'Salles', href: salles(), icone: DoorOpen },
            { titre: 'Programme', href: programme(), icone: LibraryBig },
            { titre: 'Règles', href: regles(), icone: SlidersHorizontal },
        );
    }

    items.push({ titre: 'Mon profil', href: profil(), icone: UserRound });

    return items;
});

function seDeconnecter() {
    router.flushAll();
}
</script>

<template>
    <div class="flex h-full flex-col px-4 py-7">
        <Link :href="home()" class="px-3.5" @click="emit('navigation')">
            <AppLogo />
        </Link>

        <nav class="mt-8 grid gap-2" aria-label="Menu principal">
            <Link
                v-for="lien in liens"
                :key="lien.titre"
                :href="lien.href"
                class="flex h-11 items-center gap-3 rounded-xl px-3.5 text-[15px] font-semibold transition-colors"
                :class="
                    isCurrentOrParentUrl(lien.href)
                        ? 'bg-violet-clair text-primary'
                        : 'text-muted-foreground hover:bg-fond hover:text-foreground'
                "
                @click="emit('navigation')"
            >
                <component :is="lien.icone" class="size-5" />
                <span class="flex-1">{{ lien.titre }}</span>
                <span
                    v-if="lien.badge"
                    class="rounded-full bg-crayon px-2 text-xs font-bold text-texte"
                >
                    {{ lien.badge }}
                </span>
            </Link>
        </nav>

        <Link
            :href="logout()"
            as="button"
            class="mt-auto flex h-11 items-center gap-3 rounded-xl px-3.5 text-[15px] font-semibold text-muted-foreground transition-colors hover:bg-fond hover:text-foreground"
            data-test="logout-button"
            @click="seDeconnecter"
        >
            <LogOut class="size-5" />
            Se déconnecter
        </Link>
    </div>
</template>
