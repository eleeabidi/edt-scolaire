<?php

namespace App\Http\Requests;

use App\Enums\TypeSalle;
use App\Models\Salle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T09.4 — Mettre en service / Modifier une salle : nom, type et capacité obligatoires.
 */
class SalleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Salle|null $salle */
        $salle = $this->route('salle');

        return [
            'nom' => [
                'required', 'string', 'max:255',
                Rule::unique('salles', 'nom')->where('ecole_id', $this->user()->ecole_id)->ignore($salle?->id),
            ],
            'type' => ['required', Rule::enum(TypeSalle::class)],
            'capacite' => ['required', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.unique' => 'Une salle de ce nom existe déjà dans votre école.',
        ];
    }
}
