<?php

namespace App\Enums;

/**
 * Nature d'une notification reçue par un enseignant : publication de l'emploi du temps
 * ou modification d'une de ses séances.
 */
enum TypeNotification: string
{
    case Publication = 'publication';
    case Modification = 'modification';

    /**
     * Titre affiché dans « Mes notifications ».
     */
    public function label(): string
    {
        return match ($this) {
            self::Publication => 'Emploi du temps publié',
            self::Modification => 'Séance modifiée',
        };
    }
}
