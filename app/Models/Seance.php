<?php

namespace App\Models;

use Database\Factories\SeanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Séance : une classe, un enseignant et une salle pendant un créneau. Elle se compose
 * d'une ou plusieurs matières (composition 1 — 1..*). L'éducation physique et l'éducation
 * technologique forment chacune une séance d'1 h à part.
 *
 * @property int $id
 * @property int $emploi_du_temps_id
 * @property int $classe_id
 * @property int $horaire_id
 * @property int $enseignant_id
 * @property int $salle_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['emploi_du_temps_id', 'classe_id', 'horaire_id', 'enseignant_id', 'salle_id'])]
class Seance extends Model
{
    /** @use HasFactory<SeanceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<EmploiDuTemps, $this>
     */
    public function emploiDuTemps(): BelongsTo
    {
        return $this->belongsTo(EmploiDuTemps::class);
    }

    /**
     * @return BelongsTo<Classe, $this>
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * @return BelongsTo<Horaire, $this>
     */
    public function horaire(): BelongsTo
    {
        return $this->belongsTo(Horaire::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    /**
     * @return BelongsTo<Salle, $this>
     */
    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    /**
     * Les matières de la séance, avec leurs heures.
     *
     * @return HasMany<SeanceMatiere, $this>
     */
    public function matieres(): HasMany
    {
        return $this->hasMany(SeanceMatiere::class);
    }
}
