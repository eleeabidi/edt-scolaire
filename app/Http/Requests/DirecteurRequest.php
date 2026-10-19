<?php

namespace App\Http\Requests;

use App\Concerns\EcoleValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du formulaire Ajouter / Modifier un directeur (avec son école).
 */
class DirecteurRequest extends FormRequest
{
    use EcoleValidationRules;

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
            // Ajouter un directeur 4c : pas deux écoles du même nom dans la même ville
            ...$this->ecoleRules($this->input('ecole_ville'), $directeur?->ecole_id),
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
