<script setup lang="ts">
import { Check, Power, Trash2, TriangleAlert } from '@lucide/vue';
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

// Fenêtre F3 « Supprimer ce compte ? » (réutilisable)
// icone : corbeille (suppression), alerte (mise hors service) ou marche (mise en service)
// danger : bouton rouge (par défaut) ou bouton principal violet
withDefaults(
    defineProps<{
        titre: string;
        message: string;
        libelleConfirmer?: string;
        libelleAnnuler?: string;
        enCours?: boolean;
        icone?: 'supprimer' | 'alerte' | 'service' | 'valider';
        danger?: boolean;
    }>(),
    {
        libelleConfirmer: 'Confirmer',
        libelleAnnuler: 'Annuler',
        enCours: false,
        icone: 'supprimer',
        danger: true,
    },
);

const ouvert = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ confirmer: [] }>();
</script>

<template>
    <Dialog v-model:open="ouvert">
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl"
                :class="
                    danger
                        ? 'bg-erreur/15 text-erreur'
                        : 'bg-violet-clair text-primary'
                "
            >
                <TriangleAlert v-if="icone === 'alerte'" class="size-5" />
                <Power v-else-if="icone === 'service'" class="size-5" />
                <Check v-else-if="icone === 'valider'" class="size-5" />
                <Trash2 v-else class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle>{{ titre }}</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    {{ message }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-3">
                <Button variant="outline" @click="ouvert = false">
                    {{ libelleAnnuler }}
                </Button>
                <Button
                    :variant="danger ? 'destructive' : 'default'"
                    :disabled="enCours"
                    @click="emit('confirmer')"
                >
                    <Spinner v-if="enCours" />
                    {{ libelleConfirmer }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
