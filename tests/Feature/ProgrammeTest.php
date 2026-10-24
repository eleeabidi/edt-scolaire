<?php

namespace Tests\Feature;

use App\Enums\TypeSalle;
use App\Models\Matiere;
use App\Models\NiveauMatiere;
use App\Models\User;
use Database\Seeders\ProgrammeNationalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T08.7 — Chargement et consultation du guide pédagogique.
 */
class ProgrammeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProgrammeNationalSeeder::class);
    }

    public function test_the_national_tables_have_no_school_and_no_session_duration()
    {
        $this->assertFalse(Schema::hasColumn('matieres', 'ecole_id'));
        $this->assertFalse(Schema::hasColumn('matieres', 'duree_seance'));
        $this->assertTrue(Schema::hasColumns('matieres', ['nom', 'type_salle', 'autorisee_midi']));
        $this->assertTrue(Schema::hasColumns('niveau_matiere', ['niveau', 'matiere_id', 'minutes_par_semaine']));
    }

    public function test_the_weekly_totals_match_the_official_totals()
    {
        // 1re 20 h, 2e 22 h, 3e 25 h, 4e 25 h, 5e 28 h, 6e 28 h
        foreach ([1 => 1200, 2 => 1320, 3 => 1500, 4 => 1500, 5 => 1680, 6 => 1680] as $niveau => $minutes) {
            $this->assertSame($minutes, (int) NiveauMatiere::where('niveau', $niveau)->sum('minutes_par_semaine'), "Niveau {$niveau}");
        }
    }

    public function test_every_subject_lasts_at_least_30_minutes_a_week()
    {
        $this->assertSame(0, NiveauMatiere::where('minutes_par_semaine', '<', 30)->count());
    }

    public function test_only_physical_education_needs_a_sport_room()
    {
        $this->assertSame(['Éducation physique'], Matiere::where('type_salle', TypeSalle::Sport)->pluck('nom')->all());
    }

    public function test_technology_needs_a_technology_room_and_the_others_a_classroom()
    {
        $this->assertSame(['Éducation technologique'], Matiere::where('type_salle', TypeSalle::Technologie)->pluck('nom')->all());
        $this->assertSame(11, Matiere::where('type_salle', TypeSalle::Cours)->count());
        $this->assertSame(0, Matiere::whereNotIn('type_salle', ['cours', 'sport', 'technologie'])->count());
    }

    public function test_subjects_allowed_at_noon()
    {
        $this->assertEqualsCanonicalizing(
            ['Éducation physique', 'Éducation technologique', 'Éducation musicale', 'Éducation artistique'],
            Matiere::where('autorisee_midi', true)->pluck('nom')->all(),
        );
    }

    public function test_the_volumes_are_those_of_the_decree()
    {
        $minutes = fn (string $matiere, int $niveau) => NiveauMatiere::where('niveau', $niveau)
            ->whereHas('matiere', fn ($q) => $q->where('nom', $matiere))
            ->value('minutes_par_semaine');

        $this->assertSame(13, Matiere::count());
        $this->assertSame(540, $minutes('Langue arabe', 1));
        $this->assertSame(360, $minutes('Langue arabe', 4));
        $this->assertSame(120, $minutes('Français', 2));
        $this->assertSame(480, $minutes('Français', 3));
        $this->assertSame(120, $minutes('Anglais', 6));
        $this->assertSame(30, $minutes('Éducation musicale', 5));
        $this->assertSame(40, $minutes('Histoire', 6));
    }

    public function test_a_subject_not_taught_at_a_level_has_no_volume_line()
    {
        // « — » dans le tableau : pas de français en 1re, pas d'anglais avant la 4e, pas d'histoire avant la 5e
        $lignes = fn (string $matiere, int $niveau) => NiveauMatiere::where('niveau', $niveau)
            ->whereHas('matiere', fn ($q) => $q->where('nom', $matiere))
            ->count();

        $this->assertSame(0, $lignes('Français', 1));
        $this->assertSame(0, $lignes('Anglais', 3));
        $this->assertSame(0, $lignes('Histoire', 4));
        $this->assertSame(8, NiveauMatiere::where('niveau', 1)->count());
        $this->assertSame(13, NiveauMatiere::where('niveau', 5)->count());
    }

    public function test_subjects_that_are_no_longer_in_the_programme_are_removed()
    {
        // Ex. : une matière des anciennes données d'exemple
        Matiere::create(['nom' => 'Informatique']);

        $this->seed(ProgrammeNationalSeeder::class);

        $this->assertDatabaseMissing('matieres', ['nom' => 'Informatique']);
    }

    public function test_the_seeder_can_be_run_again_without_duplicates()
    {
        $matieres = Matiere::count();
        $volumes = NiveauMatiere::count();

        $this->seed(ProgrammeNationalSeeder::class);

        $this->assertSame($matieres, Matiere::count());
        $this->assertSame($volumes, NiveauMatiere::count());
    }

    public function test_the_directeur_can_consult_the_programme()
    {
        $this->actingAs(User::factory()->directeur()->create())
            ->get(route('programme.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('programme/Index')
                ->has('niveaux', 6)
                ->where('niveaux.0.niveau', 1)
                ->where('niveaux.0.totalMinutes', 1200)
                ->where('niveaux.0.matieres.0.nom', 'Langue arabe')
                ->where('niveaux.0.matieres.0.minutes', 540)
                ->where('niveaux.0.matieres.0.typeSalle', 'cours'));

        $this->assertSame('/guide-pedagogique', route('programme.index', absolute: false));
    }

    public function test_only_the_directeur_can_consult_the_programme()
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('programme.index'))->assertForbidden();
        $this->actingAs(User::factory()->enseignant()->create())->get(route('programme.index'))->assertForbidden();
    }
}
