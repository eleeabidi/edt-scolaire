<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Affectation;
use App\Models\Disponibilite;
use App\Models\User;
use App\Support\Duree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T10.3 / T10.4 — Gestion des enseignants : leurs disponibilités (écran 18). Directeur, enseignants actifs de son école.
 * La table stocke les créneaux disponibles ; un enseignant sans aucune ligne est disponible partout.
 * Dans la grille, le directeur clique les créneaux où l'enseignant n'est pas disponible.
 */
class DisponibiliteController extends Controller
{
    public function index(Request $request): Response
    {
        $enseignants = User::query()
            ->where('role', Role::Enseignant)
            ->where('statut', StatutCompte::Actif)
            ->where('ecole_id', $request->user()->ecole_id)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom']);

        $choisi = $enseignants->firstWhere('id', $request->integer('enseignant')) ?? $enseignants->first();
        $creneaux = collect(Disponibilite::creneaux());

        $indisponibles = [];
        $minutesDisponibles = 0;

        if ($choisi) {
            $disponibles = Disponibilite::clesDisponibles($choisi);

            foreach ($creneaux as $creneau) {
                if ($disponibles === null || in_array($creneau['cle'], $disponibles, true)) {
                    $minutesDisponibles += $creneau['minutes'];
                } else {
                    $indisponibles[] = $creneau['cle'];
                }
            }
        }

        return Inertia::render('disponibilites/Index', [
            'enseignants' => $enseignants->map(fn (User $e) => ['id' => $e->id, 'nom' => $e->name])->values(),
            'enseignantId' => $choisi?->id,
            'creneaux' => $creneaux->values(),
            'indisponibles' => $indisponibles,
            'minutesDisponibles' => $minutesDisponibles,
            'minutesAffectees' => $choisi ? Affectation::minutesPourEnseignant($choisi->id) : 0,
        ]);
    }

    /**
     * Enregistre la grille : la liste des créneaux indisponibles (« jour-heure de début »).
     * 4a : si les heures disponibles sont inférieures aux heures affectées, le directeur doit
     * confirmer l'enregistrement (« confirme » = vrai) ; sinon rien n'est enregistré.
     */
    public function update(Request $request, User $enseignant): RedirectResponse
    {
        abort_unless($enseignant->isEnseignant() && $enseignant->ecole_id === $request->user()->ecole_id, 403);
        abort_unless($enseignant->isActif(), 409, 'Ce compte est désactivé.');

        $request->validate([
            'indisponibles' => ['present', 'array'],
            'indisponibles.*' => ['string'],
            'confirme' => ['sometimes', 'boolean'],
        ]);

        $creneaux = collect(Disponibilite::creneaux());
        $indisponibles = $request->collect('indisponibles')->unique();

        $inconnus = $indisponibles->diff($creneaux->pluck('cle'));
        if ($inconnus->isNotEmpty()) {
            throw ValidationException::withMessages(['indisponibles' => "Ce créneau n'existe pas : {$inconnus->first()}."]);
        }

        $disponibles = $creneaux->reject(fn (array $c) => $indisponibles->contains($c['cle']));
        $minutesDisponibles = (int) $disponibles->sum('minutes');
        $minutesAffectees = Affectation::minutesPourEnseignant($enseignant->id);
        $insuffisant = $minutesDisponibles < $minutesAffectees;

        if ($insuffisant && ! $request->boolean('confirme')) {
            throw ValidationException::withMessages([
                'confirme' => 'Attention : '.Duree::format($minutesDisponibles).' disponibles pour '
                    .Duree::format($minutesAffectees).' affectées. Confirmez l\'enregistrement ou corrigez la grille.',
            ]);
        }

        Disponibilite::enregistrer($enseignant, array_values(array_filter(
            array_column(Disponibilite::creneaux(), 'cle'),
            fn (string $cle) => $indisponibles->contains($cle),
        )));

        Inertia::flash('toast', $insuffisant
            ? ['type' => 'warning', 'message' => 'Disponibilités enregistrées, mais attention : '.Duree::format($minutesDisponibles)
                .' disponibles pour '.Duree::format($minutesAffectees).' affectées.']
            : ['type' => 'success', 'message' => 'Les disponibilités ont été enregistrées.']);

        return back();
    }
}
