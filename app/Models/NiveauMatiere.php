<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Volume hebdomadaire d'une matière pour un niveau (en minutes).
 *
 * @property int $id
 * @property int $niveau
 * @property int $matiere_id
 * @property int $minutes_par_semaine
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('niveau_matiere')]
#[Fillable(['niveau', 'matiere_id', 'minutes_par_semaine'])]
class NiveauMatiere extends Model
{
    protected function casts(): array
    {
        return [
            'niveau' => 'integer',
            'minutes_par_semaine' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Matiere, $this>
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }
}
