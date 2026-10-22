<?php

namespace App\Models;

use Database\Factories\ClasseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Classe d'une école (niveau de la 1re à la 6e année).
 *
 * @property int $id
 * @property int $ecole_id
 * @property string $nom
 * @property int $niveau
 * @property int $effectif
 * @property bool $cours_samedi
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ecole_id', 'nom', 'niveau', 'effectif', 'cours_samedi'])]
class Classe extends Model
{
    /** @use HasFactory<ClasseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'niveau' => 'integer',
            'effectif' => 'integer',
            'cours_samedi' => 'boolean',
        ];
    }

    /**
     * Le samedi est imposé selon le niveau, sauf en 3e année où le directeur choisit.
     */
    protected static function booted(): void
    {
        static::saving(function (Classe $classe) {
            $classe->cours_samedi = self::coursSamediPourNiveau($classe->niveau, (bool) $classe->cours_samedi);
        });
    }

    /**
     * Toujours non en 1re et 2e année, toujours oui de la 4e à la 6e, choix du directeur en 3e.
     */
    public static function coursSamediPourNiveau(int $niveau, bool $choix): bool
    {
        return match (true) {
            $niveau <= 2 => false,
            $niveau === 3 => $choix,
            default => true,
        };
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
     * Les affectations de la classe (supprimées avec elle).
     *
     * @return HasMany<Affectation, $this>
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }
}
