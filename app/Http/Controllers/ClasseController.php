<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClasseRequest;
use App\Http\Requests\GroupesRequest;
use App\Models\Classe;
use App\Models\Groupe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T07.5 — Gérer les classes et leurs groupes (directeur, son école uniquement).
 * Écran 16, fenêtres F7 (ajouter/modifier), F8 (groupes) et F9 (supprimer).
 */
class ClasseController extends Controller
{
    public function index(Request $request): Response
    {
        $ecole = $request->user()->ecole;
        $recherche = $request->string('q')->trim()->value();
        $niveau = $request->integer('niveau');
        $niveau = $niveau >= 1 && $niveau <= 6 ? $niveau : null;

        $classes = $ecole->classes()
            ->when($recherche, fn (Builder $q) => $q->where('nom', 'like', "%{$recherche}%"))
            ->when($niveau, fn (Builder $q) => $q->where('niveau', $niveau))
            ->with(['groupes' => fn ($q) => $q->orderBy('nom')])
            ->orderBy('niveau')
            ->orderBy('nom')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Classe $classe) => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'niveau' => $classe->niveau,
                'effectif' => $classe->effectif,
                'cours_samedi' => $classe->cours_samedi,
                'groupes' => $classe->groupes->map(fn (Groupe $groupe) => [
                    'id' => $groupe->id,
                    'nom' => $groupe->nom,
                    'effectif' => $groupe->effectif,
                ])->values(),
            ]);

        return Inertia::render('classes/Index', [
            'classes' => $classes,
            'filtres' => ['q' => $recherche, 'niveau' => $niveau],
            'ecoleNom' => $ecole->nom,
        ]);
    }

    public function store(ClasseRequest $request): RedirectResponse
    {
        $request->user()->ecole->classes()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La classe a été ajoutée.']);

        return back();
    }

    public function update(ClasseRequest $request, Classe $classe): RedirectResponse
    {
        $this->verifierEcole($request, $classe);

        $classe->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La classe a été modifiée.']);

        return back();
    }

    /**
     * Supprime la classe et ses groupes (suppression en cascade).
     */
    public function destroy(Request $request, Classe $classe): RedirectResponse
    {
        $this->verifierEcole($request, $classe);

        $classe->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La classe a été supprimée.']);

        return back();
    }

    /**
     * Fenêtre F8 : enregistre la liste complète des groupes de la classe
     * (les groupes absents de la liste sont supprimés, les nouveaux sont créés).
     */
    public function groupes(GroupesRequest $request, Classe $classe): RedirectResponse
    {
        $this->verifierEcole($request, $classe);

        DB::transaction(function () use ($request, $classe) {
            $lignes = collect($request->groupes());
            $idsConserves = $lignes->pluck('id')->filter()->all();

            $classe->groupes()->whereNotIn('id', $idsConserves)->delete();

            foreach ($lignes as $ligne) {
                $donnees = ['nom' => $ligne['nom'], 'effectif' => $ligne['effectif']];

                if (! empty($ligne['id'])) {
                    $classe->groupes()->whereKey($ligne['id'])->update($donnees);
                } else {
                    $classe->groupes()->create($donnees);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Les groupes ont été enregistrés.']);

        return back();
    }

    /**
     * Un directeur ne gère que les classes de son école.
     */
    private function verifierEcole(Request $request, Classe $classe): void
    {
        abort_unless($classe->ecole_id === $request->user()->ecole_id, 403);
    }
}
