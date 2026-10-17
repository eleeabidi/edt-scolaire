<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Http\Requests\EnseignantRequest;
use App\Models\Ecole;
use App\Models\User;
use App\Notifications\CompteCreeNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gérer les enseignants (Admin et Directeur).
 * Un directeur ne voit et ne gère que les enseignants de son école.
 */
class EnseignantController extends Controller
{
    /**
     * Liste des enseignants, avec recherche (et filtre par école pour l'Admin).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $recherche = $request->string('q')->trim()->value();
        $ecoleId = $user->isAdmin() ? $request->integer('ecole') ?: null : null;
        $statut = StatutCompte::tryFrom($request->string('statut')->value());

        $enseignants = User::query()
            ->where('role', Role::Enseignant)
            ->when($user->isDirecteur(), fn (Builder $q) => $q->where('ecole_id', $user->ecole_id))
            ->when($ecoleId, fn (Builder $q) => $q->where('ecole_id', $ecoleId))
            ->when($statut, fn (Builder $q) => $q->where('statut', $statut))
            ->when($recherche, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('prenom', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")
                ->orWhere('specialite', 'like', "%{$recherche}%")))
            ->with('ecole:id,nom')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $enseignant) => [
                'id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'prenom' => $enseignant->prenom,
                'email' => $enseignant->email,
                'telephone' => $enseignant->telephone,
                'specialite' => $enseignant->specialite,
                'statut' => $enseignant->statut->value,
                'ecole_id' => $enseignant->ecole_id,
                'ecole' => $enseignant->ecole?->nom,
            ]);

        return Inertia::render('enseignants/Index', [
            'enseignants' => $enseignants,
            'filtres' => ['q' => $recherche, 'ecole' => $ecoleId, 'statut' => $statut?->value],
            'ecoleNom' => $user->ecole?->nom,
            'ecoles' => $user->isAdmin() ? Ecole::orderBy('nom')->get(['id', 'nom']) : [],
            'estAdmin' => $user->isAdmin(),
        ]);
    }

    /**
     * Ajoute un enseignant : compte ACTIF + mot de passe temporaire envoyé par email.
     */
    public function store(EnseignantRequest $request): RedirectResponse
    {
        $user = $request->user();
        $motDePasse = Str::password(10, symbols: false);

        $enseignant = User::create([
            ...$request->validated(),
            'ecole_id' => $user->isAdmin() ? $request->validated('ecole_id') : $user->ecole_id,
            'role' => Role::Enseignant,
            'statut' => StatutCompte::Actif,
            'password' => $motDePasse,
        ]);

        $enseignant->notify(new CompteCreeNotification($motDePasse));

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'enseignant a été ajouté. Son mot de passe temporaire lui a été envoyé par email."]);

        return back();
    }

    /**
     * Modifie un enseignant.
     */
    public function update(EnseignantRequest $request, User $enseignant): RedirectResponse
    {
        $this->autoriser($request, $enseignant);

        $enseignant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'enseignant a été modifié."]);

        return back();
    }

    /**
     * « Supprime » un enseignant : le compte est désactivé, jamais effacé.
     */
    public function destroy(Request $request, User $enseignant): RedirectResponse
    {
        $this->autoriser($request, $enseignant);

        $enseignant->update(['statut' => StatutCompte::Desactive]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'enseignant a été supprimé (compte désactivé)."]);

        return back();
    }

    /**
     * Vérifie que l'utilisateur connecté a le droit de gérer cet enseignant.
     */
    private function autoriser(Request $request, User $enseignant): void
    {
        $user = $request->user();

        abort_unless(
            $enseignant->isEnseignant() && ($user->isAdmin() || $enseignant->ecole_id === $user->ecole_id),
            403,
        );
    }
}
