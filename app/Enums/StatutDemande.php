<?php

namespace App\Enums;

/**
 * État d'une demande d'indisponibilité : en attente à l'envoi, puis acceptée ou refusée par le directeur.
 */
enum StatutDemande: string
{
    case EnAttente = 'en_attente';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Acceptee => 'Acceptée',
            self::Refusee => 'Refusée',
        };
    }
}
