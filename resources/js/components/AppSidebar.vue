<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, School, UserRound, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { index as directeurs } from '@/routes/directeurs';
import { index as enseignants } from '@/routes/enseignants';
import { edit as profil } from '@/routes/profile';
import type { NavItem } from '@/types';

const page = usePage();
const role = computed(() => page.props.auth.user.role);

// Le menu dépend du rôle de l'utilisateur connecté
const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (role.value === 'admin') {
        items.push({
            title: 'Tableau de bord',
            href: dashboard(),
            icon: LayoutGrid,
        });
    }

    if (role.value === 'admin') {
        items.push({
            title: 'Directeurs',
            href: directeurs(),
            icon: School,
            badge: page.props.inscriptionsEnAttente,
        });
    }

    if (role.value === 'admin' || role.value === 'directeur') {
        items.push({ title: 'Enseignants', href: enseignants(), icon: Users });
    }

    items.push({ title: 'Mon profil', href: profil(), icon: UserRound });

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
