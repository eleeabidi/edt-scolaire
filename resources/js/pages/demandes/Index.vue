<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';
import { statutsDemande, typesSalle } from '@/lib/format';
import { accepter, refuser } from '@/routes/demandes';
import { conflits } from '@/routes/emploi';
import type { DemandeIndisponibilite, TypeSalle } from '@/types';

// US21 — « Demandes d'indisponibilité » (directeur, maquette 30) : accepter ou refuser les demandes
// des enseignants de l'école. F18 : heures disponibles insuffisantes ; F19 : séances touchées.
type Demande = DemandeIndisponibilite & {
    enseignant: string;
    initiales: string;
};

type SeancesTouchees = {
    enseignant: string;
    emploiId: number;
    seances: {
        id: number;
        quand: string;
        detail: string;
        typeSalle: TypeSalle;
    }[];
};

const props = defineProps<{
    ecoleNom: string;
    annee: string;
    demandes: Demande[];
}>();

defineOptions({
    layout: { titre: "Demandes d'indisponibilité" },
});

const enCours = ref<number | null>(null);

function refuserDemande(demande: Demande) {
    router.patch(
        refuser.url(demande.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (enCours.value = demande.id),
            onFinish: () => (enCours.value = null),
        },
    );
}

// --- Accepter ---
const avertissement = ref<{
    demande: Demande;
    message: string;
    heuresAffectees: string;
    heuresDisponibles: string;
} | null>(null);
const touchees = ref<SeancesTouchees | null>(null);

function accepterDemande(demande: Demande, confirme = false) {
    router.patch(
        accepter.url(demande.id),
        { confirme },
        {
            preserveScroll: true,
            onStart: () => (enCours.value = demande.id),
            onFinish: () => (enCours.value = null),
            onSuccess: () => (avertissement.value = null),
            // 4a : les heures disponibles deviendraient inférieures aux heures affectées → F18
            onError: (erreurs) => {
                if (erreurs.confirme) {
                    avertissement.value = {
                        demande,
                        message: erreurs.confirme,
                        heuresAffectees: erreurs.heuresAffectees ?? '',
                        heuresDisponibles: erreurs.heuresDisponibles ?? '',
                    };
                }
            },
            // 5a : des séances de l'emploi du temps publié tombent sur ces créneaux → F19
            onFlash: (flash) => {
                const donnees = (flash as { seancesTouchees?: SeancesTouchees })
                    .seancesTouchees;

                if (donnees) {
                    touchees.value = donnees;
                }
            },
        },
    );
}

const sousTitre = computed(
    () =>
        `${props.ecoleNom} · année scolaire ${props.annee}. Les demandes en attente s'affichent en premier.`,
);
</script>

<template>
    <Head title="Demandes d'indisponibilité" />

    <PageHeader titre="Demandes d'indisponibilité" :sous-titre="sousTitre" />

    <!-- 2a : aucune demande -->
    <p
        v-if="demandes.length === 0"
        class="rounded-2xl border bg-white px-5 py-10 text-center text-muted-foreground"
    >
        Aucune demande
    </p>

    <div v-else class="overflow-x-auto rounded-2xl border bg-white">
        <table class="w-full min-w-[900px] text-left text-[15px]">
            <thead class="bg-fond text-sm text-muted-foreground">
                <tr>
                    <th class="px-5 py-3 font-medium">Enseignant</th>
                    <th class="px-3 py-3 font-medium">Jour</th>
                    <th class="px-3 py-3 font-medium">Horaire</th>
                    <th class="px-3 py-3 font-medium">Motif</th>
                    <th class="px-3 py-3 font-medium">Envoyée le</th>
                    <th class="px-3 py-3 font-medium">Statut</th>
                    <th class="px-5 py-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="d in demandes" :key="d.id" class="border-t">
                    <td class="px-5 py-4">
                        <span class="flex items-center gap-3 font-semibold">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-violet-clair text-xs font-bold text-primary"
                            >
                                {{ d.initiales }}
                            </span>
                            {{ d.enseignant }}
                        </span>
                    </td>
                    <td class="px-3 py-4">{{ d.jour }}</td>
                    <td class="px-3 py-4 whitespace-nowrap">{{ d.horaire }}</td>
                    <td class="px-3 py-4 text-muted-foreground">
                        {{ d.motif }}
                    </td>
                    <td class="px-3 py-4 text-muted-foreground">
                        {{ d.dateEnvoi }}
                    </td>
                    <td class="px-3 py-4">
                        <span
                            class="rounded-full px-3 py-1 text-sm font-semibold whitespace-nowrap"
                            :class="statutsDemande[d.statut]"
                        >
                            {{ d.statutLibelle }}
                        </span>
                    </td>
                    <td class="px-5 py-4">
                        <div
                            v-if="d.statut === 'en_attente'"
                            class="flex gap-2"
                        >
                            <Button
                                variant="outline"
                                :disabled="enCours === d.id"
                                @click="refuserDemande(d)"
                            >
                                Refuser
                            </Button>
                            <Button
                                :disabled="enCours === d.id"
                                @click="accepterDemande(d)"
                            >
                                <Spinner v-if="enCours === d.id" />
                                Accepter
                            </Button>
                        </div>
                        <span v-else class="text-muted-foreground">—</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- F18 : heures disponibles insuffisantes -->
    <Dialog
        :open="avertissement !== null"
        @update:open="(ouvert) => !ouvert && (avertissement = null)"
    >
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-[#FFC94D]/25 text-[#8A6100]"
            >
                <TriangleAlert class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle>Heures disponibles insuffisantes</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    {{ avertissement?.message }}
                </DialogDescription>
            </DialogHeader>
            <dl class="grid gap-3 rounded-xl bg-fond p-4 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">
                        Heures affectées par semaine
                    </dt>
                    <dd class="font-bold">
                        {{ avertissement?.heuresAffectees }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">
                        Heures disponibles après acceptation
                    </dt>
                    <dd class="font-bold text-[#C93D3D]">
                        {{ avertissement?.heuresDisponibles }}
                    </dd>
                </div>
            </dl>
            <DialogFooter class="gap-3">
                <Button variant="outline" @click="avertissement = null">
                    Annuler
                </Button>
                <Button
                    :disabled="enCours !== null"
                    @click="
                        avertissement &&
                        accepterDemande(avertissement.demande, true)
                    "
                >
                    <Spinner v-if="enCours !== null" />
                    Confirmer l'acceptation
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- F19 : séances touchées par l'indisponibilité -->
    <Dialog
        :open="touchees !== null"
        @update:open="(ouvert) => !ouvert && (touchees = null)"
    >
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-erreur/15 text-erreur"
            >
                <TriangleAlert class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle
                    >Séances touchées par l'indisponibilité</DialogTitle
                >
                <DialogDescription class="leading-relaxed">
                    La demande de {{ touchees?.enseignant }} est acceptée. Ces
                    séances de l'emploi du temps publié tombent sur des créneaux
                    où l'enseignant n'est plus disponible. Elles apparaissent
                    dans les conflits « enseignant non disponible ».
                </DialogDescription>
            </DialogHeader>
            <ul class="grid max-h-72 gap-3 overflow-y-auto">
                <li
                    v-for="s in touchees?.seances ?? []"
                    :key="s.id"
                    class="rounded-xl px-4 py-3"
                    :class="typesSalle[s.typeSalle].classes"
                >
                    <p class="font-bold text-foreground">{{ s.quand }}</p>
                    <p class="text-sm text-muted-foreground">{{ s.detail }}</p>
                </li>
            </ul>
            <DialogFooter class="gap-3">
                <Button variant="outline" @click="touchees = null">
                    Fermer
                </Button>
                <Button v-if="touchees" as-child>
                    <Link :href="conflits(touchees.emploiId)">
                        Voir les conflits
                    </Link>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
