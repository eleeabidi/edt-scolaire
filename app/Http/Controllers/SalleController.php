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
 * T09.4 — Gérer les salles (directeur, son école uniquement) : mise en service, modification
 * et mise hors service. Une salle n'est jamais supprimée. Écran 17, fenêtres F9 et F10.
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
                'en_service' => $salle->en_service,
            ]);

        return Inertia::render('salles/Index', [
            'salles' => $salles,
            'filtres' => ['q' => $recherche, 'type' => $type?->value],
            'ecoleNom' => $ecole->nom,
        ]);
    }

    /**
     * « Mettre en service une salle » : la salle créée est en service.
     */
    public function store(SalleRequest $request): RedirectResponse
    {
        $request->user()->ecole->salles()->create([...$request->validated(), 'en_service' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La salle a été mise en service.']);

        return back();
    }

    public function update(SalleRequest $request, Salle $salle): RedirectResponse
    {
        $this->verifierEcole($request, $salle);

        $salle->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La salle a été modifiée.']);

        return back();
    }

    /**
     * La salle reste dans la liste, mais ne sera plus utilisée pour la génération (sprint 3).
     */
    public function mettreHorsService(Request $request, Salle $salle): RedirectResponse
    {
        $this->verifierEcole($request, $salle);

        $salle->update(['en_service' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "La salle {$salle->nom} a été mise hors service."]);

        return back();
    }

    /**
     * Une salle hors service redevient utilisable.
     */
    public function mettreEnService(Request $request, Salle $salle): RedirectResponse
    {
        $this->verifierEcole($request, $salle);

        $salle->update(['en_service' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "La salle {$salle->nom} a été mise en service."]);

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
