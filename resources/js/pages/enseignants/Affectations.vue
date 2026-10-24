<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Info, Trash2, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BarreHeures from '@/components/BarreHeures.vue';
import BoutonIcone from '@/components/BoutonIcone.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import Initiales from '@/components/Initiales.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDuree } from '@/lib/format';
import { destroy, store, volume } from '@/routes/affectations';
import { index as classesIndex } from '@/routes/classes';
import { index as enseignants } from '@/routes/enseignants';

// Écran 15 — Affectations d'un enseignant (directeur)
type MatiereDeClasse = {
    id: number;
    nom: string;
    minutes: number;
    enseignant: string | null; // qui l'enseigne déjà dans cette classe
};

type Affectation = {
    id: number;
    classe: string;
    matiere: string;
    minutes: number;
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
    affectations: Affectation[];
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
    volume_horaire_max: props.enseignant.volume_horaire_max as number | '',
});

watch(
    () => props.enseignant.volume_horaire_max,
    (valeur) => {
        formVolume.defaults({ volume_horaire_max: valeur });
        formVolume.reset();
    },
);

// Le maximum en cours de saisie (il est enregistré avec la nouvelle affectation)
const maximumHeures = computed(
    () => Number(formVolume.volume_horaire_max) || 0,
);

const resteMinutes = computed(() =>
    Math.max(0, maximumHeures.value * 60 - props.totalMinutes),
);

function enregistrerVolume() {
    formVolume.patch(volume.url(props.enseignant.id), { preserveScroll: true });
}

// --- Nouvelle affectation : une classe et les matières que l'enseignant y enseigne ---
const formAffectation = useForm({
    classe_id: '' as number | '',
    matieres: [] as number[],
});

const classeChoisie = computed(() =>
    props.classes.find((c) => c.id === formAffectation.classe_id),
);
const matieresChoisies = computed(
    () =>
        classeChoisie.value?.matieres.filter((m) =>
            formAffectation.matieres.includes(m.id),
        ) ?? [],
);
const minutesChoisies = computed(() =>
    matieresChoisies.value.reduce((somme, m) => somme + m.minutes, 0),
);

// Changer de classe vide le choix des matières
watch(
    () => formAffectation.classe_id,
    () => (formAffectation.matieres = []),
);

// Aperçu avant l'envoi : le serveur vérifie aussi la règle
const depassement = computed(() => {
    if (matieresChoisies.value.length === 0 || !classeChoisie.value) {
        return null;
    }

    const total = props.totalMinutes + minutesChoisies.value;

    return total > maximumHeures.value * 60
        ? `Impossible : ${matieresChoisies.value.map((m) => m.nom.toLowerCase()).join(', ')} en ${classeChoisie.value.nom} ${matieresChoisies.value.length > 1 ? 'comptent' : 'compte'} ${formatDuree(minutesChoisies.value)}. Le total passerait à ${formatDuree(total)} et dépasserait le maximum de ${maximumHeures.value} h.`
        : null;
});

// Le maximum est aussi vérifié quand il est envoyé avec la nouvelle affectation (4c)
const erreurVolume = computed(
    () =>
        formVolume.errors.volume_horaire_max ??
        (formAffectation.errors as Record<string, string | undefined>)
            .volume_horaire_max,
);

function ajouterAffectation() {
    formAffectation
        .transform((donnees) => ({
            ...donnees,
            volume_horaire_max: formVolume.volume_horaire_max,
        }))
        .post(store.url(props.enseignant.id), {
            preserveScroll: true,
            onSuccess: () => formAffectation.reset(),
        });
}

// --- Retirer une affectation, après confirmation ---
const aRetirer = ref<Affectation | null>(null);
const confirmationOuverte = ref(false);
const retraitEnCours = ref(false);

function demanderRetrait(affectation: Affectation) {
    aRetirer.value = affectation;
    confirmationOuverte.value = true;
}

function retirer() {
    if (!aRetirer.value) {
        return;
    }

    router.delete(
        destroy.url({
            enseignant: props.enseignant.id,
            affectation: aRetirer.value.id,
        }),
        {
            preserveScroll: true,
            onStart: () => (retraitEnCours.value = true),
            onFinish: () => {
                retraitEnCours.value = false;
                confirmationOuverte.value = false;
            },
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
            <InputError :message="erreurVolume" />
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
                {{
                    resteMinutes === 0
                        ? 'Il ne reste aucune heure disponible.'
                        : `Il reste ${formatDuree(resteMinutes)} disponibles.`
                }}
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
                                :libelle="`Retirer ${affectation.matiere} en ${affectation.classe}`"
                                @click="demanderRetrait(affectation)"
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

        <!-- 2a : aucune classe dans l'école → créer d'abord les classes -->
        <div
            v-if="classes.length === 0"
            class="mt-4 flex flex-col items-start gap-4 rounded-xl bg-violet-clair p-5 text-sm sm:flex-row sm:items-center"
        >
            <Info class="size-5 shrink-0 text-primary" />
            <p class="flex-1">
                Aucune classe n'existe encore dans votre école. Créez d'abord
                les classes, puis revenez affecter cet enseignant.
            </p>
            <Button as-child>
                <Link :href="classesIndex()">Créer les classes</Link>
            </Button>
        </div>

        <form
            v-else
            class="mt-4 grid gap-4"
            @submit.prevent="ajouterAffectation"
        >
            <div class="grid gap-2 lg:max-w-sm">
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
                <InputError :message="formAffectation.errors.classe_id" />
            </div>

            <fieldset v-if="classeChoisie" class="grid gap-2">
                <legend class="mb-2 text-sm font-semibold">
                    Matières enseignées en {{ classeChoisie.nom }}
                </legend>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="m in classeChoisie.matieres"
                        :key="m.id"
                        class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
                        :class="
                            m.enseignant
                                ? 'cursor-not-allowed bg-muted text-muted-foreground'
                                : 'cursor-pointer hover:border-primary'
                        "
                    >
                        <input
                            v-model="formAffectation.matieres"
                            type="checkbox"
                            :value="m.id"
                            :disabled="m.enseignant !== null"
                            class="mt-0.5 size-4 accent-primary"
                        />
                        <span>
                            <span class="font-semibold">{{ m.nom }}</span>
                            ({{ formatDuree(m.minutes) }})
                            <span v-if="m.enseignant" class="block text-xs">
                                Déjà enseignée par {{ m.enseignant }}
                            </span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div>
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="
                        formAffectation.processing ||
                        formAffectation.matieres.length === 0 ||
                        depassement !== null
                    "
                >
                    <Spinner v-if="formAffectation.processing" />
                    Ajouter l'affectation
                </Button>
            </div>
        </form>

        <div
            v-if="depassement || formAffectation.errors.matieres"
            class="mt-4 flex items-start gap-3 rounded-xl bg-erreur/15 px-4 py-3 text-sm text-[#C93D3D]"
            role="alert"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0" />
            {{ depassement ?? formAffectation.errors.matieres }}
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

    <ConfirmationDialog
        v-model:open="confirmationOuverte"
        titre="Retirer cette affectation ?"
        :message="`${aRetirer?.matiere} en ${aRetirer?.classe} ne sera plus enseignée par ${nomComplet}.`"
        libelle-confirmer="Retirer"
        :en-cours="retraitEnCours"
        @confirmer="retirer"
    />
</template>
