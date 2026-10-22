<?php

namespace App\Enums;

/**
 * Type de salle : « normale » ou « sport » uniquement.
 * Utilisé par les matières (où les placer) et, à l'étape 2, par les salles.
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
