<?php

namespace App\Http\Requests;

use App\Models\Classe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T07.5 — Ajouter / Modifier une classe.
 * cours_samedi n'est pris en compte qu'en 3e année (règle appliquée par le modèle Classe).
 */
class ClasseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Classe|null $classe */
        $classe = $this->route('classe');

        return [
            'nom' => [
                'required', 'string', 'max:255',
                Rule::unique('classes', 'nom')->where('ecole_id', $this->user()->ecole_id)->ignore($classe?->id),
            ],
            'niveau' => ['required', 'integer', 'between:1,6'],
            'effectif' => ['required', 'integer', 'min:1', 'max:99'],
            'cours_samedi' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.unique' => 'Une classe de ce nom existe déjà dans votre école.',
        ];
    }
}
