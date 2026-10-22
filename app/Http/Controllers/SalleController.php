<?php

namespace App\Http\Controllers;

use App\Enums\TypeSalle;
use App\Http\Requests\SalleRequest;
use App\Models\Salle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T09.4 — Gérer les salles (directeur, son école uniquement). Écran 17, fenêtres F10 et F11.
 */
class SalleController extends Controller
{
    public function index(Request $request): Response
    {
        $ecole = $request->user()->ecole;
        $recherche = $request->string('q')->trim()->value();
        $type = TypeSalle::tryFrom($request->string('type')->value());

        $salles = $ecole->salles()
            ->when($recherche, fn (Builder $q) => $q->where('nom', 'like', "%{$recherche}%"))
            ->when($type, fn (Builder $q) => $q->where('type', $type))
            ->orderBy('nom')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Salle $salle) => [
                'id' => $salle->id,
                'nom' => $salle->nom,
                'type' => $salle->type->value,
                'capacite' => $salle->capacite,
            ]);

        return Inertia::render('salles/Index', [
            'salles' => $salles,
            'filtres' => ['q' => $recherche, 'type' => $type?->value],
            'ecoleNom' => $ecole->nom,
        ]);
    }

    public function store(SalleRequest $request): RedirectResponse
    {
        $request->user()->ecole->salles()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La salle a été ajoutée.']);

        return back();
    }

    public function update(SalleRequest $request, Salle $salle): RedirectResponse
    {
        $this->verifierEcole($request, $salle);

        $salle->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La salle a été modifiée.']);

        return back();
    }

    public function destroy(Request $request, Salle $salle): RedirectResponse
    {
        $this->verifierEcole($request, $salle);

        $salle->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La salle a été supprimée.']);

        return back();
    }

    /**
     * Un directeur ne gère que les salles de son école.
     */
    private function verifierEcole(Request $request, Salle $salle): void
    {
        abort_unless($salle->ecole_id === $request->user()->ecole_id, 403);
    }
}
