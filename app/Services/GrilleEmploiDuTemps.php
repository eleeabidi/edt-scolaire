<?php

namespace App\Services;

use App\Enums\Jour;
use App\Http\Controllers\EmploiDuTempsController;
use App\Models\EmploiDuTemps;
use App\Models\Horaire;
use App\Models\Seance;
use App\Models\SeanceMatiere;

/**
 * T15.1 / T15.2 — Données de la grille hebdomadaire d'un emploi du temps, filtrée par classe,
 * par enseignant ou par salle : les créneaux (lignes), les jours (colonnes) et les séances.
 */
class GrilleEmploiDuTemps
{
    /** Filtres possibles et colonne correspondante dans la table seances. */
    public const FILTRES = [
        'classe' => 'classe_id',
        'enseignant' => 'enseignant_id',
        'salle' => 'salle_id',
    ];

    /**
     * Les lignes de la grille (un créneau par ligne) et les cases qui existent (« jour-HH:MM ») ;
     * le samedi après-midi et le samedi de 12 h à 13 h sont grisés (pas de cours).
     *
     * @return array{lignes: list<array{debut: string, fin: string, midi: bool}>, cases: list<string>, horaires: array<string, int>}
     */
    public static function structure(): array
    {
        $horaires = Horaire::orderBy('jour')->orderBy('heure_debut')->get();
        $lignes = [];
        $cases = [];
        $ids = [];

        foreach ($horaires as $horaire) {
            $debut = substr($horaire->heure_debut, 0, 5);
            $fin = substr($horaire->heure_fin, 0, 5);
            $midi = $debut === '12:00';
            $lignes[$debut] = ['debut' => $debut, 'fin' => $fin, 'midi' => $midi];

            if (! ($midi && $horaire->jour === Jour::Samedi)) {
                $cases[] = $horaire->jour->value.'-'.$debut;
                $ids[$horaire->jour->value.'-'.$debut] = $horaire->id;
            }
        }

        ksort($lignes);

        return ['lignes' => array_values($lignes), 'cases' => $cases, 'horaires' => $ids];
    }

    /**
     * Les séances de l'emploi du temps pour une classe, un enseignant ou une salle.
     *
     * @param  key-of<self::FILTRES>  $par
     * @return list<array<string, mixed>>
     */
    public static function seances(EmploiDuTemps $emploi, string $par, int $id): array
    {
        return array_values($emploi->seances()
            ->where(self::FILTRES[$par], $id)
            ->with(['classe', 'enseignant', 'salle', 'horaire', 'matieres.matiere'])
            ->get()
            ->map(fn (Seance $seance) => self::seance($seance))
            ->sortBy(['case', 'debut'])
            ->all());
    }

    /**
     * Total des heures (comptées comme dans le décret) d'une liste de séances.
     *
     * @param  list<array<string, mixed>>  $seances
     */
    public static function totalMinutes(array $seances): int
    {
        return (int) collect($seances)->sum('minutes');
    }

    /**
     * @return array<string, mixed>
     */
    private static function seance(Seance $seance): array
    {
        $matieres = $seance->matieres->sortBy('heure_debut')->values();
        $debutCreneau = substr($seance->horaire->heure_debut, 0, 5);

        return [
            'id' => $seance->id,
            'case' => $seance->horaire->jour->value.'-'.$debutCreneau,
            'jour' => $seance->horaire->jour->value,
            'debut' => substr((string) $matieres->first()?->heure_debut, 0, 5),
            'fin' => substr((string) $matieres->last()?->heure_fin, 0, 5),
            'classe' => $seance->classe->nom,
            'enseignant' => $seance->enseignant->name,
            'salle' => $seance->salle->nom,
            'typeSalle' => $seance->salle->type->value,
            'minutes' => $matieres->sum(fn (SeanceMatiere $m) => EmploiDuTempsController::minutesDecret($m->heure_debut, $m->heure_fin)),
            'matieres' => $matieres->map(fn (SeanceMatiere $m) => [
                'nom' => $m->matiere->nom,
                'debut' => substr($m->heure_debut, 0, 5),
                'fin' => substr($m->heure_fin, 0, 5),
            ])->all(),
        ];
    }
}
