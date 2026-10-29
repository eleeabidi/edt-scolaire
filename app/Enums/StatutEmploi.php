<?php

namespace App\Enums;

/**
 * Statut d'un emploi du temps : brouillon (visible du directeur seulement) ou publié (visible des enseignants).
 */
enum StatutEmploi: string
{
    case Brouillon = 'brouillon';
    case Publie = 'publie';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Publie => 'Publié',
        };
    }
}
