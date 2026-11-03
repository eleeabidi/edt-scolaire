<?php

namespace Tests\Feature;

use App\Enums\StatutEmploi;
use App\Models\EmploiDuTemps;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T19.5 — « Mon emploi du temps » : l'enseignant consulte ses séances de l'emploi du temps publié.
 */
class MonEmploiDuTempsTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    private EmploiDuTemps $emploi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29'); // un jeudi, année scolaire 2026-2027
        $this->seed(DatabaseSeeder::class);
        $this->directeur = User::where('email', 'directeur@edt-scolaire.test')->firstOrFail();

        $this->actingAs($this->directeur)
            ->post(route('emploi.generer'), ['annee_scolaire' => '2026-2027'])
            ->assertSessionHasNoErrors();

        $this->emploi = EmploiDuTemps::firstOrFail();
    }

    private function karim(): User
    {
        return User::where('prenom', 'Karim')->firstOrFail();
    }

    public function test_the_teacher_sees_only_his_sessions_of_the_published_timetable()
    {
        $this->emploi->publier();
        $karim = $this->karim();
        $nombre = $this->emploi->seances()->where('enseignant_id', $karim->id)->count();

        $this->actingAs($karim)
            ->get(route('mon-emploi.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('mon-emploi-du-temps/Index')
                ->where('prenom', 'Karim')
                ->where('emploi.ecole', 'École Les Jasmins')
                ->where('emploi.annee', '2026-2027')
                ->has('seances', $nombre)
                ->where('seances', fn ($seances) => collect($seances)->every(fn ($s) => $s['enseignant'] === 'Karim Jaziri'))
                ->where('seances', fn ($seances) => collect($seances)->every(fn ($s) => $s['classe'] !== '' && $s['salle'] !== '' && count($s['matieres']) > 0)));
    }

    public function test_the_week_view_is_shown_first_and_the_day_view_can_be_chosen()
    {
        $this->emploi->publier();

        $this->actingAs($this->karim())->get(route('mon-emploi.index'))
            ->assertInertia(fn (Assert $page) => $page->where('vue', 'semaine')->where('jour', 4)); // jeudi

        $this->actingAs($this->karim())->get(route('mon-emploi.index', ['vue' => 'jour', 'jour' => 3]))
            ->assertInertia(fn (Assert $page) => $page->where('vue', 'jour')->where('jour', 3));

        // Le dimanche, la vue jour s'ouvre sur le lundi
        Carbon::setTestNow('2026-11-01');
        $this->actingAs($this->karim())->get(route('mon-emploi.index', ['vue' => 'jour']))
            ->assertInertia(fn (Assert $page) => $page->where('jour', 1));
    }

    public function test_a_draft_timetable_is_not_shown()
    {
        $this->assertSame(StatutEmploi::Brouillon, $this->emploi->statut);

        $this->actingAs($this->karim())->get(route('mon-emploi.index'))
            ->assertInertia(fn (Assert $page) => $page->where('emploi', null)->missing('seances'));
    }

    public function test_no_timetable_published_for_the_current_school_year()
    {
        // Publié, mais pour l'année suivante : rien pour l'année scolaire en cours
        $this->emploi->update(['annee_scolaire' => '2027-2028']);
        $this->emploi->publier();

        $this->actingAs($this->karim())->get(route('mon-emploi.index'))
            ->assertInertia(fn (Assert $page) => $page->where('emploi', null));
    }

    public function test_a_teacher_of_another_school_sees_nothing()
    {
        $this->emploi->publier();

        $this->actingAs(User::factory()->enseignant()->create())->get(route('mon-emploi.index'))
            ->assertInertia(fn (Assert $page) => $page->where('emploi', null));
    }

    public function test_a_teacher_without_sessions()
    {
        $this->emploi->publier();
        $nouveau = User::factory()->enseignant($this->directeur->ecole)->create();

        $this->actingAs($nouveau)->get(route('mon-emploi.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('emploi.annee', '2026-2027')
                ->has('seances', 0));
    }
}
