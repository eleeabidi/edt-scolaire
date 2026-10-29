<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une matière d'une séance, avec son heure de début et de fin (au moins 30 min).
 *
 * @property int $id
 * @property int $seance_id
 * @property int $matiere_id
 * @property string $heure_debut
 * @property string $heure_fin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('seance_matiere')]
#[Fillable(['seance_id', 'matiere_id', 'heure_debut', 'heure_fin'])]
class SeanceMatiere extends Model
{
    /**
     * @return BelongsTo<Seance, $this>
     */
    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    /**
     * @return BelongsTo<Matiere, $this>
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }
}
