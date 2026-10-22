<?php

namespace App\Http\Controllers;

use App\Models\NiveauMatiere;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T08.6 — Consultation du programme national par niveau (écran 19). Consultation seule.
 */
class ProgrammeController extends Controller
{
    public function __invoke(): Response
    {
        $volumes = NiveauMatiere::query()
            ->with('matiere')
            ->orderBy('niveau')
            ->orderByDesc('minutes_par_semaine')
            ->orderBy('id')
            ->get()
            ->groupBy('niveau');

        $niveaux = collect(range(1, 6))->map(fn (int $niveau) => [
            'niveau' => $niveau,
            'totalMinutes' => (int) ($volumes->get($niveau)?->sum('minutes_par_semaine') ?? 0),
            'matieres' => ($volumes->get($niveau) ?? collect())->map(fn (NiveauMatiere $volume) => [
                'nom' => $volume->matiere->nom,
                'minutes' => $volume->minutes_par_semaine,
                'autoriseeMidi' => $volume->matiere->autorisee_midi,
                'typeSalle' => $volume->matiere->type_salle->value,
            ])->values(),
        ]);

        return Inertia::render('programme/Index', [
            'niveaux' => $niveaux,
        ]);
    }
}
