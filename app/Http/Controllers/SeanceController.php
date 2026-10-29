<?php

namespace App\Http\Controllers;

use App\Models\Horaire;
use App\Models\Salle;
use App\Models\Seance;
use App\Notifications\SeanceModifieeNotification;
use App\Services\PlanningEmploiDuTemps;
use App\Support\CreneauHoraire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

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

        $avant = $this->quand($seance);
        $seance->deplacer($horaire, $verification['decalage']);

        // 3a : emploi du temps publié → l'enseignant de la séance est prévenu
        $this->prevenir($seance, 'La séance a été déplacée.', "Votre séance de {$this->matieres($seance)} en {$seance->classe->nom} a été déplacée du {$avant} au {$this->quand($seance)}.");

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
            $this->prevenir($seance, 'La salle de la séance a été changée.', "Votre séance de {$this->matieres($seance)} en {$seance->classe->nom} du {$this->quand($seance)} a lieu désormais dans la salle {$salle->nom}.");

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La salle de la séance a été changée.']);

        return back();
    }

    /**
     * Message de succès ; si l'emploi du temps est publié, email à l'enseignant de la séance (T17.4).
     */
    private function prevenir(Seance $seance, string $succes, string $modification): void
    {
        if (! $seance->emploiDuTemps->estPublie()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $succes]);

            return;
        }

        $enseignant = $seance->enseignant()->firstOrFail();

        try {
            $enseignant->notify(new SeanceModifieeNotification($modification));
            Inertia::flash('toast', ['type' => 'success', 'message' => "{$succes} {$enseignant->name} a été prévenu(e) par email."]);
        } catch (Throwable $e) {
            report($e);
            Inertia::flash('toast', ['type' => 'warning', 'message' => "{$succes} Mais l'email n'a pas pu être envoyé à {$enseignant->name}."]);
        }
    }

    /**
     * « lundi 8 h – 9 h 55 »
     */
    private function quand(Seance $seance): string
    {
        $seance->load(['horaire', 'matieres']);

        return mb_strtolower($seance->horaire()->firstOrFail()->jour->label()).' '
            .CreneauHoraire::lisible(CreneauHoraire::minutes((string) $seance->matieres->min('heure_debut'))).' – '
            .CreneauHoraire::lisible(CreneauHoraire::minutes((string) $seance->matieres->max('heure_fin')));
    }

    /**
     * « Français » ou « Mathématiques, Éveil scientifique »
     */
    private function matieres(Seance $seance): string
    {
        return $seance->matieres()->with('matiere')->orderBy('heure_debut')->get()->map(fn ($m) => $m->matiere->nom)->join(', ');
    }

    /**
     * Un directeur ne modifie que l'emploi du temps de son école.
     */
    private function verifierEcole(Request $request, Seance $seance): void
    {
        abort_unless($seance->emploiDuTemps->ecole_id === $request->user()->ecole_id, 403);
    }
}
