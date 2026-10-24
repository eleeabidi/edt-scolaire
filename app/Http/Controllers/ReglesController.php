<?php

namespace App\Http\Controllers;

use App\Enums\Jour;
use App\Models\Contrainte;
use App\Models\Horaire;
use App\Models\Matiere;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T11.6 — Consultation des règles de gestion des emplois du temps et des créneaux, générales ou par niveau (écran 20).
 */
class ReglesController extends Controller
{
    public function __invoke(): Response
    {
        $valeur = fn (string $type) => Contrainte::generales($type)->first()?->valeur;
        $description = fn (string $type) => Contrainte::generales($type)->first()?->description;

        $creneaux = fn (Jour $jour) => Horaire::query()
            ->where('jour', $jour)
            ->orderBy('heure_debut')
            ->get()
            ->map(fn (Horaire $h) => [
                'debut' => substr($h->heure_debut, 0, 5),
                'fin' => substr($h->heure_fin, 0, 5),
            ])
            ->values();

        $niveaux = collect(range(1, 6))->map(function (int $niveau) {
            $regles = Contrainte::pourNiveau($niveau);
            $samedi = $regles->firstWhere('type', 'cours_samedi');

            return [
                'niveau' => $niveau,
                // Les règles propres au niveau, et la règle du samedi qui s'applique (générale ou propre)
                'regles' => $regles
                    ->filter(fn (Contrainte $r) => $r->niveau === $niveau || $r->type === 'cours_samedi')
                    ->reject(fn (Contrainte $r) => $r->type === 'total_hebdomadaire')
                    ->pluck('description')
                    ->values(),
                'totalMinutes' => (int) $regles->firstWhere('type', 'total_hebdomadaire')?->valeur,
                // 4a : sans règle propre (hors total par semaine), les règles générales s'appliquent
                'reglesPropres' => $regles->contains(fn (Contrainte $r) => $r->niveau === $niveau && $r->type !== 'total_hebdomadaire'),
                'coursSamedi' => $samedi->valeur ?? 'non', // oui, non ou selon_classe
            ];
        });

        return Inertia::render('regles/Index', [
            'generales' => [
                'journee' => $valeur('journee'),
                'horaireSamedi' => $valeur('horaire_samedi'),
                'pauseMidi' => $valeur('pause_midi'),
                'minimumParMatiere' => (int) $valeur('minimum_par_matiere'),
                'recreations' => Contrainte::generales('recreation')->pluck('valeur'),
                'midiLibere' => $description('midi_libere'),
                'plusieursMatieres' => $description('plusieurs_matieres_par_seance'),
            ],
            'creneaux' => $creneaux(Jour::Lundi),
            'creneauxSamedi' => $creneaux(Jour::Samedi),
            'matieresMidi' => Matiere::where('autorisee_midi', true)->orderBy('nom')->pluck('nom'),
            'niveaux' => $niveaux,
        ]);
    }
}
