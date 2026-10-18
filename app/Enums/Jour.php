<?php

namespace App\Enums;

/**
 * Jours de la semaine (numérotation ISO : 1 = lundi).
 * Utilisé par les disponibilités des enseignants et les horaires de l'école.
 */
enum Jour: int
{
    case Lundi = 1;
    case Mardi = 2;
    case Mercredi = 3;
    case Jeudi = 4;
    case Vendredi = 5;
    case Samedi = 6;

    public function label(): string
    {
        return match ($this) {
            self::Lundi => 'Lundi',
            self::Mardi => 'Mardi',
            self::Mercredi => 'Mercredi',
            self::Jeudi => 'Jeudi',
            self::Vendredi => 'Vendredi',
            self::Samedi => 'Samedi',
        };
    }
}
