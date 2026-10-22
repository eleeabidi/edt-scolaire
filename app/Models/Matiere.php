<?php

namespace App\Models;

use App\Enums\TypeSalle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Matière du programme national (commune à toutes les écoles, consultation seule).
 *
 * @property int $id
 * @property string $nom
 * @property TypeSalle $type_salle
 * @property bool $autorisee_midi
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nom', 'type_salle', 'autorisee_midi'])]
class Matiere extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type_salle' => 'normale',
        'autorisee_midi' => false,
    ];

    protected function casts(): array
    {
        return [
            'type_salle' => TypeSalle::class,
            'autorisee_midi' => 'boolean',
        ];
    }

    /**
     * Les volumes hebdomadaires de la matière, niveau par niveau.
     *
     * @return HasMany<NiveauMatiere, $this>
     */
    public function volumes(): HasMany
    {
        return $this->hasMany(NiveauMatiere::class);
    }
}
