<?php

namespace App\Enums;

/**
 * Type de salle : cours (par défaut), sport ou technologie.
 * Utilisé par les matières (où les placer) et par les salles.
 */
enum TypeSalle: string
{
    case Cours = 'cours';
    case Sport = 'sport';
    case Technologie = 'technologie';

    public function label(): string
    {
        return match ($this) {
            self::Cours => 'Cours',
            self::Sport => 'Sport',
            self::Technologie => 'Technologie',
        };
    }
}
