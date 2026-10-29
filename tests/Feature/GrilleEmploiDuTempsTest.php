<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Ecole;
use App\Models\Salle;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T15.4 — Consulter l'emploi du temps : grille de la semaine par classe, enseignant ou salle.
 */
class GrilleEmploiDuTempsTest extends TestCase
{
    use RefreshDatabase;

    private Ecole $ecole;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29');
        $this->seed(DatabaseSeeder::class);
        $this->ecole = Ecole::where('nom', 'École Les Jasmins')->firstOrFail();
        $this->directeur = User::where('email', 'directeur@edt-scolaire.test')->firstOrFail();

        $this->actingAs($this->directeur)
            ->post(route('emploi.generer'), ['annee_scolaire' => '2026-2027'])
            ->assertSessionHasNoErrors();
    }

    private function grille(array $filtres = [])
    {
        return $this->actingAs($this->directeur)->get(route('emploi.index', ['annee' => '2026-2027', ...$filtres]));
    }

    public function test_by_default_the_grid_shows_the_first_class()
    {
        $classe = Classe::where('nom', '1re année A')->firstOrFail();

        $this->grille()->assertInertia(fn (Assert $page) => $page
            ->component('emploi-du-temps/Index')
            ->where('emploi.statutLibelle', 'Brouillon')
            ->where('par', 'classe')
            ->where('choix', $classe->id)
            ->has('options.classe', 8)
            ->has('options.salle', 10)
            ->has('lignes', 5)
            ->where('lignes.2', ['debut' => '12:00', 'fin' => '13:00', 'midi' => true])
            ->has('cases', 27) // 5 jours × 5 créneaux + samedi matin (2 créneaux), sans le samedi de 12 h à 13 h
            ->where('totalMinutes', 1200)
            ->has('seances.0', fn (Assert $seance) => $seance
                ->hasAll(['id', 'case', 'jour', 'debut', 'fin', 'classe', 'enseignant', 'salle', 'typeSalle', 'minutes', 'matieres'])
                ->where('classe', '1re année A')
                ->etc()));
    }

    public function test_the_grid_can_show_a_teacher()
    {
        $youssef = User::where('prenom', 'Youssef')->firstOrFail();

        $this->grille(['par' => 'enseignant', 'id' => $youssef->id])->assertInertia(fn (Assert $page) => $page
            ->where('par', 'enseignant')
            ->where('choix', $youssef->id)
            ->has('seances', 8) // l'éducation physique de 8 classes
            ->where('totalMinutes', 480)
            ->where('seances', fn ($seances) => collect($seances)->every(fn ($s) => $s['enseignant'] === 'Youssef Khelifi'
                && $s['typeSalle'] === 'sport'
                && $s['matieres'][0]['nom'] === 'Éducation physique')));
    }

    public function test_the_grid_can_show_a_room()
    {
        $sport = Salle::where('nom', 'Salle de sport')->firstOrFail();

        $this->grille(['par' => 'salle', 'id' => $sport->id])->assertInertia(fn (Assert $page) => $page
            ->where('par', 'salle')
            ->has('seances', 8)
            ->where('seances', fn ($seances) => collect($seances)->every(fn ($s) => $s['salle'] === 'Salle de sport')));
    }

    public function test_a_class_without_session_shows_an_empty_grid()
    {
        $nouvelle = $this->ecole->classes()->create(['nom' => '6e année B', 'niveau' => 6, 'effectif' => 25]);

        $this->grille(['par' => 'classe', 'id' => $nouvelle->id])->assertInertia(fn (Assert $page) => $page
            ->where('choix', $nouvelle->id)
            ->has('seances', 0)
            ->where('totalMinutes', 0));
    }

    public function test_an_unknown_filter_or_a_class_of_another_school_falls_back_to_the_first_class()
    {
        $autre = Classe::factory()->create();
        $premiere = Classe::where('nom', '1re année A')->value('id');

        $this->grille(['par' => 'inconnu', 'id' => $autre->id])->assertInertia(fn (Assert $page) => $page
            ->where('par', 'classe')
            ->where('choix', $premiere));

        $this->grille(['par' => 'classe', 'id' => $autre->id])->assertInertia(fn (Assert $page) => $page->where('choix', $premiere));
    }

    public function test_the_other_school_year_has_no_timetable_yet()
    {
        $this->grille(['annee' => '2027-2028'])->assertInertia(fn (Assert $page) => $page
            ->where('annee', '2027-2028')
            ->where('emploi', null)
            ->missing('seances'));
    }
}
