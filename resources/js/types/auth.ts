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
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
