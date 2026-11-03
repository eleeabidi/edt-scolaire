<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatHeure, statutsDemande } from '@/lib/format';
import { store } from '@/routes/mes-demandes';
import type { DemandeIndisponibilite } from '@/types';

// US21 — « Mes demandes d'indisponibilité » (enseignant, maquette 29) : signaler une indisponibilité
// parmi les créneaux de l'école, puis suivre le statut de ses demandes.
type JourCreneaux = {
    valeur: number;
    nom: string;
    debuts: string[];
    fins: string[];
};

const props = defineProps<{
    demandes: DemandeIndisponibilite[];
    jours: JourCreneaux[];
}>();

defineOptions({
    layout: { titre: "Mes demandes d'indisponibilité" },
});

const form = useForm({
    jour: '' as number | '',
    heure_debut: '',
    heure_fin: '',
    motif: '',
});

const jourChoisi = computed(() =>
    props.jours.find((j) => j.valeur === form.jour),
);

// Un autre jour : les heures choisies ne sont peut-être plus des créneaux de ce jour
watch(
    () => form.jour,
    () => {
        if (!jourChoisi.value?.debuts.includes(form.heure_debut)) {
            form.heure_debut = '';
        }

        if (!jourChoisi.value?.fins.includes(form.heure_fin)) {
            form.heure_fin = '';
        }
    },
);

function envoyer() {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

// 3a : annuler → rien n'est enregistré
function annuler() {
    form.reset();
    form.clearErrors();
}

const selectClasses =
    'h-12 w-full rounded-md border border-input bg-white px-4 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60';
</script>

<template>
    <Head title="Mes demandes d'indisponibilité" />

    <PageHeader titre="Mes demandes d'indisponibilité" />

    <div class="grid gap-8 sm:max-w-xl">
        <form
            class="grid gap-4 rounded-2xl border bg-white p-5"
            @submit.prevent="envoyer"
        >
            <h2 class="text-lg font-bold">Signaler une indisponibilité</h2>

            <div class="grid gap-2">
                <Label for="jour">Jour</Label>
                <select id="jour" v-model="form.jour" :class="selectClasses">
                    <option value="" disabled>Choisir un jour</option>
                    <option
                        v-for="j in jours"
                        :key="j.valeur"
                        :value="j.valeur"
                    >
                        {{ j.nom }}
                    </option>
                </select>
                <InputError :message="form.errors.jour" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid content-start gap-2">
                    <Label for="heure_debut">Heure de début</Label>
                    <select
                        id="heure_debut"
                        v-model="form.heure_debut"
                        :class="selectClasses"
                        :disabled="!jourChoisi"
                    >
                        <option value="" disabled>Choisir</option>
                        <option
                            v-for="h in jourChoisi?.debuts ?? []"
                            :key="h"
                            :value="h"
                        >
                            {{ formatHeure(h) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.heure_debut" />
                </div>
                <div class="grid content-start gap-2">
                    <Label for="heure_fin">Heure de fin</Label>
                    <select
                        id="heure_fin"
                        v-model="form.heure_fin"
                        :class="selectClasses"
                        :disabled="!jourChoisi"
                    >
                        <option value="" disabled>Choisir</option>
                        <option
                            v-for="h in jourChoisi?.fins ?? []"
                            :key="h"
                            :value="h"
                        >
                            {{ formatHeure(h) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.heure_fin" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="motif">Motif</Label>
                <Input
                    id="motif"
                    v-model="form.motif"
                    placeholder="Saisir le motif"
                    maxlength="255"
                />
                <InputError :message="form.errors.motif" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <Button type="button" variant="outline" @click="annuler">
                    Annuler
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Envoyer
                </Button>
            </div>
        </form>

        <section>
            <h2 class="mb-3 text-lg font-bold">Mes demandes</h2>

            <p
                v-if="demandes.length === 0"
                class="rounded-2xl border bg-white px-5 py-6 text-center text-sm text-muted-foreground"
            >
                Aucune demande
            </p>

            <ul v-else class="grid gap-4">
                <li
                    v-for="d in demandes"
                    :key="d.id"
                    class="rounded-2xl border bg-white p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-lg font-bold">
                            {{ d.jour }} · {{ d.horaire }}
                        </p>
                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-sm font-semibold"
                            :class="statutsDemande[d.statut]"
                        >
                            {{ d.statutLibelle }}
                        </span>
                    </div>
                    <p class="mt-1 text-muted-foreground">{{ d.motif }}</p>
                    <p class="mt-2 text-sm text-muted-foreground/80">
                        Envoyée le {{ d.dateEnvoi }}
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
