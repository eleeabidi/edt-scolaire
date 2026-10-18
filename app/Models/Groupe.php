<?php

namespace App\Models;

use Database\Factories\GroupeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $classe_id
 * @property string $nom
 * @property int $effectif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['classe_id', 'nom', 'effectif'])]
class Groupe extends Model
{
    /** @use HasFactory<GroupeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effectif' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Classe, $this>
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }
}
