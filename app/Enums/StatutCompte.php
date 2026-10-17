<?php

namespace App\Enums;

enum StatutCompte: string
{
    case EnAttente = 'EN_ATTENTE';
    case Actif = 'ACTIF';
    case Refuse = 'REFUSE';
    case Desactive = 'DESACTIVE';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Actif => 'Actif',
            self::Refuse => 'Refusé',
            self::Desactive => 'Désactivé',
        };
    }
}
