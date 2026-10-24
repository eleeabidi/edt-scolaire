<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Affectation;
use App\Models\Disponibilite;
use App\Models\Horaire;
use App\Models\User;
use App\Support\Duree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $creneaux = collect($this->creneaux());

        $indisponibles = [];
        $minutesDisponibles = 0;

        if ($choisi) {
            $disponibles = $this->clesDisponibles($choisi);

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

        $creneaux = collect($this->creneaux());
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

        DB::transaction(function () use ($enseignant, $indisponibles, $disponibles) {
            $enseignant->disponibilites()->delete();

            // Aucun créneau indisponible = disponible partout = aucune ligne
            if ($indisponibles->isNotEmpty()) {
                $enseignant->disponibilites()->createMany($disponibles->map(fn (array $c) => [
                    'jour' => $c['jour'],
                    'heure_debut' => $c['debut'],
                    'heure_fin' => $c['fin'],
                ])->values()->all());
            }
        });

        Inertia::flash('toast', $insuffisant
            ? ['type' => 'warning', 'message' => 'Disponibilités enregistrées, mais attention : '.Duree::format($minutesDisponibles)
                .' disponibles pour '.Duree::format($minutesAffectees).' affectées.']
            : ['type' => 'success', 'message' => 'Les disponibilités ont été enregistrées.']);

        return back();
    }

    /**
     * Les créneaux nationaux (table horaires), avec les heures qu'ils comptent :
     * 08:00 → 09:55 compte 2 h (la récréation n'est pas déduite), 12:00 → 13:00 compte 1 h.
     *
     * @return array<int, array{cle: string, jour: int, debut: string, fin: string, minutes: int}>
     */
    private function creneaux(): array
    {
        return Horaire::query()
            ->orderBy('jour')
            ->orderBy('heure_debut')
            ->get()
            ->map(function (Horaire $h) {
                $debut = substr($h->heure_debut, 0, 5);
                $fin = substr($h->heure_fin, 0, 5);
                $duree = $this->minutes($fin) - $this->minutes($debut);

                return [
                    'cle' => "{$h->jour->value}-{$debut}",
                    'jour' => $h->jour->value,
                    'debut' => $debut,
                    'fin' => $fin,
                    'minutes' => (int) round($duree / 60) * 60,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Les créneaux disponibles d'un enseignant, ou null s'il n'a aucune ligne (disponible partout).
     *
     * @return array<int, string>|null
     */
    private function clesDisponibles(User $enseignant): ?array
    {
        $lignes = Disponibilite::where('enseignant_id', $enseignant->id)->get();

        if ($lignes->isEmpty()) {
            return null;
        }

        return $lignes->map(fn (Disponibilite $d) => "{$d->jour->value}-".substr($d->heure_debut, 0, 5))->values()->all();
    }

    private function minutes(string $heure): int
    {
        [$h, $m] = array_map('intval', explode(':', $heure));

        return $h * 60 + $m;
    }
}
