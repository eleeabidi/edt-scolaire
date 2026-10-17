<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, GraduationCap, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import InfoBox from '@/components/InfoBox.vue';
import InputError from '@/components/InputError.vue';
import StatutBadge from '@/components/StatutBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, refuser, valider } from '@/routes/directeurs';
import type { Directeur } from '@/types';

// Écran 10 — Détail d'une inscription (+ fenêtre F5 Refuser)
const props = defineProps<{
    directeur: Directeur & { date_creation_longue: string | null };
}>();

defineOptions({
    layout: { titre: 'Directeurs' },
});

const enAttente = computed(() => props.directeur.statut === 'EN_ATTENTE');
const nomComplet = computed(
    () => `${props.directeur.prenom} ${props.directeur.nom}`,
);

const lignesDirecteur = computed(() => [
    { libelle: 'Nom', valeur: props.directeur.nom },
    { libelle: 'Prénom', valeur: props.directeur.prenom },
    { libelle: 'Email', valeur: props.directeur.email },
    { libelle: 'Téléphone', valeur: props.directeur.telephone ?? '—' },
]);

const lignesEcole = computed(() => [
    { libelle: 'Nom', valeur: props.directeur.ecole?.nom ?? '—' },
    { libelle: 'Adresse', valeur: props.directeur.ecole?.adresse ?? '—' },
    { libelle: 'Ville', valeur: props.directeur.ecole?.ville ?? '—' },
    { libelle: 'Téléphone', valeur: props.directeur.ecole?.telephone ?? '—' },
]);

// --- Valider ---
const validationEnCours = ref(false);

function validerInscription() {
    router.post(
        valider.url(props.directeur.id),
        {},
        {
            onStart: () => (validationEnCours.value = true),
            onFinish: () => (validationEnCours.value = false),
        },
    );
}

// --- Refuser (motif facultatif) ---
const refusOuvert = ref(false);
const formRefus = useForm({ motif: '' });

function refuserInscription() {
    formRefus.post(refuser.url(props.directeur.id), {
        onSuccess: () => (refusOuvert.value = false),
    });
}
</script>

<template>
    <Head :title="nomComplet" />

    <Link
        :href="index()"
        class="flex w-fit items-center gap-2 text-sm font-semibold text-primary"
    >
        <ArrowLeft class="size-5" />
        Retour aux directeurs
    </Link>

    <div class="mt-6 mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold lg:text-[28px]">
                {{ enAttente ? `Inscription de ${nomComplet}` : nomComplet }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{ enAttente ? 'Reçue le' : 'Inscrit le' }}
                {{ directeur.date_creation_longue }}
            </p>
        </div>
        <StatutBadge :statut="directeur.statut" />
    </div>

    <!-- Motif du refus -->
    <div
        v-if="directeur.statut === 'REFUSE'"
        class="mb-6 rounded-xl border border-erreur/30 bg-erreur/10 px-5 py-4 text-sm"
    >
        <p class="font-semibold">Inscription refusée</p>
        <p class="mt-1 text-muted-foreground">
            {{
                directeur.motif_refus
                    ? `Motif : ${directeur.motif_refus}`
                    : 'Aucun motif indiqué.'
            }}
        </p>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <section class="rounded-xl border bg-white p-6 lg:p-7">
            <h2 class="flex items-center gap-3 text-xl font-bold">
                <UserRound class="size-5 text-primary" />
                Le directeur
            </h2>
            <dl class="mt-5 grid gap-4 text-[15px]">
                <div
                    v-for="ligne in lignesDirecteur"
                    :key="ligne.libelle"
                    class="grid grid-cols-[110px_1fr] gap-3"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ ligne.libelle }}
                    </dt>
                    <dd class="font-medium break-all">{{ ligne.valeur }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border bg-white p-6 lg:p-7">
            <h2 class="flex items-center gap-3 text-xl font-bold">
                <GraduationCap class="size-5 text-primary" />
                L'école
            </h2>
            <dl class="mt-5 grid gap-4 text-[15px]">
                <div
                    v-for="ligne in lignesEcole"
                    :key="ligne.libelle"
                    class="grid grid-cols-[110px_1fr] gap-3"
                >
                    <dt class="text-sm text-muted-foreground">
                        {{ ligne.libelle }}
                    </dt>
                    <dd class="font-medium">{{ ligne.valeur }}</dd>
                </div>
            </dl>
        </section>
    </div>

    <!-- Actions sur une inscription en attente -->
    <template v-if="enAttente">
        <InfoBox class="mt-6">
            Après validation, le directeur reçoit un email et peut se connecter.
            En cas de refus, il reçoit le motif.
        </InfoBox>

        <div
            class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Button variant="destructive" @click="refusOuvert = true">
                Refuser
            </Button>
            <Button :disabled="validationEnCours" @click="validerInscription">
                <Spinner v-if="validationEnCours" />
                Valider l'inscription
            </Button>
        </div>
    </template>

    <!-- F5 — Refuser l'inscription -->
    <Dialog v-model:open="refusOuvert">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Refuser l'inscription</DialogTitle>
                <DialogDescription>
                    {{ nomComplet }} recevra un email avec le motif du refus.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="refuserInscription">
                <div class="grid gap-2">
                    <Label for="motif">Motif (facultatif)</Label>
                    <Input
                        id="motif"
                        v-model="formRefus.motif"
                        maxlength="1000"
                        placeholder="Informations de l'école incomplètes"
                    />
                    <InputError :message="formRefus.errors.motif" />
                </div>

                <DialogFooter class="gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="refusOuvert = false"
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="formRefus.processing"
                    >
                        <Spinner v-if="formRefus.processing" />
                        Refuser
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
