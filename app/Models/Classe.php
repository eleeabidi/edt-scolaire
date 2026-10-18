<?php

namespace App\Models;

use Database\Factories\ClasseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ecole_id
 * @property string $nom
 * @property string $niveau
 * @property int $effectif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ecole_id', 'nom', 'niveau', 'effectif'])]
class Classe extends Model
{
    /** @use HasFactory<ClasseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effectif' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ecole, $this>
     */
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class);
    }

    /**
     * Une classe contient 0..* groupes.
     *
     * @return HasMany<Groupe, $this>
     */
    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class);
    }

    /**
     * Les matières de la classe, avec leur volume horaire hebdomadaire.
     *
     * @return BelongsToMany<Matiere, $this>
     */
    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class)
            ->withPivot('volume_horaire_hebdo')
            ->withTimestamps();
    }
}
