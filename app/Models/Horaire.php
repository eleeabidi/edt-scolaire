<?php

namespace App\Models;

use App\Enums\Jour;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Créneau horaire national (ex. : lundi 08:00 → 09:55), généré à partir des règles.
 *
 * @property int $id
 * @property Jour $jour
 * @property string $heure_debut
 * @property string $heure_fin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['jour', 'heure_debut', 'heure_fin'])]
class Horaire extends Model
{
    protected function casts(): array
    {
        return [
            'jour' => Jour::class,
        ];
    }
}
