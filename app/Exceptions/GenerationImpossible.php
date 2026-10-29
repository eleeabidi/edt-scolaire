<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La génération de l'emploi du temps est impossible : données incomplètes (4a)
 * ou aucune solution qui respecte toutes les règles (5a). Rien n'est enregistré.
 */
class GenerationImpossible extends RuntimeException
{
    /**
     * @param  list<string>  $causes  les causes à afficher au directeur
     */
    public function __construct(public readonly array $causes)
    {
        parent::__construct(implode(' ', $causes));
    }
}
