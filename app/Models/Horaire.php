<?php

namespace App\Models;

use App\Enums\Jour;
use Database\Factories\HoraireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Créneau horaire d'une école (ex. : lundi 08:00–09:00).
 *
 * @property int $id
 * @property int $ecole_id
 * @property Jour $jour
 * @property string $heure_debut
 * @property string $heure_fin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ecole_id', 'jour', 'heure_debut', 'heure_fin'])]
class Horaire extends Model
{
    /** @use HasFactory<HoraireFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'jour' => Jour::class,
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
