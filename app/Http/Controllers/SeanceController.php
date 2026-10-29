<?php

namespace App\Http\Controllers;

use App\Models\Horaire;
use App\Models\Salle;
use App\Models\Seance;
use App\Services\PlanningEmploiDuTemps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * US16 — Modifier une séance : la déplacer sur un autre créneau (glisser-déposer) ou changer sa salle.
 * Chaque modification est vérifiée (T16.2) ; en cas de conflit, elle est refusée avec la cause (T16.3).
 */
class SeanceController extends Controller
{
    /**
     * T16.1 / T16.4 — Déplacer la séance sur un autre créneau.
     */
    public function deplacer(Request $request, Seance $seance): RedirectResponse
    {
        $this->verifierEcole($request, $seance);

        $request->validate(['horaire_id' => ['required', 'integer', Rule::exists('horaires', 'id')]]);
        $horaire = Horaire::findOrFail($request->integer('horaire_id'));

        $verification = (new PlanningEmploiDuTemps($seance->emploiDuTemps))->verifierDeplacement($seance, $horaire);

        // 2a : conflit → refus avec la cause ; la séance reste à sa place
        if ($verification['cause'] !== null) {
            throw ValidationException::withMessages(['deplacement' => $verification['cause'].' La séance reste à sa place.']);
        }

        $seance->deplacer($horaire, $verification['decalage']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La séance a été déplacée.']);

        return back();
    }

    /**
     * 1a — Changer la salle de la séance (sur le même créneau).
     */
    public function changerSalle(Request $request, Seance $seance): RedirectResponse
    {
        $this->verifierEcole($request, $seance);

        $request->validate([
            'salle_id' => ['required', 'integer', Rule::exists('salles', 'id')->where('ecole_id', $request->user()->ecole_id)],
        ], [
            'salle_id.exists' => "Cette salle n'existe pas dans votre école.",
        ]);
        $salle = Salle::findOrFail($request->integer('salle_id'));

        if ($salle->id !== $seance->salle_id) {
            $verification = (new PlanningEmploiDuTemps($seance->emploiDuTemps))->verifierDeplacement($seance, $seance->horaire, $salle, true);

            if ($verification['cause'] !== null) {
                throw ValidationException::withMessages(['salle_id' => $verification['cause']]);
            }

            $seance->changerSalle($salle);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La salle de la séance a été changée.']);

        return back();
    }

    /**
     * Un directeur ne modifie que l'emploi du temps de son école.
     */
    private function verifierEcole(Request $request, Seance $seance): void
    {
        abort_unless($seance->emploiDuTemps->ecole_id === $request->user()->ecole_id, 403);
    }
}
