import { ref } from 'vue';
import type { NotificationEnseignant } from '@/types';

// Sprint 4 (US20) — État partagé par l'espace enseignant : le nombre de notifications non lues
// (pastille) et la dernière notification reçue en temps réel (affichée en haut de « Mes notifications »).
const nonLues = ref(0);
const derniere = ref<NotificationEnseignant | null>(null);

export function useNotificationsEnDirect() {
    return { nonLues, derniere };
}
