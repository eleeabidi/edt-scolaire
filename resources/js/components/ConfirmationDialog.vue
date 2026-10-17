<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
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
withDefaults(
    defineProps<{
        titre: string;
        message: string;
        libelleConfirmer?: string;
        enCours?: boolean;
    }>(),
    { libelleConfirmer: 'Confirmer', enCours: false },
);

const ouvert = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ confirmer: [] }>();
</script>

<template>
    <Dialog v-model:open="ouvert">
        <DialogContent>
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-erreur/15 text-erreur"
            >
                <Trash2 class="size-5" />
            </div>
            <DialogHeader>
                <DialogTitle>{{ titre }}</DialogTitle>
                <DialogDescription class="leading-relaxed">
                    {{ message }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-3">
                <Button variant="outline" @click="ouvert = false">
                    Annuler
                </Button>
                <Button
                    variant="destructive"
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
