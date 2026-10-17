<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord de l'Admin (écran 07) : chiffres clés + inscriptions en attente.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $directeurs = User::where('role', Role::Directeur);

        $inscriptions = (clone $directeurs)
            ->where('statut', StatutCompte::EnAttente)
            ->with('ecole:id,nom,ville')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (User $directeur) => [
                'id' => $directeur->id,
                'nom' => $directeur->nom,
                'prenom' => $directeur->prenom,
                'ecole' => $directeur->ecole?->nom,
                'ville' => $directeur->ecole?->ville,
                'recue' => $directeur->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Dashboard', [
            'statistiques' => [
                // Une école est « inscrite » quand son directeur a été validé
                'ecoles' => (clone $directeurs)->where('statut', StatutCompte::Actif)->distinct()->count('ecole_id'),
                'directeursActifs' => (clone $directeurs)->where('statut', StatutCompte::Actif)->count(),
                'inscriptionsEnAttente' => (clone $directeurs)->where('statut', StatutCompte::EnAttente)->count(),
            ],
            'inscriptions' => $inscriptions,
        ]);
    }
}
