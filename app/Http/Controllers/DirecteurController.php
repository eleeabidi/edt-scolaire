<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Http\Requests\DirecteurRequest;
use App\Models\Ecole;
use App\Models\User;
use App\Notifications\CompteCreeNotification;
use App\Notifications\InscriptionRefuseeNotification;
use App\Notifications\InscriptionValideeNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gérer les directeurs (Admin uniquement) : ajouter, modifier, supprimer,
 * valider ou refuser une inscription.
 */
class DirecteurController extends Controller
{
    /**
     * Liste des directeurs, avec filtre par statut et recherche.
     */
    public function index(Request $request): Response
    {
        $recherche = $request->string('q')->trim()->value();
        $statut = StatutCompte::tryFrom($request->string('statut')->value());

        $directeurs = User::query()
            ->where('role', Role::Directeur)
            ->when($statut, fn (Builder $q) => $q->where('statut', $statut))
            ->when($recherche, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('prenom', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")
                ->orWhereHas('ecole', fn (Builder $q) => $q->where('nom', 'like', "%{$recherche}%"))))
            ->with('ecole')
            // Les inscriptions en attente d'abord, puis les plus récentes
            ->orderByRaw('statut = ? desc', [StatutCompte::EnAttente->value])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $directeur) => $this->formater($directeur));

        // Nombre de directeurs par statut, pour les onglets
        $compteurs = User::where('role', Role::Directeur)
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return Inertia::render('directeurs/Index', [
            'directeurs' => $directeurs,
            'filtres' => ['q' => $recherche, 'statut' => $statut?->value],
            'compteurs' => $compteurs,
        ]);
    }

    /**
     * Détail d'un directeur / d'une inscription.
     */
    public function show(User $directeur): Response
    {
        $this->verifierDirecteur($directeur);

        return Inertia::render('directeurs/Show', [
            'directeur' => $this->formater($directeur->load('ecole')),
        ]);
    }

    /**
     * Ajoute un directeur et son école : compte ACTIF + mot de passe temporaire par email.
     */
    public function store(DirecteurRequest $request): RedirectResponse
    {
        $motDePasse = Str::password(10, symbols: false);

        $directeur = DB::transaction(function () use ($request, $motDePasse) {
            $ecole = Ecole::create($request->ecole());

            return User::create([
                ...$request->directeur(),
                'ecole_id' => $ecole->id,
                'role' => Role::Directeur,
                'statut' => StatutCompte::Actif,
                'password' => $motDePasse,
            ]);
        });

        $directeur->notify(new CompteCreeNotification($motDePasse));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le directeur a été ajouté. Son mot de passe temporaire lui a été envoyé par email.']);

        return back();
    }

    /**
     * Modifie un directeur et son école.
     */
    public function update(DirecteurRequest $request, User $directeur): RedirectResponse
    {
        $this->verifierDirecteur($directeur);

        DB::transaction(function () use ($request, $directeur) {
            $directeur->update($request->directeur());
            $directeur->ecole?->update($request->ecole());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le directeur a été modifié.']);

        return back();
    }

    /**
     * « Supprime » un directeur : le compte est désactivé, jamais effacé.
     */
    public function destroy(User $directeur): RedirectResponse
    {
        $this->verifierDirecteur($directeur);

        $directeur->update(['statut' => StatutCompte::Desactive]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le directeur a été supprimé (compte désactivé).']);

        return back();
    }

    /**
     * Valide une inscription : le compte devient ACTIF et le directeur est prévenu.
     */
    public function valider(User $directeur): RedirectResponse
    {
        $this->verifierInscriptionEnAttente($directeur);

        $directeur->update(['statut' => StatutCompte::Actif, 'motif_refus' => null]);
        $directeur->notify(new InscriptionValideeNotification);

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'inscription a été validée. Le directeur a été prévenu par email."]);

        return to_route('directeurs.index');
    }

    /**
     * Refuse une inscription (motif facultatif) et prévient le directeur.
     */
    public function refuser(Request $request, User $directeur): RedirectResponse
    {
        $this->verifierInscriptionEnAttente($directeur);

        $motif = $request->validate([
            'motif' => ['nullable', 'string', 'max:1000'],
        ])['motif'] ?? null;

        $directeur->update(['statut' => StatutCompte::Refuse, 'motif_refus' => $motif]);
        $directeur->notify(new InscriptionRefuseeNotification($motif));

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'inscription a été refusée. Le directeur a été prévenu par email."]);

        return to_route('directeurs.index');
    }

    /**
     * Les données d'un directeur envoyées à l'interface.
     *
     * @return array<string, mixed>
     */
    private function formater(User $directeur): array
    {
        return [
            'id' => $directeur->id,
            'nom' => $directeur->nom,
            'prenom' => $directeur->prenom,
            'email' => $directeur->email,
            'telephone' => $directeur->telephone,
            'statut' => $directeur->statut->value,
            'motif_refus' => $directeur->motif_refus,
            'date_creation' => $directeur->created_at?->format('d/m/Y'),
            'date_creation_longue' => $directeur->created_at?->translatedFormat('j F Y à H:i'),
            'ecole' => $directeur->ecole?->only(['nom', 'adresse', 'ville', 'telephone']),
        ];
    }

    private function verifierDirecteur(User $directeur): void
    {
        abort_unless($directeur->isDirecteur(), 404);
    }

    private function verifierInscriptionEnAttente(User $directeur): void
    {
        $this->verifierDirecteur($directeur);

        abort_unless($directeur->statut === StatutCompte::EnAttente, 409, 'Cette inscription a déjà été traitée.');
    }
}
