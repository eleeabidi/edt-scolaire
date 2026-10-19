<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Règles de validation des informations d'une école (inscription et gestion des directeurs).
 */
trait EcoleValidationRules
{
    /**
     * S'inscrire 5d / Ajouter un directeur 4c : une école du même nom ne doit pas
     * déjà exister dans la même ville.
     *
     * @param  int|null  $ecoleIgnoree  l'école modifiée (pour ne pas se signaler elle-même)
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function ecoleRules(?string $ville, ?int $ecoleIgnoree = null): array
    {
        return [
            'ecole_nom' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ecoles', 'nom')
                    ->where(fn (Builder $query) => $query->where('ville', $ville))
                    ->ignore($ecoleIgnoree),
            ],
            'ecole_adresse' => ['required', 'string', 'max:255'],
            'ecole_ville' => ['required', 'string', 'max:255'],
            'ecole_telephone' => ['required', 'string', 'max:20'],
        ];
    }
}
