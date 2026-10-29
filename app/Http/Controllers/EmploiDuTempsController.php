<?php

namespace App\Http\Controllers;

use App\Enums\StatutCompte;
use App\Enums\StatutEmploi;
use App\Exceptions\GenerationImpossible;
use App\Models\EmploiDuTemps;
use App\Models\User;
use App\Services\GenerateurEmploiDuTemps;
use App\Services\GrilleEmploiDuTemps;
use App\Support\CreneauHoraire;
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
 * US15 — Grille hebdomadaire filtrée par classe, par enseignant ou par salle (T15.1 à T15.3).
 */
class EmploiDuTempsController extends Controller
{
    /**
     * Rubrique « Emploi du temps » : la grille de l'année choisie (?annee=), filtrée par classe, enseignant
     * ou salle (?par=classe&id=3), ou la proposition de générer l'emploi du temps.
     */
    public function index(Request $request): Response
    {
        $ecole = $request->user()->ecole;
        $annees = self::anneesScolaires();
        $annee = in_array($request->query('annee'), $annees, true) ? (string) $request->query('annee') : $annees[0];

        $emploi = $ecole->emploisDuTemps()->where('annee_scolaire', $annee)->first();
        $proprietes = [
            'ecoleNom' => $ecole->nom,
            'annees' => $annees,
            'annee' => $annee,
            'emploi' => $emploi ? [
                'id' => $emploi->id,
                'annee' => $emploi->annee_scolaire,
                'statut' => $emploi->statut->value,
                'statutLibelle' => $emploi->statut->label(),
            ] : null,
        ];

        if ($emploi === null) {
            return Inertia::render('emploi-du-temps/Index', $proprietes);
        }

        // Ce qu'on peut afficher : les classes, les enseignants (actifs ou planifiés) et les salles de l'école
        $planifies = $emploi->seances()->distinct()->pluck('enseignant_id');
        $options = [
            'classe' => $ecole->classes()->orderBy('niveau')->orderBy('nom')->get(['id', 'nom'])
                ->map(fn ($c) => ['id' => $c->id, 'nom' => $c->nom])->all(),
            'enseignant' => $ecole->enseignants()
                ->where(fn ($q) => $q->where('statut', StatutCompte::Actif)->orWhereIn('id', $planifies))
                ->orderBy('nom')->orderBy('prenom')->get()
                ->map(fn (User $u) => ['id' => $u->id, 'nom' => $u->name])->all(),
            'salle' => $ecole->salles()->orderBy('nom')->get(['id', 'nom'])
                ->map(fn ($s) => ['id' => $s->id, 'nom' => $s->nom])->all(),
        ];

        $par = array_key_exists((string) $request->query('par'), GrilleEmploiDuTemps::FILTRES) ? (string) $request->query('par') : 'classe';
        $ids = array_column($options[$par], 'id');
        $id = in_array($request->integer('id'), $ids, true) ? $request->integer('id') : ($ids[0] ?? 0);
        $seances = GrilleEmploiDuTemps::seances($emploi, $par, $id);

        return Inertia::render('emploi-du-temps/Index', [
            ...$proprietes,
            ...GrilleEmploiDuTemps::structure(),
            'options' => $options,
            'par' => $par,
            'choix' => $id,
            'seances' => $seances,
            'totalMinutes' => GrilleEmploiDuTemps::totalMinutes($seances),
            'nbConflits' => count($emploi->detecterConflits()),
            'surlignee' => $request->integer('seance') ?: null, // « Voir dans la grille » depuis la page des conflits
        ]);
    }

    /**
     * US14 — Conflits de l'emploi du temps : cause et solution proposée (T14.2, T14.3).
     */
    public function conflits(Request $request, EmploiDuTemps $emploi): Response
    {
        abort_unless($emploi->ecole_id === $request->user()->ecole_id, 403);

        return Inertia::render('emploi-du-temps/Conflits', [
            'annee' => $emploi->annee_scolaire,
            'conflits' => $emploi->detecterConflits(),
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
     * Durée d'une matière comptée comme dans le décret, arrondie à 10 minutes : la récréation est comprise
     * dans les heures de cours (9 h → 9 h 55 compte 1 h, 8 h → 9 h 55 compte 2 h, 9 h → 9 h 37 compte 40 min).
     */
    public static function minutesDecret(string $debut, string $fin): int
    {
        return CreneauHoraire::minutesDecret($debut, $fin);
    }
}
