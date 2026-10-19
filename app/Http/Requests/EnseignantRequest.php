<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du formulaire Ajouter / Modifier un enseignant.
 */
class EnseignantRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $enseignant */
        $enseignant = $this->route('enseignant');

        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($enseignant?->id)],
            'telephone' => ['nullable', 'string', 'max:20'],
            // Seul l'Admin choisit l'école ; le directeur ajoute toujours dans la sienne
            'ecole_id' => $this->user()->isAdmin()
                ? ['required', 'integer', Rule::exists('ecoles', 'id')]
                : ['exclude'],
        ];
    }
}
