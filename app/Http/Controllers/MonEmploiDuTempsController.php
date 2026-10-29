<?php

namespace App\Http\Controllers;

use App\Enums\StatutEmploi;
use App\Services\GrilleEmploiDuTemps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Mon emploi du temps » (enseignant) : ses séances de la semaine, en lecture seule.
 * T17.3 — Seul un emploi du temps publié est visible : un brouillon reste caché aux enseignants.
 */
class MonEmploiDuTempsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $enseignant = $request->user();
        $annees = EmploiDuTempsController::anneesScolaires();

        // L'emploi du temps publié de l'année en cours, sinon le plus récent publié
        $emploi = $enseignant->ecole?->emploisDuTemps()
            ->where('statut', StatutEmploi::Publie)
            ->orderByRaw('annee_scolaire = ? desc', [$annees[0]])
            ->orderByDesc('annee_scolaire')
            ->first();

        if ($emploi === null) {
            return Inertia::render('mon-emploi-du-temps/Index', ['emploi' => null]);
        }

        $seances = GrilleEmploiDuTemps::seances($emploi, 'enseignant', $enseignant->id);

        return Inertia::render('mon-emploi-du-temps/Index', [
            'emploi' => ['annee' => $emploi->annee_scolaire, 'ecole' => $emploi->ecole->nom],
            ...GrilleEmploiDuTemps::structure(),
            'seances' => $seances,
            'totalMinutes' => GrilleEmploiDuTemps::totalMinutes($seances),
        ]);
    }
}
