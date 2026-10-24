<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Download,
    DoorOpen,
    FileSpreadsheet,
    Presentation,
    Upload,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InfoBox from '@/components/InfoBox.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { modele, store } from '@/routes/import';

// Écran 21 — Importer des données (enseignants, classes, salles)
type TypeImport = 'enseignants' | 'classes' | 'salles';

defineOptions({
    layout: { titre: 'Importer' },
});

const types = [
    {
        valeur: 'enseignants' as TypeImport,
        titre: 'Enseignants',
        colonnes: 'Nom, prénom, email, téléphone',
        icone: Users,
    },
    {
        valeur: 'classes' as TypeImport,
        titre: 'Classes',
        colonnes: 'Nom, niveau, effectif, cours le samedi',
        icone: Presentation,
    },
    {
        valeur: 'salles' as TypeImport,
        titre: 'Salles',
        colonnes: 'Nom, type, capacité',
        icone: DoorOpen,
    },
];

const form = useForm({
    type: 'enseignants' as TypeImport,
    fichier: null as File | null,
});

// --- Zone de dépôt du fichier ---
const champFichier = ref<HTMLInputElement | null>(null);
const survol = ref(false);

function choisirFichier(fichiers: FileList | null | undefined) {
    form.fichier = fichiers?.[0] ?? null;
    form.clearErrors('fichier');
}

function deposer(evenement: DragEvent) {
    survol.value = false;
    choisirFichier(evenement.dataTransfer?.files);
}

const tailleFichier = computed(() =>
    form.fichier
        ? `${Math.max(1, Math.round(form.fichier.size / 1024))} Ko`
        : '',
);

function importer() {
    form.post(store.url(), { forceFormData: true });
}
</script>

<template>
    <Head title="Importer des données" />

    <PageHeader
        titre="Importer des données"
        sous-titre="Ajoutez rapidement vos enseignants, classes et salles à partir d'un fichier Excel ou CSV."
    />

    <h2 class="mb-4 text-xl font-bold">1. Choisissez ce que vous importez</h2>

    <div class="grid gap-4 md:grid-cols-3">
        <button
            v-for="t in types"
            :key="t.valeur"
            type="button"
            class="rounded-xl border-2 bg-white p-5 text-left transition-colors"
            :class="
                form.type === t.valeur
                    ? 'border-primary bg-violet-clair'
                    : 'border-bordure hover:border-primary/40'
            "
            :aria-pressed="form.type === t.valeur"
            @click="form.type = t.valeur"
        >
            <div
                class="flex size-11 items-center justify-center rounded-xl"
                :class="
                    form.type === t.valeur
                        ? 'bg-primary text-white'
                        : 'bg-violet-clair text-primary'
                "
            >
                <component :is="t.icone" class="size-5" />
            </div>
            <p class="mt-4 text-lg font-bold">{{ t.titre }}</p>
            <p class="mt-1 text-sm text-muted-foreground">{{ t.colonnes }}</p>
            <a
                :href="modele.url(t.valeur)"
                class="mt-3 flex w-fit items-center gap-2 text-sm font-semibold text-primary hover:underline"
                @click.stop
            >
                <Download class="size-4" />
                Télécharger le modèle
            </a>
        </button>
    </div>
    <InputError class="mt-2" :message="form.errors.type" />

    <h2 class="mt-8 mb-4 text-xl font-bold">2. Envoyez votre fichier</h2>

    <label
        for="fichier"
        class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed bg-white p-10 text-center transition-colors"
        :class="survol ? 'border-primary bg-violet-clair' : 'border-primary/50'"
        @dragover.prevent="survol = true"
        @dragleave.prevent="survol = false"
        @drop.prevent="deposer"
    >
        <span
            class="flex size-12 items-center justify-center rounded-full bg-violet-clair text-primary"
        >
            <Upload class="size-5" />
        </span>
        <span class="font-semibold">
            Glissez votre fichier ici ou cliquez pour le choisir
        </span>
        <span class="text-sm text-muted-foreground">
            Formats acceptés : .xlsx et .csv
        </span>
        <input
            id="fichier"
            ref="champFichier"
            type="file"
            accept=".xlsx,.csv"
            class="sr-only"
            @change="choisirFichier(($event.target as HTMLInputElement).files)"
        />
    </label>
    <InputError class="mt-2" :message="form.errors.fichier" />

    <div
        v-if="form.fichier"
        class="mt-4 flex flex-col gap-3 rounded-xl bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <span class="flex items-center gap-3 text-sm font-semibold">
            <FileSpreadsheet class="size-5 text-succes" />
            {{ form.fichier.name }} · {{ tailleFichier }}
        </span>
        <Button :disabled="form.processing" @click="importer">
            <Spinner v-if="form.processing" />
            Importer
        </Button>
    </div>

    <InfoBox class="mt-6">
        <template v-if="form.type === 'enseignants'">
            Les enseignants importés sont créés avec un compte actif et
            reçoivent un mot de passe temporaire par email.
        </template>
        <template v-else-if="form.type === 'classes'">
            La colonne « cours le samedi » (oui / non) n'est lue que pour la 3e
            année : la 1re et la 2e année n'ont jamais cours le samedi, de la 4e
            à la 6e il y a toujours cours le samedi matin.
        </template>
        <template v-else>
            Le type d'une salle est « cours », « sport » ou « technologie ». Les
            salles importées sont en service.
        </template>
        La première ligne du fichier contient les titres des colonnes, comme
        dans le modèle : un fichier dont les colonnes sont différentes est
        refusé.
    </InfoBox>
</template>
