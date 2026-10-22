<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Règle nationale de l'emploi du temps.
 * niveau vide = règle générale ; niveau rempli = règle propre à ce niveau.
 *
 * @property int $id
 * @property int|null $niveau
 * @property string $type
 * @property string $description
 * @property string $valeur
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['niveau', 'type', 'description', 'valeur'])]
class Contrainte extends Model
{
    protected function casts(): array
    {
        return [
            'niveau' => 'integer',
        ];
    }

    /**
     * Les règles qui s'appliquent à un niveau : les règles générales, sauf celles
     * dont le type est redéfini pour ce niveau (la règle du niveau remplace la générale).
     *
     * @return Collection<int, Contrainte>
     */
    public static function pourNiveau(int $niveau): Collection
    {
        $regles = self::query()
            ->whereNull('niveau')
            ->orWhere('niveau', $niveau)
            ->orderBy('id')
            ->get();

        $typesDuNiveau = $regles->where('niveau', $niveau)->pluck('type')->unique();

        return $regles
            ->reject(fn (Contrainte $regle) => $regle->niveau === null && $typesDuNiveau->contains($regle->type))
            ->values();
    }

    /**
     * Les règles générales d'un type donné (ex. : les deux récréations).
     *
     * @return Collection<int, Contrainte>
     */
    public static function generales(string $type): Collection
    {
        return self::query()->whereNull('niveau')->where('type', $type)->orderBy('id')->get();
    }
}
