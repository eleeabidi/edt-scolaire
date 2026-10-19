import type { User } from './auth';

export type EcoleOption = {
    id: number;
    nom: string;
};

export type Enseignant = {
    id: number;
    nom: string;
    prenom: string;
    email: string;
    telephone: string | null;
    statut: User['statut'];
    ecole_id: number | null;
    ecole: string | null;
};

export type Ecole = {
    nom: string;
    adresse: string;
    ville: string;
    telephone: string;
};

export type Directeur = {
    id: number;
    nom: string;
    prenom: string;
    email: string;
    telephone: string | null;
    statut: User['statut'];
    motif_refus: string | null;
    date_creation: string | null;
    ecole: Ecole | null;
};

// Résultat paginé renvoyé par Laravel (->paginate())
export type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
