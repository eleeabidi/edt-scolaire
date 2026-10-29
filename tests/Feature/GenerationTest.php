<?php

namespace Tests\Feature;

use App\Enums\Jour;
use App\Enums\StatutEmploi;
use App\Enums\TypeSalle;
use App\Http\Controllers\EmploiDuTempsController;
use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Disponibilite;
use App\Models\Ecole;
use App\Models\EmploiDuTemps;
use App\Models\Horaire;
use App\Models\Matiere;
use App\Models\NiveauMatiere;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T13.5 à T13.8 et T13.11 — Générer l'emploi du temps (sur le jeu de données de démonstration).
 */
class GenerationTest extends TestCase
{
    use RefreshDatabase;

    private Ecole $ecole;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->ecole = Ecole::where('nom', 'École Les Jasmins')->firstOrFail();
        $this->directeur = User::where('email', 'directeur@edt-scolaire.test')->firstOrFail();
    }

    private function generer(array $donnees = [])
    {
        return $this->actingAs($this->directeur)->post(route('emploi.generer'), ['annee_scolaire' => '2026-2027', ...$donnees]);
    }

    private function emploi(): ?EmploiDuTemps
    {
        return $this->ecole->emploisDuTemps()->where('annee_scolaire', '2026-2027')->first();
    }

    private function enseignant(string $prenom): User
    {
        return User::where('prenom', $prenom)->firstOrFail();
    }

    private function minutes(string $heure): int
    {
        return (int) substr($heure, 0, 2) * 60 + (int) substr($heure, 3, 2);
    }

    public function test_only_the_directeur_can_open_the_timetable()
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('emploi.index'))->assertForbidden();
        $this->actingAs($this->enseignant('Amira'))->get(route('emploi.index'))->assertForbidden();
        $this->actingAs($this->directeur)->get(route('emploi.index'))->assertOk();
    }

    public function test_without_timetable_the_page_proposes_to_generate_one()
    {
        Carbon::setTestNow('2026-10-29');

        $this->actingAs($this->directeur)
            ->get(route('emploi.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('emploi-du-temps/Index')
                ->where('annees', ['2026-2027', '2027-2028'])
                ->where('annee', '2026-2027')
                ->where('emploi', null));
    }

    public function test_the_school_years_start_in_september()
    {
        $this->assertSame(['2026-2027', '2027-2028'], EmploiDuTempsController::anneesScolaires(Carbon::parse('2026-10-29')));
        $this->assertSame(['2026-2027', '2027-2028'], EmploiDuTempsController::anneesScolaires(Carbon::parse('2027-06-30')));
        $this->assertSame(['2027-2028', '2028-2029'], EmploiDuTempsController::anneesScolaires(Carbon::parse('2027-09-01')));
    }

    public function test_the_timetable_of_the_demo_school_respects_all_the_rules()
    {
        Carbon::setTestNow('2026-10-29');

        $this->generer()
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('emploi.index', ['annee' => '2026-2027']));

        $emploi = $this->emploi();
        $this->assertSame(StatutEmploi::Brouillon, $emploi->statut);

        $this->verifierRegles($emploi);

        $this->actingAs($this->directeur)
            ->get(route('emploi.index', ['annee' => '2026-2027']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('emploi.statut', 'brouillon')
                ->where('emploi.classes.0.nom', '1re année A')
                ->where('emploi.classes.0.minutes', 1200)
                ->where('emploi.classes.7.minutes', 1680));
    }

    public function test_incomplete_data_shows_the_causes_and_generates_nothing()
    {
        $anglais = Matiere::where('nom', 'Anglais')->value('id');
        Affectation::where('matiere_id', $anglais)->where('classe_id', Classe::where('nom', '4e année A')->value('id'))->delete();
        Salle::where('nom', 'Salle de sport')->update(['en_service' => false]);

        $this->generer()->assertSessionHasErrors([
            'generation' => "La matière Anglais de la classe 4e année A n'a pas d'enseignant.\nAucune salle de sport n'est en service.",
        ]);

        $this->assertNull($this->emploi());
    }

    public function test_a_subject_whose_teacher_is_deactivated_has_no_teacher()
    {
        Salle::where('type', TypeSalle::Technologie)->update(['en_service' => false]);
        $this->enseignant('Youssef')->update(['statut' => 'DESACTIVE']);

        $this->generer()->assertSessionHasErrors('generation');

        $erreur = session('errors')->get('generation')[0];
        $this->assertStringContainsString("La matière Éducation physique de la classe 1re année A n'a pas d'enseignant.", $erreur);
        $this->assertStringContainsString("Aucune salle de technologie n'est en service.", $erreur);
    }

    public function test_a_teacher_without_enough_available_hours_is_explained()
    {
        // Karim Jaziri (18 h) n'est disponible que le lundi : 9 h
        $karim = $this->enseignant('Karim');
        foreach (Horaire::where('jour', Jour::Lundi)->get() as $horaire) {
            $karim->disponibilites()->create(['jour' => $horaire->jour, 'heure_debut' => $horaire->heure_debut, 'heure_fin' => $horaire->heure_fin]);
        }

        $this->generer()->assertSessionHasErrors(['generation' => "Karim Jaziri n'a que 9 h disponibles pour 18 h affectées."]);
        $this->assertNull($this->emploi());
    }

    public function test_no_solution_respecting_all_the_rules_is_explained()
    {
        // Amira Trabelsi n'est disponible que le matin : la 1re année A doit avoir un après-midi par semaine
        $amira = $this->enseignant('Amira');
        foreach (Horaire::where('heure_fin', '<=', '12:00:00')->where('jour', '!=', Jour::Samedi)->get() as $horaire) {
            $amira->disponibilites()->create(['jour' => $horaire->jour, 'heure_debut' => $horaire->heure_debut, 'heure_fin' => $horaire->heure_fin]);
        }

        $this->generer()->assertSessionHasErrors(['generation' => 'Aucune solution ne respecte toutes les règles : les disponibilités de Amira Trabelsi ne laissent pas assez de créneaux pour la classe 1re année A.']);
        $this->assertNull($this->emploi());
    }

    public function test_every_room_must_be_big_enough_for_the_class()
    {
        Classe::where('nom', '3e année A')->update(['effectif' => 40]);

        $this->generer()->assertSessionHasErrors('generation');

        $erreur = session('errors')->get('generation')[0];
        $this->assertStringContainsString("Aucune salle de cours en service n'est assez grande pour la classe 3e année A (40 élèves).", $erreur);
        $this->assertStringContainsString("Aucune salle de technologie en service n'est assez grande pour la classe 3e année A (40 élèves).", $erreur);
    }

    public function test_an_existing_draft_is_replaced_only_after_confirmation()
    {
        $this->generer()->assertSessionHasNoErrors();
        $emploi = $this->emploi();
        $anciennes = $emploi->seances()->pluck('id');

        // Une modification faite à la main
        $seance = $emploi->seances()->first();
        $seance->update(['salle_id' => Salle::where('nom', 'Salle 8')->value('id')]);

        $this->generer()->assertSessionHasErrors(['remplacer' => "Un emploi du temps en brouillon existe déjà pour l'année scolaire 2026-2027."]);
        $this->assertModelExists($seance);

        $this->generer(['remplacer' => true])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->ecole->emploisDuTemps()->count());
        $this->assertSame(0, Seance::whereIn('id', $anciennes)->count());
        $this->verifierRegles($this->emploi());
    }

    public function test_a_failed_generation_keeps_the_existing_draft()
    {
        $this->generer()->assertSessionHasNoErrors();
        $nombre = $this->emploi()->seances()->count();

        Salle::where('nom', 'Salle de sport')->update(['en_service' => false]);
        $this->generer(['remplacer' => true])->assertSessionHasErrors('generation');

        $this->assertSame($nombre, $this->emploi()->seances()->count());
    }

    public function test_a_published_timetable_cannot_be_generated_again()
    {
        $this->generer()->assertSessionHasNoErrors();
        $emploi = $this->emploi();
        $emploi->update(['statut' => StatutEmploi::Publie]);
        $anciennes = $emploi->seances()->pluck('id')->all();

        $this->generer(['remplacer' => true])
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message', "L'emploi du temps 2026-2027 est publié : une nouvelle génération est impossible. Vous pouvez seulement modifier ses séances.");

        $this->assertSame($anciennes, $emploi->seances()->pluck('id')->all());
    }

    public function test_only_the_proposed_school_years_can_be_generated()
    {
        $this->generer(['annee_scolaire' => '1999-2000'])->assertSessionHasErrors('annee_scolaire');
    }

    /**
     * Vérifie, séance par séance, toutes les règles de la génération.
     */
    private function verifierRegles(EmploiDuTemps $emploi): void
    {
        $seances = $emploi->seances()->with(['classe', 'horaire', 'salle', 'enseignant', 'matieres.matiere'])->get();
        $programme = NiveauMatiere::all()->groupBy('niveau');
        $affectations = Affectation::all()->keyBy(fn (Affectation $a) => "{$a->classe_id}-{$a->matiere_id}");
        $disponibilites = Disponibilite::all()->groupBy('enseignant_id');
        $occupations = [];

        foreach ($seances as $seance) {
            $horaire = $seance->horaire;
            $jour = $horaire->jour->value;
            $classe = $seance->classe;
            $debutCreneau = $this->minutes($horaire->heure_debut);
            $finCreneau = $this->minutes($horaire->heure_fin);
            $midi = $debutCreneau === 720;
            $matieres = $seance->matieres->sortBy('heure_debut')->values();
            $speciale = $matieres->first()->matiere->type_salle !== TypeSalle::Cours;

            // La salle : en service, du type demandé, assez grande
            $this->assertTrue($seance->salle->en_service);
            $this->assertSame($seance->salle->ecole_id, $this->ecole->id);
            $this->assertGreaterThanOrEqual($classe->effectif, $seance->salle->capacite, "Salle trop petite pour {$classe->nom}");

            if ($speciale) {
                // Éducation physique / technologique : une séance d'1 h à part, dans la salle du bon type
                $this->assertCount(1, $matieres);
                $this->assertSame($matieres[0]->matiere->type_salle, $seance->salle->type);
                $this->assertSame(60, EmploiDuTempsController::minutesDecret($matieres[0]->heure_debut, $matieres[0]->heure_fin));
            } else {
                $this->assertSame(TypeSalle::Cours, $seance->salle->type);
            }

            $debut = $this->minutes($matieres->first()->heure_debut);
            $fin = $this->minutes($matieres->last()->heure_fin);
            $this->assertGreaterThanOrEqual($debutCreneau, $debut);
            $this->assertLessThanOrEqual($finCreneau, $fin);

            foreach ($matieres as $i => $ligne) {
                // L'enseignant de la séance est celui de l'affectation ; 30 min minimum ; matières à la suite
                $this->assertSame($affectations["{$classe->id}-{$ligne->matiere_id}"]->enseignant_id, $seance->enseignant_id);
                $this->assertGreaterThanOrEqual(30, EmploiDuTempsController::minutesDecret($ligne->heure_debut, $ligne->heure_fin));
                if ($i > 0) {
                    $this->assertSame($matieres[$i - 1]->heure_fin, $ligne->heure_debut);
                }
                // De 12 h à 13 h : seulement les matières autorisées à midi
                if ($midi) {
                    $this->assertTrue($ligne->matiere->autorisee_midi, "{$ligne->matiere->nom} placée à midi");
                }
            }

            // L'enseignant est disponible sur ce créneau
            $lignes = $disponibilites->get($seance->enseignant_id);
            if ($lignes !== null) {
                $this->assertTrue($lignes->contains(fn ($d) => $d->jour->value === $jour && $d->heure_debut === $horaire->heure_debut), "{$seance->enseignant->name} pas disponible");
            }

            // Pas de séance le samedi de 12 h à 13 h, ni le samedi pour les classes sans cours le samedi
            if ($jour === 6) {
                $this->assertFalse($midi);
                $this->assertTrue($classe->cours_samedi, "{$classe->nom} a cours le samedi");
            }

            // Même classe, même enseignant ou même salle au même moment : interdit
            foreach (['c'.$classe->id, 'e'.$seance->enseignant_id, 's'.$seance->salle_id] as $cle) {
                foreach ($occupations[$cle][$jour] ?? [] as [$a, $b]) {
                    $this->assertFalse($debut < $b && $a < $fin, "Conflit {$cle} le jour {$jour}");
                }
                $occupations[$cle][$jour][] = [$debut, $fin];
            }
        }

        foreach ($this->ecole->classes as $classe) {
            $sesSeances = $seances->where('classe_id', $classe->id);

            // Les volumes du guide pédagogique, matière par matière
            $places = [];
            foreach ($sesSeances as $seance) {
                foreach ($seance->matieres as $ligne) {
                    $places[$ligne->matiere_id] = ($places[$ligne->matiere_id] ?? 0) + EmploiDuTempsController::minutesDecret($ligne->heure_debut, $ligne->heure_fin);
                }
            }
            $attendus = $programme[$classe->niveau]->pluck('minutes_par_semaine', 'matiere_id')->all();
            ksort($places);
            ksort($attendus);
            $this->assertSame($attendus, $places, "Volumes de {$classe->nom}");

            // Par créneau : une séance, ou deux avec 1 h d'éducation physique ou de technologie
            foreach ($sesSeances->groupBy('horaire_id') as $duCreneau) {
                $this->assertLessThanOrEqual(2, $duCreneau->count());
                if ($duCreneau->count() === 2) {
                    $this->assertTrue($duCreneau->contains(fn (Seance $s) => $s->salle->type !== TypeSalle::Cours));
                }
            }

            // Créneaux utilisés par jour
            $parJour = [];
            foreach ($sesSeances->unique('horaire_id') as $seance) {
                $h = $seance->horaire;
                $parJour[$h->jour->value][] = $this->minutes($h->heure_debut);
            }

            foreach ($parJour as $jour => $debuts) {
                // Midi utilisé → libre de 10 h 05 à 12 h ou de 13 h à 14 h 55
                if (in_array(720, $debuts, true)) {
                    $this->assertFalse(in_array(605, $debuts, true) && in_array(780, $debuts, true), "Règle de midi, {$classe->nom}");
                }
            }

            if ($classe->niveau <= 2) {
                // 1re et 2e année : un bloc de 4 h par jour, 4 matinées et 1 après-midi, pas de samedi
                $this->assertSame([1, 2, 3, 4, 5], collect(array_keys($parJour))->sort()->values()->all());
                $apresMidi = 0;
                $midis = 0;
                foreach ($parJour as $debuts) {
                    sort($debuts);
                    $midis += in_array(720, $debuts, true) ? 1 : 0;
                    $sansMidi = array_values(array_diff($debuts, [720]));
                    $this->assertContains($sansMidi, [[480, 605], [780, 905]], "Bloc de 4 h, {$classe->nom}");
                    $apresMidi += $sansMidi === [780, 905] ? 1 : 0;
                }
                $this->assertSame(1, $apresMidi, "Un après-midi, {$classe->nom}");
                // 2e année : deux matinées jusqu'à 13 h pour atteindre 22 h
                $this->assertSame($classe->niveau === 2 ? 2 : 0, $midis);
            }
        }
    }
}
