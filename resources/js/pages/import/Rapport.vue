<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import InfoBox from '@/components/InfoBox.vue';
import { Button } from '@/components/ui/button';
import { index as classes } from '@/routes/classes';
import { index as enseignants } from '@/routes/enseignants';
import { index as nouvelImport } from '@/routes/import';
import { index as salles } from '@/routes/salles';

// Écran 22 — Rapport d'import (T12.5)
const props = defineProps<{
    rapport: {
        type: 'enseignants' | 'classes' | 'salles';
        fichier: string;
        lignesLues: number;
        importees: number;
        emailsNonEnvoyes: number;
        erreurs: { ligne: number; donnee: string; erreur: string }[];
    };
}>();

defineOptions({
    layout: { titre: 'Importer' },
});

const liste = computed(
    () =>
        ({
            enseignants: {
                libelle: 'Voir les enseignants',
                href: enseignants(),
            },
            classes: { libelle: 'Voir les classes', href: classes() },
            salles: { libelle: 'Voir les salles', href: salles() },
        })[props.rapport.type],
);

const pluriel = (n: number, mot: string) => `${n} ${mot}${n > 1 ? 's' : ''}`;

// [singulier, pluriel]
const libelles = {
    enseignants: ['enseignant importé', 'enseignants importés'],
    classes: ['classe importée', 'classes importées'],
    salles: ['salle importée', 'salles importées'],
};

const conclusion = computed(() => {
    const { type, importees, erreurs, emailsNonEnvoyes } = props.rapport;
    const [singulier, plurielLibelle] = libelles[type];
    const morceaux: string[] = [];

    if (type === 'enseignants' && importees > 0) {
        const recus = importees - emailsNonEnvoyes;

        morceaux.push(
            recus < importees
                ? `${recus} enseignant(s) sur ${importees} ont reçu leurs identifiants par email.`
                : importees === 1
                  ? "L'enseignant importé a reçu ses identifiants par email."
                  : `Les ${importees} enseignants importés ont reçu leurs identifiants par email.`,
        );
    } else {
        morceaux.push(
            `${importees} ${importees > 1 ? plurielLibelle : singulier}.`,
        );
    }

    if (erreurs.length > 0) {
        morceaux.push(
            `Corrigez ${erreurs.length === 1 ? 'la ligne en erreur' : `les ${erreurs.length} lignes en erreur`} dans votre fichier, puis relancez l'import.`,
        );
    }

    return morceaux.join(' ');
});
</script>

<template>
    <Head title="Rapport d'import" />

    <Link
        :href="nouvelImport()"
        class="flex w-fit items-center gap-2 text-sm font-semibold text-primary"
    >
        <ArrowLeft class="size-5" />
        Nouvel import
    </Link>

    <h1 class="mt-6 text-2xl font-bold lg:text-[28px]">Rapport d'import</h1>
    <p class="mt-1 text-muted-foreground">
        Fichier {{ rapport.fichier }} ·
        {{ pluriel(rapport.lignesLues, 'ligne') }}
        {{ rapport.lignesLues > 1 ? 'lues' : 'lue' }}
    </p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="flex items-center gap-4 rounded-xl border bg-white p-6">
            <div
                class="flex size-14 items-center justify-center rounded-xl bg-succes/15 text-[#1F8A6B]"
            >
                <Check class="size-5" />
            </div>
            <div>
                <p class="text-[32px] leading-none font-extrabold">
                    {{ rapport.importees }}
                </p>
                <p class="mt-1.5 text-sm text-muted-foreground">
                    Lignes importées
                </p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border bg-white p-6">
            <div
                class="flex size-14 items-center justify-center rounded-xl bg-erreur/15 text-erreur"
            >
                <TriangleAlert class="size-5" />
            </div>
            <div>
                <p class="text-[32px] leading-none font-extrabold">
                    {{ rapport.erreurs.length }}
                </p>
                <p class="mt-1.5 text-sm text-muted-foreground">
                    Lignes en erreur
                </p>
            </div>
        </div>
    </div>

    <section
        v-if="rapport.erreurs.length > 0"
        class="mt-6 rounded-xl border bg-white p-6 lg:p-7"
    >
        <h2 class="text-xl font-bold">Lignes en erreur</h2>
        <div class="mt-4 overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-fond text-left text-muted-foreground">
                    <tr>
                        <th class="px-5 py-3.5 font-medium">Ligne</th>
                        <th class="px-4 py-3.5 font-medium">Donnée</th>
                        <th class="px-4 py-3.5 font-medium">Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="erreur in rapport.erreurs"
                        :key="erreur.ligne"
                        class="border-t"
                    >
                        <td class="px-5 py-3">{{ erreur.ligne }}</td>
                        <td class="px-4 py-3">{{ erreur.donnee }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="flex items-center gap-2 text-[#C93D3D]"
                            >
                                <TriangleAlert class="size-4 shrink-0" />
                                {{ erreur.erreur }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <InfoBox class="mt-6">{{ conclusion }}</InfoBox>

    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <Button variant="outline" as-child>
            <Link :href="nouvelImport()">Nouvel import</Link>
        </Button>
        <Button as-child>
            <Link :href="liste.href">{{ liste.libelle }}</Link>
        </Button>
    </div>
</template>
