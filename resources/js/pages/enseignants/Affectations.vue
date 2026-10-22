<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Trash2, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BarreHeures from '@/components/BarreHeures.vue';
import BoutonIcone from '@/components/BoutonIcone.vue';
import Initiales from '@/components/Initiales.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDuree } from '@/lib/format';
import { destroy, store, volume } from '@/routes/affectations';
import { index as enseignants } from '@/routes/enseignants';

// Écran 15 — Affectations d'un enseignant (directeur)
type MatiereDeClasse = {
    id: number;
    nom: string;
    minutes: number;
    enseignant: string | null; // qui l'enseigne déjà dans cette classe
};

const props = defineProps<{
    enseignant: {
        id: number;
        nom: string;
        prenom: string;
        volume_horaire_max: number;
    };
    ecoleNom: string | null;
    totalMinutes: number;
    affectations: {
        id: number;
        classe: string;
        matiere: string;
        minutes: number;
    }[];
    classes: { id: number; nom: string; matieres: MatiereDeClasse[] }[];
}>();

defineOptions({
    layout: { titre: 'Affectations' },
});

const nomComplet = computed(
    () => `${props.enseignant.prenom} ${props.enseignant.nom}`,
);

// --- Volume horaire maximum ---
const formVolume = useForm({
    volume_horaire_max: props.enseignant.volume_horaire_max,
});

watch(
    () => props.enseignant.volume_horaire_max,
    (valeur) => formVolume.defaults({ volume_horaire_max: valeur }),
);

const resteMinutes = computed(() =>
    Math.max(0, props.enseignant.volume_horaire_max * 60 - props.totalMinutes),
);

function enregistrerVolume() {
    formVolume.patch(volume.url(props.enseignant.id), { preserveScroll: true });
}

// --- Nouvelle affectation ---
const formAffectation = useForm({
    classe_id: '' as number | '',
    matiere_id: '' as number | '',
});

const classeChoisie = computed(() =>
    props.classes.find((c) => c.id === formAffectation.classe_id),
);
const matiereChoisie = computed(() =>
    classeChoisie.value?.matieres.find(
        (m) => m.id === formAffectation.matiere_id,
    ),
);

// Changer de classe vide le choix de la matière
watch(
    () => formAffectation.classe_id,
    () => (formAffectation.matiere_id = ''),
);

// Aperçu avant l'envoi : le serveur vérifie aussi la règle
const depassement = computed(() => {
    if (!matiereChoisie.value || !classeChoisie.value) {
        return null;
    }

    const total = props.totalMinutes + matiereChoisie.value.minutes;

    return total > props.enseignant.volume_horaire_max * 60
        ? `Impossible : ${matiereChoisie.value.nom.toLowerCase()} en ${classeChoisie.value.nom} compte ${formatDuree(matiereChoisie.value.minutes)}. Le total passerait à ${formatDuree(total)} et dépasserait le maximum de ${props.enseignant.volume_horaire_max} h.`
        : null;
});

function ajouterAffectation() {
    formAffectation.post(store.url(props.enseignant.id), {
        preserveScroll: true,
        onSuccess: () => formAffectation.reset(),
    });
}

// --- Retirer une affectation ---
const retraitEnCours = ref<number | null>(null);

function retirer(id: number) {
    router.delete(
        destroy.url({ enseignant: props.enseignant.id, affectation: id }),
        {
            preserveScroll: true,
            onStart: () => (retraitEnCours.value = id),
            onFinish: () => (retraitEnCours.value = null),
        },
    );
}

const classesSelect =
    'h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:bg-muted';
</script>

<template>
    <Head :title="`Affectations de ${nomComplet}`" />

    <Link
        :href="enseignants()"
        class="flex w-fit items-center gap-2 text-sm font-semibold text-primary"
    >
        <ArrowLeft class="size-5" />
        Retour aux enseignants
    </Link>

    <div class="mt-6 mb-6 flex items-center gap-4">
        <Initiales
            :prenom="enseignant.prenom"
            :nom="enseignant.nom"
            taille="grande"
        />
        <div>
            <h1 class="text-2xl font-bold lg:text-[28px]">
                Affectations de {{ nomComplet }}
            </h1>
            <p class="text-muted-foreground">
                {{ ecoleNom }} · enseignant actif
            </p>
        </div>
    </div>

    <!-- Volume horaire maximum -->
    <section
        class="grid gap-6 rounded-xl border bg-white p-6 sm:grid-cols-2 lg:p-7"
    >
        <div class="grid gap-2">
            <Label for="volume">Volume horaire maximum (h / semaine)</Label>
            <Input
                id="volume"
                v-model="formVolume.volume_horaire_max"
                type="number"
                min="1"
                max="40"
                class="max-w-56"
            />
            <InputError :message="formVolume.errors.volume_horaire_max" />
        </div>
        <div>
            <p class="text-sm text-muted-foreground">Heures affectées</p>
            <BarreHeures
                class="mt-1"
                grande
                :minutes="totalMinutes"
                :maximum-heures="enseignant.volume_horaire_max"
            />
            <p class="mt-2 text-sm text-muted-foreground">
                Il reste {{ formatDuree(resteMinutes) }} disponibles.
            </p>
        </div>
    </section>

    <!-- Affectations actuelles -->
    <section class="mt-6 rounded-xl border bg-white p-6 lg:p-7">
        <h2 class="text-xl font-bold">Affectations actuelles</h2>

        <p
            v-if="affectations.length === 0"
            class="mt-4 rounded-xl bg-fond p-6 text-center text-muted-foreground"
        >
            Aucune affectation pour le moment.
        </p>

        <div v-else class="mt-4 overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-fond text-left text-muted-foreground">
                    <tr>
                        <th class="px-5 py-3.5 font-medium">Classe</th>
                        <th class="px-4 py-3.5 font-medium">Matière</th>
                        <th class="px-4 py-3.5 font-medium">
                            Heures / semaine
                        </th>
                        <th class="px-4 py-3.5 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="affectation in affectations"
                        :key="affectation.id"
                        class="border-t"
                    >
                        <td class="px-5 py-3">{{ affectation.classe }}</td>
                        <td class="px-4 py-3">{{ affectation.matiere }}</td>
                        <td class="px-4 py-3">
                            {{ formatDuree(affectation.minutes) }}
                        </td>
                        <td class="px-4 py-3">
                            <BoutonIcone
                                danger
                                :disabled="retraitEnCours === affectation.id"
                                :libelle="`Retirer ${affectation.matiere} en ${affectation.classe}`"
                                @click="retirer(affectation.id)"
                            >
                                <Trash2 />
                            </BoutonIcone>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Nouvelle affectation -->
    <section class="mt-6 rounded-xl border bg-white p-6 lg:p-7">
        <h2 class="text-xl font-bold">Nouvelle affectation</h2>

        <form
            class="mt-4 grid items-end gap-4 lg:grid-cols-[1fr_1fr_auto]"
            @submit.prevent="ajouterAffectation"
        >
            <div class="grid gap-2">
                <Label for="classe">Classe</Label>
                <select
                    id="classe"
                    v-model="formAffectation.classe_id"
                    required
                    :class="classesSelect"
                >
                    <option value="" disabled>Choisir une classe</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">
                        {{ c.nom }}
                    </option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="matiere">Matière</Label>
                <select
                    id="matiere"
                    v-model="formAffectation.matiere_id"
                    required
                    :disabled="!classeChoisie"
                    :class="classesSelect"
                >
                    <option value="" disabled>Choisir une matière</option>
                    <option
                        v-for="m in classeChoisie?.matieres ?? []"
                        :key="m.id"
                        :value="m.id"
                        :disabled="m.enseignant !== null"
                    >
                        {{ m.nom }} ({{ formatDuree(m.minutes) }}){{
                            m.enseignant ? ` — déjà : ${m.enseignant}` : ''
                        }}
                    </option>
                </select>
            </div>

            <Button
                type="submit"
                variant="outline"
                :disabled="formAffectation.processing || depassement !== null"
            >
                <Spinner v-if="formAffectation.processing" />
                Ajouter l'affectation
            </Button>
        </form>

        <div
            v-if="
                depassement ||
                formAffectation.errors.matiere_id ||
                formAffectation.errors.classe_id
            "
            class="mt-4 flex items-start gap-3 rounded-xl bg-erreur/15 px-4 py-3 text-sm text-[#C93D3D]"
            role="alert"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0" />
            {{
                depassement ??
                formAffectation.errors.matiere_id ??
                formAffectation.errors.classe_id
            }}
        </div>
    </section>

    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <Button variant="outline" as-child>
            <Link :href="enseignants()">Annuler</Link>
        </Button>
        <Button
            :disabled="formVolume.processing || !formVolume.isDirty"
            @click="enregistrerVolume"
        >
            <Spinner v-if="formVolume.processing" />
            Enregistrer
        </Button>
    </div>
</template>
