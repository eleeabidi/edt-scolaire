<?php

namespace App\Models;

use App\Enums\Jour;
use Database\Factories\DisponibiliteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Plage où un enseignant est disponible (ex. : lundi 08:00–12:00).
 *
 * @property int $id
 * @property int $enseignant_id
 * @property Jour $jour
 * @property string $heure_debut
 * @property string $heure_fin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['enseignant_id', 'jour', 'heure_debut', 'heure_fin'])]
class Disponibilite extends Model
{
    /** @use HasFactory<DisponibiliteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'jour' => Jour::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
