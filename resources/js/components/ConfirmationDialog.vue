<script setup lang="ts">
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

// Fenêtre « Êtes-vous sûr ? » réutilisable
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
            <DialogHeader>
                <DialogTitle>{{ titre }}</DialogTitle>
                <DialogDescription>{{ message }}</DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="ouvert = false"
                    >Annuler</Button
                >
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
