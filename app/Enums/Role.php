<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Directeur = 'directeur';
    case Enseignant = 'enseignant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Directeur => 'Directeur',
            self::Enseignant => 'Enseignant',
        };
    }
}
