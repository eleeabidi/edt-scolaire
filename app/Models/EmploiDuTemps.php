<?php

namespace App\Models;

use App\Enums\StatutEmploi;
use Database\Factories\EmploiDuTempsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Emploi du temps d'une école pour une année scolaire. Il contient ses séances (composition 1 — 1..*).
 *
 * @property int $id
 * @property int $ecole_id
 * @property string $annee_scolaire
 * @property StatutEmploi $statut
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('emplois_du_temps')]
#[Fillable(['ecole_id', 'annee_scolaire', 'statut'])]
class EmploiDuTemps extends Model
{
    /** @use HasFactory<EmploiDuTempsFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'brouillon',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutEmploi::class,
        ];
    }

    public function estPublie(): bool
    {
        return $this->statut === StatutEmploi::Publie;
    }

    /**
     * @return BelongsTo<Ecole, $this>
     */
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class);
    }

    /**
     * @return HasMany<Seance, $this>
     */
    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }
}
