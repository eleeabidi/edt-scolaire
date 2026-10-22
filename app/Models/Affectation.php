<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Affectation : un enseignant enseigne une matière dans une classe.
 *
 * @property int $id
 * @property int $enseignant_id
 * @property int $classe_id
 * @property int $matiere_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['enseignant_id', 'classe_id', 'matiere_id'])]
class Affectation extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    /**
     * @return BelongsTo<Classe, $this>
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * @return BelongsTo<Matiere, $this>
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    /**
     * Volume du programme (en minutes) de cette matière pour le niveau de la classe.
     */
    public static function minutesDuProgramme(Classe $classe, int $matiereId): int
    {
        return (int) NiveauMatiere::where('niveau', $classe->niveau)
            ->where('matiere_id', $matiereId)
            ->value('minutes_par_semaine');
    }

    /**
     * Total des minutes affectées à un enseignant (volume du niveau de chaque classe).
     */
    public static function minutesPourEnseignant(int $enseignantId): int
    {
        return (int) self::query()
            ->join('classes', 'classes.id', '=', 'affectations.classe_id')
            ->join('niveau_matiere', function ($join) {
                $join->on('niveau_matiere.niveau', '=', 'classes.niveau')
                    ->on('niveau_matiere.matiere_id', '=', 'affectations.matiere_id');
            })
            ->where('affectations.enseignant_id', $enseignantId)
            ->sum('niveau_matiere.minutes_par_semaine');
    }
}
