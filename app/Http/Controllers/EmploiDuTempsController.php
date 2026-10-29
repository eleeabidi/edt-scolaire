<?php

namespace App\Http\Controllers;

use App\Enums\StatutEmploi;
use App\Exceptions\GenerationImpossible;
use App\Models\EmploiDuTemps;
use App\Models\NiveauMatiere;
use App\Models\Seance;
use App\Services\GenerateurEmploiDuTemps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US13 — Emploi du temps de l'école du directeur : choix de l'année scolaire et génération (T13.5, T13.8).
 */
class EmploiDuTempsController extends Controller
{
    /**
     * Rubrique « Emploi du temps » : l'emploi du temps de l'année choisie, ou la proposition de le générer.
     */
    public function index(Request $request): Response
    {
        $ecole = $request->user()->ecole;
        $annees = self::anneesScolaires();
        $annee = in_array($request->query('annee'), $annees, true) ? (string) $request->query('annee') : $annees[0];

        $emploi = $ecole->emploisDuTemps()->where('annee_scolaire', $annee)->first();

        return Inertia::render('emploi-du-temps/Index', [
            'ecoleNom' => $ecole->nom,
            'annees' => $annees,
            'annee' => $annee,
            'emploi' => $emploi ? $this->resume($emploi) : null,
        ]);
    }

    /**
     * Génère l'emploi du temps de toutes les classes de l'école pour l'année choisie (statut brouillon).
     */
    public function generer(Request $request, GenerateurEmploiDuTemps $generateur): RedirectResponse
    {
        $request->validate([
            'annee_scolaire' => ['required', Rule::in(self::anneesScolaires())],
            'remplacer' => ['sometimes', 'boolean'],
        ]);

        $ecole = $request->user()->ecole;
        $annee = $request->string('annee_scolaire')->value();
        $existant = $ecole->emploisDuTemps()->where('annee_scolaire', $annee)->first();

        // 3b : un emploi du temps publié ne peut plus être généré à nouveau
        if ($existant?->estPublie()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "L'emploi du temps {$annee} est publié : une nouvelle génération est impossible. Vous pouvez seulement modifier ses séances."]);

            return back();
        }

        // 3a : un brouillon existe déjà → le directeur doit confirmer son remplacement
        if ($existant && ! $request->boolean('remplacer')) {
            throw ValidationException::withMessages([
                'remplacer' => "Un emploi du temps en brouillon existe déjà pour l'année scolaire {$annee}.",
            ]);
        }

        // 4a / 5a : génération impossible → les causes, et rien n'est enregistré
        try {
            $seances = $generateur->genererAuto($ecole);
        } catch (GenerationImpossible $e) {
            throw ValidationException::withMessages(['generation' => implode("\n", $e->causes)]);
        }

        DB::transaction(function () use ($ecole, $annee, $existant, $seances) {
            $emploi = $existant ?? $ecole->emploisDuTemps()->create(['annee_scolaire' => $annee, 'statut' => StatutEmploi::Brouillon]);
            $emploi->seances()->delete();

            foreach ($seances as $donnees) {
                $seance = $emploi->seances()->create([
                    'classe_id' => $donnees['classe_id'],
                    'horaire_id' => $donnees['horaire_id'],
                    'enseignant_id' => $donnees['enseignant_id'],
                    'salle_id' => $donnees['salle_id'],
                ]);
                $seance->matieres()->createMany($donnees['matieres']);
            }

            $emploi->touch();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'emploi du temps {$annee} a été généré (brouillon)."]);

        return to_route('emploi.index', ['annee' => $annee]);
    }

    /**
     * L'année scolaire en cours (la rentrée est en septembre) et la suivante, ex. « 2026-2027 », « 2027-2028 ».
     *
     * @return list<string>
     */
    public static function anneesScolaires(?Carbon $date = null): array
    {
        $date ??= now();
        $debut = $date->month >= 9 ? $date->year : $date->year - 1;

        return [$debut.'-'.($debut + 1), ($debut + 1).'-'.($debut + 2)];
    }

    /**
     * Statut de l'emploi du temps et heures placées pour chaque classe (la grille arrive à l'étape suivante).
     *
     * @return array<string, mixed>
     */
    private function resume(EmploiDuTemps $emploi): array
    {
        $seances = $emploi->seances()->with(['classe', 'matieres'])->get();
        $attendues = NiveauMatiere::query()->selectRaw('niveau, SUM(minutes_par_semaine) as total')->groupBy('niveau')->pluck('total', 'niveau');

        $classes = $emploi->ecole->classes()->orderBy('niveau')->orderBy('nom')->get()->map(fn ($classe) => [
            'nom' => $classe->nom,
            'minutes' => (int) $seances->where('classe_id', $classe->id)->sum(
                fn (Seance $s) => $s->matieres->sum(fn ($m) => self::minutesDecret($m->heure_debut, $m->heure_fin)),
            ),
            'attendues' => (int) ($attendues[$classe->niveau] ?? 0),
        ]);

        return [
            'id' => $emploi->id,
            'annee' => $emploi->annee_scolaire,
            'statut' => $emploi->statut->value,
            'statutLibelle' => $emploi->statut->label(),
            'seances' => $seances->count(),
            'classes' => $classes,
        ];
    }

    /**
     * Durée d'une matière comptée comme dans le décret, arrondie à 10 minutes : la récréation est comprise
     * dans les heures de cours (9 h → 9 h 55 compte 1 h, 8 h → 9 h 55 compte 2 h, 9 h → 9 h 37 compte 40 min).
     */
    public static function minutesDecret(string $debut, string $fin): int
    {
        $minutes = fn (string $h) => (int) substr($h, 0, 2) * 60 + (int) substr($h, 3, 2);

        return (int) round(($minutes($fin) - $minutes($debut)) / 10) * 10;
    }
}
