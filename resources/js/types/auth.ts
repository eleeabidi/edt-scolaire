export type User = {
    id: number;
    nom: string;
    prenom: string;
    name: string;
    email: string;
    telephone: string | null;
    role: 'admin' | 'directeur' | 'enseignant';
    statut: 'EN_ATTENTE' | 'ACTIF' | 'REFUSE' | 'DESACTIVE';
    ecole_id: number | null;
    specialite: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
