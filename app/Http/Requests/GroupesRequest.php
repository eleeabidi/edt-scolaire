<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Fenêtre F8 — Groupes d'une classe : la liste complète des groupes est enregistrée en une fois.
 */
class GroupesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'groupes' => ['present', 'array'],
            'groupes.*.id' => ['nullable', 'integer'],
            'groupes.*.nom' => ['required', 'string', 'max:255', 'distinct'],
            'groupes.*.effectif' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * La liste des groupes validée.
     *
     * @return array<int, array{id?: int|null, nom: string, effectif: int}>
     */
    public function groupes(): array
    {
        return $this->validated('groupes');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'groupes.*.nom.distinct' => 'Deux groupes ne peuvent pas avoir le même nom.',
        ];
    }
}
