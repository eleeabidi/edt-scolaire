<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Menu } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppNav from '@/components/AppNav.vue';
import OngletsEnseignant from '@/components/OngletsEnseignant.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';
import { logout } from '@/routes';

// Mise en page des écrans connectés (06 à 13) :
// menu blanc à gauche sur ordinateur, barre en haut + menu coulissant sur téléphone.
const { titre = '' } = defineProps<{
    titre?: string;
}>();

const menuOuvert = ref(false);

// Sprint 4 : sur téléphone, l'enseignant navigue avec la barre d'onglets du bas (pas de menu coulissant)
const page = usePage();
const enseignant = computed(() => page.props.auth.user.role === 'enseignant');
</script>

<template>
    <div class="min-h-svh bg-fond">
        <!-- Ordinateur : menu fixe à gauche -->
        <aside
            class="fixed inset-y-0 left-0 z-20 hidden w-66 border-r border-bordure bg-white lg:block"
        >
            <AppNav />
        </aside>

        <!-- Téléphone : barre du haut -->
        <header
            class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-bordure bg-white px-4 lg:hidden"
        >
            <span v-if="enseignant" class="size-10" />
            <button
                v-else
                type="button"
                class="flex size-10 items-center justify-center rounded-xl bg-fond"
                aria-label="Ouvrir le menu"
                @click="menuOuvert = true"
            >
                <Menu class="size-5" />
            </button>
            <span class="font-semibold">{{ titre }}</span>
            <Link
                :href="logout()"
                as="button"
                class="flex size-10 items-center justify-center rounded-xl"
                aria-label="Se déconnecter"
                @click="router.flushAll()"
            >
                <LogOut class="size-5" />
            </Link>
        </header>

        <Sheet v-model:open="menuOuvert">
            <SheetContent side="left" class="w-72 p-0">
                <SheetTitle class="sr-only">Menu</SheetTitle>
                <SheetDescription class="sr-only">
                    Navigation principale
                </SheetDescription>
                <AppNav @navigation="menuOuvert = false" />
            </SheetContent>
        </Sheet>

        <!-- Contenu de la page -->
        <main class="lg:pl-66" :class="enseignant ? 'pb-20 lg:pb-0' : ''">
            <div
                class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-12 lg:py-10"
            >
                <slot />
            </div>
        </main>

        <OngletsEnseignant v-if="enseignant" />

        <Toaster position="top-right" rich-colors />
    </div>
</template>
