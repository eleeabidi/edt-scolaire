<?php

namespace App\Models;

use App\Enums\TypeSalle;
use Database\Factories\MatiereFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ecole_id
 * @property string $nom
 * @property int $duree_seance
 * @property TypeSalle $type_salle
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ecole_id', 'nom', 'duree_seance', 'type_salle'])]
class Matiere extends Model
{
    /** @use HasFactory<MatiereFactory> */
    use HasFactory;

    /**
     * Valeurs par défaut (comme dans la migration).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'duree_seance' => 60,
        'type_salle' => 'normale',
    ];

    protected function casts(): array
    {
        return [
            'duree_seance' => 'integer',
            'type_salle' => TypeSalle::class,
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
     * Les classes qui suivent cette matière, avec le volume horaire hebdomadaire.
     *
     * @return BelongsToMany<Classe, $this>
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class)
            ->withPivot('volume_horaire_hebdo')
            ->withTimestamps();
    }

    /**
     * Les enseignants qui peuvent enseigner cette matière.
     *
     * @return BelongsToMany<User, $this>
     */
    public function enseignants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enseignant_matiere', 'matiere_id', 'enseignant_id')
            ->withTimestamps();
    }
}
