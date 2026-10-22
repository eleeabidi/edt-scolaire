<?php

namespace App\Services;

use App\Enums\Jour;
use App\Models\Contrainte;
use App\Models\Horaire;
use Illuminate\Support\Facades\DB;

/**
 * T11.5 — Génère les créneaux horaires (table horaires) à partir des règles générales (table contraintes).
 *
 * Règles utilisées (niveau vide) :
 * - journee        : « 08:00-17:00 »  début et fin de la journée (lundi → vendredi)
 * - jours          : « 1,2,3,4,5 »    jours avec une journée complète
 * - horaire_samedi : « 08:00-13:00 »  le samedi, matin seulement
 * - pause_midi     : « 12:00-13:00 »  le créneau de midi est un créneau à part
 * - recreation     : « 09:55-10:05 »  (une ligne par récréation) : pas de cours pendant la récréation
 *
 * Exemple (lundi) : 08:00→09:55, 10:05→12:00, 12:00→13:00, 13:00→14:55, 15:05→17:00.
 */
class GenerateurCreneaux
{
    /**
     * Recrée tous les créneaux. Renvoie le nombre de créneaux générés.
     */
    public function generer(): int
    {
        $journee = $this->plage($this->valeur('journee'));
        $samedi = $this->plage($this->valeur('horaire_samedi'));
        $midi = $this->plage($this->valeur('pause_midi'));
        $recreations = Contrainte::generales('recreation')->map(fn (Contrainte $r) => $this->plage($r->valeur));
        $jours = array_map('intval', explode(',', $this->valeur('jours')));

        $creneaux = [];

        foreach (Jour::cases() as $jour) {
            $plage = match (true) {
                in_array($jour->value, $jours, true) => $journee,
                $jour === Jour::Samedi => $samedi,
                default => null,
            };

            if ($plage === null) {
                continue;
            }

            foreach ($this->decouper($plage, $midi, $recreations->all()) as [$debut, $fin]) {
                $creneaux[] = [
                    'jour' => $jour->value,
                    'heure_debut' => $this->heure($debut),
                    'heure_fin' => $this->heure($fin),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($creneaux) {
            Horaire::query()->delete();
            Horaire::insert($creneaux);
        });

        return count($creneaux);
    }

    /**
     * Découpe une plage : coupure au début et à la fin de la pause de midi,
     * puis retrait des récréations.
     *
     * @param  array{int, int}  $plage
     * @param  array{int, int}  $midi
     * @param  array<int, array{int, int}>  $recreations
     * @return array<int, array{int, int}>
     */
    private function decouper(array $plage, array $midi, array $recreations): array
    {
        [$debut, $fin] = $plage;

        // 1. Les bornes de la pause de midi qui tombent dans la plage
        $bornes = array_values(array_unique(array_filter(
            [$debut, $midi[0], $midi[1], $fin],
            fn (int $minute) => $minute >= $debut && $minute <= $fin,
        )));
        sort($bornes);

        $morceaux = [];
        for ($i = 0; $i < count($bornes) - 1; $i++) {
            $morceaux[] = [$bornes[$i], $bornes[$i + 1]];
        }

        // 2. On retire les récréations
        foreach ($recreations as [$recDebut, $recFin]) {
            $resultat = [];

            foreach ($morceaux as [$a, $b]) {
                if ($recDebut > $a && $recFin < $b) {
                    $resultat[] = [$a, $recDebut];
                    $resultat[] = [$recFin, $b];
                } else {
                    $resultat[] = [$a, $b];
                }
            }

            $morceaux = $resultat;
        }

        return $morceaux;
    }

    private function valeur(string $type): string
    {
        return Contrainte::generales($type)->firstOrFail()->valeur;
    }

    /**
     * « 08:00-17:00 » → [480, 1020] (en minutes depuis minuit).
     *
     * @return array{int, int}
     */
    private function plage(string $valeur): array
    {
        [$debut, $fin] = explode('-', $valeur);

        return [$this->minutes($debut), $this->minutes($fin)];
    }

    private function minutes(string $heure): int
    {
        [$h, $m] = array_map('intval', explode(':', trim($heure)));

        return $h * 60 + $m;
    }

    private function heure(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
