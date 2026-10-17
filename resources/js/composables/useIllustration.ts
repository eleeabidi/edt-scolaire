import { ref } from 'vue';

type Illustration = { titre: string; texte: string };

// Permet à une page (ex. : l'inscription en 2 étapes) de changer le texte
// de l'illustration affichée par AuthLayout. null = textes par défaut de la page.
const illustration = ref<Illustration | null>(null);

export function useIllustration() {
    return illustration;
}
