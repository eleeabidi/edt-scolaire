<?php

namespace App\Models;

use App\Enums\Jour;
use App\Support\CreneauHoraire;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Créneau où un enseignant est disponible (ex. : lundi 08:00 → 09:55).
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
    protected function casts(): array
    {
        return [
            'jour' => Jour::class,
        ];
    }

    /**
     * Les créneaux de l'école (table horaires), avec les heures qu'ils comptent :
     * 08:00 → 09:55 compte 2 h (la récréation n'est pas déduite), 12:00 → 13:00 compte 1 h.
     *
     * @return list<array{cle: string, jour: int, debut: string, fin: string, minutes: int}>
     */
    public static function creneaux(): array
    {
        return array_values(Horaire::query()
            ->orderBy('jour')
            ->orderBy('heure_debut')
            ->get()
            ->map(function (Horaire $h) {
                $debut = substr($h->heure_debut, 0, 5);
                $fin = substr($h->heure_fin, 0, 5);

                return [
                    'cle' => "{$h->jour->value}-{$debut}",
                    'jour' => $h->jour->value,
                    'debut' => $debut,
                    'fin' => $fin,
                    'minutes' => (int) round((CreneauHoraire::minutes($fin) - CreneauHoraire::minutes($debut)) / 60) * 60,
                ];
            })
            ->all());
    }

    /**
     * Les créneaux disponibles d'un enseignant (« jour-HH:MM »), ou null s'il n'a aucune ligne (disponible partout).
     *
     * @return list<string>|null
     */
    public static function clesDisponibles(User $enseignant): ?array
    {
        $lignes = self::where('enseignant_id', $enseignant->id)->get();

        if ($lignes->isEmpty()) {
            return null;
        }

        return array_values($lignes->map(fn (Disponibilite $d) => "{$d->jour->value}-".substr($d->heure_debut, 0, 5))->all());
    }

    /**
     * Enregistre les disponibilités d'un enseignant : tous les créneaux sauf les indisponibles
     * (aucun créneau indisponible = disponible partout = aucune ligne).
     *
     * @param  list<string>  $indisponibles  clés « jour-HH:MM »
     */
    public static function enregistrer(User $enseignant, array $indisponibles): void
    {
        DB::transaction(function () use ($enseignant, $indisponibles) {
            $enseignant->disponibilites()->delete();

            if ($indisponibles === []) {
                return;
            }

            $disponibles = array_filter(self::creneaux(), fn (array $c) => ! in_array($c['cle'], $indisponibles, true));
            $enseignant->disponibilites()->createMany(array_map(fn (array $c) => [
                'jour' => $c['jour'],
                'heure_debut' => $c['debut'],
                'heure_fin' => $c['fin'],
            ], array_values($disponibles)));
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
