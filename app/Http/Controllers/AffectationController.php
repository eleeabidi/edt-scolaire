<?php

namespace App\Http\Controllers;

use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Matiere;
use App\Models\NiveauMatiere;
use App\Models\User;
use App\Support\Duree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T06.3 / T06.4 — Affectations d'un enseignant aux classes et aux matières, et volume horaire maximum.
 * Écran 15. Directeur uniquement, pour les enseignants et les classes de son école.
 */
class AffectationController extends Controller
{
    /**
     * Page « Affectations de … ».
     */
    public function show(Request $request, User $enseignant): Response
    {
        $this->verifierEnseignant($request, $enseignant);

        $ecoleId = $request->user()->ecole_id;

        $affectations = $enseignant->affectations()
            ->with(['classe', 'matiere'])
            ->get()
            ->map(fn (Affectation $a) => [
                'id' => $a->id,
                'classe' => $a->classe->nom,
                'matiere' => $a->matiere->nom,
                'minutes' => Affectation::minutesDuProgramme($a->classe, $a->matiere_id),
            ])
            ->sortBy(['classe', 'matiere'])
            ->values();

        // Pour chaque classe de l'école : les matières de son niveau, leur volume et qui les enseigne déjà
        $dejaAffectees = Affectation::query()
            ->whereHas('classe', fn ($q) => $q->where('ecole_id', $ecoleId))
            ->with('enseignant:id,nom,prenom')
            ->get()
            ->keyBy(fn (Affectation $a) => "{$a->classe_id}-{$a->matiere_id}");

        $programme = NiveauMatiere::with('matiere')->orderByDesc('minutes_par_semaine')->get()->groupBy('niveau');

        $classes = Classe::where('ecole_id', $ecoleId)
            ->orderBy('niveau')
            ->orderBy('nom')
            ->get()
            ->map(fn (Classe $classe) => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'matieres' => ($programme->get($classe->niveau) ?? collect())
                    ->map(fn (NiveauMatiere $volume) => [
                        'id' => $volume->matiere_id,
                        'nom' => $volume->matiere->nom,
                        'minutes' => $volume->minutes_par_semaine,
                        'enseignant' => $dejaAffectees->get("{$classe->id}-{$volume->matiere_id}")?->enseignant?->name,
                    ])
                    ->values(),
            ]);

        return Inertia::render('enseignants/Affectations', [
            'enseignant' => [
                'id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'prenom' => $enseignant->prenom,
                'volume_horaire_max' => $enseignant->volume_horaire_max,
            ],
            'ecoleNom' => $request->user()->ecole?->nom,
            'totalMinutes' => Affectation::minutesPourEnseignant($enseignant->id),
            'affectations' => $affectations,
            'classes' => $classes,
        ]);
    }

    /**
     * Affecte l'enseignant à une classe pour une ou plusieurs matières, avec le volume horaire
     * maximum ajusté si besoin. Tout est vérifié avant d'enregistrer : rien n'est créé en cas de refus.
     */
    public function store(Request $request, User $enseignant): RedirectResponse
    {
        $this->verifierEnseignant($request, $enseignant);

        $request->validate([
            'classe_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('ecole_id', $request->user()->ecole_id)],
            'matieres' => ['required', 'array', 'min:1'],
            'matieres.*' => ['integer', 'distinct', Rule::exists('matieres', 'id')],
            'volume_horaire_max' => ['sometimes', 'required', 'integer', 'min:1', 'max:40'],
        ], [
            'classe_id.exists' => "Cette classe n'existe pas dans votre école.",
            'matieres.required' => 'Choisissez au moins une matière.',
        ], [
            'classe_id' => 'classe',
            'volume_horaire_max' => 'volume horaire maximum',
        ]);

        $classe = Classe::findOrFail($request->integer('classe_id'));
        $maximum = $request->has('volume_horaire_max') ? $request->integer('volume_horaire_max') : $enseignant->volume_horaire_max;
        $matieres = Matiere::whereIn('id', $request->collect('matieres')->map(fn (mixed $id) => (int) $id))->orderBy('nom')->get();
        $minutes = 0;

        foreach ($matieres as $matiere) {
            $volume = Affectation::minutesDuProgramme($classe, $matiere->id);

            // La matière doit faire partie du guide pédagogique du niveau de la classe
            if ($volume === 0) {
                throw ValidationException::withMessages([
                    'matieres' => "La matière « {$matiere->nom} » n'est pas enseignée en {$this->libelleNiveau($classe->niveau)}.",
                ]);
            }

            // Une matière d'une classe n'a qu'un seul enseignant (4a : le message indique lequel)
            $existante = Affectation::where('classe_id', $classe->id)->where('matiere_id', $matiere->id)->with('enseignant')->first();
            if ($existante) {
                throw ValidationException::withMessages([
                    'matieres' => "La matière « {$matiere->nom} » est déjà enseignée en {$classe->nom} par {$existante->enseignant->name}.",
                ]);
            }

            $minutes += $volume;
        }

        // 4b : le total ne dépasse jamais le volume horaire maximum de l'enseignant
        $total = Affectation::minutesPourEnseignant($enseignant->id) + $minutes;
        if ($total > $maximum * 60) {
            throw ValidationException::withMessages([
                'matieres' => 'Impossible : avec '.($matieres->count() > 1 ? 'ces affectations' : 'cette affectation')
                    .', le total passerait à '.Duree::format($total).", au-delà du maximum de {$maximum} h de cet enseignant.",
            ]);
        }

        DB::transaction(function () use ($enseignant, $classe, $matieres, $maximum) {
            $enseignant->update(['volume_horaire_max' => $maximum]);

            foreach ($matieres as $matiere) {
                $enseignant->affectations()->create(['classe_id' => $classe->id, 'matiere_id' => $matiere->id]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $matieres->count() > 1
            ? "Les {$matieres->count()} affectations ont été ajoutées."
            : "L'affectation a été ajoutée."]);

        return back();
    }

    /**
     * Retire une affectation.
     */
    public function destroy(Request $request, User $enseignant, Affectation $affectation): RedirectResponse
    {
        $this->verifierEnseignant($request, $enseignant);
        abort_unless($affectation->enseignant_id === $enseignant->id, 404);

        $affectation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'affectation a été retirée."]);

        return back();
    }

    /**
     * Modifie le volume horaire maximum (il ne peut pas descendre sous les heures déjà affectées).
     */
    public function volume(Request $request, User $enseignant): RedirectResponse
    {
        $this->verifierEnseignant($request, $enseignant);

        $donnees = $request->validate([
            'volume_horaire_max' => ['required', 'integer', 'min:1', 'max:40'],
        ], [], ['volume_horaire_max' => 'volume horaire maximum']);

        $total = Affectation::minutesPourEnseignant($enseignant->id);
        if ($total > $donnees['volume_horaire_max'] * 60) {
            throw ValidationException::withMessages([
                'volume_horaire_max' => 'Le maximum ne peut pas être inférieur aux heures déjà affectées ('.Duree::format($total).').',
            ]);
        }

        $enseignant->update($donnees);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le volume horaire maximum a été enregistré.']);

        return back();
    }

    /**
     * L'enseignant doit être un enseignant actif de l'école du directeur.
     */
    private function verifierEnseignant(Request $request, User $enseignant): void
    {
        abort_unless($enseignant->isEnseignant() && $enseignant->ecole_id === $request->user()->ecole_id, 403);
        abort_unless($enseignant->isActif(), 409, 'Ce compte est désactivé : aucune affectation possible.');
    }

    private function libelleNiveau(int $niveau): string
    {
        return $niveau === 1 ? '1re année' : "{$niveau}e année";
    }
}
