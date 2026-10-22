<?php

namespace App\Models;

use App\Enums\TypeSalle;
use Database\Factories\SalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Salle d'une école : normale ou de sport.
 *
 * @property int $id
 * @property int $ecole_id
 * @property string $nom
 * @property TypeSalle $type
 * @property int $capacite
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ecole_id', 'nom', 'type', 'capacite'])]
class Salle extends Model
{
    /** @use HasFactory<SalleFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'normale',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeSalle::class,
            'capacite' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ecole, $this>
     */
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class);
    }
}
