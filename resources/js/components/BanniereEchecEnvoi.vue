<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { MailWarning } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

// Ajouter un enseignant 6a : le compte est créé mais l'email n'est pas parti.
// Le système signale l'échec et propose de renvoyer l'email.
const props = defineProps<{
    // Adresse de l'action « renvoyer » pour un compte donné
    urlRenvoi: (id: number) => string;
}>();

const page = usePage();
const echec = computed(() => page.props.echecEnvoi);
const envoiEnCours = ref(false);

function renvoyer() {
    if (!echec.value) {
        return;
    }

    router.post(
        props.urlRenvoi(echec.value.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (envoiEnCours.value = true),
            onFinish: () => (envoiEnCours.value = false),
        },
    );
}
</script>

<template>
    <div
        v-if="echec"
        class="mb-6 flex flex-col gap-3 rounded-xl border border-crayon/60 bg-crayon/15 px-5 py-4 sm:flex-row sm:items-center"
        role="alert"
    >
        <MailWarning class="size-5 shrink-0 text-[#8A6100]" />
        <p class="flex-1 text-sm">
            Le compte de <strong>{{ echec.nom }}</strong> a été créé, mais
            l'email avec ses identifiants n'a pas pu être envoyé.
        </p>
        <Button size="sm" :disabled="envoiEnCours" @click="renvoyer">
            <Spinner v-if="envoiEnCours" />
            Renvoyer l'email
        </Button>
    </div>
</template>
