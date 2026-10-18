<?php

namespace App\Enums;

/**
 * Type de salle : utilisé par les salles (leur type) et par les matières
 * (le type de salle où la matière doit être placée, pour la génération).
 */
enum TypeSalle: string
{
    case Normale = 'normale';
    case Sport = 'sport';

    public function label(): string
    {
        return match ($this) {
            self::Normale => 'Normale',
            self::Sport => 'Sport',
        };
    }
}
