<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClasseRequest;
use App\Models\Classe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T07.4 — Gérer les classes : ajouter, modifier, supprimer (directeur, son école uniquement).
 * Écran 16, fenêtres F7 (ajouter/modifier) et F8 (supprimer).
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
     * Supprime la classe et ses affectations (suppression en cascade).
     */
    public function destroy(Request $request, Classe $classe): RedirectResponse
    {
        $this->verifierEcole($request, $classe);

        $classe->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La classe a été supprimée.']);

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
