<?php

namespace App\Models;

use App\Enums\StatutEmploi;
use App\Services\PlanningEmploiDuTemps;
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
     * T14.1 — Les conflits de l'emploi du temps, avec leur cause et une solution proposée.
     *
     * @return list<array<string, mixed>>
     */
    public function detecterConflits(): array
    {
        return (new PlanningEmploiDuTemps($this))->detecterConflits();
    }

    /**
     * T13.6 — La séance peut-elle aller sur ce créneau (et dans cette salle) ? Renvoie la cause du refus, ou null.
     */
    public function verifierDisponibilite(Seance $seance, Horaire $horaire, ?Salle $salle = null): ?string
    {
        return (new PlanningEmploiDuTemps($this))->verifierDeplacement($seance, $horaire, $salle)['cause'];
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
