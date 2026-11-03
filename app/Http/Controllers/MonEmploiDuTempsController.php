<?php

namespace App\Http\Controllers;

use App\Enums\StatutEmploi;
use App\Services\GrilleEmploiDuTemps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US19 — « Mon emploi du temps » (enseignant) : ses séances dans l'emploi du temps publié de son école
 * pour l'année scolaire en cours, en vue semaine ou en vue jour (T19.1 à T19.3).
 * T17.3 — Un emploi du temps au statut brouillon reste caché aux enseignants.
 */
class MonEmploiDuTempsController extends Controller
{
    /**
     * ?vue=semaine (par défaut) ou ?vue=jour&jour=1 (lundi) à 6 (samedi).
     */
    public function consulterMonEmploiDuTemps(Request $request): Response
    {
        $enseignant = $request->user();
        $vue = $request->query('vue') === 'jour' ? 'jour' : 'semaine';

        // Jour choisi, sinon aujourd'hui (le dimanche : lundi)
        $jour = $request->integer('jour');
        if ($jour < 1 || $jour > 6) {
            $jour = now()->dayOfWeekIso === 7 ? 1 : now()->dayOfWeekIso;
        }

        $emploi = $enseignant->ecole?->emploisDuTemps()
            ->where('annee_scolaire', EmploiDuTempsController::anneesScolaires()[0])
            ->where('statut', StatutEmploi::Publie)
            ->first();

        $proprietes = ['prenom' => $enseignant->prenom, 'vue' => $vue, 'jour' => $jour];

        // 2a : aucun emploi du temps publié pour l'année scolaire en cours
        if ($emploi === null) {
            return Inertia::render('mon-emploi-du-temps/Index', [...$proprietes, 'emploi' => null]);
        }

        $seances = GrilleEmploiDuTemps::seances($emploi, 'enseignant', $enseignant->id);

        return Inertia::render('mon-emploi-du-temps/Index', [
            ...$proprietes,
            'emploi' => ['annee' => $emploi->annee_scolaire, 'ecole' => $emploi->ecole->nom],
            ...GrilleEmploiDuTemps::structure(),
            'seances' => $seances,
            'totalMinutes' => GrilleEmploiDuTemps::totalMinutes($seances),
        ]);
    }
}
