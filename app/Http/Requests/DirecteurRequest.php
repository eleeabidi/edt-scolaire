<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du formulaire Ajouter / Modifier un directeur (avec son école).
 */
class DirecteurRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $directeur */
        $directeur = $this->route('directeur');

        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($directeur?->id)],
            'telephone' => ['nullable', 'string', 'max:20'],
            'ecole_nom' => ['required', 'string', 'max:255'],
            'ecole_adresse' => ['required', 'string', 'max:255'],
            'ecole_ville' => ['required', 'string', 'max:255'],
            'ecole_telephone' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prenom' => 'prénom',
            'telephone' => 'téléphone',
            'ecole_nom' => "nom de l'école",
            'ecole_adresse' => "adresse de l'école",
            'ecole_ville' => "ville de l'école",
            'ecole_telephone' => "téléphone de l'école",
        ];
    }

    /**
     * Les informations du directeur.
     *
     * @return array<string, mixed>
     */
    public function directeur(): array
    {
        return $this->safe()->only(['nom', 'prenom', 'email', 'telephone']);
    }

    /**
     * Les informations de l'école (sans le préfixe « ecole_ »).
     *
     * @return array<string, mixed>
     */
    public function ecole(): array
    {
        return [
            'nom' => $this->validated('ecole_nom'),
            'adresse' => $this->validated('ecole_adresse'),
            'ville' => $this->validated('ecole_ville'),
            'telephone' => $this->validated('ecole_telephone'),
        ];
    }
}
