<?php

namespace App\Models;

use App\Enums\TypeSalle;
use Database\Factories\SalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Salle d'une école : de cours, de sport ou de technologie ; jamais supprimée, mise hors service.
 *
 * @property int $id
 * @property int $ecole_id
 * @property string $nom
 * @property TypeSalle $type
 * @property int $capacite
 * @property bool $en_service
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ecole_id', 'nom', 'type', 'capacite', 'en_service'])]
class Salle extends Model
{
    /** @use HasFactory<SalleFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'cours',
        'en_service' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeSalle::class,
            'capacite' => 'integer',
            'en_service' => 'boolean',
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
     * Les séances qui ont lieu dans la salle.
     *
     * @return HasMany<Seance, $this>
     */
    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }
}
