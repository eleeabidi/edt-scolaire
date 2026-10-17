<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import StatutBadge from '@/components/StatutBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, refuser, valider } from '@/routes/directeurs';
import type { Directeur } from '@/types';

const props = defineProps<{
    directeur: Directeur;
}>();

defineOptions({
    layout: { titre: 'Directeurs' },
});

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

// --- Refuser (avec un motif facultatif) ---
const refusOuvert = ref(false);
const formRefus = useForm({ motif: '' });

function refuserInscription() {
    formRefus.post(refuser.url(props.directeur.id), {
        onSuccess: () => (refusOuvert.value = false),
    });
}
</script>

<template>
    <Head :title="`${directeur.prenom} ${directeur.nom}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Link
            :href="index()"
            class="flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Retour aux directeurs
        </Link>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">
                    {{
                        directeur.statut === 'EN_ATTENTE'
                            ? "Demande d'inscription"
                            : 'Directeur'
                    }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Inscrit le {{ directeur.date_creation }}
                </p>
            </div>
            <StatutBadge :statut="directeur.statut" />
        </div>

        <!-- Motif du refus -->
        <div
            v-if="directeur.statut === 'REFUSE'"
            class="rounded-xl border border-[#FF6B6B]/40 bg-[#FF6B6B]/10 p-4 text-sm"
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

        <Card>
            <CardHeader>
                <CardTitle>Le directeur</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Nom complet</dt>
                        <dd class="font-medium">
                            {{ directeur.prenom }} {{ directeur.nom }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="font-medium break-all">
                            {{ directeur.email }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Téléphone</dt>
                        <dd class="font-medium">
                            {{ directeur.telephone ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card v-if="directeur.ecole">
            <CardHeader>
                <CardTitle>L'école</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Nom</dt>
                        <dd class="font-medium">{{ directeur.ecole.nom }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Téléphone</dt>
                        <dd class="font-medium">
                            {{ directeur.ecole.telephone }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted-foreground">Adresse</dt>
                        <dd class="font-medium">
                            {{ directeur.ecole.adresse }},
                            {{ directeur.ecole.ville }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- Actions sur une inscription en attente -->
        <div
            v-if="directeur.statut === 'EN_ATTENTE'"
            class="flex flex-col gap-3 sm:flex-row sm:justify-end"
        >
            <Button
                variant="outline"
                class="text-destructive"
                @click="refusOuvert = true"
            >
                <X />
                Refuser
            </Button>
            <Button
                class="bg-[#3CC9A0] text-white hover:bg-[#3CC9A0]/90"
                :disabled="validationEnCours"
                @click="validerInscription"
            >
                <Spinner v-if="validationEnCours" />
                <Check v-else />
                Valider l'inscription
            </Button>
        </div>
    </div>

    <!-- Fenêtre Refuser -->
    <Dialog v-model:open="refusOuvert">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Refuser l'inscription ?</DialogTitle>
                <DialogDescription>
                    {{ directeur.prenom }} {{ directeur.nom }} sera prévenu par
                    email.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="refuserInscription">
                <div class="grid gap-2">
                    <Label for="motif">Motif (facultatif)</Label>
                    <textarea
                        id="motif"
                        v-model="formRefus.motif"
                        rows="4"
                        maxlength="1000"
                        placeholder="Ex. : informations de l'école incomplètes"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                    <InputError :message="formRefus.errors.motif" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="refusOuvert = false"
                        >Annuler</Button
                    >
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
