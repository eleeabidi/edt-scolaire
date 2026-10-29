<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Check, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import GrilleSemaine from '@/components/emploi/GrilleSemaine.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDuree, formatHeure } from '@/lib/format';
import { conflits, generer, index, publier } from '@/routes/emploi';
import { deplacer, salle as changerSalle } from '@/routes/seances';
import type { LigneGrille, SeanceGrille, VueGrille } from '@/types';

// Rubrique « Emploi du temps » (directeur) : générer (US13) et consulter la grille (US15)
type Emploi = {
    id: number;
    annee: string;
    statut: 'brouillon' | 'publie';
    statutLibelle: string;
};

type Option = { id: number; nom: string };

const props = defineProps<{
    ecoleNom: string;
    annees: string[];
    annee: string;
    emploi: Emploi | null;
    // Grille (seulement si l'emploi du temps existe)
    lignes?: LigneGrille[];
    cases?: string[];
    options?: Record<VueGrille, Option[]>;
    par?: VueGrille;
    choix?: number;
    seances?: SeanceGrille[];
    totalMinutes?: number;
    nbConflits?: number;
    surlignee?: number | null;
    horaires?: Record<string, number>; // « jour-HH:MM » → id du créneau
    sallesPossibles?: Option[];
}>();

defineOptions({
    layout: { titre: 'Emploi du temps' },
});

// --- Année scolaire ---
const anneeChoisie = ref(props.annee);

function changerAnnee() {
    router.get(
        index.url(),
        { annee: anneeChoisie.value },
        { preserveScroll: true },
    );
}

// --- Afficher par classe, enseignant ou salle (T15.3) ---
const vues: { valeur: VueGrille; libelle: string }[] = [
    { valeur: 'classe', libelle: 'Classe' },
    { valeur: 'enseignant', libelle: 'Enseignant' },
    { valeur: 'salle', libelle: 'Salle' },
];

const par = ref<VueGrille>(props.par ?? 'classe');
const choix = ref<number>(props.choix ?? 0);

function afficher() {
    router.get(
        index.url(),
        { annee: props.annee, par: par.value, id: choix.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['par', 'choix', 'seances', 'totalMinutes'],
        },
    );
}

function changerVue(vue: VueGrille) {
    par.value = vue;
    choix.value = props.options?.[vue][0]?.id ?? 0;
    afficher();
}

const nomChoisi = computed(
    () =>
        props.options?.[par.value].find((o) => o.id === choix.value)?.nom ?? '',
);

// --- Modifier une séance (US16) : glisser-déposer, ou clic pour changer la salle ---
const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const causeRefus = ref<string | null>(null);
const refusOuvert = ref(false);

function deplacerSeance(seance: SeanceGrille, cle: string) {
    const horaireId = props.horaires?.[cle];

    if (!horaireId) {
        return;
    }

    router.patch(
        deplacer.url(seance.id),
        { horaire_id: horaireId },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (erreurs) => {
                // 2a : conflit → alerte avec la cause, la séance reste à sa place
                causeRefus.value = erreurs.deplacement ?? null;
                refusOuvert.value = causeRefus.value !== null;
            },
        },
    );
}

const seanceChoisie = ref<SeanceGrille | null>(null);
const salleOuverte = ref(false);
const formSalle = useForm({ salle_id: 0 });

function choisirSeance(seance: SeanceGrille) {
    seanceChoisie.value = seance;
    formSalle.clearErrors();
    formSalle.salle_id = 0;
    salleOuverte.value = true;

    // Les salles possibles sont calculées par le serveur pour cette séance
    router.reload({
        only: ['sallesPossibles'],
        data: { salles_pour: seance.id },
        onSuccess: () => {
            formSalle.salle_id = props.sallesPossibles?.[0]?.id ?? 0;
        },
    });
}

function enregistrerSalle() {
    if (!seanceChoisie.value) {
        return;
    }

    formSalle.patch(changerSalle.url(seanceChoisie.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (salleOuverte.value = false),
    });
}

const descriptionSeance = computed(() => {
    const s = seanceChoisie.value;

    return s
        ? `${s.classe} · ${jours[s.jour - 1]} ${formatHeure(s.debut)} – ${formatHeure(s.fin)} · ${s.matieres.map((m) => m.nom).join(', ')} · ${s.enseignant}`
        : '';
});

// --- Publier (US17) ---
const publicationOuverte = ref(false);
const publication = ref(false);
const refusPublication = ref<string | null>(null);

function lancerPublication() {
    if (!props.emploi) {
        return;
    }

    router.post(
        publier.url(props.emploi.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (publication.value = true),
            onFinish: () => {
                publication.value = false;
                publicationOuverte.value = false;
            },
            // 4a : il reste des conflits → publication refusée
            onError: (erreurs) =>
                (refusPublication.value = erreurs.publication ?? null),
        },
    );
}

const sousTitre = computed(() =>
    props.emploi?.statut === 'publie'
        ? `${props.ecoleNom} · année scolaire ${props.emploi.annee}. L'emploi du temps est publié : chaque enseignant voit le sien.`
        : props.emploi
          ? `${props.ecoleNom} · année scolaire ${props.emploi.annee}. Glissez une séance pour la déplacer, cliquez dessus pour changer la salle.`
          : `${props.ecoleNom} · générez l'emploi du temps de toutes les classes de l'école.`,
);

// --- Générer ---
const generation = ref(false);
const remplacementOuvert = ref(false);
const causes = ref<string[]>([]);
const impossibleOuvert = ref(false);

// 3a : un brouillon existe déjà → confirmer son remplacement avant de générer
function demanderGeneration() {
    if (props.emploi) {
        remplacementOuvert.value = true;

        return;
    }

    lancer(false);
}

function lancer(remplacer: boolean) {
    router.post(
        generer.url(),
        { annee_scolaire: anneeChoisie.value, remplacer },
        {
            preserveScroll: true,
            onStart: () => (generation.value = true),
            onFinish: () => {
                generation.value = false;
                remplacementOuvert.value = false;
            },
            onError: (erreurs) => {
                // 4a / 5a : les causes, une par ligne
                if (erreurs.generation) {
                    causes.value = erreurs.generation.split('\n');
                    impossibleOuvert.value = true;
                } else if (erreurs.remplacer) {
                    remplacementOuvert.value = true;
                }
            },
        },
    );
}

const selectClasses =
    'h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-56';
</script>

<template>
    <Head title="Emploi du temps" />

    <PageHeader titre="Emploi du temps" :sous-titre="sousTitre">
        <Button v-if="emploi" variant="outline" as-child>
            <Link :href="conflits(emploi.id)">
                Conflits ({{ nbConflits ?? 0 }})
            </Link>
        </Button>
        <Button
            v-if="emploi && emploi.statut === 'brouillon'"
            variant="outline"
            :disabled="generation"
            @click="demanderGeneration"
        >
            <Spinner v-if="generation" />
            Générer
        </Button>
        <Button
            v-if="emploi && emploi.statut === 'brouillon'"
            @click="publicationOuverte = true"
        >
            Publier
        </Button>
    </PageHeader>

    <!-- 2a : aucun emploi du temps pour cette année → proposer de le générer -->
    <section
        v-if="!emploi"
        class="mx-auto flex max-w-3xl flex-col items-center rounded-2xl border bg-white p-8 text-center"
    >
        <div
            class="flex size-14 items-center justify-center rounded-2xl bg-violet-clair text-primary"
        >
            <CalendarDays class="size-6" />
        </div>
        <h2 class="mt-4 text-xl font-bold">
            Aucun emploi du temps pour {{ anneeChoisie }}
        </h2>
        <p class="mt-2 max-w-xl text-sm text-muted-foreground">
            Choisissez l'année scolaire, puis lancez la génération.
            L'application place les séances de toutes les classes en respectant
            le guide pédagogique, les règles de gestion des emplois du temps,
            les disponibilités des enseignants et les salles en service.
        </p>

        <div
            class="mt-6 flex w-full flex-col items-stretch gap-3 sm:w-auto sm:flex-row sm:items-end"
        >
            <div class="grid gap-2 text-left">
                <Label for="annee">Année scolaire</Label>
                <select
                    id="annee"
                    v-model="anneeChoisie"
                    :class="selectClasses"
                    @change="changerAnnee"
                >
                    <option v-for="a in annees" :key="a" :value="a">
                        {{ a }}
                    </option>
                </select>
            </div>
            <Button :disabled="generation" @click="demanderGeneration">
                <Spinner v-if="generation" />
                Générer
            </Button>
        </div>

        <div
            class="mt-6 w-full max-w-xl rounded-xl bg-fond p-5 text-left text-sm"
        >
            <p class="font-semibold">Avant de générer, le système vérifie :</p>
            <ul class="mt-3 grid gap-2 text-muted-foreground">
                <li class="flex items-start gap-2">
                    <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                    Chaque matière de chaque classe a un enseignant (Détails des
                    enseignants)
                </li>
                <li class="flex items-start gap-2">
                    <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                    Au moins une salle de technologie est en service (Salles)
                </li>
                <li class="flex items-start gap-2">
                    <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                    Au moins une salle de sport est en service (Salles)
                </li>
            </ul>
        </div>
    </section>

    <!-- Emploi du temps de l'année : grille de la semaine (US15) -->
    <template v-else>
        <div
            class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Afficher par</span>
                    <div
                        class="flex h-12 rounded-md border bg-white p-1"
                        role="group"
                        aria-label="Afficher par"
                    >
                        <button
                            v-for="vue in vues"
                            :key="vue.valeur"
                            type="button"
                            class="rounded px-4 text-sm font-semibold transition-colors"
                            :class="
                                par === vue.valeur
                                    ? 'bg-primary text-white'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="par === vue.valeur"
                            @click="changerVue(vue.valeur)"
                        >
                            {{ vue.libelle }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="choix">{{
                        vues.find((v) => v.valeur === par)?.libelle
                    }}</Label>
                    <select
                        id="choix"
                        v-model="choix"
                        :class="selectClasses"
                        @change="afficher"
                    >
                        <option
                            v-for="o in options?.[par] ?? []"
                            :key="o.id"
                            :value="o.id"
                        >
                            {{ o.nom }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="annee">Année scolaire</Label>
                    <select
                        id="annee"
                        v-model="anneeChoisie"
                        :class="selectClasses"
                        @change="changerAnnee"
                    >
                        <option v-for="a in annees" :key="a" :value="a">
                            {{ a }}
                        </option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="
                        emploi.statut === 'publie'
                            ? 'bg-succes/15 text-[#1F8A6B]'
                            : 'bg-[#FFC94D]/25 text-[#8A6100]'
                    "
                >
                    {{ emploi.statutLibelle }}
                </span>
                <span
                    class="rounded-full bg-violet-clair px-3 py-1 text-xs font-semibold text-primary"
                >
                    {{ formatDuree(totalMinutes ?? 0) }} par semaine
                </span>
            </div>
        </div>

        <!-- 4a : aucune séance pour ce choix -->
        <p
            v-if="(seances ?? []).length === 0"
            class="mb-4 rounded-xl bg-violet-clair px-4 py-3 text-sm"
        >
            {{ nomChoisi }} n'a aucune séance dans cet emploi du temps.
        </p>

        <GrilleSemaine
            :lignes="lignes ?? []"
            :cases="cases ?? []"
            :seances="seances ?? []"
            :vue="par"
            :surlignee="surlignee"
            modifiable
            @deplacer="deplacerSeance"
            @choisir="choisirSeance"
        />
    </template>

    <!-- 3a : remplacer le brouillon -->
    <ConfirmationDialog
        v-model:open="remplacementOuvert"
        titre="Remplacer le brouillon ?"
        :message="`Un emploi du temps en brouillon existe déjà pour l'année scolaire ${anneeChoisie}. La nouvelle génération le remplacera, avec les modifications faites à la main.`"
        libelle-confirmer="Remplacer"
        icone="alerte"
        :en-cours="generation"
        @confirmer="lancer(true)"
    />

    <!-- 4a / 5a : génération impossible -->
    <Dialog v-model:open="impossibleOuvert">
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-erreur/15 text-erreur"
            >
                <TriangleAlert class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle>Génération impossible</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    Corrigez ces données, puis relancez la génération :
                </DialogDescription>
            </DialogHeader>
            <ul
                class="grid list-disc gap-1.5 pl-5 text-sm text-muted-foreground"
            >
                <li v-for="cause in causes" :key="cause">{{ cause }}</li>
            </ul>
            <DialogFooter>
                <Button @click="impossibleOuvert = false">Compris</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- 2a : déplacement impossible -->
    <Dialog v-model:open="refusOuvert">
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-erreur/15 text-erreur"
            >
                <TriangleAlert class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle>Déplacement impossible</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    {{ causeRefus }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button @click="refusOuvert = false">Compris</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- 1a : changer la salle de la séance -->
    <Dialog v-model:open="salleOuverte">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Changer la salle de la séance</DialogTitle>
                <DialogDescription>{{ descriptionSeance }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="enregistrerSalle">
                <div class="grid gap-2">
                    <Label for="salle">Salle</Label>
                    <select
                        id="salle"
                        v-model="formSalle.salle_id"
                        required
                        class="h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option
                            v-for="s in sallesPossibles ?? []"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.nom }}
                        </option>
                    </select>
                    <InputError :message="formSalle.errors.salle_id" />
                </div>
                <p class="text-sm text-muted-foreground">
                    Seules les salles en service, libres sur ce créneau, du bon
                    type et assez grandes pour la classe sont proposées.
                </p>
                <DialogFooter class="gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="salleOuverte = false"
                    >
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="formSalle.processing">
                        <Spinner v-if="formSalle.processing" />
                        Enregistrer
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Publier l'emploi du temps ? -->
    <ConfirmationDialog
        v-model:open="publicationOuverte"
        titre="Publier l'emploi du temps ?"
        :message="`L'emploi du temps ${emploi?.annee} deviendra visible pour les enseignants de l'école. Chaque enseignant recevra une notification.`"
        libelle-confirmer="Publier"
        icone="valider"
        :danger="false"
        :en-cours="publication"
        @confirmer="lancerPublication"
    />

    <!-- 4a : publication impossible (conflits) -->
    <Dialog
        :open="refusPublication !== null"
        @update:open="(ouvert) => !ouvert && (refusPublication = null)"
    >
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-erreur/15 text-erreur"
            >
                <TriangleAlert class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle>Publication impossible</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    {{ refusPublication }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-3">
                <Button variant="outline" @click="refusPublication = null">
                    Fermer
                </Button>
                <Button v-if="emploi" as-child>
                    <Link :href="conflits(emploi.id)">Voir les conflits</Link>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
